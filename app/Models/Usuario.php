<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Usuario extends Model
{
    protected string $tabla = 'usuarios';
    protected array $campos = [
        'nombre', 'usuario', 'email', 'password', 'rol_id', 'activo',
        'intentos_fallidos', 'bloqueado_hasta', 'ultimo_acceso',
    ];

    /** Usuario con los datos de su rol (nunca incluye el password) */
    public function conRol(int $id): ?array
    {
        return $this->fila(
            'SELECT u.id, u.nombre, u.usuario, u.email, u.rol_id, u.activo, u.ultimo_acceso,
                    r.nombre AS rol, r.es_superadmin
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
              WHERE u.id = ?',
            [$id]
        );
    }

    /** Listado para la tabla (sin passwords) */
    public function listado(): array
    {
        return $this->consulta(
            "SELECT u.id, u.nombre, u.usuario, u.email, u.rol_id, u.activo, u.ultimo_acceso,
                    r.nombre AS rol,
                    (u.bloqueado_hasta IS NOT NULL AND u.bloqueado_hasta > NOW()) AS bloqueado,
                    {$this->camposAuditoria('u')}
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
                    {$this->joinsAuditoria('u')}
           ORDER BY u.nombre"
        );
    }

    public function registrarIntentoFallido(int $id, int $maximo, int $minutosBloqueo): void
    {
        $u = $this->buscar($id);
        $intentos = (int) ($u['intentos_fallidos'] ?? 0) + 1;

        // Cambios automáticos del sistema: no cuentan como "modificado por"
        if ($intentos >= $maximo) {
            $this->actualizarSilencioso($id, [
                'intentos_fallidos' => 0,
                'bloqueado_hasta'   => date('Y-m-d H:i:s', time() + $minutosBloqueo * 60),
            ]);
            return;
        }

        $this->actualizarSilencioso($id, ['intentos_fallidos' => $intentos]);
    }

    public function registrarAcceso(int $id): void
    {
        $this->actualizarSilencioso($id, [
            'intentos_fallidos' => 0,
            'bloqueado_hasta'   => null,
            'ultimo_acceso'     => date('Y-m-d H:i:s'),
        ]);
    }

    /** Usuarios activos con rol de administrador total (excluyendo uno) */
    public function superadminsActivos(int $excepto = 0): int
    {
        $fila = $this->fila(
            'SELECT COUNT(*) AS total
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
              WHERE r.es_superadmin = 1 AND u.activo = 1 AND u.id <> ?',
            [$excepto]
        );
        return (int) $fila['total'];
    }
}
