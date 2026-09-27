<?php
/** Variables: $codigo, $mensaje, $excepcion (solo con APP_DEBUG=true) */
?>
<div class="text-center error-page">
    <div class="error-code"><?= (int) $codigo ?></div>
    <p class="text-body-secondary mb-4"><?= e($mensaje) ?></p>
    <a href="<?= url() ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Ir al inicio</a>

    <?php if (!empty($excepcion)): ?>
        <div class="text-start mt-4">
            <div class="alert alert-warning small mb-2">
                Detalle visible porque <code>APP_DEBUG=true</code>. En producción ponlo en <code>false</code>.
            </div>
            <pre class="error-trace"><?= e($excepcion::class . ': ' . $excepcion->getMessage()) ?>

<?= e($excepcion->getFile() . ':' . $excepcion->getLine()) ?>

<?= e($excepcion->getTraceAsString()) ?></pre>
        </div>
    <?php endif; ?>
</div>
