<?php
/**
 * Sección 6 — Acceso: iniciar y cerrar sesión (POST /auth/login, POST /auth/logout, GET /auth/me).
 * No hay registro público de cuentas ni recuperación de contraseña (decisiones_frontend.md).
 * El comportamiento está en js/modules/acceso.js.
 *
 * @var App\Core\View $this
 */
?>
<section class="seccion seccion--alterna" id="acceso" aria-labelledby="acceso-titulo" data-modulo="acceso">
    <div class="contenedor acceso">
        <div class="acceso__texto">
            <h2 class="seccion__titulo" id="acceso-titulo">Acceso</h2>
            <p class="seccion__intro">Ingrese con el usuario y la contraseña de la institución para registrar solicitudes y consultar las suyas.</p>
            <p class="campo__ayuda">Las cuentas las crea el administrador del sistema; no hay registro público.</p>
        </div>

        <div class="panel">
            <p class="mensaje" role="status" data-estado-mensaje>Comprobando sesión…</p>

            <form class="formulario" data-formulario="login" novalidate hidden>
                <p class="mensaje mensaje--error" role="alert" data-error-mensaje hidden></p>
                <div class="campo">
                    <label class="campo__etiqueta" for="login-usuario">Usuario <span class="campo__obligatorio">(obligatorio)</span></label>
                    <input class="campo__control" id="login-usuario" name="nombre_usuario" type="text"
                           autocomplete="username" autocapitalize="none" spellcheck="false"
                           required minlength="3" maxlength="50" aria-describedby="login-usuario-error">
                    <p class="campo__error" id="login-usuario-error" data-error-campo="nombre_usuario" hidden></p>
                </div>
                <div class="campo">
                    <label class="campo__etiqueta" for="login-password">Contraseña <span class="campo__obligatorio">(obligatorio)</span></label>
                    <input class="campo__control" id="login-password" name="password" type="password"
                           autocomplete="current-password" required maxlength="255" aria-describedby="login-password-error">
                    <p class="campo__error" id="login-password-error" data-error-campo="password" hidden></p>
                </div>
                <button class="boton boton--primario boton--ancho" type="submit">Iniciar sesión</button>
            </form>

            <div class="sesion" data-sesion hidden>
                <h3 class="sesion__saludo" tabindex="-1" data-sesion-saludo></h3>
                <dl class="sesion__datos">
                    <div><dt>Usuario</dt><dd data-sesion-usuario></dd></div>
                    <div><dt>Área</dt><dd data-sesion-area></dd></div>
                    <div><dt>Rol</dt><dd data-sesion-roles></dd></div>
                </dl>
                <button class="boton boton--secundario" type="button" data-accion="logout">Cerrar sesión</button>
            </div>

            <noscript><p class="aviso">Para iniciar sesión necesita JavaScript habilitado.</p></noscript>
        </div>
    </div>
</section>
