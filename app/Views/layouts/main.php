<?php
/**
 * Layout principal. Solo presentación (estructura_frontend.md).
 *
 * @var App\Core\View $this
 * @var string $titulo
 * @var string $descripcion
 * @var string $contenido  HTML ya renderizado y escapado por cada vista
 * @var list<array{id: string, vista: string, nav: string|null}> $secciones
 * @var string $anio
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= $this->e($descripcion) ?>">
    <meta name="theme-color" content="#1B2A41">
    <title><?= $this->e($titulo) ?></title>
    <link rel="icon" href="<?= $this->asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= $this->asset('css/variables.css') ?>">
    <link rel="stylesheet" href="<?= $this->asset('css/base.css') ?>">
    <link rel="stylesheet" href="<?= $this->asset('css/layout.css') ?>">
    <link rel="stylesheet" href="<?= $this->asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= $this->asset('css/forms.css') ?>">
    <link rel="stylesheet" href="<?= $this->asset('css/responsive.css') ?>">
    <script type="module" src="<?= $this->asset('js/app.js') ?>"></script>
</head>
<body>
    <a class="saltar" href="#contenido">Saltar al contenido</a>
    <?= $this->parcial('partials/header', ['secciones' => $secciones]) ?>
    <main id="contenido" tabindex="-1">
        <?= $contenido ?>
    </main>
    <?= $this->parcial('partials/footer', ['titulo' => $titulo, 'anio' => $anio]) ?>
</body>
</html>
