<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\EstadisticaRepository;

/**
 * Resumen público de cifras (GET /api/v1/estadisticas, diseno_landing.md §8.1).
 * Agrupa los conteos por estado en los indicadores de la landing.
 */
final class EstadisticaService
{
    private const REGISTRADAS = ['REGISTRADA'];
    private const EN_PROCESO = ['ASIGNADA', 'EN_PROCESO'];
    private const ATENDIDAS_CERRADAS = ['ATENDIDA', 'CERRADA'];

    public function __construct(private EstadisticaRepository $repo = new EstadisticaRepository())
    {
    }

    /**
     * @return array{total: int, registradas: int, en_proceso: int, atendidas_cerradas: int,
     *               por_estado: list<array{codigo: string, nombre: string, total: int}>, generado_en: string}
     */
    public function resumen(): array
    {
        $porEstado = $this->repo->conteoPorEstado();

        $sumar = static function (array $codigos) use ($porEstado): int {
            $total = 0;
            foreach ($porEstado as $fila) {
                if (in_array($fila['codigo'], $codigos, true)) {
                    $total += $fila['total'];
                }
            }
            return $total;
        };

        return [
            'total' => array_sum(array_column($porEstado, 'total')),
            'registradas' => $sumar(self::REGISTRADAS),
            'en_proceso' => $sumar(self::EN_PROCESO),
            'atendidas_cerradas' => $sumar(self::ATENDIDAS_CERRADAS),
            'por_estado' => $porEstado,
            'generado_en' => $this->repo->horaServidor(),
        ];
    }
}
