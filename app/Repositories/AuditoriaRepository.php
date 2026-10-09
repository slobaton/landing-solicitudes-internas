<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Inserciones de LOGIN / LOGIN_FALLIDO en aud_auditoria (auditoria.md §3, T-12).
 * Nunca se guarda la contrasena intentada.
 */
final class AuditoriaRepository
{
    public function login(int $usuarioId, string $ip): void
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id, valores_anteriores, valores_nuevos, ip_origen)
             VALUES ('seg_usuario', ?, 'LOGIN', ?, NULL, NULL, ?)"
        );
        $stmt->execute([(string) $usuarioId, $usuarioId, substr($ip, 0, 45)]);
    }

    public function loginFallido(?int $usuarioId, string $nombreIntentado, string $ip): void
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO aud_auditoria (entidad, registro_id, accion, usuario_id, valores_anteriores, valores_nuevos, ip_origen)
             VALUES ('seg_usuario', ?, 'LOGIN_FALLIDO', NULL, NULL, ?, ?)"
        );
        $stmt->execute([
            $usuarioId === null ? '-' : (string) $usuarioId,
            json_encode(['nombre_usuario_intentado' => mb_substr($nombreIntentado, 0, 50)], JSON_UNESCAPED_UNICODE),
            substr($ip, 0, 45),
        ]);
    }
}
