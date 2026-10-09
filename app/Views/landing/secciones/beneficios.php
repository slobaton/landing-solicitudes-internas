<?php
/**
 * Sección 4 — Beneficios.
 *
 * @var App\Core\View $this
 */
$beneficios = [
    ['icono' => 'icono-numero.svg', 'titulo' => 'Número único', 'texto' => 'Cada solicitud recibe un número AAAA-NNNNN para identificarla y darle seguimiento sin confusiones.'],
    ['icono' => 'icono-reloj.svg', 'titulo' => 'Fecha límite automática', 'texto' => 'El sistema calcula el plazo según la prioridad, contando solo horas laborales y sin feriados.'],
    ['icono' => 'icono-historial.svg', 'titulo' => 'Historial completo', 'texto' => 'Cada registro, cambio de estado y cancelación queda guardado con fecha, autor y motivo.'],
    ['icono' => 'icono-canal.svg', 'titulo' => 'Un solo canal', 'texto' => 'Todas las áreas reciben sus pedidos en el mismo lugar, con la misma información mínima.'],
];
?>
<section class="seccion seccion--alterna" id="beneficios" aria-labelledby="beneficios-titulo">
    <div class="contenedor">
        <h2 class="seccion__titulo" id="beneficios-titulo">Beneficios</h2>
        <p class="seccion__intro">Menos llamadas para preguntar "¿cómo va lo mío?" y más claridad para quien atiende.</p>
        <ul class="rejilla rejilla--4 beneficios">
            <?php foreach ($beneficios as $beneficio): ?>
                <li class="beneficio">
                    <img class="beneficio__icono" src="<?= $this->asset('img/' . $beneficio['icono']) ?>" alt="" width="48" height="48">
                    <h3 class="beneficio__titulo"><?= $this->e($beneficio['titulo']) ?></h3>
                    <p class="beneficio__texto"><?= $this->e($beneficio['texto']) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
