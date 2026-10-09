<?php
/**
 * Sección 2 — El problema.
 *
 * @var App\Core\View $this
 */
$canales = [
    ['icono' => 'icono-correo.svg', 'titulo' => 'Se pierden en el correo', 'texto' => 'Los pedidos quedan enterrados entre cientos de mensajes y nadie sabe quién los tiene.'],
    ['icono' => 'icono-chat.svg', 'titulo' => 'Se olvidan en WhatsApp', 'texto' => 'Los mensajes de grupos y chats personales no dejan un registro formal.'],
    ['icono' => 'icono-telefono.svg', 'titulo' => 'Llamadas sin registro', 'texto' => 'Lo que se pide por teléfono depende de la memoria de quien atiende.'],
    ['icono' => 'icono-persona.svg', 'titulo' => 'Pedidos en persona sin plazo', 'texto' => 'Sin número ni fecha límite, no hay forma de reclamar un retraso.'],
];
?>
<section class="seccion seccion--alterna" id="problema" aria-labelledby="problema-titulo">
    <div class="contenedor">
        <h2 class="seccion__titulo" id="problema-titulo">El problema</h2>
        <p class="seccion__intro">Hoy las solicitudes entre áreas llegan por muchos canales distintos. Cada uno funciona a su manera y ninguno deja rastro.</p>
        <ul class="rejilla rejilla--4 tarjetas">
            <?php foreach ($canales as $canal): ?>
                <li class="tarjeta">
                    <img class="tarjeta__icono" src="<?= $this->asset('img/' . $canal['icono']) ?>" alt="" width="40" height="40">
                    <h3 class="tarjeta__titulo"><?= $this->e($canal['titulo']) ?></h3>
                    <p class="tarjeta__texto"><?= $this->e($canal['texto']) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="seccion__cierre">Resultado: <strong>nadie sabe en qué estado está su pedido.</strong></p>
    </div>
</section>
