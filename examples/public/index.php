<?php
include __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'App.php';
\Example\App::one()->init()->run();

echo "Hello!!! // " . \Example\App::one()->web['shortcode'] . "\n";
