# DB — работа с базой данных

`IFMiniLib\DB` — синглтон, обёртка над PDO с внутренним кешированием запросов в рамках одного запроса.

## Настройка подключения

В `config/config.php`:

```php
'dbpool' => [
    'default' => [
        'dsn'      => 'mysql:dbname=mydb;host=127.0.0.1;port=3306;charset=utf8mb4',
        'user'     => 'root',
        'password' => 'secret',
        // 'afterConnect' => ["SET NAMES 'utf8mb4'"] // команды после подключения
    ],
    // второе подключение:
    'archive' => [
        'dsn'      => 'mysql:dbname=archive;host=127.0.0.1;charset=utf8mb4',
        'user'     => 'root',
        'password' => 'secret',
    ]
]
```

Переключение между подключениями:
```php
DB::one()->setKey('archive');
// ... работа с archive
DB::one()->setKey('default'); // вернуться к основному
```

## Выборка через Model

Основные методы работают через модели (см. [Model](model.md)):

```php
// Получить все записи (до 100 по умолчанию)
$users = DB::getAll([
    'model'    => ModelUsers::class,
    'filter'   => ['statusid' => 1],
    'sort'     => ['created' => 'DESC'],
    'pageSize' => 20,
    'page'     => 1,
]);

// Получить одну запись
$user = DB::getOne([
    'model'  => ModelUsers::class,
    'filter' => ['email' => 'user@example.com'],
]);

// Количество записей
$count = DB::getCountAll([
    'model'  => ModelUsers::class,
    'filter' => ['statusid' => 1],
]);

// Удалить записи
DB::deleteAll([
    'model'  => ModelUsers::class,
    'filter' => ['statusid' => 3],
]);
```

## Фильтры

```php
'filter' => [
    // Точное совпадение
    'statusid' => 1,

    // Операторы сравнения
    'created' => ['>' => '2024-01-01'],
    'id'      => ['<=' => 100],

    // BETWEEN
    'id' => ['between' => [10, 100]],

    // IN
    'statusid' => ['in' => [1, 2]],

    // NOT IN
    'statusid' => ['not in' => [3, 4]],
    // или
    'statusid' => ['!in' => [3, 4]],

    // LIKE
    'name' => ['like' => '%Иван%'],

    // IS NULL / IS NOT NULL
    'deleted' => '[:null:]',
    'email'   => '[:!null:]',

    // Произвольный SQL (осторожно — без экранирования!)
    ':sql:' => 'AND price > 100 AND price < 1000',
]
```

## Индексированный результат

```php
// Вернуть массив, индексированный по id
$usersById = DB::getAll(
    ['model' => ModelUsers::class],
    ['index' => true]
);

$user = $usersById[42]; // доступ по id

// Индексировать по другому полю
$usersByEmail = DB::getAll(
    ['model' => ModelUsers::class],
    ['index' => 'email']
);
```

## JOIN

```php
$rows = DB::getAll([
    'model'  => ModelOrders::class,
    'joins'  => [
        [
            ModelUsers::class,
            'typeJoin' => 'LEFT JOIN',
            'on'       => '`orders`.`user_id` = t0.`id`',
        ]
    ],
    'fields' => ['`orders`.*', 't0.`name` as user_name'],
]);
```

## Произвольные запросы

```php
// SELECT — результат массив ассоциативных массивов
$rows = DB::one()->queryAll("SELECT * FROM users WHERE statusid = 1");

// SELECT — результат как объекты класса
$rows = DB::one()->queryAll(
    "SELECT * FROM users",
    ['type' => 'class', 'classname' => ModelUsers::class]
);

// INSERT / UPDATE / DELETE / ALTER
DB::one()->execute("UPDATE users SET statusid = 0 WHERE id = 5");

// Prepared statement вручную
$st = DB::one()->prepare("SELECT * FROM users WHERE id = :id");
$st->execute([':id' => 42]);
$row = $st->fetch(\PDO::FETCH_ASSOC);
```

## Транзакции

```php
DB::one()->begin();
try {
    // операции...
    DB::one()->commit();
} catch (\Exception $e) {
    DB::one()->rollback();
    throw $e;
}
```

## Кеш

Результаты `getAll`, `getOne`, `getCountAll`, `query`, `queryAll` кешируются в памяти в рамках одного HTTP-запроса. Для принудительного обновления:

```php
// Отключить кеш для конкретного запроса
DB::getAll(['model' => ModelUsers::class], ['nocache' => true]);

// Очистить весь кеш
DB::clearInnerCache();

// Очистить кеш только для модели
DB::clearInnerCache(ModelUsers::class);

// Очистить только кеш таблиц (не сырых запросов)
DB::clearInnerCache(':table:');

// Очистить только кеш сырых запросов
DB::clearInnerCache(':query:');
```

## Ошибки БД

```php
DB::addError('Текст ошибки', ModelUsers::class, 'email');

if (DB::isError(ModelUsers::class, 'email')) {
    $errors = DB::getError(ModelUsers::class, 'email');
}

if (DB::isErrors(ModelUsers::class)) {
    $allErrors = DB::getErrors(ModelUsers::class);
}

DB::clearErrors(ModelUsers::class);
```
