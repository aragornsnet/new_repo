<?php
/**
 * IPV - API de Turnos para Supervisor/Administrador
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';

ApiBootstrap::iniciar(['Supervisor', 'Administrador']);

$accion = $_GET['accion'] ?? 'listar';

switch ($accion) {

    // ============================================================
    // LISTAR turnos con filtros
    // ============================================================
    case 'listar':
        $q = trim($_GET['q'] ?? '');
        $pvId = (int)($_GET['pv_id'] ?? 0);
        $vendedorId = (int)($_GET['vendedor_id'] ?? 0);
        $estado = trim($_GET['estado'] ?? '');
        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');

        $condiciones = [];
        $params = [];

        if ($q !== '') {
            $condiciones[] = '(u.nombre LIKE :q1 OR pv.nombre LIKE :q2)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
        }
        if ($pvId > 0) {
            $condiciones[] = 't.punto_venta_id = :pv_id';
            $params[':pv_id'] = $pvId;
        }
        if ($vendedorId > 0) {
            $condiciones[] = 't.usuario_id = :vendedor_id';
            $params[':vendedor_id'] = $vendedorId;
        }
        if ($estado === 'abierto' || $estado === 'cerrado') {
            $condiciones[] = 't.estado = :estado';
            $params[':estado'] = $estado;
        }

        if ($desde !== '') {
            $condiciones[] = 't.fecha_apertura >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        } else {
            $condiciones[] = 'DATE(t.fecha_apertura) = CURDATE()';
        }
        if ($hasta !== '') {
            $condiciones[] = 't.fecha_apertura <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $turnos = Database::fetchAll("
            SELECT 
                t.*,
                pv.nombre AS pv,
                u.nombre AS vendedor,
                uf.nombre AS forzado_por_nombre,
                (SELECT COUNT(*) FROM ventas v WHERE v.turno_id = t.id AND v.estado = 'completada') AS num_ventas,
                (SELECT COALESCE(SUM(v.total), 0) FROM ventas v WHERE v.turno_id = t.id AND v.estado = 'completada') AS total_vendido
            FROM turnos t
            JOIN puntos_venta pv ON pv.id = t.punto_venta_id
            JOIN usuarios u ON u.id = t.usuario_id
            LEFT JOIN usuarios uf ON uf.id = t.forzado_por
            $where
            ORDER BY t.fecha_apertura DESC
            LIMIT 500
        ", $params);

        Response::ok($turnos);
        break;

    // ============================================================
    // OBTENER detalle de un turno
    // ============================================================
    case 'obtener':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $turno = Database::fetchOne("
            SELECT 
                t.*,
                pv.nombre AS pv, pv.direccion AS pv_direccion,
                u.nombre AS vendedor, u.email AS vendedor_email,
                uf.nombre AS forzado_por_nombre
            FROM turnos t
            JOIN puntos_venta pv ON pv.id = t.punto_venta_id
            JOIN usuarios u ON u.id = t.usuario_id
            LEFT JOIN usuarios uf ON uf.id = t.forzado_por
            WHERE t.id = ?
        ", [$id]);

        if (!$turno) Response::noEncontrado('Turno no encontrado');

        // Ventas del turno
        $turno['ventas'] = Database::fetchAll("
            SELECT 
                v.id, v.folio, v.fecha, v.total, v.estado,
                (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'efectivo') AS efectivo,
                (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'transferencia') AS transferencia
            FROM ventas v
            WHERE v.turno_id = ?
            ORDER BY v.fecha DESC
        ", [$id]);

        // Resumen por método
        $turno['resumen_pagos'] = Database::fetchOne("
            SELECT 
                COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END), 0) AS total_efectivo,
                COALESCE(SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END), 0) AS total_transferencia
            FROM pagos_venta p
            JOIN ventas v ON v.id = p.venta_id
            WHERE v.turno_id = ? AND v.estado = 'completada'
        ", [$id]);

        // Inventario del turno (si se cerró con conteo)
        $turno['inventario'] = Database::fetchAll("
            SELECT 
                ti.*,
                p.nombre AS producto, p.codigo_barras
            FROM turno_inventario ti
            JOIN productos p ON p.id = ti.producto_id
            WHERE ti.turno_id = ?
            ORDER BY p.nombre
        ", [$id]);

        Response::ok($turno);
        break;

    // ============================================================
    // RESUMEN del día (abiertos y cerrados)
    // ============================================================
    case 'resumen_dia':
        // Turnos totales hoy
        $hoy = Database::fetchOne("
            SELECT 
                COUNT(*) AS total_turnos,
                SUM(CASE WHEN estado = 'cerrado' THEN 1 ELSE 0 END) AS cerrados,
                SUM(CASE WHEN forzado = 1 THEN 1 ELSE 0 END) AS forzados
            FROM turnos
            WHERE DATE(fecha_apertura) = CURDATE()
        ");

        // Turnos abiertos AHORA (sin filtro de fecha)
        $abiertosAhora = (int) Database::fetchValue("
            SELECT COUNT(*) FROM turnos WHERE estado = 'abierto'
        ", [], 0);

        Response::ok([
            'total_turnos' => (int) ($hoy['total_turnos'] ?? 0),
            'abiertos'     => $abiertosAhora,
            'cerrados'     => (int) ($hoy['cerrados'] ?? 0),
            'forzados'     => (int) ($hoy['forzados'] ?? 0),
        ]);
        break;

    // ============================================================
    // CATÁLOGOS
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

        Response::ok([
            'puntos_venta' => $pvs,
            'vendedores' => $vendedores,
        ]);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}