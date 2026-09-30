<?php
/**
 * IPV - API de Ranking de Vendedores (Supervisor y Administrador)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';

ApiBootstrap::iniciar(['Supervisor', 'Administrador']);

$accion = $_GET['accion'] ?? 'listar';

switch ($accion) {

    // ============================================================
    // RANKING general
    // ============================================================
    case 'listar':
        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');
        $pvId = (int)($_GET['pv_id'] ?? 0);
        $incluirSinVentas = ($_GET['incluir_sin_ventas'] ?? '0') === '1';

        // Default: este mes
        if ($desde === '') $desde = date('Y-m-01');
        if ($hasta === '') $hasta = date('Y-m-d');

        $condiciones = [
            'v.estado = \'completada\'',
            'DATE(v.fecha) >= :desde',
            'DATE(v.fecha) <= :hasta',
        ];
        $params = [
            ':desde' => $desde,
            ':hasta' => $hasta,
        ];

        if ($pvId > 0) {
            $condiciones[] = 'v.punto_venta_id = :pv_id';
            $params[':pv_id'] = $pvId;
        }

        $where = 'WHERE ' . implode(' AND ', $condiciones);

        // Ranking por vendedor
        $ranking = Database::fetchAll("
            SELECT 
                u.id AS vendedor_id,
                u.nombre AS vendedor,
                pv.nombre AS pv,
                COUNT(DISTINCT v.id) AS num_ventas,
                COALESCE(SUM(DISTINCT v.total), 0) AS total_vendido,
                COALESCE(AVG(DISTINCT v.total), 0) AS ticket_promedio,
                COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END), 0) AS total_efectivo,
                COALESCE(SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END), 0) AS total_transferencia,
                COALESCE(SUM(p.monto), 0) AS total_pagos
            FROM usuarios u
            JOIN puntos_venta pv ON pv.id = u.punto_venta_id
            LEFT JOIN ventas v ON v.usuario_id = u.id 
                AND v.estado = 'completada'
                AND DATE(v.fecha) >= :desde 
                AND DATE(v.fecha) <= :hasta
                " . ($pvId > 0 ? "AND v.punto_venta_id = :pv_id_join" : "") . "
            LEFT JOIN pagos_venta p ON p.venta_id = v.id
            WHERE u.rol_id = 3 AND u.activo = 1
            GROUP BY u.id, u.nombre, pv.nombre
            " . ($incluirSinVentas ? "" : "HAVING num_ventas > 0") . "
            ORDER BY total_vendido DESC, num_ventas DESC
        ", array_merge($params, $pvId > 0 ? [':pv_id_join' => $pvId] : []));

        // Totales generales
        $totales = Database::fetchOne("
            SELECT 
                COUNT(DISTINCT v.id) AS total_ventas,
                COALESCE(SUM(DISTINCT v.total), 0) AS total_vendido,
                COALESCE(AVG(DISTINCT v.total), 0) AS ticket_promedio,
                COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END), 0) AS total_efectivo,
                COALESCE(SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END), 0) AS total_transferencia
            FROM ventas v
            JOIN pagos_venta p ON p.venta_id = v.id
            $where
        ", $params);

        // Comparativa con período anterior (misma duración)
        $diasPeriodo = (strtotime($hasta) - strtotime($desde)) / 86400 + 1;
        $desdeAnterior = date('Y-m-d', strtotime($desde . ' -' . $diasPeriodo . ' days'));
        $hastaAnterior = date('Y-m-d', strtotime($hasta . ' -' . $diasPeriodo . ' days'));

        $anterior = Database::fetchOne("
            SELECT 
                COUNT(*) AS num_ventas,
                COALESCE(SUM(total), 0) AS total_vendido
            FROM ventas
            WHERE estado = 'completada'
              AND DATE(fecha) >= ?
              AND DATE(fecha) <= ?
        ", [$desdeAnterior, $hastaAnterior]);

        $variacion = ((float)$anterior['total_vendido'] > 0)
            ? ((float)$totales['total_vendido'] - (float)$anterior['total_vendido']) / (float)$anterior['total_vendido'] * 100
            : 0;

        Response::ok([
            'ranking' => $ranking,
            'totales' => $totales,
            'comparativa' => [
                'periodo_actual'   => ['desde' => $desde, 'hasta' => $hasta],
                'periodo_anterior' => ['desde' => $desdeAnterior, 'hasta' => $hastaAnterior, 'total' => (float)$anterior['total_vendido'], 'ventas' => (int)$anterior['num_ventas']],
                'variacion'        => round($variacion, 2),
            ],
        ]);
        break;

    // ============================================================
    // TOP productos por vendedor
    // ============================================================
    case 'top_productos':
        $vendedorId = (int)($_GET['vendedor_id'] ?? 0);
        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');
        $limit = min(max((int)($_GET['limit'] ?? 5), 1), 50);

        if ($vendedorId <= 0) Response::error('Vendedor requerido');

        if ($desde === '') $desde = date('Y-m-01');
        if ($hasta === '') $hasta = date('Y-m-d');

        $sql = "
            SELECT 
                pr.id AS producto_id,
                pr.nombre AS producto,
                pr.codigo_barras,
                SUM(dv.cantidad) AS unidades,
                SUM(dv.subtotal) AS total_vendido,
                COUNT(DISTINCT v.id) AS num_ventas
            FROM detalle_ventas dv
            JOIN productos pr ON pr.id = dv.producto_id
            JOIN ventas v ON v.id = dv.venta_id
            WHERE v.usuario_id = :vendedor_id
              AND v.estado = 'completada'
              AND DATE(v.fecha) >= :desde
              AND DATE(v.fecha) <= :hasta
            GROUP BY pr.id, pr.nombre, pr.codigo_barras
            ORDER BY unidades DESC
            LIMIT $limit
        ";

        $productos = Database::fetchAll($sql, [
            ':vendedor_id' => $vendedorId,
            ':desde' => $desde,
            ':hasta' => $hasta,
        ]);

        Response::ok($productos);
        break;

    // ============================================================
    // EVOLUCIÓN diaria de un vendedor
    // ============================================================
    case 'evolucion':
        $vendedorId = (int)($_GET['vendedor_id'] ?? 0);
        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');

        if ($vendedorId <= 0) Response::error('Vendedor requerido');

        if ($desde === '') $desde = date('Y-m-d', strtotime('-30 days'));
        if ($hasta === '') $hasta = date('Y-m-d');

        $evolucion = Database::fetchAll("
            SELECT 
                DATE(v.fecha) AS dia,
                COUNT(*) AS num_ventas,
                COALESCE(SUM(v.total), 0) AS total
            FROM ventas v
            WHERE v.usuario_id = ?
              AND v.estado = 'completada'
              AND DATE(v.fecha) >= ?
              AND DATE(v.fecha) <= ?
            GROUP BY DATE(v.fecha)
            ORDER BY dia ASC
        ", [$vendedorId, $desde, $hasta]);

        Response::ok($evolucion);
        break;

    // ============================================================
    // CATÁLOGOS
    // ============================================================
    case 'catalogos':
        $pvs = Database::fetchAll("
            SELECT id, nombre FROM puntos_venta WHERE activo = 1 ORDER BY nombre
        ");

        Response::ok([
            'puntos_venta' => $pvs,
            'hoy' => date('Y-m-d'),
            'inicio_mes' => date('Y-m-01'),
            'inicio_mes_anterior' => date('Y-m-01', strtotime('first day of last month')),
            'fin_mes_anterior' => date('Y-m-t', strtotime('last day of last month')),
        ]);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}