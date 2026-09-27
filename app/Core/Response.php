<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Respuestas: JSON, redirecciones y páginas de error.
 */
final class Response
{
    private const TEXTOS = [
        400 => 'Petición inválida',
        401 => 'Debes iniciar sesión',
        403 => 'No tienes permiso para realizar esta acción',
        404 => 'La página que buscas no existe',
        405 => 'Método no permitido',
        419 => 'La sesión expiró. Recarga la página e intenta de nuevo.',
        422 => 'Revisa los datos del formulario',
        500 => 'Ocurrió un error interno. Intenta de nuevo más tarde.',
    ];

    /**
     * Establece el código HTTP con su texto. Se manda la línea completa porque
     * Apache convierte en 500 los códigos que no conoce (como 419) si no llevan texto.
     */
    public static function estado(int $codigo): void
    {
        $protocolo = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        $texto = [200 => 'OK', 419 => 'Page Expired', 422 => 'Unprocessable Content'][$codigo] ?? '';
        if ($texto !== '' && !headers_sent()) {
            header("$protocolo $codigo $texto", true, $codigo);
            return;
        }
        http_response_code($codigo);
    }

    public static function json(mixed $datos, int $codigo = 200): never
    {
        self::estado($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $ruta): never
    {
        header('Location: ' . url($ruta));
        exit;
    }

    /**
     * Corta la petición con un código de error.
     * AJAX recibe JSON; el navegador recibe una página de error.
     */
    public static function abort(int $codigo, string $mensaje = '', ?\Throwable $excepcion = null): never
    {
        $mensaje = $mensaje !== '' ? $mensaje : (self::TEXTOS[$codigo] ?? 'Error');

        if (Request::esAjax()) {
            self::json(['ok' => false, 'mensaje' => $mensaje], $codigo);
        }

        self::estado($codigo);
        try {
            View::render('errors/error', [
                'titulo'    => 'Error ' . $codigo,
                'codigo'    => $codigo,
                'mensaje'   => $mensaje,
                'excepcion' => $excepcion,
            ], 'blank');
        } catch (\Throwable) {
            echo '<h1>Error ' . $codigo . '</h1><p>' . htmlspecialchars($mensaje) . '</p>';
        }
        exit;
    }
}
