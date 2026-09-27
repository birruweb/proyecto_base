/**
 * Módulo de ejemplo: Productos.
 * Este archivo es la plantilla a copiar para un CRUD nuevo.
 */
$(function () {
    const $tabla = $('#tablaProductos');
    const puedeEditar = Number($tabla.data('editar')) === 1;
    const puedeEliminar = Number($tabla.data('eliminar')) === 1;

    const form = document.getElementById('formProducto');
    const modal = new bootstrap.Modal('#modalProducto');

    // ---- Listado ----
    const tabla = App.tabla('#tablaProductos', {
        ajax: App.url('productos/listar'),
        order: [[0, 'asc']],
        columns: [
            { data: 'nombre', render: App.render.texto },
            { data: 'descripcion', render: App.render.texto },
            { data: 'precio', render: App.render.moneda, className: 'text-end' },
            { data: 'stock', className: 'text-end' },
            { data: 'activo', render: App.render.estado },
            {
                data: null, orderable: false, searchable: false, className: 'text-end text-nowrap',
                render: () => App.botonesAccion(puedeEditar, puedeEliminar),
            },
        ],
    });

    // ---- Nuevo ----
    $('#btnNuevo').on('click', function () {
        App.formulario.limpiar(form);
        $('#modalProductoTitulo').text('Nuevo producto');
        modal.show();
    });

    // ---- Editar ----
    $tabla.on('click', '.btn-editar', function () {
        const fila = tabla.row($(this).closest('tr')).data();
        App.formulario.limpiar(form);
        App.formulario.cargar(form, fila);
        $('#modalProductoTitulo').text('Editar producto');
        modal.show();
    });

    // ---- Guardar (crear o actualizar según haya id) ----
    $(form).on('submit', function (e) {
        e.preventDefault();
        const ruta = form.elements.id.value ? 'productos/actualizar' : 'productos/crear';

        App.formulario.enviar(form, ruta).done((r) => {
            modal.hide();
            App.alerta.ok(r.mensaje);
            tabla.ajax.reload(null, false);
        });
    });

    // ---- Eliminar ----
    $tabla.on('click', '.btn-eliminar', async function () {
        const fila = tabla.row($(this).closest('tr')).data();
        if (!(await App.confirmar(`Se eliminará el producto "${fila.nombre}".`))) return;

        App.post('productos/eliminar', { id: fila.id }).done((r) => {
            App.alerta.ok(r.mensaje);
            tabla.ajax.reload(null, false);
        });
    });
});
