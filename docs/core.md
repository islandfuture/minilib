# Core — класс приложения

`IFMiniLib\Core` — синглтон, центральная точка приложения. Наследуется от `Only`.

## Создание своего App

```php
<?php
namespace MyApp;

class App extends \IFMiniLib\Core
{
    // можно переопределить методы или добавить свои
}
```

Доступ к экземпляру:
```php
\MyApp\App::one()       // основной способ
\IFMiniLib\Core::$app   // статическая ссылка на текущее приложение
```

## Инициализация

```php
App::one()->init()->run();
```

`init()` выполняет:
- Чтение `config/config.php`
- Установку путей (`PATH_ROOT`, `PATH_APP`, `PATH_PUBLIC` и др.)
- Регистрацию автозагрузчика классов
- Настройку обработчиков ошибок и исключений
- Запуск сессии (если задан параметр `session` в конфиге)

## Конфигурация

Все параметры из `config.php` доступны через магические свойства:

```php
App::one()->web['name'];   // название сайта
App::one()->debug;         // режим отладки ('Y' или 'N')
App::one()->dbpool;        // настройки БД
```

Установка своих параметров:
```php
App::one()->myParam = 'value';
$val = App::one()->myParam;
```

### Параметры конфига

| Ключ | Тип | Описание |
|------|-----|----------|
| `web` | array | Данные сайта: `site`, `name`, `shortcode` |
| `dbpool` | array | Настройки подключений к БД |
| `session` | string | `'auto'` — обычная сессия, `'none'` — без сессии, название класса — сессия через модель |
| `user` | string | Класс модели пользователя для сессии (`'none'` если не нужно) |
| `debug` | string | `'Y'` — показывать ошибки, `'N'` — скрывать |
| `output` | string | `'html'` или `'json'` — формат ответа при ошибках |
| `logdir` | string | Путь к папке с логами (относительно `PATH_APP`) |
| `buglovers` | array | `from` и `to` для отправки email при критических ошибках |
| `errorpage5xx` | string | Путь к html-файлу с 500 ошибкой (относительно `PATH_PUBLIC`) |

## Логирование

```php
App::one()->log('Сообщение');                          // лог в файл log.txt
App::one()->log('Ошибка', ['key' => 'val'], 'error');  // лог в error.txt
App::one()->log('Отладка', [], 'debug');               // лог в debug.txt
```

Файл лога: `{PATH_APP}/{logdir}/{shortcode}-{file}.txt`

## Actions

Каждый Action — класс, наследующий `BaseAction`:

```php
<?php
namespace MyApp\WebActions;

use IFMiniLib\Response;
use IFMiniLib\Request;

class UsersAction extends \IFMiniLib\BaseAction
{
    public function _beforeRun()
    {
        // выполняется перед run()
    }

    public function run()
    {
        $id = (int) Request::one()->get['id'];
        return Response::one()->json(['id' => $id])->sendAndExit();
    }

    public function _afterRun($response)
    {
        // выполняется после run()
    }
}
```

## CLI-режим

```php
// Конфиг:
'_mode' => 'cli'
```

Запуск: `php app/cli/my-script.php`
Класс: `CliActions\MyScriptAction`

Защита от параллельного запуска:
```php
App::one()->checkOneRun(); // die() если процесс уже запущен
```

## Определение языка пользователя

```php
$lang = App::one()->getUserLang(); // 'RU' или 'EN'
```
