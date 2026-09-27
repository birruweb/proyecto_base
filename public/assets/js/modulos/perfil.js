$(function () {
    const form = document.getElementById('formPassword');

    $(form).on('submit', function (e) {
        e.preventDefault();

        App.formulario.enviar(form, 'perfil/password').done((r) => {
            form.reset();
            App.alerta.ok(r.mensaje);
        });
    });
});
