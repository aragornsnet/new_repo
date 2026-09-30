<?php
/**
 * IPV - API de Transferencias (Supervisor y Administrador)
 * Permite verificar y rechazar transferencias de pago
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Auditoria.php';

ApiBootstrap::iniciar(['Supervisor', 'Administrador']);

$accion = $_GET['accion'] ?? 'listar';

switch ($accion) {

    // ============================================================
    // LISTAR transferencias con filtros
    // ============================================================
    case 'listar':
        $q = trim($_GET['q'] ?? '');
        $estado = trim($_GET['estado'] ?? '');
        $pvId = (int)($_GET['pv_id'] ?? 0);
        $vendedorId = (int)($_GET['vendedor_id'] ?? 0);
        $metodoFiltro = trim($_GET['metodo'] ?? '');
        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');

        $condiciones = ["p.metodo = 'transferencia'"];
        $params = [];

        if ($q !== '') {
            $condiciones[] = '(v.folio LIKE :q1 OR p.referencia LIKE :q2 OR u.nombre LIKE :q3)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
            $params[':q3'] = "%$q%";
        }

        if ($estado === 'pendiente') {
            $condiciones[] = 'p.verificado = 0 AND p.motivo_rechazo IS NULL';
        } elseif ($estado === 'verificada') {
            $condiciones[] = 'p.verificado = 1';
        } elseif ($estado === 'rechazada') {
            $condiciones[] = 'p.motivo_rechazo IS NOT NULL';
        }

        if ($pvId > 0) {
            $condiciones[] = 'v.punto_venta_id = :pv_id';
            $params[':pv_id'] = $pvId;
        }
        if ($vendedorId > 0) {
            $condiciones[] = 'v.usuario_id = :vendedor_id';
            $params[':vendedor_id'] = $vendedorId;
        }
        if ($metodoFiltro !== '') {
            $condiciones[] = 'p.metodo_detalle = :metodo_detalle';
            $params[':metodo_detalle'] = $metodoFiltro;
        }

        if ($desde !== '') {
            $condiciones[] = 'v.fecha >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        } else {
            // Por defecto: últimos 30 días
            $condiciones[] = 'v.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
        }
        if ($hasta !== '') {
            $condiciones[] = 'v.fecha <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        $where = 'WHERE ' . implode(' AND ', $condiciones);

        $transferencias = Database::fetchAll("
            SELECT 
                p.id AS pago_id, p.metodo_detalle, p.monto, p.referencia,
                p.ultimos_digitos, p.titular, p.banco, p.comprobante,
                p.verificado, p.fecha_verificacion, p.motivo_rechazo,
                v.id AS venta_id, v.folio, v.fecha, v.total, v.estado AS venta_estado,
                pv.id AS pv_id, pv.nombre AS pv,
                u.id AS vendedor_id, u.nombre AS vendedor,
                uv.nombre AS verificado_por_nombre
            FROM pagos_venta p
            JOIN ventas v ON v.id = p.venta_id
            JOIN puntos_venta pv ON pv.id = v.punto_venta_id
            JOIN usuarios u ON u.id = v.usuario_id
            LEFT JOIN usuarios uv ON uv.id = p.verificado_por
            $where
            ORDER BY v.fecha DESC
            LIMIT 500
        ", $params);

        // Resumen
        $resumen = [
            'pendientes' => 0,
            'verificadas' => 0,
            'rechazadas' => 0,
            'monto_pendiente' => 0,
            'monto_verificado' => 0,
            'monto_rechazado' => 0,
        ];

        foreach ($transferencias as $t) {
            if ($t['motivo_rechazo']) {
                $resumen['rechazadas']++;
                $resumen['monto_rechazado'] += (float) $t['monto'];
            } elseif ($t['verificado']) {
                $resumen['verificadas']++;
                $resumen['monto_verificado'] += (float) $t['monto'];
            } else {
                $resumen['pendientes']++;
                $resumen['monto_pendiente'] += (float) $t['monto'];
            }
        }

        Response::ok([
            'datos' => $transferencias,
            'resumen' => $resumen,
        ]);
        break;

    // ============================================================
    // OBTENER detalle completo de una transferencia
    // ============================================================
    case 'obtener':
        try {
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) Response::error('ID inválido');

            $transf = Database::fetchOne("
                SELECT 
                    p.id AS pago_id, p.metodo, p.metodo_detalle, p.monto, p.moneda, p.monto_divisa,
                    p.referencia, p.ultimos_digitos, p.titular, p.banco, p.comprobante,
                    p.verificado, p.verificado_por, p.fecha_verificacion, p.motivo_rechazo,
                    v.id AS venta_id, v.folio, v.fecha, v.total, v.subtotal, v.estado AS venta_estado,
                    pv.nombre AS pv, pv.direccion AS pv_direccion,
                    u.nombre AS vendedor, u.email AS vendedor_email,
                    uv.nombre AS verificado_por_nombre
                FROM pagos_venta p
                JOIN ventas v ON v.id = p.venta_id
                JOIN puntos_venta pv ON pv.id = v.punto_venta_id
                JOIN usuarios u ON u.id = v.usuario_id
                LEFT JOIN usuarios uv ON uv.id = p.verificado_por
                WHERE p.id = ?
            ", [$id]);

            if (!$transf) Response::noEncontrado('Transferencia no encontrada');

            // Detalle de la venta
            $transf['detalle_venta'] = Database::fetchAll("
                SELECT 
                    dv.cantidad, dv.precio_unitario, dv.subtotal,
                    pr.nombre AS producto
                FROM detalle_ventas dv
                JOIN productos pr ON pr.id = dv.producto_id
                WHERE dv.venta_id = ?
            ", [$transf['venta_id']]);

            // Otros pagos de la misma venta (si fue pago mixto)
            $transf['otros_pagos'] = Database::fetchAll("
                SELECT id, metodo, metodo_detalle, monto
                FROM pagos_venta
                WHERE venta_id = ? AND id != ?
            ", [$transf['venta_id'], $id]);

            Response::ok($transf);

        } catch (Throwable $e) {
            error_log('Error obtener transferencia: ' . $e->getMessage());
            Response::servidor('Error al obtener la transferencia: ' . $e->getMessage());
        }
        break;

    // ============================================================
    // VERIFICAR transferencia (aprobar)
    // ============================================================
    case 'verificar':
        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);

        if ($id <= 0) Response::error('ID inválido');

        $pago = Database::fetchOne("
            SELECT p.*, v.folio, v.usuario_id AS vendedor_id
            FROM pagos_venta p
            JOIN ventas v ON v.id = p.venta_id
            WHERE p.id = ?
        ", [$id]);

        if (!$pago) Response::noEncontrado('Transferencia no encontrada');

        if ($pago['metodo'] !== 'transferencia') {
            Response::error('Solo se pueden verificar transferencias');
        }

        if ($pago['verificado']) {
            Response::error('Esta transferencia ya está verificada');
        }

        if ($pago['motivo_rechazo']) {
            Response::error('Esta transferencia fue rechazada. No se puede verificar.');
        }

        try {
            Database::begin();

            Database::update('pagos_venta', [
                'verificado' => 1,
                'verificado_por' => Auth::id(),
                'fecha_verificacion' => date('Y-m-d H:i:s'),
            ], 'id = :id', [':id' => $id]);

            Auditoria::registrar('transferencia_verificada', 'pagos_venta', $id, [
                'folio'     => $pago['folio'],
                'monto'     => (float)$pago['monto'],
                'referencia'=> $pago['referencia'],
                'metodo'    => $pago['metodo_detalle'],
            ]);

            Database::commit();

            Response::ok(null, 'Transferencia verificada correctamente');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error verificar transferencia: ' . $e->getMessage());
            Response::servidor('No se pudo verificar');
        }
        break;

    // ============================================================
    // VERIFICAR MÚLTIPLES transferencias (masivo)
    // ============================================================
    case 'verificar_masivo':
        $body = jsonBody();
        $ids = $body['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            Response::error('No hay transferencias seleccionadas');
        }

        $ids = array_filter(array_map('intval', $ids), fn($id) => $id > 0);
        if (empty($ids)) Response::error('IDs inválidos');

        $procesados = 0;
        $errores = [];

        try {
            Database::begin();

            foreach ($ids as $id) {
                $pago = Database::fetchOne("
                    SELECT p.*, v.folio
                    FROM pagos_venta p
                    JOIN ventas v ON v.id = p.venta_id
                    WHERE p.id = ?
                ", [$id]);

                if (!$pago) {
                    $errores[] = "Pago #$id no encontrado";
                    continue;
                }

                if ($pago['metodo'] !== 'transferencia') {
                    $errores[] = "Pago #$id no es transferencia";
                    continue;
                }

                if ($pago['verificado']) {
                    $errores[] = "Pago #$id ya verificado";
                    continue;
                }

                if ($pago['motivo_rechazo']) {
                    $errores[] = "Pago #$id fue rechazado";
                    continue;
                }

                Database::update('pagos_venta', [
                    'verificado' => 1,
                    'verificado_por' => Auth::id(),
                    'fecha_verificacion' => date('Y-m-d H:i:s'),
                ], 'id = :id', [':id' => $id]);

                Auditoria::registrar('transferencia_verificada', 'pagos_venta', $id, [
                    'folio' => $pago['folio'],
                    'monto' => (float)$pago['monto'],
                    'masivo' => true,
                ]);

                $procesados++;
            }

            Database::commit();

            Response::ok([
                'procesados' => $procesados,
                'errores' => $errores,
            ], "$procesados transferencia(s) verificada(s)");

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error verificar masivo: ' . $e->getMessage());
            Response::servidor('No se pudieron verificar');
        }
        break;

    // ============================================================
    // RECHAZAR transferencia
    // ============================================================
    case 'rechazar':
        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        $motivo = trim($body['motivo'] ?? '');

        if ($id <= 0) Response::error('ID inválido');
        if ($motivo === '') Response::error('Motivo de rechazo requerido');
        if (mb_strlen($motivo) < 5) Response::error('El motivo debe tener al menos 5 caracteres');

        $pago = Database::fetchOne("
            SELECT p.*, v.folio
            FROM pagos_venta p
            JOIN ventas v ON v.id = p.venta_id
            WHERE p.id = ?
        ", [$id]);

        if (!$pago) Response::noEncontrado('Transferencia no encontrada');

        if ($pago['metodo'] !== 'transferencia') {
            Response::error('Solo se pueden rechazar transferencias');
        }

        if ($pago['verificado']) {
            Response::error('Esta transferencia ya está verificada');
        }

        try {
            Database::begin();

            Database::update('pagos_venta', [
                'motivo_rechazo' => $motivo,
                'verificado' => 0,
                'verificado_por' => Auth::id(),
                'fecha_verificacion' => date('Y-m-d H:i:s'),
            ], 'id = :id', [':id' => $id]);

            Auditoria::registrar('transferencia_rechazada', 'pagos_venta', $id, [
                'folio'  => $pago['folio'],
                'monto'  => (float)$pago['monto'],
                'motivo' => $motivo,
            ]);

            Database::commit();

            Response::ok(null, 'Transferencia rechazada');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error rechazar transferencia: ' . $e->getMessage());
            Response::servidor('No se pudo rechazar');
        }
        break;

    // ============================================================
    // CATÁLOGOS para filtros
    // ============================================================
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

        $metodos = Database::fetchAll("
            SELECT DISTINCT metodo_detalle
            FROM pagos_venta
            WHERE metodo = 'transferencia' AND metodo_detalle IS NOT NULL
            ORDER BY metodo_detalle
        ");

        Response::ok([
            'puntos_venta' => $pvs,
            'vendedores' => $vendedores,
            'metodos' => array_column($metodos, 'metodo_detalle'),
        ]);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}