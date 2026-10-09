<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogoService;

/**
 * GET /api/v1/catalogos — con sesión (AuthMiddleware), solo registros activos
 * (diseno_landing.md §8.2).
 */
final class CatalogoController
{
    public function index(Request $request): Response
    {
        return Response::ok((new CatalogoService())->activos());
    }
}
