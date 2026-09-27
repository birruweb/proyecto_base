<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Excel;
use App\Core\Response;
use App\Models\Producto;

/**
 * Módulo de ejemplo con un CRUD completo.
 * Para crear un módulo nuevo, copia este archivo, el modelo Producto,
 * la vista productos/index.php y el JS modulos/productos.js.
 */
final class ProductosController extends Controller
{
    private Producto $productos;

    public function __construct()
    {
        $this->productos = new Producto();
    }

    public function index(): void
    {
        $this->view('productos/index', [
            'titulo'  => 'Productos',
            'scripts' => ['js/modulos/productos.js'],
        ]);
    }

    public function listar(): void
    {
        $this->json(['data' => $this->productos->todos('nombre')]);
    }

    /** Descarga el catálogo como .xlsx (productos-AAAA-MM-DD.xlsx) */
    public function exportar(): void
    {
        Excel::descargar('productos', [
            'nombre'      => 'Nombre',
            'descripcion' => 'Descripción',
            'precio'      => 'Precio',
            'stock'       => 'Stock',
        ], $this->productos->todos('nombre'), 'Productos');
    }

    public function crear(): void
    {
        $datos = $this->validar($this->reglas());
        $id = $this->productos->crear($datos);
        $this->ok('Producto creado', ['id' => $id]);
    }

    public function actualizar(): void
    {
        $id = $this->id();
        $this->productos->buscar($id) ?? Response::abort(404, 'El producto no existe');

        $datos = $this->validar($this->reglas());
        $this->productos->actualizar($id, $datos);
        $this->ok('Producto actualizado');
    }

    public function eliminar(): void
    {
        $id = $this->id();
        $this->productos->buscar($id) ?? Response::abort(404, 'El producto no existe');

        $this->productos->eliminar($id);
        $this->ok('Producto eliminado');
    }

    private function reglas(): array
    {
        return [
            'nombre'      => 'required|max:150',
            'descripcion' => 'nullable|max:1000',
            'precio'      => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'activo'      => 'required|in:0,1',
        ];
    }
}
