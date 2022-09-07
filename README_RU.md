<p align="center">Документация <a href="https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/2.x/README.md">EN</a> | <b>RU</b></p>

<p align="center">
<a href="https://github.com/Opekunov/laravel-centrifugo-broadcaster/releases"><img src="https://img.shields.io/github/release/Opekunov/laravel-centrifugo-broadcaster.svg?style=flat-square" alt="Latest Version"></a>
<a href="https://github.styleci.io/repos/372425291?branch=master"><img src="https://github.styleci.io/repos/372425291/shield?branch=master" alt="StyleCI"></a>
<a href="https://scrutinizer-ci.com/g/Opekunov/laravel-centrifugo-broadcaster/?branch=master"><img src="https://scrutinizer-ci.com/g/Opekunov/laravel-centrifugo-broadcaster/badges/quality-score.png?b=master" alt="StyleCI"></a>
<a href="https://packagist.org/packages/opekunov/laravel-centrifugo-broadcaster"><img src="https://img.shields.io/packagist/dt/opekunov/laravel-centrifugo-broadcaster.svg?style=flat-square" alt="Total Downloads"></a>
<a href="https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/master/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="Software License"></a>
</p>

<h1 align="center">Laravel Centrifugo 4 Broadcaster</h1>
<h2 align="center">Centrifugo 4 broadcast драйвер для Laravel 8.75 - 9.x </h2>

