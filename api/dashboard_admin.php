<?php
/**
 * IPV - API de estadísticas para el dashboard del Administrador
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';

ApiBootstrap::iniciar(['Administrador']);

// ------------------------------------------------------------
// Estadísticas del día
// ------------------------------------------------------------
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

// ------------------------------------------------------------
// Estadísticas del mes
// ------------------------------------------------------------
$mesActual = Database::fetchOne("
    SELECT 
        COUNT(*) AS num_ventas,
        COALESCE(SUM(total), 0) AS total
    FROM ventas
    WHERE MONTH(fecha) = MONTH(CURDATE()) 
      AND YEAR(fecha) = YEAR(CURDATE())
      AND estado = 'completada'
");

$mesAnterior = Database::fetchOne("
    SELECT 
        COUNT(*) AS num_ventas,
        COALESCE(SUM(total), 0) AS total
    FROM ventas
    WHERE MONTH(fecha) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
      AND YEAR(fecha) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
      AND estado = 'completada'
");

$totalMes = (float) $mesActual['total'];
$totalMesAnt = (float) $mesAnterior['total'];
$variacionMes = $totalMesAnt > 0 ? (($totalMes - $totalMesAnt) / $totalMesAnt) * 100 : 0;

// ------------------------------------------------------------
// Top 5 productos del mes
// ------------------------------------------------------------
$topProductos = Database::fetchAll("
    SELECT 
        pr.id, pr.nombre,
        SUM(dv.cantidad) AS unidades,
        SUM(dv.subtotal) AS total
    FROM detalle_ventas dv
    JOIN productos pr ON pr.id = dv.producto_id
    JOIN ventas v ON v.id = dv.venta_id
    WHERE MONTH(v.fecha) = MONTH(CURDATE())
      AND YEAR(v.fecha) = YEAR(CURDATE())
      AND v.estado = 'completada'
    GROUP BY pr.id, pr.nombre
    ORDER BY unidades DESC
    LIMIT 5
");

// ------------------------------------------------------------
// Ranking de vendedores (hoy)
// ------------------------------------------------------------
$rankingHoy = Database::fetchAll("
    SELECT 
        u.id, u.nombre,
        COUNT(v.id) AS num_ventas,
        COALESCE(SUM(v.total), 0) AS total
    FROM usuarios u
    LEFT JOIN ventas v ON v.usuario_id = u.id 
        AND DATE(v.fecha) = CURDATE() 
        AND v.estado = 'completada'
    WHERE u.rol_id = 3 AND u.activo = 1
    GROUP BY u.id, u.nombre
    ORDER BY total DESC
");

// ------------------------------------------------------------
// Alertas
// ------------------------------------------------------------
$stockBajo = (int) Database::fetchValue("
    SELECT COUNT(*) FROM v_stock_actual WHERE alerta_stock = 1
", [], 0);

$stockNegativo = (int) Database::fetchValue("
    SELECT COUNT(*) FROM v_stock_actual WHERE stock_negativo = 1
", [], 0);

$transfPendientes = (int) Database::fetchValue("
    SELECT COUNT(*) FROM v_transferencias_pendientes
", [], 0);

// ⭐ Contar turnos abiertos - consulta robusta
$turnosAbiertos = (int) Database::fetchValue("
    SELECT COUNT(*) FROM turnos WHERE estado = 'abierto'
");

// Log de depuración (comentar después)
error_log('Dashboard Admin - Turnos abiertos: ' . $turnosAbiertos);

// ------------------------------------------------------------
// Ventas últimos 7 días (para gráfico)
// ------------------------------------------------------------
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

// Rellenar días faltantes con 0
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

// ------------------------------------------------------------
// Ventas por PV (mes actual)
// ------------------------------------------------------------
$ventasPorPV = Database::fetchAll("
    SELECT 
        pv.id, pv.nombre,
        COUNT(v.id) AS num_ventas,
        COALESCE(SUM(v.total), 0) AS total
    FROM puntos_venta pv
    LEFT JOIN ventas v ON v.punto_venta_id = pv.id 
        AND MONTH(v.fecha) = MONTH(CURDATE())
        AND YEAR(v.fecha) = YEAR(CURDATE())
        AND v.estado = 'completada'
    WHERE pv.activo = 1
    GROUP BY pv.id, pv.nombre
    ORDER BY total DESC
");

// ------------------------------------------------------------
// Respuesta
// ------------------------------------------------------------
Response::ok([
    'hoy' => [
        'num_ventas'      => (int) $hoy['num_ventas'],
        'total'           => (float) $hoy['total'],
        'ticket_promedio' => (float) $hoy['ticket_promedio'],
        'efectivo'        => (float) $cajaHoy['efectivo'],
        'transferencia'   => (float) $cajaHoy['transferencia'],
    ],
    'mes' => [
        'total'          => $totalMes,
        'num_ventas'     => (int) $mesActual['num_ventas'],
        'total_anterior' => $totalMesAnt,
        'variacion'      => round($variacionMes, 2),
    ],
    'top_productos'   => $topProductos,
    'ranking_hoy'     => $rankingHoy,
    'alertas' => [
        'stock_bajo'          => $stockBajo,
        'stock_negativo'      => $stockNegativo,
        'transfer_pendientes' => $transfPendientes,
        'turnos_abiertos'     => $turnosAbiertos,
    ],
    'grafico_7dias' => [
        'labels' => $dias,
        'data'   => $totales,
    ],
    'ventas_por_pv' => $ventasPorPV,
]);