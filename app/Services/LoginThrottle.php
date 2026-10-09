<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Limite de intentos fallidos de login por nombre de usuario + IP
 * (seguridad.md: la limitacion se aplica en la capa de aplicacion).
 * Almacenamiento en archivos locales: no modifica el esquema de la BD.
 */
final class LoginThrottle
{
    public function __construct(
        private string $path,
        private int $maxIntentos,
        private int $ventanaSegundos,
    ) {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0700, true);
        }
    }

    /** Segundos de espera restantes, o 0 si puede intentar. */
    public function esperaRestante(string $nombreUsuario, string $ip): int
    {
        $fallos = $this->leer($this->archivo($nombreUsuario, $ip));
        if (count($fallos) < $this->maxIntentos) {
            return 0;
        }
        return max(1, min($fallos) + $this->ventanaSegundos - time());
    }

    public function registrarFallo(string $nombreUsuario, string $ip): void
    {
        $archivo = $this->archivo($nombreUsuario, $ip);
        $fallos = $this->leer($archivo);
        $fallos[] = time();
        file_put_contents($archivo, json_encode(array_slice($fallos, -$this->maxIntentos)), LOCK_EX);
    }

    public function limpiar(string $nombreUsuario, string $ip): void
    {
        $archivo = $this->archivo($nombreUsuario, $ip);
        if (is_file($archivo)) {
            unlink($archivo);
        }
    }

    private function archivo(string $nombreUsuario, string $ip): string
    {
        return $this->path . '/' . hash('sha256', mb_strtolower($nombreUsuario) . '|' . $ip) . '.json';
    }

    /** @return list<int> Marcas de tiempo de fallos dentro de la ventana. */
    private function leer(string $archivo): array
    {
        if (!is_file($archivo)) {
            return [];
        }
        $datos = json_decode((string) file_get_contents($archivo), true);
        if (!is_array($datos)) {
            return [];
        }
        $limite = time() - $this->ventanaSegundos;
        return array_values(array_filter(array_map('intval', $datos), static fn (int $t): bool => $t > $limite));
    }
}
