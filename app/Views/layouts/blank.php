<?php
/**
 * Layout mínimo para páginas de error (no consulta la base de datos).
 */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo ?? 'Error') ?> · <?= e(env('APP_NAME', 'Proyecto Base')) ?></title>
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="auth-body">
<main class="auth-wrap">
    <?= $contenido ?>
</main>
</body>
</html>
