<?php
/** Variables: $roles */
?>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h2 class="h6 mb-0">Listado de usuarios</h2>
        <?php if (puede('usuarios.crear')): ?>
            <button type="button" class="btn btn-primary btn-sm text-nowrap" id="btnNuevo">
                <i class="bi bi-plus-lg me-1"></i>Nuevo usuario
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="tablaUsuarios" class="table table-hover align-middle w-100"
               data-editar="<?= puede('usuarios.editar') ? 1 : 0 ?>"
               data-eliminar="<?= puede('usuarios.eliminar') ? 1 : 0 ?>">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Usuario</th>
                <th>Correo</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Último acceso</th>
                <th class="text-end">Acciones</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="modalUsuario" tabindex="-1" aria-labelledby="modalUsuarioTitulo" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="formUsuario" novalidate autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title" id="modalUsuarioTitulo">Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id">

                <div class="mb-3">
                    <label class="form-label" for="u_nombre">Nombre completo</label>
                    <input type="text" class="form-control" id="u_nombre" name="nombre" maxlength="100">
                    <div class="invalid-feedback"></div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="u_usuario">Usuario</label>
                        <input type="text" class="form-control" id="u_usuario" name="usuario" maxlength="50">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="u_email">Correo <span class="text-body-secondary small">(opcional)</span></label>
                        <input type="email" class="form-control" id="u_email" name="email" maxlength="150">
                        <div class="invalid-feedback"></div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="u_rol">Rol</label>
                        <select class="form-select" id="u_rol" name="rol_id">
                            <option value="">Selecciona...</option>
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?= (int) $rol['id'] ?>"><?= e($rol['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="u_activo">Estado</label>
                        <select class="form-select" id="u_activo" name="activo">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>

                <div>
                    <label class="form-label" for="u_password">Contraseña</label>
                    <input type="password" class="form-control" id="u_password" name="password" autocomplete="new-password">
                    <div class="invalid-feedback"></div>
                    <div class="form-text" id="ayudaPassword">Mínimo 8 caracteres.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>
