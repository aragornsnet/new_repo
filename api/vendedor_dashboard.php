<?php
/**
 * IPV - API Dashboard del Vendedor
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';

ApiBootstrap::iniciar(['Vendedor', 'Supervisor', 'Administrador']);

$vendedorId = Auth::id();
$pvId = Auth::puntoVentaId();

// ============================================================
// Turno actual del vendedor
// ============================================================
$turnoActual = Database::fetchOne("
    SELECT 
        t.id, t.fecha_apertura, t.monto_inicial, t.estado,
        pv.nombre AS pv, pv.id AS pv_id
    FROM turnos t
    JOIN puntos_venta pv ON pv.id = t.punto_venta_id
    WHERE t.usuario_id = ? AND t.estado = 'abierto'
    ORDER BY t.fecha_apertura DESC
    LIMIT 1
", [$vendedorId]);

// ============================================================
// Si no hay turno, devolver solo datos mínimos
// ============================================================
if (!$turnoActual) {
    // Productos con stock bajo en su PV (info útil)
    $stockBajo = (int) Database::fetchValue("
        SELECT COUNT(*) FROM v_stock_actual 
        WHERE punto_venta_id = ? AND alerta_stock = 1
    ", [$pvId], 0);

    $stockNegativo = (int) Database::fetchValue("
        SELECT COUNT(*) FROM v_stock_actual 
        WHERE punto_venta_id = ? AND stock_negativo = 1
    ", [$pvId], 0);

    Response::ok([
        'turno' => null,
        'alertas' => [
            'stock_bajo' => $stockBajo,
            'stock_negativo' => $stockNegativo,
        ],
    ]);
    exit;
}

// ============================================================
// Stats del turno
// ============================================================
$turnoId = (int) $turnoActual['id'];

$stats = Database::fetchOne("
    SELECT 
        COUNT(*) AS num_ventas,
        COALESCE(SUM(total), 0) AS total_vendido
    FROM ventas
    WHERE turno_id = ? AND estado = 'completada'
", [$turnoId]);

$caja = Database::fetchOne("
    SELECT 
        COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END), 0) AS total_efectivo,
        COALESCE(SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END), 0) AS total_transferencia
    FROM pagos_venta p
    JOIN ventas v ON v.id = p.venta_id
    WHERE v.turno_id = ? AND v.estado = 'completada'
", [$turnoId]);

// ============================================================
// Últimas 5 ventas del turno
// ============================================================
$ultimasVentas = Database::fetchAll("
    SELECT 
        v.id, v.folio, v.fecha, v.total, v.estado,
        (SELECT COUNT(*) FROM detalle_ventas dv WHERE dv.venta_id = v.id) AS num_productos
    FROM ventas v
    WHERE v.turno_id = ?
    ORDER BY v.fecha DESC
    LIMIT 5
", [$turnoId]);

// ============================================================
// Top 3 productos del turno
// ============================================================
$topProductos = Database::fetchAll("
    SELECT 
        p.id, p.nombre,
        SUM(dv.cantidad) AS unidades,
        SUM(dv.subtotal) AS total
    FROM detalle_ventas dv
    JOIN productos p ON p.id = dv.producto_id
    JOIN ventas v ON v.id = dv.venta_id
    WHERE v.turno_id = ? AND v.estado = 'completada'
    GROUP BY p.id, p.nombre
    ORDER BY unidades DESC
    LIMIT 3
", [$turnoId]);

// ============================================================
// Alertas de stock
// ============================================================
$stockBajo = (int) Database::fetchValue("
    SELECT COUNT(*) FROM v_stock_actual 
    WHERE punto_venta_id = ? AND alerta_stock = 1
", [$pvId], 0);

$stockNegativo = (int) Database::fetchValue("
    SELECT COUNT(*) FROM v_stock_actual 
    WHERE punto_venta_id = ? AND stock_negativo = 1
", [$pvId], 0);

// ============================================================
// Productos disponibles en su PV
// ============================================================
$productosDisponibles = (int) Database::fetchValue("
    SELECT COUNT(*) FROM stock_punto_venta spv
    JOIN productos p ON p.id = spv.producto_id
    WHERE spv.punto_venta_id = ? AND spv.stock > 0 AND p.activo = 1
", [$pvId], 0);

// ============================================================
// Duración del turno
// ============================================================
$apertura = strtotime($turnoActual['fecha_apertura']);
$duracionSeg = time() - $apertura;

// ============================================================
// Respuesta
// ============================================================
Response::ok([
    'turno' => [
        'id'             => (int) $turnoActual['id'],
        'fecha_apertura' => $turnoActual['fecha_apertura'],
        'monto_inicial'  => (float) $turnoActual['monto_inicial'],
        'pv'             => $turnoActual['pv'],
        'pv_id'          => (int) $turnoActual['pv_id'],
        'duracion_seg'   => $duracionSeg,
    ],
    'stats' => [
        'num_ventas'        => (int) $stats['num_ventas'],
        'total_vendido'     => (float) $stats['total_vendido'],
        'total_efectivo'    => (float) $caja['total_efectivo'],
        'total_transferencia'=> (float) $caja['total_transferencia'],
    ],
    'ultimas_ventas' => $ultimasVentas,
    'top_productos'  => $topProductos,
    'alertas' => [
        'stock_bajo'          => $stockBajo,
        'stock_negativo'      => $stockNegativo,
        'productos_disponibles' => $productosDisponibles,
    ],
]);