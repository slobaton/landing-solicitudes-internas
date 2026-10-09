<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\ApiException;
use App\Models\Solicitud;

/**
 * Validacion de entrada de solicitudes (contrato §4.2, §4.4, §4.5).
 * Solo formato: la existencia/actividad de catalogos la valida el servicio.
 */
final class SolicitudValidator
{
    public const TITULO_MAX = 150;
    public const DESCRIPCION_MAX = 5000;

    /**
     * @param array<string, mixed> $input
     * @return array{titulo: string, descripcion: string, area_destino_id: int, tipo_solicitud_id: int, prioridad_id: int}
     */
    public function registrar(array $input): array
    {
        $errores = [];

        $titulo = $this->texto($input, 'titulo', 'El título', 'obligatorio', self::TITULO_MAX, $errores);
        $descripcion = $this->texto($input, 'descripcion', 'La descripción', 'obligatoria', self::DESCRIPCION_MAX, $errores);
        $area = $this->entero($input, 'area_destino_id', 'El área destino', 'obligatoria', $errores);
        $tipo = $this->entero($input, 'tipo_solicitud_id', 'El tipo de solicitud', 'obligatorio', $errores);
        $prioridad = $this->entero($input, 'prioridad_id', 'La prioridad', 'obligatoria', $errores);
        if ($prioridad !== null && ($prioridad < 1 || $prioridad > 4)) {
            $errores['prioridad_id'][] = 'La prioridad debe estar entre 1 y 4.';
        }

        if ($errores !== []) {
            throw ApiException::validacion($errores);
        }

        return [
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'area_destino_id' => $area,
            'tipo_solicitud_id' => $tipo,
            'prioridad_id' => $prioridad,
        ];
    }

    public const MOTIVO_MAX = 500;

    /**
     * Edicion (contrato §4.6): titulo, descripcion y tipo obligatorios + version.
     * area_destino_id y prioridad_id son opcionales: si llegan se validan como
     * enteros y el servicio los compara con los actuales.
     *
     * @param array<string, mixed> $input
     * @return array{titulo: string, descripcion: string, tipo_solicitud_id: int, version: int, area_destino_id: ?int, prioridad_id: ?int}
     */
    public function editar(array $input): array
    {
        $errores = [];

        $titulo = $this->texto($input, 'titulo', 'El título', 'obligatorio', self::TITULO_MAX, $errores);
        $descripcion = $this->texto($input, 'descripcion', 'La descripción', 'obligatoria', self::DESCRIPCION_MAX, $errores);
        $tipo = $this->entero($input, 'tipo_solicitud_id', 'El tipo de solicitud', 'obligatorio', $errores);
        $version = $this->version($input, $errores);

        $area = array_key_exists('area_destino_id', $input)
            ? $this->entero($input, 'area_destino_id', 'El área destino', 'obligatoria', $errores) : null;
        $prioridad = array_key_exists('prioridad_id', $input)
            ? $this->entero($input, 'prioridad_id', 'La prioridad', 'obligatoria', $errores) : null;

        if ($errores !== []) {
            throw ApiException::validacion($errores);
        }

        return [
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'tipo_solicitud_id' => $tipo,
            'version' => $version,
            'area_destino_id' => $area,
            'prioridad_id' => $prioridad,
        ];
    }

    /**
     * Cancelacion (contrato §4.7): motivo 1..500 tras trim y version.
     *
     * @param array<string, mixed> $input
     * @return array{motivo: string, version: int}
     */
    public function cancelar(array $input): array
    {
        $errores = [];
        $motivo = $this->texto($input, 'motivo', 'El motivo', 'obligatorio', self::MOTIVO_MAX, $errores);
        $version = $this->version($input, $errores);

        if ($errores !== []) {
            throw ApiException::validacion($errores);
        }

        return ['motivo' => $motivo, 'version' => $version];
    }

    /** @param array<string, list<string>> $errores */
    private function version(array $input, array &$errores): ?int
    {
        $valor = $input['version'] ?? null;
        if ($valor === null || $valor === '') {
            $errores['version'][] = 'La versión es obligatoria.';
            return null;
        }
        if (is_int($valor) && $valor >= 0) {
            return $valor;
        }
        if (is_string($valor) && preg_match('/^[0-9]{1,9}$/', $valor) === 1) {
            return (int) $valor;
        }
        $errores['version'][] = 'La versión debe ser un entero mayor o igual a 0.';
        return null;
    }

    /** {id} de la ruta: entero positivo; si no, 400 PARAMETRO_INVALIDO. */
    public function id(?string $valor): int
    {
        if ($valor === null || preg_match('/^[1-9][0-9]{0,17}$/', $valor) !== 1) {
            throw new ApiException(400, 'PARAMETRO_INVALIDO', 'El identificador debe ser un entero positivo.', [
                'id' => ['Debe ser un entero positivo.'],
            ]);
        }
        return (int) $valor;
    }

