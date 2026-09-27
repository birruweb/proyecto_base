<?php
/**
 * Layout principal (con menú lateral). Variables: $contenido, $titulo, $scripts, $migas (opcional)
 */
use App\Core\View;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-url" content="<?= e(url()) ?>">
    <title><?= e($titulo ?? 'Inicio') ?> · <?= e(env('APP_NAME', 'Proyecto Base')) ?></title>

    <link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/datatables/dataTables.bootstrap5.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<div class="app">
    <?php View::parcial('partials/sidebar') ?>

    <div class="app-main">
        <?php View::parcial('partials/topbar', ['titulo' => $titulo ?? '', 'migas' => $migas ?? []]) ?>

        <main class="app-content">
            <?php View::parcial('partials/flash') ?>
            <?= $contenido ?>
        </main>
    </div>
</div>

<script src="<?= asset('vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('vendor/datatables/dataTables.min.js') ?>"></script>
<script src="<?= asset('vendor/datatables/dataTables.bootstrap5.min.js') ?>"></script>
<script src="<?= asset('vendor/sweetalert2/sweetalert2.all.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/utils.js') ?>"></script>
<?php foreach ($scripts ?? [] as $script): ?>
<script src="<?= asset($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
