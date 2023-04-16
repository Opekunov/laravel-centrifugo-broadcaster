<?php

declare(strict_types=1);

namespace Opekunov\Centrifugo;

use Carbon\Carbon;
use Exception;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Opekunov\Centrifugo\Contracts\CentrifugoInterface;
use Opekunov\Centrifugo\Exceptions\CentrifugoConnectionException;
use Opekunov\Centrifugo\Exceptions\CentrifugoException;

class CentrifugoBroadcaster extends Broadcaster
{
    /**
     * The Centrifugo SDK instance.
     */
    protected CentrifugoInterface $centrifugo;

    /**
     * Create a new broadcaster instance.
     *
     * @param Centrifugo $centrifugo
     */
    public function __construct(Centrifugo $centrifugo)
    {
        $this->centrifugo = $centrifugo;
    }

    /**
     * Authenticate the incoming request for a given channel.
     *
     * @param Request $request
     *
     * @return Application|ResponseFactory|Response
     */
    public function auth($request): Response|Application|ResponseFactory
    {
        $client = $request->user() ? $request->user()->id : '';
        $channel = $request->get('channel');
        $this->verifyUserCanAccessChannel($request, $channel);

        return response($this->makeResponseForClient($channel, (string) $client));
    }

    /**
     * Return the valid authentication response.
     *
     * @param Request $request
     * @param mixed   $result
     *
     * @return mixed
     */
    public function validAuthenticationResponse($request, $result): mixed
    {
        return $result;
    }

    /**
     * Broadcast the given event.
     *
     * @param array  $channels
     * @param string $event
     * @param array  $payload
     *
     * @throws CentrifugoConnectionException
     * @throws CentrifugoException
     *
     * @return void
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        $payload['event'] = $event;
        $channels = array_map(function ($channel) {
            return str_replace('private-', '', (string) $channel);
        }, $channels);

        $response = $this->centrifugo->broadcast($this->formatChannels($channels), $payload);

        if (!isset($response['error'])) {
            return;
        }

        throw new BroadcastException(
            $response['error'] instanceof Exception ? $response['error']->getMessage() : $response['error']['message'],
            $response['error'] instanceof Exception ? $response['error']->getCode() : $response['error']['code'],
        );
    }

    /**
     * Make response for client, based on access rights of private channel.
     *
     * @param string $channel
     * @param string $userId
     *
     * @return array
     */
    private function makeResponseForClient(string $channel, string $userId): array
    {
        if ($this->centrifugo->showNodeInfo()) {
            try {
                $info = $this->centrifugo->info();
            } catch (CentrifugoException $e) {
                $info = ['error' => $e->getMessage()];
            }
        }

        return [
            'channel'   => $channel,
            'token'     => $this->centrifugo->generateSubscriptionToken(
                $userId,
                $channel,
                Carbon::now()->addSeconds($this->centrifugo->getDefaultTokenExpiration())
            ),
            'node_info' => $info ?? null,
        ];
    }
}
