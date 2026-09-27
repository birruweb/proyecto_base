<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Lee el archivo .env (CLAVE=valor) y guarda los valores en memoria.
 */
final class Env
{
    private static array $vars = [];

    public static function cargar(string $archivo): void
    {
        foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            $linea = trim($linea);
            if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
                continue;
            }

            [$clave, $valor] = array_map('trim', explode('=', $linea, 2));

            // Valores entre comillas se toman literal: DB_PASS="mi#clave"
            if (preg_match('/^(["\'])(.*)\1$/', $valor, $m)) {
                self::$vars[$clave] = $m[2];
                continue;
            }

            self::$vars[$clave] = match (strtolower($valor)) {
                'true'  => true,
                'false' => false,
                'null', '' => null,
                default => $valor,
            };
        }
    }

    public static function get(string $clave, mixed $defecto = null): mixed
    {
        return array_key_exists($clave, self::$vars) && self::$vars[$clave] !== null
            ? self::$vars[$clave]
            : $defecto;
    }
}
