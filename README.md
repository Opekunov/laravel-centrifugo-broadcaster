<p align="center">Documentation <b>EN</b> | <a href="https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/2.x/README_RU.md">RU</a></p>

<p align="center">
<a href="https://github.com/Opekunov/laravel-centrifugo-broadcaster/releases"><img src="https://img.shields.io/github/release/Opekunov/laravel-centrifugo-broadcaster.svg?style=flat-square" alt="Latest Version"></a>
<a href="https://github.styleci.io/repos/372425291?branch=master"><img src="https://github.styleci.io/repos/372425291/shield?branch=master" alt="StyleCI"></a>
<a href="https://scrutinizer-ci.com/g/Opekunov/laravel-centrifugo-broadcaster/?branch=master"><img src="https://scrutinizer-ci.com/g/Opekunov/laravel-centrifugo-broadcaster/badges/quality-score.png?b=master" alt="StyleCI"></a>
<a href="https://packagist.org/packages/opekunov/laravel-centrifugo-broadcaster"><img src="https://img.shields.io/packagist/dt/opekunov/laravel-centrifugo-broadcaster.svg?style=flat-square" alt="Total Downloads"></a>
<a href="https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/master/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="Software License"></a>
</p>

<h1 align="center">Laravel Centrifugo 5-6 Broadcaster</h1>
<h2 align="center">Centrifugo 5-6 broadcast driver for Laravel 8.75 - 12.x </h2>

