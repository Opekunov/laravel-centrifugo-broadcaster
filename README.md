<p align="center">Documentation <b>EN</b> | <a href="https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/master/README_RU.md">RU</a></p>

<p align="center">
<a href="https://github.com/Opekunov/laravel-centrifugo-broadcaster/releases"><img src="https://img.shields.io/github/release/Opekunov/laravel-centrifugo-broadcaster.svg?style=flat-square" alt="Latest Version"></a>
<a href="https://github.styleci.io/repos/372425291?branch=master"><img src="https://github.styleci.io/repos/372425291/shield?branch=master" alt="StyleCI"></a>
<a href="https://scrutinizer-ci.com/g/Opekunov/laravel-centrifugo-broadcaster/?branch=master"><img src="https://scrutinizer-ci.com/g/Opekunov/laravel-centrifugo-broadcaster/badges/quality-score.png?b=master" alt="StyleCI"></a>
<a href="https://packagist.org/packages/opekunov/laravel-centrifugo-broadcaster"><img src="https://img.shields.io/packagist/dt/opekunov/laravel-centrifugo-broadcaster.svg?style=flat-square" alt="Total Downloads"></a>
<a href="https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/master/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="Software License"></a>
</p>

<h1 align="center">Laravel Centrifugo Broadcaster</h1>
<h2 align="center">Centrifugo broadcast driver for Laravel 8.75 - 9.x </h2>

## Introduction

