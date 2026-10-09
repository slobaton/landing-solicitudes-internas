<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\ApiException;
use App\Core\Request;
use App\Core\Session;

/**
 * Exige la cabecera X-CSRF-Token igual al token de la sesion (403 CSRF_INVALIDO).
 * Se aplica despues de AuthMiddleware en POST/PUT/DELETE.
 */
final class CsrfMiddleware
{
    public function handle(Request $request): void
    {
        $esperado = Session::resume() ? Session::csrfToken() : null;
        $recibido = $request->header('x-csrf-token');

        if ($esperado === null || $recibido === null || !hash_equals($esperado, $recibido)) {
            throw new ApiException(403, 'CSRF_INVALIDO', 'Token CSRF ausente o inválido.');
        }
    }
}
