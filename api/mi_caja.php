<?php
/**
 * IPV - API de Mi Caja (resumen de efectivo del turno actual)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';

ApiBootstrap::iniciar(['Vendedor', 'Supervisor', 'Administrador']);

$vendedorId = Auth::id();

// Verificar turno activo
$turno = Database::fetchOne("
    SELECT t.*, pv.nombre AS pv
    FROM turnos t
    JOIN puntos_venta pv ON pv.id = t.punto_venta_id
    WHERE t.usuario_id = ? AND t.estado = 'abierto'
    LIMIT 1
", [$vendedorId]);

if (!$turno) {
    Response::error('No tienes un turno abierto', 403);
}

$turnoId = (int) $turno['id'];

// ============================================================
// RESUMEN DEL EFECTIVO
// ============================================================
$resumen = Database::fetchOne("
    SELECT 
        COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END), 0) AS total_efectivo,
        COALESCE(SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END), 0) AS total_transferencia,
        COALESCE(SUM(p.monto), 0) AS total_general
    FROM pagos_venta p
    JOIN ventas v ON v.id = p.venta_id
    WHERE v.turno_id = ? AND v.estado = 'completada'
", [$turnoId]);

$numVentas = (int) Database::fetchValue("
    SELECT COUNT(*) FROM ventas 
    WHERE turno_id = ? AND estado = 'completada'
", [$turnoId], 0);

$numVentasEfectivo = (int) Database::fetchValue("
    SELECT COUNT(DISTINCT v.id) FROM ventas v
    JOIN pagos_venta p ON p.venta_id = v.id
    WHERE v.turno_id = ? AND v.estado = 'completada' AND p.metodo = 'efectivo'
", [$turnoId], 0);

// Efectivo teórico actual = monto_inicial + ventas efectivo
$efectivoTeorico = (float) $turno['monto_inicial'] + (float) $resumen['total_efectivo'];

// ============================================================
// MOVIMIENTOS DE EFECTIVO DETALLADOS
// ============================================================
$movimientos = Database::fetchAll("
    SELECT 
        v.id, v.folio, v.fecha, v.total,
        (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'efectivo') AS monto_efectivo,
        (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'transferencia') AS monto_transferencia
    FROM ventas v
    WHERE v.turno_id = ? 
      AND v.estado = 'completada'
      AND EXISTS (SELECT 1 FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'efectivo')
    ORDER BY v.fecha DESC
", [$turnoId]);

// ============================================================
// VENTAS EN DIVISAS
// ============================================================
$ventasDivisa = Database::fetchAll("
    SELECT 
        v.moneda,
        COUNT(*) AS num_ventas,
        COALESCE(SUM(v.total_divisa), 0) AS total_divisa,
        MAX(v.tasa_aplicada) AS tasa
    FROM ventas v
    WHERE v.turno_id = ? AND v.estado = 'completada' AND v.moneda != 'CUP'
    GROUP BY v.moneda
", [$turnoId]);

Response::ok([
    'turno' => [
        'id'             => $turnoId,
        'pv'             => $turno['pv'],
        'fecha_apertura' => $turno['fecha_apertura'],
        'monto_inicial'  => (float) $turno['monto_inicial'],
        'duracion_seg'   => time() - strtotime($turno['fecha_apertura']),
    ],
    'resumen' => [
        'num_ventas'              => $numVentas,
        'num_ventas_efectivo'     => $numVentasEfectivo,
        'total_efectivo'          => (float) $resumen['total_efectivo'],
        'total_transferencia'     => (float) $resumen['total_transferencia'],
        'total_general'           => (float) $resumen['total_general'],
        'efectivo_teorico'        => $efectivoTeorico,
    ],
    'movimientos' => $movimientos,
    'ventas_divisa' => $ventasDivisa,
]);