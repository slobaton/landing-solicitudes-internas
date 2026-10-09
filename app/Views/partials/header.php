<?php
/**
 * Encabezado con navegación por anclas. El botón "Menú" está oculto sin JavaScript
 * (la navegación se ve completa); js/modules/navegacion.js lo activa en pantallas pequeñas.
 *
 * @var App\Core\View $this
 * @var list<array{id: string, vista: string, nav: string|null}> $secciones
 */
?>
<header class="encabezado">
    <div class="contenedor encabezado__barra">
        <a class="marca" href="#inicio">
            <img class="marca__logo" src="<?= $this->asset('img/favicon.svg') ?>" alt="" width="32" height="32">
            <span class="marca__texto">SIS <span class="marca__sub">Solicitudes</span></span>
        </a>
        <button class="boton-menu" type="button" aria-expanded="false" aria-controls="menu-principal" hidden>
            <span class="boton-menu__icono" aria-hidden="true"></span>
            Menú
        </button>
        <nav class="navegacion" id="menu-principal" aria-label="Secciones de la página">
            <ul class="navegacion__lista">
                <?php foreach ($secciones as $seccion): ?>
                    <?php if ($seccion['nav'] !== null): ?>
                        <li><a class="navegacion__enlace" href="#<?= $this->e($seccion['id']) ?>"><?= $this->e($seccion['nav']) ?></a></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </nav>
        <a class="boton boton--primario encabezado__sesion" href="#acceso" data-sesion-enlace>Iniciar sesión</a>
    </div>
</header>
