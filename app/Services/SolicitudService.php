<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ApiException;
use App\Models\Solicitud;
use App\Repositories\CatalogoRepository;
use App\Repositories\SolicitudRepository;

/**
 * Casos de uso de solicitudes: registrar (RF-02, RF-03), listar mis solicitudes
 * y ver el detalle con historial (RF-09). Contrato §4.3-§4.5.
 */
final class SolicitudService
{
    public function __construct(
        private SolicitudRepository $solicitudes = new SolicitudRepository(),
        private CatalogoRepository $catalogos = new CatalogoRepository(),
        private FechaLimiteCalculator $calculadora = new FechaLimiteCalculator(),
    ) {
    }

    /**
     * Transaccion T-01. La notificacion al jefe (paso 8) queda fuera de alcance.
     *
     * @param array{titulo: string, descripcion: string, area_destino_id: int, tipo_solicitud_id: int, prioridad_id: int} $d
     * @return array<string, mixed> Detalle de la solicitud creada
     */
    public function registrar(int $usuarioId, array $d, string $ip): array
    {
        // 1. Catalogos activos (RN-D12); TRG-01 vuelve a comprobarlo al insertar.
        $errores = [];
        if (!$this->catalogos->areaActiva($d['area_destino_id'])) {
            $errores['area_destino_id'][] = 'El área destino no existe o está inactiva.';
        }
        if (!$this->catalogos->tipoActivo($d['tipo_solicitud_id'])) {
            $errores['tipo_solicitud_id'][] = 'El tipo de solicitud no existe o está inactivo.';
        }
        $horas = $this->catalogos->horasPrioridadActiva($d['prioridad_id']);
        if ($horas === null) {
            $errores['prioridad_id'][] = 'La prioridad no existe o está inactiva.';
        }
        if ($errores !== []) {
            throw ApiException::validacion($errores);
        }

        $horario = $this->catalogos->horarioLaboral();
        $tz = new \DateTimeZone(date_default_timezone_get());

        $id = $this->solicitudes->enTransaccion(function () use ($usuarioId, $d, $ip, $horas, $horario, $tz): int {
            // 2. Hora de la BD: fecha de registro y anio del numero.
            $ts = new \DateTimeImmutable($this->solicitudes->ahora(), $tz);

            // 3-4. Numero AAAA-NNNNN.
            $anio = (int) $ts->format('Y');
            $numero = sprintf('%04d-%05d', $anio, $this->solicitudes->siguienteNumero($anio));

            // 5. Fecha limite en horas laborales.
            $feriados = $this->catalogos->feriados(
                $ts->format('Y-m-d'),
                $ts->modify('+' . FechaLimiteCalculator::MAX_DIAS . ' days')->format('Y-m-d')
            );
            $limite = $this->calculadora->calcular($ts, $horas, $horario, $feriados);

            // 6. Solicitud en estado REGISTRADA.
            $id = $this->solicitudes->insertar([
                'numero' => $numero,
                'titulo' => $d['titulo'],
                'descripcion' => $d['descripcion'],
                'solicitante_id' => $usuarioId,
                'area_destino_id' => $d['area_destino_id'],
                'tipo_solicitud_id' => $d['tipo_solicitud_id'],
                'prioridad_id' => $d['prioridad_id'],
                'fecha_registro' => $ts->format('Y-m-d H:i:s'),
                'fecha_limite' => $limite->format('Y-m-d H:i:s'),
            ]);

            // 7. Historial REGISTRO.
            $this->solicitudes->insertarHistorial(
                solicitudId: $id,
                tipoEvento: 'REGISTRO',
                usuarioId: $usuarioId,
                ip: $ip,
                estadoNuevoId: Solicitud::REGISTRADA,
                prioridadNuevaId: $d['prioridad_id'],
                detalle: ['prioridad_id' => $d['prioridad_id'], 'fecha_limite' => $limite->format('Y-m-d H:i:s')],
            );

            return $id;
        });

        return $this->detalle($usuarioId, $id);
    }

