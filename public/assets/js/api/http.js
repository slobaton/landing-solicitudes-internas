/**
 * Comunicación HTTP común con la API (estructura_frontend.md: js/api/).
 * - Solo rutas relativas del mismo origen (/api/v1/...), cookie de sesión con credentials: 'same-origin'.
 * - El token CSRF vive SOLO en esta variable de módulo (nunca localStorage/sessionStorage).
 * - Normaliza toda respuesta a { ok, status, data, meta, error } y nunca lanza excepciones.
 * - Si una llamada protegida devuelve 401 NO_AUTENTICADO emite el evento 'sesion:expirada'.
 */
const BASE = '/api/v1';
const METODOS_CON_CSRF = new Set(['POST', 'PUT', 'DELETE']);

let csrfToken = null;

export function fijarCsrf(token) {
    csrfToken = typeof token === 'string' && token !== '' ? token : null;
}

export function borrarCsrf() {
    csrfToken = null;
}

function errorLocal(codigo, mensaje, status = 0) {
    return { ok: false, status, data: null, meta: null, error: { codigo, mensaje, detalles: null } };
}

/**
 * @param {string} metodo  GET | POST | PUT | DELETE
 * @param {string} ruta    ruta bajo /api/v1, p. ej. '/auth/me'
 * @param {{ cuerpo?: object, avisarSesion?: boolean }} [opciones]
 */
export async function solicitar(metodo, ruta, opciones = {}) {
    const { cuerpo, avisarSesion = true } = opciones;
    const cabeceras = { Accept: 'application/json' };
    const init = { method: metodo, headers: cabeceras, credentials: 'same-origin', cache: 'no-store' };

    if (cuerpo !== undefined) {
        cabeceras['Content-Type'] = 'application/json';
        init.body = JSON.stringify(cuerpo);
    }
    if (METODOS_CON_CSRF.has(metodo) && csrfToken !== null) {
        cabeceras['X-CSRF-Token'] = csrfToken;
    }

    let respuesta;
    try {
        respuesta = await fetch(BASE + ruta, init);
    } catch {
        return errorLocal('RED', 'No se pudo conectar con el servidor. Revise su conexión e intente nuevamente.');
    }

    let json = null;
    try {
        json = await respuesta.json();
    } catch {
        return errorLocal('RESPUESTA_INVALIDA', 'El servidor devolvió una respuesta inesperada.', respuesta.status);
    }

    if (json && json.ok === true) {
        return { ok: true, status: respuesta.status, data: json.data, meta: json.meta ?? null, error: null };
    }

    const error = json && json.error && typeof json.error === 'object'
        ? { codigo: String(json.error.codigo ?? 'ERROR'), mensaje: String(json.error.mensaje ?? 'Error.'), detalles: json.error.detalles ?? null }
        : { codigo: 'ERROR', mensaje: 'Ocurrió un error. Intente nuevamente.', detalles: null };

    if (respuesta.status === 401 && error.codigo === 'NO_AUTENTICADO') {
        borrarCsrf();
        if (avisarSesion) {
            document.dispatchEvent(new CustomEvent('sesion:expirada'));
        }
    }

    return { ok: false, status: respuesta.status, data: null, meta: null, error };
}
