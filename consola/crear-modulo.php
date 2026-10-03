<?php
/**
 * Generador de módulos CRUD a partir de una tabla que ya existe en la BD.
 *
 *   php consola/crear-modulo.php clientes --grupo=catalogos --icono=bi-people
 *
 * Lee las columnas de la tabla y crea, siguiendo el patrón de Productos:
 *   app/Models/Cliente.php
 *   app/Controllers/ClientesController.php
 *   app/Views/clientes/index.php
 *   public/assets/js/modulos/clientes.js
 * Además agrega las 5 rutas en app/routes.php y registra el módulo en el menú.
 *
 * Opciones:
 *   --grupo=clave          Grupo del menú donde va. Sin él queda como módulo raíz.
 *   --nombre="Texto"       Nombre en el menú y título. Por defecto sale de la tabla.
 *   --singular=palabra     Singular de la tabla si no lo adivina bien (paises -> pais).
 *   --icono=bi-algo        Icono de https://icons.getbootstrap.com (por defecto bi-circle).
 *   --femenino | --masculino  Para los textos: "Nueva marca" / "Nuevo cliente".
 *   --forzar               Sobrescribe los archivos si ya existen.
 *
 * Reconoce: textos, textos largos, enteros, decimales, activo/booleanos (tinyint(1)),
 * fechas, fecha y hora, horas, enum, columnas UNIQUE y llaves foráneas (se vuelven select).
 * El código generado es un punto de partida: revísalo y ajústalo a tu gusto.
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

if (!is_file(BASE_DIR . '/.env')) {
    salir('Falta el archivo .env.');
}
Env::cargar(BASE_DIR . '/.env');
date_default_timezone_set((string) env('APP_TIMEZONE', 'America/Mazatlan'));

// ==========================================================
// Argumentos
// ==========================================================
$tabla = null;
$op = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $arg, $m)) {
        $op[$m[1]] = $m[2] ?? true;
    } elseif ($tabla === null) {
        $tabla = $arg;
    } else {
        salir("Argumento no reconocido: $arg");
    }
}

if ($tabla === null || isset($op['ayuda'])) {
    echo "Uso: php consola/crear-modulo.php <tabla> [--grupo=clave] [--nombre=\"Texto\"] [--singular=palabra]\n"
       . "                                         [--icono=bi-algo] [--femenino|--masculino] [--forzar]\n";
    exit($tabla === null ? 1 : 0);
}

if (!preg_match('/^[a-z][a-z0-9_]*$/', $tabla)) {
    salir('El nombre de la tabla solo puede llevar minúsculas, números y guion bajo.');
}
if (in_array($tabla, ['usuarios', 'roles', 'modulos', 'permisos'], true)) {
    salir("La tabla $tabla es del sistema y ya tiene su módulo.");
}

$singular = isset($op['singular']) ? (string) $op['singular'] : singular($tabla);
if (!preg_match('/^[a-z][a-z0-9_]*$/', $singular)) {
    salir('--singular solo puede llevar minúsculas, números y guion bajo.');
}
$icono = isset($op['icono']) ? (string) $op['icono'] : 'bi-circle';
if (!preg_match('/^bi-[a-z0-9-]+$/', $icono)) {
    salir('El icono debe tener la forma bi-nombre (ej. bi-people).');
}
$femenino = isset($op['femenino']) ? true : (isset($op['masculino']) ? false : esFemenino($singular));
$forzar = isset($op['forzar']);

// ==========================================================
// Nombres derivados
// ==========================================================
$nombre      = isset($op['nombre']) ? trim((string) $op['nombre']) : etiqueta($tabla);
$modelo      = studly($singular);                 // Cliente
$controlador = studly($tabla) . 'Controller';     // ClientesController
$variable    = lcfirst(studly($tabla));           // clientes
$sufijoId    = studly($singular);                 // modalCliente / formCliente
$sufijoTabla = studly($tabla);                    // tablaClientes
$texto       = mb_strtolower(etiqueta($singular)); // cliente
$el          = $femenino ? 'la' : 'el';
$nuevo       = $femenino ? 'Nueva' : 'Nuevo';
$o           = $femenino ? 'a' : 'o';              // creado / creada

// ==========================================================
// Estructura de la tabla
// ==========================================================
$pdo = Database::conexion();
$bd = (string) env('DB_NAME');

$columnas = consulta($pdo,
    'SELECT COLUMN_NAME AS col, DATA_TYPE AS tipo, COLUMN_TYPE AS tipo_completo, IS_NULLABLE AS nulo,
            CHARACTER_MAXIMUM_LENGTH AS largo, CHARACTER_OCTET_LENGTH AS bytes,
            NUMERIC_PRECISION AS precision_, NUMERIC_SCALE AS escala, COLUMN_KEY AS llave, EXTRA AS extra
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
   ORDER BY ORDINAL_POSITION',
    [$bd, $tabla]
);
if ($columnas === []) {
    salir("La tabla '$tabla' no existe en la base de datos '$bd'. Créala primero.");
}

$llavePrimaria = array_column(array_filter($columnas, fn($c) => $c['llave'] === 'PRI'), 'col');
if ($llavePrimaria !== ['id']) {
    salir("La tabla debe tener una llave primaria llamada 'id' (el modelo base la usa).");
}

$foraneas = [];
foreach (consulta($pdo,
    'SELECT COLUMN_NAME AS col, REFERENCED_TABLE_NAME AS tabla, REFERENCED_COLUMN_NAME AS ref
       FROM information_schema.KEY_COLUMN_USAGE
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
    [$bd, $tabla]
) as $f) {
    $foraneas[$f['col']] = $f;
}

$nombresColumnas = array_column($columnas, 'col');
$auditoria = in_array('creado_por', $nombresColumnas, true) && in_array('actualizado_por', $nombresColumnas, true);
$omitir = ['id', 'creado_en', 'creado_por', 'actualizado_en', 'actualizado_por'];

$campos = [];
$avisos = [];
foreach ($columnas as $c) {
    if (in_array($c['col'], $omitir, true)
        || str_contains(strtoupper((string) $c['extra']), 'ON UPDATE')
        || preg_match('/(VIRTUAL|STORED|PERSISTENT) GENERATED/i', (string) $c['extra'])) {
        continue;
    }
    $campo = analizarColumna($c, $foraneas[$c['col']] ?? null, $pdo, $bd);
    if ($campo === null) {
        $avisos[] = "Columna '{$c['col']}' ({$c['tipo_completo']}) omitida: tipo no soportado, agrégala a mano.";
        continue;
    }
    $campos[] = $campo;
}
if ($campos === []) {
    salir('La tabla no tiene columnas editables.');
}

// Columna que identifica al registro: la primera de texto (para ordenar y para "¿Eliminar X?")
$principal = null;
foreach ($campos as $campo) {
    if (in_array($campo['tipo'], ['texto', 'email'], true)) {
        $principal = $campo['col'];
        break;
    }
}
$hayUnicos = array_filter($campos, fn($c) => $c['unico']) !== [];
$hayForaneas = array_filter($campos, fn($c) => $c['tipo'] === 'fk') !== [];

// ==========================================================
// Validaciones antes de escribir nada
// ==========================================================
$archivos = [
    'modelo'      => APP_DIR . "/Models/$modelo.php",
    'controlador' => APP_DIR . "/Controllers/$controlador.php",
    'vista'       => APP_DIR . "/Views/$tabla/index.php",
    'js'          => BASE_DIR . "/public/assets/js/modulos/$tabla.js",
];
if (!$forzar) {
    $existen = array_filter($archivos, 'is_file');
    if ($existen !== []) {
        salir("Ya existen estos archivos (usa --forzar para sobrescribirlos):\n  - "
            . implode("\n  - ", array_map('relativa', $existen)));
    }
}

$padreId = null;
if (isset($op['grupo'])) {
    $grupo = fila($pdo, 'SELECT id FROM modulos WHERE clave = ? AND padre_id IS NULL AND ruta IS NULL', [(string) $op['grupo']]);
    if ($grupo === null) {
        $grupos = array_column(consulta($pdo, 'SELECT clave FROM modulos WHERE padre_id IS NULL AND ruta IS NULL ORDER BY orden'), 'clave');
        salir("No existe el grupo '{$op['grupo']}'. Grupos disponibles: " . implode(', ', $grupos));
    }
    $padreId = (int) $grupo['id'];
}

// ==========================================================
// Generación
// ==========================================================
escribir($archivos['modelo'], generarModelo());
escribir($archivos['controlador'], generarControlador());
escribir($archivos['vista'], generarVista());
escribir($archivos['js'], generarJs());

$rutasAgregadas = agregarRutas();
$moduloRegistrado = registrarModulo($pdo, $padreId);

// ==========================================================
// Resumen
// ==========================================================
echo "\nMódulo '$nombre' generado (modelo $modelo, " . ($femenino ? 'femenino' : 'masculino') . ").\n\n";
foreach ($archivos as $archivo) {
    echo "  + " . relativa($archivo) . "\n";
}
echo $rutasAgregadas ? "  + app/routes.php (5 rutas)\n" : "  = app/routes.php ya tenía las rutas de /$tabla\n";
echo $moduloRegistrado ? "  + Menú: módulo '$tabla' registrado\n" : "  = Menú: el módulo '$tabla' ya estaba registrado\n";

foreach ($avisos as $aviso) {
    echo "\n  ! $aviso";
}
echo "\n\nSiguientes pasos:\n"
   . "  1. Abre " . rtrim((string) env('APP_URL', 'http://localhost/' . basename(BASE_DIR)), '/') . "/$tabla\n"
   . "  2. Para otros roles, marca los permisos en Administración -> Roles y permisos.\n"
   . "  3. Revisa los textos y el orden de los campos en la vista.\n";
if (!isset($op['singular'])) {
    echo "\n  Singular usado: '$singular'. Si no es correcto, repite con --singular=... --forzar\n";
}

// ==========================================================
// Análisis de columnas
// ==========================================================

/**
 * Convierte una columna de la BD en un campo del formulario:
 * tipo de input, regla de validación y forma de mostrarlo en la tabla.
 */
