# Validator — валидация данных

`IFMiniLib\Validator` — класс для валидации и санации входных данных. Наследуется от `Only` (синглтон).

## Базовое использование

```php
$v = new \IFMiniLib\Validator();

$ok = $v->validate($_POST, [
    'name'  => 'required|string',
    'email' => 'required|email',
    'age'   => 'int|between:18,120',
    'site'  => 'url',
]);

if (!$ok) {
    $errors = $v->getErrors();
    // ['email' => ['Invalid email'], ...]
}

// Очищенные и нормализованные данные
$data = $v->data;
```

## Правила

Правила указываются через `|`. Порядок имеет значение — санирующие правила (`string`, `int`, `price` и др.) должны идти **перед** валидирующими (`length`, `between`, `email`).

### Обязательность

| Правило | Описание |
|---------|----------|
| `required` | Поле обязательно; пустое значение добавит ошибку |

Если поле не `required` и значение пустое — оно пропускается, в `$v->data[$field]` записывается `null`.

### Санация (очистка)

| Правило | Описание |
|---------|----------|
| `string` | `Clean::string()` — удалить теги, спецсимволы |
| `string:@.` | Разрешить дополнительные символы (`@`, `.`) |
| `html` | `Clean::html()` — санировать HTML через symfony/html-sanitizer |
| `html:<b><i><a>` | HTML с указанием разрешённых тегов |
| `int` / `integer` | Привести к `(int)` |
| `number` | `Clean::number()` — нормализовать число/дробь |
| `price` | `Clean::price()` — нормализовать цену в `float` |
| `ip` | `Clean::ip()` — санировать IP-адрес |
| `ipnet` | `Clean::ipnet()` — санировать подсеть |
| `replace:from>to` | Заменить символ: `replace: >_` заменяет пробел на `_` |

### Валидация

| Правило | Описание |
|---------|----------|
| `email` | Санировать и проверить формат email |
| `url` | Санировать и проверить формат URL |
| `domain` | Санировать и проверить домен |
| `length:min,max` | Проверить длину строки (UTF-8) |
| `between:min,max` | Проверить что число в диапазоне |
| `min:N` | Минимальное значение |
| `max:N` | Максимальное значение |
| `in:a,b,c` | Значение должно быть в списке |
| `exists:ClassName` | Запись с таким id должна существовать в БД |

## Примеры

### Форма регистрации

```php
$v = new Validator();
$ok = $v->validate($_POST, [
    'name'     => 'required|string|length:2,100',
    'email'    => 'required|email',
    'password' => 'required|string|length:8,255',
    'age'      => 'int|between:18,120',
    'site'     => 'url',
]);

if (!$ok) {
    return Response::one()->jsonError($v->getErrors())->sendAndExit();
}

// $v->data содержит очищенные данные
$model = new ModelUsers();
$model->name  = $v->data['name'];
$model->email = $v->data['email'];
$model->save();
```

### Фильтрация параметров API

```php
$v = new Validator();
$ok = $v->validate($_GET, [
    'page'     => 'int|min:1',
    'per_page' => 'int|between:1,100',
    'status'   => 'in:active,inactive,deleted',
    'q'        => 'string|length:0,200',
]);
```

### Проверка существования связанной записи

```php
$v = new Validator();
$ok = $v->validate($_POST, [
    'category_id' => 'required|int|exists:ModelCategories',
]);
// Если ModelCategories::getById($id) вернёт null — ошибка
```

### Сохранение HTML-контента

```php
$v = new Validator();
$ok = $v->validate($_POST, [
    'title'   => 'required|string|length:1,255',
    'content' => 'required|html',      // полная санация
    'excerpt' => 'html:<p><b><i><a>',  // разрешённые теги
]);
```

### Замена символов

```php
$v = new Validator();
$v->validate($_POST, [
    'code' => 'string| replace: >_', // пробелы → _
]);
```

## Получение результатов

```php
// Проверка наличия ошибок
if (!$ok) {
    $errors = $v->getErrors();
    // ['field' => ['Сообщение об ошибке', ...], ...]
}

// Очищенные данные (только поля из rules)
$data = $v->data;

// Отдельное поле
$name = $v->data['name'] ?? null;
```

## Использование как синглтон (через Only)

```php
// Не рекомендуется — данные предыдущей валидации будут сброшены
// Лучше создавать new Validator() каждый раз
$v = Validator::one();
$v->validate($data, $rules);
```

## Совместная работа с Model

Типичный паттерн — валидация данных перед сохранением в модель:

```php
public function run()
{
    $v = new Validator();
    $ok = $v->validate(Request::one()->post, [
        'name'  => 'required|string|length:1,200',
        'email' => 'required|email',
        'price' => 'required|price',
    ]);

    if (!$ok) {
        return Response::one()->jsonError($v->getErrors())->sendAndExit();
    }

    $item = new ModelProducts();
    foreach ($v->data as $key => $val) {
        $item->$key = $val;
    }
    $item->save();

    return Response::one()->json(['id' => $item->id])->sendAndExit();
}
```
