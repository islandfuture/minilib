# IFMiniLib — документация

Минималистичный PHP-фреймворк для построения веб-приложений и CLI-скриптов.

## Содержание

- [Структура проекта](structure.md)
- [Core — приложение](core.md)
- [DB — база данных](db.md)
- [Model — модели данных](model.md)
- [Clean — санация данных](clean.md)
- [Validator — валидация](validator.md)
- [Request — входящий запрос](request.md)
- [Response — ответ](response.md)
- [Pages — шаблоны](pages.md)

## Быстрый старт

```bash
composer require islandfuture/minilib
```

**public/index.php:**
```php
<?php
include __DIR__ . '/../app/App.php';
\MyApp\App::one()->init()->run();
```

**app/App.php:**
```php
<?php
namespace MyApp;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class App extends \IFMiniLib\Core
{
}
```

**config/config.php:**
```php
<?php
return [
    'web' => [
        'site' => 'example.ru',
        'name' => 'My App',
        'shortcode' => 'app'
    ],
    'dbpool' => [
        'default' => [
            'dsn'      => 'mysql:dbname=mydb;host=127.0.0.1;charset=utf8mb4',
            'user'     => 'root',
            'password' => ''
        ]
    ],
    'session' => 'auto',
    'output'  => 'html',
    'debug'   => 'N'
];
```