function analizarColumna(array $c, ?array $foranea, PDO $pdo, string $bd): ?array
{
    $campo = [
        'col'       => $c['col'],
        'etiqueta'  => etiqueta($c['col']),
        'requerido' => $c['nulo'] === 'NO',
        'unico'     => $c['llave'] === 'UNI',
        'unsigned'  => str_contains((string) $c['tipo_completo'], 'unsigned'),
        'max'       => null,
        'step'      => null,
        'opciones'  => [],
        'ref'       => null,
    ];

    if ($foranea !== null) {
        return ['tipo' => 'fk', 'ref' => [
            'tabla' => $foranea['tabla'],
            'col'   => $foranea['ref'],
            'texto' => columnaTexto($pdo, $bd, $foranea['tabla'], $foranea['ref']),
        ]] + $campo;
    }

    switch ($c['tipo']) {
        case 'tinyint':
            if (str_starts_with((string) $c['tipo_completo'], 'tinyint(1)')) {
                if ($c['col'] === 'activo') {
                    return ['tipo' => 'estado', 'etiqueta' => 'Estado'] + $campo;
                }
                return ['tipo' => 'booleano'] + $campo;
            }
            return ['tipo' => 'entero'] + $campo;

        case 'smallint':
        case 'mediumint':
        case 'int':
        case 'bigint':
        case 'year':
            return ['tipo' => 'entero'] + $campo;

        case 'decimal':
            $enteros = (int) $c['precision_'] - (int) $c['escala'];
            $escala = (int) $c['escala'];
            $campo['max'] = str_repeat('9', max(1, $enteros)) . ($escala > 0 ? '.' . str_repeat('9', $escala) : '');
            $campo['step'] = $escala > 0 ? '0.' . str_repeat('0', $escala - 1) . '1' : '1';
            return ['tipo' => 'decimal'] + $campo;

        case 'float':
        case 'double':
            return ['tipo' => 'decimal', 'step' => 'any'] + $campo;

        case 'char':
        case 'varchar':
            $campo['max'] = (int) $c['largo'];
            $esCorreo = preg_match('/(^|_)(email|correo)($|_)/', $c['col']) === 1;
            return ['tipo' => $esCorreo ? 'email' : 'texto'] + $campo;

        case 'tinytext':
        case 'text':
        case 'mediumtext':
        case 'longtext':
            // Los TEXT se miden en bytes; /4 garantiza que quepa aunque todo sea utf8mb4
            $campo['max'] = min(65535, intdiv((int) $c['bytes'], 4));
            return ['tipo' => 'textarea'] + $campo;

        case 'date':
            return ['tipo' => 'fecha'] + $campo;

        case 'datetime':
        case 'timestamp':
            return ['tipo' => 'fechahora'] + $campo;

        case 'time':
            return ['tipo' => 'hora'] + $campo;

        case 'enum':
            preg_match_all("/'((?:[^']|'')*)'/", (string) $c['tipo_completo'], $m);
            $opciones = array_map(fn($v) => str_replace("''", "'", $v), $m[1]);
            foreach ($opciones as $v) {
                if (str_contains($v, ',') || str_contains($v, '|')) {
                    return null;   // la regla in: no admite comas
                }
            }
            return ['tipo' => 'enum', 'opciones' => $opciones] + $campo;
    }

    return null;
}

