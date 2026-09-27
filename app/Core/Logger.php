<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Bitácora en storage/logs/app-AAAA-MM-DD.log
 */
final class Logger
{
    public static function error(string $mensaje): void
    {
        self::escribir('ERROR', $mensaje);
    }

    public static function info(string $mensaje): void
    {
        self::escribir('INFO', $mensaje);
    }

    private static function escribir(string $nivel, string $mensaje): void
    {
        $archivo = BASE_DIR . '/storage/logs/app-' . date('Y-m-d') . '.log';
        $linea = sprintf("[%s] %s: %s%s", date('Y-m-d H:i:s'), $nivel, $mensaje, PHP_EOL);
        @file_put_contents($archivo, $linea, FILE_APPEND | LOCK_EX);
    }
}
