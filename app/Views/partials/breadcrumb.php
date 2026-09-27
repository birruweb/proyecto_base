<?php
/**
 * Migas de pan dinámicas: Inicio › Grupo › Módulo › (extras).
 *
 * El grupo y el módulo salen de la tabla "modulos" según la ruta actual,
 * así que un módulo nuevo las tiene sin hacer nada. Si la página no es
 * un módulo registrado (ej. Mi perfil), se usa el $titulo.
 *
 * Para agregar niveles desde el controlador (ej. una página de detalle):
 *   $this->view('productos/detalle', [
 *       'titulo' => 'Detalle',
 *       'migas'  => [['texto' => 'Detalle']],   // 'ruta' opcional para que sea enlace
 *   ]);
 *
 * Variables: $titulo, $migas
 */
use App\Core\Request;
use App\Models\Modulo;

$ruta = Request::ruta();
if ($ruta === '/') {
    return;   // En Inicio no hace falta
}

$items = (new Modulo())->rastro($ruta);
if ($items === []) {
    $items[] = ['texto' => $titulo ?? '', 'ruta' => null];
}
foreach ($migas ?? [] as $miga) {
    $items[] = $miga + ['ruta' => null];
}
$ultimo = array_key_last($items);
?>
<nav aria-label="Ruta de navegación">
    <ol class="breadcrumb breadcrumb-app mb-0">
        <li class="breadcrumb-item">
            <a href="<?= url() ?>"><i class="bi bi-house-door"></i><span class="visually-hidden">Inicio</span></a>
        </li>
        <?php foreach ($items as $i => $item): ?>
            <?php if ($i === $ultimo): ?>
                <li class="breadcrumb-item active" aria-current="page"><?= e($item['texto']) ?></li>
            <?php elseif (!empty($item['ruta'])): ?>
                <li class="breadcrumb-item"><a href="<?= url($item['ruta']) ?>"><?= e($item['texto']) ?></a></li>
            <?php else: ?>
                <li class="breadcrumb-item"><?= e($item['texto']) ?></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>
