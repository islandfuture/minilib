# Clean — санация данных

`IFMiniLib\Clean` — статический класс для очистки и нормализации входных данных перед использованием или сохранением в БД.

## Строки

### `Clean::string($string, $symbols = '', $stripTags = true)`

Удаляет теги и все символы кроме: букв (латиница, кириллица, основные европейские), цифр, пробела и `+`, `-`, `_`, `:`.

```php
$name = Clean::string($_POST['name']);

// Разрешить дополнительные символы (например @)
$login = Clean::string($_POST['login'], '@');

// Не удалять HTML-теги (для последующей обработки)
$text = Clean::string($html, '', false);
```

### `Clean::stringSC($str)`

Удаляет теги + `htmlspecialchars`. Подходит для вывода в HTML.

```php
$comment = Clean::stringSC($_POST['comment']);
echo $comment; // безопасно выводить в HTML
```

### `Clean::stringMb4($str, $stripTags = true)`

Удаляет 4-байтовые Unicode-символы (emoji и т.п.), которые не поддерживает MySQL `utf8` (не `utf8mb4`).

```php
$text = Clean::stringMb4($_POST['text']);
```

### `Clean::letters($str)`

Только латинские буквы, дефис, точка, подчёркивание, слэш.

```php
$filename = Clean::letters($_POST['filename']);
```

### `Clean::lettersAndNumbers($str)`

Только латинские буквы, цифры, дефис, точка, подчёркивание, слэш.

```php
$slug = Clean::lettersAndNumbers($_POST['slug']);
```

## HTML

### `Clean::html($html, $allowedTags = '')`

Санация HTML через `symfony/html-sanitizer`. Удаляет опасные атрибуты и теги.

> Требует: `composer require symfony/html-sanitizer`

```php
// Полная санация — только безопасные элементы
$content = Clean::html($_POST['content']);

// Предварительная фильтрация тегов
$content = Clean::html($_POST['content'], '<p><b><i><a>');
```

### `Clean::htmlentitiesValuesInArray($array)`

Рекурсивно применяет `htmlentities()` ко всем строковым значениям массива.

```php
$data = Clean::htmlentitiesValuesInArray($_POST);
```

## Числа

### `Clean::number($str)`

Нормализует строку с числом (целым или дробным). Обрабатывает запятую как разделитель.

```php
$qty = Clean::number('1 234,56'); // '1234.56'
$qty = Clean::number('100,00');   // '100'
```

### `Clean::price($price)`

Парсит цену из строки, включая форматы `1.234,56` и `1,234.56`.

```php
$price = Clean::price('1 234,56 руб.');  // 1234.56 (float)
$price = Clean::price('$1,299.99');      // 1299.99 (float)
```

### `Clean::digitFloat($value)`

Приводит значение к `float`, если в нём есть хоть одна цифра.

```php
$val = Clean::digitFloat('3.14'); // 3.14
$val = Clean::digitFloat('abc');  // ''
```

### `Clean::digitPx($value)`

Нормализует CSS-значение в пикселях.

```php
Clean::digitPx('100px'); // '100px'
Clean::digitPx('50');    // '50px'
Clean::digitPx('100vh'); // '100vh'
Clean::digitPx('0');     // ''
```

## Ссылки и URL

### `Clean::url($url)`

Нормализует URL: декодирует IDN-домены, кодирует спецсимволы в path и query. Проверяет на XSS.

```php
$url = Clean::url($_POST['url']);
if ($url === false) {
    // XSS-атака обнаружена
}
```

### `Clean::link($str)`

Проверяет ссылку через `isSafeLink()` — проверка DNS, приватных IP, схемы. Обрезает до 500 символов.

```php
$link = Clean::link($_POST['link']);
if ($link === '') {
    // небезопасная или невалидная ссылка
}
```

### `Clean::isSafeLink($url): bool`

