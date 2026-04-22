# Request — входящий запрос

`IFMiniLib\Request` — синглтон, обёртка над суперглобальными переменными PHP.

## Доступ к данным

```php
use IFMiniLib\Request;

$req = Request::one();
```

### Через магический `__get` (автоопределение источника)

Порядок поиска: `POST` → `GET` → `SERVER` → `COOKIE`

```php
$name = Request::one()->name;    // ищет в POST, затем GET
$page = Request::one()->page;
```

### С явным указанием источника

```php
$id      = Request::one()->{'get.id'};
$token   = Request::one()->{'post.token'};
$cookie  = Request::one()->{'cookie.session'};
$ua      = Request::one()->{'server.HTTP_USER_AGENT'};
$arg     = Request::one()->{'args.mode'}; // CLI-аргументы
```

### Прямой доступ к массивам

```php
$post    = Request::one()->post;    // массив $_POST
$get     = Request::one()->get;     // массив $_GET
$cookie  = Request::one()->cookie;  // массив $_COOKIE
$files   = Request::one()->files;   // массив $_FILES
$server  = Request::one()->server;  // массив $_SERVER
$headers = Request::one()->headers; // HTTP-заголовки (getallheaders())
$args    = Request::one()->args;    // CLI-аргументы (ключ=значение)
```

## JSON-запросы

Если запрос пришёл с `Content-Type` отличным от `x-www-form-urlencoded` и тело начинается с `{`, оно автоматически декодируется в `$request->post`:

```php
// Клиент отправил: POST body = '{"name":"Иван","age":30}'
$name = Request::one()->{'post.name'}; // 'Иван'
$age  = Request::one()->{'post.age'};  // 30
```

## Сырой ввод

```php
$raw = Request::one()->getRawInput(); // содержимое php://input
```

## Сжатый запрос (gzcontent)

Если в POST есть поле `gzcontent` — оно автоматически распаковывается (gzip + base64) и данные из него заменяют `post`, `get`, `cookie`, `headers`.

## CLI-аргументы

```bash
php app/cli/import.php mode=full limit=100
```

```php
$mode  = Request::one()->{'args.mode'};  // 'full'
$limit = Request::one()->{'args.limit'}; // '100'
```

## Признак proxy-запроса

```php
if (Request::one()->isProxyRequest) {
    // запрос пришёл через Tilda-прокси
}
```

## Паттерн использования с санацией

```php
use IFMiniLib\Request;
use IFMiniLib\Clean;

$id    = (int) Request::one()->{'get.id'};
$name  = Clean::string(Request::one()->{'post.name'} ?? '');
$email = Clean::email(Request::one()->{'post.email'} ?? '');
```

Или через `Validator`:

```php
$v = new Validator();
$v->validate(Request::one()->post, [
    'name'  => 'required|string',
    'email' => 'required|email',
]);
```
