<?php

namespace Opekunov\Centrifugo\Tests\Unit;

use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Http\Request;
use Mockery;
use Opekunov\Centrifugo\Centrifugo;
use Opekunov\Centrifugo\CentrifugoBroadcaster;
use Opekunov\Centrifugo\Tests\TestCase;

class CentrifugoBroadcasterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ---- broadcast() ----

    public function test_broadcast_removes_private_prefix(): void
    {
        $mockCentrifugo = Mockery::mock(Centrifugo::class);
        $mockCentrifugo->shouldReceive('broadcast')
            ->once()
            ->with(['test-channel', 'another-channel'], ['message' => 'Hello', 'event' => 'test-event'])
            ->andReturn(['result' => []]);

        $broadcaster = new CentrifugoBroadcaster($mockCentrifugo);
        $broadcaster->broadcast(['test-channel', 'private-another-channel'], 'test-event', ['message' => 'Hello']);

        $this->assertTrue(true);
    }

    public function test_broadcast_throws_on_error(): void
    {
        $mockCentrifugo = Mockery::mock(Centrifugo::class);
        $mockCentrifugo->shouldReceive('broadcast')
            ->once()
            ->andReturn([
                'error' => [
                    'message' => 'Channel not found',
                    'code' => 404,
                ],
            ]);

        $broadcaster = new CentrifugoBroadcaster($mockCentrifugo);

        $this->expectException(BroadcastException::class);
        $this->expectExceptionMessage('Channel not found');
        $this->expectExceptionCode(404);

        $broadcaster->broadcast(['test-channel'], 'test-event', ['message' => 'Hello']);
    }

    public function test_broadcast_success_no_exception(): void
    {
        $mockCentrifugo = Mockery::mock(Centrifugo::class);
        $mockCentrifugo->shouldReceive('broadcast')
            ->once()
            ->andReturn(['result' => []]);

        $broadcaster = new CentrifugoBroadcaster($mockCentrifugo);
        $broadcaster->broadcast(['channel1', 'channel2'], 'event', ['data' => 'value']);

        $this->assertTrue(true);
    }

    // ---- auth() ----

    public function test_auth(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn((object) ['id' => '123']);
        $request->shouldReceive('get')->with('channel')->andReturn('test-channel');

        $mockCentrifugo = Mockery::mock(Centrifugo::class);
        $mockCentrifugo->shouldReceive('showNodeInfo')->andReturn(false);
        $mockCentrifugo->shouldReceive('getDefaultTokenExpiration')->andReturn(300);
        $mockCentrifugo->shouldReceive('generateSubscriptionToken')
            ->once()
            ->andReturn('test-token');

        $broadcaster = Mockery::mock(CentrifugoBroadcaster::class, [$mockCentrifugo])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $broadcaster->shouldReceive('verifyUserCanAccessChannel')
            ->once()
            ->with($request, 'test-channel')
            ->andReturnNull();

        $response = $broadcaster->auth($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_auth_with_node_info(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn((object) ['id' => '123']);
        $request->shouldReceive('get')->with('channel')->andReturn('test-channel');

        $mockCentrifugo = Mockery::mock(Centrifugo::class);
        $mockCentrifugo->shouldReceive('showNodeInfo')->andReturn(true);
        $mockCentrifugo->shouldReceive('info')->andReturn(['nodes' => []]);
        $mockCentrifugo->shouldReceive('getDefaultTokenExpiration')->andReturn(300);
        $mockCentrifugo->shouldReceive('generateSubscriptionToken')
            ->once()
            ->andReturn('test-token');

        $broadcaster = Mockery::mock(CentrifugoBroadcaster::class, [$mockCentrifugo])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $broadcaster->shouldReceive('verifyUserCanAccessChannel')
            ->once()
            ->andReturnNull();

        $response = $broadcaster->auth($request);

        $this->assertEquals(200, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('node_info', $content);
    }

    public function test_auth_without_user(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn(null);
        $request->shouldReceive('get')->with('channel')->andReturn('test-channel');

        $mockCentrifugo = Mockery::mock(Centrifugo::class);
        $mockCentrifugo->shouldReceive('showNodeInfo')->andReturn(false);
        $mockCentrifugo->shouldReceive('getDefaultTokenExpiration')->andReturn(300);
        $mockCentrifugo->shouldReceive('generateSubscriptionToken')
            ->once()
            ->with('', 'test-channel', Mockery::any())
            ->andReturn('anonymous-token');

        $broadcaster = Mockery::mock(CentrifugoBroadcaster::class, [$mockCentrifugo])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $broadcaster->shouldReceive('verifyUserCanAccessChannel')
            ->once()
            ->andReturnNull();

        $response = $broadcaster->auth($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    // ---- validAuthenticationResponse() ----

    public function test_valid_authentication_response(): void
    {
        $centrifugo = new Centrifugo([
            'secret' => 'test-secret',
            'apikey' => 'test-key',
        ]);
        $broadcaster = new CentrifugoBroadcaster($centrifugo);

        $request = Mockery::mock(Request::class);
        $result = ['token' => 'test-token'];

        $this->assertEquals($result, $broadcaster->validAuthenticationResponse($request, $result));
    }
}
