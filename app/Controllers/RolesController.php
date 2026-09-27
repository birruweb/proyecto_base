<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;

final class RolesController extends Controller
{
    private Rol $roles;
    private Permiso $permisos;

    public function __construct()
    {
        $this->roles = new Rol();
        $this->permisos = new Permiso();
    }

    public function index(): void
    {
        $this->view('roles/index', [
            'titulo'  => 'Roles y permisos',
            'modulos' => (new Modulo())->menu(),   // árbol: grupos con sus submódulos
            'scripts' => ['js/modulos/roles.js'],
        ]);
    }

    public function listar(): void
    {
        $this->json(['data' => $this->roles->listado()]);
    }

    /** Datos de un rol con su matriz de permisos (para el modal de edición) */
    public function permisos(): void
    {
        $id = $this->id();
        $rol = $this->roles->buscar($id) ?? Response::abort(404, 'El rol no existe');

        $this->json([
            'rol'      => $rol,
            'permisos' => $this->permisos->porModulo($id),
        ]);
    }

    public function crear(): void
    {
        $datos = $this->validar($this->reglas());
        [$permisos, $editables] = $this->permisosDelFormulario();

        $id = Database::transaccion(function () use ($datos, $permisos, $editables) {
            $id = $this->roles->crear($datos + ['es_superadmin' => 0]);
            $this->permisos->reemplazar($id, $permisos, $editables);
            return $id;
        });

        $this->ok('Rol creado', ['id' => $id]);
    }

    public function actualizar(): void
    {
        $id = $this->id();
        $rol = $this->roles->buscar($id) ?? Response::abort(404, 'El rol no existe');
        $datos = $this->validar($this->reglas($id));
        [$permisos, $editables] = $this->permisosDelFormulario();

        Database::transaccion(function () use ($id, $rol, $datos, $permisos, $editables) {
            $this->roles->actualizar($id, $datos);
            // El administrador tiene acceso total: sus permisos no se tocan
            if (!(int) $rol['es_superadmin']) {
                $this->permisos->reemplazar($id, $permisos, $editables);
            }
        });

        $this->ok('Rol actualizado');
    }

    public function eliminar(): void
    {
        $id = $this->id();
        $rol = $this->roles->buscar($id) ?? Response::abort(404, 'El rol no existe');

        if ((int) $rol['es_superadmin']) {
            $this->error('El rol de administrador no se puede eliminar.', 409);
        }

        $asignados = $this->roles->usuariosAsignados($id);
        if ($asignados > 0) {
            $this->error("Este rol tiene $asignados usuario(s) asignado(s). Cámbialos de rol antes de eliminarlo.", 409);
        }

        $this->roles->eliminar($id);   // los permisos se borran en cascada
        $this->ok('Rol eliminado');
    }

    private function reglas(?int $id = null): array
    {
        return [
            'nombre'      => 'required|max:50|unique:roles,nombre' . ($id ? ",$id" : ''),
            'descripcion' => 'nullable|max:255',
        ];
    }

    /**
     * Convierte los checkboxes permisos[modulo_id][accion] en filas limpias.
     * Si puede crear o editar, automáticamente puede ver.
     * "eliminar" nunca se guarda: es exclusivo del Superadmin.
     * Devuelve [permisos, ids de los módulos que aparecen en la matriz].
     */
    private function permisosDelFormulario(): array
    {
        $modulos = (new Modulo())->conPermisos();
        $editables = array_map('intval', array_column($modulos, 'id'));

        $entrada = $_POST['permisos'] ?? [];
        if (!is_array($entrada)) {
            return [[], $editables];
        }

        $resultado = [];
        foreach ($modulos as $modulo) {
            $marcados = $entrada[$modulo['id']] ?? [];
            if (!is_array($marcados)) {
                continue;
            }

            $acciones = [];
            foreach (Permiso::ACCIONES as $accion) {
                $acciones[$accion] = isset($marcados[$accion]) ? 1 : 0;
            }
            $acciones['eliminar'] = 0;
            if ($acciones['crear'] || $acciones['editar']) {
                $acciones['ver'] = 1;
            }
            if (array_sum($acciones) > 0) {
                $resultado[(int) $modulo['id']] = $acciones;
            }
        }
        return [$resultado, $editables];
    }
}
