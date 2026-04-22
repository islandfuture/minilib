<?php
return [
    'PATH_CORE' => dirname(dirname(dirname(__DIR__))),
    'web' => [
        'site' => 'example.ru',
        'name' => 'SITE NAME',
        'shortcode' => 'app'
    ],
    'dbpool' => [
        'default' => [
            'dsn' => 'mysql:dbname=' . ($_ENV['DB_NAME'] ?? '') . ';host=' . ($_ENV['DB_HOST'] ?? '127.0.0.1') . ';port=' . ($_ENV['DB_PORT'] ?? '3306') . ';charset=utf8mb4',
            'user' => $_ENV['DB_USER'] ?? 'guest',
            'password' => $_ENV['DB_PWD'] ?? ''
        ]
    ],

    'user' => 'none', // если сессия должна быть связана с моделью юзера, то указываем название модели 'Users' иначе пишем 'none',
    'session' => 'auto', // если сессия должна быть связана с моделью юзера, то указываем название модели 'Users' иначе пишем 'none' или auto,

    'buglovers' => [
        'from' => 'root@localhost',
        'to' => 'your@mail.com'
    ],

    'logdir' => '../../app-logs/',

    'output' => 'html' /* 'json' */,
    'debug' => 'Y'
];
