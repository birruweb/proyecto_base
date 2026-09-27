/**
 * Utilidades globales del proyecto base.
 * Disponibles en todas las páginas como App.*
 *
 *   App.url('usuarios/listar')           -> URL completa respetando la subcarpeta
 *   App.get(ruta, datos) / App.post(...) -> AJAX con CSRF y manejo de errores
 *   App.tabla('#tabla', opciones)        -> DataTable en español
 *   App.formulario.enviar(form, ruta)    -> envía y marca errores de validación
 *   App.alerta.ok / error / aviso(msg)   -> notificaciones
 *   App.confirmar('¿Eliminar?')          -> Promise<boolean>
 */
const App = (() => {
    'use strict';

    const base = ($('meta[name="base-url"]').attr('content') || '/').replace(/\/$/, '');
    const csrf = $('meta[name="csrf-token"]').attr('content');

    // Todas las peticiones AJAX llevan el token CSRF
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrf } });

    function url(ruta = '') {
        return base + '/' + String(ruta).replace(/^\/+/, '');
    }

    function escapar(texto) {
        return String(texto ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // ---------- Alertas ----------
    const toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false, timer: 2500, timerProgressBar: true,
    });

    const alerta = {
        ok: (mensaje) => toast.fire({ icon: 'success', title: mensaje }),
        aviso: (mensaje) => toast.fire({ icon: 'warning', title: mensaje }),
        error: (mensaje) => Swal.fire({ icon: 'error', title: 'Algo salió mal', text: mensaje, confirmButtonColor: '#0d6efd' }),
    };

    function confirmar(texto, boton = 'Sí, eliminar') {
        return Swal.fire({
            title: '¿Estás seguro?',
            text: texto,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: boton,
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            reverseButtons: true,
            focusCancel: true,
        }).then((r) => r.isConfirmed);
    }

    // ---------- Manejo central de errores AJAX (incluye DataTables) ----------
    $(document).ajaxError((evento, xhr) => {
        if (xhr.statusText === 'abort') return;
        const r = xhr.responseJSON || {};

        switch (xhr.status) {
            case 401:
                window.location.href = url('login');
                break;
            case 419:
                Swal.fire({ icon: 'warning', title: 'Sesión expirada', text: r.mensaje, confirmButtonText: 'Recargar' })
                    .then(() => window.location.reload());
                break;
            case 422:
                alerta.aviso(r.mensaje || 'Revisa los datos del formulario');
                break;
            case 0:
                alerta.error('No hay conexión con el servidor.');
                break;
            default:
                alerta.error(r.mensaje || `Error inesperado (${xhr.status}).`);
        }
    });

    function peticion(metodo, ruta, datos) {
        return $.ajax({ url: url(ruta), method: metodo, data: datos, dataType: 'json' });
    }

    // ---------- DataTables ----------
    DataTable.ext.errMode = 'none'; // los errores se muestran con ajaxError

    const idiomaTabla = {
        processing: 'Procesando...',
        search: '',
        searchPlaceholder: 'Buscar...',
        lengthMenu: '_MENU_ por página',
        info: '_START_ a _END_ de _TOTAL_',
        infoEmpty: 'Sin registros',
        infoFiltered: '(de _MAX_)',
        loadingRecords: 'Cargando...',
        zeroRecords: 'No se encontraron resultados',
        emptyTable: 'No hay registros',
        paginate: { first: '«', previous: '‹', next: '›', last: '»' },
    };

    function tabla(selector, opciones = {}) {
        return new DataTable(selector, $.extend(true, {
            language: idiomaTabla,
            pageLength: 10,
            autoWidth: false,
        }, opciones));
    }

    /** Funciones "render" para columnas de DataTables */
    const render = {
        texto(dato, tipo) {
            if (tipo !== 'display') return dato ?? '';
            return dato === null || dato === '' ? '<span class="text-body-secondary">—</span>' : escapar(dato);
        },
        fecha(dato, tipo) {
            if (tipo !== 'display') return dato ?? '';
            if (!dato) return '<span class="text-body-secondary">—</span>';
            const f = new Date(String(dato).replace(' ', 'T'));
            return f.toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
        },
        /** Columnas DATE (sin hora). Se arma con T00:00 para que no se recorra un día por la zona horaria */
        soloFecha(dato, tipo) {
            if (tipo !== 'display') return dato ?? '';
            if (!dato) return '<span class="text-body-secondary">—</span>';
            return new Date(String(dato).slice(0, 10) + 'T00:00').toLocaleDateString('es-MX', { dateStyle: 'short' });
        },
        siNo(dato, tipo) {
            if (tipo !== 'display') return dato;
            return Number(dato) === 1 ? 'Sí' : 'No';
        },
        moneda(dato, tipo) {
            if (tipo !== 'display') return Number(dato);
            return Number(dato).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
        },
        estado(dato, tipo) {
            if (tipo !== 'display') return dato;
            return Number(dato) === 1
                ? '<span class="badge text-bg-success">Activo</span>'
                : '<span class="badge text-bg-secondary">Inactivo</span>';
        },
    };

    /** Botones de la columna Acciones: editar/eliminar según permisos + info (siempre, al final) */
    function botonesAccion(puedeEditar, puedeEliminar) {
        let html = '';
        if (puedeEditar) {
            html += '<button type="button" class="btn btn-sm btn-outline-primary btn-editar" title="Editar"><i class="bi bi-pencil"></i></button> ';
        }
        if (puedeEliminar) {
            html += '<button type="button" class="btn btn-sm btn-outline-danger btn-eliminar" title="Eliminar"><i class="bi bi-trash"></i></button> ';
        }
        html += '<button type="button" class="btn btn-sm btn-outline-secondary btn-info-registro" title="Información del registro"><i class="bi bi-info-circle"></i></button>';
        return html;
    }

    /** Muestra quién creó y quién modificó por última vez un registro */
    function infoRegistro(fila) {
        if (!('creado_en' in fila) || !('creado_por_nombre' in fila)) {
            alerta.aviso('Este módulo no guarda quién crea o modifica sus registros.');
            return;
        }

        const quien = (nombre) => (nombre ? escapar(nombre) : '<span class="text-body-secondary">No registrado</span>');
        const cuando = (fecha) => (fecha
            ? new Date(String(fecha).replace(' ', 'T')).toLocaleString('es-MX', { dateStyle: 'long', timeStyle: 'short' })
            : '');

        const modificado = fila.actualizado_en
            ? `<div>${quien(fila.actualizado_por_nombre)}</div><div class="text-body-secondary">${cuando(fila.actualizado_en)}</div>`
            : '<div class="text-body-secondary">Sin modificaciones</div>';

        Swal.fire({
            title: 'Información del registro',
            html: `
                <div class="text-start">
                    <div class="d-flex gap-3 mb-3">
                        <i class="bi bi-plus-circle fs-4 text-success"></i>
                        <div>
                            <div class="fw-semibold">Creado por</div>
                            <div>${quien(fila.creado_por_nombre)}</div>
                            <div class="text-body-secondary">${cuando(fila.creado_en)}</div>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <i class="bi bi-pencil-square fs-4 text-primary"></i>
                        <div>
                            <div class="fw-semibold">Última modificación</div>
                            ${modificado}
                        </div>
                    </div>
                </div>`,
            confirmButtonText: 'Cerrar',
            confirmButtonColor: '#0d6efd',
            customClass: { title: 'fs-4' },
        });
    }

    // Un solo manejador para el botón de info de TODAS las tablas del sistema
    $(document).on('click', '.btn-info-registro', function () {
        const tabla = $(this).closest('table').DataTable();
        infoRegistro(tabla.row($(this).closest('tr')).data());
    });

    // ---------- Formularios ----------
    const formulario = {
        limpiarErrores(form) {
            $(form).find('.is-invalid').removeClass('is-invalid');
            $(form).find('.invalid-feedback').text('');
            $(form).find('[data-error]').removeClass('d-block');
        },

        limpiar(form) {
            form.reset();
            $(form).find('input[type="hidden"]').not('[name="_token"]').val('');
            formulario.limpiarErrores(form);
        },

        /** Rellena el formulario con un objeto { campo: valor } (los password se omiten) */
        cargar(form, datos) {
            $.each(datos, (campo, valor) => {
                const $campo = $(form).find(`[name="${campo}"]`);
                if (!$campo.length || $campo.attr('type') === 'password') return;
                if ($campo.attr('type') === 'checkbox') {
                    $campo.prop('checked', Number(valor) === 1);
                } else if ($campo.attr('type') === 'radio') {
                    $campo.filter(`[value="${valor}"]`).prop('checked', true);
                } else if ($campo.attr('type') === 'datetime-local' && valor) {
                    $campo.val(String(valor).replace(' ', 'T'));   // MySQL usa espacio; el input, T
                } else {
                    $campo.val(valor === null ? '' : String(valor));
                }
            });
        },

        /**
         * Marca en rojo los campos con error: { campo: 'mensaje' }
         * El mensaje va en el .invalid-feedback junto al campo, o en [data-error="campo"]
         */
        errores(form, errores) {
            $.each(errores || {}, (campo, mensaje) => {
                const $campo = $(form).find(`[name="${campo}"]`).addClass('is-invalid');
                const $propio = $(form).find(`[data-error="${campo}"]`);
                if ($propio.length) {
                    $propio.text(mensaje).addClass('d-block');
                } else {
                    $campo.siblings('.invalid-feedback').first().text(mensaje);
                }
            });
            $(form).find('.is-invalid').first().trigger('focus');
        },

        /** Envía el formulario por POST; devuelve la promesa de jQuery */
        enviar(form, ruta) {
            const $boton = $(form).find('[type="submit"]');
            const textoOriginal = $boton.html();

            formulario.limpiarErrores(form);
            $boton.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-1"></span>Guardando...');

            return peticion('POST', ruta, $(form).serialize())
                .fail((xhr) => {
                    if (xhr.status === 422) formulario.errores(form, xhr.responseJSON?.errores);
                })
                .always(() => $boton.prop('disabled', false).html(textoOriginal));
        },
    };

    // Al teclear en un campo con error se quita la marca
    $(document).on('input change', '.is-invalid', function () {
        $(this).removeClass('is-invalid');
    });

    return {
        url,
        escapar,
        get: (ruta, datos) => peticion('GET', ruta, datos),
        post: (ruta, datos) => peticion('POST', ruta, datos),
        alerta,
        confirmar,
        tabla,
        render,
        botonesAccion,
        infoRegistro,
        formulario,
    };
})();
