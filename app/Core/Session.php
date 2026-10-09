<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sesion PHP: cookie httpOnly + SameSite=Lax, modo estricto, expiracion por
 * inactividad y token CSRF (contrato §1, §3).
 */
final class Session
{
    private static bool $started = false;

    /** @var array<string, mixed> */
    private static array $cfg = [];

    /**
     * Reanuda la sesion solo si el cliente envio la cookie: las peticiones
     * anonimas no crean sesiones ni reciben Set-Cookie.
     */
    public static function resume(): bool
    {
        if (self::$started) {
            return true;
        }
        $name = (require dirname(__DIR__, 2) . '/config/session.php')['name'];
        if (!isset($_COOKIE[$name]) || !is_string($_COOKIE[$name]) || $_COOKIE[$name] === '') {
            return false;
        }
        self::start();
        return true;
    }

    public static function start(): void
    {
        if (self::$started) {
            return;
        }

        self::$cfg = require dirname(__DIR__, 2) . '/config/session.php';

        if (!is_dir(self::$cfg['save_path'])) {
            mkdir(self::$cfg['save_path'], 0700, true);
        }
        session_save_path(self::$cfg['save_path']);
        session_name(self::$cfg['name']);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) self::$cfg['idle_timeout']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => self::$cfg['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::$started = true;

        $last = $_SESSION['_ultima_actividad'] ?? null;
        if (is_int($last) && time() - $last > self::$cfg['idle_timeout']) {
            self::destroy();
            session_start();
        }
        $_SESSION['_ultima_actividad'] = time();
    }

    /**
     * Inicio de sesion autenticada: nuevo id (evita fijacion) y nuevo token CSRF.
     *
     * @param list<string> $roles
     */
    public static function login(int $usuarioId, array $roles): string
    {
        session_regenerate_id(true);
        $token = bin2hex(random_bytes(32));
        $_SESSION['usuario_id'] = $usuarioId;
        $_SESSION['roles'] = $roles;
        $_SESSION['csrf_token'] = $token;
        $_SESSION['_ultima_actividad'] = time();
        return $token;
    }

    public static function usuarioId(): ?int
    {
        $id = $_SESSION['usuario_id'] ?? null;
        return is_int($id) ? $id : null;
    }

    public static function csrfToken(): ?string
    {
        $token = $_SESSION['csrf_token'] ?? null;
        return is_string($token) ? $token : null;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $params['path'],
                'secure' => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_destroy();
        }
    }
}
