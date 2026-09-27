<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Atrapa cualquier error no controlado, lo registra en el log
 * y muestra una respuesta amigable (con detalle solo si APP_DEBUG=true).
 */
final class ErrorHandler
{
    public static function registrar(): void
    {
        // Warnings y notices se convierten en excepciones para no pasarlos por alto
        set_error_handler(static function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
            if (!(error_reporting() & $nivel)) {
                return false;
            }
            if ($nivel === E_DEPRECATED || $nivel === E_USER_DEPRECATED) {
                Logger::info("Deprecado: $mensaje en $archivo:$linea");
                return true;
            }
            throw new \ErrorException($mensaje, 0, $nivel, $archivo, $linea);
        });

        set_exception_handler([self::class, 'manejar']);
    }

    public static function manejar(\Throwable $e): void
    {
        Logger::error(sprintf(
            "%s: %s en %s:%d\n%s",
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $debug = (bool) env('APP_DEBUG', false);
        Response::abort(500, $debug ? $e->getMessage() : '', $debug ? $e : null);
    }
}
