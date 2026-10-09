<?php

declare(strict_types=1);

use App\Controllers\Api\AuthController;
use App\Controllers\Api\CatalogoController;
use App\Controllers\Api\EstadisticaController;
use App\Controllers\Api\HealthController;
use App\Controllers\Api\SolicitudController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

/** @var App\Core\Router $router */

$router->get('/api/v1/health', [HealthController::class, 'show']);

// Landing (CP-FRONT-03): cifras públicas, solo conteos agregados, sin sesión.
$router->get('/api/v1/estadisticas', [EstadisticaController::class, 'show']);

$router->post('/api/v1/auth/login', [AuthController::class, 'login']);
$router->post('/api/v1/auth/logout', [AuthController::class, 'logout'], [AuthMiddleware::class, CsrfMiddleware::class]);
$router->get('/api/v1/auth/me', [AuthController::class, 'me'], [AuthMiddleware::class]);

// Landing (CP-FRONT-04): catálogos activos para el formulario de registro, con sesión.
$router->get('/api/v1/catalogos', [CatalogoController::class, 'index'], [AuthMiddleware::class]);

// Solicitudes (CP-API-03 y CP-API-04). DELETE = cancelar con motivo (UPDATE, nunca DELETE FROM).

$router->post('/api/v1/solicitudes', [SolicitudController::class, 'store'], [AuthMiddleware::class, CsrfMiddleware::class]);
$router->get('/api/v1/solicitudes', [SolicitudController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/v1/solicitudes/{id}', [SolicitudController::class, 'show'], [AuthMiddleware::class]);
$router->put('/api/v1/solicitudes/{id}', [SolicitudController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);
$router->delete('/api/v1/solicitudes/{id}', [SolicitudController::class, 'destroy'], [AuthMiddleware::class, CsrfMiddleware::class]);
