<?php
/** Variables: $usuario, $tarjetas */
?>
<div class="card mb-4">
    <div class="card-body d-flex align-items-center gap-3">
        <div class="avatar avatar-lg"><?= e(mb_strtoupper(mb_substr($usuario['nombre'], 0, 1))) ?></div>
        <div>
            <h2 class="h5 mb-1">¡Hola, <?= e($usuario['nombre']) ?>!</h2>
            <p class="text-body-secondary mb-0 small">
                Rol: <strong><?= e($usuario['rol']) ?></strong>
            </p>
        </div>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($tarjetas as $t): ?>
        <div class="col-sm-6 col-xl-3">
            <a href="<?= url($t['ruta']) ?>" class="card card-stat h-100 text-decoration-none">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-<?= e($t['color']) ?>-subtle text-<?= e($t['color']) ?>-emphasis">
                        <i class="bi <?= e($t['icono']) ?>"></i>
                    </div>
                    <div>
                        <div class="text-body-secondary small"><?= e($t['titulo']) ?></div>
                        <div class="fs-3 fw-semibold text-body"><?= (int) $t['valor'] ?></div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>

    <?php if ($tarjetas === []): ?>
        <div class="col-12">
            <div class="alert alert-light border mb-0">Tu rol todavía no tiene módulos asignados.</div>
        </div>
    <?php endif; ?>
</div>
