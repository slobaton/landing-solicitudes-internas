<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Respuesta JSON uniforme (decisiones_api.md, contrato §1.1-§1.2).
 * Respuesta HTML solo para las vistas web (CP-FRONT-02, Response::html).
 */
final class Response
{
    /** Cabeceras de seguridad de las páginas HTML (sin recursos externos ni scripts en línea). */
    private const HTML_HEADERS = [
        'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; "
            . "connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'",
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'same-origin',
        'Cache-Control' => 'no-cache',
    ];

    /**
     * @param array<string, mixed>|string $body    arreglo => JSON; cadena => HTML ya renderizado
     * @param array<string, string>       $headers
     */
    private function __construct(
        private int $status,
        private array|string $body,
        private array $headers = [],
        private string $contentType = 'application/json; charset=utf-8',
    ) {
    }

    /** @param array<string, string> $headers */
    public static function html(string $html, int $status = 200, array $headers = []): self
    {
        return new self($status, $html, $headers + self::HTML_HEADERS, 'text/html; charset=utf-8');
    }

    /**
     * @param array<string, mixed>|null $meta
     * @param array<string, string>     $headers
     */
    public static function ok(mixed $data, int $status = 200, ?array $meta = null, array $headers = []): self
    {
        $body = ['ok' => true, 'data' => $data];
        if ($meta !== null) {
            $body['meta'] = $meta;
        }
        return new self($status, $body, $headers);
    }

    /**
     * @param array<string, list<string>>|null $detalles
     * @param array<string, string>            $headers
     */
    public static function error(int $status, string $codigo, string $mensaje, ?array $detalles = null, array $headers = []): self
    {
        return new self($status, [
            'ok' => false,
            'error' => ['codigo' => $codigo, 'mensaje' => $mensaje, 'detalles' => $detalles],
        ], $headers);
    }

    public static function fromException(ApiException $e): self
    {
        return self::error($e->status, $e->codigo, $e->getMessage(), $e->detalles, $e->headers);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            header_remove('X-Powered-By');
            http_response_code($this->status);
            header('Content-Type: ' . $this->contentType);
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store');
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        if (is_string($this->body)) {
            echo $this->body;
            return;
        }
        echo json_encode(
            $this->body,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }
}
