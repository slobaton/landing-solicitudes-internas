<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Representacion de la solicitud para la API (contrato §4.1).
 * IDs de estado fijos (V009, DF-05).
 */
final class Solicitud
{
    public const REGISTRADA = 1;
    public const ASIGNADA = 2;
    public const CANCELADA = 7;

    /** seg_rol.id del SOLICITANTE (V009). */
    public const ROL_SOLICITANTE = 1;

    /** Codigo de estado => id (cat_estado, ids fijos). */
    public const ESTADOS = [
        'REGISTRADA' => 1,
        'ASIGNADA' => 2,
        'EN_PROCESO' => 3,
        'ATENDIDA' => 4,
        'CERRADA' => 5,
        'RECHAZADA' => 6,
        'CANCELADA' => 7,
    ];

    /**
     * @param array<string, mixed> $row Fila de SolicitudRepository (SELECT_BASE)
     * @return array<string, mixed>
     */
    public static function resumen(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'numero' => (string) $row['numero'],
            'titulo' => (string) $row['titulo'],
            'area_destino' => ['id' => (int) $row['area_destino_id'], 'nombre' => (string) $row['area_nombre']],
            'tipo_solicitud' => ['id' => (int) $row['tipo_solicitud_id'], 'nombre' => (string) $row['tipo_nombre']],
            'prioridad' => [
                'id' => (int) $row['prioridad_id'],
                'codigo' => (string) $row['prioridad_codigo'],
                'nombre' => (string) $row['prioridad_nombre'],
            ],
            'estado' => [
                'id' => (int) $row['estado_id'],
                'codigo' => (string) $row['estado_codigo'],
                'nombre' => (string) $row['estado_nombre'],
            ],
            'fecha_registro' => (string) $row['fecha_registro'],
            'fecha_limite' => (string) $row['fecha_limite'],
            'vencida' => (int) $row['vencida'] === 1,
        ];
    }

    /**
     * @param array<string, mixed>       $row
     * @param list<array<string, mixed>> $historial Filas de SolicitudRepository::historial()
     * @return array<string, mixed>
     */
    public static function detalle(array $row, array $historial): array
    {
        $estadoId = (int) $row['estado_id'];

        return self::resumen($row) + [
            'descripcion' => (string) $row['descripcion'],
            'solicitante' => [
                'id' => (int) $row['solicitante_id'],
                'nombres' => (string) $row['sol_nombres'],
                'apellidos' => (string) $row['sol_apellidos'],
            ],
            'responsable' => $row['responsable_id'] === null ? null : [
                'id' => (int) $row['responsable_id'],
                'nombres' => (string) $row['resp_nombres'],
                'apellidos' => (string) $row['resp_apellidos'],
            ],
            'fecha_atencion' => $row['fecha_atencion'],
            'fecha_cierre' => $row['fecha_cierre'],
            'version' => (int) $row['version'],
            'acciones' => [
                'puede_editar' => $estadoId === self::REGISTRADA,
                'puede_cancelar' => in_array($estadoId, [self::REGISTRADA, self::ASIGNADA], true),
            ],
            'historial' => array_map(self::evento(...), $historial),
        ];
    }

    /**
     * @param array<string, mixed> $h
     * @return array<string, mixed>
     */
    private static function evento(array $h): array
    {
        $estado = static fn (string $p): ?array => $h[$p . '_id'] === null ? null : [
            'id' => (int) $h[$p . '_id'],
            'codigo' => (string) $h[$p . '_codigo'],
            'nombre' => (string) $h[$p . '_nombre'],
        ];

        return [
            'id' => (int) $h['id'],
            'tipo_evento' => (string) $h['tipo_evento'],
            'fecha' => (string) $h['fecha'],
            'usuario' => $h['usuario_id'] === null ? null : [
                'id' => (int) $h['usuario_id'],
                'nombres' => (string) $h['usu_nombres'],
                'apellidos' => (string) $h['usu_apellidos'],
            ],
            'estado_anterior' => $estado('estado_anterior'),
            'estado_nuevo' => $estado('estado_nuevo'),
            'motivo' => $h['motivo'],
            'detalle' => $h['detalle'] === null ? null : json_decode((string) $h['detalle'], true),
        ];
    }
}