> For Centrifugo 4.x use [version 2.x](https://github.com/Opekunov/laravel-centrifugo-broadcaster/tree/2.x)  
> For Centrifugo 2.8 - 3.x use [version 1.2.6](https://github.com/Opekunov/laravel-centrifugo-broadcaster/tree/master)

## Features

- Compatible with latest [Centrifugo 5.x and 6.x](https://github.com/centrifugal/centrifugo/) 🚀
- Uses new HTTP API format introduced in Centrifugo 5.x 📡
- Wrapper over [Centrifugo HTTP API](https://centrifugal.dev/docs/server/server_api/) 🔌
- Authentication with JWT token (HMAC algorithm) 🗝️

## Requirements

- PHP >= 8.0 (including 8.4)
- Laravel 8.75 - 12.x
- ext-json
- Centrifugo Server 5.x or newer (see [here](https://github.com/centrifugal/centrifugo))
 
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

For broadcasting events, see [official documentation of laravel](https://laravel.com/docs/broadcasting)

### Channel authentication example:

Laravel

```php
// routes/channels.php

// In Centrifugo 5+, channel namespaces are separated by ':'. The '$' prefix is not used.
Broadcast::channel('namespace:channel', function (){
    // Some auth logic for example:
    return \Auth::user()->group === 'private-channel-group';
});

Broadcast::channel('namespace:channel-{id}', function ($user, $id){
    return $user->id === $id;
});
```

Frontend. See documentation [centrifugal/centrifuge-js](https://github.com/centrifugal/centrifuge-js)

```bash
npm install centrifuge
```

```js
import { Centrifuge, UnauthorizedError } from 'centrifuge';

// CONNECTION_TOKEN must be obtained from Centrifugo::generateConnectionToken(...)
const client = new Centrifuge('ws://localhost:8000/connection/websocket', {
  token: 'CONNECTION_TOKEN'
});

// Connection state events
client.on('connected', (ctx) => {
  console.log('Connected:', ctx.client, 'transport:', ctx.transport);
});

client.on('disconnected', (ctx) => {
  console.log('Disconnected:', ctx.code, ctx.reason);
});

// Getting a subscription token from your Laravel application.
// Don't forget to add 'path' => [..., 'broadcasting/auth'] to your application's cors.php file
async function getSubscriptionToken(ctx) {
  const res = await fetch('/broadcasting/auth', {
    method: 'POST',
    headers: new Headers({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(ctx),
  });
  if (!res.ok) {
    if (res.status === 403) {
      throw new UnauthorizedError();
    }
    throw new Error(`Unexpected status code ${res.status}`);
  }
  const data = await res.json();
  return data.token;
}

// Subscribe to a private channel
const sub = client.newSubscription('private:chat', {
  getToken: getSubscriptionToken,
});

// Listen for messages
sub.on('publication', (ctx) => {
  console.log('New message:', ctx.data);
});

sub.on('subscribed', (ctx) => {
  console.log('Subscribed to', ctx.channel);
});

sub.subscribe();
client.connect();
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
        $token = $centrifugo->generateSubscriptionToken((string)Auth::id(), 'channel', $expire, [
            'name' => Auth::user()->name,
        ]);

        //Get a list of currently active channels.
        $centrifugo->channels();

        //Get channel presence information (all clients currently subscribed on this channel).
        $centrifugo->presence('news');

    }
}
```

### History example

Centrifugo allows you to retrieve the history of messages in a channel (the channel must have history configured on the server side).

**Backend** (Laravel):

```php
// Get the last 10 messages from the channel
$history = $centrifugo->history('chat:room1', limit: 10);

// Get messages in reverse order (newest first)
$history = $centrifugo->history('chat:room1', limit: 10, reverse: true);

// Pagination: get messages from a specific position
$history = $centrifugo->history('chat:room1', limit: 50, offset: 100, epoch: 'EPOCH');

// Remove channel history
$centrifugo->historyRemove('chat:room1');
```

**Frontend** (centrifuge-js):

```js
const sub = client.newSubscription('chat:room1');

sub.on('subscribed', async (ctx) => {
  // Get the last 50 messages
  const history = await sub.history({ limit: 50 });
  history.publications.forEach((pub) => {
    console.log('Message:', pub.data, 'offset:', pub.offset);
  });

  // Get messages since a specific position (for pagination)
  const newMessages = await sub.history({
    since: { offset: history.offset, epoch: history.epoch },
    limit: 100,
  });

  // Get messages in reverse order (newest first)
  const latest = await sub.history({ limit: 10, reverse: true });
});

sub.subscribe();
```

### Presence example

Presence allows you to see which users are currently subscribed to a channel (must be enabled in Centrifugo server config).

**Backend** (Laravel):

```php
// Get full presence info (all clients with their data)
$presence = $centrifugo->presence('chat:room1');

// Get short presence stats (number of clients and unique users)
$stats = $centrifugo->presenceStats('chat:room1');
```

**Frontend** (centrifuge-js):

```js
const sub = client.newSubscription('chat:room1', {
  joinLeave: true, // Enable join/leave events
});

// Track who is online
sub.on('subscribed', async (ctx) => {
  const presence = await sub.presence();
  for (const [clientId, info] of Object.entries(presence.clients)) {
    console.log(`Online: ${info.user} (${clientId})`);
  }
});

// Real-time join/leave events
sub.on('join', (ctx) => {
  console.log('User joined:', ctx.info.user);
});

sub.on('leave', (ctx) => {
  console.log('User left:', ctx.info.user);
});

sub.subscribe();
```

### Server-side subscribe/unsubscribe example

You can manage subscriptions from the backend without client involvement:

```php
// Subscribe a user to a channel from the server side
$centrifugo->subscribe('notifications:user1', 'user1');

// With additional info and data
$centrifugo->subscribe('notifications:user1', 'user1',
    info: ['role' => 'admin'],
    data: ['message' => 'Welcome!']
);

// Unsubscribe a user from a channel
$centrifugo->unsubscribe('notifications:user1', 'user1');

// Disconnect a user entirely
$centrifugo->disconnect('user1');
```

### Available methods

| Name                                                                                                                                  | Description                                                                           |
|---------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------|
| publish(string $channel, array $data)                                                                                                 | Send message into channel.                                                            |
| broadcast(array $channels, array $data)                                                                                               | Send message into multiple channel.                                                   |
| publishMany(array $data)                                                                                                              | Send multiple data to multiple channels. $data - array of data arrays [channel, data] |
| presence(string $channel)                                                                                                             | Get channel presence information (all clients currently subscribed on this channel).  |
| presenceStats(string $channel)                                                                                                        | Get channel presence information in short form (number of clients).                   |
| history(string $channel, int $limit = 0, ?int $offset = null, ?string $epoch = null, bool $reverse = false)                           | Get channel history information (list of last messages sent into channel).            |
| historyRemove(string $channel)                                                                                                        | Remove channel history information.                                                   |
| subscribe(string $channel, string $user, array $info = [], array $data = [])                                                          | Subscribe user to channel (server-side).                                              |
| unsubscribe(string $channel, string $user)                                                                                            | Unsubscribe user from channel.                                                        |
| disconnect(string $userId)                                                                                                            | Disconnect user by it's ID.                                                           |
| rpc(string $method, array $data = [])                                                                                                 | Remote procedure call.                                                                |
| channels(string $pattern = '')                                                                                                        | Get channels information (list of currently active channels).                         |
| info()                                                                                                                                | Get stats information about running server nodes.                                     |
| generateConnectionToken(string&#124;int $userId, int&#124;Carbon $exp = 0, array $info = [], array $channels = [])                    | Generate connection token.                                                            |
| generateSubscriptionToken(string&#124;int $userId, string $channel, int&#124;Carbon $exp = 0, array $info = [], array $override = []) | Generate subscription token.                                                          |

## License

The MIT License (MIT). Please
see [License File](https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/master/LICENSE) for more information.
