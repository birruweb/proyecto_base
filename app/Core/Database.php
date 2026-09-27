<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Conexión única a MySQL/MariaDB (se crea la primera vez que se usa).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function conexion(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                env('DB_HOST', '127.0.0.1'),
                env('DB_PORT', '3306'),
                env('DB_NAME', '')
            );

            try {
                self::$pdo = new PDO($dsn, (string) env('DB_USER', 'root'), (string) env('DB_PASS', ''), [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (\PDOException $e) {
                throw new \RuntimeException(
                    'No se pudo conectar a la base de datos. Revisa que MySQL esté encendido '
                    . 'y los datos DB_* del archivo .env. Detalle: ' . $e->getMessage(),
                    0,
                    $e
                );
            }

            // Misma zona horaria que PHP para que las fechas coincidan
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
        }

        return self::$pdo;
    }

    /** Ejecuta varias operaciones como una sola: si una falla, se deshacen todas */
    public static function transaccion(callable $operaciones): mixed
    {
        $pdo = self::conexion();
        $pdo->beginTransaction();
        try {
            $resultado = $operaciones($pdo);
            $pdo->commit();
            return $resultado;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
