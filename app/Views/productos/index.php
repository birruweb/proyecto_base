<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h2 class="h6 mb-0">Catálogo de productos</h2>
        <div class="d-flex gap-2">
            <a href="<?= url('productos/exportar') ?>" class="btn btn-outline-success btn-sm text-nowrap">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar
            </a>
            <?php if (puede('productos.crear')): ?>
                <button type="button" class="btn btn-primary btn-sm text-nowrap" id="btnNuevo">
                    <i class="bi bi-plus-lg me-1"></i>Nuevo producto
                </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <table id="tablaProductos" class="table table-hover align-middle w-100"
               data-editar="<?= puede('productos.editar') ? 1 : 0 ?>"
               data-eliminar="<?= puede('productos.eliminar') ? 1 : 0 ?>">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Descripción</th>
                <th class="text-end">Precio</th>
                <th class="text-end">Stock</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="modalProducto" tabindex="-1" aria-labelledby="modalProductoTitulo" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="formProducto" novalidate autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title" id="modalProductoTitulo">Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id">

                <div class="mb-3">
                    <label class="form-label obligatorio" for="p_nombre">Nombre</label>
                    <input type="text" class="form-control" id="p_nombre" name="nombre" maxlength="150">
                    <div class="invalid-feedback"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="p_descripcion">Descripción <span class="text-body-secondary small">(opcional)</span></label>
                    <textarea class="form-control" id="p_descripcion" name="descripcion" rows="2" maxlength="1000"></textarea>
                    <div class="invalid-feedback"></div>
                </div>

                <div class="row g-3">
                    <div class="col-sm-4">
                        <label class="form-label obligatorio" for="p_precio">Precio</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="p_precio" name="precio" min="0" step="0.01">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label obligatorio" for="p_stock">Stock</label>
                        <input type="number" class="form-control" id="p_stock" name="stock" min="0" step="1">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label obligatorio" for="p_activo">Estado</label>
                        <select class="form-select" id="p_activo" name="activo">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-body-secondary me-auto"><span class="text-danger">*</span> Obligatorio</small>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>