Centrifugo broadcaster for laravel is fork of [laravel-centrifuge](https://github.com/denis660/laravel-centrifuge),
based on:

- [LaraComponents/centrifuge-broadcaster](https://github.com/LaraComponents/centrifuge-broadcaster)
- [centrifugal/phpcent](https://github.com/centrifugal/phpcent)

## Features

- Compatible with latest [Centrifugo 3.1.x](https://github.com/centrifugal/centrifugo/) 🚀
- Wrapper over [Centrifugo HTTP API](https://centrifugal.github.io/centrifugo/server/http_api/) 🔌
- Authentication with JWT token (HMAC algorithm) for anonymous, authenticated user and private channel 🗝️

## Requirements

- PHP >= 7.4
- Laravel 8.75 - 9.x
- guzzlehttp/guzzle 6 - 7
- Centrifugo Server 2.8.2 or newer (see [here](https://github.com/centrifugal/centrifugo))

## Installation

Require this package with composer:

```bash
composer req opekunov/laravel-centrifugo-broadcaster:1.2.6
```

Open your `config/app.php` and add the following to the providers array:

```php
return [

    // .... //
    
    'providers' => [
        // Add service provider ( Laravel 5.4 or below )
        Opekunov\Centrifugo\CentrifugoServiceProvider::class,
    
        // And uncomment BroadcastServiceProvider
        App\Providers\BroadcastServiceProvider::class,
    ],
    
    // .... //
    
];
```

Open your `config/broadcasting.php` and add new connection like this:

```php
return [

        // .... //
    
        'centrifugo' => [
            'driver' => 'centrifugo',
            'secret'  => env('CENTRIFUGO_SECRET'),
            'apikey'  => env('CENTRIFUGO_APIKEY'),
            'api_path' => env('CENTRIFUGO_API_PATH', '/api'), // Centrifugo api endpoint (default '/api')
            'url'     => env('CENTRIFUGO_URL', 'http://localhost:8000'), // centrifugo api url
            'verify'  => env('CENTRIFUGO_VERIFY', false), // Verify host ssl if centrifugo uses this
            'ssl_key' => env('CENTRIFUGO_SSL_KEY', null), // Self-Signed SSl Key for Host (require verify=true),
            'show_node_info' => env('CENTRIFUGO_SHOW_NODE_INFO', false), // Show node info in response with auth token
            'timeout' => env('CENTRIFUGO_TIMEOUT', 3), // Float describing the total timeout of the request to centrifugo api in seconds. Use 0 to wait indefinitely (the default is 3)
            'tries' => env('CENTRIFUGO_TRIES', 1) //Number of times to repeat the request, in case of failure (the default is 1)
        ],
        
       // .... //
       
];
```

Also you should add these two lines to your `.env` file:

```
CENTRIFUGO_SECRET=token_hmac_secret_key-from-centrifugo-config
CENTRIFUGO_APIKEY=api_key-from-centrifugo-config
CENTRIFUGO_URL=http://localhost:8000
```

These lines are optional:

```
CENTRIFUGO_SSL_KEY=/etc/ssl/some.pem
CENTRIFUGO_VERIFY=false
CENTRIFUGO_API_PATH=/api
CENTRIFUGO_SHOW_NODE_INFO=false
CENTRIFUGO_TIMEOUT=10
CENTRIFUGO_TRIES=1
```

Don't forget to change `BROADCAST_DRIVER` setting in .env file!

```
BROADCAST_DRIVER=centrifugo
```

## Basic Usage

To configure Centrifugo server, read [official documentation](https://centrifugal.github.io/centrifugo/)

For broadcasting events, see [official documentation of laravel](https://laravel.com/docs/8.x/broadcasting)

### Authentication example:

Laravel

```php
// routes/channels.php

// Private channel. You can write channel name without prefix $
Broadcast::channel('private:channel', function (){
    // Some auth logic for example:
    return \Auth::user()->group === 'private-channel-group';
});

// Public channel
Broadcast::channel('public:channel', function (){
    return true;
});
```

Frontend. See documentation [centrifugal/centrifuge-js](https://github.com/centrifugal/centrifuge-js)

```js
// set default Laravel broadcasting auth path 
var config = {subscribeEndpoint: '/broadcasting/auth'};
var centrifuge = new Centrifuge('wss://centrifuge.example.com/connection/websocket', config);

// CONNECTION_TOKEN must be obtained via generateConnectionToken ()
centrifuge.setToken("CONNECTION_TOKEN");
var subscription = centrifuge.subscribe("$private:channel", function (ctx) {
		console.log('ctx');
});

centrifuge.connect();
```

### Broadcasting example

Create event (for example SendMessage) with artisan `php artisan make:event SendMessageEvent`

```php
<?php
// App/Events/SendMessageEvent.php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

//Use "implements ShouldBroadcast" if you want add event to queue
class SendMessageEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var string Message text
     */
    private $messageText;

    public function __construct(string $messageText)
    {
        $this->messageText = $messageText;
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        //example event broadcast name. Show in Web Socket JSON
        return 'message.new';
    }


    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return ['message' => $this->messageText];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // Private channel example. The name of the private channel must be written without the $ prefix
        return new PrivateChannel('private:chat');
        
        // Public chat example
        // return new Channel('public:chat');
    }
}

```

A simple client usage example:

```php
<?php
declare(strict_types = 1);

namespace App\Http\Controllers;

use Opekunov\Centrifugo\Centrifugo;
use Illuminate\Support\Facades\Auth;

class ExampleController
{

    public function example(Centrifugo $centrifugo)
    {
        //or $centrifugo = new Centrifugo();
        
        // Send message into channel
        $centrifugo->publish('news', ['message' => 'Hello world']);

        // Generate connection token
        $token = $centrifugo->generateConnectionToken((string)Auth::id(), 0, [
            'name' => Auth::user()->name,
        ]);

        // Generate private channel token
        $expire = now()->addDay(); //or you can use Unix: $expire = time() + 60 * 60 * 24; 
        $apiSign = $centrifugo->generatePrivateChannelToken((string)Auth::id(), 'channel', $expire, [
            'name' => Auth::user()->name,
        ]);

        //Get a list of currently active channels.
        $centrifugo->channels();

        //Get channel presence information (all clients currently subscribed on this channel).
        $centrifugo->presence('news');

    }
}
```

### Available methods

| Name                                                                                | Description                                                                           |
|-------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------|
| publish(string $channel, array $data)                                               | Send message into channel.                                                            |
| broadcast(array $channels, array $data)                                             | Send message into multiple channel.                                                   |
| publishMany(array $data)                                                            | Send multiple data to multiple channels. $data - array of data arrays [channel, data] |
| presence(string $channel)                                                           | Get channel presence information (all clients currently subscribed on this channel).  |
| presenceStats(string $channel)                                                      | Get channel presence information in short form (number of clients).                   |
| history(string $channel)                                                            | Get channel history information (list of last messages sent into channel).            |
| historyRemove(string $channel)                                                      | Remove channel history information.                                                   
| unsubscribe(string $channel, string $user)                                          | Unsubscribe user from channel.                                                        |
| disconnect(string $user_id)                                                         | Disconnect user by it's ID.                                                           |
| channels()                                                                          | Get channels information (list of currently active channels).                         |
| info()                                                                              | Get stats information about running server nodes.                                     |
| generateConnectionToken(string $userId, int $exp, array $info)                      | Generate connection token.                                                            |
| generatePrivateChannelToken(string $client, string $channel, int $exp, array $info) | Generate private channel token.                                                       |

## License

The MIT License (MIT). Please
see [License File](https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/master/LICENSE) for more information.
