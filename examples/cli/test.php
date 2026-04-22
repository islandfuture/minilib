<?php
require_once dirname(__DIR__) . '/../vendor/autoload.php';
require_once dirname(__DIR__) . '/app/App.php';

\Example\App::one()->init();
echo "Test " . \Example\App::one()->web['shortcode'] . PHP_EOL;