> Для Centrifugo 2.8 - 3.x используйте [версию 1.2.6](https://github.com/Opekunov/laravel-centrifugo-broadcaster/tree/master)

## Особенности

- Совместимость с последней версией [Centrifugo 4.x](https://github.com/centrifugal/centrifugo/) 🚀
- Обертка над [Centrifugo HTTP API](https://centrifugal.dev/docs/server/server_api/) 🔌
- Аутентификация с помощью токена JWT (HMAC) 🗝️

## Требования

- PHP >= 8.0
- Laravel 8.75 - 9.x
- guzzlehttp/guzzle 6 - 7
- Centrifugo Сервер 4.x или новее (см. [здесь](https://github.com/centrifugal/centrifugo))

## Установка

Установить через composer, выполнив команду в консоле:

```bash
composer req opekunov/laravel-centrifugo-broadcaster
```

Откройте ваш `config/app.php` и раскомментируйте следующую строку:

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

Откройте ваш `config/broadcasting.php` и добавьте туда новое подключение (`connections`):

```php
return [

        // .... //
    
        'centrifugo' => [
            'driver' => 'centrifugo',
            'secret'  => env('CENTRIFUGO_SECRET'),
            'apikey'  => env('CENTRIFUGO_APIKEY'),
            'api_path' => env('CENTRIFUGO_API_PATH', '/api'), // Centrifugo api endpoint (по умолчанию '/api')
            'url'     => env('CENTRIFUGO_URL', 'http://localhost:8000'), // Centrifugo api url
            'verify'  => env('CENTRIFUGO_VERIFY', false), // Проверка ssl хоста если centrifugo использует его
            'ssl_key' => env('CENTRIFUGO_SSL_KEY', null), // Самоподписанный SSl ключ для хоста (require verify=true),
            'show_node_info' => env('CENTRIFUGO_SHOW_NODE_INFO', false), // Показывать информацию о ноде в ответе
            'timeout' => env('CENTRIFUGO_TIMEOUT', 3), // Общее время ожидания запроса к centrifugo api в секундах (по умолчанию 3)
            'tries' => env('CENTRIFUGO_TRIES', 1), //Количество повторов запроса в случае неудачи (по умолчанию 1)
            'token_expire_time' => env('CENTRIFUGO_TOKEN_EXPIRE', 120), //Базовое время, через которое истечет токен. Используется в подписках на каналы /broadcasting/auth
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
CENTRIFUGO_API_PATH=/api
CENTRIFUGO_SHOW_NODE_INFO=false
CENTRIFUGO_TIMEOUT=10
CENTRIFUGO_TRIES=1
CENTRIFUGO_TOKEN_EXPIRE=120
```

Не забудьте изменить параметр `BROADCAST_DRIVER` в файле .env!

```
BROADCAST_DRIVER=centrifugo
```

## Базовое использование

Настройте ваш сервер Centrifugo, детальнее
в [официальной документации](https://centrifugal.dev/docs/getting-started/installation)

Для отправки событий, почитайте [официальную документацию для Laravel](https://laravel.com/docs/9.x/broadcasting)

### Пример аутентификации для канала:

Laravel

```php
// routes/channels.php

// ВАЖНО. В Centrifugo 4 символ '$' перед приватным каналом считается устаревшим. Не используйте его. https://centrifugal.dev/docs/server/channels#private-channel-prefix- 
Broadcast::channel('private:channel', function (){
    // Логика авторизации, пример:
    return \Auth::user()->group === 'private-channel-group';
});

Broadcast::channel('public:channel', function (){
    return true;
});
```

Frontend. Смотрите документацию [centrifugal/centrifuge-js](https://github.com/centrifugal/centrifuge-js)

```js
// Пример:
import {Centrifuge} from 'centrifuge';

// Устанавливаем базовый путь Laravel broadcasting.
// Не забудьте добавить 'path' => [..., 'broadcasting/auth'] в файл cors.php  вашего приложения
const subscribeTokenEndpoint = 'http://127.0.0.1/broadcasting/auth'

const centrifuge = new Centrifuge('ws://localhost:8001/connection/websocket', {
  //CONNECTION_TOKEN необходимо получить через Centrifuge::generateConnectionToken(...)
  token: 'CONNECTION_TOKEN'
})

// Устанавливаем подписку
const sub = centrifuge.newSubscription('test:test', {
  getToken: function (ctx) {
    return customGetToken(subscribeTokenEndpoint, ctx);
  },
})

// Получение токена подписки от вашего Laravel приложения.
// Важно! В этом примере получение токена подписки реализуется через базовый fetch() без передачи параметров для идентификации пользователя в вашем Laravel приложении. Используйте методы подходящие вашему приложению
function customGetToken(endpoint, ctx) {
  return new Promise((resolve, reject) => {
    fetch(endpoint, {
      method: 'POST',
      headers: new Headers({'Content-Type': 'application/json'}),
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
        return new Channel('public:chat');
        // или return new PrivateChannel('private:chat');
        // в Centrifuge 4 все каналы являются защищенными, а префикс '$' считается устаревшим. https://centrifugal.dev/docs/server/channels#private-channel-prefix-
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
        //или centrifugo()
        
        // Отправить сообщение в канал
        $centrifugo->publish('news', ['message' => 'Hello world']);

        // Сгенерировать токен для подключения
        $token = $centrifugo->generateConnectionToken((string)Auth::id(), 0, [
            'name' => Auth::user()->name,
        ]);

        // Сгенерировать токен подписки
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

| Название                                                                                                                              | Описание                                                                                            |
|---------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------|
| publish(string $channel, array $data)                                                                                                 | Отправка сообщения в канал                                                                          |
| broadcast(array $channels, array $data)                                                                                               | Отправить сообщение в несколько каналов.                                                            |
| publishMany(array $data)                                                                                                              | Отправка нескольких сообщений на несколько каналов. $data - массив массивов данных [канал, данные]  |
| presence(string $channel)                                                                                                             | Получите информацию о присутствии в канале (все клиенты в настоящее время подписаны на этот канал). |
| presenceStats(string $channel)                                                                                                        | Получите краткую информацию о канале (количество клиентов).                                         |
| history(string $channel)                                                                                                              | Получить информацию об истории канала (список последних сообщений, отправленных в канал).           |
| historyRemove(string $channel)                                                                                                        | Удалить информацию из истории канала.                                                               |
| unsubscribe(string $channel, string $user)                                                                                            | Отписать пользователя от канала.                                                                    |
| disconnect(string $userId)                                                                                                            | Отключить пользователя по его ID.                                                                   |
| channels()                                                                                                                            | Cписок текущих активных каналов.                                                                    |
| info()                                                                                                                                | Статистическая информация о запущенных серверных узлах.                                             |
| generateConnectionToken(string&#124;int $userId, int&#124;Carbon $exp = 0, array $info = [])                                          | Генерация токена для подключения                                                                    |
| generateSubscriptionToken(string&#124;int $userId, string $channel, int&#124;Carbon $exp = 0, array $info = [], array $override = []) | Генерация приватного токена для приватного канала                                                   |

## Лицензия

Лицензия MIT. Пожалуйста
прочитайте [Файл лицензии](https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/master/LICENSE) для получения
дополнительной информации.
