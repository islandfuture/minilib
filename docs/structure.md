# Структура проекта

```
my-app/
├── public/               # Document Root (entry point)
│   └── index.php
├── app/                  # Код приложения
│   ├── App.php           # Класс приложения (extends Core)
│   ├── WebActions/       # Обработчики веб-запросов
│   │   └── DefaultAction.php
│   ├── CliActions/       # Обработчики CLI-скриптов
│   └── logs/             # Логи (создаётся автоматически)
├── config/
│   └── config.php        # Конфигурация
├── tpl/                  # Шаблоны страниц (*.tpl.php)
│   └── index.tpl.php
├── layout/               # Шапки и футеры
│   └── main/
│       ├── header.php
│       └── footer.php
└── vendor/
```

## Маршрутизация

URL автоматически маппится на класс Action:

| URL | Класс |
|-----|-------|
| `/` или `/index` | `WebActions\DefaultAction` |
| `/users` | `WebActions\UsersAction` |
| `/admin/users` | `WebActions\Admin\UsersAction` |

Для CLI — имя скрипта маппится на `CliActions\<Name>Action`.

## Жизненный цикл запроса

1. `public/index.php` вызывает `App::one()->init()->run()`
2. `init()` читает конфиг, настраивает автозагрузчик, запускает сессию
3. `run()` определяет нужный Action по URL
4. Вызывает `_beforeRun()` → `run()` → `_afterRun()` у Action
