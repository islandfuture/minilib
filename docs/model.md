# Model — модели данных

`IFMiniLib\Model` — базовый класс для описания таблиц БД. Реализует `JsonSerializable`.

## Создание модели

```php
<?php
namespace MyApp;

use IFMiniLib\Model;

class ModelUsers extends Model
{
    // Название таблицы
    public static function getTable(): string
    {
        return 'users';
    }

    // Название модели (для интерфейса)
    public static function getTitle(): string
    {
        return 'Пользователи';
    }

    // Имя первичного ключа
    public static function getIdName(): string
    {
        return 'id';
    }

    // Тип генерации первичного ключа
    public static function getIdDefault(): string
    {
        // 'AUTOINC' — автоинкремент MySQL
        // 'UUID'    — случайное число между $uidMin и $uidMax
        // 'GUID'    — случайный GUID
        // 'VALUE'   — значение задаётся вручную
        // 'NONE'    — не задаётся
        return 'AUTOINC';
    }

    // Поля таблицы — ключи = имена полей, значения = null
    public static function getClearFields(): array
    {
        return [
            'id'       => null,
            'name'     => null,
            'email'    => null,
            'passwd'   => null,
            'statusid' => null,
            'created'  => null,
            'modified' => null,
        ];
    }

    // Значения по умолчанию для новых записей
    public static function getDefault(): array
    {
        return [
            'name'     => '',
            'statusid' => '0',
            'created'  => 'CURRENT_TIMESTAMP',
        ];
    }
}
```

## Чтение данных

```php
// По id
$user = ModelUsers::getById(42);

// Несколько записей
$users = DB::getAll([
    'model'    => ModelUsers::class,
    'filter'   => ['statusid' => 1],
    'sort'     => ['name' => 'ASC'],
    'pageSize' => 20,
    'page'     => 1,
]);

// Одна запись
$user = DB::getOne([
    'model'  => ModelUsers::class,
    'filter' => ['email' => 'user@example.com'],
]);
```

## Доступ к полям

```php
echo $user->name;
echo $user->email;
echo $user->id;
```

## Сохранение

```php
// Создание новой записи
$user = new ModelUsers();
$user->name  = 'Иван';
$user->email = 'ivan@example.com';
$user->save();
echo $user->id; // id после вставки

// Обновление существующей
$user = ModelUsers::getById(42);
$user->name = 'Пётр';
$user->save();
```

## Удаление

```php
$user = ModelUsers::getById(42);
$user->delete();
```

## Типы первичного ключа

### AUTOINC

Стандартный автоинкремент MySQL. Id присваивается базой данных автоматически.

### UUID (числовой)

Случайное число в диапазоне `$uidMin`..`$uidMax`. По умолчанию: 100000000000–999999999999.

```php
class ModelProducts extends Model
{
    public $uidMin = 1000000;
    public $uidMax = 9999999;

    public static function getIdDefault(): string { return 'UUID'; }
}
```

Если нужно чтобы UUID не пересекались на разных серверах — задайте в конфиге:
```php
'needUidOffset' => 'yes',
'uidOffset'     => 2,  // все UUID будут заканчиваться на 2
```

### GUID

Случайная строка формата UUID (`xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`).

## Связи между моделями

```php
class ModelOrders extends Model
{
    public static function getRelations(): array
    {
        return [
            'user' => [
                'belongsTo',       // тип связи
                'user_id',         // внешний ключ в этой таблице
                'id',              // поле в связанной модели
                ModelUsers::class, // класс связанной модели
            ]
        ];
    }

    // Метод для удобного доступа к связанной записи
    public function user(): ?ModelUsers
    {
        return ModelUsers::getById($this->user_id);
    }
}
```

## Частичная выборка полей

```php
// Выбрать только нужные поля (readOnly — сохранение недоступно)
$users = DB::getAll([
    'model'  => ModelUsers::class,
    'fields' => ['id', 'name', 'email'],
]);

// $users[0]->readOnly === true — save() вызовет исключение
```

## Шифрование полей (AES)

```php
$users = DB::getAll([
    'model'         => ModelUsers::class,
    'securesecret'  => 'hexkey',          // ключ шифрования в hex
    'securefields'  => ['passwd'],        // шифруемые поля
]);
```

## База данных модели

Если модель хранится в другой БД:

```php
public static function getDatabase(): string
{
    return 'archive_db';
}
```