/** Columna a mostrar de una tabla relacionada: nombre, o la primera de texto, o el id */
function columnaTexto(PDO $pdo, string $bd, string $tabla, string $ref): string
{
    $cols = consulta($pdo,
        'SELECT COLUMN_NAME AS col, DATA_TYPE AS tipo FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
        [$bd, $tabla]
    );
    foreach (['nombre', 'descripcion', 'titulo', 'clave'] as $preferida) {
        if (in_array($preferida, array_column($cols, 'col'), true)) {
            return $preferida;
        }
    }
    foreach ($cols as $c) {
        if (in_array($c['tipo'], ['varchar', 'char'], true)) {
            return $c['col'];
        }
    }
    return $ref;
}

/** Nombre de la propiedad que trae el texto de una llave foránea: marca_id -> marca_nombre */
function aliasForanea(string $col): string
{
    return preg_replace('/_id$/', '', $col) . '_nombre';
}

/** Variable con las opciones de un select: marca_id -> opcionesMarca */
function varOpciones(string $col): string
{
    return 'opciones' . studly(preg_replace('/_id$/', '', $col));
}

// ==========================================================
// Plantillas
// ==========================================================

function generarModelo(): string
{
    global $tabla, $modelo, $nombre, $campos, $hayForaneas, $auditoria, $principal;

    $lista = implode(', ', array_map(fn($c) => "'{$c['col']}'", $campos));
    $extra = '';

    if ($hayForaneas) {
        $selects = ['t.*'];
        $joins = [];
        $n = 0;
        foreach ($campos as $c) {
            if ($c['tipo'] !== 'fk') {
                continue;
            }
            $n++;
            $selects[] = "r$n.`{$c['ref']['texto']}` AS " . aliasForanea($c['col']);
            $joins[] = "          LEFT JOIN `{$c['ref']['tabla']}` r$n ON r$n.`{$c['ref']['col']}` = t.`{$c['col']}`";
        }
        if ($auditoria) {
            $selects[] = '{$this->camposAuditoria(\'t\')}';
            $joins[] = '                    {$this->joinsAuditoria(\'t\')}';
        }
        $orden = $principal ?? 'id';

        $extra .= "\n    /** Listado para la tabla, con el texto de cada relación */\n"
            . "    public function listado(): array\n"
            . "    {\n"
            . "        return \$this->consulta(\n"
            . "            \"SELECT " . implode(",\n                    ", $selects) . "\n"
            . "               FROM `$tabla` t\n"
            . implode("\n", $joins) . "\n"
            . "           ORDER BY t.`$orden`\"\n"
            . "        );\n"
            . "    }\n";

        foreach ($campos as $c) {
            if ($c['tipo'] !== 'fk') {
                continue;
            }
            $r = $c['ref'];
            $extra .= "\n    /** Opciones para el select de {$c['col']} */\n"
                . "    public function " . varOpciones($c['col']) . "(): array\n"
                . "    {\n"
                . "        return \$this->consulta('SELECT `{$r['col']}` AS valor, `{$r['texto']}` AS texto FROM `{$r['tabla']}` ORDER BY `{$r['texto']}`');\n"
                . "    }\n";
        }
    }

    return <<<PHP
<?php
declare(strict_types=1);

namespace App\\Models;

use App\\Core\\Model;

/**
 * {$nombre}. Generado con consola/crear-modulo.php
 */
final class $modelo extends Model
{
    protected string \$tabla = '$tabla';
    protected array \$campos = [$lista];
$extra}

PHP;
}

