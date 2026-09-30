<?php
/**
 * IPV - Avisos automáticos de vencimiento de contratos de proveedor
 *
 * Se llama en cada carga de página (desde layouts/header.php) para usuarios
 * Admin y Supervisor. Genera notificaciones con anti-duplicado para no
 * repetir el mismo aviso en cada request.
 *
 * Lógica:
 *   - Contratos 'activo' con vencimiento en >30 días: sin aviso.
 *   - Contratos 'activo' con vencimiento <=30 días: aviso "por vencer".
 *   - Contratos 'activo' que vencen hoy: aviso crítico.
 *   - Contratos vencidos (activo con fecha < hoy) → pasan a 'por_renovar'
 *     y generan aviso cada 7 días hasta que el Admin decida.
 *
 * Anti-duplicado:
 *   - Se usa el tipo como identificador único por contrato y umbral.
 *   - Si ya existe una notificación del mismo tipo para el mismo usuario
 *     creada en las últimas N horas, no se duplica.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Notificacion.php';
require_once __DIR__ . '/Config.php';

class ContratoAvisos
{
    /**
     * Ejecuta la revisión de contratos y crea las notificaciones que hagan falta.
     */
    public static function revisar(): void
    {
        try {
            // 1) Actualizar contratos vencidos de 'activo' a 'por_renovar'
            self::actualizarVencidos();

            // 2) Avisos por proximidad (30, 15, 7 días)
            self::avisarProximos(30);
            self::avisarProximos(15);
            self::avisarProximos(7);

            // 3) Aviso crítico el día de vencimiento
            self::avisarVencenHoy();

            // 4) Recordatorio recurrente para 'por_renovar' cada 7 días
            self::avisarPorRenovarRecurrente();

        } catch (Throwable $e) {
            error_log('ContratoAvisos error: ' . $e->getMessage());
        }
    }

    /**
     * Contratos activos cuya fecha ya pasó → estado 'por_renovar'.
     */
    private static function actualizarVencidos(): void
    {
        Database::query("
            UPDATE contratos_proveedor
            SET estado = 'por_renovar'
            WHERE estado = 'activo'
              AND fecha_caducidad < CURDATE()
        ");
    }

    /**
     * Avisa de contratos activos que vencen en <=N días.
     *
     * Reglas de rango:
     *   - Umbral 30: contratos con 16..30 días restantes.
     *   - Umbral 15: contratos con  8..15 días restantes.
     *   - Umbral 7:  contratos con  1..7  días restantes.
     *
     * Cualquier contrato dentro de 1..30 días entra en al menos un umbral,
     * gracias al solapamiento calculado.
     */
    private static function avisarProximos(int $dias): void
    {
        $rangoInferior = match ($dias) {
            30 => 16,
            15 => 8,
            7  => 1,
            default => 1,
        };

        $contratos = Database::fetchAll("
            SELECT
                c.id, c.num_contrato, c.fecha_caducidad,
                p.nombre AS proveedor,
                DATEDIFF(c.fecha_caducidad, CURDATE()) AS dias_restantes
            FROM contratos_proveedor c
            JOIN proveedores p ON p.id = c.proveedor_id
            WHERE c.estado = 'activo'
              AND DATEDIFF(c.fecha_caducidad, CURDATE()) BETWEEN ? AND ?
        ", [$rangoInferior, $dias]);

        foreach ($contratos as $c) {
            self::notificarProximo($c, $dias);
        }
    }

    /**
     * Avisa de contratos activos que vencen hoy.
     */
    private static function avisarVencenHoy(): void
    {
        $contratos = Database::fetchAll("
            SELECT
                c.id, c.num_contrato, c.fecha_caducidad,
                p.nombre AS proveedor,
                0 AS dias_restantes
            FROM contratos_proveedor c
            JOIN proveedores p ON p.id = c.proveedor_id
            WHERE c.estado = 'activo'
              AND c.fecha_caducidad = CURDATE()
        ");

        foreach ($contratos as $c) {
            self::notificarVenceHoy($c);
        }
    }

    /**
     * Recordatorio recurrente para contratos 'por_renovar' cada 7 días.
     */
    private static function avisarPorRenovarRecurrente(): void
    {
        $contratos = Database::fetchAll("
            SELECT
                c.id, c.num_contrato, c.fecha_caducidad,
                p.nombre AS proveedor,
                ABS(DATEDIFF(c.fecha_caducidad, CURDATE())) AS dias_vencido
            FROM contratos_proveedor c
            JOIN proveedores p ON p.id = c.proveedor_id
            WHERE c.estado = 'por_renovar'
            ORDER BY c.fecha_caducidad ASC
            LIMIT 100
        ");

        foreach ($contratos as $c) {
            self::notificarPorRenovar($c);
        }
    }

    // ============================================================
    // NOTIFICACIONES (con anti-duplicado)
    // ============================================================

    private static function notificarProximo(array $c, int $dias): void
    {
        $tipo   = 'contrato_vence_' . $dias . 'd';
        $titulo = "Contrato vence en {$dias} día" . ($dias !== 1 ? 's' : '');
        $mensaje = $c['num_contrato'] . ' · ' . $c['proveedor']
                 . ' · ' . date('d/m/Y', strtotime($c['fecha_caducidad']));

        self::crearUnaVezParaRol(
            [1, 2],
            $tipo,
            $titulo,
            $mensaje,
            'views/admin/contratos.php',
            'file-earmark-text',
            $dias <= 7 ? 'danger' : 'warning',
            48,
            $c['id']
        );
    }

    private static function notificarVenceHoy(array $c): void
    {
        $tipo = 'contrato_vence_hoy';
        $titulo = 'Contrato vence HOY';
        $mensaje = $c['num_contrato'] . ' · ' . $c['proveedor']
                 . ' · ' . date('d/m/Y', strtotime($c['fecha_caducidad']));

        self::crearUnaVezParaRol(
            [1, 2],
            $tipo,
            $titulo,
            $mensaje,
            'views/admin/contratos.php',
            'exclamation-triangle-fill',
            'danger',
            12,
            $c['id']
        );
    }

    private static function notificarPorRenovar(array $c): void
    {
        $tipo = 'contrato_por_renovar';
        $titulo = 'Contrato vencido — decisión pendiente';
        $dias = (int)$c['dias_vencido'];
        $mensaje = $c['num_contrato'] . ' · ' . $c['proveedor']
                 . ' · Venció hace ' . $dias . ' día' . ($dias !== 1 ? 's' : '')
                 . '. Renueva o marca como "No renovar".';

        self::crearUnaVezParaRol(
            [1, 2],
            $tipo,
            $titulo,
            $mensaje,
            'views/admin/contratos.php',
            'hourglass-split',
            'danger',
            168,
            $c['id']
        );
    }

    /**
     * Crea la notificación para todos los usuarios de los roles dados,
     * pero solo si no existe una igual en las últimas N horas.
     *
     * El campo `tipo` incluye el ID del contrato para diferenciar por contrato.
     */
    private static function crearUnaVezParaRol(
        array $roles,
        string $tipo,
        string $titulo,
        string $mensaje,
        string $url,
        string $icono,
        string $color,
        int $horas,
        int $contratoId
    ): void {
        $tipoConId = $tipo . '_' . $contratoId;

        $usuarios = Database::fetchAll("
            SELECT id FROM usuarios
            WHERE rol_id IN (" . implode(',', array_map('intval', $roles)) . ")
              AND activo = 1
        ");

        foreach ($usuarios as $u) {
            $usuarioId = (int)$u['id'];

            $existente = Database::fetchValue("
                SELECT COUNT(*) FROM notificaciones
                WHERE usuario_id = ?
                  AND tipo = ?
                  AND fecha_creacion > DATE_SUB(NOW(), INTERVAL ? HOUR)
            ", [$usuarioId, $tipoConId, $horas]);

            if ($existente) continue;

            try {
                Notificacion::crear(
                    $usuarioId,
                    $tipoConId,
                    $titulo,
                    $mensaje,
                    $url,
                    $icono,
                    $color
                );
            } catch (Throwable $e) {
                error_log('ContratoAvisos: error creando notificación para usuario '
                    . $usuarioId . ' (' . $tipoConId . '): ' . $e->getMessage());
            }
        }
    }
}