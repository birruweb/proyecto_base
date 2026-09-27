<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Modulo;

/**
 * Administración del menú: grupos raíz, módulos raíz y submódulos.
 *
 * Registrar un módulo aquí crea la opción del menú y sus permisos,
 * pero el código (controlador, vista, JS y rutas) se crea aparte.
 */
final class ModulosController extends Controller
{
    /** Módulos que usa el propio sistema: no se eliminan ni se les cambia la clave */
    private const DEL_SISTEMA = ['usuarios', 'roles', 'modulos'];

    private Modulo $modulos;

    public function __construct()
    {
        $this->modulos = new Modulo();
    }

    public function index(): void
    {
        $this->view('modulos/index', [
            'titulo'  => 'Módulos',
            'sistema' => self::DEL_SISTEMA,
            'scripts' => ['js/modulos/modulos.js'],
        ]);
    }

    public function listar(): void
    {
        $this->json(['data' => $this->modulos->listado()]);
    }

    public function crear(): void
    {
        $datos = $this->validarModulo();
        $id = $this->modulos->crear($datos);
        $this->ok('Módulo creado', ['id' => $id]);
    }

    public function actualizar(): void
    {
        $id = $this->id();
        $actual = $this->modulos->buscar($id) ?? Response::abort(404, 'El módulo no existe');
        $datos = $this->validarModulo($id, $actual);

        $this->modulos->actualizar($id, $datos);
        $this->ok('Módulo actualizado');
    }

    public function eliminar(): void
    {
        $id = $this->id();
        $modulo = $this->modulos->buscar($id) ?? Response::abort(404, 'El módulo no existe');

        if (in_array($modulo['clave'], self::DEL_SISTEMA, true)) {
            $this->error('Este módulo es parte del sistema y no se puede eliminar.', 409);
        }
        $hijos = $this->modulos->contarHijos($id);
        if ($hijos > 0) {
            $this->error("Este grupo tiene $hijos submódulo(s). Muévelos o elimínalos primero.", 409);
        }

        $this->modulos->eliminar($id);   // sus permisos se borran en cascada
        $this->ok('Módulo eliminado');
    }

    /**
     * Valida según el tipo:
     *   grupo -> sin ruta ni padre
     *   raiz  -> con ruta, sin padre
     *   hijo  -> con ruta y con un grupo como padre
     */
    private function validarModulo(?int $id = null, ?array $actual = null): array
    {
        $datos = $this->validar([
            'tipo'     => 'required|in:grupo,raiz,hijo',
            'nombre'   => 'required|max:100',
            'clave'    => ['required', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:modulos,clave' . ($id ? ",$id" : '')],
            'icono'    => ['required', 'max:50', 'regex:/^bi-[a-z0-9-]+$/'],
            'ruta'     => ['nullable', 'max:100', 'regex:/^\/?[a-z0-9_\-\/]+$/'],
            'padre_id' => 'nullable|integer',
            'orden'    => 'required|integer|min:0|max:9999',
            'activo'   => 'required|in:0,1',
        ]);

        $tipo = $datos['tipo'];
        unset($datos['tipo']);

        $errores = [];
        $ruta = $datos['ruta'] === null ? null : trim($datos['ruta'], '/');

        if ($tipo === 'grupo') {
            $ruta = null;
            $datos['padre_id'] = null;
        } else {
            if ($ruta === null || $ruta === '') {
                $errores['ruta'] = 'Escribe la ruta (la misma que usas en routes.php).';
            }
            if ($tipo === 'raiz') {
                $datos['padre_id'] = null;
            } elseif ($datos['padre_id'] === null) {
                $errores['padre_id'] = 'Elige el grupo al que pertenece.';
            } elseif ((int) $datos['padre_id'] === $id || !$this->modulos->esGrupo((int) $datos['padre_id'])) {
                $errores['padre_id'] = 'Elige un grupo raíz válido.';
            }
        }
        $datos['ruta'] = $ruta;

        if ($actual !== null) {
            if ($tipo !== 'grupo' && $this->modulos->contarHijos((int) $actual['id']) > 0) {
                $errores['tipo'] = 'Tiene submódulos: debe seguir siendo grupo.';
            }
            $contieneSistema = array_intersect($this->modulos->clavesHijos((int) $actual['id']), self::DEL_SISTEMA) !== [];
            if ($contieneSistema && (int) $datos['activo'] === 0) {
                $errores['activo'] = 'Este grupo contiene módulos del sistema; no se puede desactivar.';
            }
            if (in_array($actual['clave'], self::DEL_SISTEMA, true)) {
                if ($datos['clave'] !== $actual['clave']) {
                    $errores['clave'] = 'La clave de un módulo del sistema no se puede cambiar.';
                }
                if ((int) $datos['activo'] === 0) {
                    $errores['activo'] = 'Un módulo del sistema no se puede desactivar.';
                }
            }
        }

        if ($errores !== []) {
            $this->error('Revisa los datos del formulario', 422, $errores);
        }

        return $datos;
    }
}
