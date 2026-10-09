<?php

declare(strict_types=1);

/**
 * Crea los usuarios de demostracion (CP-API-02):
 *   - un SOLICITANTE;
 *   - un JEFE_AREA (roles SOLICITANTE + JEFE_AREA), asignado como jefe del area
 *     si esta aun no tiene jefe (TRG-08 valida rol, area y estado activo).
 *
 * Uso:  php tools/crear_usuarios_demo.php
 *
 * Variables en .env (no versionado):
 *   DEMO_AREA_CODIGO            (opcional; por defecto ADMIN_AREA_CODIGO)
 *   DEMO_SOLICITANTE_USUARIO    DEMO_SOLICITANTE_PASSWORD
 *   DEMO_JEFE_USUARIO           DEMO_JEFE_PASSWORD
 *
 * Idempotente: si el usuario ya existe no se modifica. Nunca imprime contrasenas.
 * Solo INSERT/UPDATE; no borra nada.
 */

use App\Core\Database;
use App\Core\Env;
use App\Repositories\AreaRepository;
use App\Repositories\UsuarioRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap/app.php';
restore_exception_handler();

const ROL_SOLICITANTE = 1;
const ROL_JEFE_AREA = 2;

try {
    $areaCodigo = Env::get('DEMO_AREA_CODIGO') ?: Env::require('ADMIN_AREA_CODIGO');

    $demo = [
        [
            'usuario' => Env::require('DEMO_SOLICITANTE_USUARIO'),
            'password' => Env::require('DEMO_SOLICITANTE_PASSWORD'),
            'nombres' => 'Sofía',
            'apellidos' => 'Solicitante Demo',
            'roles' => [ROL_SOLICITANTE],
            'jefe' => false,
        ],
        [
            'usuario' => Env::require('DEMO_JEFE_USUARIO'),
            'password' => Env::require('DEMO_JEFE_PASSWORD'),
            'nombres' => 'Jorge',
            'apellidos' => 'Jefe Demo',
            'roles' => [ROL_SOLICITANTE, ROL_JEFE_AREA],
            'jefe' => true,
        ],
    ];

    foreach ($demo as $d) {
        if (strlen($d['password']) < 12) {
            throw new RuntimeException("La contraseña de {$d['usuario']} debe tener al menos 12 caracteres.");
        }
    }

    Database::setContext(null, 'cli:crear_usuarios_demo');

    $areas = new AreaRepository();
    $usuarios = new UsuarioRepository();

    $area = $areas->findActivaPorCodigo($areaCodigo);
    if ($area === null) {
        throw new RuntimeException("El área {$areaCodigo} no existe o está inactiva.");
    }

    foreach ($demo as $d) {
        $existente = $usuarios->findByNombreUsuario($d['usuario']);
        if ($existente !== null) {
            $id = $existente->id;
            echo "= {$d['usuario']}: ya existe (id {$id}); no se modifica.\n";
        } else {
            $id = $usuarios->crear(
                $d['usuario'],
                $d['nombres'],
                $d['apellidos'],
                $d['usuario'] . '@institucion.local',
                password_hash($d['password'], PASSWORD_ARGON2ID),
                $area['id'],
                $d['roles'],
            );
            echo "+ {$d['usuario']}: creado (id {$id}), área {$area['codigo']}, roles " . implode(',', $d['roles']) . ".\n";
        }

        if ($d['jefe']) {
            $area = $areas->findActivaPorCodigo($areaCodigo);
            if ($area['jefe_usuario_id'] === null) {
                $areas->asignarJefe($area['id'], $id);
                echo "+ {$d['usuario']}: asignado como jefe del área {$area['codigo']}.\n";
            } elseif ($area['jefe_usuario_id'] === $id) {
                echo "= {$d['usuario']}: ya es jefe del área {$area['codigo']}.\n";
            } else {
                echo "! El área {$area['codigo']} ya tiene otro jefe (id {$area['jefe_usuario_id']}); no se cambia.\n";
            }
        }
    }

    echo "Listo.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
