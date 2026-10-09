<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Lectura de org_area y asignacion de jefe (solo herramienta de demo; TRG-08 valida).
 */
final class AreaRepository
{
    /** @return array{id: int, codigo: string, jefe_usuario_id: int|null}|null */
    public function findActivaPorCodigo(string $codigo): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, codigo, jefe_usuario_id FROM org_area WHERE codigo = ? AND activo = 1'
        );
        $stmt->execute([$codigo]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'codigo' => (string) $row['codigo'],
            'jefe_usuario_id' => $row['jefe_usuario_id'] === null ? null : (int) $row['jefe_usuario_id'],
        ];
    }

    public function asignarJefe(int $areaId, int $usuarioId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE org_area SET jefe_usuario_id = ? WHERE id = ? AND jefe_usuario_id IS NULL'
        );
        $stmt->execute([$usuarioId, $areaId]);
    }
}
