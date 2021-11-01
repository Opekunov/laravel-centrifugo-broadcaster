<?php

declare(strict_types=1);

namespace Opekunov\Centrifugo;

use GuzzleHttp\Client as HttpClient;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\ServiceProvider;
use Opekunov\Centrifugo\Contracts\CentrifugoInterface;

class CentrifugoServiceProvider extends ServiceProvider
{
    /**
     * Add centrifugo broadcaster.
     *
     * @param \Illuminate\Broadcasting\BroadcastManager $broadcastManager
     */
    public function boot(BroadcastManager $broadcastManager)
    {
        $broadcastManager->extend('centrifugo', function ($app) {
            return new CentrifugoBroadcaster($app->make('centrifugo'));
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('centrifugo', function ($app) {
            $config = $app->make('config')->get('broadcasting.connections.centrifugo');
            $http = new HttpClient();

            return new Centrifugo($config, $http);
        });

        $this->app->alias('centrifugo', Centrifugo::class);
        $this->app->alias('centrifugo', CentrifugoInterface::class);
    }
}
