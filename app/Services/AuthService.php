<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ApiException;
use App\Core\Database;
use App\Core\Session;
use App\Models\Usuario;
use App\Repositories\AuditoriaRepository;
use App\Repositories\UsuarioRepository;

/**
 * Casos de uso de autenticacion (RF-01, RNF-01; transacciones_concurrencia.md T-12).
 */
final class AuthService
{
    /** Hash ficticio para igualar el tiempo de respuesta si el usuario no existe. */
    private const HASH_FICTICIO = '$argon2id$v=19$m=65536,t=4,p=1$deM0ZMbSgL8uYDt1ws1aZg$jmzTpl0T9IIoMoLP0EU2gpQmI9QbTheBlEBXsVkRIr4';

    public function __construct(
        private UsuarioRepository $usuarios = new UsuarioRepository(),
        private AuditoriaRepository $auditoria = new AuditoriaRepository(),
        private ?LoginThrottle $throttle = null,
    ) {
        if ($this->throttle === null) {
            $cfg = require dirname(__DIR__, 2) . '/config/app.php';
            $this->throttle = new LoginThrottle($cfg['throttle_path'], $cfg['login_max_intentos'], $cfg['login_ventana_segundos']);
        }
    }

    /**
     * @return array{usuario: Usuario, csrf_token: string}
     */
    public function login(string $nombreUsuario, string $password, string $ip): array
    {
        $espera = $this->throttle->esperaRestante($nombreUsuario, $ip);
        if ($espera > 0) {
            throw new ApiException(
                429,
                'DEMASIADOS_INTENTOS',
                'Demasiados intentos fallidos. Intente nuevamente más tarde.',
                null,
                ['Retry-After' => (string) $espera]
            );
        }

        $cred = $this->usuarios->findCredenciales($nombreUsuario);
        $hash = $cred['password_hash'] ?? self::HASH_FICTICIO;
        $valida = password_verify($password, $hash);

        if ($cred === null || !$valida || !$cred['activo']) {
            $this->throttle->registrarFallo($nombreUsuario, $ip);
            $this->auditoria->loginFallido($cred['id'] ?? null, $nombreUsuario, $ip);
            throw new ApiException(401, 'CREDENCIALES_INVALIDAS', 'Usuario o contraseña incorrectos.');
        }

        $usuarioId = $cred['id'];
        Database::setContext($usuarioId, $ip);

        if (password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
            $this->usuarios->actualizarHash($usuarioId, password_hash($password, PASSWORD_ARGON2ID));
        }
        $this->usuarios->registrarAcceso($usuarioId);
        $this->auditoria->login($usuarioId, $ip);
        $this->throttle->limpiar($nombreUsuario, $ip);

        $usuario = $this->usuarios->findById($usuarioId);
        if ($usuario === null) {
            throw new \RuntimeException('Usuario no encontrado tras un login correcto');
        }

        Session::start();
        $token = Session::login($usuarioId, $usuario->roles);

        return ['usuario' => $usuario, 'csrf_token' => $token];
    }

    public function logout(): void
    {
        if (Session::resume()) {
            Session::destroy();
        }
    }

    /**
     * Comprueba que hay sesion y que su usuario sigue existiendo y activo
     * (decision del usuario 2026-10-07: verificacion en cada peticion
     * autenticada). Si no, destruye la sesion y responde 401 NO_AUTENTICADO.
     * La usan AuthMiddleware y /auth/me.
     */
    public function verificarSesion(): int
    {
        $usuarioId = Session::resume() ? Session::usuarioId() : null;
        if ($usuarioId === null) {
            throw ApiException::noAutenticado();
        }
        if (!$this->usuarios->estaActivo($usuarioId)) {
            Session::destroy();
            throw ApiException::noAutenticado();
        }
        return $usuarioId;
    }

    /**
     * Usuario de la sesion releido de la BD (contrato §3.3).
     *
     * @return array{usuario: Usuario, csrf_token: string}
     */
    public function actual(): array
    {
        $usuario = $this->usuarios->findById($this->verificarSesion());
        if ($usuario === null) {
            Session::destroy();
            throw ApiException::noAutenticado();
        }

        return ['usuario' => $usuario, 'csrf_token' => (string) Session::csrfToken()];
    }
}
