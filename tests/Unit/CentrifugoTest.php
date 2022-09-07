<?php

namespace Opekunov\Centrifugo\Tests\Unit;

use Opekunov\Centrifugo\Centrifugo;
use Opekunov\Centrifugo\Exceptions\CentrifugoConnectionException;
use Opekunov\Centrifugo\Tests\TestCase;

class CentrifugoTest extends TestCase
{
    public function testGenerateToken()
    {
        $timestamp = 1491650279;
        $info = [
            'first_name' => 'Aleksandr',
            'last_name'  => 'Opekunov',
        ];
        $clientId = '0c951315-be0e-4516-b99e-05e60b0cc317';

        $clientToken1 = $this->centrifuge->generateConnectionToken($clientId, $timestamp);
        $this->assertEquals(
            'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIwYzk1MTMxNS1iZTBlLTQ1MTYtYjk5ZS0wNWU2MGIwY2MzMTciLCJleHAiOjE0OTE2NTAyNzl9.jue8OmcTwCwyDtV-eEz5HTudI8G7kr7cK2lAoi_hMyE',
            $clientToken1
        );

        $clientToken2 = $this->centrifuge->generateSubscriptionToken($clientId, 'test', $timestamp, $info);
        $this->assertEquals(
            'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIwYzk1MTMxNS1iZTBlLTQ1MTYtYjk5ZS0wNWU2MGIwY2MzMTciLCJleHAiOjE0OTE2NTAyNzksImNoYW5uZWwiOiJ0ZXN0IiwiaW5mbyI6eyJmaXJzdF9uYW1lIjoiQWxla3NhbmRyIiwibGFzdF9uYW1lIjoiT3Bla3Vub3YifX0.fagXHy6MAaziGVGwgtWHWqMEGGvbg5ldHpA2dB5Nc7M',
            $clientToken2
        );
    }

    public function testCentrifugoApiPublish()
    {
        $publish = $this->centrifuge->publish('test-test', ['event' => 'test-event']);
        $this->assertEquals(['result' => []], $publish);
    }

    public function testCentrifugoApiPublishMany()
    {
        $publish = $this->centrifuge->publishMany(
            [['channel' => 'test-channel:1', 'data' => 'Hello'], ['channel' => 'test-channel:2', 'data' => 'World']]
        );
        $this->assertEquals([], $publish);
    }

    public function testCentrifugoApiBroadcast()
    {
        $broadcast = $this->centrifuge->broadcast(['test-channel-1', 'test-channel-2'], ['event' => 'test-event']);
        $this->assertEquals([
            'result' => [
                'responses' => [
                    ['result' => []],
                    ['result' => []],
                ],
            ],
        ], $broadcast);
    }

    public function testCentrifugoApiPresence()
    {
        $presence = $this->centrifuge->presence('test-channel');
        $this->assertEquals(['result' => ['presence' => []]], $presence);
    }

    public function testCentrifugoApiHistory()
    {
        $history = $this->centrifuge->history('test-channel');
        $this->assertIsArray($history['error']);
        $this->assertEquals('not available', $history['error']['message']);
        $this->assertEquals(108, $history['error']['code']);
    }

    public function testCentrifugoApiChannels()
    {
        $channels = $this->centrifuge->channels();
        $this->assertArrayHasKey('channels', $channels['result']);
    }

    public function testCentrifugoApiUnsubscribe()
    {
        $unsubscribe = $this->centrifuge->unsubscribe('test-channel', '1');
        $this->assertEquals([], $unsubscribe);
    }

    public function testCentrifugoApiSubscribe()
    {
        $subscribe = $this->centrifuge->unsubscribe('test-channel', '1');
        $this->assertEquals([], $subscribe);
    }

    public function testCentrifugoApiStats()
    {
        $stats = $this->centrifuge->presenceStats('test-channel');
        $this->assertEquals([
            'result' => [
                'num_clients' => 0,
                'num_users'   => 0,
            ],
        ], $stats);
    }

    public function testTimeoutFunction()
    {
        $timeout = 3;
        $delta = 0.5;

        $badCentrifugo = new Centrifugo(
            [
                'driver'   => 'centrifugo',
                'secret'   => 'd55bf295-bee6-4259-8912-0a58f44ed30e',
                'apikey'   => '0c951315-be0e-4516-b99e-05e60b0cc307_',
                'api_path' => '',
                'url'      => 'https://httpstat.us/200?sleep=20000',
                'timeout'  => $timeout,
                'tries'    => 1,
            ]
        );

        $start = microtime(true);
        $this->expectException(CentrifugoConnectionException::class);

        try {
            $badCentrifugo->publish('test-channel', ['event' => 'test-event']);
        } catch (\Exception $e) {
            $end = microtime(true);
            $eval = $end - $start;
            $this->assertTrue($eval < $timeout + $delta);

            throw $e;
        }
    }

    public function testTriesFunction()
    {
        $timeout = 1;
        $tries = 3;
        $delta = 0.5;

        $badCentrifugo = new Centrifugo(
            [
                'driver'   => 'centrifugo',
                'secret'   => 'd55bf295-bee6-4259-8912-0a58f44ed30e',
                'apikey'   => '0c951315-be0e-4516-b99e-05e60b0cc307_',
                'api_path' => '',
                'url'      => 'https://httpstat.us/200?sleep=20000',
                'timeout'  => $timeout,
                'tries'    => $tries,
            ]
        );

        $start = microtime(true);
        $this->expectException(CentrifugoConnectionException::class);

        try {
            $badCentrifugo->publish('test-channel', ['event' => 'test-event']);
        } catch (\Exception $e) {
            $end = microtime(true);
            $eval = $end - $start;
            $this->assertTrue($eval < ($timeout + $delta) * $tries);

            throw $e;
        }
    }
}
