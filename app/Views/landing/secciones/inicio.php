<?php
/**
 * Sección 1 — Inicio (hero). Único <h1> de la página.
 * "Registrar solicitud" solo se muestra cuando la sección #registrar existe (CP-FRONT-04).
 *
 * @var App\Core\View $this
 * @var list<string> $idsSecciones
 */
$hayRegistro = in_array('registrar', $idsSecciones, true);
?>
<section class="hero" id="inicio" aria-labelledby="inicio-titulo">
    <div class="contenedor hero__rejilla">
        <div class="hero__texto">
            <p class="hero__antetitulo">Sistema de seguimiento de solicitudes internas</p>
            <h1 class="hero__titulo" id="inicio-titulo">Sus solicitudes internas, en un solo lugar y con seguimiento.</h1>
            <p class="hero__subtitulo">Registre su pedido a cualquier área, reciba un número y sepa en todo momento en qué estado está y cuál es su fecha límite de atención.</p>
            <div class="hero__acciones">
                <?php if ($hayRegistro): ?>
                    <a class="boton boton--primario" href="#registrar">Registrar solicitud</a>
                    <a class="boton boton--secundario" href="#como-funciona">Cómo funciona</a>
                <?php else: ?>
                    <a class="boton boton--primario" href="#como-funciona">Cómo funciona</a>
                    <a class="boton boton--secundario" href="#beneficios">Ver beneficios</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero__figura">
            <img src="<?= $this->asset('img/hero-solicitud.svg') ?>" alt="" width="420" height="320">
        </div>
    </div>
</section>
