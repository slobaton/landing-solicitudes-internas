<?php

declare(strict_types=1);

// Front Controller: solo bootstrap, rutas y despacho (estructura_backend.md).

use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;

require dirname(__DIR__) . '/bootstrap/app.php';

$request = Request::fromGlobals();
Database::setContext(null, $request->ip());

$router = new Router();
require dirname(__DIR__) . '/routes/api.php';
require dirname(__DIR__) . '/routes/web.php';

try {
    $response = $router->dispatch($request);
} catch (Throwable $e) {
    $response = ErrorHandler::toResponse($e);
}

$response->send();
