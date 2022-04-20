<?php

namespace Opekunov\Centrifugo\Tests\Unit;

use GuzzleHttp\Exception\ConnectException;
use Opekunov\Centrifugo\Centrifugo;

class CentrifugoTest extends \Opekunov\Centrifugo\Tests\TestCase
{
    public function testGenerateToken()
    {
        $timestamp = 1491650279;
        $user_id = 1;
        $info = [
            'first_name' => 'Aleksandr',
            'last_name' => 'Opekunov',
        ];
        $client = '0c951315-be0e-4516-b99e-05e60b0cc317';

        $clientToken1 = $this->centrifuge->generateConnectionToken($client, $timestamp);
        $this->assertEquals('eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIwYzk1MTMxNS1iZTBlLTQ1MTYtYjk5ZS0wNWU2MGIwY2MzMTciLCJleHAiOjE0OTE2NTAyNzl9.7l9xTSgNDE-b2770rLNk7aN1xlG-jIKx2oz578fEnNg', $clientToken1);

        $clientToken2 = $this->centrifuge->generateConnectionToken($user_id, $timestamp, $info);
        $this->assertEquals('eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIiwiaW5mbyI6eyJmaXJzdF9uYW1lIjoiQWxla3NhbmRyIiwibGFzdF9uYW1lIjoiT3Bla3Vub3YifSwiZXhwIjoxNDkxNjUwMjc5fQ.lacRMie5NCXLhLPZ29rgAwPjCm44C1Fm48Dh0seZ6LU', $clientToken2);
    }

    public function testCentrifugoApiPublish()
    {
        $publish = $this->centrifuge->publish('test-channel', ['event' => 'test-event']);
        $this->assertEquals(['result' => []], $publish);
    }

    public function testCentrifugoApiPublishMany()
    {
        $publish = $this->centrifuge->publishMany(
            [['channel' => 'test-channel:1', 'data' => 'Hello'], ['channel' => 'test-channel:2', 'data' => 'World']]
        );
        $this->assertEquals(null, $publish);
    }

    public function testCentrifugoApiBroadcast()
    {
        $broadcast = $this->centrifuge->broadcast(['test-channel-1', 'test-channel-2'], ['event' => 'test-event']);
        $this->assertEquals([
            'result' => [
                'responses' => [
                    ['result' => []],
                    ['result' => []]
                ]
            ]
        ], $broadcast);
    }

    public function testCentrifugoApiPresence()
    {
        $presence = $this->centrifuge->presence('test-channel');
        $this->assertEquals(['result' => ["presence" => []]], $presence);
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
        $this->assertEquals(['result' => ['channels' => []]], $channels);
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
                'num_users' => 0
            ]
        ], $stats);
    }

    public function testTimeoutFunction()
    {
        $timeout = 3;
        $delta = 0.5;

        $badCentrifugo = new Centrifugo(
            [
                'driver' => 'centrifugo',
                'secret' => 'd55bf295-bee6-4259-8912-0a58f44ed30e',
                'apikey' => '0c951315-be0e-4516-b99e-05e60b0cc307_',
                'api_path' => '',
                'url' => 'https://httpstat.us/200?sleep=20000',
                'timeout' => $timeout,
                'tries' => 1
            ]
        );

        $start = microtime(true);
        $this->expectException(ConnectException::class);
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
                'driver' => 'centrifugo',
                'secret' => 'd55bf295-bee6-4259-8912-0a58f44ed30e',
                'apikey' => '0c951315-be0e-4516-b99e-05e60b0cc307_',
                'api_path' => '',
                'url' => 'https://httpstat.us/200?sleep=20000',
                'timeout' => $timeout,
                'tries' => $tries
            ]
        );

        $start = microtime(true);
        $this->expectException(ConnectException::class);
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
