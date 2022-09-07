<?php

namespace Opekunov\Centrifugo\Tests;

use Opekunov\Centrifugo\Centrifugo;
use Opekunov\Centrifugo\CentrifugoServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * @var Centrifugo
     */
    protected Centrifugo $centrifuge;

    public function setUp(): void
    {
        parent::setUp();
        $this->centrifuge = $this->app->make('centrifugo');
    }

    protected function getPackageProviders($app): array
    {
        return [
            CentrifugoServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('broadcasting.default', 'centrifugo');
        $app['config']->set('broadcasting.connections.centrifugo', [
            'driver' => 'centrifugo',
            'secret' => 'bbe7d157-a253-4094-9759-06a8236543f9',
            'apikey' => 'd7627bb6-2292-4911-82e1-615c0ed3eebb',
            'url'    => 'http://host.docker.internal:8001',
        ]);
    }
}
