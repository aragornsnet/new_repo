<?php
/**
 * IPV - API de Historial completo de movimientos (Supervisor y Administrador)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';

ApiBootstrap::iniciar(['Supervisor', 'Administrador']);

$accion = $_GET['accion'] ?? 'listar';

switch ($accion) {

    case 'listar':
        $q = trim($_GET['q'] ?? '');
        $tipo = trim($_GET['tipo'] ?? '');
        $pvId = (int)($_GET['pv_id'] ?? 0);
        $usuarioId = (int)($_GET['usuario_id'] ?? 0);
        $productoId = (int)($_GET['producto_id'] ?? 0);
        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $porPagina = 100;
        $offset = ($pagina - 1) * $porPagina;

        $condiciones = [];
        $params = [];

        if ($q !== '') {
            $condiciones[] = '(p.nombre LIKE :q1 OR p.codigo_barras LIKE :q2 OR m.motivo LIKE :q3 OR m.descripcion LIKE :q4)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
            $params[':q3'] = "%$q%";
            $params[':q4'] = "%$q%";
        }
        if ($tipo !== '') {
            $condiciones[] = 'm.tipo = :tipo';
            $params[':tipo'] = $tipo;
        }
        if ($pvId > 0) {
            $condiciones[] = 'm.punto_venta_id = :pv_id';
            $params[':pv_id'] = $pvId;
        }
        if ($usuarioId > 0) {
            $condiciones[] = 'm.usuario_id = :usuario_id';
            $params[':usuario_id'] = $usuarioId;
        }
        if ($productoId > 0) {
            $condiciones[] = 'm.producto_id = :producto_id';
            $params[':producto_id'] = $productoId;
        }
        if ($desde !== '') {
            $condiciones[] = 'm.fecha >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        }
        if ($hasta !== '') {
            $condiciones[] = 'm.fecha <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        if ($desde === '' && $hasta === '') {
            $condiciones[] = 'm.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $total = (int) Database::fetchValue("
            SELECT COUNT(*) 
            FROM movimientos m
            JOIN productos p ON p.id = m.producto_id
            $where
        ", $params, 0);

        $movimientos = Database::fetchAll("
            SELECT 
                m.id, m.tipo, m.cantidad, m.motivo, m.descripcion, 
                m.valor_anterior, m.valor_nuevo, m.fecha,
                p.id AS producto_id, p.nombre AS producto, p.codigo_barras,
                p.unidad_medida,
                pv.id AS pv_id, pv.nombre AS pv,
                u.id AS usuario_id, u.nombre AS usuario,
                t.id AS turno_id,
                t.estado AS turno_estado
            FROM movimientos m
            JOIN productos p ON p.id = m.producto_id
            JOIN puntos_venta pv ON pv.id = m.punto_venta_id
            JOIN usuarios u ON u.id = m.usuario_id
            LEFT JOIN turnos t ON t.id = m.turno_id
            $where
            ORDER BY m.fecha DESC
            LIMIT $porPagina OFFSET $offset
        ", $params);

        Response::ok([
            'datos'      => $movimientos,
            'total'      => $total,
            'pagina'     => $pagina,
            'por_pagina' => $porPagina,
            'total_pags' => (int) ceil($total / $porPagina),
        ]);
        break;

    case 'obtener':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $mov = Database::fetchOne("
            SELECT 
                m.*,
                p.nombre AS producto, p.codigo_barras, p.unidad_medida,
                pv.nombre AS pv,
                u.nombre AS usuario, u.email AS usuario_email,
                t.id AS turno_id, t.estado AS turno_estado, t.fecha_apertura AS turno_apertura
            FROM movimientos m
            JOIN productos p ON p.id = m.producto_id
            JOIN puntos_venta pv ON pv.id = m.punto_venta_id
            JOIN usuarios u ON u.id = m.usuario_id
            LEFT JOIN turnos t ON t.id = m.turno_id
            WHERE m.id = ?
        ", [$id]);

        if (!$mov) Response::noEncontrado('Movimiento no encontrado');

        Response::ok($mov);
        break;

    case 'catalogos':
        $pvs = Database::fetchAll("
            SELECT id, nombre FROM puntos_venta WHERE activo = 1 ORDER BY nombre
        ");

        $usuarios = Database::fetchAll("
            SELECT u.id, u.nombre, COUNT(m.id) AS total
            FROM usuarios u
            LEFT JOIN movimientos m ON m.usuario_id = u.id
            WHERE u.activo = 1
            GROUP BY u.id, u.nombre
            HAVING total > 0
            ORDER BY u.nombre
        ");

        $tipos = [
            ['valor' => 'entrada',       'nombre' => 'Entrada'],
            ['valor' => 'salida',        'nombre' => 'Salida'],
            ['valor' => 'baja',          'nombre' => 'Baja'],
            ['valor' => 'ajuste',        'nombre' => 'Ajuste'],
            ['valor' => 'transferencia', 'nombre' => 'Transferencia'],
        ];

        Response::ok([
            'puntos_venta' => $pvs,
            'usuarios' => $usuarios,
            'tipos' => $tipos,
        ]);
        break;

    case 'estadisticas':
        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');

        $condiciones = [];
        $params = [];

        if ($desde !== '') {
            $condiciones[] = 'm.fecha >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        } else {
            $condiciones[] = 'm.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
        }
        if ($hasta !== '') {
            $condiciones[] = 'm.fecha <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $stats = Database::fetchAll("
            SELECT 
                tipo,
                COUNT(*) AS num_movimientos,
                SUM(cantidad) AS total_cantidad
            FROM movimientos m
            $where
            GROUP BY tipo
        ", $params);

        Response::ok($stats);
        break;

    case 'exportar':
        $q = trim($_GET['q'] ?? '');
        $tipo = trim($_GET['tipo'] ?? '');
        $pvId = (int)($_GET['pv_id'] ?? 0);
        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');

        $condiciones = [];
        $params = [];

        if ($q !== '') {
            $condiciones[] = '(p.nombre LIKE :q1 OR m.motivo LIKE :q2)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
        }
        if ($tipo !== '') {
            $condiciones[] = 'm.tipo = :tipo';
            $params[':tipo'] = $tipo;
        }
        if ($pvId > 0) {
            $condiciones[] = 'm.punto_venta_id = :pv_id';
            $params[':pv_id'] = $pvId;
        }
        if ($desde !== '') {
            $condiciones[] = 'm.fecha >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        }
        if ($hasta !== '') {
            $condiciones[] = 'm.fecha <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $movimientos = Database::fetchAll("
            SELECT 
                m.fecha, m.tipo, p.nombre AS producto, p.codigo_barras,
                p.unidad_medida,
                pv.nombre AS pv, m.cantidad, m.valor_anterior, m.valor_nuevo,
                m.motivo, m.descripcion, u.nombre AS usuario
            FROM movimientos m
            JOIN productos p ON p.id = m.producto_id
            JOIN puntos_venta pv ON pv.id = m.punto_venta_id
            JOIN usuarios u ON u.id = m.usuario_id
            $where
            ORDER BY m.fecha DESC
            LIMIT 10000
        ", $params);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="movimientos_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($out, ['Fecha', 'Tipo', 'Producto', 'Código', 'Unidad', 'PV', 'Cantidad', 'Stock antes', 'Stock después', 'Motivo', 'Descripción', 'Usuario']);

        foreach ($movimientos as $m) {
            fputcsv($out, [
                $m['fecha'], $m['tipo'], $m['producto'], $m['codigo_barras'] ?? '',
                $m['unidad_medida'] ?? '',
                $m['pv'], $m['cantidad'], $m['valor_anterior'], $m['valor_nuevo'],
                $m['motivo'] ?? '', $m['descripcion'] ?? '', $m['usuario'],
            ]);
        }
        fclose($out);
        exit;

    default:
        Response::error('Acción no reconocida', 404);
}