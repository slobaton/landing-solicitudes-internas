<?php
/**
 * Sección 7 — Registrar solicitud (RF-02, RF-03).
 * Catálogos: GET /api/v1/catalogos (con sesión). Envío: POST /api/v1/solicitudes con X-CSRF-Token.
 * El comportamiento está en js/modules/registro-solicitud.js. Los límites coinciden con
 * SolicitudValidator (título 150, descripción 5000); la validación real es del backend.
 *
 * @var App\Core\View $this
 */
?>
<section class="seccion" id="registrar" aria-labelledby="registrar-titulo" data-modulo="registro" aria-busy="false">
    <div class="contenedor contenedor--formulario">
        <h2 class="seccion__titulo" id="registrar-titulo">Registrar solicitud</h2>
        <p class="seccion__intro">Describa lo que necesita y elija el área que debe atenderlo. Al registrar recibirá un número y la fecha límite de atención.</p>

        <div class="aviso" data-registro-sin-sesion hidden>
            <p>Inicie sesión para registrar una solicitud.</p>
            <a class="boton boton--primario" href="#acceso">Ir a iniciar sesión</a>
        </div>

        <div class="panel" data-registro-panel hidden>
            <p class="mensaje" role="status" data-estado-mensaje></p>
            <div class="mensaje mensaje--exito-caja" role="status" tabindex="-1" data-exito-mensaje hidden></div>
            <p class="mensaje mensaje--error" role="alert" tabindex="-1" data-error-mensaje hidden></p>
            <button class="boton boton--secundario" type="button" data-accion="reintentar-catalogos" hidden>Reintentar</button>

            <form class="formulario" data-formulario="registro" novalidate>
                <div class="campo">
                    <label class="campo__etiqueta" for="reg-titulo">Título <span class="campo__obligatorio">(obligatorio)</span></label>
                    <input class="campo__control" id="reg-titulo" name="titulo" type="text" required maxlength="150"
                           aria-describedby="reg-titulo-ayuda reg-titulo-error">
                    <p class="campo__ayuda" id="reg-titulo-ayuda">Una frase corta, por ejemplo: "Instalar impresora en Contabilidad". Hasta 150 caracteres.</p>
                    <p class="campo__error" id="reg-titulo-error" data-error-campo="titulo" hidden></p>
                </div>

                <div class="campo">
                    <label class="campo__etiqueta" for="reg-descripcion">Descripción <span class="campo__obligatorio">(obligatorio)</span></label>
                    <textarea class="campo__control" id="reg-descripcion" name="descripcion" rows="5" required maxlength="5000"
                              aria-describedby="reg-descripcion-ayuda reg-descripcion-error"></textarea>
                    <p class="campo__ayuda" id="reg-descripcion-ayuda">Qué necesita, dónde y cualquier dato útil. Hasta 5000 caracteres. <span class="contador" data-contador="descripcion">0 / 5000</span></p>
                    <p class="campo__error" id="reg-descripcion-error" data-error-campo="descripcion" hidden></p>
                </div>

                <div class="formulario__fila">
                    <div class="campo">
                        <label class="campo__etiqueta" for="reg-area">Área destino <span class="campo__obligatorio">(obligatorio)</span></label>
                        <select class="campo__control" id="reg-area" name="area_destino_id" required aria-describedby="reg-area-error" data-catalogo="areas">
                            <option value="">Cargando…</option>
                        </select>
                        <p class="campo__error" id="reg-area-error" data-error-campo="area_destino_id" hidden></p>
                    </div>

                    <div class="campo">
                        <label class="campo__etiqueta" for="reg-tipo">Tipo de solicitud <span class="campo__obligatorio">(obligatorio)</span></label>
                        <select class="campo__control" id="reg-tipo" name="tipo_solicitud_id" required aria-describedby="reg-tipo-ayuda reg-tipo-error" data-catalogo="tipos_solicitud">
                            <option value="">Cargando…</option>
                        </select>
                        <p class="campo__ayuda" id="reg-tipo-ayuda" data-tipo-descripcion></p>
                        <p class="campo__error" id="reg-tipo-error" data-error-campo="tipo_solicitud_id" hidden></p>
                    </div>
                </div>

                <div class="campo campo--corto">
                    <label class="campo__etiqueta" for="reg-prioridad">Prioridad <span class="campo__obligatorio">(obligatorio)</span></label>
                    <select class="campo__control" id="reg-prioridad" name="prioridad_id" required aria-describedby="reg-prioridad-ayuda reg-prioridad-error" data-catalogo="prioridades">
                        <option value="">Cargando…</option>
                    </select>
                    <p class="campo__ayuda" id="reg-prioridad-ayuda">Define la fecha límite: se cuentan solo horas laborales.</p>
                    <p class="campo__error" id="reg-prioridad-error" data-error-campo="prioridad_id" hidden></p>
                </div>

                <button class="boton boton--primario" type="submit">Registrar solicitud</button>
            </form>
        </div>

        <noscript><p class="aviso">Para registrar solicitudes necesita JavaScript habilitado.</p></noscript>
    </div>
</section>
