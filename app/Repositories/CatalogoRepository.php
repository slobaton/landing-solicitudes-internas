<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Lecturas de catalogos activos (RN-D12) y del calendario laboral (D-05).
 * Listas para el formulario de la landing: GET /api/v1/catalogos (CP-FRONT-04).
 */
final class CatalogoRepository
{
    public function areaActiva(int $id): bool
    {
        return $this->existe('SELECT 1 FROM org_area WHERE id = ? AND activo = 1', $id);
    }

    public function tipoActivo(int $id): bool
    {
        return $this->existe('SELECT 1 FROM cat_tipo_solicitud WHERE id = ? AND activo = 1', $id);
    }

    /** Horas maximas de atencion de la prioridad activa, o null si no existe/inactiva. */
    public function horasPrioridadActiva(int $id): ?int
    {
        $stmt = Database::connection()->prepare(
            'SELECT horas_max_atencion FROM cat_prioridad WHERE id = ? AND activo = 1'
        );
        $stmt->execute([$id]);
        $horas = $stmt->fetchColumn();
        return $horas === false ? null : (int) $horas;
    }

    /**
     * Horario laboral por dia ISO (1 = lunes ... 7 = domingo).
     *
     * @return array<int, array{inicio: string, fin: string, activo: bool}>
     */
    public function horarioLaboral(): array
    {
        $horario = [];
        foreach (Database::connection()->query('SELECT dia_semana, hora_inicio, hora_fin, activo FROM cfg_horario_laboral') as $r) {
            $horario[(int) $r['dia_semana']] = [
                'inicio' => (string) $r['hora_inicio'],
                'fin' => (string) $r['hora_fin'],
                'activo' => (int) $r['activo'] === 1,
            ];
        }
        return $horario;
    }

    /** @return array<string, true> Fechas Y-m-d de feriados en el rango. */
    public function feriados(string $desde, string $hasta): array
    {
        $stmt = Database::connection()->prepare('SELECT fecha FROM cfg_feriado WHERE fecha BETWEEN ? AND ?');
        $stmt->execute([$desde, $hasta]);
        return array_fill_keys(array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN)), true);
    }

    /**
     * Áreas activas para el formulario de registro (GET /catalogos, RN-D12).
     * No expone jefe_usuario_id, activo ni fechas de auditoría.
     *
     * @return list<array{id: int, codigo: string, nombre: string}>
     */
    public function areasActivas(): array
    {
        $filas = [];
        foreach ($this->leer('SELECT id, codigo, nombre FROM org_area WHERE activo = 1 ORDER BY nombre') as $r) {
            $filas[] = ['id' => (int) $r['id'], 'codigo' => (string) $r['codigo'], 'nombre' => (string) $r['nombre']];
        }
        return $filas;
    }

    /** @return list<array{id: int, nombre: string, descripcion: string|null}> */
    public function tiposActivos(): array
    {
        $filas = [];
        foreach ($this->leer('SELECT id, nombre, descripcion FROM cat_tipo_solicitud WHERE activo = 1 ORDER BY nombre') as $r) {
            $filas[] = [
                'id' => (int) $r['id'],
                'nombre' => (string) $r['nombre'],
                'descripcion' => $r['descripcion'] === null ? null : (string) $r['descripcion'],
            ];
        }
        return $filas;
    }

    /** @return list<array{id: int, codigo: string, nombre: string, horas_max_atencion: int}> */
    public function prioridadesActivas(): array
    {
        $filas = [];
        foreach ($this->leer('SELECT id, codigo, nombre, horas_max_atencion FROM cat_prioridad WHERE activo = 1 ORDER BY orden') as $r) {
            $filas[] = [
                'id' => (int) $r['id'],
                'codigo' => (string) $r['codigo'],
                'nombre' => (string) $r['nombre'],
                'horas_max_atencion' => (int) $r['horas_max_atencion'],
            ];
        }
        return $filas;
    }

    /** @return list<array<string, mixed>> */
    private function leer(string $sql): array
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function existe(string $sql, int $id): bool
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetchColumn() !== false;
    }
}
