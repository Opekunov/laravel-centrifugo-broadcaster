<?php

namespace Opekunov\Centrifugo\Tests\Unit;

use Opekunov\Centrifugo\Centrifugo;
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    private function createCentrifugo(array $overrides = []): Centrifugo
    {
        return new Centrifugo(array_merge([
            'secret' => 'test-secret',
            'apikey' => 'test-key',
        ], $overrides));
    }

    public function testSecretsNotExposedInExceptions(): void
    {
        $centrifugo = new Centrifugo([
            'secret' => 'super-secret-key',
            'apikey' => 'super-secret-api-key',
            'url' => 'http://invalid-host-that-does-not-exist.local:8001',
            'timeout' => 1,
        ]);

        $exceptionThrown = false;
        try {
            $centrifugo->info();
        } catch (\Exception $e) {
            $exceptionThrown = true;
            $this->assertStringNotContainsString('super-secret-key', $e->getMessage());
            $this->assertStringNotContainsString('super-secret-api-key', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown, 'Expected exception to be thrown for invalid host');
    }

    public function testJwtTokensAreUrlSafeBase64(): void
    {
        $centrifugo = $this->createCentrifugo();

        $token = $centrifugo->generateConnectionToken('user123', time() + 300);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $token);

        $subToken = $centrifugo->generateSubscriptionToken('user123', 'test-channel', time() + 300);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $subToken);
    }

    public function testJwtTokenContainsExpiration(): void
    {
        $centrifugo = $this->createCentrifugo();
        $expTime = time() + 600;

        $token = $centrifugo->generateConnectionToken('user123', $expTime);
        $payload = $this->decodeJwtPayload($token);

        $this->assertEquals($expTime, $payload['exp']);
        $this->assertEquals('user123', $payload['sub']);
    }

    public function testMaliciousInputHandledSafely(): void
    {
        $centrifugo = $this->createCentrifugo();

        $maliciousUserId = '<script>alert("xss")</script>';
        $maliciousChannel = '../../etc/passwd';

        $token1 = $centrifugo->generateConnectionToken($maliciousUserId, time() + 300);
        $this->assertIsString($token1);

        $token2 = $centrifugo->generateSubscriptionToken($maliciousUserId, $maliciousChannel, time() + 300);
        $this->assertIsString($token2);

        $payload = $this->decodeJwtPayload($token1);
        $this->assertEquals($maliciousUserId, $payload['sub']);
    }

    public function testSecureDefaults(): void
    {
        $centrifugo = $this->createCentrifugo();

        $this->assertFalse($centrifugo->showNodeInfo());
        $this->assertGreaterThan(0, $centrifugo->getDefaultTokenExpiration());
        $this->assertLessThan(3600, $centrifugo->getDefaultTokenExpiration());
    }

    public function testSslUrlPreservation(): void
    {
        $centrifugo = $this->createCentrifugo([
            'url' => 'https://example.com',
            'verify' => true,
            'ssl_key' => '/path/to/cert.pem',
        ]);

        $reflection = new \ReflectionClass($centrifugo);
        $method = $reflection->getMethod('prepareUrl');
        $method->setAccessible(true);

        $url = $method->invoke($centrifugo, 'publish');
        $this->assertStringStartsWith('https://', $url);
    }

    public function testVariousChannelNameFormats(): void
    {
        $centrifugo = $this->createCentrifugo();

        $channels = [
            'simple',
            'namespace:channel',
            'user:123',
            'chat-room-1',
            'test_channel',
            'deep:nested:channel',
        ];

        foreach ($channels as $channel) {
            $token = $centrifugo->generateSubscriptionToken('user123', $channel, time() + 300);
            $payload = $this->decodeJwtPayload($token);
            $this->assertEquals($channel, $payload['channel']);
        }
    }

    private function decodeJwtPayload(string $token): array
    {
        $parts = explode('.', $token);
        $padded = str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT);

        return json_decode(base64_decode($padded), true);
    }
}
