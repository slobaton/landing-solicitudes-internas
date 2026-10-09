<?php
/**
 * Pie de página.
 *
 * @var App\Core\View $this
 * @var string $titulo
 * @var string $anio
 */
?>
<footer class="pie" aria-labelledby="pie-titulo">
    <div class="contenedor pie__rejilla">
        <div>
            <h2 class="pie__titulo" id="pie-titulo"><?= $this->e($titulo) ?></h2>
            <p class="pie__texto">Un solo canal para registrar, asignar, atender y cerrar las solicitudes internas de la institución.</p>
        </div>
        <ul class="pie__enlaces">
            <li><a href="#inicio">Volver al inicio</a></li>
            <li><a href="/api/v1/health">Estado del servicio</a></li>
        </ul>
    </div>
    <div class="contenedor pie__legal">
        <p>&copy; <?= $this->e($anio) ?> Institución. Trabajo Final — Módulo 3, Desarrollo al lado del cliente y servidor web.</p>
    </div>
</footer>
