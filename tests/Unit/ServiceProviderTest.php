<?php

namespace Opekunov\Centrifugo\Tests\Unit;

use Opekunov\Centrifugo\Centrifugo;
use Opekunov\Centrifugo\Contracts\CentrifugoInterface;
use Opekunov\Centrifugo\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function testCentrifugoIsSingleton(): void
    {
        $instance1 = $this->app->make('centrifugo');
        $instance2 = $this->app->make('centrifugo');

        $this->assertSame($instance1, $instance2);
    }

    public function testResolvesViaConcretClass(): void
    {
        $instance = $this->app->make(Centrifugo::class);
        $this->assertInstanceOf(Centrifugo::class, $instance);
    }

    public function testResolvesViaInterface(): void
    {
        $instance = $this->app->make(CentrifugoInterface::class);
        $this->assertInstanceOf(Centrifugo::class, $instance);
    }

    public function testBroadcastDriverRegistered(): void
    {
        $manager = $this->app->make(\Illuminate\Broadcasting\BroadcastManager::class);
        $driver = $manager->connection('centrifugo');

        $this->assertInstanceOf(\Opekunov\Centrifugo\CentrifugoBroadcaster::class, $driver);
    }

    public function testConfigurationIsApplied(): void
    {
        $centrifugo = $this->app->make('centrifugo');

        $this->assertEquals(300, $centrifugo->getDefaultTokenExpiration());
        $this->assertFalse($centrifugo->showNodeInfo());
    }
}
