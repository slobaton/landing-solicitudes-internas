<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * Landing page "/" (CP-FRONT-02). Solo elige la vista y prepara datos mínimos
 * de presentación; sin SQL ni reglas de negocio (estructura_frontend.md).
 * Los datos de la BD llegan al navegador únicamente por fetch a /api/v1.
 */
final class LandingController
{
    public function index(Request $request): Response
    {
        // Secciones en orden de la página (diseno_landing.md §3). 'nav' = etiqueta en el menú o null.
        // Menú = diseno_landing.md §3 (Cómo funciona, Cifras, Registrar, Mis solicitudes).
        // Acceso no va en el menú: tiene su propio botón en el encabezado.
        $secciones = [
            ['id' => 'inicio', 'vista' => 'landing/secciones/inicio', 'nav' => null],
            ['id' => 'problema', 'vista' => 'landing/secciones/problema', 'nav' => null],
            ['id' => 'como-funciona', 'vista' => 'landing/secciones/como-funciona', 'nav' => 'Cómo funciona'],
            ['id' => 'beneficios', 'vista' => 'landing/secciones/beneficios', 'nav' => null],
            ['id' => 'cifras', 'vista' => 'landing/secciones/cifras', 'nav' => 'Cifras'],
            ['id' => 'acceso', 'vista' => 'landing/secciones/acceso', 'nav' => null],
            ['id' => 'registrar', 'vista' => 'landing/secciones/registrar', 'nav' => 'Registrar'],
            ['id' => 'mis-solicitudes', 'vista' => 'landing/secciones/mis-solicitudes', 'nav' => 'Mis solicitudes'],
        ];

        $html = View::pagina('landing/index', [
            'titulo' => 'Sistema de seguimiento de solicitudes internas',
            'descripcion' => 'Registre sus solicitudes internas a cualquier área, reciba un número y siga su estado y fecha límite de atención.',
            'secciones' => $secciones,
            'idsSecciones' => array_column($secciones, 'id'),
            'anio' => date('Y'),
        ]);

        return Response::html($html);
    }
}
