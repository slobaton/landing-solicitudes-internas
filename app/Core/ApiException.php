<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Error controlado de la API: se traduce a la respuesta JSON uniforme
 * { ok: false, error: { codigo, mensaje, detalles } } (contrato §1.2).
 */
class ApiException extends \RuntimeException
{
    /**
     * @param array<string, list<string>>|null $detalles
     * @param array<string, string>            $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $codigo,
        string $mensaje,
        public readonly ?array $detalles = null,
        public readonly array $headers = [],
    ) {
        parent::__construct($mensaje, $status);
    }

    public static function validacion(array $detalles, string $mensaje = 'Los datos enviados no son válidos.'): self
    {
        return new self(422, 'VALIDACION', $mensaje, $detalles);
    }

    public static function noAutenticado(): self
    {
        return new self(401, 'NO_AUTENTICADO', 'Debe iniciar sesión.');
    }
}