    /**
     * @param array{estado_id: ?int, prioridad_id: ?int, desde: ?string, hasta_exclusivo: ?string, q: ?string, pagina: int, por_pagina: int} $f
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function listar(int $usuarioId, array $f): array
    {
        $resultado = $this->solicitudes->listarDeSolicitante(
            $usuarioId,
            $f,
            $f['por_pagina'],
            ($f['pagina'] - 1) * $f['por_pagina']
        );

        return [
            'data' => array_map(Solicitud::resumen(...), $resultado['filas']),
            'meta' => [
                'pagina' => $f['pagina'],
                'por_pagina' => $f['por_pagina'],
                'total' => $resultado['total'],
                'total_paginas' => (int) ceil($resultado['total'] / $f['por_pagina']),
            ],
        ];
    }

    /**
     * Editar (T-04, RN-D10). Solo el solicitante y solo en REGISTRADA.
     *
     * @param array{titulo: string, descripcion: string, tipo_solicitud_id: int, version: int, area_destino_id: ?int, prioridad_id: ?int} $d
     * @return array<string, mixed> Detalle actualizado
     */
    public function editar(int $usuarioId, int $id, array $d, string $ip): array
    {
        $this->solicitudes->enTransaccion(function () use ($usuarioId, $id, $d, $ip): void {
            $actual = $this->bloquearPropia($usuarioId, $id);

            if ($actual['estado_id'] !== Solicitud::REGISTRADA) {
                throw new ApiException(409, 'ESTADO_NO_PERMITE', 'Solo se puede editar una solicitud en estado REGISTRADA.');
            }
            if ($actual['version'] !== $d['version']) {
                throw $this->conflictoVersion();
            }

            // Campos no editables: si llegan con otro valor, 422 (contrato §4.6).
            $errores = [];
            if ($d['area_destino_id'] !== null && $d['area_destino_id'] !== $actual['area_destino_id']) {
                $errores['area_destino_id'][] = 'El área destino no se puede modificar después del registro.';
            }
            if ($d['prioridad_id'] !== null && $d['prioridad_id'] !== $actual['prioridad_id']) {
                $errores['prioridad_id'][] = 'La prioridad solo la cambia el jefe del área destino.';
            }
            if ($d['tipo_solicitud_id'] !== $actual['tipo_solicitud_id'] && !$this->catalogos->tipoActivo($d['tipo_solicitud_id'])) {
                $errores['tipo_solicitud_id'][] = 'El tipo de solicitud no existe o está inactivo.';
            }
            if ($errores !== []) {
                throw ApiException::validacion($errores);
            }

            // Solo los campos que cambian (comparacion binaria, como TRG-02).
            $cambios = [];
            foreach (['titulo', 'descripcion', 'tipo_solicitud_id'] as $campo) {
                if ($d[$campo] !== $actual[$campo]) {
                    $cambios[$campo] = ['antes' => $actual[$campo], 'despues' => $d[$campo]];
                }
            }
            if ($cambios === []) {
                return; // Sin cambios: no hay UPDATE ni historial (la transaccion se confirma vacia).
            }

            $filas = $this->solicitudes->actualizarEdicion($id, $d['version'], $d['titulo'], $d['descripcion'], $d['tipo_solicitud_id']);
            if ($filas === 0) {
                throw $this->conflictoVersion();
            }

            $this->solicitudes->insertarHistorial(
                solicitudId: $id,
                tipoEvento: 'EDICION',
                usuarioId: $usuarioId,
                ip: $ip,
                detalle: $cambios,
            );
        });

        return $this->detalle($usuarioId, $id);
    }

    /**
     * Cancelar (T-03 con T03/T06, RF-14, RN-D04). UPDATE a CANCELADA, nunca DELETE.
     *
     * @param array{motivo: string, version: int} $d
     * @return array<string, mixed> Detalle actualizado
     */
    public function cancelar(int $usuarioId, int $id, array $d, string $ip): array
    {
        $this->solicitudes->enTransaccion(function () use ($usuarioId, $id, $d, $ip): void {
            $actual = $this->bloquearPropia($usuarioId, $id);

            if ($actual['version'] !== $d['version']) {
                throw $this->conflictoVersion();
            }

            $transicion = $this->solicitudes->transicionActiva($actual['estado_id'], Solicitud::CANCELADA, Solicitud::ROL_SOLICITANTE);
            if ($transicion === null) {
                throw new ApiException(409, 'ESTADO_NO_PERMITE', 'Solo se puede cancelar una solicitud en estado REGISTRADA o ASIGNADA.');
            }

            if ($this->solicitudes->cancelar($id, $d['version']) === 0) {
                throw $this->conflictoVersion();
            }

            $this->solicitudes->insertarHistorial(
                solicitudId: $id,
                tipoEvento: 'CAMBIO_ESTADO',
                usuarioId: $usuarioId,
                ip: $ip,
                transicionId: $transicion['id'],
                estadoAnteriorId: $actual['estado_id'],
                estadoNuevoId: Solicitud::CANCELADA,
                motivo: $d['motivo'],
            );
        });

        return $this->detalle($usuarioId, $id);
    }

    /**
     * Bloquea la solicitud y comprueba existencia (404) y propiedad (403).
     *
     * @return array<string, mixed>
     */
    private function bloquearPropia(int $usuarioId, int $id): array
    {
        $actual = $this->solicitudes->bloquearParaActualizar($id);
        if ($actual === null) {
            throw new ApiException(404, 'NO_ENCONTRADO', 'La solicitud no existe.');
        }
        if ($actual['solicitante_id'] !== $usuarioId) {
            throw new ApiException(403, 'SIN_PERMISO', 'No tiene permiso para modificar esta solicitud.');
        }
        return $actual;
    }

    private function conflictoVersion(): ApiException
    {
        return new ApiException(409, 'CONFLICTO_VERSION', 'La solicitud fue modificada por otro usuario. Recárguela e intente de nuevo.');
    }

    /** @return array<string, mixed> */
    public function detalle(int $usuarioId, int $id): array
    {
        $fila = $this->solicitudes->findById($id);
        if ($fila === null) {
            throw new ApiException(404, 'NO_ENCONTRADO', 'La solicitud no existe.');
        }
        if ((int) $fila['solicitante_id'] !== $usuarioId) {
            throw new ApiException(403, 'SIN_PERMISO', 'No tiene permiso para ver esta solicitud.');
        }

        return Solicitud::detalle($fila, $this->solicitudes->historial($id));
    }
}
