<?php

declare(strict_types=1);

namespace Opekunov\Centrifugo;

use Carbon\Carbon;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Opekunov\Centrifugo\Contracts\CentrifugoInterface;
use Opekunov\Centrifugo\Exceptions\CentrifugoConnectionException;
use Opekunov\Centrifugo\Exceptions\CentrifugoException;
use Psr\Http\Message\ResponseInterface;

class Centrifugo implements CentrifugoInterface
{
    const API_PATH = '/api';

    /**
     * @var HttpClient
     */
    protected HttpClient $httpClient;

    /**
     * @var array
     */
    protected array $config;

    /**
     * Create a new Centrifugo instance.
     *
     * @param array|null      $config
     * @param HttpClient|null $httpClient
     *
     * @throws BindingResolutionException
     */
    public function __construct(array $config = null, HttpClient $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new HttpClient();
        if (!$config) {
            $this->config = app()->make('config')->get('broadcasting.connections.centrifugo');
        } else {
            $this->config = $this->initConfiguration($config) ??
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
    protected function initConfiguration(array $config): array
    {
        $defaults = [
            'url'               => 'http://127.0.0.1:8001',
            'api_path'          => '/api',
            'secret'            => null,
            'apikey'            => null,
            'ssl_key'           => null,
            'verify'            => true,
            'token_expire_time' => 300,
            'show_node_info'    => false,
            'timeout'           => 3,
            'tries'             => 1,
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
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return array
     */
    public function publish(string $channel, array $data): array
    {
        return $this->send('publish', [
            'channel' => $channel,
            'data'    => $data,
        ]);
    }

    /**
     * Send message to centrifugo server.
     *
     * @param string $method
     * @param array  $params
     *
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return array
     */
    protected function send(string $method, array $params = []): array
    {
        $json = json_encode(['method' => $method, 'params' => $params]);

        return $this->sendData($method, $json);
    }

    /**
     * Send json data to centrifugo server.
     *
     * @param string $method
     * @param string $json
     *
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return array
     */
    protected function sendData(string $method, string $json): array
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

            $tries = intval($this->config['tries'] ?? 1);

            $response = $this->postRequest($this->prepareUrl(), $config->toArray(), $tries);

            $result = json_decode((string) $response->getBody(), true);
        } catch (ClientException $e) {
            $result = [
                'method' => $method,
                'error'  => [
                    'message' => $e->getMessage(),
                    'code'    => $e->getCode(),
                ],
                'body'   => $json,
            ];
        } catch (ConnectException $e) {
            throw new CentrifugoConnectionException($e->getMessage());
        } catch (GuzzleException $e) {
            throw new CentrifugoException($e->getMessage());
        }

        return $result ?? [];
    }

    /**
     * Prepare URL to send the http request.
     *
     * @return string
     */
    protected function prepareUrl(): string
    {
        $address = rtrim($this->config['url'], '/');
        $apiPath = $this->config['api_path'] ?? self::API_PATH;

        if (substr_compare($address, $apiPath, -strlen($apiPath)) !== 0) {
            $address .= $apiPath;
        }

        return $address;
    }

    /**
     * Send request to centrifugo API.
     *
     * @param string $url
     * @param array  $configs
     * @param int    $tries
     * @param int    $retriesCounter
     *
     * @throws GuzzleException
     *
     * @return ResponseInterface
     */
    private function postRequest(string $url, array $configs, int $tries = 1, int $retriesCounter = 0): ResponseInterface
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
     * Send multiple message into multiple channel.
     *
     * @param array $params Example: [ ['channel' => 'channel:1', 'data' => 'Hello'],
     *                      ['channel' => 'channel:2', 'data' => 'World']]
     *
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return array
     */
    public function publishMany(array $params): array
    {
        return $this->sendMany('publish', $params);
    }

    /**
     * Send many messages per one request to centrifugo server.
     *
     * @param       $method
     * @param array $params
     *
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return array
     */
    protected function sendMany($method, array $params = []): array
    {
        $json = '';
        foreach ($params as $param) {
            $json .= json_encode(['method' => $method, 'params' => $param])."\r\n";
        }
        $json = trim($json);

        return $this->sendData($method, $json);
    }

    /**
     * Send message into multiple channel.
     *
     * @param array $channels
     * @param array $data
     *
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return array
     */
    public function broadcast(array $channels, array $data): array
    {
        $params = ['channels' => $channels, 'data' => $data];

        return $this->send('broadcast', $params);
    }

    /**
     * Get channel presence information (all clients currently subscribed on this channel).
     *
     * @param string $channel
     *
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return array
     */
    public function presence(string $channel): array
    {
        return $this->send('presence', ['channel' => $channel]);
    }

    /**
     * Get channel presence information in short form.
     *
     * @param string $channel
     *
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return array
     */
    public function presenceStats(string $channel): array
    {
        return $this->send('presence_stats', ['channel' => $channel]);
    }

    /**
     * Get channel history information (list of last messages sent into channel).
     *
     * @param string $channel
     *
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return array
     */
    public function history(string $channel): array
    {
        return $this->send('history', ['channel' => $channel]);
    }

    /**
     * Remove channel history information.
     *
     * @param string $channel
     *
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return array
     */
    public function historyRemove(string $channel): array
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
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return array
     */
    public function unsubscribe(string $channel, string $user): array
    {
        return $this->send('unsubscribe', [
            'channel' => $channel,
            'user'    => $user,
        ]);
    }

    /**
     * Disconnect user by its ID.
     *
     * @param string $userId
     *
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return array
     */
    public function disconnect(string $userId): array
    {
        return $this->send('disconnect', ['user' => (string) $userId]);
    }

    /**
     * Get channels information (list of currently active channels).
     *
     * @param string $pattern Pattern to filter channels
     *
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return array
     */
    public function channels(string $pattern = ''): array
    {
        return $this->send('channels', ['pattern' => $pattern]);
    }

    /**
     * Get stats information about running server nodes.
     *
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return array
     */
    public function info(): array
    {
        return $this->send('info');
    }

    /**
     * Generate user channel SUBSCRIPTION token.
     *
     * @param string|int $userId   Required. This is a standard JWT claim which must contain an ID of the current application user (as string).
     * @param string     $channel  Required. Channel that client tries to subscribe to with this token (string)
     * @param int|Carbon $exp      Optional. Token expiration time. Carbon time or UNIX
     * @param array      $info     Additional information for connection inside this channel.
     * @param array      $override This is an object which allows overriding channel options.
     *                             https://centrifugal.dev/docs/server/channel_token_auth#override
     *
     * @return string
     *
     * @see https://centrifugal.dev/docs/server/channel_token_auth
     */
    public function generateSubscriptionToken(
        string|int $userId,
        string $channel,
        int|Carbon $exp = 0,
        array $info = [],
        array $override = []
    ): string {
        $payload = [
            'sub' => (string) $userId,
            'exp' => gettype($exp) !== 'integer' ? $exp->unix() : $exp,
        ];

        if ($channel) {
            $payload['channel'] = $channel;
        }
        if (!empty($info)) {
            $payload['info'] = $info;
        }
        if (!empty($override)) {
            $payload['override'] = $info;
        }

        return $this->createJWTToken($payload);
    }

    /**
     * Generate user CONNECTION token.
     *
     * @param int|string $userId Required. This is a standard JWT claim which must contain an ID of the current application user (as string)
     * @param int|Carbon $exp    Optional. Token expiration time. Carbon time or UNIX
     * @param array      $info
     *
     * @return string
     */
    public function generateConnectionToken(int|string $userId, int|Carbon $exp = 0, array $info = []): string
    {
        $payload = [
            'sub' => (string) $userId,
            'exp' => gettype($exp) !== 'integer' ? $exp->unix() : $exp,
        ];

        if (!empty($info)) {
            $payload['info'] = $info;
        }

        return $this->createJWTToken($payload);
    }

    /**
     * Create JWT Token from payload.
     *
     * @param array $payload
     *
     * @return string
     */
    private function createJWTToken(array $payload): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];

        $segments = [
            $this->urlSafeB64Encode(json_encode($header)),
            $this->urlSafeB64Encode(json_encode($payload)),
        ];

        $signature = $this->sign(implode('.', $segments), $this->getSecret());
        $segments[] = $this->urlSafeB64Encode($signature);

        return implode('.', $segments);
    }

    /**
     * Safely encode string in base64.
     *
     * @param string $input
     *
     * @return string
     */
    private function urlSafeB64Encode(string $input): string
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
    private function sign(string $msg, string $key): string
    {
        return hash_hmac('sha256', $msg, $key, true);
    }

    /**
     * Get secret key.
     *
     * @return string
     */
    protected function getSecret(): string
    {
        return $this->config['secret'];
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

    public function getDefaultTokenExpiration()
    {
        return $this->config['token_expire_time'];
    }
}
