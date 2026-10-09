<?php
/**
 * Sección 3 — Cómo funciona (estados del sistema y plazos por prioridad, V009).
 *
 * @var App\Core\View $this
 */
$pasos = [
    ['titulo' => 'Registrar', 'quien' => 'Usted', 'texto' => 'Describe su pedido y elige el área y la prioridad. Recibe un número (por ejemplo 2026-00042) y una fecha límite calculada automáticamente.', 'estado' => 'Registrada'],
    ['titulo' => 'Asignar', 'quien' => 'Jefe del área', 'texto' => 'El jefe del área destino revisa la solicitud y elige a la persona responsable de atenderla.', 'estado' => 'Asignada'],
    ['titulo' => 'Atender', 'quien' => 'Responsable', 'texto' => 'La persona responsable trabaja en el pedido y lo marca como atendido al terminar.', 'estado' => 'En proceso → Atendida'],
    ['titulo' => 'Cerrar', 'quien' => 'Usted o el sistema', 'texto' => 'Usted confirma que quedó resuelto, o el sistema la cierra automáticamente pasado el plazo de confirmación.', 'estado' => 'Cerrada'],
];
$plazos = [
    ['prioridad' => 'Baja', 'horas' => 40],
    ['prioridad' => 'Media', 'horas' => 24],
    ['prioridad' => 'Alta', 'horas' => 8],
    ['prioridad' => 'Urgente', 'horas' => 4],
];
?>
<section class="seccion" id="como-funciona" aria-labelledby="como-funciona-titulo">
    <div class="contenedor">
        <h2 class="seccion__titulo" id="como-funciona-titulo">Cómo funciona</h2>
        <p class="seccion__intro">Cada solicitud recorre cuatro pasos. En cualquier momento puede ver en cuál está.</p>
        <ol class="pasos">
            <?php foreach ($pasos as $paso): ?>
                <li class="paso">
                    <h3 class="paso__titulo"><?= $this->e($paso['titulo']) ?></h3>
                    <p class="paso__quien"><?= $this->e($paso['quien']) ?></p>
                    <p class="paso__texto"><?= $this->e($paso['texto']) ?></p>
                    <p class="paso__estado">Estado: <span class="insignia"><?= $this->e($paso['estado']) ?></span></p>
                </li>
            <?php endforeach; ?>
        </ol>
        <div class="como-funciona__extra">
            <p class="aviso">Puede <strong>cancelar</strong> su solicitud mientras esté <em>Registrada</em> o <em>Asignada</em>, indicando el motivo. Las solicitudes nunca se borran: queda el historial de cada cambio.</p>
            <div class="tabla-envoltura">
                <table class="tabla tabla--compacta">
                    <caption>Plazo máximo de atención según la prioridad</caption>
                    <thead>
                        <tr>
                            <th scope="col">Prioridad</th>
                            <th scope="col">Plazo (horas laborales)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plazos as $plazo): ?>
                            <tr>
                                <th scope="row"><?= $this->e($plazo['prioridad']) ?></th>
                                <td class="numero"><?= $this->e($plazo['horas']) ?> h</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
