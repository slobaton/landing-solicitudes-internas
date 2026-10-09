<?php
/**
 * Sección 5 — Cifras en vivo (GET /api/v1/estadisticas, público).
 * Los números los escribe js/modules/estadisticas.js con textContent.
 *
 * @var App\Core\View $this
 */
$indicadores = [
    ['clave' => 'total', 'etiqueta' => 'Solicitudes registradas', 'ayuda' => 'Todas, en cualquier estado'],
    ['clave' => 'registradas', 'etiqueta' => 'Pendientes de asignar', 'ayuda' => 'Estado Registrada'],
    ['clave' => 'en_proceso', 'etiqueta' => 'En proceso', 'ayuda' => 'Asignadas o en proceso'],
    ['clave' => 'atendidas_cerradas', 'etiqueta' => 'Atendidas o cerradas', 'ayuda' => 'Trabajo terminado'],
];
?>
<section class="seccion" id="cifras" aria-labelledby="cifras-titulo" data-modulo="estadisticas" aria-busy="false">
    <div class="contenedor">
        <h2 class="seccion__titulo" id="cifras-titulo">Cifras en vivo</h2>
        <p class="seccion__intro">Datos leídos en este momento de la base de datos del sistema. Solo totales: no se muestra información de ninguna persona.</p>
        <ul class="rejilla rejilla--4 cifras">
            <?php foreach ($indicadores as $indicador): ?>
                <li class="cifra">
                    <span class="cifra__valor numero" data-cifra="<?= $this->e($indicador['clave']) ?>">—</span>
                    <span class="cifra__etiqueta"><?= $this->e($indicador['etiqueta']) ?></span>
                    <span class="cifra__ayuda"><?= $this->e($indicador['ayuda']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="cifras__pie">
            <p class="mensaje" role="status" data-estado-mensaje>Cargando cifras…</p>
            <p class="mensaje mensaje--error" role="alert" data-error-mensaje hidden></p>
            <button class="boton boton--secundario" type="button" data-accion="actualizar">Actualizar</button>
        </div>
        <noscript><p class="aviso">Esta sección requiere JavaScript para mostrar las cifras.</p></noscript>
    </div>
</section>
