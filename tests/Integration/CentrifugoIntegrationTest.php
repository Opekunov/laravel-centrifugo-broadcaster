<?php

namespace Opekunov\Centrifugo\Tests\Integration;

use Opekunov\Centrifugo\Centrifugo;
use Opekunov\Centrifugo\Http\HttpClient;
use PHPUnit\Framework\TestCase;

class CentrifugoIntegrationTest extends TestCase
{
    private Centrifugo $centrifugo;
    private string $centrifugoUrl;

    public function setUp(): void
    {
        parent::setUp();

        $this->centrifugoUrl = $_ENV['CENTRIFUGO_URL'] ?? 'http://localhost:8001';

        $config = [
            'secret' => 'bbe7d157-a253-4094-9759-06a8236543f9',
            'apikey' => 'd7627bb6-2292-4911-82e1-615c0ed3eebb',
            'url' => $this->centrifugoUrl,
            'timeout' => 5,
            'tries' => 1,
        ];

        $this->centrifugo = new Centrifugo($config);
    }

    public function testCentrifugoConnectionAndInfo()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $result = $this->centrifugo->info();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
    }

    public function testPublishToChannel()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $result = $this->centrifugo->publish('test:integration', [
            'message' => 'Hello from integration test!',
            'timestamp' => time(),
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testBroadcastToMultipleChannels()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $result = $this->centrifugo->broadcast(['test:channel1', 'test:channel2'], [
            'message' => 'Broadcast message',
            'type' => 'broadcast_test',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testPublishMany()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $result = $this->centrifugo->publishMany([
            ['channel' => 'test:batch1', 'data' => ['message' => 'Batch message 1']],
            ['channel' => 'test:batch2', 'data' => ['message' => 'Batch message 2']],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testChannelsList()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        // First publish something to create channels
        $this->centrifugo->publish('test:channels-list', ['test' => 'data']);

        $result = $this->centrifugo->channels();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('channels', $result['result']);
    }

    public function testPresenceForEmptyChannel()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $result = $this->centrifugo->presence('test:empty-presence');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);

        // Empty channel should have empty presence
        if (isset($result['result']['presence'])) {
            $this->assertIsArray($result['result']['presence']);
        }
    }

    public function testPresenceStats()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $result = $this->centrifugo->presenceStats('test:empty-stats');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);

        if (isset($result['result'])) {
            $this->assertArrayHasKey('num_clients', $result['result']);
            $this->assertArrayHasKey('num_users', $result['result']);
        }
    }

    public function testTokenGeneration()
    {
        // Token generation doesn't require server connection
        $connectionToken = $this->centrifugo->generateConnectionToken('test-user-123', time() + 300);
        $this->assertIsString($connectionToken);
        $this->assertNotEmpty($connectionToken);

        // Verify JWT structure
        $parts = explode('.', $connectionToken);
        $this->assertCount(3, $parts);

        $subscriptionToken = $this->centrifugo->generateSubscriptionToken('test-user-123', 'test:subscription', time() + 300);
        $this->assertIsString($subscriptionToken);
        $this->assertNotEmpty($subscriptionToken);

        // Verify JWT structure
        $parts = explode('.', $subscriptionToken);
        $this->assertCount(3, $parts);
    }

    public function testApiUrlFormat()
    {
        // Test that URLs are prepared correctly for v5+ API format
        $reflection = new \ReflectionClass($this->centrifugo);
        $method = $reflection->getMethod('prepareUrl');
        $method->setAccessible(true);

        $baseUrl = $this->centrifugoUrl;
        $this->assertEquals("{$baseUrl}/api/publish", $method->invoke($this->centrifugo, 'publish'));
        $this->assertEquals("{$baseUrl}/api/broadcast", $method->invoke($this->centrifugo, 'broadcast'));
        $this->assertEquals("{$baseUrl}/api/channels", $method->invoke($this->centrifugo, 'channels'));
        $this->assertEquals("{$baseUrl}/api/info", $method->invoke($this->centrifugo, 'info'));
        $this->assertEquals("{$baseUrl}/api/presence", $method->invoke($this->centrifugo, 'presence'));
        $this->assertEquals("{$baseUrl}/api/history", $method->invoke($this->centrifugo, 'history'));
        $this->assertEquals("{$baseUrl}/api/batch", $method->invoke($this->centrifugo, 'batch'));
    }

    public function testUnsubscribeFromChannel()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $result = $this->centrifugo->unsubscribe('test:unsubscribe-channel', 'test-user-123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testDisconnectUser()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $result = $this->centrifugo->disconnect('test-user-123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testHistoryOperations()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $channel = 'test:history-channel';

        // First publish some messages
        $this->centrifugo->publish($channel, ['message' => 'First message', 'id' => 1]);
        $this->centrifugo->publish($channel, ['message' => 'Second message', 'id' => 2]);

        // Get history
        $history = $this->centrifugo->history($channel);
        $this->assertIsArray($history);
        $this->assertArrayHasKey('result', $history);

        // Remove history
        $removeResult = $this->centrifugo->historyRemove($channel);
        $this->assertIsArray($removeResult);
        $this->assertArrayHasKey('result', $removeResult);
    }

    public function testChannelWithPattern()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        // First publish to create some channels
        $this->centrifugo->publish('test:pattern1', ['test' => 'data1']);
        $this->centrifugo->publish('test:pattern2', ['test' => 'data2']);
        $this->centrifugo->publish('other:channel', ['test' => 'data3']);

        // Get channels with pattern
        $result = $this->centrifugo->channels('test:*');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
    }

    public function testComplexJwtTokens()
    {
        // Test connection token with channels and info
        $userId = 'user-' . time();
        $info = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'role' => 'admin',
        ];
        $channels = ['personal:' . $userId, 'notifications'];

        $connectionToken = $this->centrifugo->generateConnectionToken($userId, time() + 600, $info, $channels);

        // Verify token structure
        $parts = explode('.', $connectionToken);
        $this->assertCount(3, $parts);

        // Decode and verify payload
        $payload = json_decode(base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT)), true);

        $this->assertEquals($userId, $payload['sub']);
        $this->assertEquals($info, $payload['info']);
        $this->assertEquals($channels, $payload['channels']);

        // Test subscription token with override
        $override = [
            'presence' => true,
            'recover' => true,
            'history_size' => 100,
            'history_ttl' => '24h',
        ];

        $subscriptionToken = $this->centrifugo->generateSubscriptionToken(
            $userId,
            'premium:channel',
            time() + 3600,
            $info,
            $override
        );

        $parts = explode('.', $subscriptionToken);
        $payload = json_decode(base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT)), true);

        $this->assertEquals($userId, $payload['sub']);
        $this->assertEquals('premium:channel', $payload['channel']);
        $this->assertEquals($info, $payload['info']);
        $this->assertEquals($override, $payload['override']);
    }

    public function testSubscribeUser()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $result = $this->centrifugo->subscribe('test:subscribe-channel', 'test-user-123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testHistoryWithParameters()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $channel = 'test:history-params';

        $this->centrifugo->publish($channel, ['message' => 'msg1']);
        $this->centrifugo->publish($channel, ['message' => 'msg2']);
        $this->centrifugo->publish($channel, ['message' => 'msg3']);

        // Test with limit
        $history = $this->centrifugo->history($channel, limit: 2);
        $this->assertIsArray($history);
        $this->assertArrayHasKey('result', $history);

        // Test with reverse
        $historyReverse = $this->centrifugo->history($channel, limit: 2, reverse: true);
        $this->assertIsArray($historyReverse);
        $this->assertArrayHasKey('result', $historyReverse);
    }

    public function testErrorHandling()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        // Test presence on non-existent namespace (should return error or empty result)
        $result = $this->centrifugo->presence('nonexistent:channel');
        $this->assertIsArray($result);

        // Test history on channel without history enabled (might return error)
        $result = $this->centrifugo->history('no-history:channel');
        $this->assertIsArray($result);
    }

    public function testV5ApiHeadersFormat()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        // Test that our client sends the correct headers for v5+ API
        $result = $this->centrifugo->info();
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('nodes', $result['result']);

        // Verify we get a response that looks like v5+ format
        $nodes = $result['result']['nodes'];
        $this->assertIsArray($nodes);
        $this->assertNotEmpty($nodes);

        $node = $nodes[0];
        $this->assertArrayHasKey('version', $node);

        // Version should be 5.x or 6.x
        $version = $node['version'];
        $this->assertTrue(
            str_starts_with($version, '5.') || str_starts_with($version, '6.'),
            "Expected Centrifugo version 5.x or 6.x, got: $version"
        );
    }

    public function testRpc()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        // RPC without configured proxy will return an error — that's expected
        $result = $this->centrifugo->rpc('test_method', ['key' => 'value']);
        $this->assertIsArray($result);
        // We expect either result or error key — both are valid API responses
        $this->assertTrue(
            array_key_exists('result', $result) || array_key_exists('error', $result),
            'RPC response should contain either result or error key'
        );
    }

    public function testPublishWithUnicodeData()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $result = $this->centrifugo->publish('test:unicode', [
            'message' => 'Привет мир! 你好世界 🚀🎉',
            'emoji' => '👍',
            'cyrillic' => 'Тестовое сообщение',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testPublishEmptyData()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $result = $this->centrifugo->publish('test:empty-data', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
    }

    public function testHistoryPagination()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $channel = 'test:history-pagination-' . time();

        // Publish several messages
        for ($i = 1; $i <= 5; $i++) {
            $this->centrifugo->publish($channel, ['message' => "Message {$i}", 'id' => $i]);
        }

        // Get first page with limit
        $page1 = $this->centrifugo->history($channel, limit: 2);
        $this->assertIsArray($page1);
        $this->assertArrayHasKey('result', $page1);

        if (isset($page1['result']['publications'])) {
            $this->assertLessThanOrEqual(2, count($page1['result']['publications']));

            // If there's an offset, use it for next page
            if (isset($page1['result']['offset'])) {
                $page2 = $this->centrifugo->history(
                    $channel,
                    limit: 2,
                    offset: $page1['result']['offset'],
                    epoch: $page1['result']['epoch'] ?? '',
                );
                $this->assertIsArray($page2);
                $this->assertArrayHasKey('result', $page2);
            }
        }
    }

    public function testBroadcastToSingleChannel()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $result = $this->centrifugo->broadcast(['test:single-broadcast'], [
            'message' => 'Single channel broadcast',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testChannelsEmpty()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        // Use a pattern that won't match any channels
        $result = $this->centrifugo->channels('nonexistent-prefix:*');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('channels', $result['result']);
    }

    public function testDisconnectNonExistentUser()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        // Disconnecting a non-existent user should not cause an error
        $result = $this->centrifugo->disconnect('non-existent-user-' . time());

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testSubscribeWithInfo()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $result = $this->centrifugo->subscribe('test:subscribe-info', 'test-user-456', [
            'username' => 'testuser',
            'role' => 'admin',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayNotHasKey('error', $result);
    }

    private function isCentrifugoRunning(): bool
    {
        try {
            $result = $this->centrifugo->info();
            return isset($result['result']);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
