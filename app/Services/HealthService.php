<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ApiException;
use App\Core\ErrorHandler;
use App\Repositories\SistemaRepository;

/**
 * Comprobacion de API + BD (contrato §2).
 */
final class HealthService
{
    public function __construct(private SistemaRepository $sistema = new SistemaRepository())
    {
    }

    /** @return array{estado: string, base_datos: string, hora_servidor: string} */
    public function estado(): array
    {
        try {
            $hora = $this->sistema->ping();
        } catch (\Throwable $e) {
            ErrorHandler::log($e);
            throw new ApiException(503, 'SERVICIO_NO_DISPONIBLE', 'La base de datos no está disponible.');
        }

        return ['estado' => 'ok', 'base_datos' => 'ok', 'hora_servidor' => $hora];
    }
}
