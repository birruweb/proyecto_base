<?php
/** Variables: $modulos */
$acciones = ['ver' => 'Ver', 'crear' => 'Crear', 'editar' => 'Editar', 'eliminar' => 'Eliminar'];
?>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h2 class="h6 mb-0">Roles del sistema</h2>
        <?php if (puede('roles.crear')): ?>
            <button type="button" class="btn btn-primary btn-sm text-nowrap" id="btnNuevo">
                <i class="bi bi-plus-lg me-1"></i>Nuevo rol
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="tablaRoles" class="table table-hover align-middle w-100"
               data-editar="<?= puede('roles.editar') ? 1 : 0 ?>"
               data-eliminar="<?= puede('roles.eliminar') ? 1 : 0 ?>">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Descripción</th>
                <th class="text-center">Usuarios</th>
                <th>Tipo</th>
                <th class="text-end">Acciones</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="modalRol" tabindex="-1" aria-labelledby="modalRolTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="formRol" novalidate autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRolTitulo">Rol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id">

                <div class="row g-3 mb-4">
                    <div class="col-md-5">
                        <label class="form-label" for="r_nombre">Nombre</label>
                        <input type="text" class="form-control" id="r_nombre" name="nombre" maxlength="50">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label" for="r_descripcion">Descripción <span class="text-body-secondary small">(opcional)</span></label>
                        <input type="text" class="form-control" id="r_descripcion" name="descripcion" maxlength="255">
                        <div class="invalid-feedback"></div>
                    </div>
                </div>

                <h6 class="mb-2">Permisos por módulo</h6>

                <div class="alert alert-info d-none" id="avisoSuperadmin">
                    <i class="bi bi-info-circle me-1"></i>
                    Este es el rol de administrador: tiene acceso total a todos los módulos y sus permisos no se editan.
                </div>

                <div class="table-responsive" id="matrizPermisos">
                    <table class="table table-sm table-bordered align-middle mb-1">
                        <thead class="table-light">
                        <tr>
                            <th>Módulo</th>
                            <?php foreach ($acciones as $etiqueta): ?>
                                <th class="text-center" style="width: 90px"><?= $etiqueta ?></th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        // Fila con checkboxes de un módulo que tiene ruta
                        $fila = function (array $m, bool $esHijo) use ($acciones): void { ?>
                            <tr>
                                <td class="<?= $esHijo ? 'ps-4' : '' ?>">
                                    <i class="bi <?= e($m['icono']) ?> me-2 text-body-secondary"></i><?= e($m['nombre']) ?>
                                </td>
                                <?php foreach ($acciones as $accion => $etiqueta): ?>
                                    <td class="text-center">
                                        <input class="form-check-input permiso" type="checkbox" value="1"
                                               name="permisos[<?= (int) $m['id'] ?>][<?= $accion ?>]"
                                               data-modulo="<?= (int) $m['id'] ?>" data-accion="<?= $accion ?>"
                                               aria-label="<?= e($etiqueta . ' ' . $m['nombre']) ?>">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php };

                        foreach ($modulos as $m):
                            if ($m['ruta'] !== null) {
                                $fila($m, false);           // módulo raíz
                                continue;
                            }
                            if ($m['hijos'] === []) {
                                continue;                   // grupo vacío: nada que permitir
                            } ?>
                            <tr class="table-light">
                                <td colspan="5" class="fw-semibold small text-uppercase text-body-secondary">
                                    <i class="bi <?= e($m['icono']) ?> me-2"></i><?= e($m['nombre']) ?>
                                </td>
                            </tr>
                            <?php foreach ($m['hijos'] as $hijo) {
                                $fila($hijo, true);         // submódulos del grupo
                            }
                        endforeach; ?>
                        </tbody>
                    </table>
                    <div class="form-text">Al marcar crear, editar o eliminar se marca "ver" automáticamente.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>
