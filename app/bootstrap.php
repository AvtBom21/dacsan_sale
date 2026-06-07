<?php

declare(strict_types=1);

use DacSanNhaDan\Core\Autoloader;
use DacSanNhaDan\Core\Database;

$rootPath = dirname(__DIR__);

require_once $rootPath . '/app/Core/Autoloader.php';

Autoloader::register($rootPath . '/app');

$config = [
    'app' => require $rootPath . '/config/app.php',
    'database' => require $rootPath . '/config/database.php',
    'session' => require $rootPath . '/config/session.php',
];

date_default_timezone_set((string) $config['app']['timezone']);

Database::configure($config['database']);

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name((string) $config['session']['name']);
    session_set_cookie_params([
        'lifetime' => (int) $config['session']['lifetime'],
        'path' => (string) $config['session']['path'],
        'domain' => (string) $config['session']['domain'],
        'secure' => (bool) $config['session']['secure'],
        'httponly' => (bool) $config['session']['httponly'],
        'samesite' => (string) $config['session']['samesite'],
    ]);
    session_start();
}

return $config;
