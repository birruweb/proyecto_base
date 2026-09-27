<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Renderiza vistas PHP dentro de un layout.
 * La vista se genera primero y se inserta en el layout como $contenido.
 */
final class View
{
    public static function render(string $vista, array $datos = [], ?string $layout = 'main'): void
    {
        $contenido = self::capturar(APP_DIR . '/Views/' . $vista . '.php', $datos);

        if ($layout === null) {
            echo $contenido;
            return;
        }

        echo self::capturar(
            APP_DIR . '/Views/layouts/' . $layout . '.php',
            $datos + ['contenido' => $contenido]
        );
    }

    /** Incluye una vista parcial (dentro de otra vista o layout) */
    public static function parcial(string $vista, array $datos = []): void
    {
        echo self::capturar(APP_DIR . '/Views/' . $vista . '.php', $datos);
    }

    private static function capturar(string $__archivo, array $__datos): string
    {
        if (!is_file($__archivo)) {
            throw new \RuntimeException('Vista no encontrada: ' . $__archivo);
        }

        extract($__datos, EXTR_SKIP);
        ob_start();
        try {
            require $__archivo;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
