<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router minimo: metodo + ruta con parametros {nombre}, middleware y
 * resolucion de controlador. 404 RUTA_NO_ENCONTRADA / 405 METODO_NO_PERMITIDO.
 */
final class Router
{
    /** @var list<array{method: string, pattern: string, regex: string, handler: array{0: class-string, 1: string}, middleware: list<class-string>}> */
    private array $routes = [];

    /** @param list<class-string> $middleware */
    public function get(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    /** @param list<class-string> $middleware */
    public function post(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    /** @param list<class-string> $middleware */
    public function put(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('PUT', $pattern, $handler, $middleware);
    }

    /** @param list<class-string> $middleware */
    public function delete(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('DELETE', $pattern, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<class-string>                $middleware
     */
    private function add(string $method, string $pattern, array $handler, array $middleware): void
    {
        // La raiz '/' se conserva tal cual (Request normaliza la ruta vacia a '/').
        $base = $pattern === '/' ? '/' : rtrim($pattern, '/');
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $base) . '$#';
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => $regex,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path(), $matches)) {
                continue;
            }
            if ($route['method'] !== $request->method()) {
                $allowed[] = $route['method'];
                continue;
            }

            $request->withParams(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));

            foreach ($route['middleware'] as $middlewareClass) {
                (new $middlewareClass())->handle($request);
            }

            [$class, $action] = $route['handler'];
            return (new $class())->$action($request);
        }

        if ($allowed !== []) {
            throw new ApiException(
                405,
                'METODO_NO_PERMITIDO',
                'Método no permitido para esta ruta.',
                null,
                ['Allow' => implode(', ', array_unique($allowed))]
            );
        }

        throw new ApiException(404, 'RUTA_NO_ENCONTRADA', 'La ruta solicitada no existe.');
    }
}
