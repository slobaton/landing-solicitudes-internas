<?php

declare(strict_types=1);

use App\Core\Env;

// Credenciales solo desde .env (no versionado).
$tz = new DateTimeZone(Env::get('APP_TIMEZONE', 'UTC'));

return [
    'host' => Env::require('DB_HOST'),
    'port' => (int) Env::get('DB_PORT', '3306'),
    'name' => Env::require('DB_NAME'),
    'user' => Env::require('DB_USER'),
    'password' => Env::require('DB_PASSWORD'),
    'ssl_ca' => Env::get('DB_SSL_CA', ''),
    'ssl_verify' => Env::bool('DB_SSL_VERIFY', true),
    'timeout' => 5,
    // Desplazamiento UTC de APP_TIMEZONE para que NOW() coincida con PHP.
    'time_zone' => (new DateTimeImmutable('now', $tz))->format('P'),
];