function generarControlador(): string
{
    global $tabla, $modelo, $controlador, $variable, $nombre, $texto, $el, $o, $campos, $hayUnicos, $hayForaneas, $principal;

    $El = ucfirst($el);
    $Texto = ucfirst($texto);

    // Datos para la vista
    $datosVista = ['titulo' => lit($nombre)];
    foreach ($campos as $c) {
        if ($c['tipo'] === 'fk') {
            $datosVista[varOpciones($c['col'])] = "\$this->$variable->" . varOpciones($c['col']) . '()';
        }
    }
    $datosVista['scripts'] = "['js/modulos/$tabla.js']";
    $ancho = max(array_map('strlen', array_keys($datosVista))) + 2;
    $lineasVista = '';
    foreach ($datosVista as $clave => $valor) {
        $lineasVista .= '            ' . str_pad("'$clave'", $ancho) . " => $valor,\n";
    }

    // Reglas
    $ancho = max(array_map(fn($c) => strlen($c['col']), $campos)) + 2;
    $lineasReglas = '';
    foreach ($campos as $c) {
        $regla = lit(regla($c));
        if ($c['unico']) {
            $regla .= " . (\$id ? \",\$id\" : '')";
        }
        $lineasReglas .= '            ' . str_pad("'{$c['col']}'", $ancho) . " => $regla,\n";
    }

    $listar = $hayForaneas ? 'listado()' : 'todos(' . lit($principal ?? 'id') . ')';
    $paramReglas = $hayUnicos ? 'int $id = 0' : '';
    $idReglas = $hayUnicos ? '$id' : '';
    $noExiste = lit("$El $texto no existe");
    $creado = lit("$Texto cread$o");
    $actualizado = lit("$Texto actualizad$o");
    $eliminado = lit("$Texto eliminad$o");

    return <<<PHP
<?php
declare(strict_types=1);

namespace App\\Controllers;

use App\\Core\\Controller;
use App\\Core\\Response;
use App\\Models\\$modelo;

/**
 * {$nombre}. Generado con consola/crear-modulo.php
 */
final class $controlador extends Controller
{
    private $modelo \$$variable;

    public function __construct()
    {
        \$this->$variable = new $modelo();
    }

    public function index(): void
    {
        \$this->view('$tabla/index', [
$lineasVista        ]);
    }

    public function listar(): void
    {
        \$this->json(['data' => \$this->{$variable}->$listar]);
    }

    public function crear(): void
    {
        \$datos = \$this->validar(\$this->reglas());
        \$id = \$this->{$variable}->crear(\$datos);
        \$this->ok($creado, ['id' => \$id]);
    }

    public function actualizar(): void
    {
        \$id = \$this->id();
        \$this->{$variable}->buscar(\$id) ?? Response::abort(404, $noExiste);

        \$datos = \$this->validar(\$this->reglas($idReglas));
        \$this->{$variable}->actualizar(\$id, \$datos);
        \$this->ok($actualizado);
    }

    public function eliminar(): void
    {
        \$id = \$this->id();
        \$this->{$variable}->buscar(\$id) ?? Response::abort(404, $noExiste);

        \$this->{$variable}->eliminar(\$id);
        \$this->ok($eliminado);
    }

    private function reglas($paramReglas): array
    {
        return [
$lineasReglas        ];
    }
}

PHP;
}

