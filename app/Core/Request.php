<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Peticion HTTP: metodo, ruta, parametros, cabeceras y cuerpo JSON.
 */
final class Request
{
    /** @var array<string, string> */
    private array $params = [];

    /** @var array<string, mixed>|null */
    private ?array $json = null;

    /**
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $headers,
        private string $rawBody,
        private string $ip,
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';
        $path = '/' . trim($path, '/');

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $headers,
            (string) file_get_contents('php://input'),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function queryAll(): array
    {
        return $this->query;
    }

    /** @param array<string, string> $params */
    public function withParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $key): ?string
    {
        return $this->params[$key] ?? null;
    }

    /**
     * Cuerpo JSON como objeto (array asociativo). 400 si no es JSON valido
     * o si el Content-Type no es application/json (contrato §1.3).
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }

        $contentType = strtolower($this->header('content-type') ?? '');
        if (!str_starts_with($contentType, 'application/json')) {
            throw new ApiException(400, 'JSON_INVALIDO', 'El cuerpo debe enviarse como application/json.');
        }

        try {
            $data = json_decode($this->rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiException(400, 'JSON_INVALIDO', 'El cuerpo no es un JSON válido.');
        }

        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new ApiException(400, 'JSON_INVALIDO', 'El cuerpo debe ser un objeto JSON.');
        }

        return $this->json = $data;
    }
}