Проверяет:
- Схему (только `http`/`https`)
- Localhost и приватные диапазоны IP
- XSS-паттерны (`javascript:`, `onerror`, `eval` и др.)
- Разрешает DNS и проверяет что IP не приватный

```php
if (!Clean::isSafeLink($url)) {
    throw new \Exception('Небезопасная ссылка');
}
```

### `Clean::imageUrl($str)`

Для URL изображений — разрешены только `a-zA-Z0-9`, дефис, подчёркивание, точка, двоеточие, слэш.

```php
$imgUrl = Clean::imageUrl($_POST['image_url']);
```

## Email и домены

### `Clean::email($email)`

Нормализует email: санирует логин и домен, конвертирует IDN-домены в punycode.

```php
$email = Clean::email($_POST['email']);
if ($email === '') {
    // невалидный email
}
```

### `Clean::sanitizeDomain($str, $onlyascii = false)`

Убирает `http://`, `https://`, `www.`, пробелы, слэши. При `$onlyascii = true` конвертирует в punycode.

```php
$domain = Clean::sanitizeDomain('https://www.пример.рф'); // 'пример.рф'
$domain = Clean::sanitizeDomain('https://www.пример.рф', true); // 'xn--e1afmapc.xn--p1af'
```

### `Clean::isValidDomain($domain): bool`

Проверяет формат доменного имени (длина, структура, символы).

## Сеть

### `Clean::ip($ip)`

Валидирует и возвращает IPv4 или IPv6 адрес. При невалидном значении возвращает `''`.

```php
$ip = Clean::ip($_SERVER['REMOTE_ADDR']);
```

### `Clean::ipnet($ipnet)`

Валидирует подсеть формата `192.168.1.0/24`.

```php
$net = Clean::ipnet('192.168.1.0/24'); // '192.168.1.0/24'
$net = Clean::ipnet('invalid');         // ''
```

## Прочее

### `Clean::hash($hash, $length)`

Оставляет только `a-zA-Z0-9` и обрезает до нужной длины.

```php
$token = Clean::hash($_GET['token'], 64);
```

### `Clean::filenamePart($part)`

Только `a-zA-Z0-9_` — безопасная часть имени файла.

```php
$name = Clean::filenamePart($_POST['name']);
```

### `Clean::timezone($timezone, $default = 'GMT+3:00'): DateTimeZone`

Создаёт `DateTimeZone` из строки. При невалидном значении использует `$default`.

```php
$tz = Clean::timezone($_POST['timezone']);
```

### `Clean::json($json, $sanitize = false): ?string`

Проверяет что строка — валидный JSON и нормализует его. При `$sanitize = true` применяет `strip_tags` и `htmlspecialchars` к строковым значениям.

```php
$json = Clean::json($input);
if ($json === null) {
    // невалидный JSON
}

// С санацией значений (для хранения пользовательского JSON)
$json = Clean::json($input, true);
```

## Маскировка в логах

### `Clean::hideLogData($data, $params, $addHidePrefixes)`

Маскирует секретные данные перед логированием.

```php
$safeData = Clean::hideLogData($requestData);
// по умолчанию скрывает поля: password, pass, api_key
// и заголовки: Authorization, X-Token, X-API-Key

// Дополнительные поля
$safeData = Clean::hideLogData($data, ['password', 'secret', 'token']);
```

### `Clean::hideString($str): string`

Маскирует строку звёздочками, оставляя не более 2 символов с каждого края.

```php
Clean::hideString('mypassword123'); // 'my***23'
Clean::hideString('ab');           // '*****'
```

### `Clean::findXssInUrl($url)`

Ищет XSS-паттерны в URL (`eval`, `atob`, `onerror`, `javascript:` и др.). Возвращает модифицированный URL или `false` при явной атаке (2+ паттерна).

```php
$url = Clean::findXssInUrl($_GET['url']);
if ($url === false) {
    // атака
}
```