function generarVista(): string
{
    global $tabla, $nombre, $texto, $nuevo, $sufijoId, $sufijoTabla, $singular, $campos;

    $encabezados = '';
    foreach ($campos as $c) {
        if ($c['tipo'] === 'textarea') {
            continue;   // los textos largos no caben bien en la tabla
        }
        $clase = in_array($c['tipo'], ['entero', 'decimal'], true) ? ' class="text-end"' : '';
        $encabezados .= "                <th$clase>" . h($c['etiqueta']) . "</th>\n";
    }

    $inputs = '';
    foreach ($campos as $c) {
        $inputs .= "\n" . campoFormulario($c, $singular);
    }

    $variables = array_map(fn($c) => '$' . varOpciones($c['col']), array_filter($campos, fn($c) => $c['tipo'] === 'fk'));
    $doc = $variables === [] ? '' : "<?php\n/** Variables: " . implode(', ', $variables) . " */\n?>\n";

    $nombreH = h($nombre);
    $nuevoH = h("$nuevo $texto");
    $textoH = h(ucfirst($texto));

    return <<<HTML
{$doc}<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h2 class="h6 mb-0">$nombreH</h2>
        <?php if (puede('$tabla.crear')): ?>
            <button type="button" class="btn btn-primary btn-sm text-nowrap" id="btnNuevo">
                <i class="bi bi-plus-lg me-1"></i>$nuevoH
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="tabla$sufijoTabla" class="table table-hover align-middle w-100"
               data-editar="<?= puede('$tabla.editar') ? 1 : 0 ?>"
               data-eliminar="<?= puede('$tabla.eliminar') ? 1 : 0 ?>">
            <thead>
            <tr>
$encabezados                <th class="text-end">Acciones</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="modal$sufijoId" tabindex="-1" aria-labelledby="modal{$sufijoId}Titulo" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="form$sufijoId" novalidate autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title" id="modal{$sufijoId}Titulo">$textoH</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id">
$inputs            </div>
            <div class="modal-footer">
                <small class="text-body-secondary me-auto"><span class="text-danger">*</span> Obligatorio</small>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

HTML;
}

