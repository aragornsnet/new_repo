<?php
/**
 * IPV - API de Mis Ventas (vendedor ve sus ventas del turno actual)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';

ApiBootstrap::iniciar(['Vendedor', 'Supervisor', 'Administrador']);

$vendedorId = Auth::id();
$accion = $_GET['accion'] ?? 'listar';

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

switch ($accion) {

    case 'listar':
        $q = trim($_GET['q'] ?? '');
        $metodo = trim($_GET['metodo'] ?? '');
        $estado = trim($_GET['estado'] ?? '');

        $condiciones = ['v.turno_id = :turno_id'];
        $params = [':turno_id' => $turnoId];

        if ($q !== '') {
            $condiciones[] = 'v.folio LIKE :q1';
            $params[':q1'] = "%$q%";
        }

        if ($metodo !== '') {
            $condiciones[] = 'EXISTS (
                SELECT 1 FROM pagos_venta p2 
                WHERE p2.venta_id = v.id AND p2.metodo = :metodo
            )';
            $params[':metodo'] = $metodo;
        }

        if ($estado === 'completada' || $estado === 'cancelada') {
            $condiciones[] = 'v.estado = :estado';
            $params[':estado'] = $estado;
        }

        $where = 'WHERE ' . implode(' AND ', $condiciones);

        $ventas = Database::fetchAll("
            SELECT 
                v.id, v.folio, v.fecha, v.subtotal, v.total, v.estado, v.moneda,
                v.total_divisa, v.tasa_aplicada,
                v.motivo_cancelacion,
                (SELECT COUNT(*) FROM detalle_ventas dv WHERE dv.venta_id = v.id) AS num_productos,
                (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'efectivo') AS pago_efectivo,
                (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'transferencia') AS pago_transferencia
            FROM ventas v
            $where
            ORDER BY v.fecha DESC
            LIMIT 500
        ", $params);

        $totales = [
            'num_ventas'         => 0,
            'total'              => 0,
            'total_efectivo'     => 0,
            'total_transferencia'=> 0,
        ];

        foreach ($ventas as $v) {
            if ($v['estado'] === 'completada') {
                $totales['num_ventas']++;
                $totales['total'] += (float) $v['total'];
                $totales['total_efectivo'] += (float) $v['pago_efectivo'];
                $totales['total_transferencia'] += (float) $v['pago_transferencia'];
            }
        }

        Response::ok([
            'datos' => $ventas,
            'totales' => $totales,
            'turno' => [
                'id' => $turnoId,
                'pv' => $turno['pv'],
            ],
        ]);
        break;

    case 'detalle':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $venta = Database::fetchOne("
            SELECT 
                v.*,
                t.fecha_apertura AS turno_apertura
            FROM ventas v
            JOIN turnos t ON t.id = v.turno_id
            WHERE v.id = ? AND v.turno_id = ?
        ", [$id, $turnoId]);

        if (!$venta) Response::noEncontrado('Venta no encontrada en tu turno');

        $venta['detalle'] = Database::fetchAll("
            SELECT 
                dv.cantidad, dv.precio_unitario, dv.subtotal,
                p.nombre AS producto, p.codigo_barras,
                p.unidad_medida
            FROM detalle_ventas dv
            JOIN productos p ON p.id = dv.producto_id
            WHERE dv.venta_id = ?
        ", [$id]);

        $venta['pagos'] = Database::fetchAll("
            SELECT 
                metodo, metodo_detalle, monto, moneda, monto_divisa,
                referencia, ultimos_digitos, titular, banco, comprobante,
                verificado, motivo_rechazo
            FROM pagos_venta
            WHERE venta_id = ?
        ", [$id]);

        Response::ok($venta);
        break;

    case 'exportar':
        $ventas = Database::fetchAll("
            SELECT 
                v.folio, v.fecha, v.total, v.estado, v.moneda,
                (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'efectivo') AS efectivo,
                (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'transferencia') AS transferencia
            FROM ventas v
            WHERE v.turno_id = ?
            ORDER BY v.fecha DESC
        ", [$turnoId]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="mis_ventas_turno_' . $turnoId . '_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($out, ['Folio', 'Fecha', 'Total', 'Efectivo', 'Transferencia', 'Moneda', 'Estado']);

        foreach ($ventas as $v) {
            fputcsv($out, [
                $v['folio'],
                $v['fecha'],
                $v['total'],
                $v['efectivo'],
                $v['transferencia'],
                $v['moneda'],
                $v['estado'],
            ]);
        }
        fclose($out);
        exit;

    default:
        Response::error('Acción no reconocida', 404);
}