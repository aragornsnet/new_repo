<?php
/**
 * IPV - API de Ventas para Supervisor/Administrador
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Notificacion.php';

ApiBootstrap::iniciar(['Supervisor', 'Administrador']);

$accion = $_GET['accion'] ?? 'listar';

switch ($accion) {

    case 'listar':
        $q = trim($_GET['q'] ?? '');
        $pvId = (int)($_GET['pv_id'] ?? 0);
        $vendedorId = (int)($_GET['vendedor_id'] ?? 0);
        $metodo = trim($_GET['metodo'] ?? '');
        $estado = trim($_GET['estado'] ?? '');
        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');

        $condiciones = [];
        $params = [];

        if ($q !== '') {
            $condiciones[] = '(v.folio LIKE :q1 OR u.nombre LIKE :q2)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
        }
        if ($pvId > 0) {
            $condiciones[] = 'v.punto_venta_id = :pv_id';
            $params[':pv_id'] = $pvId;
        }
        if ($vendedorId > 0) {
            $condiciones[] = 'v.usuario_id = :vendedor_id';
            $params[':vendedor_id'] = $vendedorId;
        }
        if ($estado === 'completada' || $estado === 'cancelada') {
            $condiciones[] = 'v.estado = :estado';
            $params[':estado'] = $estado;
        }

        if ($metodo !== '') {
            $condiciones[] = 'EXISTS (
                SELECT 1 FROM pagos_venta p2 
                WHERE p2.venta_id = v.id AND p2.metodo = :metodo
            )';
            $params[':metodo'] = $metodo;
        }

        if ($desde !== '') {
            $condiciones[] = 'v.fecha >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        } else {
            $condiciones[] = 'DATE(v.fecha) = CURDATE()';
        }
        if ($hasta !== '') {
            $condiciones[] = 'v.fecha <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $ventas = Database::fetchAll("
            SELECT 
                v.id, v.folio, v.fecha, v.subtotal, v.total, v.estado, v.moneda,
                v.motivo_cancelacion,
                pv.id AS pv_id, pv.nombre AS pv,
                u.id AS vendedor_id, u.nombre AS vendedor,
                t.id AS turno_id,
                (SELECT COUNT(*) FROM detalle_ventas dv WHERE dv.venta_id = v.id) AS num_productos,
                (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'efectivo') AS pago_efectivo,
                (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'transferencia') AS pago_transferencia
            FROM ventas v
            JOIN puntos_venta pv ON pv.id = v.punto_venta_id
            JOIN usuarios u ON u.id = v.usuario_id
            LEFT JOIN turnos t ON t.id = v.turno_id
            $where
            ORDER BY v.fecha DESC
            LIMIT 1000
        ", $params);

        $totalEfectivo = 0;
        $totalTransferencia = 0;
        $totalGeneral = 0;
        $numVentas = 0;

        foreach ($ventas as $v) {
            if ($v['estado'] === 'completada') {
                $totalEfectivo += (float) $v['pago_efectivo'];
                $totalTransferencia += (float) $v['pago_transferencia'];
                $totalGeneral += (float) $v['total'];
                $numVentas++;
            }
        }

        Response::ok([
            'datos' => $ventas,
            'totales' => [
                'num_ventas'        => $numVentas,
                'total'             => $totalGeneral,
                'total_efectivo'    => $totalEfectivo,
                'total_transferencia'=> $totalTransferencia,
            ],
        ]);
        break;

    case 'obtener':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $venta = Database::fetchOne("
            SELECT 
                v.*,
                pv.nombre AS pv, pv.direccion AS pv_direccion,
                u.nombre AS vendedor, u.email AS vendedor_email,
                t.id AS turno_id, t.fecha_apertura AS turno_apertura,
                uc.nombre AS cancelada_por_nombre
            FROM ventas v
            JOIN puntos_venta pv ON pv.id = v.punto_venta_id
            JOIN usuarios u ON u.id = v.usuario_id
            LEFT JOIN turnos t ON t.id = v.turno_id
            LEFT JOIN usuarios uc ON uc.id = v.cancelada_por
            WHERE v.id = ?
        ", [$id]);

        if (!$venta) Response::noEncontrado('Venta no encontrada');

        $venta['detalle'] = Database::fetchAll("
            SELECT 
                dv.id, dv.cantidad, dv.precio_unitario, dv.subtotal,
                p.nombre AS producto, p.codigo_barras,
                p.unidad_medida
            FROM detalle_ventas dv
            JOIN productos p ON p.id = dv.producto_id
            WHERE dv.venta_id = ?
            ORDER BY dv.id
        ", [$id]);

        $venta['pagos'] = Database::fetchAll("
            SELECT 
                p.id, p.metodo, p.metodo_detalle, p.monto, p.moneda, p.monto_divisa,
                p.referencia, p.ultimos_digitos, p.titular, p.banco,
                p.comprobante, p.verificado, p.fecha_verificacion,
                u.nombre AS verificado_por_nombre
            FROM pagos_venta p
            LEFT JOIN usuarios u ON u.id = p.verificado_por
            WHERE p.venta_id = ?
            ORDER BY p.id
        ", [$id]);

        $venta['denominaciones'] = Database::fetchAll("
            SELECT 
                vd.tipo_movimiento, vd.cantidad, vd.subtotal,
                d.valor, d.tipo AS tipo_denominacion, d.moneda
            FROM venta_denominaciones vd
            JOIN denominaciones d ON d.id = vd.denominacion_id
            WHERE vd.venta_id = ?
            ORDER BY vd.tipo_movimiento DESC, d.valor DESC
        ", [$id]);

        Response::ok($venta);
        break;

    case 'catalogos':
        $pvs = Database::fetchAll("
            SELECT id, nombre FROM puntos_venta WHERE activo = 1 ORDER BY nombre
        ");

        $vendedores = Database::fetchAll("
            SELECT u.id, u.nombre, pv.nombre AS pv
            FROM usuarios u
            LEFT JOIN puntos_venta pv ON pv.id = u.punto_venta_id
            WHERE u.rol_id = 3 AND u.activo = 1
            ORDER BY u.nombre
        ");

        Response::ok([
            'puntos_venta' => $pvs,
            'vendedores' => $vendedores,
        ]);
        break;

    case 'cancelar':
        if (metodoHttp() !== 'POST') Response::error('Método no permitido');

        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        $motivo = trim($body['motivo'] ?? '');

        if ($id <= 0) Response::error('ID inválido');
        if ($motivo === '') Response::error('Motivo requerido');

        $venta = Database::fetchOne("SELECT * FROM ventas WHERE id = ?", [$id]);
        if (!$venta) Response::noEncontrado('Venta no encontrada');

        if ($venta['estado'] === 'cancelada') {
            Response::error('La venta ya está cancelada');
        }

        try {
            Database::begin();

            Database::update('ventas', [
                'estado' => 'cancelada',
                'motivo_cancelacion' => $motivo,
                'cancelada_por' => Auth::id(),
                'fecha_cancelacion' => date('Y-m-d H:i:s'),
            ], 'id = :id', [':id' => $id]);

            $detalle = Database::fetchAll("
                SELECT producto_id, cantidad FROM detalle_ventas WHERE venta_id = ?
            ", [$id]);

            foreach ($detalle as $d) {
                Database::query("
                    UPDATE stock_punto_venta 
                    SET stock = stock + :cantidad
                    WHERE producto_id = :pid AND punto_venta_id = :pvid
                ", [
                    ':cantidad' => $d['cantidad'],
                    ':pid' => $d['producto_id'],
                    ':pvid' => $venta['punto_venta_id'],
                ]);

                $stockActual = Database::fetchOne("
                    SELECT stock FROM stock_punto_venta 
                    WHERE producto_id = ? AND punto_venta_id = ?
                ", [$d['producto_id'], $venta['punto_venta_id']]);

                Database::insert('movimientos', [
                    'producto_id'    => $d['producto_id'],
                    'punto_venta_id' => $venta['punto_venta_id'],
                    'turno_id'       => $venta['turno_id'],
                    'tipo'           => 'entrada',
                    'cantidad'       => $d['cantidad'],
                    'motivo'         => 'Cancelación de venta ' . $venta['folio'],
                    'valor_anterior' => (int)$stockActual['stock'] - $d['cantidad'],
                    'valor_nuevo'    => (int)$stockActual['stock'],
                    'usuario_id'     => Auth::id(),
                ]);
            }

            Auditoria::registrar('venta_cancelada', 'ventas', $id, [
                'folio' => $venta['folio'],
                'motivo' => $motivo,
                'monto' => (float)$venta['total'],
            ]);

            Notificacion::crearParaAdmins(
                'venta_cancelada',
                'Venta cancelada',
                'Folio ' . $venta['folio'] . ' - Motivo: ' . $motivo,
                'views/admin/ventas.php',
                'x-circle-fill',
                'danger'
            );

            Database::commit();

            Response::ok(null, 'Venta cancelada correctamente');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error cancelar venta: ' . $e->getMessage());
            Response::servidor('No se pudo cancelar la venta');
        }
        break;

    default:
        Response::error('Acción no reconocida', 404);
}