/** HTML de un campo del formulario según su tipo */
function campoFormulario(array $c, string $prefijo): string
{
    $id = "{$prefijo}_{$c['col']}";
    $opcional = $c['requerido'] ? '' : ' <span class="text-body-secondary small">(opcional)</span>';
    $atributos = "class=\"form-control\" id=\"$id\" name=\"{$c['col']}\"";
    $sel = "class=\"form-select\" id=\"$id\" name=\"{$c['col']}\"";
    $min = $c['unsigned'] ? ' min="0"' : '';

    $control = match ($c['tipo']) {
        'texto'     => "<input type=\"text\" $atributos maxlength=\"{$c['max']}\">",
        'email'     => "<input type=\"email\" $atributos maxlength=\"{$c['max']}\">",
        'textarea'  => "<textarea $atributos rows=\"3\" maxlength=\"{$c['max']}\"></textarea>",
        'entero'    => "<input type=\"number\" $atributos step=\"1\"$min>",
        'decimal'   => "<input type=\"number\" $atributos step=\"{$c['step']}\"$min>",
        'fecha'     => "<input type=\"date\" $atributos>",
        'fechahora' => "<input type=\"datetime-local\" $atributos>",
        'hora'      => "<input type=\"time\" $atributos>",
        'estado'    => "<select $sel>\n"
                     . "                        <option value=\"1\">Activo</option>\n"
                     . "                        <option value=\"0\">Inactivo</option>\n"
                     . "                    </select>",
        'booleano'  => "<select $sel>\n"
                     . "                        <option value=\"1\">Sí</option>\n"
                     . "                        <option value=\"0\">No</option>\n"
                     . "                    </select>",
        'enum'      => "<select $sel>\n"
                     . "                        <option value=\"\">Selecciona...</option>\n"
                     . implode('', array_map(
                         fn($v) => '                        <option value="' . h($v) . '">' . h(mb_convert_case($v, MB_CASE_TITLE)) . "</option>\n",
                         $c['opciones']
                     ))
                     . "                    </select>",
        'fk'        => "<select $sel>\n"
                     . "                        <option value=\"\">Selecciona...</option>\n"
                     . "                        <?php foreach (\$" . varOpciones($c['col']) . " as \$opcion): ?>\n"
                     . "                            <option value=\"<?= e(\$opcion['valor']) ?>\"><?= e(\$opcion['texto']) ?></option>\n"
                     . "                        <?php endforeach; ?>\n"
                     . "                    </select>",
    };

    return "                <div class=\"mb-3\">\n"
         . "                    <label class=\"form-label" . ($c['requerido'] ? ' obligatorio' : '') . "\" for=\"$id\">" . h($c['etiqueta']) . "$opcional</label>\n"
         . "                    $control\n"
         . "                    <div class=\"invalid-feedback\"></div>\n"
         . "                </div>\n";
}

function generarJs(): string
{
    global $tabla, $nombre, $texto, $el, $nuevo, $sufijoId, $sufijoTabla, $campos, $principal;

    $columnasJs = '';
    foreach ($campos as $c) {
        if ($c['tipo'] === 'textarea') {
            continue;
        }
        $columnasJs .= '            ' . columnaJs($c) . ",\n";
    }

    $identificar = $principal !== null ? " \"\${fila.$principal}\"" : '';
    $confirmar = jsTexto("Se eliminará $el $texto") . $identificar . '.';
    $nuevoJs = jsTexto("$nuevo $texto");
    $editarJs = jsTexto("Editar $texto");
    $nombreJs = jsTexto($nombre);

    return <<<JS
/**
 * Módulo: $nombreJs.
 * Generado con consola/crear-modulo.php
 */
$(function () {
    const \$tabla = $('#tabla$sufijoTabla');
    const puedeEditar = Number(\$tabla.data('editar')) === 1;
    const puedeEliminar = Number(\$tabla.data('eliminar')) === 1;

    const form = document.getElementById('form$sufijoId');
    const modal = new bootstrap.Modal('#modal$sufijoId');

    // ---- Listado ----
    const tabla = App.tabla('#tabla$sufijoTabla', {
        ajax: App.url('$tabla/listar'),
        order: [[0, 'asc']],
        columns: [
$columnasJs            {
                data: null, orderable: false, searchable: false, className: 'text-end text-nowrap',
                render: () => App.botonesAccion(puedeEditar, puedeEliminar),
            },
        ],
    });

    // ---- Nuevo ----
    $('#btnNuevo').on('click', function () {
        App.formulario.limpiar(form);
        $('#modal{$sufijoId}Titulo').text('$nuevoJs');
        modal.show();
    });

    // ---- Editar ----
    \$tabla.on('click', '.btn-editar', function () {
        const fila = tabla.row($(this).closest('tr')).data();
        App.formulario.limpiar(form);
        App.formulario.cargar(form, fila);
        $('#modal{$sufijoId}Titulo').text('$editarJs');
        modal.show();
    });

    // ---- Guardar (crear o actualizar según haya id) ----
    $(form).on('submit', function (e) {
        e.preventDefault();
        const ruta = form.elements.id.value ? '$tabla/actualizar' : '$tabla/crear';

        App.formulario.enviar(form, ruta).done((r) => {
            modal.hide();
            App.alerta.ok(r.mensaje);
            tabla.ajax.reload(null, false);
        });
    });

    // ---- Eliminar ----
    \$tabla.on('click', '.btn-eliminar', async function () {
        const fila = tabla.row($(this).closest('tr')).data();
        if (!(await App.confirmar(`$confirmar`))) return;

        App.post('$tabla/eliminar', { id: fila.id }).done((r) => {
            App.alerta.ok(r.mensaje);
            tabla.ajax.reload(null, false);
        });
    });
});

JS;
}

