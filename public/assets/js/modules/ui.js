/**
 * Utilidades de interfaz compartidas por los módulos.
 * Todo texto se inserta con textContent (nunca innerHTML con datos de la API).
 */

/** Muestra un texto en un elemento de mensaje; con texto vacío lo oculta. */
export function mostrarMensaje(elemento, texto) {
    if (!elemento) {
        return;
    }
    elemento.textContent = texto ?? '';
    elemento.hidden = !texto;
}

/** Marca una región como ocupada (aria-busy) y deshabilita/habilita sus botones. */
export function marcarOcupado(region, ocupado, botones = []) {
    if (region) {
        region.setAttribute('aria-busy', ocupado ? 'true' : 'false');
    }
    for (const boton of botones) {
        if (boton) {
            boton.disabled = ocupado;
        }
    }
}

/** Formatea 'YYYY-MM-DD HH:MM:SS' como 'HH:MM' (hora del servidor, sin conversión de zona). */
export function horaCorta(fechaServidor) {
    const coincide = /^\d{4}-\d{2}-\d{2} (\d{2}):(\d{2})/.exec(String(fechaServidor ?? ''));
    return coincide ? `${coincide[1]}:${coincide[2]}` : '';
}

/** Número entero con separador de miles local. */
export function formatearEntero(valor) {
    const n = Number(valor);
    return Number.isFinite(n) ? n.toLocaleString('es') : '—';
}

/** Formatea 'YYYY-MM-DD HH:MM:SS' como 'DD/MM/YYYY HH:MM' (hora del servidor). */
export function fechaHora(fechaServidor) {
    const m = /^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})/.exec(String(fechaServidor ?? ''));
    return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : String(fechaServidor ?? '');
}

/** Reemplaza las opciones de un <select> (texto con textContent). */
export function llenarSelect(select, opciones, textoVacio) {
    const vacia = document.createElement('option');
    vacia.value = '';
    vacia.textContent = textoVacio;
    const nuevas = [vacia];
    for (const { valor, texto } of opciones) {
        const opcion = document.createElement('option');
        opcion.value = String(valor);
        opcion.textContent = texto;
        nuevas.push(opcion);
    }
    select.replaceChildren(...nuevas);
}
