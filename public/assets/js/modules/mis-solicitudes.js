/**
 * Sección "Mis solicitudes" (RF-09, RF-14): lista paginada, detalle con historial
 * y cancelación con motivo (DELETE = estado CANCELADA, nunca borrado).
 * Estados: unauthorized · loading · empty · success · error (incluye 403/404/409/422/red).
 * Escucha 'sesion:cambio' y 'solicitud:registrada'; emite 'solicitud:cancelada'.
 */
import { listarMisSolicitudes, obtenerSolicitud, cancelarSolicitud } from '../api/solicitudes-api.js';
import { sesionActual } from '../api/auth-api.js';
import { mostrarMensaje, marcarOcupado, fechaHora } from './ui.js';

const POR_PAGINA = 10;
const MOTIVO_MAX = 500;
const EVENTOS = {
    REGISTRO: 'Registro de la solicitud',
    EDICION: 'Edición de datos',
    ASIGNACION: 'Asignación de responsable',
    CAMBIO_ESTADO: 'Cambio de estado',
    CAMBIO_PRIORIDAD: 'Cambio de prioridad',
};

const persona = (p) => (p ? `${p.nombres ?? ''} ${p.apellidos ?? ''}`.trim() : '');

function crear(etiqueta, texto, clase) {
    const el = document.createElement(etiqueta);
    if (clase) el.className = clase;
    if (texto !== undefined && texto !== null) el.textContent = String(texto);
    return el;
}

function insigniaEstado(estado) {
    const codigo = String(estado?.codigo ?? '').toLowerCase().replace(/[^a-z_]/g, '');
    return crear('span', estado?.nombre ?? '', `insignia insignia--${codigo}`);
}