/** Definición de una columna de DataTables */
function columnaJs(array $c): string
{
    $esDinero = preg_match('/(precio|costo|importe|total|monto|saldo|pago|sueldo|salario|subtotal)/', $c['col']) === 1;

    return match ($c['tipo']) {
        'fk'        => "{ data: '" . aliasForanea($c['col']) . "', render: App.render.texto }",
        'estado'    => "{ data: '{$c['col']}', render: App.render.estado }",
        'booleano'  => "{ data: '{$c['col']}', render: App.render.siNo }",
        'fecha'     => "{ data: '{$c['col']}', render: App.render.soloFecha }",
        'fechahora' => "{ data: '{$c['col']}', render: App.render.fecha }",
        'entero'    => "{ data: '{$c['col']}', className: 'text-end' }",
        'decimal'   => $esDinero
            ? "{ data: '{$c['col']}', render: App.render.moneda, className: 'text-end' }"
            : "{ data: '{$c['col']}', className: 'text-end' }",
        default     => "{ data: '{$c['col']}', render: App.render.texto }",
    };
}

/** Regla de validación de un campo (sin el unique, que depende del id al editar) */
function regla(array $c): string
{
    global $tabla;

    $r = [$c['requerido'] ? 'required' : 'nullable'];
    switch ($c['tipo']) {
        case 'email':
            $r[] = 'email';
            $r[] = "max:{$c['max']}";
            break;
        case 'texto':
        case 'textarea':
            $r[] = "max:{$c['max']}";
            break;
        case 'entero':
            $r[] = 'integer';
            break;
        case 'decimal':
            $r[] = 'numeric';
            if ($c['max'] !== null) {
                $r[] = "max:{$c['max']}";
            }
            break;
        case 'estado':
        case 'booleano':
            $r[] = 'in:0,1';
            break;
        case 'fecha':
            $r[] = 'regex:/^\d{4}-\d{2}-\d{2}$/';
            break;
        case 'fechahora':
            $r[] = 'regex:/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/';
            break;
        case 'hora':
            $r[] = 'regex:/^\d{2}:\d{2}(:\d{2})?$/';
            break;
        case 'enum':
            $r[] = 'in:' . implode(',', $c['opciones']);
            break;
        case 'fk':
            $r[] = 'integer';
            $r[] = "exists:{$c['ref']['tabla']},{$c['ref']['col']}";
            break;
    }
    if ($c['unsigned'] && in_array($c['tipo'], ['entero', 'decimal'], true)) {
        array_splice($r, 2, 0, 'min:0');
    }
    if ($c['unico']) {
        $r[] = "unique:$tabla,{$c['col']}";
    }
    return implode('|', $r);
}

// ==========================================================
// Rutas y menú
// ==========================================================

/** Agrega el use y las 5 rutas al final de routes.php (si no estaban) */
function agregarRutas(): bool
{
    global $tabla, $controlador, $nombre;

    $archivo = APP_DIR . '/routes.php';
    $rutas = (string) file_get_contents($archivo);

    if (str_contains($rutas, "'/$tabla'")) {
        return false;
    }

    $use = "use App\\Controllers\\$controlador;";
    if (!str_contains($rutas, $use)) {
        // Después del último "use App\Controllers\..."
        $rutas = preg_replace_callback(
            '/(?:^use App\\\\Controllers\\\\[^\n]+\n)+/m',
            fn($m) => $m[0] . $use . "\n",
            $rutas,
            1
        );
    }

    $definiciones = [
        ['get',  '',            'index',      'ver'],
        ['get',  '/listar',     'listar',     'ver'],
        ['post', '/crear',      'crear',      'crear'],
        ['post', '/actualizar', 'actualizar', 'editar'],
        ['post', '/eliminar',   'eliminar',   'eliminar'],
    ];
    $bloque = "\n\n// ---- $nombre ----\n";
    foreach ($definiciones as [$metodo, $sufijo, $accion, $permiso]) {
        $inicio = str_pad("\$router->$metodo(", 14) . "'/$tabla$sufijo',";
        $bloque .= str_pad($inicio, 14 + strlen($tabla) + 16)
            . str_pad("[$controlador::class, '$accion'],", strlen($controlador) + 23)
            . "'$tabla.$permiso');\n";
    }

    file_put_contents($archivo, rtrim($rutas) . $bloque);
    return true;
}

