/**
 * Solicitudes (contrato_solicitudes.md §4): registrar, listar, detalle y cancelar.
 * La edición (PUT) existe en la API pero no se usa en la landing (diseno_landing.md §3).
 */
import { solicitar } from './http.js';

/**
 * POST /api/v1/solicitudes (requiere sesión; http.js agrega X-CSRF-Token).
 * @param {{titulo: string, descripcion: string, area_destino_id: number, tipo_solicitud_id: number, prioridad_id: number}} datos
 */
export function registrarSolicitud(datos) {
    return solicitar('POST', '/solicitudes', { cuerpo: datos });
}

/** GET /api/v1/solicitudes?pagina=&por_pagina= — solo las del usuario de la sesión. */
export function listarMisSolicitudes(pagina = 1, porPagina = 10) {
    const q = new URLSearchParams({ pagina: String(pagina), por_pagina: String(porPagina) });
    return solicitar('GET', `/solicitudes?${q.toString()}`);
}

/** GET /api/v1/solicitudes/{id} — detalle con historial. */
export function obtenerSolicitud(id) {
    return solicitar('GET', `/solicitudes/${encodeURIComponent(String(id))}`);
}

/** DELETE /api/v1/solicitudes/{id} — CANCELA con motivo (no borra). */
export function cancelarSolicitud(id, motivo, version) {
    return solicitar('DELETE', `/solicitudes/${encodeURIComponent(String(id))}`, { cuerpo: { motivo, version } });
}
