<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Renderizador de vistas PHP (estructura_frontend.md): solo presentación.
 * Las vistas se ejecutan con $this = View, por lo que disponen de
 * $this->e(), $this->parcial() y $this->asset(). Sin SQL ni reglas de negocio.
 */
final class View
{
    private const VIEWS_DIR = BASE_PATH . '/app/Views/';
    private const PUBLIC_DIR = BASE_PATH . '/public/';

    /**
     * Renderiza una vista dentro de un layout (la vista queda en $contenido).
     *
     * @param array<string, mixed> $datos
     */
    public static function pagina(string $vista, array $datos = [], string $layout = 'layouts/main'): string
    {
        $view = new self();
        $contenido = $view->parcial($vista, $datos);
        return $view->parcial($layout, $datos + ['contenido' => $contenido]);
    }

    /** @param array<string, mixed> $datos */
    public function parcial(string $vista, array $datos = []): string
    {
        if (preg_match('#^[a-z0-9_\-]+(/[a-z0-9_\-]+)*$#', $vista) !== 1) {
            throw new \InvalidArgumentException('Nombre de vista no válido.');
        }
        $archivo = self::VIEWS_DIR . $vista . '.php';
        if (!is_file($archivo)) {
            throw new \RuntimeException('Vista inexistente: ' . $vista);
        }

        $render = function (string $__archivo, array $__datos): void {
            extract($__datos, EXTR_SKIP);
            require $__archivo;
        };

        ob_start();
        try {
            $render($archivo, $datos);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** Escape obligatorio de toda salida (estructura_frontend.md, Seguridad). */
    public function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }

    /** URL de un recurso de public/assets con versión por fecha de modificación (evita caché vieja). */
    public function asset(string $ruta): string
    {
        $ruta = ltrim($ruta, '/');
        $archivo = self::PUBLIC_DIR . 'assets/' . $ruta;
        $version = is_file($archivo) ? (string) filemtime($archivo) : '0';
        return $this->e('/assets/' . $ruta . '?v=' . $version);
    }
}
