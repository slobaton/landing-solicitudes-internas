<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\EstadisticaService;

/**
 * GET /api/v1/estadisticas — público, solo conteos (diseno_landing.md §8.1).
 */
final class EstadisticaController
{
    public function show(Request $request): Response
    {
        return Response::ok((new EstadisticaService())->resumen());
    }
}