/** Da de alta el módulo en el menú (al final de su grupo) */
function registrarModulo(PDO $pdo, ?int $padreId): bool
{
    global $tabla, $nombre, $icono;

    if (fila($pdo, 'SELECT id FROM modulos WHERE clave = ?', [$tabla]) !== null) {
        return false;
    }
    $orden = fila($pdo, 'SELECT COALESCE(MAX(orden), 0) + 10 AS orden FROM modulos WHERE padre_id <=> ?', [$padreId]);

    $pdo->prepare('INSERT INTO modulos (padre_id, clave, nombre, icono, ruta, orden, activo) VALUES (?, ?, ?, ?, ?, ?, 1)')
        ->execute([$padreId, $tabla, mb_substr($nombre, 0, 100), $icono, $tabla, (int) $orden['orden']]);
    return true;
}

// ==========================================================
// Utilidades
// ==========================================================

/** fecha_nacimiento -> "Fecha nacimiento", marca_id -> "Marca", descripcion -> "Descripción" */
function etiqueta(string $columna): string
{
    static $acentos = [
        'descripcion' => 'descripción', 'telefono' => 'teléfono', 'direccion' => 'dirección',
        'codigo' => 'código', 'categoria' => 'categoría', 'categorias' => 'categorías', 'numero' => 'número',
        'razon' => 'razón', 'ultimo' => 'último', 'ultima' => 'última', 'credito' => 'crédito',
        'limite' => 'límite', 'minimo' => 'mínimo', 'maximo' => 'máximo', 'genero' => 'género', 'metodo' => 'método',
        'electronico' => 'electrónico', 'dias' => 'días', 'pais' => 'país', 'paises' => 'países',
        'articulo' => 'artículo', 'articulos' => 'artículos', 'vehiculo' => 'vehículo', 'vehiculos' => 'vehículos',
        'rfc' => 'RFC', 'curp' => 'CURP', 'nss' => 'NSS', 'url' => 'URL', 'email' => 'correo',
    ];

    $palabras = explode('_', preg_replace('/_id$/', '', $columna));
    $palabras = array_map(function (string $p) use ($acentos) {
        return $acentos[$p] ?? preg_replace(['/cion$/', '/sion$/', '/ciones$/', '/siones$/'], ['ción', 'sión', 'ciones', 'siones'], $p);
    }, $palabras);

    $texto = implode(' ', $palabras);
    return mb_strtoupper(mb_substr($texto, 0, 1)) . mb_substr($texto, 1);
}

/**
 * Singular aproximado en español, palabra por palabra:
 * clientes -> cliente, proveedores -> proveedor, luces -> luz, datos_fiscales -> dato_fiscal
 */
function singular(string $tabla): string
{
    return implode('_', array_map(function (string $p): string {
        if (strlen($p) <= 3) {
            return $p;   // prefijos como "cat_" o "zz_"
        }
        if (str_ends_with($p, 'ces')) {
            return substr($p, 0, -3) . 'z';
        }
        if (preg_match('/[rlndjy]es$/', $p)) {
            return substr($p, 0, -2);
        }
        return str_ends_with($p, 's') ? substr($p, 0, -1) : $p;
    }, explode('_', $tabla)));
}

/** Heurística de género para los textos (marca, unidad, dirección -> femenino) */
function esFemenino(string $singular): bool
{
    // La primera palabra real (se salta prefijos cortos como "cat_")
    $palabras = explode('_', $singular);
    $p = current(array_filter($palabras, fn($w) => strlen($w) > 3)) ?: $palabras[0];
    if (in_array($p, ['dia', 'mapa', 'tema', 'sistema', 'problema', 'programa', 'idioma', 'clima'], true)) {
        return false;
    }
    return (bool) preg_match('/(a|dad|tad|cion|sion|tud|umbre)$/', $p);
}

function studly(string $texto): string
{
    return str_replace(' ', '', ucwords(str_replace('_', ' ', $texto)));
}

/** Texto como literal PHP entre comillas simples */
function lit(string $texto): string
{
    return "'" . str_replace("'", "\\'", $texto) . "'";
}

/** Escapa texto para HTML */
function h(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

/** Escapa texto para meterlo en una cadena JS ('...' o `...`) */
function jsTexto(string $texto): string
{
    return str_replace(['\\', "'", '`', '${'], ['\\\\', "\\'", '\\`', '\\${'], $texto);
}

function consulta(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fila(PDO $pdo, string $sql, array $params = []): ?array
{
    $filas = consulta($pdo, $sql, $params);
    return $filas[0] ?? null;
}

function escribir(string $archivo, string $contenido): void
{
    if (!is_dir(dirname($archivo))) {
        mkdir(dirname($archivo), 0775, true);
    }
    file_put_contents($archivo, $contenido);
}

function relativa(string $archivo): string
{
    return ltrim(str_replace([BASE_DIR, '\\'], ['', '/'], $archivo), '/');
}

function salir(string $mensaje): never
{
    fwrite(STDERR, "Error: $mensaje\n");
    exit(1);
}
