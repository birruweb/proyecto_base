<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Permisos por rol y módulo: ver, crear, editar, eliminar.
 */
final class Permiso extends Model
{
    public const ACCIONES = ['ver', 'crear', 'editar', 'eliminar'];

    protected string $tabla = 'permisos';
    protected array $campos = ['rol_id', 'modulo_id', 'ver', 'crear', 'editar', 'eliminar'];

    /**
     * ['usuarios' => ['ver' => 1, 'crear' => 0, ...], ...]
     * Si el módulo o su grupo están inactivos, no cuenta.
     */
    public function deRol(int $rolId): array
    {
        $filas = $this->consulta(
            'SELECT m.clave, p.ver, p.crear, p.editar, p.eliminar
               FROM permisos p
               JOIN modulos m ON m.id = p.modulo_id
          LEFT JOIN modulos g ON g.id = m.padre_id
              WHERE p.rol_id = ? AND m.activo = 1 AND (g.id IS NULL OR g.activo = 1)',
            [$rolId]
        );

        $permisos = [];
        foreach ($filas as $f) {
            $permisos[$f['clave']] = array_map('intval', array_intersect_key($f, array_flip(self::ACCIONES)));
        }
        return $permisos;
    }

    /** Para el formulario de roles: [modulo_id => ['ver' => 1, ...], ...] */
    public function porModulo(int $rolId): array
    {
        $filas = $this->consulta('SELECT * FROM permisos WHERE rol_id = ?', [$rolId]);

        $permisos = [];
        foreach ($filas as $f) {
            $permisos[$f['modulo_id']] = array_map('intval', array_intersect_key($f, array_flip(self::ACCIONES)));
        }
        return $permisos;
    }

    /**
     * Reemplaza los permisos del rol SOLO en los módulos que se editaron
     * (los de módulos inactivos se conservan). Usar dentro de una transacción.
     */
    public function reemplazar(int $rolId, array $permisos, array $modulosEditados): void
    {
        if ($modulosEditados !== []) {
            $marcas = implode(', ', array_fill(0, count($modulosEditados), '?'));
            $this->ejecutar(
                "DELETE FROM permisos WHERE rol_id = ? AND modulo_id IN ($marcas)",
                [$rolId, ...array_map('intval', $modulosEditados)]
            );
        }

        foreach ($permisos as $moduloId => $acciones) {
            $this->crear(['rol_id' => $rolId, 'modulo_id' => $moduloId] + $acciones);
        }
    }
}
