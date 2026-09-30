<?php
/**
 * IPV - Sistema de notificaciones
 */

require_once __DIR__ . '/Database.php';

class Notificacion
{
    /**
     * Crea una notificación solo si no se ha enviado una igual recientemente.
     * Evita duplicados cuando el evento se repite en cada request.
     */
    public static function crearUnaVez(
        int $usuarioId,
        string $tipo,
        string $titulo,
        string $mensaje = '',
        ?string $url = null,
        string $icono = 'bell',
        string $color = 'primary',
        int $horasMinimas = 24
    ): bool {
        try {
            $existente = Database::fetchOne("
                SELECT id FROM notificaciones
                WHERE usuario_id = ?
                AND tipo = ?
                AND fecha_creacion > DATE_SUB(NOW(), INTERVAL ? HOUR)
                LIMIT 1
            ", [$usuarioId, $tipo, $horasMinimas]);

            if ($existente) return false;

            self::crear($usuarioId, $tipo, $titulo, $mensaje, $url, $icono, $color);
            return true;
        } catch (Throwable $e) {
            error_log('Notificacion crearUnaVez error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Igual que crearParaRol pero sin duplicados.
     */
    public static function crearParaRolUnaVez(
        int $rolId,
        string $tipo,
        string $titulo,
        string $mensaje = '',
        ?string $url = null,
        string $icono = 'bell',
        string $color = 'primary',
        int $horasMinimas = 24
    ): int {
        $usuarios = Database::fetchAll(
            "SELECT id FROM usuarios WHERE rol_id = ? AND activo = 1",
            [$rolId]
        );

        $count = 0;
        foreach ($usuarios as $u) {
            if (self::crearUnaVez((int)$u['id'], $tipo, $titulo, $mensaje, $url, $icono, $color, $horasMinimas)) {
                $count++;
            }
        }
        return $count;
    }

    public static function crearParaAdminsUnaVez(
        string $tipo,
        string $titulo,
        string $mensaje = '',
        ?string $url = null,
        string $icono = 'bell',
        string $color = 'primary',
        int $horasMinimas = 24
    ): int {
        return self::crearParaRolUnaVez(1, $tipo, $titulo, $mensaje, $url, $icono, $color, $horasMinimas);
    }    

    public static function crear(
        int $usuarioId,
        string $tipo,
        string $titulo,
        string $mensaje = '',
        ?string $url = null,
        string $icono = 'bell',
        string $color = 'primary'
    ): int {
        return Database::insert('notificaciones', [
            'usuario_id' => $usuarioId,
            'tipo'       => $tipo,
            'titulo'     => $titulo,
            'mensaje'    => $mensaje ?: null,
            'url'        => $url,
            'icono'      => $icono,
            'color'      => $color,
        ]);
    }

    public static function crearParaRol(
        int $rolId,
        string $tipo,
        string $titulo,
        string $mensaje = '',
        ?string $url = null,
        string $icono = 'bell',
        string $color = 'primary'
    ): int {
        $usuarios = Database::fetchAll(
            "SELECT id FROM usuarios WHERE rol_id = ? AND activo = 1",
            [$rolId]
        );
        $count = 0;
        foreach ($usuarios as $u) {
            self::crear((int)$u['id'], $tipo, $titulo, $mensaje, $url, $icono, $color);
            $count++;
        }
        return $count;
    }

    public static function crearParaAdmins(
        string $tipo,
        string $titulo,
        string $mensaje = '',
        ?string $url = null,
        string $icono = 'bell',
        string $color = 'primary'
    ): int {
        return self::crearParaRol(1, $tipo, $titulo, $mensaje, $url, $icono, $color);
    }

    public static function crearParaSupervisores(
        string $tipo,
        string $titulo,
        string $mensaje = '',
        ?string $url = null,
        string $icono = 'bell',
        string $color = 'primary'
    ): int {
        $c1 = self::crearParaRol(1, $tipo, $titulo, $mensaje, $url, $icono, $color);
        $c2 = self::crearParaRol(2, $tipo, $titulo, $mensaje, $url, $icono, $color);
        return $c1 + $c2;
    }

    public static function contarNoLeidas(int $usuarioId): int
    {
        return (int) Database::fetchValue(
            "SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0",
            [$usuarioId],
            0
        );
    }

    public static function limpiarAntiguas(int $dias = 90): int
    {
        return Database::delete(
            'notificaciones',
            'fecha_creacion < DATE_SUB(NOW(), INTERVAL :dias DAY)',
            [':dias' => $dias]
        );
    }
}