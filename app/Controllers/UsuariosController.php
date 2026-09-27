<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\Rol;
use App\Models\Usuario;

final class UsuariosController extends Controller
{
    private Usuario $usuarios;

    public function __construct()
    {
        $this->usuarios = new Usuario();
    }

    public function index(): void
    {
        $this->view('usuarios/index', [
            'titulo'  => 'Usuarios',
            'roles'   => (new Rol())->todos('nombre'),
            'scripts' => ['js/modulos/usuarios.js'],
        ]);
    }

    /** JSON para DataTables */
    public function listar(): void
    {
        $this->json(['data' => $this->usuarios->listado()]);
    }

    public function crear(): void
    {
        $datos = $this->validar($this->reglas());
        $datos['password'] = password_hash($datos['password'], PASSWORD_DEFAULT);

        $id = $this->usuarios->crear($datos);
        $this->ok('Usuario creado', ['id' => $id]);
    }

    public function actualizar(): void
    {
        $id = $this->id();
        $actual = $this->usuarios->buscar($id) ?? Response::abort(404, 'El usuario no existe');
        $datos = $this->validar($this->reglas($id));

        // Reglas de negocio que no son de formato
        $errores = [];
        if ($id === Auth::id()) {
            if ((int) $datos['activo'] === 0) {
                $errores['activo'] = 'No puedes desactivar tu propio usuario.';
            }
            if ((int) $datos['rol_id'] !== (int) $actual['rol_id']) {
                $errores['rol_id'] = 'No puedes cambiar tu propio rol.';
            }
        }
        if ($errores === [] && $this->dejaSinAdministrador($actual, $datos)) {
            $errores['rol_id'] = 'Debe quedar al menos un administrador activo.';
        }
        if ($errores !== []) {
            $this->error('Revisa los datos del formulario', 422, $errores);
        }

        if ($datos['password'] === null) {
            unset($datos['password']);          // vacío = no cambiar
        } else {
            $datos['password'] = password_hash($datos['password'], PASSWORD_DEFAULT);
        }

        // Al guardar desde aquí también se desbloquea la cuenta
        $datos['intentos_fallidos'] = 0;
        $datos['bloqueado_hasta'] = null;

        $this->usuarios->actualizar($id, $datos);
        $this->ok('Usuario actualizado');
    }

    public function eliminar(): void
    {
        $id = $this->id();
        $usuario = $this->usuarios->buscar($id) ?? Response::abort(404, 'El usuario no existe');

        if ($id === Auth::id()) {
            $this->error('No puedes eliminar tu propio usuario.', 409);
        }
        if ($this->dejaSinAdministrador($usuario, ['activo' => 0, 'rol_id' => $usuario['rol_id']])) {
            $this->error('No puedes eliminar al único administrador activo.', 409);
        }

        $this->usuarios->eliminar($id);
        $this->ok('Usuario eliminado');
    }

    private function reglas(?int $id = null): array
    {
        $ignorar = $id ? ",$id" : '';

        return [
            'nombre'   => 'required|max:100',
            'usuario'  => ['required', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', "unique:usuarios,usuario$ignorar"],
            'email'    => "nullable|email|max:150|unique:usuarios,email$ignorar",
            'rol_id'   => 'required|integer|exists:roles,id',
            'password' => ($id ? 'nullable' : 'required') . '|min:8|max:72',
            'activo'   => 'required|in:0,1',
        ];
    }

    /** ¿El cambio dejaría al sistema sin ningún administrador activo? */
    private function dejaSinAdministrador(array $actual, array $nuevo): bool
    {
        $roles = new Rol();
        $eraAdmin = (int) $actual['activo'] === 1 && (int) ($roles->buscar((int) $actual['rol_id'])['es_superadmin'] ?? 0) === 1;
        $seraAdmin = (int) $nuevo['activo'] === 1 && (int) ($roles->buscar((int) $nuevo['rol_id'])['es_superadmin'] ?? 0) === 1;

        return $eraAdmin && !$seraAdmin && $this->usuarios->superadminsActivos((int) $actual['id']) === 0;
    }
}
