<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Información de la petición actual: ruta, método, datos, AJAX...
 */
final class Request
{
    private static ?string $base = null;

    /**
     * Carpeta donde vive la app dentro del servidor.
     *   http://localhost/proyecto-base/usuarios  ->  "/proyecto-base"
     *   http://proyecto.test/usuarios            ->  ""
     * Se detecta sola; se puede forzar con APP_BASE_PATH en el .env.
     */
    public static function base(): string
    {
        if (self::$base !== null) {
            return self::$base;
        }

        $forzada = env('APP_BASE_PATH');
        if (is_string($forzada) && $forzada !== '') {
            return self::$base = rtrim('/' . trim($forzada, '/'), '/');
        }

        // En Windows dirname() puede devolver "\" en vez de "/": se normaliza
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $dir = rtrim($dir, '/');

        if (str_ends_with($dir, '/public')) {
            $dir = substr($dir, 0, -strlen('/public'));
        }

        return self::$base = $dir;
    }

    /** Ruta pedida sin la carpeta base: "/usuarios/listar" */
    public static function ruta(): string
    {
        $uri = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        $base = self::base();

        if ($base !== '' && stripos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }
        if ($uri === '/public' || str_starts_with($uri, '/public/')) {
            $uri = substr($uri, strlen('/public'));
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, strlen('/index.php'));
        }

        return '/' . trim($uri, '/');
    }

    public static function metodo(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function esAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public static function esHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /** Valor de GET o POST (POST tiene prioridad) */
    public static function input(string $clave, mixed $defecto = null): mixed
    {
        return $_POST[$clave] ?? $_GET[$clave] ?? $defecto;
    }

    public static function todo(): array
    {
        return array_merge($_GET, $_POST);
    }

    public static function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
