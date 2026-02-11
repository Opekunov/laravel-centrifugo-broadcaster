<?php

namespace Opekunov\Centrifugo\Tests\Integration;

use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Http\Request;
use Mockery;
use Opekunov\Centrifugo\Centrifugo;
use Opekunov\Centrifugo\CentrifugoBroadcaster;
use Opekunov\Centrifugo\Tests\TestCase;

class CentrifugoBroadcasterIntegrationTest extends TestCase
{
    private CentrifugoBroadcaster $broadcaster;
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
            'show_node_info' => true,
            'timeout' => 5,
        ];

        $this->centrifugo = new Centrifugo($config);
        $this->broadcaster = new CentrifugoBroadcaster($this->centrifugo);
    }

    public function testBroadcastWithRealServer()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $channels = ['test:broadcast1', 'test:broadcast2', 'private-test:broadcast3'];
        $event = 'TestEvent';
        $payload = [
            'message' => 'Integration test broadcast',
            'timestamp' => time(),
            'data' => ['key' => 'value'],
        ];

        // Should not throw exception
        $this->broadcaster->broadcast($channels, $event, $payload);

        // If we reach here without exception, the test passes
        $this->assertTrue(true);
    }

    public function testAuthWithRealTokenGeneration()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        $user = (object) ['id' => 'integration-user-' . time()];

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);
        $request->shouldReceive('get')->with('channel')->andReturn('test:auth-channel');

        // Create a partial mock that allows the real auth method to run
        // but mocks the access control check
        $broadcasterPartial = Mockery::mock(CentrifugoBroadcaster::class, [$this->centrifugo])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $broadcasterPartial->shouldReceive('verifyUserCanAccessChannel')
            ->once()
            ->andReturnNull();

        $response = $broadcasterPartial->auth($request);

        $this->assertEquals(200, $response->getStatusCode());

        // Decode the response and verify structure
        $responseData = json_decode($response->getContent(), true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('channel', $responseData);
        $this->assertArrayHasKey('token', $responseData);
        $this->assertArrayHasKey('node_info', $responseData);

        $this->assertEquals('test:auth-channel', $responseData['channel']);
        $this->assertIsString($responseData['token']);
        $this->assertNotEmpty($responseData['token']);

        // Verify token structure (JWT should have 3 parts)
        $tokenParts = explode('.', $responseData['token']);
        $this->assertCount(3, $tokenParts);

        // Verify node_info is present (since show_node_info is true)
        $this->assertIsArray($responseData['node_info']);
    }

    public function testBroadcastErrorHandling()
    {
        // Create a Centrifugo instance with invalid config to trigger errors
        $invalidConfig = [
            'secret' => 'invalid-secret',
            'apikey' => 'invalid-api-key',
            'url' => 'http://localhost:9999', // Non-existent port
            'timeout' => 1,
        ];

        $invalidCentrifugo = new Centrifugo($invalidConfig);
        $broadcaster = new CentrifugoBroadcaster($invalidCentrifugo);

        $this->expectException(BroadcastException::class);

        $broadcaster->broadcast(['test:error-channel'], 'ErrorEvent', ['message' => 'test']);
    }

    public function testChannelNameFormatting()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        // Test that private- prefix is correctly removed
        $channels = [
            'private-user:123',
            'private-chat:room1',
            'public:general',
            'presence-online:users',
        ];

        $event = 'ChannelFormattingTest';
        $payload = ['test' => 'channel formatting'];

        // Should not throw exception - channels should be properly formatted
        $this->broadcaster->broadcast($channels, $event, $payload);

        $this->assertTrue(true);
    }

    public function testValidAuthenticationResponse()
    {
        $request = Mockery::mock(Request::class);
        $testResult = ['token' => 'test-token', 'expires_at' => time() + 300];

        $response = $this->broadcaster->validAuthenticationResponse($request, $testResult);

        $this->assertEquals($testResult, $response);
    }

    public function testAuthWithoutNodeInfo()
    {
        if (!$this->isCentrifugoRunning()) {
            $this->markTestSkipped("Centrifugo server is not running on {$this->centrifugoUrl}");
        }

        // Create config without node info
        $config = [
            'secret' => 'bbe7d157-a253-4094-9759-06a8236543f9',
            'apikey' => 'd7627bb6-2292-4911-82e1-615c0ed3eebb',
            'url' => $this->centrifugoUrl,
            'show_node_info' => false,
        ];

        $centrifugo = new Centrifugo($config);
        $broadcaster = new CentrifugoBroadcaster($centrifugo);

        $user = (object) ['id' => 'no-node-info-user'];

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);
        $request->shouldReceive('get')->with('channel')->andReturn('test:no-node-info');

        $broadcasterPartial = Mockery::mock(CentrifugoBroadcaster::class, [$centrifugo])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $broadcasterPartial->shouldReceive('verifyUserCanAccessChannel')
            ->once()
            ->andReturnNull();

        $response = $broadcasterPartial->auth($request);

        $responseData = json_decode($response->getContent(), true);
        $this->assertNull($responseData['node_info']);
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

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
