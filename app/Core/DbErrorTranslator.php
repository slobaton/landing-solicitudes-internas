<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Traduce errores de MySQL a errores de la API (contrato §1.4,
 * transacciones_concurrencia.md §8). Nunca expone el mensaje del motor:
 * el detalle se registra en el log y el cliente recibe un mensaje de negocio.
 */
final class DbErrorTranslator
{
    private const ESTADO = ['RN-D10', 'RN-D11', 'RN-D04', 'RC-03'];
    private const VALIDACION = ['RN-D12', 'RC-05'];

    public static function codigoMysql(\PDOException $e): int
    {
        return (int) ($e->errorInfo[1] ?? 0);
    }

    public static function esReintentable(\PDOException $e): bool
    {
        $codigo = self::codigoMysql($e);
        if ($codigo === 1213 || $codigo === 1205) {
            return true;
        }
        return $codigo === 1062 && str_contains($e->getMessage(), 'uq_sol_solicitud_numero');
    }

    /** Codigo de regla al inicio del mensaje de un trigger (p. ej. "RN-D12"). */
    public static function reglaTrigger(\PDOException $e): ?string
    {
        if (self::codigoMysql($e) !== 1644) {
            return null;
        }
        $mensaje = (string) ($e->errorInfo[2] ?? $e->getMessage());
        return preg_match('/^\s*((?:RN-D|RC-|INT-|S-|R-)\d+):/', $mensaje, $m) === 1 ? $m[1] : null;
    }

    public static function traducir(\PDOException $e): \Throwable
    {
        $codigo = self::codigoMysql($e);
        $regla = self::reglaTrigger($e);

        if ($regla !== null && in_array($regla, self::ESTADO, true)) {
            ErrorHandler::log($e);
            return new ApiException(409, 'ESTADO_NO_PERMITE', 'El estado actual de la solicitud no permite esta acción.');
        }
        if ($regla !== null && in_array($regla, self::VALIDACION, true)) {
            ErrorHandler::log($e);
            return new ApiException(422, 'VALIDACION', 'Algún dato no existe, está inactivo o falta un dato obligatorio.');
        }
        if ($codigo === 3819 || $codigo === 1452) {
            ErrorHandler::log($e);
            return new ApiException(422, 'VALIDACION', 'Los datos enviados no cumplen las reglas de la base de datos.');
        }

        // INT-01, RN-D13, RN-D01, reintentos agotados y cualquier otro error:
        // fallo interno (el ErrorHandler lo registra y responde 500 generico).
        return $e;
    }
}