    /**
     * Parametros de consulta del listado (contrato §4.4).
     *
     * @param array<string, mixed> $q
     * @return array{estado_id: ?int, prioridad_id: ?int, desde: ?string, hasta_exclusivo: ?string, q: ?string, pagina: int, por_pagina: int}
     */
    public function filtros(array $q): array
    {
        $errores = [];
        $leer = static function (string $k) use ($q, &$errores): ?string {
            if (!array_key_exists($k, $q) || $q[$k] === '') {
                return null;
            }
            if (!is_string($q[$k])) {
                $errores[$k][] = 'Debe enviarse una sola vez como texto.';
                return null;
            }
            return trim($q[$k]);
        };

        $estadoId = null;
        if (($estado = $leer('estado')) !== null) {
            $estadoId = Solicitud::ESTADOS[strtoupper($estado)] ?? null;
            if ($estadoId === null) {
                $errores['estado'][] = 'Estado no válido. Valores: ' . implode(', ', array_keys(Solicitud::ESTADOS)) . '.';
            }
        }

        $prioridadId = null;
        if (($p = $leer('prioridad_id')) !== null) {
            if (preg_match('/^[1-4]$/', $p) !== 1) {
                $errores['prioridad_id'][] = 'Debe ser un entero entre 1 y 4.';
            } else {
                $prioridadId = (int) $p;
            }
        }

        $desde = $this->fecha($leer('desde'), 'desde', $errores);
        $hasta = $this->fecha($leer('hasta'), 'hasta', $errores);
        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            $errores['desde'][] = 'La fecha "desde" no puede ser posterior a "hasta".';
        }

        $texto = $leer('q');
        if ($texto !== null && mb_strlen($texto) > 100) {
            $errores['q'][] = 'La búsqueda admite hasta 100 caracteres.';
        }

        $pagina = $this->rango($leer('pagina'), 'pagina', 1, 1_000_000, 1, $errores);
        $porPagina = $this->rango($leer('por_pagina'), 'por_pagina', 1, 50, 20, $errores);

        if ($errores !== []) {
            throw new ApiException(400, 'PARAMETRO_INVALIDO', 'Parámetros de consulta no válidos.', $errores);
        }

        return [
            'estado_id' => $estadoId,
            'prioridad_id' => $prioridadId,
            'desde' => $desde?->format('Y-m-d'),
            'hasta_exclusivo' => $hasta?->modify('+1 day')->format('Y-m-d'),
            'q' => $texto === '' ? null : $texto,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
        ];
    }

    /** @param array<string, list<string>> $errores */
    private function texto(array $input, string $campo, string $etiqueta, string $obligatorio, int $max, array &$errores): ?string
    {
        $valor = $input[$campo] ?? null;
        if (!is_string($valor) || trim($valor) === '') {
            $errores[$campo][] = "{$etiqueta} es {$obligatorio}.";
            return null;
        }
        $valor = trim($valor);
        if (mb_strlen($valor) > $max) {
            $errores[$campo][] = "{$etiqueta} admite hasta {$max} caracteres.";
        }
        return $valor;
    }

    /** @param array<string, list<string>> $errores */
    private function entero(array $input, string $campo, string $etiqueta, string $obligatorio, array &$errores): ?int
    {
        $valor = $input[$campo] ?? null;
        if ($valor === null || $valor === '') {
            $errores[$campo][] = "{$etiqueta} es {$obligatorio}.";
            return null;
        }
        if (is_int($valor) && $valor > 0) {
            return $valor;
        }
        if (is_string($valor) && preg_match('/^[1-9][0-9]{0,8}$/', $valor) === 1) {
            return (int) $valor;
        }
        $errores[$campo][] = "{$etiqueta} debe ser un entero positivo.";
        return null;
    }

    /** @param array<string, list<string>> $errores */
    private function fecha(?string $valor, string $campo, array &$errores): ?\DateTimeImmutable
    {
        if ($valor === null) {
            return null;
        }
        $f = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        if ($f === false || $f->format('Y-m-d') !== $valor) {
            $errores[$campo][] = 'Debe tener el formato AAAA-MM-DD.';
            return null;
        }
        return $f;
    }

    /** @param array<string, list<string>> $errores */
    private function rango(?string $valor, string $campo, int $min, int $max, int $defecto, array &$errores): int
    {
        if ($valor === null) {
            return $defecto;
        }
        if (preg_match('/^[0-9]{1,7}$/', $valor) !== 1 || (int) $valor < $min || (int) $valor > $max) {
            $errores[$campo][] = "Debe ser un entero entre {$min} y {$max}.";
            return $defecto;
        }
        return (int) $valor;
    }
}
