/**
 * Catálogos activos (con sesión): GET /api/v1/catalogos (diseno_landing.md §8.2).
 */
import { solicitar } from './http.js';

export function obtenerCatalogos() {
    return solicitar('GET', '/catalogos');
}
