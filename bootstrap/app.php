<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\ErrorHandler;

define('BASE_PATH', dirname(__DIR__));

// Autocarga PSR-4 simple: App\ -> app/
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

Env::load(BASE_PATH . '/.env');

$appConfig = require BASE_PATH . '/config/app.php';
date_default_timezone_set($appConfig['timezone']);
ErrorHandler::register($appConfig['log_file']);

return $appConfig;
