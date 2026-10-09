<?php
/**
 * Sección 8 — Mis solicitudes (RF-09, RF-14).
 * Lista: GET /api/v1/solicitudes · Detalle con historial: GET /api/v1/solicitudes/{id}
 * Cancelar con motivo: DELETE /api/v1/solicitudes/{id} (cambia a CANCELADA; nunca borra).
 * La edición (PUT) no forma parte de la landing (diseno_landing.md §3).
 * Filas, detalle e historial los construye js/modules/mis-solicitudes.js con textContent.
 *
 * @var App\Core\View $this
 */
?>
<section class="seccion seccion--alterna" id="mis-solicitudes" aria-labelledby="mis-solicitudes-titulo" data-modulo="mis-solicitudes" aria-busy="false">
    <div class="contenedor">
        <h2 class="seccion__titulo" id="mis-solicitudes-titulo">Mis solicitudes</h2>
        <p class="seccion__intro">Consulte el estado y la fecha límite de las solicitudes que registró. Puede cancelar las que estén Registradas o Asignadas.</p>

        <div class="aviso" data-mis-sin-sesion hidden>
            <p>Inicie sesión para ver sus solicitudes.</p>
            <a class="boton boton--primario" href="#acceso">Ir a iniciar sesión</a>
        </div>

        <div data-mis-panel hidden>
            <div class="lista__barra">
                <p class="mensaje" role="status" data-estado-mensaje></p>
                <button class="boton boton--secundario" type="button" data-accion="recargar">Actualizar lista</button>
            </div>
            <p class="mensaje mensaje--error" role="alert" data-error-mensaje hidden></p>

            <div class="aviso" data-mis-vacio hidden>
                <p>Todavía no ha registrado solicitudes.</p>
                <a class="boton boton--primario" href="#registrar">Registrar una solicitud</a>
            </div>

            <div class="tabla-envoltura" data-mis-tabla hidden>
                <!-- Roles ARIA explícitos: en móvil la tabla se muestra como tarjetas (display: block) y algunos
                     lectores de pantalla pierden la semántica de tabla sin ellos (revisión CP-FRONT-06). -->
                <table class="tabla tabla--solicitudes" role="table">
                    <caption class="visualmente-oculto">Mis solicitudes, de la más reciente a la más antigua</caption>
                    <thead role="rowgroup">
                        <tr role="row">
                            <th scope="col" role="columnheader">Número</th>
                            <th scope="col" role="columnheader">Título</th>
                            <th scope="col" role="columnheader">Estado</th>
                            <th scope="col" role="columnheader">Prioridad</th>
                            <th scope="col" role="columnheader">Registro</th>
                            <th scope="col" role="columnheader">Fecha límite</th>
                            <th scope="col" role="columnheader"><span class="visualmente-oculto">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup" data-mis-filas></tbody>
                </table>
            </div>

            <nav class="paginacion" aria-label="Páginas de mis solicitudes" data-mis-paginacion hidden>
                <button class="boton boton--secundario" type="button" data-accion="anterior">&larr; Anterior</button>
                <p class="paginacion__texto" data-paginacion-texto></p>
                <button class="boton boton--secundario" type="button" data-accion="siguiente">Siguiente &rarr;</button>
            </nav>

            <article class="detalle panel" aria-labelledby="detalle-titulo" data-detalle hidden>
                <div class="detalle__cabecera">
                    <h3 class="detalle__titulo" id="detalle-titulo" tabindex="-1" data-detalle-titulo></h3>
                    <button class="boton boton--secundario" type="button" data-accion="cerrar-detalle">Cerrar detalle</button>
                </div>
                <p class="mensaje" role="status" data-detalle-estado></p>
                <p class="mensaje mensaje--error" role="alert" tabindex="-1" data-detalle-error hidden></p>

                <div data-detalle-contenido hidden>
                    <dl class="detalle__datos">
                        <div><dt>Estado</dt><dd data-campo="estado"></dd></div>
                        <div><dt>Área destino</dt><dd data-campo="area"></dd></div>
                        <div><dt>Tipo</dt><dd data-campo="tipo"></dd></div>
                        <div><dt>Prioridad</dt><dd data-campo="prioridad"></dd></div>
                        <div><dt>Registrada</dt><dd data-campo="registro"></dd></div>
                        <div><dt>Fecha límite</dt><dd data-campo="limite"></dd></div>
                        <div><dt>Responsable</dt><dd data-campo="responsable"></dd></div>
                        <div><dt>Cerrada</dt><dd data-campo="cierre"></dd></div>
                    </dl>
                    <h4 class="detalle__subtitulo">Descripción</h4>
                    <p class="detalle__descripcion" data-campo="descripcion"></p>

                    <h4 class="detalle__subtitulo">Historial</h4>
                    <ol class="historial" data-detalle-historial></ol>

                    <div class="cancelar" data-cancelar hidden>
                        <button class="boton boton--peligro" type="button" data-accion="mostrar-cancelar" aria-expanded="false" aria-controls="form-cancelar">Cancelar esta solicitud</button>
                        <form class="formulario cancelar__formulario" id="form-cancelar" data-formulario="cancelar" novalidate hidden>
                            <p class="aviso aviso--peligro">La solicitud pasará a <strong>Cancelada</strong> y ya no se atenderá. No se borra: quedará en su historial con el motivo.</p>
                            <div class="campo">
                                <label class="campo__etiqueta" for="cancelar-motivo">Motivo de la cancelación <span class="campo__obligatorio">(obligatorio)</span></label>
                                <textarea class="campo__control" id="cancelar-motivo" name="motivo" rows="3" required maxlength="500"
                                          aria-describedby="cancelar-motivo-ayuda cancelar-motivo-error"></textarea>
                                <p class="campo__ayuda" id="cancelar-motivo-ayuda">Hasta 500 caracteres.</p>
                                <p class="campo__error" id="cancelar-motivo-error" data-error-campo="motivo" hidden></p>
                            </div>
                            <div class="cancelar__acciones">
                                <button class="boton boton--peligro" type="submit">Confirmar cancelación</button>
                                <button class="boton boton--secundario" type="button" data-accion="ocultar-cancelar">Volver</button>
                            </div>
                        </form>
                    </div>
                </div>
            </article>
        </div>

        <noscript><p class="aviso">Para consultar sus solicitudes necesita JavaScript habilitado.</p></noscript>
    </div>
</section>
