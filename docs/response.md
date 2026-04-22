# Response — ответ клиенту

`IFMiniLib\Response` — синглтон, формирует и отправляет HTTP-ответ.

## JSON-ответ

```php
use IFMiniLib\Response;

// Успешный ответ
// {"success":true,"result":{...}}
Response::one()
    ->json(['id' => 42, 'name' => 'Иван'])
    ->sendAndExit();

// Ответ с кодом
Response::one()
    ->json($data, Response::HTTP_OK)
    ->sendAndExit();

// Ошибка
// {"success":false,"error":"Не авторизован"}
Response::one()
    ->jsonError('Не авторизован', Response::HTTP_UNAUTHORIZED)
    ->sendAndExit();

// Ошибка валидации
Response::one()
    ->jsonError($v->getErrors(), Response::HTTP_BAD_REQUEST)
    ->sendAndExit();
```

## HTML-ответ

```php
Response::one()
    ->set('<h1>Hello</h1>')
    ->sendAndExit();

// С кодом
Response::one()
    ->set('Not Found', Response::HTTP_NOT_FOUND)
    ->sendAndExit();
```

## Редирект

```php
// 301 по умолчанию
Response::one()->redirect('/login/')->sendAndExit();

// 302 временный
Response::one()->redirect('/dashboard/', 302)->sendAndExit();

// Без санации URL (если URL уже проверен)
Response::one()->redirect($url, 301, false)->sendAndExit();
```

## Файл

```php
Response::one()
    ->setFile('/path/to/file.pdf')
    ->sendFileAndExit();
// автоматически устанавливает Content-Type через mime_content_type()
```

## HTTP-заголовки

```php
Response::one()
    ->header('X-Custom-Header', 'value')
    ->header('Cache-Control', 'no-cache')
    ->set($content)
    ->sendAndExit();
```

## Константы кодов

```php
Response::HTTP_OK                    // 200
Response::HTTP_BAD_REQUEST           // 400
Response::HTTP_UNAUTHORIZED          // 401
Response::HTTP_FORBIDDEN             // 403
Response::HTTP_NOT_FOUND             // 404
Response::HTTP_INTERNAL_SERVER_ERROR // 500
```

## Типичные паттерны в Action

### REST API

```php
public function run()
{
    $v = new Validator();
    if (!$v->validate(Request::one()->post, ['email' => 'required|email'])) {
        return Response::one()
            ->jsonError($v->getErrors())
            ->sendAndExit();
    }

    $user = DB::getOne(['model' => ModelUsers::class, 'filter' => ['email' => $v->data['email']]]);
    if (!$user) {
        return Response::one()
            ->jsonError('Пользователь не найден', Response::HTTP_NOT_FOUND)
            ->sendAndExit();
    }

    return Response::one()->json($user)->sendAndExit();
}
```

### Веб-страница

```php
public function run()
{
    return (new Pages(App::one()))->show('index', 'main', [
        'title' => 'Главная'
    ]);
    // Pages::show() сам выводит HTML, Response не нужен
}
```

## CLI

В режиме CLI `sendAndExit()` выводит текст в STDOUT вместо HTTP-ответа:

```php
Response::one()->set("Done!\n")->sendAndExit();
// выводит: Done!

Response::one()->json(['count' => 42])->sendAndExit();
// выводит JSON в stdout
```
