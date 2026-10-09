<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Validators\AuthValidator;

/**
 * POST /auth/login, POST /auth/logout, GET /auth/me (contrato §3).
 */
final class AuthController
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    public function login(Request $request): Response
    {
        $datos = (new AuthValidator())->login($request->json());
        $resultado = $this->auth->login($datos['nombre_usuario'], $datos['password'], $request->ip());

        return Response::ok([
            'usuario' => $resultado['usuario']->toArray(),
            'csrf_token' => $resultado['csrf_token'],
        ]);
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();
        return Response::ok(['mensaje' => 'Sesión cerrada.']);
    }

    public function me(Request $request): Response
    {
        $resultado = $this->auth->actual();

        return Response::ok([
            'usuario' => $resultado['usuario']->toArray(),
            'csrf_token' => $resultado['csrf_token'],
        ]);
    }
}
