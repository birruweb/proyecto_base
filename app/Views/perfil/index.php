<?php
/** Variables: $usuario */
?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="avatar avatar-lg"><?= e(mb_strtoupper(mb_substr($usuario['nombre'], 0, 1))) ?></div>
                    <div>
                        <h2 class="h5 mb-0"><?= e($usuario['nombre']) ?></h2>
                        <span class="badge text-bg-primary"><?= e($usuario['rol']) ?></span>
                    </div>
                </div>
                <dl class="row mb-0 small">
                    <dt class="col-5 text-body-secondary fw-normal">Usuario</dt>
                    <dd class="col-7"><?= e($usuario['usuario']) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal">Correo</dt>
                    <dd class="col-7"><?= e($usuario['email'] ?: '—') ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal">Último acceso</dt>
                    <dd class="col-7 mb-0"><?= e(fecha($usuario['ultimo_acceso'])) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h2 class="h6 mb-0">Cambiar contraseña</h2></div>
            <div class="card-body">
                <form id="formPassword" novalidate>
                    <div class="mb-3">
                        <label class="form-label" for="password_actual">Contraseña actual</label>
                        <input type="password" class="form-control" id="password_actual" name="password_actual" autocomplete="current-password">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="password">Nueva contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" autocomplete="new-password">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="password_confirmation">Confirmar nueva</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Actualizar contraseña</button>
                </form>
            </div>
        </div>
    </div>
</div>
