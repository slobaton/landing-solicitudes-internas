<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\Usuario;

/**
 * Persistencia de seg_usuario / seg_usuario_rol (PA-01) y auditoria de acceso (T-12).
 */
final class UsuarioRepository
{
    private const SELECT_USUARIO = <<<'SQL'
        SELECT u.id, u.nombre_usuario, u.nombres, u.apellidos, u.correo, u.activo,
               u.area_id, a.codigo AS area_codigo, a.nombre AS area_nombre
          FROM seg_usuario u
          JOIN org_area a ON a.id = u.area_id
        SQL;

    /**
     * Credenciales para el login: id, activo y password_hash (uso interno del servicio).
     *
     * @return array{id: int, activo: bool, password_hash: string}|null
     */
    public function findCredenciales(string $nombreUsuario): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, activo, password_hash FROM seg_usuario WHERE nombre_usuario = ?'
        );
        $stmt->execute([$nombreUsuario]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'activo' => (int) $row['activo'] === 1,
            'password_hash' => (string) $row['password_hash'],
        ];
    }

    public function findById(int $id): ?Usuario
    {
        $stmt = Database::connection()->prepare(self::SELECT_USUARIO . ' WHERE u.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return Usuario::fromRow($row, $this->rolesDe($id));
    }

    public function findByNombreUsuario(string $nombreUsuario): ?Usuario
    {
        $stmt = Database::connection()->prepare(self::SELECT_USUARIO . ' WHERE u.nombre_usuario = ?');
        $stmt->execute([$nombreUsuario]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return Usuario::fromRow($row, $this->rolesDe((int) $row['id']));
    }

    /** true si el usuario existe y esta activo (SELECT por PK). */
    public function estaActivo(int $id): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM seg_usuario WHERE id = ? AND activo = 1');
        $stmt->execute([$id]);
        return $stmt->fetchColumn() !== false;
    }

    /** @return list<string> */
    public function rolesDe(int $usuarioId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.codigo FROM seg_usuario_rol ur JOIN seg_rol r ON r.id = ur.rol_id
              WHERE ur.usuario_id = ? ORDER BY r.id'
        );
        $stmt->execute([$usuarioId]);
        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function registrarAcceso(int $usuarioId): void
    {
        $stmt = Database::connection()->prepare('UPDATE seg_usuario SET ultimo_acceso_en = NOW() WHERE id = ?');
        $stmt->execute([$usuarioId]);
    }

    public function actualizarHash(int $usuarioId, string $hash): void
    {
        $stmt = Database::connection()->prepare('UPDATE seg_usuario SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $usuarioId]);
    }

    /**
     * Crea un usuario con sus roles en una transaccion (herramienta de demo).
     * Los triggers de V007 auditan la creacion con @sis_usuario_id / @sis_ip.
     *
     * @param list<int> $rolIds
     */
    public function crear(string $nombreUsuario, string $nombres, string $apellidos, string $correo, string $hash, int $areaId, array $rolIds): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO seg_usuario (nombre_usuario, nombres, apellidos, correo, password_hash, area_id)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$nombreUsuario, $nombres, $apellidos, $correo, $hash, $areaId]);
            $id = (int) $pdo->lastInsertId();

            $rol = $pdo->prepare('INSERT INTO seg_usuario_rol (usuario_id, rol_id, asignado_por) VALUES (?, ?, NULL)');
            foreach ($rolIds as $rolId) {
                $rol->execute([$id, $rolId]);
            }
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
