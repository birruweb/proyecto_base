<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Módulo de ejemplo. Para un CRUD normal esto es todo lo que necesitas:
 * la tabla y las columnas que se pueden escribir.
 */
final class Producto extends Model
{
    protected string $tabla = 'productos';
    protected array $campos = ['nombre', 'descripcion', 'precio', 'stock', 'activo'];
}
