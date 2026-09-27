<?php
/**
 * Funciones globales de ayuda, disponibles en controladores y vistas.
 */
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Request;

/** Lee una variable del .env */
function env(string $clave, mixed $defecto = null): mixed
{
    return Env::get($clave, $defecto);
}

/** URL completa dentro de la app: url('usuarios') -> /proyecto-base/usuarios */
function url(string $ruta = ''): string
{
    return Request::base() . '/' . ltrim($ruta, '/');
}

/** URL de un archivo en public/assets con versión para evitar caché vieja */
function asset(string $ruta): string
{
    $ruta = ltrim($ruta, '/');
    $archivo = BASE_DIR . '/public/assets/' . $ruta;
    $version = is_file($archivo) ? '?v=' . filemtime($archivo) : '';
    return url('assets/' . $ruta) . $version;
}

/** Escapa texto para imprimirlo en HTML (evita XSS). Úsalo SIEMPRE en las vistas. */
function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    return Csrf::token();
}

/** Campo oculto con el token CSRF para formularios normales */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

/** ¿El usuario actual tiene el permiso? puede('productos.crear') */
function puede(string $permiso): bool
{
    return Auth::puede($permiso);
}

function usuario_actual(): ?array
{
    return Auth::usuario();
}

/** Mensaje de un solo uso (se muestra en la siguiente página) */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['_flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

/** Devuelve y borra los mensajes flash pendientes */
function flashes(): array
{
    $mensajes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $mensajes;
}

/** Guarda un valor para rellenar el formulario después de redirigir */
function old(string $campo, string $defecto = ''): string
{
    $valor = $_SESSION['_old'][$campo] ?? $defecto;
    unset($_SESSION['_old'][$campo]);
    return (string) $valor;
}

function set_old(array $datos): void
{
    $_SESSION['_old'] = $datos;
}

/** ¿La ruta actual empieza con $ruta? (para marcar el menú activo) */
function ruta_activa(string $ruta): bool
{
    $actual = Request::ruta();
    $ruta = '/' . trim($ruta, '/');
    return $ruta === '/' ? $actual === '/' : ($actual === $ruta || str_starts_with($actual, $ruta . '/'));
}

function fecha(?string $valor, string $formato = 'd/m/Y H:i'): string
{
    return $valor ? date($formato, strtotime($valor)) : '—';
}
