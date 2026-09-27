$(function () {
    const $tabla = $('#tablaRoles');
    const puedeEditar = Number($tabla.data('editar')) === 1;
    const puedeEliminar = Number($tabla.data('eliminar')) === 1;

    const form = document.getElementById('formRol');
    const modal = new bootstrap.Modal('#modalRol');

    const tabla = App.tabla('#tablaRoles', {
        ajax: App.url('roles/listar'),
        order: [],
        columns: [
            { data: 'nombre', render: App.render.texto },
            { data: 'descripcion', render: App.render.texto },
            { data: 'usuarios', className: 'text-center' },
            {
                data: 'es_superadmin',
                render: (dato, tipo) => tipo !== 'display' ? dato : (Number(dato) === 1
                    ? '<span class="badge text-bg-dark">Acceso total</span>'
                    : '<span class="badge text-bg-light border">Por permisos</span>'),
            },
            {
                data: null, orderable: false, searchable: false, className: 'text-end text-nowrap',
                render: (dato, tipo, fila) => App.botonesAccion(puedeEditar, puedeEliminar && Number(fila.es_superadmin) !== 1),
            },
        ],
    });

    function modoSuperadmin(activo) {
        $('#avisoSuperadmin').toggleClass('d-none', !activo);
        $('#matrizPermisos').toggleClass('d-none', activo);
    }

    // Crear/editar/eliminar implican "ver"; quitar "ver" quita todo
    $(form).on('change', '.permiso', function () {
        const modulo = $(this).data('modulo');
        const $fila = $(form).find(`.permiso[data-modulo="${modulo}"]`);

        if ($(this).data('accion') === 'ver' && !this.checked) {
            $fila.prop('checked', false);
        } else if (this.checked) {
            $fila.filter('[data-accion="ver"]').prop('checked', true);
        }
    });

    $('#btnNuevo').on('click', function () {
        App.formulario.limpiar(form);
        modoSuperadmin(false);
        $('#modalRolTitulo').text('Nuevo rol');
        modal.show();
    });

    $tabla.on('click', '.btn-editar', function () {
        const fila = tabla.row($(this).closest('tr')).data();

        App.get('roles/permisos', { id: fila.id }).done((r) => {
            App.formulario.limpiar(form);
            App.formulario.cargar(form, r.rol);

            $.each(r.permisos, (moduloId, acciones) => {
                $.each(acciones, (accion, valor) => {
                    $(form).find(`.permiso[data-modulo="${moduloId}"][data-accion="${accion}"]`)
                        .prop('checked', Number(valor) === 1);
                });
            });

            modoSuperadmin(Number(r.rol.es_superadmin) === 1);
            $('#modalRolTitulo').text('Editar rol');
            modal.show();
        });
    });

    $(form).on('submit', function (e) {
        e.preventDefault();
        const ruta = form.elements.id.value ? 'roles/actualizar' : 'roles/crear';

        App.formulario.enviar(form, ruta).done((r) => {
            modal.hide();
            App.alerta.ok(r.mensaje);
            tabla.ajax.reload(null, false);
        });
    });

    $tabla.on('click', '.btn-eliminar', async function () {
        const fila = tabla.row($(this).closest('tr')).data();
        if (!(await App.confirmar(`Se eliminará el rol "${fila.nombre}".`))) return;

        App.post('roles/eliminar', { id: fila.id }).done((r) => {
            App.alerta.ok(r.mensaje);
            tabla.ajax.reload(null, false);
        });
    });
});
