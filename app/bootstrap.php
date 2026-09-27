<?php
/**
 * Arranque de la aplicación: autoload, .env, errores y sesión.
 */
declare(strict_types=1);

define('BASE_DIR', dirname(__DIR__));
define('APP_DIR', __DIR__);

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Este proyecto requiere PHP 8.1 o superior. Versión actual: ' . PHP_VERSION);
}

// Autoload sin Composer: App\Core\Router  ->  app/Core/Router.php
spl_autoload_register(static function (string $clase): void {
    if (!str_starts_with($clase, 'App\\')) {
        return;
    }
    $archivo = APP_DIR . '/' . str_replace('\\', '/', substr($clase, 4)) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});

// Librerías de Composer (PhpSpreadsheet...). vendor/ no se sube a Git: cada copia corre "composer install"
if (!is_file(BASE_DIR . '/vendor/autoload.php')) {
    http_response_code(500);
    exit('Faltan las librerías de PHP. Desde la carpeta del proyecto corre: composer install');
}
require BASE_DIR . '/vendor/autoload.php';

require APP_DIR . '/Core/helpers.php';

// Variables de entorno
if (!is_file(BASE_DIR . '/.env')) {
    http_response_code(500);
    exit('Falta el archivo .env. Copia .env.example como .env y ajusta los datos de la base de datos.');
}
App\Core\Env::cargar(BASE_DIR . '/.env');

date_default_timezone_set((string) env('APP_TIMEZONE', 'America/Mazatlan'));

// Errores: nunca a pantalla; se registran en storage/logs y se muestra una página amigable
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_DIR . '/storage/logs/php-error.log');
App\Core\ErrorHandler::registrar();

// Cabeceras de seguridad básicas
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header_remove('X-Powered-By');

App\Core\Session::iniciar();
