<?php
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
include $_SERVER['DOCUMENT_ROOT']. DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'class_app.php';
App::I()->init();

echo "Hello!!! // ".App::I()->web['shortcode']."\n";