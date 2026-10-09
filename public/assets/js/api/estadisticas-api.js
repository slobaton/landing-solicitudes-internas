/**
 * Cifras públicas: GET /api/v1/estadisticas (diseno_landing.md §8.1).
 */
import { solicitar } from './http.js';

export function obtenerEstadisticas() {
    return solicitar('GET', '/estadisticas', { avisarSesion: false });
}
