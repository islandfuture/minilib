# IFMiniLib

Минималистичный PHP-фреймворк для построения веб-приложений и CLI-скриптов с поддержкой MySQL через PDO.

## Требования

- PHP 8.1+
- ext-pdo
- ext-pdo_mysql
- ext-intl (для IDN-доменов и email с кириллицей)
- ext-mbstring

## Установка

```bash
composer require islandfuture/minilib
```

## Структура проекта

После установки создайте следующие папки и файлы:

```
my-project/
├── public/                   # Document Root веб-сервера
│   └── index.php             # Точка входа
├── app/                      # Код приложения
│   ├── App.php               # Класс приложения
│   └── WebActions/           # Обработчики запросов
│       └── DefaultAction.php
├── config/
│   └── config.php            # Конфигурация
├── tpl/                      # Шаблоны страниц
│   └── index.tpl.php
├── layout/                   # Общие шапки и футеры
│   └── main/
│       ├── header.php
│       └── footer.php
└── vendor/                   # Зависимости composer
```

## Минимальная конфигурация

**config/config.php**
```php
<?php
return [
    'web' => [
        'site'      => 'example.ru',
        'name'      => 'My App',
        'shortcode' => 'app',
    ],
    'dbpool' => [
        'default' => [
            'dsn'      => 'mysql:dbname=mydb;host=127.0.0.1;port=3306;charset=utf8mb4',
            'user'     => 'root',
            'password' => '',
        ]
    ],
    'session' => 'auto',  // 'auto', 'none', или имя класса модели пользователя
    'output'  => 'html',  // 'html' или 'json'
    'debug'   => 'N',     // 'Y' — показывать ошибки
];
```

**app/App.php**
```php
<?php
namespace MyApp;

use IFMiniLib\Core;

class App extends Core
{
}
```

**public/index.php**
```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/App.php';

\MyApp\App::one()->init()->run();
```

**app/WebActions/DefaultAction.php**
```php
<?php
namespace MyApp\WebActions;

use IFMiniLib\BaseAction;
use IFMiniLib\Response;

class DefaultAction extends BaseAction
{
    public function run()
    {
        return Response::one()->set('<h1>Hello World</h1>')->sendAndExit();
    }
}
```

## Подробная документация

- [Структура проекта и маршрутизация](docs/structure.md)
- [Core — приложение, конфиг, логирование](docs/core.md)
- [DB — работа с базой данных](docs/db.md)
- [Model — модели данных](docs/model.md)
- [Clean — санация входных данных](docs/clean.md)
- [Validator — валидация данных](docs/validator.md)
- [Request — входящий запрос](docs/request.md)
- [Response — HTTP-ответ](docs/response.md)
- [Pages — шаблоны и HTML-страницы](docs/pages.md)
