<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Configuración segura de la sesión PHP y cierre por inactividad.
 */
final class Session
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_name((string) env('SESSION_NAME', 'app_session'));
        session_set_cookie_params([
            'lifetime' => 0,              // se borra al cerrar el navegador
            'path'     => Request::base() ?: '/',   // solo esta app: /cliente-a no ve la cookie de /cliente-b
            'httponly' => true,           // JavaScript no puede leer la cookie
            'secure'   => Request::esHttps(),
            'samesite' => 'Lax',
        ]);
        session_start();

        // Varias apps en el mismo servidor comparten la carpeta de sesiones de PHP:
        // una sesión creada por otra app (aunque traigan el mismo id) no vale aquí
        $app = hash('sha256', BASE_DIR);
        if ($_SESSION !== [] && ($_SESSION['_app'] ?? null) !== $app) {
            session_regenerate_id(false);   // id nuevo; la sesión ajena se deja intacta para su app
            $_SESSION = [];
        }
        $_SESSION['_app'] = $app;

        // Cierre por inactividad (minutos en SESSION_LIFETIME)
        $limite = (int) env('SESSION_LIFETIME', 120) * 60;
        $ultima = $_SESSION['_ultima_actividad'] ?? null;

        if ($ultima !== null && time() - $ultima > $limite && isset($_SESSION['usuario_id'])) {
            $_SESSION = [];
            session_regenerate_id(true);
            flash('warning', 'Tu sesión se cerró por inactividad.');
        }

        $_SESSION['_ultima_actividad'] = time();
    }
}
