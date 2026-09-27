<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Controlador base: vistas, respuestas JSON y validación.
 */
abstract class Controller
{
    /** Muestra una vista dentro de un layout (main por defecto) */
    protected function view(string $vista, array $datos = [], ?string $layout = 'main'): void
    {
        View::render($vista, $datos, $layout);
    }

    protected function json(mixed $datos, int $codigo = 200): never
    {
        Response::json($datos, $codigo);
    }

    /** Respuesta de éxito estándar: { ok: true, mensaje: "...", ...extra } */
    protected function ok(string $mensaje = 'Operación realizada', array $extra = []): never
    {
        Response::json(['ok' => true, 'mensaje' => $mensaje] + $extra);
    }

    /** Respuesta de error estándar: { ok: false, mensaje: "...", errores: {...} } */
    protected function error(string $mensaje, int $codigo = 400, array $errores = []): never
    {
        $respuesta = ['ok' => false, 'mensaje' => $mensaje];
        if ($errores !== []) {
            $respuesta['errores'] = $errores;
        }
        Response::json($respuesta, $codigo);
    }

    protected function redirect(string $ruta): never
    {
        Response::redirect($ruta);
    }

    protected function input(string $clave, mixed $defecto = null): mixed
    {
        return Request::input($clave, $defecto);
    }

    /** id numérico recibido por GET o POST (0 si no viene o no es válido) */
    protected function id(string $clave = 'id'): int
    {
        return max(0, (int) filter_var(Request::input($clave), FILTER_VALIDATE_INT));
    }

    /**
     * Valida los datos de la petición. Si hay errores responde 422 y corta.
     * Devuelve solo los campos validados, ya limpios.
     */
    protected function validar(array $reglas, ?array $datos = null): array
    {
        $validador = new Validator($datos ?? Request::todo(), $reglas);
        if (!$validador->pasa()) {
            $this->error('Revisa los datos del formulario', 422, $validador->errores());
        }
        return $validador->validados();
    }

    /** Corta con 403 si el usuario no tiene el permiso */
    protected function autorizar(string $permiso): void
    {
        if (!Auth::puede($permiso)) {
            Response::abort(403);
        }
    }
}
