<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Permiso;
use App\Models\Usuario;

/**
 * Autenticación por sesión y permisos por módulo.
 *
 * En cada petición se vuelve a leer el usuario y sus permisos de la BD:
 * si un administrador desactiva a alguien o le quita permisos,
 * el cambio aplica de inmediato sin esperar a que cierre sesión.
 */
final class Auth
{
    private const MAX_INTENTOS = 5;
    private const MINUTOS_BLOQUEO = 15;

    private static ?array $usuario = null;
    private static ?array $permisos = null;
    private static bool $cargado = false;

    /**
     * Intenta iniciar sesión. Devuelve null si entró o el mensaje de error.
     */
    public static function intentar(string $usuario, string $password): ?string
    {
        $modelo = new Usuario();
        $u = $modelo->buscarPor('usuario', $usuario);

        if ($u === null) {
            // Se verifica igual un hash falso para que la respuesta tarde lo mismo
            password_verify($password, '$2y$12$5rZQI8nIJ.HB7zSqLXcoMufAAp01fved8gynRh0xxHt2P5xLUCdEK');
            return 'Usuario o contraseña incorrectos.';
        }

        if ($u['bloqueado_hasta'] !== null && strtotime($u['bloqueado_hasta']) > time()) {
            $minutos = (int) ceil((strtotime($u['bloqueado_hasta']) - time()) / 60);
            return "Cuenta bloqueada por intentos fallidos. Intenta en $minutos min.";
        }

        if (!password_verify($password, $u['password'])) {
            $modelo->registrarIntentoFallido((int) $u['id'], self::MAX_INTENTOS, self::MINUTOS_BLOQUEO);
            Logger::info("Login fallido: {$usuario} desde " . Request::ip());
            return 'Usuario o contraseña incorrectos.';
        }

        if (!(int) $u['activo']) {
            return 'Tu usuario está desactivado. Contacta al administrador.';
        }

        if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) {
            $modelo->actualizarSilencioso((int) $u['id'], ['password' => password_hash($password, PASSWORD_DEFAULT)]);
        }

        $modelo->registrarAcceso((int) $u['id']);
        self::login((int) $u['id']);
        return null;
    }

    public static function login(int $usuarioId): void
    {
        session_regenerate_id(true);   // evita fijación de sesión
        $_SESSION['usuario_id'] = $usuarioId;
        unset($_SESSION['_csrf']);     // token nuevo tras iniciar sesión
        self::$cargado = false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$usuario = null;
        self::$permisos = null;
        self::$cargado = true;
    }

    public static function check(): bool
    {
        return self::usuario() !== null;
    }

    /** Usuario actual con datos de su rol, o null */
    public static function usuario(): ?array
    {
        if (self::$cargado) {
            return self::$usuario;
        }
        self::$cargado = true;

        $id = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($id === 0) {
            return null;
        }

        $u = (new Usuario())->conRol($id);
        if ($u === null || !(int) $u['activo']) {
            unset($_SESSION['usuario_id']);
            return null;
        }

        return self::$usuario = $u;
    }

    public static function id(): ?int
    {
        $u = self::usuario();
        return $u ? (int) $u['id'] : null;
    }

    public static function esSuperadmin(): bool
    {
        return (bool) (self::usuario()['es_superadmin'] ?? false);
    }

    /** Permisos del rol actual: ['usuarios' => ['ver' => 1, 'crear' => 0, ...], ...] */
    public static function permisos(): array
    {
        if (self::$permisos === null) {
            $u = self::usuario();
            self::$permisos = $u ? (new Permiso())->deRol((int) $u['rol_id']) : [];
        }
        return self::$permisos;
    }

    /** puede('usuarios.editar') */
    public static function puede(string $permiso): bool
    {
        if (!self::check()) {
            return false;
        }
        if (self::esSuperadmin()) {
            return true;
        }
        [$modulo, $accion] = array_pad(explode('.', $permiso, 2), 2, 'ver');
        if ($accion === 'eliminar') {
            return false;   // eliminar es exclusivo del Superadmin, aunque la BD diga otra cosa
        }
        return !empty(self::permisos()[$modulo][$accion]);
    }
}
