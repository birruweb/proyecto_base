<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Enrutador.
 *
 * Cada ruta tiene un tercer parámetro opcional con el permiso requerido:
 *   null               -> pública (cualquiera entra)
 *   'auth'             -> solo requiere haber iniciado sesión
 *   'usuarios.editar'  -> sesión + permiso "editar" en el módulo "usuarios"
 *
 * Todas las rutas POST validan el token CSRF automáticamente.
 */
final class Router
{
    private array $rutas = [];

    public function get(string $ruta, array $accion, ?string $permiso = null): void
    {
        $this->agregar('GET', $ruta, $accion, $permiso);
    }

    public function post(string $ruta, array $accion, ?string $permiso = null): void
    {
        $this->agregar('POST', $ruta, $accion, $permiso);
    }

    private function agregar(string $metodo, string $ruta, array $accion, ?string $permiso): void
    {
        $ruta = '/' . trim($ruta, '/');
        $this->rutas[$metodo][$ruta] = ['accion' => $accion, 'permiso' => $permiso];
    }

    public function despachar(): void
    {
        $metodo = Request::metodo();
        $ruta = Request::ruta();
        $definicion = $this->rutas[$metodo][$ruta] ?? null;

        if ($definicion === null) {
            $existeEnOtroMetodo = isset($this->rutas[$metodo === 'GET' ? 'POST' : 'GET'][$ruta]);
            Response::abort($existeEnOtroMetodo ? 405 : 404);
        }

        if ($metodo === 'POST') {
            Csrf::verificar();
        }

        $permiso = $definicion['permiso'];
        if ($permiso !== null) {
            if (!Auth::check()) {
                if (Request::esAjax()) {
                    Response::abort(401);
                }
                if ($metodo === 'GET' && $ruta !== '/') {
                    $_SESSION['_destino'] = $ruta;   // para regresar aquí después del login
                    flash('warning', 'Inicia sesión para continuar.');
                }
                Response::redirect('login');
            }
            if ($permiso !== 'auth' && !Auth::puede($permiso)) {
                Response::abort(403);
            }
        }

        [$clase, $metodoControlador] = $definicion['accion'];
        (new $clase())->$metodoControlador();
    }
}
