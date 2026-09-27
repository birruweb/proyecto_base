$(function () {
    const $tabla = $('#tablaUsuarios');
    const puedeEditar = Number($tabla.data('editar')) === 1;
    const puedeEliminar = Number($tabla.data('eliminar')) === 1;

    const form = document.getElementById('formUsuario');
    const modal = new bootstrap.Modal('#modalUsuario');

    const tabla = App.tabla('#tablaUsuarios', {
        ajax: App.url('usuarios/listar'),
        order: [[0, 'asc']],
        columns: [
            { data: 'nombre', render: App.render.texto },
            { data: 'usuario', render: App.render.texto },
            { data: 'email', render: App.render.texto },
            { data: 'rol', render: App.render.texto },
            {
                data: 'activo',
                render: (dato, tipo, fila) => {
                    if (tipo !== 'display') return dato;
                    let html = App.render.estado(dato, tipo);
                    if (Number(fila.bloqueado) === 1) {
                        html += ' <span class="badge text-bg-danger" title="Demasiados intentos fallidos. Edítalo y guarda para desbloquear.">Bloqueado</span>';
                    }
                    return html;
                },
            },
            { data: 'ultimo_acceso', render: App.render.fecha },
            {
                data: null, orderable: false, searchable: false, className: 'text-end text-nowrap',
                render: () => App.botonesAccion(puedeEditar, puedeEliminar),
            },
        ],
    });

    function abrir(titulo, esNuevo) {
        $('#modalUsuarioTitulo').text(titulo);
        $('#ayudaPassword').text(esNuevo ? 'Mínimo 8 caracteres.' : 'Déjala vacía para no cambiarla.');
        modal.show();
    }

    $('#btnNuevo').on('click', function () {
        App.formulario.limpiar(form);
        abrir('Nuevo usuario', true);
    });

    $tabla.on('click', '.btn-editar', function () {
        const fila = tabla.row($(this).closest('tr')).data();
        App.formulario.limpiar(form);
        App.formulario.cargar(form, fila);
        abrir('Editar usuario', false);
    });

    $(form).on('submit', function (e) {
        e.preventDefault();
        const ruta = form.elements.id.value ? 'usuarios/actualizar' : 'usuarios/crear';

        App.formulario.enviar(form, ruta).done((r) => {
            modal.hide();
            App.alerta.ok(r.mensaje);
            tabla.ajax.reload(null, false);
        });
    });

    $tabla.on('click', '.btn-eliminar', async function () {
        const fila = tabla.row($(this).closest('tr')).data();
        if (!(await App.confirmar(`Se eliminará al usuario "${fila.usuario}".`))) return;

        App.post('usuarios/eliminar', { id: fila.id }).done((r) => {
            App.alerta.ok(r.mensaje);
            tabla.ajax.reload(null, false);
        });
    });
});
