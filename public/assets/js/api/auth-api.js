/**
 * Autenticación: /auth/login, /auth/logout, /auth/me (contrato_solicitudes.md §3).
 * Guarda el csrf_token recibido en memoria (http.js); no toca la interfaz.
 */
import { solicitar, fijarCsrf, borrarCsrf } from './http.js';

export async function iniciarSesion(nombreUsuario, password) {
    const r = await solicitar('POST', '/auth/login', {
        cuerpo: { nombre_usuario: nombreUsuario, password },
        avisarSesion: false,
    });
    if (r.ok) {
        fijarCsrf(r.data.csrf_token);
    }
    return r;
}

/** Sesión actual. Un 401 aquí significa "sin sesión", no "sesión expirada". */
export async function sesionActual() {
    const r = await solicitar('GET', '/auth/me', { avisarSesion: false });
    if (r.ok) {
        fijarCsrf(r.data.csrf_token);
    }
    return r;
}

export async function cerrarSesion() {
    const r = await solicitar('POST', '/auth/logout', { avisarSesion: false });
    // Con 200 o 401 la sesión ya no existe en el servidor.
    if (r.ok || r.status === 401) {
        borrarCsrf();
    }
    return r;
}
