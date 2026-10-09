<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Services\AuthService;

/**
 * Exige sesion iniciada y usuario existente y activo (401 NO_AUTENTICADO;
 * si el usuario fue inactivado, la sesion se destruye). Fija el usuario en el
 * contexto de auditoria de la conexion (@sis_usuario_id).
 */
final class AuthMiddleware
{
    public function handle(Request $request): void
    {
        $usuarioId = (new AuthService())->verificarSesion();
        Database::setContext($usuarioId, $request->ip());
    }
}
