<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;

final class DashboardController extends Controller
{
    public function index(): void
    {
        // Cada tarjeta solo aparece si el usuario puede ver ese módulo
        $tarjetas = [];

        if (puede('usuarios.ver')) {
            $tarjetas[] = [
                'titulo' => 'Usuarios activos', 'icono' => 'bi-people', 'color' => 'primary', 'ruta' => 'usuarios',
                'valor'  => (new Usuario())->contar('activo = 1'),
            ];
        }
        if (puede('roles.ver')) {
            $tarjetas[] = [
                'titulo' => 'Roles', 'icono' => 'bi-shield-lock', 'color' => 'success', 'ruta' => 'roles',
                'valor'  => (new Rol())->contar(),
            ];
        }
        if (puede('productos.ver')) {
            $tarjetas[] = [
                'titulo' => 'Productos', 'icono' => 'bi-box-seam', 'color' => 'warning', 'ruta' => 'productos',
                'valor'  => (new Producto())->contar('activo = 1'),
            ];
        }

        $this->view('dashboard/index', [
            'titulo'   => 'Inicio',
            'usuario'  => Auth::usuario(),
            'tarjetas' => $tarjetas,
        ]);
    }
}
