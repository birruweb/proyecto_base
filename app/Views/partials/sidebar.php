<?php
/**
 * Menú lateral: se arma con la tabla "modulos".
 *   - Módulo raíz  -> enlace directo
 *   - Grupo raíz   -> encabezado desplegable con sus submódulos
 * Solo se muestra lo que el usuario tiene permiso de ver; un grupo
 * sin submódulos visibles no aparece.
 */
use App\Models\Modulo;
?>
<aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-label="Menú principal">
    <div class="sidebar-header">
        <a href="<?= url() ?>" class="sidebar-brand">
            <i class="bi bi-grid-1x2-fill"></i>
            <span><?= e(env('APP_NAME', 'Proyecto Base')) ?></span>
        </a>
        <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas"
                data-bs-target="#sidebar" aria-label="Cerrar"></button>
    </div>

    <nav class="sidebar-nav">
        <a class="sidebar-link <?= ruta_activa('/') ? 'active' : '' ?>" href="<?= url() ?>">
            <i class="bi bi-speedometer2"></i> Inicio
        </a>

        <?php foreach ((new Modulo())->menu() as $modulo): ?>

            <?php if ($modulo['ruta'] !== null): ?>
                <?php if (!puede($modulo['clave'] . '.ver')) continue; ?>
                <a class="sidebar-link <?= ruta_activa($modulo['ruta']) ? 'active' : '' ?>" href="<?= url($modulo['ruta']) ?>">
                    <i class="bi <?= e($modulo['icono']) ?>"></i> <?= e($modulo['nombre']) ?>
                </a>

            <?php else:
                $hijos = array_filter($modulo['hijos'], fn($h) => puede($h['clave'] . '.ver'));
                if ($hijos === []) continue;
                $abierto = array_filter($hijos, fn($h) => ruta_activa($h['ruta'])) !== [];
                $idMenu = 'menu-' . (int) $modulo['id'];
            ?>
                <button type="button" class="sidebar-link sidebar-grupo <?= $abierto ? '' : 'collapsed' ?>"
                        data-bs-toggle="collapse" data-bs-target="#<?= $idMenu ?>"
                        aria-expanded="<?= $abierto ? 'true' : 'false' ?>" aria-controls="<?= $idMenu ?>">
                    <i class="bi <?= e($modulo['icono']) ?>"></i>
                    <span class="flex-grow-1 text-start"><?= e($modulo['nombre']) ?></span>
                    <i class="bi bi-chevron-down sidebar-flecha"></i>
                </button>
                <div class="collapse <?= $abierto ? 'show' : '' ?>" id="<?= $idMenu ?>">
                    <div class="sidebar-sub">
                        <?php foreach ($hijos as $hijo): ?>
                            <a class="sidebar-link <?= ruta_activa($hijo['ruta']) ? 'active' : '' ?>" href="<?= url($hijo['ruta']) ?>">
                                <i class="bi <?= e($hijo['icono']) ?>"></i> <?= e($hijo['nombre']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endforeach; ?>
    </nav>
</aside>
