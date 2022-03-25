<p align="center">Документация <a href="https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/master/README.md">EN</a> | <b>RU</b></p>

<p align="center">
<a href="https://github.com/Opekunov/laravel-centrifugo-broadcaster/releases"><img src="https://img.shields.io/github/release/Opekunov/laravel-centrifugo-broadcaster.svg?style=flat-square" alt="Latest Version"></a>
<a href="https://github.styleci.io/repos/372425291?branch=master"><img src="https://github.styleci.io/repos/372425291/shield?branch=master" alt="StyleCI"></a>
<a href="https://scrutinizer-ci.com/g/Opekunov/laravel-centrifugo-broadcaster/?branch=master"><img src="https://scrutinizer-ci.com/g/Opekunov/laravel-centrifugo-broadcaster/badges/quality-score.png?b=master" alt="StyleCI"></a>
<a href="https://packagist.org/packages/opekunov/laravel-centrifugo-broadcaster"><img src="https://img.shields.io/packagist/dt/opekunov/laravel-centrifugo-broadcaster.svg?style=flat-square" alt="Total Downloads"></a>
<a href="https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/master/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="Software License"></a>
</p>

<h1 align="center">Laravel Centrifugo Broadcaster</h1>
<h2 align="center">Centrifugo broadcast драйвер для Laravel 5.6 - 8 </h2>

## Введение

