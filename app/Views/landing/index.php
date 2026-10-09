<?php
/**
 * Landing "/": compone las secciones en el orden que define LandingController.
 *
 * @var App\Core\View $this
 * @var list<array{id: string, vista: string, nav: string|null}> $secciones
 * @var list<string> $idsSecciones
 */
?>
<?php foreach ($secciones as $seccion): ?>
<?= $this->parcial($seccion['vista'], ['idsSecciones' => $idsSecciones]) ?>
<?php endforeach; ?>
