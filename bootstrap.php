<?php
session_start();

$configPath = __DIR__ . '/config/config.php';
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/config/config.example.php';
}
$config = require $configPath;

spl_autoload_register(function ($class) {
    $baseDir = __DIR__ . '/src/';
    $file = $baseDir . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
