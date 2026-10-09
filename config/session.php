<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => 'SIS_SESSID',
    'save_path' => dirname(__DIR__) . '/storage/sessions',
    'idle_timeout' => 1800,
    // Cookie Secure fuera del entorno local (contrato §1).
    'secure' => Env::get('APP_ENV', 'production') !== 'local',
];
