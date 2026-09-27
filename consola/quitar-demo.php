<?php
/**
 * Quita el módulo de ejemplo Productos de un proyecto nuevo.
 *
 *   php consola/quitar-demo.php
 *
 * Borra sus 4 archivos, sus rutas, la tarjeta de Inicio, su parte de
 * database/instalar.sql, el registro del menú (con sus permisos) y la tabla.
 * Se puede correr varias veces: lo que ya no existe se salta.
 *
 * Opciones:
 *   --si            No pregunta antes de borrar.
 *   --solo-codigo   No toca la base de datos (solo archivos).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_DIR', dirname(__DIR__));
define('APP_DIR', BASE_DIR . '/app');

spl_autoload_register(static function (string $clase): void {
    if (str_starts_with($clase, 'App\\')) {
        $archivo = APP_DIR . '/' . str_replace('\\', '/', substr($clase, 4)) . '.php';
        if (is_file($archivo)) {
            require $archivo;
        }
    }
});
require APP_DIR . '/Core/helpers.php';

use App\Core\Database;
use App\Core\Env;

$op = array_flip(array_slice($argv, 1));
$soloCodigo = isset($op['--solo-codigo']);

echo "Se quitará el módulo de ejemplo Productos:\n"
   . "  - Modelo, controlador, vista y JS\n"
   . "  - Sus rutas en app/routes.php y su tarjeta en Inicio\n"
   . "  - Su tabla y datos de ejemplo en database/instalar.sql\n"
   . ($soloCodigo ? '' : "  - Su registro en el menú, sus permisos y la tabla productos CON SUS DATOS\n");

if (!isset($op['--si'])) {
    echo "\n¿Continuar? Escribe si: ";
    if (strtolower(trim((string) fgets(STDIN))) !== 'si') {
        echo "Cancelado. No se borró nada.\n";
        exit(0);
    }
}
echo "\n";

// ---- Archivos ----
foreach ([
    'app/Models/Producto.php',
    'app/Controllers/ProductosController.php',
    'app/Views/productos/index.php',
    'public/assets/js/modulos/productos.js',
] as $relativo) {
    $archivo = BASE_DIR . '/' . $relativo;
    if (is_file($archivo)) {
        unlink($archivo);
        paso(true, "Borrado $relativo");
    } else {
        paso(null, "$relativo ya no existía");
    }
}
$carpeta = APP_DIR . '/Views/productos';
if (is_dir($carpeta) && count(scandir($carpeta)) === 2) {
    rmdir($carpeta);
}

// ---- Rutas: el use, el comentario del bloque y las 5 rutas ----
editar('app/routes.php', 'Rutas de Productos', [
    '/^use App\\\\Controllers\\\\ProductosController;\n/m' => '',
    '/\n*^\/\/ -+ Productos\b.*\n/m'                        => "\n",
    '/^\$router->.*ProductosController::class.*\n?/m'       => '',
], 'ProductosController');

// ---- Inicio: la tarjeta de Productos ----
editar('app/Controllers/DashboardController.php', 'Tarjeta de Productos en Inicio', [
    '/^use App\\\\Models\\\\Producto;\n/m'                           => '',
    '/^[ \t]*if \(puede\(\'productos\.ver\'\)\) \{\n.*?^[ \t]*\}\n/ms' => '',
], 'Producto');

// ---- instalar.sql: que las instalaciones nuevas ya no traigan Productos ----
editar('database/instalar.sql', 'Productos en database/instalar.sql', [
    // CREATE TABLE productos con su comentario
    '/^-- -+ Productos.*\n(?:.*\n)*?\).*;\n\n/m'                          => '',
    // Su fila en el INSERT de modulos (el ; pasa a la fila anterior)
    '/,\n[ \t]*\(\d+,[^\n]*\'productos\'[^\n]*\);/'                        => ';',
    // Los permisos sobre Productos (su bloque propio) y los productos de ejemplo
    '/^-- Permisos de Productos.*\nINSERT IGNORE INTO permisos .*\n(?:[ \t]+\(.*\n)+\n/m' => '',
    '/^INSERT IGNORE INTO productos .*\n(?:[ \t]+\(.*\n)+\n/m'              => '',
    // El rol Consulta se queda, pero ya no habla de productos
    "/'Solo puede ver productos'/"                                          => "'Rol de ejemplo: asígnale los permisos que necesites'",
], '/\bproductos\b/', true);

// ---- Base de datos ----
if ($soloCodigo) {
    paso(null, 'Base de datos sin tocar (--solo-codigo)');
} else {
    try {
        Env::cargar(BASE_DIR . '/.env');
        $pdo = Database::conexion();

        $borrados = $pdo->exec("DELETE FROM modulos WHERE clave = 'productos'");
        paso($borrados > 0 ? true : null, $borrados > 0
            ? 'Módulo quitado del menú (y sus permisos)'
            : 'El módulo ya no estaba en el menú');

        $existe = $pdo->query("SHOW TABLES LIKE 'productos'")->fetchColumn() !== false;
        if ($existe) {
            $pdo->exec('DROP TABLE productos');
            paso(true, 'Tabla productos eliminada');
        } else {
            paso(null, 'La tabla productos ya no existía');
        }
    } catch (\Throwable $e) {
        paso(false, 'Base de datos: ' . $e->getMessage());
    }
}

echo "\nListo. Si el grupo Catálogos quedó vacío no aparece en el menú;\n"
   . "úsalo para tus módulos o bórralo en Administración -> Módulos.\n";

// ==========================================================

/**
 * Aplica reemplazos (regex => texto) a un archivo. Si después sigue
 * mencionando $rastro, avisa para que se revise a mano.
 */
function editar(string $relativo, string $descripcion, array $reemplazos, string $rastro, bool $rastroEsRegex = false): void
{
    $archivo = BASE_DIR . '/' . $relativo;
    if (!is_file($archivo)) {
        paso(false, "$relativo no existe");
        return;
    }

    $original = (string) file_get_contents($archivo);
    $nuevo = preg_replace(array_keys($reemplazos), array_values($reemplazos), $original);
    $nuevo = preg_replace("/\n{3,}/", "\n\n", (string) $nuevo);

    if ($nuevo !== $original) {
        file_put_contents($archivo, $nuevo);
    }

    $queda = $rastroEsRegex ? preg_match($rastro, $nuevo) === 1 : str_contains($nuevo, $rastro);
    if ($queda) {
        paso(false, "$descripcion: no se pudo quitar todo, revisa $relativo a mano");
    } else {
        paso($nuevo !== $original ? true : null, ($nuevo !== $original ? 'Quitado: ' : 'Ya no estaba: ') . $descripcion);
    }
}

/** true = hecho, null = ya estaba, false = requiere atención */
function paso(?bool $estado, string $mensaje): void
{
    echo match ($estado) {
        true  => '  + ',
        null  => '  = ',
        false => '  ! ',
    } . $mensaje . "\n";
}
