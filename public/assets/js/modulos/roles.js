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

    /**
     * Pone cada interruptor "Todos" según sus checkboxes:
     * el de un módulo, encendido si tiene todas sus acciones;
     * el de un grupo, encendido si todos sus submódulos lo están.
     */
    function sincronizarTodos() {
        $(form).find('.permiso-todos').each(function () {
            const $acciones = $(form).find(`.permiso[data-modulo="${$(this).data('modulo')}"]`);
            this.checked = $acciones.length > 0 && $acciones.not(':checked').length === 0;
        });
        $(form).find('.grupo-todos').each(function () {
            const $modulos = $(form).find(`.permiso-todos[data-grupo="${$(this).data('grupo')}"]`);
            this.checked = $modulos.length > 0 && $modulos.not(':checked').length === 0;
        });
    }

    // Crear/editar implican "ver"; quitar "ver" quita todo
    $(form).on('change', '.permiso', function () {
        const modulo = $(this).data('modulo');
        const $fila = $(form).find(`.permiso[data-modulo="${modulo}"]`);

        if ($(this).data('accion') === 'ver' && !this.checked) {
            $fila.prop('checked', false);
        } else if (this.checked) {
            $fila.filter('[data-accion="ver"]').prop('checked', true);
        }
        sincronizarTodos();
    });

    // "Todos" de un módulo: marca o desmarca ver, crear y editar de una vez
    $(form).on('change', '.permiso-todos', function () {
        $(form).find(`.permiso[data-modulo="${$(this).data('modulo')}"]`).prop('checked', this.checked);
        sincronizarTodos();
    });

    // "Todos" de un grupo: lo mismo para cada submódulo del grupo
    $(form).on('change', '.grupo-todos', function () {
        $(form).find(`.permiso-todos[data-grupo="${$(this).data('grupo')}"]`).each((i, interruptor) => {
            $(form).find(`.permiso[data-modulo="${$(interruptor).data('modulo')}"]`).prop('checked', this.checked);
        });
        sincronizarTodos();
    });

    $('#btnNuevo').on('click', function () {
        App.formulario.limpiar(form);
        sincronizarTodos();
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
            sincronizarTodos();

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
