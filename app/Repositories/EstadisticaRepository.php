<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Conteos agregados para la sección "Cifras en vivo" (diseno_landing.md §8.1).
 * Solo COUNT por estado: nunca títulos, números, personas ni áreas.
 */
final class EstadisticaRepository
{
    /**
     * Conteo de solicitudes por cada estado de cat_estado (incluye los de 0), en su orden.
     *
     * @return list<array{codigo: string, nombre: string, total: int}>
     */
    public function conteoPorEstado(): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.codigo, e.nombre, COUNT(s.id) AS total
               FROM cat_estado e
               LEFT JOIN sol_solicitud s ON s.estado_id = e.id
              GROUP BY e.id, e.codigo, e.nombre, e.orden
              ORDER BY e.orden'
        );
        $stmt->execute();

        $filas = [];
        foreach ($stmt->fetchAll() as $r) {
            $filas[] = [
                'codigo' => (string) $r['codigo'],
                'nombre' => (string) $r['nombre'],
                'total' => (int) $r['total'],
            ];
        }
        return $filas;
    }

    public function horaServidor(): string
    {
        return (string) Database::connection()->query('SELECT DATE_FORMAT(NOW(), \'%Y-%m-%d %H:%i:%s\')')->fetchColumn();
    }
}
