/**
 * Sección "Acceso": iniciar y cerrar sesión desde la landing.
 * Estados: loading (comprobando / ingresando / saliendo), success (sesión iniciada),
 * error (credenciales, validación, límite de intentos, red) y unauthorized (sin sesión: formulario).
 * La validación aquí es solo de ayuda; la real está en el backend (AuthValidator).
 * Emite 'sesion:cambio' con detail { usuario } (objeto o null) para el resto de módulos.
 */
import { iniciarSesion, cerrarSesion, sesionActual } from '../api/auth-api.js';
import { mostrarMensaje, marcarOcupado } from './ui.js';

const MENSAJES_ERROR = {
    CREDENCIALES_INVALIDAS: 'Usuario o contraseña incorrectos.',
    DEMASIADOS_INTENTOS: 'Demasiados intentos fallidos. Espere 15 minutos antes de volver a intentar.',
    VALIDACION: 'Revise los datos marcados.',
    RED: 'No se pudo conectar con el servidor. Intente nuevamente.',
};

export function iniciarAcceso(raiz = document.querySelector('[data-modulo="acceso"]')) {
    if (!raiz) {
        return { comprobarSesion: async () => {} };
    }

    const estado = raiz.querySelector('[data-estado-mensaje]');
    const formulario = raiz.querySelector('[data-formulario="login"]');
    const errorGeneral = formulario.querySelector('[data-error-mensaje]');
    const botonIngresar = formulario.querySelector('button[type="submit"]');
    const campoUsuario = formulario.elements.namedItem('nombre_usuario');
    const campoPassword = formulario.elements.namedItem('password');
    const panelSesion = raiz.querySelector('[data-sesion]');
    const saludo = raiz.querySelector('[data-sesion-saludo]');
    const botonSalir = raiz.querySelector('[data-accion="logout"]');
    const enlaceEncabezado = document.querySelector('[data-sesion-enlace]');

    const campos = { nombre_usuario: campoUsuario, password: campoPassword };

    function errorCampo(nombre, texto) {
        const control = campos[nombre];
        const salida = formulario.querySelector(`[data-error-campo="${nombre}"]`);
        mostrarMensaje(salida, texto);
        if (control) {
            if (texto) {
                control.setAttribute('aria-invalid', 'true');
            } else {
                control.removeAttribute('aria-invalid');
            }
        }
    }

    function limpiarErrores() {
        mostrarMensaje(errorGeneral, '');
        Object.keys(campos).forEach((nombre) => errorCampo(nombre, ''));
    }

    function avisar(usuario) {
        document.dispatchEvent(new CustomEvent('sesion:cambio', { detail: { usuario } }));
    }

    /** unauthorized: sin sesión, se muestra el formulario. */
    function mostrarSinSesion(mensaje = '') {
        panelSesion.hidden = true;
        formulario.hidden = false;
        mostrarMensaje(estado, mensaje);
        if (enlaceEncabezado) {
            enlaceEncabezado.textContent = 'Iniciar sesión';
        }
        avisar(null);
    }

    /** success: sesión iniciada. */
    function mostrarConSesion(usuario) {
        const nombres = String(usuario.nombres ?? '');
        saludo.textContent = `Hola, ${nombres}`;
        raiz.querySelector('[data-sesion-usuario]').textContent = String(usuario.nombre_usuario ?? '');
        raiz.querySelector('[data-sesion-area]').textContent = usuario.area ? String(usuario.area.nombre ?? '') : 'Sin área';
        raiz.querySelector('[data-sesion-roles]').textContent = Array.isArray(usuario.roles)
            ? usuario.roles.map((rol) => String(rol).replace('_', ' ').toLowerCase()).join(', ')
            : '';
        formulario.hidden = true;
        panelSesion.hidden = false;
        mostrarMensaje(estado, '');
        if (enlaceEncabezado) {
            enlaceEncabezado.textContent = `Hola, ${nombres}`;
        }
        avisar(usuario);
    }

    function validar() {
        const errores = {};
        const usuario = campoUsuario.value.trim();
        if (usuario === '') {
            errores.nombre_usuario = 'Escriba su usuario.';
        } else if (usuario.length < 3 || usuario.length > 50) {
            errores.nombre_usuario = 'El usuario debe tener entre 3 y 50 caracteres.';
        }
        if (campoPassword.value === '') {
            errores.password = 'Escriba su contraseña.';
        }
        return errores;
    }

    function enfocarPrimerError() {
        const invalido = formulario.querySelector('[aria-invalid="true"]');
        (invalido ?? errorGeneral)?.focus?.();
    }

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        limpiarErrores();

        const errores = validar();
        if (Object.keys(errores).length > 0) {
            Object.entries(errores).forEach(([nombre, texto]) => errorCampo(nombre, texto));
            enfocarPrimerError();
            return;
        }

        // loading
        marcarOcupado(raiz, true, [botonIngresar]);
        botonIngresar.textContent = 'Ingresando…';
        const r = await iniciarSesion(campoUsuario.value.trim(), campoPassword.value);
        marcarOcupado(raiz, false, [botonIngresar]);
        botonIngresar.textContent = 'Iniciar sesión';
        campoPassword.value = ''; // la contraseña nunca se conserva

        if (r.ok) {
            mostrarConSesion(r.data.usuario);
            saludo.focus();
            return;
        }

        // error
        if (r.error.codigo === 'VALIDACION' && r.error.detalles) {
            Object.entries(r.error.detalles).forEach(([nombre, mensajes]) => {
                if (campos[nombre]) {
                    errorCampo(nombre, Array.isArray(mensajes) ? mensajes.join(' ') : String(mensajes));
                }
            });
        }
        mostrarMensaje(errorGeneral, MENSAJES_ERROR[r.error.codigo] ?? 'No se pudo iniciar sesión. Intente nuevamente.');
        if (r.error.codigo === 'VALIDACION') {
            enfocarPrimerError();
        } else {
            campoPassword.focus();
        }
    });

    botonSalir.addEventListener('click', async () => {
        marcarOcupado(raiz, true, [botonSalir]);
        mostrarMensaje(estado, 'Cerrando sesión…');
        let r = await cerrarSesion();
        if (!r.ok && r.error.codigo === 'CSRF_INVALIDO') {
            // Token desactualizado: se renueva con /auth/me y se reintenta una vez.
            const me = await sesionActual();
            r = me.ok ? await cerrarSesion() : me;
        }
        marcarOcupado(raiz, false, [botonSalir]);

        if (r.ok || r.status === 401) {
            mostrarSinSesion('Sesión cerrada.');
            campoUsuario.focus();
            return;
        }
        mostrarMensaje(estado, 'No se pudo cerrar la sesión. Intente nuevamente.');
    });

    // Sesión expirada en cualquier llamada protegida (avisada por http.js).
    document.addEventListener('sesion:expirada', () => {
        mostrarSinSesion('Su sesión expiró. Inicie sesión nuevamente.');
    });

    /** Al cargar la página: GET /auth/me. 401 = sin sesión (no es un error). */
    async function comprobarSesion() {
        mostrarMensaje(estado, 'Comprobando sesión…');
        const r = await sesionActual();
        if (r.ok) {
            mostrarConSesion(r.data.usuario);
        } else if (r.status === 401) {
            mostrarSinSesion('');
        } else {
            mostrarSinSesion('No se pudo comprobar la sesión. Puede intentar ingresar.');
        }
    }

    return { comprobarSesion };
}
