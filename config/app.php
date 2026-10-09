<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG'),
    'timezone' => Env::get('APP_TIMEZONE', 'UTC'),
    'log_file' => dirname(__DIR__) . '/storage/logs/app.log',
    // Limite de intentos de login (contrato §3.1).
    'login_max_intentos' => 5,
    'login_ventana_segundos' => 900,
    'throttle_path' => dirname(__DIR__) . '/storage/throttle',
];
