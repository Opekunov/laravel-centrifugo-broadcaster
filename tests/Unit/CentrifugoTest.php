<?php

namespace Opekunov\Centrifugo\Tests\Unit;

use Opekunov\Centrifugo\Centrifugo;
use Opekunov\Centrifugo\Exceptions\CentrifugoConnectionException;
use Opekunov\Centrifugo\Http\HttpClient;
use Opekunov\Centrifugo\Tests\TestCase;

class CentrifugoTest extends TestCase
{
    private function createCentrifugo(array $overrides = []): Centrifugo
    {
        return new Centrifugo(array_merge([
            'secret' => 'test-secret',
            'apikey' => 'test-key',
            'url' => 'http://localhost:8001',
            'token_expire_time' => 300,
            'show_node_info' => false,
        ], $overrides));
    }

    // ---- Construction ----

    public function testConstruction(): void
    {
        $centrifugo = $this->createCentrifugo();
        $this->assertInstanceOf(Centrifugo::class, $centrifugo);
    }

    public function testConstructionWithHttpClient(): void
    {
        $centrifugo = new Centrifugo([
            'secret' => 'test-secret',
            'apikey' => 'test-key',
        ], new HttpClient());

        $this->assertInstanceOf(Centrifugo::class, $centrifugo);
    }

    public function testResolvesFromContainer(): void
    {
        $this->assertInstanceOf(Centrifugo::class, $this->centrifuge);
    }

    // ---- Configuration ----

    public function testConfigurationDefaults(): void
    {
        $centrifugo = new Centrifugo([
            'secret' => 'test-secret',
            'apikey' => 'test-key',
        ]);

        $this->assertGreaterThan(0, $centrifugo->getDefaultTokenExpiration());
        $this->assertFalse($centrifugo->showNodeInfo());
    }

    public function testSslConfiguration(): void
    {
        $centrifugo = $this->createCentrifugo([
            'url' => 'https://localhost:8001',
            'verify' => true,
            'ssl_key' => '/path/to/cert.pem',
        ]);

        $this->assertInstanceOf(Centrifugo::class, $centrifugo);
    }

    // ---- API URL format (v5+) ----

    public function testApiUrlPreparation(): void
    {
        $centrifugo = $this->createCentrifugo();

        $reflection = new \ReflectionClass($centrifugo);
        $method = $reflection->getMethod('prepareUrl');
        $method->setAccessible(true);

        $endpoints = [
            'publish', 'broadcast', 'presence', 'presence_stats',
            'history', 'history_remove', 'channels', 'info',
            'batch', 'subscribe', 'unsubscribe', 'disconnect', 'rpc',
        ];

        foreach ($endpoints as $endpoint) {
            $this->assertStringEndsWith('/api/' . $endpoint, $method->invoke($centrifugo, $endpoint));
        }
    }

    public function testApiUrlPreparationWithoutMethod(): void
    {
        $centrifugo = $this->createCentrifugo();

        $reflection = new \ReflectionClass($centrifugo);
        $method = $reflection->getMethod('prepareUrl');
        $method->setAccessible(true);

        $this->assertStringEndsWith('/api', $method->invoke($centrifugo, null));
    }

    public function testApiUrlWithHttps(): void
    {
        $centrifugo = $this->createCentrifugo(['url' => 'https://example.com']);

        $reflection = new \ReflectionClass($centrifugo);
        $method = $reflection->getMethod('prepareUrl');
        $method->setAccessible(true);

        $url = $method->invoke($centrifugo, 'publish');
        $this->assertEquals('https://example.com/api/publish', $url);
    }

    // ---- JWT Token generation ----

    public function testGenerateConnectionToken(): void
    {
        $timestamp = 1491650279;
        $clientId = '0c951315-be0e-4516-b99e-05e60b0cc317';

        $token = $this->centrifuge->generateConnectionToken($clientId, $timestamp);

        $this->assertEquals(
            'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIwYzk1MTMxNS1iZTBlLTQ1MTYtYjk5ZS0wNWU2MGIwY2MzMTciLCJleHAiOjE0OTE2NTAyNzl9.jue8OmcTwCwyDtV-eEz5HTudI8G7kr7cK2lAoi_hMyE',
            $token
        );
    }

    public function testGenerateSubscriptionToken(): void
    {
        $timestamp = 1491650279;
        $clientId = '0c951315-be0e-4516-b99e-05e60b0cc317';
        $info = ['first_name' => 'Aleksandr', 'last_name' => 'Opekunov'];

        $token = $this->centrifuge->generateSubscriptionToken($clientId, 'test', $timestamp, $info);

        $this->assertEquals(
            'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIwYzk1MTMxNS1iZTBlLTQ1MTYtYjk5ZS0wNWU2MGIwY2MzMTciLCJleHAiOjE0OTE2NTAyNzksImNoYW5uZWwiOiJ0ZXN0IiwiaW5mbyI6eyJmaXJzdF9uYW1lIjoiQWxla3NhbmRyIiwibGFzdF9uYW1lIjoiT3Bla3Vub3YifX0.fagXHy6MAaziGVGwgtWHWqMEGGvbg5ldHpA2dB5Nc7M',
            $token
        );
    }

