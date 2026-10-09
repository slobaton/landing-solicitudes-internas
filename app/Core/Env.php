<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Carga de variables desde el archivo .env (no versionado).
 * Formato admitido: CLAVE=valor, lineas vacias y comentarios con #.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('No se encontro el archivo de configuracion .env');
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$values[$key] ?? $default;
    }

    public static function require(string $key): string
    {
        $value = self::$values[$key] ?? null;
        if ($value === null || $value === '') {
            throw new \RuntimeException("Falta la variable de configuracion {$key} en .env");
        }
        return $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::$values[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
