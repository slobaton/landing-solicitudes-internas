/**
 * Sección "Cifras en vivo": estados loading / empty / success / error.
 * Endpoint público: no aplica el estado unauthorized.
 */
import { obtenerEstadisticas } from '../api/estadisticas-api.js';
import { mostrarMensaje, marcarOcupado, horaCorta, formatearEntero } from './ui.js';

const CLAVES = ['total', 'registradas', 'en_proceso', 'atendidas_cerradas'];

export function iniciarEstadisticas(raiz = document.querySelector('[data-modulo="estadisticas"]')) {
    if (!raiz) {
        return { cargar: async () => {} };
    }

    const estado = raiz.querySelector('[data-estado-mensaje]');
    const error = raiz.querySelector('[data-error-mensaje]');
    const botonActualizar = raiz.querySelector('[data-accion="actualizar"]');
    const valores = Object.fromEntries(
        CLAVES.map((clave) => [clave, raiz.querySelector(`[data-cifra="${clave}"]`)])
    );

    const pintar = (datos) => {
        for (const clave of CLAVES) {
            if (valores[clave]) {
                valores[clave].textContent = datos ? formatearEntero(datos[clave]) : '—';
            }
        }
    };

    async function cargar() {
        // loading
        marcarOcupado(raiz, true, [botonActualizar]);
        mostrarMensaje(error, '');
        mostrarMensaje(estado, 'Cargando cifras…');

        const r = await obtenerEstadisticas();
        marcarOcupado(raiz, false, [botonActualizar]);

        if (!r.ok) {
            // error: las tarjetas quedan con "—" y se ofrece reintentar
            pintar(null);
            mostrarMensaje(estado, '');
            mostrarMensaje(error, 'No se pudieron cargar las cifras. Use "Actualizar" para reintentar.');
            if (botonActualizar) {
                botonActualizar.textContent = 'Reintentar';
            }
            return;
        }

        pintar(r.data);
        if (botonActualizar) {
            botonActualizar.textContent = 'Actualizar';
        }
        const hora = horaCorta(r.data.generado_en);
        if (Number(r.data.total) === 0) {
            // empty
            mostrarMensaje(estado, `Aún no hay solicitudes registradas.${hora ? ` Actualizado: ${hora}.` : ''}`);
        } else {
            // success
            mostrarMensaje(estado, hora ? `Actualizado: ${hora}.` : 'Cifras actualizadas.');
        }
    }

    botonActualizar?.addEventListener('click', () => {
        cargar();
    });

    return { cargar };
}
