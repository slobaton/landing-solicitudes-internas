<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CatalogoRepository;

/**
 * Catálogos activos para el formulario "Registrar solicitud"
 * (GET /api/v1/catalogos, diseno_landing.md §8.2). Solo ayuda a la interfaz:
 * POST /solicitudes vuelve a validar cada id contra la BD (RN-D12).
 */
final class CatalogoService
{
    public function __construct(private CatalogoRepository $repo = new CatalogoRepository())
    {
    }

    /**
     * @return array{areas: list<array{id: int, codigo: string, nombre: string}>,
     *               tipos_solicitud: list<array{id: int, nombre: string, descripcion: string|null}>,
     *               prioridades: list<array{id: int, codigo: string, nombre: string, horas_max_atencion: int}>}
     */
    public function activos(): array
    {
        return [
            'areas' => $this->repo->areasActivas(),
            'tipos_solicitud' => $this->repo->tiposActivos(),
            'prioridades' => $this->repo->prioridadesActivas(),
        ];
    }
}
