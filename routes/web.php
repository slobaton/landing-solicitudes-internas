<?php

declare(strict_types=1);

use App\Controllers\Web\LandingController;

/** @var App\Core\Router $router */

// Rutas web (HTML). La API sigue en routes/api.php bajo /api/v1.
$router->get('/', [LandingController::class, 'index']);
