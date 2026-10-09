/**
 * Sección "Registrar solicitud" (RF-02, RF-03).
 * Estados: unauthorized (sin sesión) · loading (catálogos / envío) · empty (catálogo vacío)
 *          · success (número y fecha límite) · error (422 por campo, CSRF, red, 500).
 * La validación es solo de ayuda; el backend valida de nuevo (SolicitudValidator, RN-D12).
 * Escucha 'sesion:cambio' (acceso.js) y emite 'solicitud:registrada' con el detalle creado.
 */
import { obtenerCatalogos } from '../api/catalogos-api.js';
import { registrarSolicitud } from '../api/solicitudes-api.js';
import { sesionActual } from '../api/auth-api.js';
import { mostrarMensaje, marcarOcupado, llenarSelect, fechaHora } from './ui.js';

const LIMITES = { titulo: 150, descripcion: 5000 };
const CAMPOS = ['titulo', 'descripcion', 'area_destino_id', 'tipo_solicitud_id', 'prioridad_id'];

export function iniciarRegistro(raiz = document.querySelector('[data-modulo="registro"]')) {
    if (!raiz) {
        return;
    }

    const avisoSinSesion = raiz.querySelector('[data-registro-sin-sesion]');
    const panel = raiz.querySelector('[data-registro-panel]');
    const estado = raiz.querySelector('[data-estado-mensaje]');
    const exito = raiz.querySelector('[data-exito-mensaje]');
    const error = raiz.querySelector('[data-error-mensaje]');
    const botonReintentar = raiz.querySelector('[data-accion="reintentar-catalogos"]');
    const formulario = raiz.querySelector('[data-formulario="registro"]');
    const botonEnviar = formulario.querySelector('button[type="submit"]');
    const contador = raiz.querySelector('[data-contador="descripcion"]');
    const ayudaTipo = raiz.querySelector('[data-tipo-descripcion]');
    const selects = {
        areas: formulario.elements.namedItem('area_destino_id'),
        tipos_solicitud: formulario.elements.namedItem('tipo_solicitud_id'),
        prioridades: formulario.elements.namedItem('prioridad_id'),
    };

    let usuarioActual = null;
    let catalogosListos = false;
    let tipos = [];

    // ---- utilidades de errores por campo ---------------------------------------------
    function errorCampo(nombre, texto) {
        const control = formulario.elements.namedItem(nombre);
        mostrarMensaje(formulario.querySelector(`[data-error-campo="${nombre}"]`), texto);
        if (control) {
            if (texto) {
                control.setAttribute('aria-invalid', 'true');
            } else {
                control.removeAttribute('aria-invalid');
            }
        }
    }

    function limpiarMensajes() {
        mostrarMensaje(error, '');
        exito.hidden = true;
        exito.replaceChildren();
        CAMPOS.forEach((c) => errorCampo(c, ''));
    }

    function enfocarPrimerError() {
        const invalido = formulario.querySelector('[aria-invalid="true"]');
        (invalido ?? error).focus();
    }

    function habilitarFormulario(habilitado) {
        for (const control of formulario.elements) {
            control.disabled = !habilitado;
        }
    }

    // ---- unauthorized ------------------------------------------------------------------
    function mostrarSinSesion() {
        usuarioActual = null;
        catalogosListos = false;
        panel.hidden = true;
        avisoSinSesion.hidden = false;
    }

    // ---- loading / empty / error de catálogos -------------------------------------------
    async function cargarCatalogos() {
        catalogosListos = false;
        botonReintentar.hidden = true;
        mostrarMensaje(error, '');
        habilitarFormulario(false);
        Object.values(selects).forEach((s) => llenarSelect(s, [], 'Cargando…'));
        marcarOcupado(raiz, true);
        mostrarMensaje(estado, 'Cargando áreas, tipos y prioridades…');

        const r = await obtenerCatalogos();
        marcarOcupado(raiz, false);

        if (!r.ok) {
            if (r.status === 401) {
                return; // http.js ya avisó 'sesion:expirada'; acceso.js cambia la vista
            }
            mostrarMensaje(estado, '');
            mostrarMensaje(error, 'No se pudieron cargar las opciones del formulario.');
            botonReintentar.hidden = false;
            return;
        }

        const { areas = [], tipos_solicitud: tiposSolicitud = [], prioridades = [] } = r.data;
        tipos = tiposSolicitud;
        llenarSelect(selects.areas, areas.map((a) => ({ valor: a.id, texto: a.nombre })), 'Seleccione un área');
        llenarSelect(selects.tipos_solicitud, tiposSolicitud.map((t) => ({ valor: t.id, texto: t.nombre })), 'Seleccione un tipo');
        llenarSelect(
            selects.prioridades,
            prioridades.map((p) => ({ valor: p.id, texto: `${p.nombre} — ${p.horas_max_atencion} horas laborales` })),
            'Seleccione la prioridad'
        );
        actualizarAyudaTipo();

        const vacios = [];
        if (areas.length === 0) vacios.push('áreas');
        if (tiposSolicitud.length === 0) vacios.push('tipos de solicitud');
        if (prioridades.length === 0) vacios.push('prioridades');
        if (vacios.length > 0) {
            // empty: no se puede registrar sin catálogos activos
            mostrarMensaje(estado, '');
            mostrarMensaje(error, `No hay ${vacios.join(', ')} disponibles. Contacte al administrador del sistema.`);
            return;
        }

        catalogosListos = true;
        habilitarFormulario(true);
        mostrarMensaje(estado, '');
    }

    function actualizarAyudaTipo() {
        const tipo = tipos.find((t) => String(t.id) === selects.tipos_solicitud.value);
        ayudaTipo.textContent = tipo && tipo.descripcion ? tipo.descripcion : '';
    }

    function actualizarContador() {
        const largo = formulario.elements.namedItem('descripcion').value.length;
        contador.textContent = `${largo} / ${LIMITES.descripcion}`;
    }

    // ---- validación de ayuda --------------------------------------------------------------
    function leerDatos() {
        const valor = (n) => formulario.elements.namedItem(n).value;
        return {
            titulo: valor('titulo').trim(),
            descripcion: valor('descripcion').trim(),
            area_destino_id: Number.parseInt(valor('area_destino_id'), 10),
            tipo_solicitud_id: Number.parseInt(valor('tipo_solicitud_id'), 10),
            prioridad_id: Number.parseInt(valor('prioridad_id'), 10),
        };
    }

    function validar(d) {
        const e = {};
        if (d.titulo === '') e.titulo = 'Escriba un título.';
        else if (d.titulo.length > LIMITES.titulo) e.titulo = `El título admite hasta ${LIMITES.titulo} caracteres.`;
        if (d.descripcion === '') e.descripcion = 'Describa lo que necesita.';
        else if (d.descripcion.length > LIMITES.descripcion) e.descripcion = `La descripción admite hasta ${LIMITES.descripcion} caracteres.`;
        if (!Number.isInteger(d.area_destino_id)) e.area_destino_id = 'Seleccione el área destino.';
        if (!Number.isInteger(d.tipo_solicitud_id)) e.tipo_solicitud_id = 'Seleccione el tipo de solicitud.';
        if (!Number.isInteger(d.prioridad_id)) e.prioridad_id = 'Seleccione la prioridad.';
        return e;
    }

    // ---- success -----------------------------------------------------------------------
    function mostrarExito(detalle) {
        const titulo = document.createElement('p');
        const fuerte = document.createElement('strong');
        fuerte.textContent = String(detalle.numero);
        titulo.append('Solicitud ', fuerte, ' registrada.');
        const limite = document.createElement('p');
        limite.textContent = `Fecha límite de atención: ${fechaHora(detalle.fecha_limite)} (prioridad ${detalle.prioridad?.nombre ?? ''}).`;
        const estadoTexto = document.createElement('p');
        estadoTexto.textContent = `Estado: ${detalle.estado?.nombre ?? ''}. El jefe del área ${detalle.area_destino?.nombre ?? ''} la asignará a un responsable.`;
        exito.replaceChildren(titulo, limite, estadoTexto);
        exito.hidden = false;
        exito.focus();
    }

    // ---- envío ---------------------------------------------------------------------------
    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        if (!catalogosListos) {
            return;
        }
        limpiarMensajes();

        const datos = leerDatos();
        const errores = validar(datos);
        if (Object.keys(errores).length > 0) {
            Object.entries(errores).forEach(([c, t]) => errorCampo(c, t));
            enfocarPrimerError();
            return;
        }

        // loading (evita doble envío)
        habilitarFormulario(false);
        marcarOcupado(raiz, true);
        botonEnviar.textContent = 'Registrando…';
        const r = await registrarSolicitud(datos);
        marcarOcupado(raiz, false);
        botonEnviar.textContent = 'Registrar solicitud';
        habilitarFormulario(true);

        if (r.ok) {
            formulario.reset();
            actualizarContador();
            actualizarAyudaTipo();
            mostrarExito(r.data);
            document.dispatchEvent(new CustomEvent('solicitud:registrada', { detail: { solicitud: r.data } }));
            return;
        }

        // errores (los datos escritos se conservan)
        switch (r.error.codigo) {
            case 'VALIDACION':
                if (r.error.detalles) {
                    Object.entries(r.error.detalles).forEach(([c, mensajes]) => {
                        if (CAMPOS.includes(c)) {
                            errorCampo(c, Array.isArray(mensajes) ? mensajes.join(' ') : String(mensajes));
                        }
                    });
                }
                mostrarMensaje(error, 'Revise los datos marcados.');
                enfocarPrimerError();
                break;
            case 'CSRF_INVALIDO': {
                const me = await sesionActual(); // renueva el token en memoria
                mostrarMensaje(error, me.ok
                    ? 'Su sesión se actualizó. Pulse "Registrar solicitud" otra vez.'
                    : 'No se pudo validar su sesión. Inicie sesión nuevamente.');
                error.focus();
                break;
            }
            case 'NO_AUTENTICADO':
                break; // acceso.js muestra "Su sesión expiró"
            default:
                mostrarMensaje(error, r.error.codigo === 'RED'
                    ? r.error.mensaje
                    : 'No se pudo registrar la solicitud. Intente nuevamente en unos minutos.');
                error.focus();
        }
    });

    formulario.elements.namedItem('descripcion').addEventListener('input', actualizarContador);
    selects.tipos_solicitud.addEventListener('change', actualizarAyudaTipo);
    botonReintentar.addEventListener('click', () => cargarCatalogos());

    // ---- sesión -------------------------------------------------------------------------
    document.addEventListener('sesion:cambio', (evento) => {
        const usuario = evento.detail?.usuario ?? null;
        if (!usuario) {
            mostrarSinSesion();
            return;
        }
        const mismoUsuario = usuarioActual !== null && usuarioActual.id === usuario.id;
        usuarioActual = usuario;
        avisoSinSesion.hidden = true;
        panel.hidden = false;
        if (!mismoUsuario || !catalogosListos) {
            limpiarMensajes();
            formulario.reset();
            actualizarContador();
            cargarCatalogos();
        }
    });
}
