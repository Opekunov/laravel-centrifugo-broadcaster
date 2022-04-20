<?php

declare(strict_types=1);

namespace Opekunov\Centrifugo;

use Carbon\Carbon;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use Opekunov\Centrifugo\Contracts\CentrifugoInterface;

class Centrifugo implements CentrifugoInterface
{
    const API_PATH = '/api';

    /**
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * @var array
     */
    protected $config;

    /**
     * Create a new Centrifugo instance.
     *
     * @param array              $config
     * @param \GuzzleHttp\Client $httpClient
     */
    public function __construct(array $config = null, HttpClient $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new HttpClient();
        if (!$config) {
            $this->config = app()->make('config')->get('broadcasting.connections.centrifugo');
        } else {
            $this->config = $this->initConfiguration($config ?? []) ??
                app()->make('config')->get('broadcasting.connections.centrifugo');
        }
    }

    /**
     * Init centrifugo configuration.
     *
     * @param array $config
     *
     * @return array
     */
    protected function initConfiguration(array $config)
    {
        $defaults = [
            'url'            => 'http://localhost:8000',
            'api_path'       => '/api',
            'secret'         => null,
            'apikey'         => null,
            'ssl_key'        => null,
            'verify'         => true,
            'show_node_info' => false,
            'timeout'        => 3,
            'tries'          => 1,
        ];

        foreach ($config as $key => $value) {
            if (array_key_exists($key, $defaults)) {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }

    /**
     * Send message into channel.
     *
     * @param string $channel
     * @param array  $data
     *
     * @return mixed
     */
    public function publish(string $channel, array $data)
    {
        return $this->send('publish', [
            'channel' => $channel,
            'data'    => $data,
        ]);
    }

    /**
     * Send multiple message into multiple channel.
     *
     * @param array $params Example: [ ['channel' => 'channel:1', 'data' => 'Hello'],
     *                      ['channel' => 'channel:2', 'data' => 'World']]
     *
     * @return array|mixed
     */
    public function publishMany(array $params)
    {
        return $this->sendMany('publish', $params);
    }

    /**
     * Send message into multiple channel.
     *
     * @param array $channels
     * @param array $data
     *
     * @return mixed
     */
    public function broadcast(array $channels, array $data)
    {
        $params = ['channels' => $channels, 'data' => $data];

        return $this->send('broadcast', $params);
    }

    /**
     * Get channel presence information (all clients currently subscribed on this channel).
     *
     * @param string $channel
     *
     * @return mixed
     */
    public function presence(string $channel)
    {
        return $this->send('presence', ['channel' => $channel]);
    }

    /**
     * Get channel presence information in short form.
     *
     * @param string $channel
     *
     * @return mixed
     */
    public function presenceStats(string $channel)
    {
        return $this->send('presence_stats', ['channel' => $channel]);
    }

    /**
     * Get channel history information (list of last messages sent into channel).
     *
     * @param string $channel
     *
     * @return mixed
     */
    public function history(string $channel)
    {
        return $this->send('history', ['channel' => $channel]);
    }

    /**
     * Remove channel history information.
     *
     * @param string $channel
     *
     * @return mixed
     */
    public function historyRemove(string $channel)
    {
        return $this->send('history_remove', [
            'channel' => $channel,
        ]);
    }

    /**
     * Unsubscribe user from channel.
     *
     * @param string $channel
     * @param string $user
     *
     * @return mixed
     */
    public function unsubscribe(string $channel, string $user)
    {
        return $this->send('unsubscribe', [
            'channel' => $channel,
            'user'    => $user,
        ]);
    }

    /**
     * Disconnect user by its ID.
     *
     * @param string $user_id
     *
     * @return mixed
     */
    public function disconnect(string $user_id)
    {
        return $this->send('disconnect', ['user' => (string) $user_id]);
    }

    /**
     * Get channels information (list of currently active channels).
     *
     * @param string $pattern Pattern to filter channels
     *
     * @return mixed
     */
    public function channels(string $pattern = '')
    {
        return $this->send('channels', ['pattern' => $pattern]);
    }

    /**
     * Get stats information about running server nodes.
     *
     * @return mixed
     */
    public function info()
    {
        return $this->send('info');
    }

    /**
     * Generate connection token.
     *
     * @param string     $userId
     * @param int|Carbon $exp
     * @param array      $info
     *
     * @return string
     */
    public function generateConnectionToken(string $userId = '', $exp = 0, array $info = []): string
    {
        if (gettype($exp) !== 'integer') {
            $exp = $exp->unix();
        }
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $payload = ['sub' => $userId];
        if (!empty($info)) {
            $payload['info'] = $info;
        }
        if ($exp) {
            $payload['exp'] = $exp;
        }
        $segments = [];
        $segments[] = $this->urlsafeB64Encode(json_encode($header));
        $segments[] = $this->urlsafeB64Encode(json_encode($payload));
        $signing_input = implode('.', $segments);
        $signature = $this->sign($signing_input, $this->getSecret());
        $segments[] = $this->urlsafeB64Encode($signature);

        return implode('.', $segments);
    }

    /**
     * Generate private channel token.
     *
     * @param string     $client
     * @param string     $channel
     * @param int|Carbon $exp
     * @param array      $info
     *
     * @return string
     */
    public function generatePrivateChannelToken(string $client, string $channel, $exp = 0, array $info = []): string
    {
        if (gettype($exp) !== 'integer') {
            $exp = $exp->unix();
        }
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $payload = ['channel' => $channel, 'client' => $client];
        if (!empty($info)) {
            $payload['info'] = $info;
        }
        if ($exp) {
            $payload['exp'] = $exp;
        }
        $segments = [];
        $segments[] = $this->urlsafeB64Encode(json_encode($header));
        $segments[] = $this->urlsafeB64Encode(json_encode($payload));
        $signing_input = implode('.', $segments);
        $signature = $this->sign($signing_input, $this->getSecret());
        $segments[] = $this->urlsafeB64Encode($signature);

        return implode('.', $segments);
    }

    /**
     * Get secret key.
     *
     * @return string
     */
    protected function getSecret()
    {
        return $this->config['secret'];
    }

    /**
     * Send message to centrifugo server.
     *
     * @param string $method
     * @param array  $params
     *
     * @return mixed
     */
    protected function send($method, array $params = [])
    {
        $json = json_encode(['method' => $method, 'params' => $params]);

        return $this->sendData($method, $json);
    }

    /**
     * Send many messages per one request to centrifugo server.
     *
     * @param       $method
     * @param array $params
     *
     * @return array|mixed
     */
    protected function sendMany($method, array $params = [])
    {
        $json = '';
        foreach ($params as $param) {
            $json .= json_encode(['method' => $method, 'params' => $param])."\r\n";
        }
        $json = trim($json);

        return $this->sendData($method, $json);
    }

    /**
     * Send json data to centrifugo server.
     *
     * @param string $method
     * @param array  $params
     *
     * @return mixed
     */
    protected function sendData(string $method, string $json)
    {
        $headers = [
            'Content-type'  => 'application/json',
            'Authorization' => 'apikey '.$this->config['apikey'],
        ];

        try {
            $url = parse_url($this->prepareUrl());

            $config = collect([
                'headers'     => $headers,
                'body'        => $json,
                'http_errors' => true,
                'timeout'     => $this->config['timeout'],
            ]);

            if ($url['scheme'] == 'https') {
                $config->put('verify', collect($this->config)->get('verify', false));

                if (collect($this->config)->get('ssl_key')) {
                    $config->put('ssl_key', collect($this->config)->get('ssl_key'));
                }
            }

            $tries = $this->config['tries'] ?? 1;

            $response = $this->postRequest($this->prepareUrl(), $config->toArray(), $tries);

            $result = json_decode((string) $response->getBody(), true);
        } catch (ClientException $e) {
            $result = [
                'method' => $method,
                'error'  => $e->getMessage(),
                'body'   => $json,
            ];
        }

        return $result;
    }

    /**
     * Send request to centrifugo API.
     *
     * @param string $url
     * @param array  $configs
     * @param int    $tries
     * @param int    $retriesCounter
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return mixed
     */
    private function postRequest(string $url, array $configs, int $tries = 1, int $retriesCounter = 0)
    {
        try {
            return $this->httpClient->post($url, $configs);
        } catch (ClientException|TransferException|ConnectException $e) {
            $retriesCounter++;
            if ($retriesCounter < $tries) {
                return $this->postRequest($url, $configs, $tries, $retriesCounter);
            }

            throw $e;
        }
    }

    /**
     * Prepare URL to send the http request.
     *
     * @return string
     */
    protected function prepareUrl()
    {
        $address = rtrim($this->config['url'], '/');
        $apiPath = $this->config['api_path'] ?? self::API_PATH;

        if (substr_compare($address, $apiPath, -strlen($apiPath)) !== 0) {
            $address .= $apiPath;
        }
        //$address .= '/';

        return $address;
    }

    /**
     * Safely encode string in base64.
     *
     * @param string $input
     *
     * @return string
     */
    private function urlsafeB64Encode($input)
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    /**
     * Sign message with secret key.
     *
     * @param string $msg
     * @param string $key
     *
     * @return string
     */
    private function sign($msg, $key)
    {
        return hash_hmac('sha256', $msg, $key, true);
    }

    /**
     * Can show Node info when return auth token.
     *
     * @return bool
     */
    public function showNodeInfo(): bool
    {
        return (bool) $this->config['show_node_info'];
    }
}