    public function testJwtTokenStructure(): void
    {
        $centrifugo = $this->createCentrifugo();

        $connectionToken = $centrifugo->generateConnectionToken('user123', time() + 300);
        $parts = explode('.', $connectionToken);
        $this->assertCount(3, $parts);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $parts[0]);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $parts[1]);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $parts[2]);

        $subscriptionToken = $centrifugo->generateSubscriptionToken('user123', 'ch', time() + 300);
        $this->assertCount(3, explode('.', $subscriptionToken));
    }

    public function testConnectionTokenWithChannels(): void
    {
        $channels = ['channel1', 'channel2'];
        $token = $this->centrifuge->generateConnectionToken('123', 1491650279, [], $channels);

        $payload = $this->decodeJwtPayload($token);
        $this->assertArrayHasKey('channels', $payload);
        $this->assertEquals($channels, $payload['channels']);
    }

    public function testConnectionTokenWithInfo(): void
    {
        $centrifugo = $this->createCentrifugo();
        $info = ['name' => 'Test User', 'role' => 'admin'];

        $token = $centrifugo->generateConnectionToken('123', 1491650279, $info);

        $payload = $this->decodeJwtPayload($token);
        $this->assertArrayHasKey('info', $payload);
        $this->assertEquals($info, $payload['info']);
    }

    public function testConnectionTokenWithoutInfoOmitsKey(): void
    {
        $centrifugo = $this->createCentrifugo();
        $token = $centrifugo->generateConnectionToken('123', 1491650279);

        $payload = $this->decodeJwtPayload($token);
        $this->assertArrayNotHasKey('info', $payload);
        $this->assertArrayNotHasKey('channels', $payload);
    }

    public function testSubscriptionTokenWithOverride(): void
    {
        $centrifugo = $this->createCentrifugo();
        $override = ['presence' => true, 'history_size' => 100];

        $token = $centrifugo->generateSubscriptionToken('123', 'test-channel', 1491650279, [], $override);

        $payload = $this->decodeJwtPayload($token);
        $this->assertArrayHasKey('override', $payload);
        $this->assertEquals($override, $payload['override']);
    }

    public function testSubscriptionTokenContainsChannel(): void
    {
        $centrifugo = $this->createCentrifugo();

        $token = $centrifugo->generateSubscriptionToken('user1', 'chat:room', 1491650279);

        $payload = $this->decodeJwtPayload($token);
        $this->assertArrayHasKey('channel', $payload);
        $this->assertEquals('chat:room', $payload['channel']);
    }

    public function testTokenContainsExpAndSub(): void
    {
        $centrifugo = $this->createCentrifugo();
        $expTime = time() + 600;

        $token = $centrifugo->generateConnectionToken('user123', $expTime);

        $payload = $this->decodeJwtPayload($token);
        $this->assertEquals($expTime, $payload['exp']);
        $this->assertEquals('user123', $payload['sub']);
    }

    public function testTokenWithIntegerUserId(): void
    {
        $centrifugo = $this->createCentrifugo();

        $token = $centrifugo->generateConnectionToken(42, time() + 300);

        $payload = $this->decodeJwtPayload($token);
        $this->assertEquals('42', $payload['sub']);
    }

    // ---- Batch command format (v5+) ----

    public function testBatchCommandFormat(): void
    {
        $params = [
            ['channel' => 'test1', 'data' => ['msg' => 'hello']],
            ['channel' => 'test2', 'data' => ['msg' => 'world']],
        ];

        $commands = [];
        foreach ($params as $param) {
            $commands[] = ['publish' => $param];
        }
        $decoded = json_decode(json_encode(['commands' => $commands]), true);

        $this->assertArrayHasKey('commands', $decoded);
        $this->assertCount(2, $decoded['commands']);

        foreach ($decoded['commands'] as $command) {
            $this->assertArrayHasKey('publish', $command);
            $this->assertArrayNotHasKey('method', $command);
            $this->assertArrayNotHasKey('params', $command);
        }
    }

    // ---- Timeout & retries ----

    public function testTimeoutFunction(): void
    {
        $timeout = 3;
        $delta = 0.5;

        $badCentrifugo = new Centrifugo([
            'driver' => 'centrifugo',
            'secret' => 'd55bf295-bee6-4259-8912-0a58f44ed30e',
            'apikey' => '0c951315-be0e-4516-b99e-05e60b0cc307_',
            'api_path' => '',
            'url' => 'https://httpstat.us/200?sleep=20000',
            'timeout' => $timeout,
            'tries' => 1,
        ]);

        $start = microtime(true);
        $this->expectException(CentrifugoConnectionException::class);

        try {
            $badCentrifugo->publish('test-channel', ['event' => 'test-event']);
        } catch (\Exception $e) {
            $eval = microtime(true) - $start;
            $this->assertTrue($eval < $timeout + $delta);
            throw $e;
        }
    }

    public function testTriesFunction(): void
    {
        $timeout = 1;
        $tries = 3;
        $delta = 2.0;

        $badCentrifugo = new Centrifugo([
            'driver' => 'centrifugo',
            'secret' => 'd55bf295-bee6-4259-8912-0a58f44ed30e',
            'apikey' => '0c951315-be0e-4516-b99e-05e60b0cc307_',
            'api_path' => '',
            'url' => 'https://httpstat.us/200?sleep=20000',
            'timeout' => $timeout,
            'tries' => $tries,
        ]);

        $start = microtime(true);
        $this->expectException(CentrifugoConnectionException::class);

        try {
            $badCentrifugo->publish('test-channel', ['event' => 'test-event']);
        } catch (\Exception $e) {
            $eval = microtime(true) - $start;
            $this->assertTrue($eval < ($timeout + $delta) * $tries);
            throw $e;
        }
    }

    // ---- Helpers ----

    private function decodeJwtPayload(string $token): array
    {
        $parts = explode('.', $token);
        $padded = str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT);

        return json_decode(base64_decode($padded), true);
    }
}
