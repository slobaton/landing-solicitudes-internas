<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Manejo centralizado de errores (skill error-handling-patterns):
 * - ApiException -> respuesta controlada;
 * - cualquier otro Throwable (PDOException incluida) -> 500 ERROR_INTERNO
 *   sin exponer SQL, trazas ni mensajes del motor; el detalle va al log.
 */
final class ErrorHandler
{
    private static string $logFile = '';

    public static function register(string $logFile): void
    {
        self::$logFile = $logFile;
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);
        error_reporting(E_ALL);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (\Throwable $e): void {
            self::toResponse($e)->send();
        });
    }

    public static function toResponse(\Throwable $e): Response
    {
        if ($e instanceof ApiException) {
            return Response::fromException($e);
        }

        self::log($e);
        return Response::error(500, 'ERROR_INTERNO', 'Ocurrió un error interno. Intente nuevamente.');
    }

    public static function log(\Throwable $e): void
    {
        $line = sprintf(
            "[%s] %s: %s en %s:%d\n",
            date('Y-m-d H:i:s'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );
        error_log($line, 3, self::$logFile);
    }
}
