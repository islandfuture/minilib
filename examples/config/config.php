<?php
return [
    'PATH_CORE' => dirname(dirname(dirname(__DIR__))),
    'web' => [
        'site' => 'example.ru',
        'name' => 'SITE NAME',
        'shortcode' => 'site'
    ],
    'dbpool' => array(
        'default' => array(
            'dsn' => 'mysql:dbname=TABLE;host=127.0.0.1;port=3306',
            'user' => 'MYSQL_USER',
            'password' => 'MYSQL_PWD'
        )
    ),

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
