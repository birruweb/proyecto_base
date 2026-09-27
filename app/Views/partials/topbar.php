<?php
use App\Core\View;

$u = usuario_actual();
?>
<header class="topbar">
    <button class="btn btn-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Abrir menú">
        <i class="bi bi-list"></i>
    </button>

    <div class="flex-grow-1 min-w-0">
        <h1 class="h5 mb-0 text-truncate"><?= e($titulo) ?></h1>
        <?php View::parcial('partials/breadcrumb', ['titulo' => $titulo, 'migas' => $migas ?? []]) ?>
    </div>

    <div class="dropdown">
        <button class="btn btn-light d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="avatar"><?= e(mb_strtoupper(mb_substr($u['nombre'], 0, 1))) ?></span>
            <span class="d-none d-sm-block text-start lh-sm">
                <span class="d-block fw-semibold small"><?= e($u['nombre']) ?></span>
                <span class="d-block text-body-secondary small-2"><?= e($u['rol']) ?></span>
            </span>
            <i class="bi bi-chevron-down small text-body-secondary"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li><a class="dropdown-item" href="<?= url('perfil') ?>"><i class="bi bi-person me-2"></i>Mi perfil</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="post" action="<?= url('logout') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button>
                </form>
            </li>
        </ul>
    </div>
</header>
