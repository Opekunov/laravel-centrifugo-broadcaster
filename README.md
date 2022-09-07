<p align="center">Documentation <b>EN</b> | <a href="https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/master/README_RU.md">RU</a></p>

<p align="center">
<a href="https://github.com/Opekunov/laravel-centrifugo-broadcaster/releases"><img src="https://img.shields.io/github/release/Opekunov/laravel-centrifugo-broadcaster.svg?style=flat-square" alt="Latest Version"></a>
<a href="https://github.styleci.io/repos/372425291?branch=master"><img src="https://github.styleci.io/repos/372425291/shield?branch=master" alt="StyleCI"></a>
<a href="https://scrutinizer-ci.com/g/Opekunov/laravel-centrifugo-broadcaster/?branch=master"><img src="https://scrutinizer-ci.com/g/Opekunov/laravel-centrifugo-broadcaster/badges/quality-score.png?b=master" alt="StyleCI"></a>
<a href="https://packagist.org/packages/opekunov/laravel-centrifugo-broadcaster"><img src="https://img.shields.io/packagist/dt/opekunov/laravel-centrifugo-broadcaster.svg?style=flat-square" alt="Total Downloads"></a>
<a href="https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/master/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="Software License"></a>
</p>

<h1 align="center">Laravel Centrifugo 4 Broadcaster</h1>
<h2 align="center">Centrifugo 4 broadcast driver for Laravel 8.75 - 9.x </h2>

## Features

- Compatible with latest [Centrifugo 4.x](https://github.com/centrifugal/centrifugo/) 🚀
- Wrapper over [Centrifugo HTTP API](https://centrifugal.dev/docs/server/server_api/) 🔌
- Authentication with JWT token (HMAC algorithm) 🗝️

## Requirements

- PHP >= 8.0
- Laravel 8.75 - 9.x
- guzzlehttp/guzzle 6 - 7
- Centrifugo Server 4.x or newer (see [here](https://github.com/centrifugal/centrifugo))

> For Centrifugo 2.8 - 3.x use [version 1.2.6](https://github.com/Opekunov/laravel-centrifugo-broadcaster/tree/master)
> 
## Installation

Require this package with composer:

```bash
composer req opekunov/laravel-centrifugo-broadcaster
```

Open your `config/app.php` and uncomment this line:

```php
return [

    // .... //
    
    'providers' => [    
        // Uncomment BroadcastServiceProvider
        App\Providers\BroadcastServiceProvider::class,
    ],
    
    // .... //
    
];
```

Open your `config/broadcasting.php` and add new `connection` like this:

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
            'tries' => env('CENTRIFUGO_TRIES', 1), //Number of times to repeat the request, in case of failure (the default is 1)
            'token_expire_time' => env('CENTRIFUGO_TOKEN_EXPIRE', 120), //Default token expire time. Used in channel subscriptions /broadcasting/auth
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
CENTRIFUGO_TOKEN_EXPIRE=120
```

Don't forget to change `BROADCAST_DRIVER` setting in .env file!

```
BROADCAST_DRIVER=centrifugo
```

## Basic Usage

To configure Centrifugo server, read [official documentation](https://centrifugal.dev/docs/getting-started/installation)

For broadcasting events, see [official documentation of laravel](https://laravel.com/docs/9.x/broadcasting)

### Channel authentication example:

Laravel

```php
// routes/channels.php

// IMPORTANT. In Centrifugo 4, the '$' character in front of the private channel is considered obsolete. Do not use it. https://centrifugal.dev/docs/server/channels#private-channel-prefix-
Broadcast::channel('namespace:channel', function (){
    // Some auth logic for example:
    return \Auth::user()->group === 'private-channel-group';
});

Broadcast::channel('namespace:channel-{id}', function ($user, $id){
    return $user->id === $id;
});
```

Frontend. See documentation [centrifugal/centrifuge-js](https://github.com/centrifugal/centrifuge-js)

```js
// Example:
import { Centrifuge } from 'centrifuge';

// Set the base path of Laravel broadcasting.
// Don't forget to add 'path' => [..., 'broadcasting/auth'] to your application's cors.php file
const subscribeTokenEndpoint = 'http://127.0.0.1/broadcasting/auth'

const centrifuge = new Centrifuge('ws://localhost:8001/connection/websocket', {
  //CONNECTION_TOKEN must be obtained from Centrifuge::generateConnectionToken(...)
  token: 'CONNECTION_TOKEN'
})

// Set the subscription
const sub = centrifuge.newSubscription('test:test', {
  getToken: function (ctx) {
    return customGetToken(subscribeTokenEndpoint, ctx);
  },
})

// Getting a subscription token from your Laravel application.
// Important: In this example, getting a subscription token is implemented through basic fetch() without passing parameters to identify the user in your Laravel application. Use methods appropriate for your application
function getToken(endpoint, ctx) {
  return new Promise((resolve, reject) => {
    fetch(endpoint, {
      method: 'POST',
      headers: new Headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(ctx)
    })
      .then(res => {
        if (!res.ok) {
          throw new Error(`Unexpected status code ${res.status}`);
        }
        return res.json();
      })
      .then(data => {
        resolve(data.token);
      })
      .catch(err => {
        reject(err);
      });
  });
}

sub.subscribe();
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
        return new Channel('public:chat');
        // or return new PrivateChannel('private:chat');
        // in Centrifuge 4 all channels are protected, and the '$' prefix is considered obsolete. https://centrifugal.dev/docs/server/channels#private-channel-prefix-
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
        //or centrifugo()
        
        // Send message into channel
        $centrifugo->publish('news', ['message' => 'Hello world']);

        // Generate connection token
        $token = $centrifugo->generateConnectionToken((string)Auth::id(), 0, [
            'name' => Auth::user()->name,
        ]);

        // Generate subscription token
        $expire = now()->addDay(); //or you can use Unix: $expire = time() + 60 * 60 * 24; 
        $apiSign = $centrifugo->generateSubscriptionToken((string)Auth::id(), 'channel', $expire, [
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

| Name                                                                                                                                  | Description                                                                           |
|---------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------|
| publish(string $channel, array $data)                                                                                                 | Send message into channel.                                                            |
| broadcast(array $channels, array $data)                                                                                               | Send message into multiple channel.                                                   |
| publishMany(array $data)                                                                                                              | Send multiple data to multiple channels. $data - array of data arrays [channel, data] |
| presence(string $channel)                                                                                                             | Get channel presence information (all clients currently subscribed on this channel).  |
| presenceStats(string $channel)                                                                                                        | Get channel presence information in short form (number of clients).                   |
| history(string $channel)                                                                                                              | Get channel history information (list of last messages sent into channel).            |
| historyRemove(string $channel)                                                                                                        | Remove channel history information.                                                   
| unsubscribe(string $channel, string $user)                                                                                            | Unsubscribe user from channel.                                                        |
| disconnect(string $userId)                                                                                                            | Disconnect user by it's ID.                                                           |
| channels()                                                                                                                            | Get channels information (list of currently active channels).                         |
| info()                                                                                                                                | Get stats information about running server nodes.                                     |
| generateConnectionToken(string&#124;int $userId, int&#124;Carbon $exp = 0, array $info = [])                                          | Generate connection token.                                                            |
| generateSubscriptionToken(string&#124;int $userId, string $channel, int&#124;Carbon $exp = 0, array $info = [], array $override = []) | Generate subscription token.                                                          |

## License

The MIT License (MIT). Please
see [License File](https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/master/LICENSE) for more information.
