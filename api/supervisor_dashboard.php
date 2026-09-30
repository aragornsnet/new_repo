<?php
/**
 * IPV - API Dashboard del Supervisor
 * Puede ver TODOS los puntos de venta
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';

ApiBootstrap::iniciar(['Supervisor', 'Administrador']);

// ============================================================
// Stats del día
// ============================================================
$hoy = Database::fetchOne("
    SELECT 
        COUNT(*) AS num_ventas,
        COALESCE(SUM(total), 0) AS total,
        COALESCE(AVG(total), 0) AS ticket_promedio
    FROM ventas
    WHERE DATE(fecha) = CURDATE() AND estado = 'completada'
");

$cajaHoy = Database::fetchOne("
    SELECT 
        COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END), 0) AS efectivo,
        COALESCE(SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END), 0) AS transferencia
    FROM ventas v
    JOIN pagos_venta p ON p.venta_id = v.id
    WHERE DATE(v.fecha) = CURDATE() AND v.estado = 'completada'
");

// ============================================================
// Turnos abiertos ahora
// ============================================================
$turnosAbiertos = Database::fetchAll("
    SELECT 
        t.id, t.fecha_apertura, t.monto_inicial,
        u.nombre AS vendedor,
        pv.id AS pv_id, pv.nombre AS pv,
        (SELECT COUNT(*) FROM ventas v WHERE v.turno_id = t.id AND v.estado = 'completada') AS num_ventas,
        (SELECT COALESCE(SUM(v.total), 0) FROM ventas v WHERE v.turno_id = t.id AND v.estado = 'completada') AS total_vendido
    FROM turnos t
    JOIN usuarios u ON u.id = t.usuario_id
    JOIN puntos_venta pv ON pv.id = t.punto_venta_id
    WHERE t.estado = 'abierto'
    ORDER BY t.fecha_apertura DESC
");

// ============================================================
// Transferencias pendientes
// ============================================================
$transfPendientes = Database::fetchAll("
    SELECT 
        p.id AS pago_id, p.metodo_detalle, p.monto, p.referencia, p.comprobante,
        v.id AS venta_id, v.folio, v.fecha,
        u.nombre AS vendedor,
        pv.nombre AS pv
    FROM pagos_venta p
    JOIN ventas v ON v.id = p.venta_id
    JOIN usuarios u ON u.id = v.usuario_id
    JOIN puntos_venta pv ON pv.id = v.punto_venta_id
    WHERE p.metodo = 'transferencia' 
      AND p.verificado = 0
      AND p.motivo_rechazo IS NULL
      AND v.estado = 'completada'
    ORDER BY v.fecha DESC
    LIMIT 10
");

$totalTransfPendientes = (int) Database::fetchValue("
    SELECT COUNT(*) FROM pagos_venta p
    JOIN ventas v ON v.id = p.venta_id
    WHERE p.metodo = 'transferencia' 
      AND p.verificado = 0 
      AND p.motivo_rechazo IS NULL
      AND v.estado = 'completada'
", [], 0);

// ============================================================
// Alertas de stock
// ============================================================
$stockBajo = (int) Database::fetchValue("
    SELECT COUNT(*) FROM v_stock_actual WHERE alerta_stock = 1
", [], 0);

$stockNegativo = (int) Database::fetchValue("
    SELECT COUNT(*) FROM v_stock_actual WHERE stock_negativo = 1
", [], 0);

// ============================================================
// Ventas por PV (hoy)
// ============================================================
$ventasPorPV = Database::fetchAll("
    SELECT 
        pv.id, pv.nombre,
        COUNT(v.id) AS num_ventas,
        COALESCE(SUM(v.total), 0) AS total
    FROM puntos_venta pv
    LEFT JOIN ventas v ON v.punto_venta_id = pv.id 
        AND DATE(v.fecha) = CURDATE()
        AND v.estado = 'completada'
    WHERE pv.activo = 1
    GROUP BY pv.id, pv.nombre
    ORDER BY total DESC
");

// ============================================================
// Ranking rápido (hoy)
// ============================================================
$rankingHoy = Database::fetchAll("
    SELECT 
        u.id, u.nombre,
        pv.nombre AS pv,
        COUNT(v.id) AS num_ventas,
        COALESCE(SUM(v.total), 0) AS total
    FROM usuarios u
    LEFT JOIN puntos_venta pv ON pv.id = u.punto_venta_id
    LEFT JOIN ventas v ON v.usuario_id = u.id 
        AND DATE(v.fecha) = CURDATE() 
        AND v.estado = 'completada'
    WHERE u.rol_id = 3 AND u.activo = 1
    GROUP BY u.id, u.nombre, pv.nombre
    ORDER BY total DESC
");

// ============================================================
// Ventas últimos 7 días
// ============================================================
$ventas7dias = Database::fetchAll("
    SELECT 
        DATE(fecha) AS dia,
        COUNT(*) AS num_ventas,
        COALESCE(SUM(total), 0) AS total
    FROM ventas
    WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      AND estado = 'completada'
    GROUP BY DATE(fecha)
    ORDER BY dia ASC
");

// Rellenar días faltantes
$dias = [];
$totales = [];
for ($i = 6; $i >= 0; $i--) {
    $fecha = date('Y-m-d', strtotime("-$i days"));
    $dias[] = date('d/m', strtotime($fecha));
    $encontrado = false;
    foreach ($ventas7dias as $v) {
        if ($v['dia'] === $fecha) {
            $totales[] = (float) $v['total'];
            $encontrado = true;
            break;
        }
    }
    if (!$encontrado) $totales[] = 0;
}

// ============================================================
// Respuesta
// ============================================================
Response::ok([
    'hoy' => [
        'num_ventas'      => (int) $hoy['num_ventas'],
        'total'           => (float) $hoy['total'],
        'ticket_promedio' => (float) $hoy['ticket_promedio'],
        'efectivo'        => (float) $cajaHoy['efectivo'],
        'transferencia'   => (float) $cajaHoy['transferencia'],
    ],
    'turnos_abiertos'       => $turnosAbiertos,
    'transfer_pendientes'   => $transfPendientes,
    'alertas' => [
        'stock_bajo'          => $stockBajo,
        'stock_negativo'      => $stockNegativo,
        'transfer_pendientes' => $totalTransfPendientes,
        'turnos_abiertos'     => count($turnosAbiertos),
    ],
    'ventas_por_pv' => $ventasPorPV,
    'ranking_hoy'   => $rankingHoy,
    'grafico_7dias' => [
        'labels' => $dias,
        'data'   => $totales,
    ],
]);