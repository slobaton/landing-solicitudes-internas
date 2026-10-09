<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\SolicitudService;
use App\Validators\SolicitudValidator;

/**
 * POST /solicitudes, GET /solicitudes, GET|PUT|DELETE /solicitudes/{id} (contrato §4.3-§4.7).
 */
final class SolicitudController
{
    private SolicitudService $servicio;
    private SolicitudValidator $validador;

    public function __construct()
    {
        $this->servicio = new SolicitudService();
        $this->validador = new SolicitudValidator();
    }

    public function store(Request $request): Response
    {
        $datos = $this->validador->registrar($request->json());
        $detalle = $this->servicio->registrar((int) Session::usuarioId(), $datos, $request->ip());

        return Response::ok($detalle, 201, null, ['Location' => '/api/v1/solicitudes/' . $detalle['id']]);
    }

    public function index(Request $request): Response
    {
        $filtros = $this->validador->filtros($request->queryAll());
        $resultado = $this->servicio->listar((int) Session::usuarioId(), $filtros);

        return Response::ok($resultado['data'], 200, $resultado['meta']);
    }

    public function update(Request $request): Response
    {
        $id = $this->validador->id($request->param('id'));
        $datos = $this->validador->editar($request->json());

        return Response::ok($this->servicio->editar((int) Session::usuarioId(), $id, $datos, $request->ip()));
    }

    /** DELETE = cancelar con motivo (no borra la fila). */
    public function destroy(Request $request): Response
    {
        $id = $this->validador->id($request->param('id'));
        $datos = $this->validador->cancelar($request->json());

        return Response::ok($this->servicio->cancelar((int) Session::usuarioId(), $id, $datos, $request->ip()));
    }

    public function show(Request $request): Response
    {
        $id = $this->validador->id($request->param('id'));

        return Response::ok($this->servicio->detalle((int) Session::usuarioId(), $id));
    }
}
