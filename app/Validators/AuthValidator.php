<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\ApiException;

/**
 * Validacion de entrada del login (contrato §3.1).
 */
final class AuthValidator
{
    /**
     * @param array<string, mixed> $input
     * @return array{nombre_usuario: string, password: string}
     */
    public function login(array $input): array
    {
        $errores = [];

        $nombre = $input['nombre_usuario'] ?? null;
        if (!is_string($nombre) || trim($nombre) === '') {
            $errores['nombre_usuario'][] = 'El nombre de usuario es obligatorio.';
        } else {
            $nombre = trim($nombre);
            $largo = mb_strlen($nombre);
            if ($largo < 3 || $largo > 50) {
                $errores['nombre_usuario'][] = 'El nombre de usuario debe tener entre 3 y 50 caracteres.';
            }
        }

        $password = $input['password'] ?? null;
        if (!is_string($password) || $password === '') {
            $errores['password'][] = 'La contraseña es obligatoria.';
        } elseif (strlen($password) > 255) {
            $errores['password'][] = 'La contraseña no puede superar 255 caracteres.';
        }

        if ($errores !== []) {
            throw ApiException::validacion($errores);
        }

        return ['nombre_usuario' => $nombre, 'password' => $password];
    }
}
