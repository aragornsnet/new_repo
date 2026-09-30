<?php
/**
 * IPV - API de Caja del Día (consolidado por vendedor)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';

ApiBootstrap::iniciar(['Supervisor', 'Administrador']);

$desde = trim($_GET['desde'] ?? '');
$hasta = trim($_GET['hasta'] ?? '');
$pvId = (int)($_GET['pv_id'] ?? 0);

if ($desde === '') $desde = date('Y-m-d');
if ($hasta === '') $hasta = $desde;

$condiciones = ['v.estado = \'completada\'', 'DATE(v.fecha) >= :desde', 'DATE(v.fecha) <= :hasta'];
$params = [':desde' => $desde, ':hasta' => $hasta];

if ($pvId > 0) {
    $condiciones[] = 'v.punto_venta_id = :pv_id';
    $params[':pv_id'] = $pvId;
}

$where = 'WHERE ' . implode(' AND ', $condiciones);

// Consolidado por vendedor
$porVendedor = Database::fetchAll("
    SELECT 
        u.id AS vendedor_id, u.nombre AS vendedor,
        pv.id AS pv_id, pv.nombre AS pv,
        COUNT(DISTINCT v.id) AS num_ventas,
        COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END), 0) AS total_efectivo,
        COALESCE(SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END), 0) AS total_transferencia,
        COALESCE(SUM(p.monto), 0) AS total_general
    FROM usuarios u
    JOIN puntos_venta pv ON pv.id = u.punto_venta_id
    LEFT JOIN ventas v ON v.usuario_id = u.id 
        AND v.estado = 'completada'
        AND DATE(v.fecha) >= :desde 
        AND DATE(v.fecha) <= :hasta
        " . ($pvId > 0 ? "AND v.punto_venta_id = :pv_id_join" : "") . "
    LEFT JOIN pagos_venta p ON p.venta_id = v.id
    WHERE u.rol_id = 3 AND u.activo = 1
    GROUP BY u.id, u.nombre, pv.id, pv.nombre
    ORDER BY total_general DESC
", array_merge($params, $pvId > 0 ? [':pv_id_join' => $pvId] : []));

// Consolidado total
$totales = Database::fetchOne("
    SELECT 
        COUNT(DISTINCT v.id) AS num_ventas,
        COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END), 0) AS total_efectivo,
        COALESCE(SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END), 0) AS total_transferencia,
        COALESCE(SUM(p.monto), 0) AS total_general
    FROM ventas v
    JOIN pagos_venta p ON p.venta_id = v.id
    $where
", $params);

// Consolidado por PV
$porPV = Database::fetchAll("
    SELECT 
        pv.id AS pv_id, pv.nombre AS pv,
        COUNT(DISTINCT v.id) AS num_ventas,
        COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END), 0) AS total_efectivo,
        COALESCE(SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END), 0) AS total_transferencia,
        COALESCE(SUM(p.monto), 0) AS total_general
    FROM puntos_venta pv
    LEFT JOIN ventas v ON v.punto_venta_id = pv.id 
        AND v.estado = 'completada'
        AND DATE(v.fecha) >= :desde 
        AND DATE(v.fecha) <= :hasta
    LEFT JOIN pagos_venta p ON p.venta_id = v.id
    WHERE pv.activo = 1
    GROUP BY pv.id, pv.nombre
    ORDER BY total_general DESC
", $params);

Response::ok([
    'por_vendedor' => $porVendedor,
    'por_pv' => $porPV,
    'totales' => $totales,
    'rango' => [
        'desde' => $desde,
        'hasta' => $hasta,
    ],
]);