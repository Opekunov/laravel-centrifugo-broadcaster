<p align="center">Документация <a href="https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/2.x/README.md">EN</a> | <b>RU</b></p>

<p align="center">
<a href="https://github.com/Opekunov/laravel-centrifugo-broadcaster/releases"><img src="https://img.shields.io/github/release/Opekunov/laravel-centrifugo-broadcaster.svg?style=flat-square" alt="Latest Version"></a>
<a href="https://github.com/Opekunov/laravel-centrifugo-broadcaster/actions/workflows/tests.yml"><img src="https://github.com/Opekunov/laravel-centrifugo-broadcaster/actions/workflows/tests.yml/badge.svg?branch=3.x" alt="Tests"></a>
<a href="https://github.com/Opekunov/laravel-centrifugo-broadcaster/actions/workflows/code-quality.yml"><img src="https://github.com/Opekunov/laravel-centrifugo-broadcaster/actions/workflows/code-quality.yml/badge.svg?branch=3.x" alt="Code Quality"></a>
<a href="https://codecov.io/gh/Opekunov/laravel-centrifugo-broadcaster"><img src="https://codecov.io/gh/Opekunov/laravel-centrifugo-broadcaster/branch/3.x/graph/badge.svg" alt="Coverage"></a>
<a href="https://packagist.org/packages/opekunov/laravel-centrifugo-broadcaster"><img src="https://img.shields.io/packagist/dt/opekunov/laravel-centrifugo-broadcaster.svg?style=flat-square" alt="Total Downloads"></a>
<a href="https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/master/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="Software License"></a>
</p>

<h1 align="center">Laravel Centrifugo 5-6 Broadcaster</h1>
<h2 align="center">Centrifugo 5-6 broadcast драйвер для Laravel 8.75 - 12.x </h2>

