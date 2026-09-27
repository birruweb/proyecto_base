<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;

final class AuthController extends Controller
{
    public function formulario(): void
    {
        if (Auth::check()) {
            $this->redirect('');
        }
        $this->view('auth/login', ['titulo' => 'Iniciar sesión'], 'auth');
    }

    /** Login con formulario normal + redirección (patrón PRG) */
    public function login(): void
    {
        $usuario = trim((string) $this->input('usuario', ''));
        $password = (string) $this->input('password', '');

        $error = ($usuario === '' || $password === '')
            ? 'Escribe tu usuario y contraseña.'
            : Auth::intentar($usuario, $password);

        if ($error !== null) {
            flash('danger', $error);
            set_old(['usuario' => $usuario]);
            $this->redirect('login');
        }

        $destino = $_SESSION['_destino'] ?? '';
        unset($_SESSION['_destino']);
        $this->redirect($destino);
    }

    public function logout(): void
    {
        Auth::logout();
        Session::iniciar();
        flash('success', 'Cerraste sesión correctamente.');
        $this->redirect('login');
    }
}
