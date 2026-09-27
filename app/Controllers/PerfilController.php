<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Usuario;

final class PerfilController extends Controller
{
    public function index(): void
    {
        $this->view('perfil/index', [
            'titulo'  => 'Mi perfil',
            'usuario' => Auth::usuario(),
            'scripts' => ['js/modulos/perfil.js'],
        ]);
    }

    public function cambiarPassword(): void
    {
        $datos = $this->validar([
            'password_actual' => 'required',
            'password'        => 'required|min:8|max:72|confirmed',
        ]);

        $modelo = new Usuario();
        $usuario = $modelo->buscar((int) Auth::id());

        if (!password_verify($datos['password_actual'], $usuario['password'])) {
            $this->error('Revisa los datos del formulario', 422, [
                'password_actual' => 'La contraseña actual no es correcta.',
            ]);
        }

        $modelo->actualizar((int) Auth::id(), ['password' => password_hash($datos['password'], PASSWORD_DEFAULT)]);
        session_regenerate_id(true);

        $this->ok('Contraseña actualizada');
    }
}