export function iniciarMisSolicitudes(raiz = document.querySelector('[data-modulo="mis-solicitudes"]')) {
    if (!raiz) {
        return;
    }

    const q = (s) => raiz.querySelector(s);
    const avisoSinSesion = q('[data-mis-sin-sesion]');
    const panel = q('[data-mis-panel]');
    const estado = q('[data-estado-mensaje]');
    const error = q('[data-error-mensaje]');
    const vacio = q('[data-mis-vacio]');
    const tabla = q('[data-mis-tabla]');
    const filas = q('[data-mis-filas]');
    const paginacion = q('[data-mis-paginacion]');
    const textoPagina = q('[data-paginacion-texto]');
    const botonAnterior = q('[data-accion="anterior"]');
    const botonSiguiente = q('[data-accion="siguiente"]');
    const botonRecargar = q('[data-accion="recargar"]');

    const detalle = q('[data-detalle]');
    const detalleTitulo = q('[data-detalle-titulo]');
    const detalleEstado = q('[data-detalle-estado]');
    const detalleError = q('[data-detalle-error]');
    const detalleContenido = q('[data-detalle-contenido]');
    const historial = q('[data-detalle-historial]');
    const zonaCancelar = q('[data-cancelar]');
    const botonMostrarCancelar = q('[data-accion="mostrar-cancelar"]');
    const formCancelar = q('[data-formulario="cancelar"]');
    const motivo = formCancelar.elements.namedItem('motivo');
    const errorMotivo = q('[data-error-campo="motivo"]');
    const botonConfirmar = formCancelar.querySelector('button[type="submit"]');

    let sesionActiva = false;
    let pagina = 1;
    let totalPaginas = 1;
    let actual = null; // detalle abierto (id, version, acciones)
    let botonOrigen = null; // para devolver el foco al cerrar el detalle

    // ---------------------------------------------------------------- lista
    async function cargarLista(nuevaPagina = pagina) {
        if (!sesionActiva) return;
        marcarOcupado(raiz, true, [botonAnterior, botonSiguiente, botonRecargar]);
        mostrarMensaje(error, '');
        mostrarMensaje(estado, 'Cargando sus solicitudes…');

        const r = await listarMisSolicitudes(nuevaPagina, POR_PAGINA);
        marcarOcupado(raiz, false, [botonRecargar]);

        if (!r.ok) {
            if (r.status === 401) return; // 'sesion:expirada' ya cambió la vista
            mostrarMensaje(estado, '');
            mostrarMensaje(error, r.error.codigo === 'RED'
                ? r.error.mensaje
                : 'No se pudieron cargar sus solicitudes. Use "Actualizar lista" para reintentar.');
            return;
        }

        const meta = r.meta ?? { pagina: nuevaPagina, total: r.data.length, total_paginas: 1 };
        // Si la página pedida quedó vacía (p. ej. tras cambios), volver a la última con datos.
        if (r.data.length === 0 && meta.total > 0 && nuevaPagina > 1) {
            await cargarLista(Math.max(1, meta.total_paginas));
            return;
        }
        pagina = meta.pagina;
        totalPaginas = Math.max(1, meta.total_paginas);
        pintarFilas(r.data);

        if (meta.total === 0) {
            // empty
            tabla.hidden = true;
            paginacion.hidden = true;
            vacio.hidden = false;
            mostrarMensaje(estado, '');
            return;
        }
        vacio.hidden = true;
        tabla.hidden = false;
        paginacion.hidden = totalPaginas <= 1;
        textoPagina.textContent = `Página ${pagina} de ${totalPaginas}`;
        botonAnterior.disabled = pagina <= 1;
        botonSiguiente.disabled = pagina >= totalPaginas;
        mostrarMensaje(estado, meta.total === 1 ? '1 solicitud.' : `${meta.total} solicitudes.`);
    }

    function pintarFilas(lista) {
        const nuevas = lista.map((s) => {
            const tr = document.createElement('tr');
            tr.setAttribute('role', 'row');
            tr.dataset.id = String(s.id);
            tr.classList.toggle('fila--activa', Boolean(actual && actual.id === s.id));
            const celda = (etiqueta, contenido, clase) => {
                const td = crear('td', null, clase);
                td.setAttribute('role', 'cell');
                td.dataset.etiqueta = etiqueta;
                if (contenido instanceof Node) td.append(contenido); else td.textContent = String(contenido ?? '');
                return td;
            };
            const limite = crear('span', fechaHora(s.fecha_limite));
            const celdaLimite = celda('Fecha límite', limite, 'numero');
            if (s.vencida) {
                celdaLimite.append(' ', crear('span', 'Vencida', 'insignia insignia--vencida'));
            }
            const ver = crear('button', 'Ver detalle', 'boton boton--secundario boton--pequeno');
            ver.type = 'button';
            ver.setAttribute('aria-label', `Ver detalle de la solicitud ${s.numero}`);
            ver.addEventListener('click', () => abrirDetalle(s.id, ver));

            tr.append(
                celda('Número', s.numero, 'numero celda--numero'),
                celda('Título', s.titulo, 'celda--titulo'),
                celda('Estado', insigniaEstado(s.estado)),
                celda('Prioridad', s.prioridad?.nombre ?? ''),
                celda('Registro', fechaHora(s.fecha_registro), 'numero'),
                celdaLimite,
                celda('Acciones', ver, 'celda--acciones'),
            );
            return tr;
        });
        filas.replaceChildren(...nuevas);
    }

    // -------------------------------------------------------------- detalle
    async function abrirDetalle(id, origen = null, mensaje = '') {
        botonOrigen = origen ?? botonOrigen;
        detalle.hidden = false;
        detalleContenido.hidden = true;
        ocultarCancelar();
        mostrarMensaje(detalleError, '');
        detalleTitulo.textContent = 'Cargando detalle…';
        mostrarMensaje(detalleEstado, 'Cargando detalle…');
        marcarOcupado(detalle, true);

        const r = await obtenerSolicitud(id);
        marcarOcupado(detalle, false);

        if (!r.ok) {
            if (r.status === 401) return;
            actual = null;
            detalleTitulo.textContent = 'Detalle no disponible';
            mostrarMensaje(detalleEstado, '');
            const textos = {
                SIN_PERMISO: 'Esta solicitud no le pertenece.',
                NO_ENCONTRADO: 'La solicitud no existe.',
                RED: r.error.mensaje,
            };
            mostrarMensaje(detalleError, textos[r.error.codigo] ?? 'No se pudo cargar el detalle. Intente nuevamente.');
            detalleTitulo.focus();
            return;
        }

        const s = r.data;
        actual = { id: s.id, version: s.version, numero: s.numero, puedeCancelar: Boolean(s.acciones?.puede_cancelar) };
        detalleTitulo.textContent = `${s.numero} · ${s.titulo}`;
        mostrarMensaje(detalleEstado, mensaje);
        const set = (campo, valor) => { q(`[data-campo="${campo}"]`).textContent = String(valor ?? ''); };
        q('[data-campo="estado"]').replaceChildren(insigniaEstado(s.estado));
        set('area', s.area_destino?.nombre);
        set('tipo', s.tipo_solicitud?.nombre);
        set('prioridad', s.prioridad?.nombre);
        set('registro', fechaHora(s.fecha_registro));
        set('limite', fechaHora(s.fecha_limite) + (s.vencida ? ' (vencida)' : ''));
        set('responsable', persona(s.responsable) || 'Sin asignar');
        set('cierre', s.fecha_cierre ? fechaHora(s.fecha_cierre) : '—');
        set('descripcion', s.descripcion);
        pintarHistorial(Array.isArray(s.historial) ? s.historial : []);

        zonaCancelar.hidden = !actual.puedeCancelar;
        detalleContenido.hidden = false;
        filas.querySelectorAll('tr').forEach((tr) => tr.classList.toggle('fila--activa', tr.dataset.id === String(s.id)));
        detalleTitulo.focus();
    }

    function pintarHistorial(eventos) {
        const items = eventos.map((ev) => {
            const li = crear('li', null, 'historial__evento');
            const cabecera = crear('p', null, 'historial__cabecera');
            cabecera.append(crear('strong', EVENTOS[ev.tipo_evento] ?? ev.tipo_evento), ' · ', crear('span', fechaHora(ev.fecha), 'numero'));
            li.append(cabecera);
            if (ev.tipo_evento === 'CAMBIO_ESTADO' && ev.estado_nuevo) {
                li.append(crear('p', `${ev.estado_anterior?.nombre ?? '—'} → ${ev.estado_nuevo.nombre}`));
            } else if (ev.tipo_evento === 'REGISTRO' && ev.estado_nuevo) {
                li.append(crear('p', `Estado inicial: ${ev.estado_nuevo.nombre}`));
            }
            if (ev.motivo) {
                li.append(crear('p', `Motivo: ${ev.motivo}`, 'historial__motivo'));
            }
            const autor = persona(ev.usuario);
            if (autor) {
                li.append(crear('p', `Por: ${autor}`, 'historial__autor'));
            }
            return li;
        });
        if (items.length === 0) {
            items.push(crear('li', 'Sin eventos registrados.', 'historial__evento'));
        }
        historial.replaceChildren(...items);
    }

    function cerrarDetalle() {
        detalle.hidden = true;
        actual = null;
        filas.querySelectorAll('tr.fila--activa').forEach((tr) => tr.classList.remove('fila--activa'));
        ocultarCancelar();
        if (botonOrigen && botonOrigen.isConnected) botonOrigen.focus();
    }

    // ------------------------------------------------------------- cancelar
    function errorDeMotivo(texto) {
        mostrarMensaje(errorMotivo, texto);
        if (texto) motivo.setAttribute('aria-invalid', 'true'); else motivo.removeAttribute('aria-invalid');
    }

    function mostrarCancelar() {
        formCancelar.hidden = false;
        botonMostrarCancelar.hidden = true;
        botonMostrarCancelar.setAttribute('aria-expanded', 'true');
        motivo.focus();
    }

    function ocultarCancelar() {
        formCancelar.reset();
        formCancelar.hidden = true;
        errorDeMotivo('');
        botonMostrarCancelar.hidden = false;
        botonMostrarCancelar.setAttribute('aria-expanded', 'false');
    }

    formCancelar.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        if (!actual) return;
        errorDeMotivo('');
        mostrarMensaje(detalleError, '');

        const texto = motivo.value.trim();
        if (texto === '') {
            errorDeMotivo('Indique el motivo de la cancelación.');
            motivo.focus();
            return;
        }
        if (texto.length > MOTIVO_MAX) {
            errorDeMotivo(`El motivo admite hasta ${MOTIVO_MAX} caracteres.`);
            motivo.focus();
            return;
        }

        const { id } = actual;
        marcarOcupado(detalle, true, [botonConfirmar]);
        botonConfirmar.textContent = 'Cancelando…';
        const r = await cancelarSolicitud(id, texto, actual.version);
        marcarOcupado(detalle, false, [botonConfirmar]);
        botonConfirmar.textContent = 'Confirmar cancelación';

        if (r.ok) {
            document.dispatchEvent(new CustomEvent('solicitud:cancelada', { detail: { solicitud: r.data } }));
            await cargarLista(pagina);
            await abrirDetalle(id, null, `Solicitud ${r.data.numero} cancelada. Quedó registrada en el historial con su motivo.`);
            return;
        }

        switch (r.error.codigo) {
            case 'VALIDACION': {
                const m = r.error.detalles?.motivo;
                errorDeMotivo(m ? (Array.isArray(m) ? m.join(' ') : String(m)) : 'Revise el motivo.');
                motivo.focus();
                break;
            }
            case 'CONFLICTO_VERSION':
            case 'ESTADO_NO_PERMITE':
                // La solicitud cambió en otro lugar: se relee y se informa.
                await cargarLista(pagina);
                await abrirDetalle(id, null, '');
                mostrarMensaje(detalleError, r.error.codigo === 'CONFLICTO_VERSION'
                    ? 'La solicitud fue modificada mientras la veía. Se recargó el detalle; revise y vuelva a intentar.'
                    : 'El estado actual de la solicitud ya no permite cancelarla. Se recargó el detalle.');
                detalleError.focus();
                break;
            case 'CSRF_INVALIDO': {
                const me = await sesionActual();
                mostrarMensaje(detalleError, me.ok
                    ? 'Su sesión se actualizó. Pulse "Confirmar cancelación" otra vez.'
                    : 'No se pudo validar su sesión. Inicie sesión nuevamente.');
                detalleError.focus();
                break;
            }
            case 'NO_AUTENTICADO':
                break;
            default:
                mostrarMensaje(detalleError, r.error.codigo === 'RED'
                    ? r.error.mensaje
                    : 'No se pudo cancelar la solicitud. Intente nuevamente.');
                detalleError.focus();
        }
    });

    // --------------------------------------------------------------- eventos
    botonAnterior.addEventListener('click', () => { if (pagina > 1) cargarLista(pagina - 1); });
    botonSiguiente.addEventListener('click', () => { if (pagina < totalPaginas) cargarLista(pagina + 1); });
    botonRecargar.addEventListener('click', () => cargarLista(pagina));
    q('[data-accion="cerrar-detalle"]').addEventListener('click', cerrarDetalle);
    botonMostrarCancelar.addEventListener('click', mostrarCancelar);
    q('[data-accion="ocultar-cancelar"]').addEventListener('click', () => {
        ocultarCancelar();
        botonMostrarCancelar.focus();
    });
    detalle.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !formCancelar.hidden) {
            ocultarCancelar();
            botonMostrarCancelar.focus();
        }
    });

    document.addEventListener('solicitud:registrada', () => cargarLista(1));

    document.addEventListener('sesion:cambio', (evento) => {
        const usuario = evento.detail?.usuario ?? null;
        if (!usuario) {
            // unauthorized: se oculta todo dato de la sesión anterior
            sesionActiva = false;
            actual = null;
            panel.hidden = true;
            avisoSinSesion.hidden = false;
            filas.replaceChildren();
            detalle.hidden = true;
            return;
        }
        const yaActiva = sesionActiva;
        sesionActiva = true;
        avisoSinSesion.hidden = true;
        panel.hidden = false;
        if (!yaActiva) {
            pagina = 1;
            detalle.hidden = true;
            cargarLista(1);
        }
    });
}
