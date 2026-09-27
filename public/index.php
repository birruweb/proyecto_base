<?php
/**
 * Punto de entrada único (front controller).
 *
 * Todas las peticiones llegan aquí gracias al .htaccess (Apache/XAMPP)
 * o al try_files (nginx). Desde aquí se carga la app y se despacha la ruta.
 */
declare(strict_types=1);

// Servidor embebido de PHP (php -S): deja pasar los archivos estáticos
if (PHP_SAPI === 'cli-server') {
    $archivo = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($archivo)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

$router = new App\Core\Router();
require APP_DIR . '/routes.php';
$router->despachar();
