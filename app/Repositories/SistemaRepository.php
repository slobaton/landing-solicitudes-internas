<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Consultas de diagnostico (health).
 */
final class SistemaRepository
{
    /** Ejecuta SELECT 1 y devuelve la hora de la BD. */
    public function ping(): string
    {
        $row = Database::connection()->query('SELECT 1 AS uno, NOW() AS ahora')->fetch();
        if ((int) $row['uno'] !== 1) {
            throw new \RuntimeException('Respuesta inesperada de SELECT 1');
        }
        return (string) $row['ahora'];
    }
}
