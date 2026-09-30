<?php
/**
 * IPV - Registro de auditoría
 * Guarda un histórico de todas las acciones importantes del sistema
 */

require_once __DIR__ . '/Database.php';

class Auditoria
{
    /**
     * Registra una acción en la auditoría
     */
    public static function registrar(
        string $accion,
        ?string $tabla = null,
        ?int $registroId = null,
        $detalle = null
    ): void {
        try {
            $usuarioId = $_SESSION['user']['id'] ?? null;
            $ip = self::obtenerIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            if (is_array($detalle) || is_object($detalle)) {
                $detalle = json_encode($detalle, JSON_UNESCAPED_UNICODE);
            }

            Database::insert('auditoria', [
                'usuario_id'     => $usuarioId,
                'accion'         => $accion,
                'tabla_afectada' => $tabla,
                'registro_id'    => $registroId,
                'detalle'        => $detalle,
                'ip'             => $ip,
                'user_agent'     => $userAgent ? substr($userAgent, 0, 255) : null,
            ]);
        } catch (Throwable $e) {
            // Nunca romper la app por un fallo de auditoría
            error_log('Auditoria error: ' . $e->getMessage());
        }
    }

    /**
     * Devuelve el listado paginado de auditoría
     */
    public static function listar(array $filtros = [], int $pagina = 1, int $porPagina = 50): array
    {
        $condiciones = [];
        $params = [];

        if (!empty($filtros['usuario_id'])) {
            $condiciones[] = 'a.usuario_id = :usuario_id';
            $params[':usuario_id'] = (int) $filtros['usuario_id'];
        }
        if (!empty($filtros['accion'])) {
            $condiciones[] = 'a.accion = :accion';
            $params[':accion'] = $filtros['accion'];
        }
        if (!empty($filtros['tabla'])) {
            $condiciones[] = 'a.tabla_afectada = :tabla';
            $params[':tabla'] = $filtros['tabla'];
        }
        if (!empty($filtros['desde'])) {
            $condiciones[] = 'a.fecha >= :desde';
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $condiciones[] = 'a.fecha <= :hasta';
            $params[':hasta'] = $filtros['hasta'];
        }
        if (!empty($filtros['busqueda'])) {
            $condiciones[] = '(a.detalle LIKE :busqueda OR a.accion LIKE :busqueda)';
            $params[':busqueda'] = '%' . $filtros['busqueda'] . '%';
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $total = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM auditoria a $where",
            $params,
            0
        );

        $offset = max(0, ($pagina - 1) * $porPagina);

        $filas = Database::fetchAll(
            "SELECT 
                a.*,
                u.nombre AS usuario_nombre,
                u.email AS usuario_email
             FROM auditoria a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             $where
             ORDER BY a.fecha DESC, a.id DESC
             LIMIT $porPagina OFFSET $offset",
            $params
        );

        return [
            'total'       => $total,
            'pagina'      => $pagina,
            'por_pagina'  => $porPagina,
            'total_pags'  => (int) ceil($total / $porPagina),
            'datos'       => $filas,
        ];
    }

    /**
     * Obtiene la IP real del cliente (respetando proxies)
     */
    public static function obtenerIP(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',   // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = explode(',', $_SERVER[$h])[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}