<?php
/** Variables: $sistema (claves de módulos que no se pueden eliminar) */
?>
<div class="alert alert-info d-none" id="avisoMenu">
    <div class="d-flex align-items-center justify-content-between gap-3">
        <span><i class="bi bi-info-circle me-1"></i>Hubo cambios: el menú lateral se actualiza al recargar la página.</span>
        <button type="button" class="btn btn-sm btn-primary text-nowrap" id="btnRecargar">Recargar ahora</button>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h2 class="h6 mb-0">Menú del sistema</h2>
        <?php if (puede('modulos.crear')): ?>
            <button type="button" class="btn btn-primary btn-sm text-nowrap" id="btnNuevo">
                <i class="bi bi-plus-lg me-1"></i>Nuevo módulo
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <p class="text-body-secondary small mb-3">
            Aquí registras las opciones del menú y sus permisos. Cada módulo con ruta también necesita su código
            (controlador, vista, JS y rutas en <code>routes.php</code>).
        </p>
        <table id="tablaModulos" class="table table-hover align-middle w-100"
               data-editar="<?= puede('modulos.editar') ? 1 : 0 ?>"
               data-eliminar="<?= puede('modulos.eliminar') ? 1 : 0 ?>"
               data-sistema="<?= e(implode(',', $sistema)) ?>">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Clave</th>
                <th>Ruta</th>
                <th class="text-center">Orden</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="modalModulo" tabindex="-1" aria-labelledby="modalModuloTitulo" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="formModulo" novalidate autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title" id="modalModuloTitulo">Módulo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id">

                <label class="form-label d-block">Tipo</label>
                <div class="btn-group w-100" role="group" aria-label="Tipo de módulo">
                    <input type="radio" class="btn-check" name="tipo" id="tipo_grupo" value="grupo">
                    <label class="btn btn-outline-primary" for="tipo_grupo"><i class="bi bi-folder me-1"></i>Grupo</label>

                    <input type="radio" class="btn-check" name="tipo" id="tipo_raiz" value="raiz" checked>
                    <label class="btn btn-outline-primary" for="tipo_raiz"><i class="bi bi-link-45deg me-1"></i>Módulo raíz</label>

                    <input type="radio" class="btn-check" name="tipo" id="tipo_hijo" value="hijo">
                    <label class="btn btn-outline-primary" for="tipo_hijo"><i class="bi bi-diagram-2 me-1"></i>Submódulo</label>
                </div>
                <div class="invalid-feedback" data-error="tipo"></div>
                <div class="form-text mb-3" id="ayudaTipo"></div>

                <div class="mb-3" id="campoPadre">
                    <label class="form-label" for="m_padre">Grupo al que pertenece</label>
                    <select class="form-select" id="m_padre" name="padre_id"></select>
                    <div class="invalid-feedback"></div>
                </div>

                <div class="row g-3">
                    <div class="col-sm-7">
                        <label class="form-label" for="m_nombre">Nombre en el menú</label>
                        <input type="text" class="form-control" id="m_nombre" name="nombre" maxlength="100">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-sm-5">
                        <label class="form-label" for="m_clave">Clave</label>
                        <input type="text" class="form-control font-monospace" id="m_clave" name="clave" maxlength="50">
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="form-text mb-3">
                    La clave se usa en los permisos de <code>routes.php</code>: <code id="ejemploClave">clave.ver</code>
                </div>

                <div class="mb-3" id="campoRuta">
                    <label class="form-label" for="m_ruta">Ruta</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text text-body-secondary"><?= e(url()) ?></span>
                        <input type="text" class="form-control font-monospace" id="m_ruta" name="ruta" maxlength="100">
                        <div class="invalid-feedback"></div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="m_icono">
                            Ícono <a href="https://icons.getbootstrap.com" target="_blank" rel="noopener" class="small">ver catálogo</a>
                        </label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="bi bi-circle" id="iconoPreview"></i></span>
                            <input type="text" class="form-control font-monospace" id="m_icono" name="icono" maxlength="50" value="bi-circle">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <label class="form-label" for="m_orden">Orden</label>
                        <input type="number" class="form-control" id="m_orden" name="orden" min="0" step="10" value="10">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <label class="form-label" for="m_activo">Estado</label>
                        <select class="form-select" id="m_activo" name="activo">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>
