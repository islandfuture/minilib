<?php
if (empty($argv[1])) {
    die("php createproject.php <directory project>\n");
}

$projectdir = $argv[1];
$projectdir = str_replace('\\', '/', $projectdir);
if (substr($argv[1],0,1) != '/') {
    $projectdir = __DIR__ . '/' . $projectdir;
}

try {
    if (! file_exists($projectdir)) {
        mkdir($projectdir);
    }
} catch(Exception $e) {

}

if (! file_exists($projectdir)) {
    die("Cannot create project directory: $projectdir\n");
}

$needCopyCore = isset($argv[2]) && $argv[2] == 'one' ? false : true;


