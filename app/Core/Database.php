<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Conexion PDO unica por peticion (cuenta sis_app con TLS, decisiones_api.md).
 * Fija en la sesion MySQL: zona horaria, READ COMMITTED, lock_wait_timeout
 * y el contexto de auditoria @sis_usuario_id / @sis_ip (V007).
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static ?int $usuarioId = null;
    private static ?string $ip = null;

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        /** @var array<string, mixed> $cfg */
        $cfg = require dirname(__DIR__, 2) . '/config/database.php';

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => $cfg['timeout'],
        ];
        if ($cfg['ssl_ca'] !== '') {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $cfg['ssl_ca'];
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $cfg['ssl_verify'];
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['host'],
            $cfg['port'],
            $cfg['name']
        );

        $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], $options);

        $pdo->exec("SET time_zone = '" . $cfg['time_zone'] . "'");
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 10');

        self::$pdo = $pdo;
        self::applyContext();

        return $pdo;
    }

    /**
     * Ejecuta $fn dentro de una transaccion. Reintenta hasta $intentos veces ante
     * interbloqueo (1213), espera agotada (1205) o numero duplicado (1062 en
     * uq_sol_solicitud_numero), con espera aleatoria de 50-200 ms
     * (transacciones_concurrencia.md §2, §6). Cualquier otro error del motor se
     * traduce con DbErrorTranslator (contrato §1.4).
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public static function transaction(callable $fn, int $intentos = 3): mixed
    {
        $pdo = self::connection();

        for ($intento = 1; ; $intento++) {
            $pdo->beginTransaction();
            try {
                $resultado = $fn();
                $pdo->commit();
                return $resultado;
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($e instanceof \PDOException && DbErrorTranslator::esReintentable($e) && $intento < $intentos) {
                    usleep(random_int(50_000, 200_000));
                    continue;
                }
                if ($e instanceof \PDOException) {
                    throw DbErrorTranslator::traducir($e);
                }
                throw $e;
            }
        }
    }

    /**
     * Contexto de auditoria. Si la conexion ya existe se aplica de inmediato;
     * si no, al conectar.
     */
    public static function setContext(?int $usuarioId, ?string $ip): void
    {
        self::$usuarioId = $usuarioId;
        self::$ip = $ip !== null ? substr($ip, 0, 45) : null;
        if (self::$pdo !== null) {
            self::applyContext();
        }
    }

    private static function applyContext(): void
    {
        $stmt = self::$pdo->prepare('SET @sis_usuario_id = ?, @sis_ip = ?');
        $stmt->bindValue(1, self::$usuarioId, self::$usuarioId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(2, self::$ip, self::$ip === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();
    }
}
