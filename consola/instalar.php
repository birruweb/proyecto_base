<?php
/**
 * Instala la base de datos del proyecto usando los datos del .env.
 *
 *   php consola/instalar.php
 *
 * Crea la base DB_NAME si no existe y ejecuta database/instalar.sql en ella.
 * Se puede correr varias veces: el SQL usa IF NOT EXISTS / INSERT IGNORE,
 * así que no borra ni duplica nada.
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

use App\Core\Env;

if (!is_file(BASE_DIR . '/.env')) {
    salir('Falta el archivo .env. Copia .env.example como .env y pon el nombre de tu base en DB_NAME.');
}
Env::cargar(BASE_DIR . '/.env');

$nombre = (string) env('DB_NAME', '');
if (!preg_match('/^[A-Za-z0-9_]+$/', $nombre)) {
    salir("DB_NAME en el .env debe tener solo letras, números y guion bajo (actual: '$nombre').");
}

$host = (string) env('DB_HOST', '127.0.0.1');
$puerto = (string) env('DB_PORT', '3306');
$opciones = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
$usuario = (string) env('DB_USER', 'root');
$clave = (string) env('DB_PASS', '');

// ---- La base: se usa si ya existe; si no, se crea ----
try {
    $pdo = new PDO("mysql:host=$host;port=$puerto;dbname=$nombre;charset=utf8mb4", $usuario, $clave, $opciones);
    echo "  = La base '$nombre' ya existía\n";
} catch (PDOException $e) {
    if ((int) $e->errorInfo[1] !== 1049) {   // 1049 = la base no existe
        salir('No se pudo conectar a MySQL. ¿Está encendido? Revisa DB_HOST, DB_USER y DB_PASS en el .env. '
            . 'Detalle: ' . $e->getMessage());
    }
    try {
        $servidor = new PDO("mysql:host=$host;port=$puerto;charset=utf8mb4", $usuario, $clave, $opciones);
        $servidor->exec("CREATE DATABASE `$nombre` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO("mysql:host=$host;port=$puerto;dbname=$nombre;charset=utf8mb4", $usuario, $clave, $opciones);
        echo "  + Base '$nombre' creada\n";
    } catch (PDOException $e) {
        salir("No se pudo crear la base '$nombre': " . $e->getMessage()
            . "\n  Si tu usuario no tiene permiso de crear bases, créala a mano y vuelve a correr este comando.");
    }
}

$tablasAntes = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

// ---- instalar.sql, sentencia por sentencia para reportar bien los errores ----
$archivo = BASE_DIR . '/database/instalar.sql';
if (!is_file($archivo)) {
    salir('No se encontró database/instalar.sql');
}
$sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($archivo));   // quita los comentarios
$sentencias = array_filter(array_map('trim', preg_split('/;\s*(?:\n|$)/', $sql)));

foreach ($sentencias as $sentencia) {
    if (preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $sentencia)) {
        continue;   // la base la decide el .env, no el archivo
    }
    try {
        $pdo->exec($sentencia);
    } catch (PDOException $e) {
        salir("Falló una sentencia de instalar.sql:\n\n" . mb_substr($sentencia, 0, 300) . "\n\n" . $e->getMessage());
    }
}

$tablasDespues = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$nuevas = array_diff($tablasDespues, $tablasAntes);

echo $nuevas === []
    ? "  = Las tablas ya existían; no se borró ni se duplicó nada\n"
    : '  + Tablas creadas: ' . implode(', ', $nuevas) . "\n";

echo in_array('usuarios', $nuevas, true)
    ? "\nListo. Abre la app y entra con admin / Admin123! (cámbiala en Mi perfil).\n"
    : "\nListo.\n";

function salir(string $mensaje): never
{
    fwrite(STDERR, "Error: $mensaje\n");
    exit(1);
}
