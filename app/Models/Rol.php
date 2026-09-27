<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Rol extends Model
{
    protected string $tabla = 'roles';
    protected array $campos = ['nombre', 'descripcion', 'es_superadmin'];

    /** Roles con el número de usuarios que tienen asignado cada uno */
    public function listado(): array
    {
        return $this->consulta(
            "SELECT r.id, r.nombre, r.descripcion, r.es_superadmin,
                    (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = r.id) AS usuarios,
                    {$this->camposAuditoria('r')}
               FROM roles r
                    {$this->joinsAuditoria('r')}
           ORDER BY r.es_superadmin DESC, r.nombre"
        );
    }

    public function usuariosAsignados(int $id): int
    {
        $fila = $this->fila('SELECT COUNT(*) AS total FROM usuarios WHERE rol_id = ?', [$id]);
        return (int) $fila['total'];
    }
}
