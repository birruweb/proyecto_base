<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Modelo base: CRUD genérico con consultas preparadas.
 *
 * Cada modelo hijo define:
 *   $tabla   -> nombre de la tabla
 *   $campos  -> columnas que se pueden escribir (lista blanca)
 *
 * Auditoría automática: si la tabla tiene las columnas creado_por y
 * actualizado_por, se llenan solas con el usuario que hace el cambio.
 */
abstract class Model
{
    protected string $tabla;
    protected string $llave = 'id';
    protected array $campos = [];

    /** Columnas de cada tabla (se consultan una vez por petición) */
    private static array $columnas = [];

    protected function db(): PDO
    {
        return Database::conexion();
    }

    /**
     * Todos los registros. Si la tabla tiene auditoría, incluye
     * creado_por_nombre y actualizado_por_nombre.
     */
    public function todos(string $orden = 'id'): array
    {
        $tabla = $this->q($this->tabla);
        $orden = $this->q($orden);

        if ($this->tieneAuditoria()) {
            return $this->consulta(
                "SELECT t.*, {$this->camposAuditoria('t')}
                   FROM $tabla t {$this->joinsAuditoria('t')}
               ORDER BY t.$orden"
            );
        }

        return $this->consulta("SELECT * FROM $tabla ORDER BY $orden");
    }

    public function buscar(int $id): ?array
    {
        return $this->fila(
            'SELECT * FROM ' . $this->q($this->tabla) . ' WHERE ' . $this->q($this->llave) . ' = ?',
            [$id]
        );
    }

    public function buscarPor(string $columna, mixed $valor): ?array
    {
        return $this->fila(
            'SELECT * FROM ' . $this->q($this->tabla) . ' WHERE ' . $this->q($columna) . ' = ? LIMIT 1',
            [$valor]
        );
    }

    /** Inserta y devuelve el id nuevo (registra creado_por si la tabla lo tiene) */
    public function crear(array $datos): int
    {
        $datos = $this->filtrar($datos) + $this->sello('creado_por');
        $columnas = implode(', ', array_map([$this, 'q'], array_keys($datos)));
        $marcas = implode(', ', array_fill(0, count($datos), '?'));

        $this->ejecutar(
            'INSERT INTO ' . $this->q($this->tabla) . " ($columnas) VALUES ($marcas)",
            array_values($datos)
        );

        return (int) $this->db()->lastInsertId();
    }

    /** Actualiza (registra actualizado_por si la tabla lo tiene) */
    public function actualizar(int $id, array $datos): bool
    {
        $datos = $this->filtrar($datos);
        if ($datos === []) {
            return false;
        }
        return $this->update($id, $datos + $this->sello('actualizado_por'), []);
    }

    /**
     * Actualiza SIN registrar quién ni cuándo. Para cambios automáticos
     * del sistema (último acceso, intentos de login), no de un usuario.
     */
    public function actualizarSilencioso(int $id, array $datos): bool
    {
        $datos = $this->filtrar($datos);
        if ($datos === []) {
            return false;
        }
        // Asignar la columna a sí misma evita que ON UPDATE CURRENT_TIMESTAMP la cambie
        $extra = $this->tieneColumna('actualizado_en') ? ['`actualizado_en` = `actualizado_en`'] : [];
        return $this->update($id, $datos, $extra);
    }

    public function eliminar(int $id): bool
    {
        return $this->ejecutar(
            'DELETE FROM ' . $this->q($this->tabla) . ' WHERE ' . $this->q($this->llave) . ' = ?',
            [$id]
        ) > 0;
    }

    public function contar(string $where = '1 = 1', array $params = []): int
    {
        $fila = $this->fila('SELECT COUNT(*) AS total FROM ' . $this->q($this->tabla) . " WHERE $where", $params);
        return (int) ($fila['total'] ?? 0);
    }

    // ---- Auditoría ----

    public function tieneAuditoria(): bool
    {
        return $this->tieneColumna('creado_por') && $this->tieneColumna('actualizado_por');
    }

    /** Para consultas propias: campos con fechas y nombres de quién creó/modificó */
    protected function camposAuditoria(string $alias): string
    {
        return "$alias.creado_en, $alias.actualizado_en,
                aud_c.nombre AS creado_por_nombre, aud_a.nombre AS actualizado_por_nombre";
    }

    /** Para consultas propias: los JOIN con usuarios que necesitan camposAuditoria() */
    protected function joinsAuditoria(string $alias): string
    {
        return "LEFT JOIN usuarios aud_c ON aud_c.id = $alias.creado_por
                LEFT JOIN usuarios aud_a ON aud_a.id = $alias.actualizado_por";
    }

    // ---- Ayudantes para consultas propias en los modelos hijos ----

    protected function consulta(string $sql, array $params = []): array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function fila(string $sql, array $params = []): ?array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $fila = $stmt->fetch();
        return $fila === false ? null : $fila;
    }

    /** INSERT/UPDATE/DELETE: devuelve filas afectadas */
    protected function ejecutar(string $sql, array $params = []): int
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    protected function tieneColumna(string $columna): bool
    {
        self::$columnas[$this->tabla] ??= array_column(
            $this->consulta('SHOW COLUMNS FROM ' . $this->q($this->tabla)),
            'Field'
        );
        return in_array($columna, self::$columnas[$this->tabla], true);
    }

    private function update(int $id, array $datos, array $extra): bool
    {
        $sets = array_map(fn($c) => $this->q($c) . ' = ?', array_keys($datos));
        $sets = implode(', ', [...$sets, ...$extra]);

        return $this->ejecutar(
            'UPDATE ' . $this->q($this->tabla) . " SET $sets WHERE " . $this->q($this->llave) . ' = ?',
            [...array_values($datos), $id]
        ) > 0;
    }

    /** ['creado_por' => id del usuario actual] si la tabla tiene esa columna y hay sesión */
    private function sello(string $columna): array
    {
        $usuarioId = Auth::id();
        return ($usuarioId !== null && $this->tieneColumna($columna)) ? [$columna => $usuarioId] : [];
    }

    /** Solo deja pasar las columnas declaradas en $campos */
    private function filtrar(array $datos): array
    {
        return array_intersect_key($datos, array_flip($this->campos));
    }

    /** Escapa nombres de tabla/columna (evita inyección SQL en identificadores) */
    protected function q(string $identificador): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identificador)) {
            throw new \InvalidArgumentException("Identificador inválido: $identificador");
        }
        return "`$identificador`";
    }
}
