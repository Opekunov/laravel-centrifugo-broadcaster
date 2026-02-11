<?php

declare(strict_types=1);

namespace Opekunov\Centrifugo;

use Carbon\Carbon;
use Illuminate\Contracts\Container\BindingResolutionException;
use Opekunov\Centrifugo\Contracts\CentrifugoInterface;
use Opekunov\Centrifugo\Exceptions\CentrifugoConnectionException;
use Opekunov\Centrifugo\Exceptions\CentrifugoException;
use Opekunov\Centrifugo\Http\HttpClient;
use Opekunov\Centrifugo\Http\HttpResponse;

class Centrifugo implements CentrifugoInterface
{
    const API_PATH = '/api';

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
    public function __construct(?array $config = null, ?HttpClient $httpClient = null)
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
        // Ensure empty arrays become empty objects in JSON
        $json = json_encode($params ?: (object)[]);

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
            'X-API-Key' => $this->config['apikey'],
        ];

        try {
            $url = $this->prepareUrl($method);
            
            $options = [
                'headers' => $headers,
                'body' => $json,
                'timeout' => $this->config['timeout'],
                'tries' => intval($this->config['tries'] ?? 1),
                'url' => $url,
            ];

            $parsedUrl = parse_url($url);
            if (($parsedUrl['scheme'] ?? '') === 'https') {
                $options['verify_ssl'] = $this->config['verify'] ?? true;
                if (!empty($this->config['ssl_key'])) {
                    $options['ssl_cert'] = $this->config['ssl_key'];
                }
            }

            $response = $this->httpClient->post($url, $options);
            
            if (!$response->isSuccessful()) {
                $result = [
                    'method' => $method,
                    'error'  => [
                        'message' => 'HTTP ' . $response->getStatusCode(),
                        'code'    => $response->getStatusCode(),
                    ],
                    'body'   => $json,
                ];
            } else {
                $result = json_decode($response->getBody(), true);
            }
        } catch (CentrifugoConnectionException $e) {
            throw $e;
        } catch (CentrifugoException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new CentrifugoException($e->getMessage(), 0, $e);
        }

        return $result ?? [];
    }

    /**
     * Prepare URL to send the http request.
     *
     * @param string|null $method API method name for v5+ format
     *
     * @return string
     */
    protected function prepareUrl(?string $method = null): string
    {
        $address = rtrim($this->config['url'], '/');
        $apiPath = $this->config['api_path'] ?? self::API_PATH;

        if (substr_compare($address, $apiPath, -strlen($apiPath)) !== 0) {
            $address .= $apiPath;
        }

        // Use new v5+ API format: /api/{method}
        if ($method !== null) {
            $address .= '/' . $method;
        }

        return $address;
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
     * Uses the new batch API format for v5+.
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
        // Use new batch format for v5+
        $commands = [];
        foreach ($params as $param) {
            $commands[] = [$method => $param];
        }
        
        $json = json_encode(['commands' => $commands]);

        return $this->sendData('batch', $json);
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
     * @param string      $channel
     * @param int         $limit   Limit number of returned publications (0 = no limit)
     * @param int|null    $offset  Stream position offset for pagination
     * @param string|null $epoch   Stream position epoch for pagination
     * @param bool        $reverse Return publications in reverse order
     *
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return array
     */
    public function history(
        string $channel,
        int $limit = 0,
        ?int $offset = null,
        ?string $epoch = null,
        bool $reverse = false
    ): array {
        $params = ['channel' => $channel, 'limit' => $limit, 'reverse' => $reverse];

        if ($offset !== null || $epoch !== null) {
            $params['since'] = [
                'offset' => $offset,
                'epoch'  => $epoch,
            ];
        }

        return $this->send('history', $params);
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
     * Subscribe user to a channel (server-side).
     *
     * @param string $channel
     * @param string $user
     * @param array  $info    Custom data to attach to subscription
     * @param array  $data    Custom subscription data sent to client
     *
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return array
     */
    public function subscribe(string $channel, string $user, array $info = [], array $data = []): array
    {
        $params = [
            'channel' => $channel,
            'user'    => $user,
        ];

        if (!empty($info)) {
            $params['info'] = $info;
        }
        if (!empty($data)) {
            $params['data'] = $data;
        }

        return $this->send('subscribe', $params);
    }

    /**
     * Remote procedure call.
     *
     * @param string $method RPC method name
     * @param array  $data   RPC data
     *
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return array
     */
    public function rpc(string $method, array $data = []): array
    {
        return $this->send('rpc', ['method' => $method, 'data' => $data]);
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
            $payload['override'] = $override;
        }

        return $this->createJWTToken($payload);
    }

    /**
     * Generate user CONNECTION token.
     *
     * @param int|string $userId    Required. This is a standard JWT claim which must contain an ID of the current application user (as string)
     * @param int|Carbon $exp       Optional. Token expiration time. Carbon time or UNIX
     * @param array      $info
     * @param array      $channels  Optional. The channels to automatically subscribe to when connecting
     *
     * @return string
     */
    public function generateConnectionToken(int|string $userId, int|Carbon $exp = 0, array $info = [], array $channels = []): string
    {
        $payload = [
            'sub' => (string) $userId,
            'exp' => gettype($exp) !== 'integer' ? $exp->unix() : $exp,
        ];

        if (!empty($info)) {
            $payload['info'] = $info;
        }

        if (!empty($channels)) {
            $payload['channels'] = $channels;
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
