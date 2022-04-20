<?php

namespace Opekunov\Centrifugo\Tests;

use Opekunov\Centrifugo\Centrifugo;
use Opekunov\Centrifugo\CentrifugoServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * @var Centrifugo $centrifuge
     */
    protected $centrifuge;

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
            'secret' => 'd55bf295-bee6-4259-8912-0a58f44ed30e',
            'apikey' => '0c951315-be0e-4516-b99e-05e60b0cc307',
            'url' => 'http://localhost:8000',
        ]);
    }
}