> Для Centrifugo 4.x используйте [версию 2.x](https://github.com/Opekunov/laravel-centrifugo-broadcaster/releases/tag/v2.3)  
> Для Centrifugo 2.8 - 3.x используйте [версию 1.2.6](https://github.com/Opekunov/laravel-centrifugo-broadcaster/tree/master)

## Особенности

- Совместимость с последними версиями [Centrifugo 5.x и 6.x](https://github.com/centrifugal/centrifugo/) 🚀
- Использует новый формат HTTP API, введенный в Centrifugo 5.x 📡
- Обертка над [Centrifugo HTTP API](https://centrifugal.dev/docs/server/server_api/) 🔌
- Аутентификация с помощью токена JWT (HMAC) 🗝️
- Собственный HTTP-клиент без внешних зависимостей ⚡

## Требования

- PHP >= 8.0 (включая 8.4)
- Laravel 8.75 - 12.x
- Centrifugo Сервер 5.x или новее (см. [здесь](https://github.com/centrifugal/centrifugo))
- Расширение ext-json

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

Для отправки событий, почитайте [официальную документацию для Laravel](https://laravel.com/docs/broadcasting)

### Пример аутентификации для канала:

Laravel

```php
// routes/channels.php

// В Centrifugo 5+ пространства имён каналов разделяются через ':'. Префикс '$' не используется.
Broadcast::channel('private:channel', function (){
    // Логика авторизации, пример:
    return \Auth::user()->group === 'private-channel-group';
});

Broadcast::channel('public:channel', function (){
    return true;
});
```

Frontend. Смотрите документацию [centrifugal/centrifuge-js](https://github.com/centrifugal/centrifuge-js)

```bash
npm install centrifuge
```

```js
import { Centrifuge, UnauthorizedError } from 'centrifuge';

// CONNECTION_TOKEN необходимо получить через Centrifugo::generateConnectionToken(...)
const client = new Centrifuge('ws://localhost:8000/connection/websocket', {
  token: 'CONNECTION_TOKEN'
});

// События состояния подключения
client.on('connected', (ctx) => {
  console.log('Подключено:', ctx.client, 'транспорт:', ctx.transport);
});

client.on('disconnected', (ctx) => {
  console.log('Отключено:', ctx.code, ctx.reason);
});

// Получение токена подписки от вашего Laravel приложения.
// Не забудьте добавить 'path' => [..., 'broadcasting/auth'] в файл cors.php вашего приложения
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

// Подписка на приватный канал
const sub = client.newSubscription('private:chat', {
  getToken: getSubscriptionToken,
});

// Прослушивание сообщений
sub.on('publication', (ctx) => {
  console.log('Новое сообщение:', ctx.data);
});

sub.on('subscribed', (ctx) => {
  console.log('Подписан на', ctx.channel);
});

sub.subscribe();
client.connect();
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
        $token = $centrifugo->generateSubscriptionToken((string)Auth::id(), 'channel', $expire, [
            'name' => Auth::user()->name,
        ]);

        //Получить список активных каналов.
        $centrifugo->channels();

        //Получить информацию о канале news, список активных клиентов
        $centrifugo->presence('news');

    }
}
```

### Пример работы с историей

Centrifugo позволяет получать историю сообщений в канале (канал должен иметь настроенную историю на стороне сервера).

**Backend** (Laravel):

```php
// Получить последние 10 сообщений из канала
$history = $centrifugo->history('chat:room1', limit: 10);

// Получить сообщения в обратном порядке (сначала новые)
$history = $centrifugo->history('chat:room1', limit: 10, reverse: true);

// Пагинация: получить сообщения с определённой позиции
$history = $centrifugo->history('chat:room1', limit: 50, offset: 100, epoch: 'EPOCH');

// Удалить историю канала
$centrifugo->historyRemove('chat:room1');
```

**Frontend** (centrifuge-js):

```js
const sub = client.newSubscription('chat:room1');

sub.on('subscribed', async (ctx) => {
  // Получить последние 50 сообщений
  const history = await sub.history({ limit: 50 });
  history.publications.forEach((pub) => {
    console.log('Сообщение:', pub.data, 'offset:', pub.offset);
  });

  // Получить сообщения с определённой позиции (для пагинации)
  const newMessages = await sub.history({
    since: { offset: history.offset, epoch: history.epoch },
    limit: 100,
  });

  // Получить сообщения в обратном порядке (сначала новые)
  const latest = await sub.history({ limit: 10, reverse: true });
});

sub.subscribe();
```

### Пример работы с присутствием (presence)

Presence позволяет узнать, какие пользователи сейчас подписаны на канал (должно быть включено в конфигурации сервера Centrifugo).

**Backend** (Laravel):

```php
// Получить полную информацию о присутствии (все клиенты с их данными)
$presence = $centrifugo->presence('chat:room1');

// Получить краткую статистику (количество клиентов и уникальных пользователей)
$stats = $centrifugo->presenceStats('chat:room1');
```

**Frontend** (centrifuge-js):

```js
const sub = client.newSubscription('chat:room1', {
  joinLeave: true, // Включить события входа/выхода
});

// Узнать кто онлайн
sub.on('subscribed', async (ctx) => {
  const presence = await sub.presence();
  for (const [clientId, info] of Object.entries(presence.clients)) {
    console.log(`Онлайн: ${info.user} (${clientId})`);
  }
});

// События входа/выхода в реальном времени
sub.on('join', (ctx) => {
  console.log('Пользователь подключился:', ctx.info.user);
});

sub.on('leave', (ctx) => {
  console.log('Пользователь отключился:', ctx.info.user);
});

sub.subscribe();
```

### Пример серверной подписки/отписки

Вы можете управлять подписками со стороны сервера без участия клиента:

```php
// Подписать пользователя на канал со стороны сервера
$centrifugo->subscribe('notifications:user1', 'user1');

// С дополнительной информацией и данными
$centrifugo->subscribe('notifications:user1', 'user1',
    info: ['role' => 'admin'],
    data: ['message' => 'Добро пожаловать!']
);

// Отписать пользователя от канала
$centrifugo->unsubscribe('notifications:user1', 'user1');

// Отключить пользователя полностью
$centrifugo->disconnect('user1');
```

### Методы

| Название                                                                                                                              | Описание                                                                                            |
|---------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------|
| publish(string $channel, array $data)                                                                                                 | Отправка сообщения в канал                                                                          |
| broadcast(array $channels, array $data)                                                                                               | Отправить сообщение в несколько каналов.                                                            |
| publishMany(array $data)                                                                                                              | Отправка нескольких сообщений на несколько каналов. $data - массив массивов данных [канал, данные]  |
| presence(string $channel)                                                                                                             | Получите информацию о присутствии в канале (все клиенты в настоящее время подписаны на этот канал). |
| presenceStats(string $channel)                                                                                                        | Получите краткую информацию о канале (количество клиентов).                                         |
| history(string $channel, int $limit = 0, ?int $offset = null, ?string $epoch = null, bool $reverse = false)                           | Получить информацию об истории канала (список последних сообщений, отправленных в канал).           |
| historyRemove(string $channel)                                                                                                        | Удалить информацию из истории канала.                                                               |
| subscribe(string $channel, string $user, array $info = [], array $data = [])                                                          | Подписать пользователя на канал (серверная подписка).                                               |
| unsubscribe(string $channel, string $user)                                                                                            | Отписать пользователя от канала.                                                                    |
| disconnect(string $userId)                                                                                                            | Отключить пользователя по его ID.                                                                   |
| rpc(string $method, array $data = [])                                                                                                 | Удалённый вызов процедуры (RPC).                                                                   |
| channels(string $pattern = '')                                                                                                        | Cписок текущих активных каналов.                                                                    |
| info()                                                                                                                                | Статистическая информация о запущенных серверных узлах.                                             |
| generateConnectionToken(string&#124;int $userId, int&#124;Carbon $exp = 0, array $info = [], array $channels = [])                    | Генерация токена для подключения                                                                    |
| generateSubscriptionToken(string&#124;int $userId, string $channel, int&#124;Carbon $exp = 0, array $info = [], array $override = []) | Генерация приватного токена для приватного канала                                                   |

## Лицензия

Лицензия MIT. Пожалуйста
прочитайте [Файл лицензии](https://github.com/opekunov/laravel-centrifugo-broadcaster/blob/master/LICENSE) для получения
дополнительной информации.
