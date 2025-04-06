<?php
require_once dirname(__DIR__) . '/../vendor/autoload.php';
require_once dirname(__DIR__) . '/app/App.php';

App::I()->init();
// DebugUtils::I()->setDebugMode(true);
// DebugUtils::I()->start('test');
echo "Test ".App::I()->web['shortcode'].PHP_EOL;
// DebugUtils::I()->end('test');
// echo "Timer: ";
