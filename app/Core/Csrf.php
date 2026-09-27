<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Protección CSRF: cada POST debe traer el token de la sesión,
 * ya sea en el campo _token (formularios) o en el header X-CSRF-TOKEN (AJAX).
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verificar(): void
    {
        $enviado = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (is_string($enviado) && $enviado !== '' && hash_equals(self::token(), $enviado)) {
            return;
        }

        if (Request::esAjax()) {
            Response::abort(419);
        }

        // Formulario normal (p. ej. login con la página abierta mucho tiempo)
        flash('warning', 'La página expiró. Intenta de nuevo.');
        Response::redirect(Auth::check() ? '' : 'login');
    }
}
