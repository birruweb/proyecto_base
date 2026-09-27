<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Módulos del sistema (opciones del menú), en dos niveles:
 *
 *   Grupo raíz    padre_id NULL, ruta NULL   -> encabezado desplegable
 *   Módulo raíz   padre_id NULL, con ruta    -> enlace directo
 *   Submódulo     padre_id = grupo, con ruta -> enlace dentro del grupo
 *
 * Solo los que tienen ruta llevan permisos (ver, crear, editar, eliminar).
 */
final class Modulo extends Model
{
    protected string $tabla = 'modulos';
    protected array $campos = ['padre_id', 'clave', 'nombre', 'icono', 'ruta', 'orden', 'activo'];

    /** Árbol de módulos activos: raíces con su arreglo 'hijos' */
    public function menu(): array
    {
        $filas = $this->consulta(
            'SELECT * FROM modulos WHERE activo = 1 ORDER BY padre_id IS NOT NULL, orden, nombre'
        );

        $raices = [];
        foreach ($filas as $f) {
            if ($f['padre_id'] === null) {
                $raices[$f['id']] = $f + ['hijos' => []];
            }
        }
        foreach ($filas as $f) {
            if ($f['padre_id'] !== null && isset($raices[$f['padre_id']])) {
                $raices[$f['padre_id']]['hijos'][] = $f;
            }
        }
        return array_values($raices);
    }

    /**
     * Migas de pan de una ruta: [grupo?, módulo] según la tabla modulos.
     * Toma el módulo cuya ruta coincide más (productos/reporte -> productos).
     * Devuelve [] si la ruta no pertenece a ningún módulo activo.
     */
    public function rastro(string $ruta): array
    {
        $ruta = trim($ruta, '/');
        if ($ruta === '') {
            return [];
        }

        $modulos = $this->consulta(
            'SELECT m.nombre, m.ruta, g.nombre AS grupo
               FROM modulos m
          LEFT JOIN modulos g ON g.id = m.padre_id
              WHERE m.ruta IS NOT NULL AND m.activo = 1 AND (g.id IS NULL OR g.activo = 1)'
        );

        $elegido = null;
        foreach ($modulos as $m) {
            $rutaModulo = trim((string) $m['ruta'], '/');
            $coincide = $ruta === $rutaModulo || str_starts_with($ruta, $rutaModulo . '/');
            if ($coincide && strlen($rutaModulo) > strlen(trim((string) ($elegido['ruta'] ?? ''), '/'))) {
                $elegido = $m;
            }
        }

        if ($elegido === null) {
            return [];
        }

        $migas = [];
        if ($elegido['grupo'] !== null) {
            $migas[] = ['texto' => $elegido['grupo'], 'ruta' => null];
        }
        $migas[] = ['texto' => $elegido['nombre'], 'ruta' => $elegido['ruta']];
        return $migas;
    }

    /** Módulos con ruta y activos (incluido su grupo): son los que llevan permisos */
    public function conPermisos(): array
    {
        return $this->consulta(
            'SELECT m.*
               FROM modulos m
          LEFT JOIN modulos p ON p.id = m.padre_id
              WHERE m.ruta IS NOT NULL AND m.activo = 1 AND (p.id IS NULL OR p.activo = 1)'
        );
    }

    /** Listado para la tabla de administración, ya ordenado como árbol */
    public function listado(): array
    {
        return $this->consulta(
            "SELECT m.id, m.padre_id, m.clave, m.nombre, m.icono, m.ruta, m.orden, m.activo,
                    p.nombre AS padre,
                    CASE WHEN m.padre_id IS NOT NULL THEN 'hijo'
                         WHEN m.ruta IS NULL THEN 'grupo'
                         ELSE 'raiz' END AS tipo,
                    (SELECT COUNT(*) FROM modulos h WHERE h.padre_id = m.id) AS hijos,
                    {$this->camposAuditoria('m')}
               FROM modulos m
          LEFT JOIN modulos p ON p.id = m.padre_id
                    {$this->joinsAuditoria('m')}
           ORDER BY COALESCE(p.orden, m.orden), COALESCE(p.nombre, m.nombre),
                    m.padre_id IS NOT NULL, m.orden, m.nombre"
        );
    }

    /** ¿Es un grupo raíz? (puede tener submódulos) */
    public function esGrupo(int $id): bool
    {
        $m = $this->buscar($id);
        return $m !== null && $m['padre_id'] === null && $m['ruta'] === null;
    }

    public function contarHijos(int $id): int
    {
        return $this->contar('padre_id = ?', [$id]);
    }

    /** Claves de los submódulos de un grupo */
    public function clavesHijos(int $id): array
    {
        return array_column($this->consulta('SELECT clave FROM modulos WHERE padre_id = ?', [$id]), 'clave');
    }
}
