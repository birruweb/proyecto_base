<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validador sencillo con reglas estilo Laravel.
 *
 *   'nombre' => 'required|max:100'
 *   'email'  => 'nullable|email|unique:usuarios,email,5'   (5 = id a ignorar al editar)
 *   'rol_id' => 'required|integer|exists:roles,id'
 *   'clave'  => ['required', 'regex:/^[a-z0-9]+$/']         (arreglo si la regla lleva "|")
 *
 * Reglas: required, nullable, email, numeric, integer, min, max, in,
 *         regex, confirmed, unique, exists, array
 */
final class Validator
{
    private array $errores = [];
    private array $validados = [];

    public function __construct(private array $datos, private array $reglas)
    {
        $this->validar();
    }

    public function pasa(): bool
    {
        return $this->errores === [];
    }

    public function errores(): array
    {
        return $this->errores;
    }

    public function validados(): array
    {
        return $this->validados;
    }

    private function validar(): void
    {
        foreach ($this->reglas as $campo => $reglas) {
            $reglas = is_array($reglas) ? $reglas : explode('|', $reglas);
            $valor = $this->datos[$campo] ?? null;
            if (is_string($valor)) {
                $valor = trim($valor);
            }

            $vacio = $valor === null || $valor === '' || $valor === [];

            if ($vacio) {
                if (in_array('required', $reglas, true)) {
                    $this->errores[$campo] = 'Este campo es obligatorio.';
                } else {
                    $this->validados[$campo] = null;
                }
                continue;
            }

            $esNumero = in_array('numeric', $reglas, true) || in_array('integer', $reglas, true);

            foreach ($reglas as $regla) {
                [$nombre, $parametro] = array_pad(explode(':', $regla, 2), 2, null);
                $error = $this->aplicar($nombre, $parametro, $campo, $valor, $esNumero);
                if ($error !== null) {
                    $this->errores[$campo] = $error;
                    continue 2;
                }
            }

            $this->validados[$campo] = $valor;
        }
    }

    private function aplicar(string $regla, ?string $param, string $campo, mixed $valor, bool $esNumero): ?string
    {
        if ($regla !== 'array' && is_array($valor)) {
            return 'Valor inválido.';
        }

        switch ($regla) {
            case 'required':
            case 'nullable':
                return null;

            case 'array':
                return is_array($valor) ? null : 'Valor inválido.';

            case 'email':
                return filter_var($valor, FILTER_VALIDATE_EMAIL) ? null : 'Escribe un correo válido.';

            case 'numeric':
                return is_numeric($valor) ? null : 'Debe ser un número.';

            case 'integer':
                return filter_var($valor, FILTER_VALIDATE_INT) !== false ? null : 'Debe ser un número entero.';

            case 'min':
                if ($esNumero) {
                    return (float) $valor >= (float) $param ? null : "Debe ser mayor o igual a $param.";
                }
                return mb_strlen((string) $valor) >= (int) $param ? null : "Debe tener al menos $param caracteres.";

            case 'max':
                if ($esNumero) {
                    return (float) $valor <= (float) $param ? null : "Debe ser menor o igual a $param.";
                }
                return mb_strlen((string) $valor) <= (int) $param ? null : "Máximo $param caracteres.";

            case 'in':
                return in_array((string) $valor, explode(',', (string) $param), true) ? null : 'Opción inválida.';

            case 'regex':
                return preg_match((string) $param, (string) $valor) ? null : 'Formato inválido.';

            case 'confirmed':
                return ($this->datos[$campo . '_confirmation'] ?? null) === $valor ? null : 'La confirmación no coincide.';

            case 'unique':
                [$tabla, $columna, $ignorarId] = array_pad(explode(',', (string) $param), 3, null);
                $sql = 'SELECT COUNT(*) FROM ' . $this->q($tabla) . ' WHERE ' . $this->q($columna ?? $campo) . ' = ?';
                $params = [$valor];
                if ($ignorarId !== null && $ignorarId !== '') {
                    $sql .= ' AND id <> ?';
                    $params[] = (int) $ignorarId;
                }
                return $this->contar($sql, $params) === 0 ? null : 'Este valor ya está registrado.';

            case 'exists':
                [$tabla, $columna] = array_pad(explode(',', (string) $param), 2, null);
                $sql = 'SELECT COUNT(*) FROM ' . $this->q($tabla) . ' WHERE ' . $this->q($columna ?? 'id') . ' = ?';
                return $this->contar($sql, [$valor]) > 0 ? null : 'El valor seleccionado no existe.';
        }

        throw new \InvalidArgumentException("Regla de validación desconocida: $regla");
    }

    private function contar(string $sql, array $params): int
    {
        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function q(?string $identificador): string
    {
        if ($identificador === null || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identificador)) {
            throw new \InvalidArgumentException('Identificador inválido en regla de validación');
        }
        return "`$identificador`";
    }
}
