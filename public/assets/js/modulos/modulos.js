/**
 * Administración del menú: grupos, módulos raíz y submódulos.
 */
$(function () {
    const $tabla = $('#tablaModulos');
    const puedeEditar = Number($tabla.data('editar')) === 1;
    const puedeEliminar = Number($tabla.data('eliminar')) === 1;
    const delSistema = String($tabla.data('sistema')).split(',');

    const form = document.getElementById('formModulo');
    const modal = new bootstrap.Modal('#modalModulo');

    const tipos = {
        grupo: { badge: '<span class="badge text-bg-dark">Grupo</span>', ayuda: 'Encabezado desplegable del menú. No tiene ruta: solo agrupa submódulos.' },
        raiz: { badge: '<span class="badge text-bg-primary">Módulo raíz</span>', ayuda: 'Enlace directo en el menú, fuera de cualquier grupo.' },
        hijo: { badge: '<span class="badge text-bg-light border">Submódulo</span>', ayuda: 'Enlace dentro de un grupo.' },
    };

    // ---- Grupos colapsados: sus submódulos no se muestran ----
    const colapsados = new Set();

    DataTable.ext.search.push((settings, datos, indice, fila) => {
        if (settings.nTable.id !== 'tablaModulos' || fila.padre_id === null) return true;
        if (tabla.search() !== '') return true;   // al buscar se muestra todo
        return !colapsados.has(Number(fila.padre_id));
    });

    /** Flecha para colapsar un grupo con submódulos; un espacio igual en las demás raíces para alinear */
    function flecha(fila) {
        if (fila.tipo === 'hijo') return '';
        if (fila.tipo !== 'grupo' || Number(fila.hijos) === 0) {
            return '<span class="d-inline-block me-1" style="width: 1rem"></span>';
        }
        const cerrado = colapsados.has(Number(fila.id));
        return `<button type="button" class="btn btn-link btn-sm p-0 me-1 text-body-secondary btn-colapsar"
                        data-id="${Number(fila.id)}" aria-expanded="${!cerrado}"
                        title="${cerrado ? 'Mostrar submódulos' : 'Ocultar submódulos'}" style="width: 1rem">
                    <i class="bi bi-chevron-${cerrado ? 'right' : 'down'}"></i>
                </button>`;
    }

    // ---- Listado (el servidor ya lo manda ordenado como árbol) ----
    const tabla = App.tabla('#tablaModulos', {
        ajax: App.url('modulos/listar'),
        ordering: false,
        pageLength: 25,
        columns: [
            {
                data: 'nombre',
                render: (dato, tipo, fila) => {
                    if (tipo !== 'display') return dato;
                    const rama = fila.tipo === 'hijo' ? '<span class="text-body-tertiary ms-3 me-1">└</span>' : '';
                    const peso = fila.tipo === 'grupo' ? 'fw-semibold' : '';
                    return `${flecha(fila)}${rama}<i class="bi ${App.escapar(fila.icono)} me-2"></i><span class="${peso}">${App.escapar(dato)}</span>`;
                },
            },
            { data: 'tipo', render: (dato, tipo) => (tipo === 'display' ? tipos[dato].badge : dato) },
            { data: 'clave', render: (dato, tipo) => (tipo === 'display' ? `<code>${App.escapar(dato)}</code>` : dato) },
            { data: 'ruta', render: App.render.texto },
            {
                data: 'orden', className: 'text-center',
                // En negritas solo el de los grupos, para distinguir su orden del de sus submódulos
                render: (dato, tipo, fila) => (tipo === 'display' && fila.tipo === 'grupo' ? `<strong>${Number(dato)}</strong>` : dato),
            },
            { data: 'activo', render: App.render.estado },
            {
                data: null, orderable: false, searchable: false, className: 'text-end text-nowrap',
                render: (dato, tipo, fila) => App.botonesAccion(puedeEditar, puedeEliminar && !delSistema.includes(fila.clave)),
            },
        ],
    });

    // ---- Colapsar / expandir un grupo ----
    $tabla.on('click', '.btn-colapsar', function () {
        const id = Number($(this).data('id'));
        if (colapsados.has(id)) {
            colapsados.delete(id);
        } else {
            colapsados.add(id);
        }
        const cerrado = colapsados.has(id);

        $(this).attr('aria-expanded', String(!cerrado))
            .attr('title', cerrado ? 'Mostrar submódulos' : 'Ocultar submódulos')
            .find('i').attr('class', `bi bi-chevron-${cerrado ? 'right' : 'down'}`);
        tabla.draw(false);   // false: se queda en la misma página
    });

    // ---- Ayudantes del formulario ----
    function tipoActual() {
        return $(form).find('[name="tipo"]:checked').val();
    }

    /** Muestra u oculta ruta y grupo según el tipo elegido */
    function aplicarTipo() {
        const tipo = tipoActual();
        $('#campoRuta').toggleClass('d-none', tipo === 'grupo');
        $('#campoPadre').toggleClass('d-none', tipo !== 'hijo');
        $('#ayudaTipo').text(tipos[tipo].ayuda);
    }

    /** Llena el select de grupos con los que hay en la tabla */
    function llenarGrupos(excluirId = null) {
        const $select = $('#m_padre').empty().append(new Option('Selecciona un grupo...', ''));
        tabla.rows().data().toArray()
            .filter((m) => m.tipo === 'grupo' && m.id !== excluirId)
            .forEach((g) => $select.append(new Option(g.nombre, g.id)));
    }

    /** "Control de Acceso" -> "control_de_acceso" */
    function aClave(texto) {
        return texto.normalize('NFD').replace(/[̀-ͯ]/g, '')
            .toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/^[0-9_]+/, '');
    }

    function avisarCambioMenu() {
        $('#avisoMenu').removeClass('d-none');
    }

    // Vista previa del ícono
    $('#m_icono').on('input', function () {
        $('#iconoPreview').attr('class', 'bi ' + this.value.trim());
    });

    // Sugerir clave y ruta a partir del nombre (solo al crear y si no las han tocado)
    $('#m_clave, #m_ruta').on('input', function () {
        $(this).data('tocado', true);
    });
    $('#m_nombre').on('input', function () {
        if (form.elements.id.value) return;
        const clave = aClave(this.value);
        if (!$('#m_clave').data('tocado')) $('#m_clave').val(clave).trigger('change');
        if (!$('#m_ruta').data('tocado')) $('#m_ruta').val(clave.replace(/_/g, '-'));
    });
    $('#m_clave').on('input change', function () {
        $('#ejemploClave').text((this.value || 'clave') + '.ver');
    });

    $(form).on('change', '[name="tipo"]', aplicarTipo);

    // ---- Nuevo ----
    $('#btnNuevo').on('click', function () {
        App.formulario.limpiar(form);
        $('#m_clave, #m_ruta').data('tocado', false);
        llenarGrupos();
        $('#m_icono').trigger('input');
        $('#m_clave').trigger('change');
        aplicarTipo();
        $('#modalModuloTitulo').text('Nuevo módulo');
        modal.show();
    });

    // ---- Editar ----
    $tabla.on('click', '.btn-editar', function () {
        const fila = tabla.row($(this).closest('tr')).data();
        App.formulario.limpiar(form);
        llenarGrupos(fila.id);
        App.formulario.cargar(form, fila);
        $('#m_icono').trigger('input');
        $('#m_clave').trigger('change');
        aplicarTipo();
        $('#modalModuloTitulo').text('Editar módulo');
        modal.show();
    });

    // ---- Guardar ----
    $(form).on('submit', function (e) {
        e.preventDefault();
        const ruta = form.elements.id.value ? 'modulos/actualizar' : 'modulos/crear';

        App.formulario.enviar(form, ruta).done((r) => {
            modal.hide();
            App.alerta.ok(r.mensaje);
            tabla.ajax.reload(null, false);
            avisarCambioMenu();
        });
    });

    // ---- Eliminar ----
    $tabla.on('click', '.btn-eliminar', async function () {
        const fila = tabla.row($(this).closest('tr')).data();
        if (!(await App.confirmar(`Se eliminará "${fila.nombre}" y sus permisos.`))) return;

        App.post('modulos/eliminar', { id: fila.id }).done((r) => {
            App.alerta.ok(r.mensaje);
            tabla.ajax.reload(null, false);
            avisarCambioMenu();
        });
    });

    $('#btnRecargar').on('click', () => window.location.reload());
});