Centrifugo broadcaster для laravel это форк
репозитория [laravel-centrifuge](https://github.com/denis660/laravel-centrifuge), based on:

- [LaraComponents/centrifugo-broadcaster](https://github.com/LaraComponents/centrifugo-broadcaster)
- [centrifugal/phpcent](https://github.com/centrifugal/phpcent)

## Особенности

- Совместимость с последней версией [Centrifugo 3.1.x](https://github.com/centrifugal/centrifugo/) 🚀
- Обертка над [Centrifugo HTTP API](https://centrifugal.github.io/centrifugo/server/http_api/) 🔌
- Аутентификация с помощью токена JWT (HMAC алгоритм) для анонимного, авторизованного пользователя и приватного канала
  🗝️

## Требования

- PHP >= 7.3
- Framework Laravel 5.6 - 9.x
- guzzlehttp/guzzle 6 - 7
- Centrifugo Сервер 2.8.2 или новее (см. [здесь](https://github.com/centrifugal/centrifugo))

## Установка

Установить через composer, выполнив команду в консоле:

```bash
composer req opekunov/laravel-centrifugo-broadcaster
```

Откройте ваш `config/app.php` и добавьте следующее в раздел providers:

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

Откройте ваш `config/broadcasting.php` и добавьте туда новое подключение:

```php
return [

        // .... //
    
        'centrifugo' => [
            'driver' => 'centrifugo',
            'secret'  => env('CENTRIFUGO_SECRET'),
            'apikey'  => env('CENTRIFUGO_APIKEY'),
            'url'     => env('CENTRIFUGO_URL', 'http://localhost:8000'), // centrifugo api url
            'verify'  => env('CENTRIFUGO_VERIFY', false), // Verify host ssl if centrifugo uses this
            'ssl_key' => env('CENTRIFUGO_SSL_KEY', null), // Self-Signed SSl Key for Host (require verify=true),
            'show_node_info' => env('CENTRIFUGO_SHOW_NODE_INFO', false), // Show node info in response with auth token
            'timeout' => env('CENTRIFUGO_TIMEOUT', 10), // Float describing the total timeout of the request to websocket in seconds. Use 0 to wait indefinitely (the default is 10)
        ],
        
       // .... //
       
];
```

Также вы должны добавить эти две строчки в ваш `.env` файл:

```
CENTRIFUGO_SECRET=token_hmac_secret_key-from-centrifugo-config
CENTRIFUGO_APIKEY=api_key-from-centrifugo-config
CENTRIFUGO_URL=http://localhost:8000
```

Эти строки необязательны:

```
CENTRIFUGO_SSL_KEY=/etc/ssl/some.pem
CENTRIFUGO_VERIFY=false
CENTRIFUGO_SHOW_NODE_INFO=false
CENTRIFUGO_TIMEOUT=10
```

Не забудьте изменить параметр `BROADCAST_DRIVER` в файле .env!

```
BROADCAST_DRIVER=centrifugo
```

## Базовое использование

Настройте ваш сервер Centrifugo, детальнее в [официальной документации](https://centrifugal.github.io/centrifugo/)

Для отправки событий, почитайте [официальную документацию для Laravel](https://laravel.com/docs/8.x/broadcasting)

### Пример аутентификации:

Laravel

```php
// routes/channels.php

// Приватный канал. Вы можете использовать его без префикса $
Broadcast::channel('private:channel', function (){
    // Логика авторизации, пример:
    return \Auth::user()->group === 'private-channel-group';
});

// Публичный канал
Broadcast::channel('public:channel', function (){
    return true;
});
```

Frontend. Смотрите документацию [centrifugal/centrifuge-js](https://github.com/centrifugal/centrifuge-js)

```js
// устанавливаем базовый путь Laravel broadcasting 
var config = {subscribeEndpoint: '/broadcasting/auth'};
var centrifuge = new Centrifuge('wss://centrifuge.example.com/connection/websocket', config);

// CONNECTION_TOKEN необходимо получить через generateConnectionToken()
centrifuge.setToken("CONNECTION_TOKEN");
var subscription = centrifuge.subscribe("$private:channel", function (ctx) {
		console.log('ctx');
});

centrifuge.connect();
```

### Пример вещания

Создайте событие (для примера - SendMessage) с помощью artisan `php artisan make:event SendMessageEvent`

```php
<?php
// App/Events/SendMessageEvent.php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

//Используйте "implements ShouldBroadcast" если хотите добавлять событие в очереди
class SendMessageEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var string Текст сообщения
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
        //Пример имени события. Будет отображено Web Socket JSON
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
        // Пример отправки в приватный канал. Имя приватного канала должны быть написано без префикса $
        return new PrivateChannel('private:chat');
        
        // Пример отправки в публичный канал
        // return new Channel('public:chat');
    }
}

```

Простой пример использования клиента:

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
        //или $centrifugo = new Centrifugo();
        
        // Отправить сообщение в канал
        $centrifugo->publish('news', ['message' => 'Hello world']);

        // Сгенерировать токен для подключения
        $token = $centrifugo->generateConnectionToken((string)Auth::id(), 0, [
            'name' => Auth::user()->name,
        ]);

        // Сгенерировать токен для подключения к приватному каналу
        $expire = now()->addDay(); //или вы можете использовать формат Unix: $expire = time() + 60 * 60 * 24; 
        $apiSign = $centrifugo->generatePrivateChannelToken((string)Auth::id(), 'channel', $expire, [
            'name' => Auth::user()->name,
        ]);

        //Получить список активных каналов.
        $centrifugo->channels();

        //Получить информацию о канале news, список активных клиентов
        $centrifugo->presence('news');

    }
}
```

### Методы

| Название | Описание |
|------|-------------|
| publish(string $channel, array $data) | Отправка сообщения в канал |
| broadcast(array $channels, array $data) | Отправить сообщение в несколько каналов. |
| presence(string $channel) | Получите информацию о присутствии в канале (все клиенты в настоящее время подписаны на этот канал). |
| presenceStats(string $channel) | Получите краткую информацию о канале (количество клиентов).|
| history(string $channel) | Получить информацию об истории канала (список последних сообщений, отправленных в канал). |
| historyRemove(string $channel) | Удалить информацию из истории канала. |
| unsubscribe(string $channel, string $user) | Отписать пользователя от канала. |
| disconnect(string $user_id) | Отключить пользователя по его ID. |
| channels() | Cписок текущих активных каналов. |
| info() | Статистическая информация о запущенных серверных узлах. |
| generateConnectionToken(string $userId, int $exp, array $info)  | Генерация токена для подключения |
| generatePrivateChannelToken(string $client, string $channel, int $exp, array $info) | Генерация приватного токена для приватного канала |

## Лицензия

Лицензия MIT. Пожалуйста
прочитайте [Файл лицензии](https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/master/LICENSE) для получения
дополнительной информации.
