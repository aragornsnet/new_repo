<?php
/**
 * IPV - API de datos para vista previa de reportes
 * Devuelve JSON con los mismos datos que los PDFs/Excels
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(120);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';

// Verifica login (cualquier usuario), pero no licencia (para reportes públicos)
ApiBootstrap::iniciar([], true, false);

$tipo = $_GET['tipo'] ?? '';

// Mismos permisos que en reportes.php
$permisosPorRol = [
    'Administrador' => [
        'ventas_fechas', 'ventas_vendedor', 'ventas_pv', 'top_productos',
        'caja_dia', 'movimientos', 'turnos', 'transferencias', 'stock_bajo', 'auditoria',
        'facturas_fechas', 'facturas_cliente', 'facturas_vendedor',
        'comprobantes_fechas', 'comprobantes_vendedor', 'comprobantes_pv',
    ],
    'Supervisor' => [
        'ventas_fechas', 'ventas_vendedor', 'ventas_pv', 'top_productos',
        'caja_dia', 'movimientos', 'turnos', 'transferencias', 'stock_bajo',
        'facturas_fechas', 'facturas_cliente', 'facturas_vendedor',
        'comprobantes_fechas', 'comprobantes_vendedor', 'comprobantes_pv',
    ],
    'Vendedor' => [
        'mis_ventas', 'mi_caja',
    ],
];

$rol = Auth::rol();
$tiposPermitidos = $permisosPorRol[$rol] ?? [];

if (!in_array($tipo, $tiposPermitidos, true)) {
    Response::prohibido('No tienes permiso para ver este reporte');
}

// Llamar al método correspondiente
switch ($tipo) {

    case 'ventas_fechas':
        datos_ventas_fechas();
        break;

    case 'ventas_vendedor':
        datos_ventas_vendedor();
        break;

    case 'ventas_pv':
        datos_ventas_pv();
        break;

    case 'top_productos':
        datos_top_productos();
        break;

    case 'caja_dia':
        datos_caja_dia();
        break;

    case 'movimientos':
        datos_movimientos();
        break;

    case 'turnos':
        datos_turnos();
        break;

    case 'transferencias':
        datos_transferencias();
        break;

    case 'stock_bajo':
        datos_stock_bajo();
        break;

    case 'auditoria':
        datos_auditoria();
        break;

    case 'mis_ventas':
        datos_mis_ventas();
        break;

    case 'mi_caja':
        datos_mi_caja();
        break;

    case 'facturas_fechas':
        datos_facturas_fechas();
        break;

    case 'facturas_cliente':
        datos_facturas_cliente();
        break;

    case 'facturas_vendedor':
        datos_facturas_vendedor();
        break;

        case 'comprobantes_fechas':
        datos_comprobantes_fechas();
        break;

    case 'comprobantes_vendedor':
        datos_comprobantes_vendedor();
        break;

    case 'comprobantes_pv':
        datos_comprobantes_pv();
        break;

    default:
        Response::error('Tipo de reporte no reconocido', 404);
}

// ============================================================
// FUNCIONES POR TIPO DE REPORTE
// ============================================================

function datos_ventas_fechas()
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $pvId = (int)($_GET['pv_id'] ?? 0);
    $vendedorId = (int)($_GET['vendedor_id'] ?? 0);

    $condiciones = ["v.estado = 'completada'", 'DATE(v.fecha) >= :desde', 'DATE(v.fecha) <= :hasta'];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($pvId > 0) {
        $condiciones[] = 'v.punto_venta_id = :pv_id';
        $params[':pv_id'] = $pvId;
    }
    if ($vendedorId > 0) {
        $condiciones[] = 'v.usuario_id = :vendedor_id';
        $params[':vendedor_id'] = $vendedorId;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $ventas = Database::fetchAll("
        SELECT 
            v.folio, v.fecha, v.total, v.moneda,
            pv.nombre AS pv,
            u.nombre AS vendedor,
            (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'efectivo') AS efectivo,
            (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'transferencia') AS transferencia
        FROM ventas v
        JOIN puntos_venta pv ON pv.id = v.punto_venta_id
        JOIN usuarios u ON u.id = v.usuario_id
        $where
        ORDER BY v.fecha DESC
        LIMIT 500
    ", $params);

    $totalEfectivo = 0;
    $totalTransf = 0;
    $totalGeneral = 0;
    foreach ($ventas as $v) {
        $totalEfectivo += (float)$v['efectivo'];
        $totalTransf += (float)$v['transferencia'];
        $totalGeneral += (float)$v['total'];
    }

    $filas = [];
    foreach ($ventas as $v) {
        $filas[] = [
            $v['folio'],
            fecha($v['fecha'], 'd/m/Y H:i'),
            $v['pv'],
            $v['vendedor'],
            moneda($v['efectivo']),
            moneda($v['transferencia']),
            moneda($v['total']),
        ];
    }

    Response::ok([
        'titulo' => 'Reporte de Ventas',
        'subtitulo' => "Desde $desde hasta $hasta · " . count($ventas) . " ventas",
        'columnas' => ['Folio', 'Fecha', 'PV', 'Vendedor', 'Efectivo', 'Transf.', 'Total'],
        'filas' => $filas,
        'resumen' => [
            ['label' => 'Total Ventas', 'valor' => count($ventas)],
            ['label' => 'Total General', 'valor' => moneda($totalGeneral)],
            ['label' => 'Efectivo', 'valor' => moneda($totalEfectivo)],
            ['label' => 'Transferencias', 'valor' => moneda($totalTransf)],
        ],
        'totales' => [
            'Total efectivo' => moneda($totalEfectivo),
            'Total transferencias' => moneda($totalTransf),
            'TOTAL GENERAL' => moneda($totalGeneral),
        ],
    ]);
}

function datos_ventas_vendedor()
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $pvId = (int)($_GET['pv_id'] ?? 0);

    $sql = "
        SELECT 
            u.nombre AS vendedor,
            pv.nombre AS pv,
            COUNT(DISTINCT v.id) AS num_ventas,
            COALESCE(SUM(DISTINCT v.total), 0) AS total,
            COALESCE(AVG(DISTINCT v.total), 0) AS ticket_promedio,
            COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END), 0) AS efectivo,
            COALESCE(SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END), 0) AS transferencia
        FROM usuarios u
        JOIN puntos_venta pv ON pv.id = u.punto_venta_id
        LEFT JOIN ventas v ON v.usuario_id = u.id 
            AND v.estado = 'completada'
            AND DATE(v.fecha) >= :desde 
            AND DATE(v.fecha) <= :hasta
            " . ($pvId > 0 ? "AND v.punto_venta_id = :pv_id" : "") . "
        LEFT JOIN pagos_venta p ON p.venta_id = v.id
        WHERE u.rol_id = 3
        GROUP BY u.id, u.nombre, pv.nombre
        HAVING num_ventas > 0
        ORDER BY total DESC
    ";

    $params = [':desde' => $desde, ':hasta' => $hasta];
    if ($pvId > 0) $params[':pv_id'] = $pvId;

    $datos = Database::fetchAll($sql, $params);

    $filas = [];
    foreach ($datos as $i => $d) {
        $filas[] = [
            $i + 1,
            $d['vendedor'],
            $d['pv'],
            $d['num_ventas'],
            moneda($d['ticket_promedio']),
            moneda($d['total']),
        ];
    }

    $totalGeneral = array_sum(array_column($datos, 'total'));

    Response::ok([
        'titulo' => 'Ventas por Vendedor',
        'subtitulo' => "Desde $desde hasta $hasta",
        'columnas' => ['#', 'Vendedor', 'PV', 'Ventas', 'Ticket Prom.', 'Total'],
        'filas' => $filas,
        'totales' => [
            'TOTAL GENERAL' => moneda($totalGeneral),
        ],
    ]);
}

function datos_ventas_pv()
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');

    $datos = Database::fetchAll("
        SELECT 
            pv.nombre AS pv,
            COUNT(DISTINCT v.id) AS num_ventas,
            COALESCE(SUM(DISTINCT v.total), 0) AS total,
            COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END), 0) AS efectivo,
            COALESCE(SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END), 0) AS transferencia
        FROM puntos_venta pv
        LEFT JOIN ventas v ON v.punto_venta_id = pv.id 
            AND v.estado = 'completada'
            AND DATE(v.fecha) >= :desde 
            AND DATE(v.fecha) <= :hasta
        LEFT JOIN pagos_venta p ON p.venta_id = v.id
        WHERE pv.activo = 1
        GROUP BY pv.id, pv.nombre
        ORDER BY total DESC
    ", [':desde' => $desde, ':hasta' => $hasta]);

    $filas = [];
    foreach ($datos as $d) {
        $filas[] = [
            $d['pv'],
            $d['num_ventas'],
            moneda($d['efectivo']),
            moneda($d['transferencia']),
            moneda($d['total']),
        ];
    }

    Response::ok([
        'titulo' => 'Ventas por Punto de Venta',
        'subtitulo' => "Desde $desde hasta $hasta",
        'columnas' => ['Punto de venta', 'Ventas', 'Efectivo', 'Transferencia', 'Total'],
        'filas' => $filas,
        'totales' => [
            'TOTAL GENERAL' => moneda(array_sum(array_column($datos, 'total'))),
        ],
    ]);
}

function datos_top_productos()
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $limit = min(max((int)($_GET['limit'] ?? 20), 1), 100);

    $datos = Database::fetchAll("
        SELECT 
            p.nombre AS producto,
            p.codigo_barras,
            c.nombre AS categoria,
            SUM(dv.cantidad) AS unidades,
            SUM(dv.subtotal) AS total,
            COUNT(DISTINCT v.id) AS num_ventas
        FROM detalle_ventas dv
        JOIN productos p ON p.id = dv.producto_id
        JOIN ventas v ON v.id = dv.venta_id
        LEFT JOIN categorias c ON c.id = p.categoria_id
        WHERE v.estado = 'completada'
          AND DATE(v.fecha) >= :desde
          AND DATE(v.fecha) <= :hasta
        GROUP BY p.id, p.nombre, p.codigo_barras, c.nombre
        ORDER BY unidades DESC
        LIMIT $limit
    ", [':desde' => $desde, ':hasta' => $hasta]);

    $filas = [];
    foreach ($datos as $i => $d) {
        $filas[] = [
            $i + 1,
            $d['producto'],
            $d['categoria'] ?? '—',
            $d['unidades'],
            $d['num_ventas'],
            moneda($d['total']),
        ];
    }

    Response::ok([
        'titulo' => "Top $limit Productos Más Vendidos",
        'subtitulo' => "Desde $desde hasta $hasta",
        'columnas' => ['#', 'Producto', 'Categoría', 'Unidades', 'Ventas', 'Total'],
        'filas' => $filas,
        'totales' => [
            'Total unidades' => array_sum(array_column($datos, 'unidades')),
            'Total ingresos' => moneda(array_sum(array_column($datos, 'total'))),
        ],
    ]);
}

function datos_caja_dia()
{
    $desde = $_GET['desde'] ?? date('Y-m-d');
    $hasta = $_GET['hasta'] ?? $desde;
    $pvId = (int)($_GET['pv_id'] ?? 0);

    $condiciones = ["v.estado = 'completada'", 'DATE(v.fecha) >= :desde', 'DATE(v.fecha) <= :hasta'];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($pvId > 0) {
        $condiciones[] = 'v.punto_venta_id = :pv_id';
        $params[':pv_id'] = $pvId;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT 
            u.nombre AS vendedor,
            pv.nombre AS pv,
            DATE(v.fecha) AS dia,
            SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END) AS efectivo,
            SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END) AS transferencia,
            SUM(p.monto) AS total,
            COUNT(DISTINCT v.id) AS num_ventas
        FROM ventas v
        JOIN usuarios u ON u.id = v.usuario_id
        JOIN puntos_venta pv ON pv.id = v.punto_venta_id
        JOIN pagos_venta p ON p.venta_id = v.id
        $where
        GROUP BY u.id, u.nombre, pv.nombre, DATE(v.fecha)
        ORDER BY u.nombre, dia
    ", $params);

    $filas = [];
    foreach ($datos as $d) {
        $filas[] = [
            $d['vendedor'],
            $d['pv'],
            fecha($d['dia'], 'd/m/Y'),
            $d['num_ventas'],
            moneda($d['efectivo']),
            moneda($d['transferencia']),
            moneda($d['total']),
        ];
    }

    Response::ok([
        'titulo' => 'Caja del Día',
        'subtitulo' => ($desde === $hasta) ? "Día: $desde" : "Desde $desde hasta $hasta",
        'columnas' => ['Vendedor', 'PV', 'Día', 'Ventas', 'Efectivo', 'Transf.', 'Total'],
        'filas' => $filas,
        'totales' => [
            'Total efectivo' => moneda(array_sum(array_column($datos, 'efectivo'))),
            'Total transferencias' => moneda(array_sum(array_column($datos, 'transferencia'))),
            'TOTAL GENERAL' => moneda(array_sum(array_column($datos, 'total'))),
        ],
    ]);
}

function datos_movimientos()
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $tipoMov = trim($_GET['tipo_mov'] ?? '');
    $pvId = (int)($_GET['pv_id'] ?? 0);

    $condiciones = ['DATE(m.fecha) >= :desde', 'DATE(m.fecha) <= :hasta'];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($tipoMov !== '') {
        $condiciones[] = 'm.tipo = :tipo';
        $params[':tipo'] = $tipoMov;
    }
    if ($pvId > 0) {
        $condiciones[] = 'm.punto_venta_id = :pv_id';
        $params[':pv_id'] = $pvId;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT 
            m.fecha, m.tipo, m.cantidad, m.valor_anterior, m.valor_nuevo,
            p.nombre AS producto,
            pv.nombre AS pv,
            u.nombre AS usuario
        FROM movimientos m
        JOIN productos p ON p.id = m.producto_id
        JOIN puntos_venta pv ON pv.id = m.punto_venta_id
        JOIN usuarios u ON u.id = m.usuario_id
        $where
        ORDER BY m.fecha DESC
        LIMIT 500
    ", $params);

    $filas = [];
    foreach ($datos as $d) {
        $filas[] = [
            fecha($d['fecha'], 'd/m/Y H:i'),
            ucfirst($d['tipo']),
            $d['producto'],
            $d['pv'],
            ($d['cantidad'] > 0 ? '+' : '') . $d['cantidad'],
            $d['valor_anterior'] . ' → ' . $d['valor_nuevo'],
            $d['usuario'],
        ];
    }

    Response::ok([
        'titulo' => 'Movimientos de Inventario',
        'subtitulo' => "Desde $desde hasta $hasta",
        'columnas' => ['Fecha', 'Tipo', 'Producto', 'PV', 'Cant.', 'Stock', 'Usuario'],
        'filas' => $filas,
        'totales' => ['Total movimientos' => count($datos)],
    ]);
}

function datos_turnos()
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $estado = trim($_GET['estado_turno'] ?? 'cerrado');

    $condiciones = ['DATE(t.fecha_apertura) >= :desde', 'DATE(t.fecha_apertura) <= :hasta'];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($estado === 'abierto' || $estado === 'cerrado') {
        $condiciones[] = 't.estado = :estado';
        $params[':estado'] = $estado;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT 
            t.id, t.fecha_apertura, t.fecha_cierre,
            t.monto_inicial, t.total_ventas, t.total_descuadres, t.estado,
            pv.nombre AS pv,
            u.nombre AS vendedor
        FROM turnos t
        JOIN puntos_venta pv ON pv.id = t.punto_venta_id
        JOIN usuarios u ON u.id = t.usuario_id
        $where
        ORDER BY t.fecha_apertura DESC
        LIMIT 500
    ", $params);

    $filas = [];
    foreach ($datos as $d) {
        $filas[] = [
            $d['id'],
            $d['vendedor'],
            $d['pv'],
            fecha($d['fecha_apertura'], 'd/m/Y H:i'),
            $d['fecha_cierre'] ? fecha($d['fecha_cierre'], 'd/m/Y H:i') : '—',
            moneda($d['monto_inicial']),
            moneda($d['total_ventas']),
            number_format((float)$d['total_descuadres'], 2),
        ];
    }

    Response::ok([
        'titulo' => 'Reporte de Turnos',
        'subtitulo' => "Desde $desde hasta $hasta",
        'columnas' => ['#', 'Vendedor', 'PV', 'Apertura', 'Cierre', 'Inicial', 'Total', 'Descuadre'],
        'filas' => $filas,
        'totales' => [
            'Total turnos' => count($datos),
            'Suma de descuadres' => number_format(array_sum(array_column($datos, 'total_descuadres')), 2),
        ],
    ]);
}

function datos_transferencias()
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $estado = trim($_GET['estado_transf'] ?? '');

    $condiciones = ["p.metodo = 'transferencia'", 'DATE(v.fecha) >= :desde', 'DATE(v.fecha) <= :hasta'];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($estado === 'pendiente') {
        $condiciones[] = 'p.verificado = 0 AND p.motivo_rechazo IS NULL';
    } elseif ($estado === 'verificada') {
        $condiciones[] = 'p.verificado = 1';
    } elseif ($estado === 'rechazada') {
        $condiciones[] = 'p.motivo_rechazo IS NOT NULL';
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT 
            v.folio, v.fecha,
            p.metodo_detalle, p.monto, p.referencia,
            p.verificado, p.motivo_rechazo,
            pv.nombre AS pv,
            u.nombre AS vendedor
        FROM pagos_venta p
        JOIN ventas v ON v.id = p.venta_id
        JOIN puntos_venta pv ON pv.id = v.punto_venta_id
        JOIN usuarios u ON u.id = v.usuario_id
        $where
        ORDER BY v.fecha DESC
        LIMIT 500
    ", $params);

    $filas = [];
    foreach ($datos as $d) {
        $estadoTxt = $d['motivo_rechazo'] ? 'Rechazada' : ($d['verificado'] ? 'Verificada' : 'Pendiente');
        $filas[] = [
            $d['folio'],
            fecha($d['fecha'], 'd/m/Y'),
            $d['vendedor'],
            $d['pv'],
            $d['metodo_detalle'] ?? 'Transferencia',
            $d['referencia'] ?? '—',
            moneda($d['monto']),
            $estadoTxt,
        ];
    }

    Response::ok([
        'titulo' => 'Reporte de Transferencias',
        'subtitulo' => "Desde $desde hasta $hasta",
        'columnas' => ['Folio', 'Fecha', 'Vendedor', 'PV', 'Método', 'Ref.', 'Monto', 'Estado'],
        'filas' => $filas,
        'totales' => [
            'Total transferencias' => count($datos),
            'Monto total' => moneda(array_sum(array_column($datos, 'monto'))),
        ],
    ]);
}

function datos_stock_bajo()
{
    $filtro = trim($_GET['filtro_stock'] ?? 'bajo');
    $pvId = (int)($_GET['pv_id'] ?? 0);

    $condiciones = ['p.activo = 1', 'pv.activo = 1'];
    $params = [];

    if ($filtro === 'negativo') {
        $condiciones[] = 'spv.stock < 0';
    } elseif ($filtro === 'bajo') {
        $condiciones[] = 'spv.stock <= p.stock_minimo AND spv.stock >= 0';
    } else {
        $condiciones[] = 'spv.stock <= p.stock_minimo';
    }

    if ($pvId > 0) {
        $condiciones[] = 'pv.id = :pv_id';
        $params[':pv_id'] = $pvId;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT 
            p.nombre AS producto, p.codigo_barras,
            spv.stock, p.stock_minimo,
            c.nombre AS categoria,
            pv.nombre AS pv
        FROM stock_punto_venta spv
        JOIN productos p ON p.id = spv.producto_id
        JOIN puntos_venta pv ON pv.id = spv.punto_venta_id
        LEFT JOIN categorias c ON c.id = p.categoria_id
        $where
        ORDER BY spv.stock ASC, p.nombre
        LIMIT 500
    ", $params);

    $filas = [];
    foreach ($datos as $d) {
        $estado = $d['stock'] < 0 ? 'NEGATIVO' : ($d['stock'] <= $d['stock_minimo'] ? 'BAJO' : 'OK');
        $filas[] = [
            $d['producto'],
            $d['codigo_barras'] ?? '—',
            $d['categoria'] ?? '—',
            $d['pv'],
            $d['stock'],
            $d['stock_minimo'],
            $estado,
        ];
    }

    Response::ok([
        'titulo' => 'Productos con Stock ' . ($filtro === 'negativo' ? 'Negativo' : 'Bajo'),
        'subtitulo' => count($datos) . ' productos encontrados',
        'columnas' => ['Producto', 'Código', 'Categoría', 'PV', 'Stock', 'Mín.', 'Estado'],
        'filas' => $filas,
        'totales' => ['Total productos' => count($datos)],
    ]);
}

function datos_auditoria()
{
    $desde = $_GET['desde'] ?? date('Y-m-d');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $usuarioId = (int)($_GET['usuario_id'] ?? 0);
    $accion = trim($_GET['accion_filtro'] ?? '');

    $condiciones = ['DATE(a.fecha) >= :desde', 'DATE(a.fecha) <= :hasta'];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($usuarioId > 0) {
        $condiciones[] = 'a.usuario_id = :usuario_id';
        $params[':usuario_id'] = $usuarioId;
    }
    if ($accion !== '') {
        $condiciones[] = 'a.accion = :accion';
        $params[':accion'] = $accion;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT 
            a.fecha, a.accion, a.tabla_afectada, a.registro_id, a.ip,
            u.nombre AS usuario
        FROM auditoria a
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        $where
        ORDER BY a.fecha DESC
        LIMIT 500
    ", $params);

    $filas = [];
    foreach ($datos as $d) {
        $filas[] = [
            fecha($d['fecha'], 'd/m/Y H:i'),
            $d['usuario'] ?? 'Sistema',
            $d['accion'],
            $d['tabla_afectada'] ?? '—',
            $d['ip'] ?? '—',
        ];
    }

    Response::ok([
        'titulo' => 'Reporte de Auditoría',
        'subtitulo' => "Desde $desde hasta $hasta · " . count($datos) . " registros",
        'columnas' => ['Fecha', 'Usuario', 'Acción', 'Tabla', 'IP'],
        'filas' => $filas,
        'totales' => ['Total registros' => count($datos)],
        'nota' => count($datos) >= 500 ? 'Mostrando los primeros 500 registros. Descarga Excel para ver todos.' : null,
    ]);
}

function datos_mis_ventas()
{
    $vendedorId = Auth::id();
    $desde = $_GET['desde'] ?? date('Y-m-d');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');

    $ventas = Database::fetchAll("
        SELECT 
            v.folio, v.fecha, v.total, v.moneda,
            (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'efectivo') AS efectivo,
            (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'transferencia') AS transferencia
        FROM ventas v
        WHERE v.usuario_id = :vendedor_id
          AND v.estado = 'completada'
          AND DATE(v.fecha) >= :desde
          AND DATE(v.fecha) <= :hasta
        ORDER BY v.fecha DESC
        LIMIT 500
    ", [
        ':vendedor_id' => $vendedorId,
        ':desde' => $desde,
        ':hasta' => $hasta,
    ]);

    $filas = [];
    foreach ($ventas as $v) {
        $filas[] = [
            $v['folio'],
            fecha($v['fecha'], 'd/m/Y H:i'),
            moneda($v['efectivo']),
            moneda($v['transferencia']),
            moneda($v['total']),
        ];
    }

    $totalGeneral = array_sum(array_column($ventas, 'total'));

    Response::ok([
        'titulo' => 'Mis Ventas',
        'subtitulo' => "Desde $desde hasta $hasta",
        'columnas' => ['Folio', 'Fecha', 'Efectivo', 'Transf.', 'Total'],
        'filas' => $filas,
        'totales' => ['TOTAL GENERAL' => moneda($totalGeneral)],
    ]);
}

function datos_mi_caja()
{
    $vendedorId = Auth::id();

    $turno = Database::fetchOne("
        SELECT t.*, pv.nombre AS pv
        FROM turnos t
        JOIN puntos_venta pv ON pv.id = t.punto_venta_id
        WHERE t.usuario_id = :vendedor_id AND t.estado = 'abierto'
        LIMIT 1
    ", [':vendedor_id' => $vendedorId]);

    if (!$turno) {
        Response::error('No tienes un turno abierto');
    }

    $movimientos = Database::fetchAll("
        SELECT 
            v.folio, v.fecha, v.total,
            (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'efectivo') AS efectivo
        FROM ventas v
        WHERE v.turno_id = :turno_id
          AND v.estado = 'completada'
          AND EXISTS (SELECT 1 FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'efectivo')
        ORDER BY v.fecha ASC
    ", [':turno_id' => (int)$turno['id']]);

    $filas = [];
    foreach ($movimientos as $m) {
        $filas[] = [
            $m['folio'],
            fecha($m['fecha'], 'd/m/Y H:i'),
            moneda($m['efectivo']),
            moneda($m['total']),
        ];
    }

    $totalEfectivo = array_sum(array_column($movimientos, 'efectivo'));
    $efectivoTeorico = (float)$turno['monto_inicial'] + $totalEfectivo;

    Response::ok([
        'titulo' => 'Mi Caja — Turno #' . $turno['id'],
        'subtitulo' => $turno['pv'] . ' · Apertura: ' . fecha($turno['fecha_apertura'], 'd/m/Y H:i'),
        'columnas' => ['Folio', 'Fecha', 'Efectivo recibido', 'Total venta'],
        'filas' => $filas,
        'resumen' => [
            ['label' => 'Monto inicial', 'valor' => moneda($turno['monto_inicial'])],
            ['label' => 'Ventas efectivo', 'valor' => moneda($totalEfectivo)],
            ['label' => 'En caja ahora', 'valor' => moneda($efectivoTeorico)],
        ],
        'totales' => [
            'Monto inicial' => moneda($turno['monto_inicial']),
            'Ventas en efectivo' => moneda($totalEfectivo),
            'TOTAL EN CAJA' => moneda($efectivoTeorico),
        ],
    ]);
}

// ============================================================
// FACTURAS POR FECHAS
// ============================================================
function datos_facturas_fechas()
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $clienteId = (int)($_GET['cliente_id'] ?? 0);
    $vendedorId = (int)($_GET['vendedor_id'] ?? 0);
    $estado = trim($_GET['estado'] ?? '');

    $condiciones = [
        'DATE(f.fecha_emision) >= :desde',
        'DATE(f.fecha_emision) <= :hasta',
    ];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($clienteId > 0) {
        $condiciones[] = 'f.cliente_id = :cliente_id';
        $params[':cliente_id'] = $clienteId;
    }
    if ($vendedorId > 0) {
        $condiciones[] = 'v.usuario_id = :vendedor_id';
        $params[':vendedor_id'] = $vendedorId;
    }
    if (in_array($estado, ['emitida', 'parcial', 'pagada', 'anulada'], true)) {
        $condiciones[] = 'f.estado = :estado';
        $params[':estado'] = $estado;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT
            f.folio, f.fecha_emision, f.fecha_vencimiento,
            f.total, f.estado,
            c.nombre AS cliente, c.nit AS cliente_nit,
            v.folio AS venta_folio,
            u.nombre AS vendedor,
            COALESCE((SELECT SUM(fp.monto) FROM facturas_pagos fp WHERE fp.factura_id = f.id), 0) AS total_pagado
        FROM facturas f
        JOIN clientes c ON c.id = f.cliente_id
        JOIN ventas v ON v.id = f.venta_id
        JOIN usuarios u ON u.id = v.usuario_id
        $where
        ORDER BY f.fecha_emision DESC
        LIMIT 500
    ", $params);

    $filas = [];
    foreach ($datos as $d) {
        $saldo = (float)$d['total'] - (float)$d['total_pagado'];
        $filas[] = [
            $d['folio'],
            fecha($d['fecha_emision'], 'd/m/Y'),
            $d['fecha_vencimiento'] ? fecha($d['fecha_vencimiento'], 'd/m/Y') : '—',
            $d['cliente'],
            $d['cliente_nit'] ?: '—',
            $d['venta_folio'],
            ucfirst($d['estado']),
            moneda($d['total']),
            moneda($d['total_pagado']),
            moneda($saldo),
        ];
    }

    $totalGeneral = 0;
    $totalPagado = 0;
    $totalSaldo = 0;
    foreach ($datos as $d) {
        if ($d['estado'] === 'anulada') continue;
        $totalGeneral += (float)$d['total'];
        $totalPagado  += (float)$d['total_pagado'];
        $totalSaldo   += ((float)$d['total'] - (float)$d['total_pagado']);
    }

    Response::ok([
        'titulo' => 'Reporte de Facturación',
        'subtitulo' => "Desde $desde hasta $hasta · " . count($datos) . " factura(s)",
        'columnas' => ['Folio', 'Emisión', 'Vence', 'Cliente', 'NIT', 'Venta', 'Estado', 'Total', 'Pagado', 'Saldo'],
        'filas' => $filas,
        'totales' => [
            'Total facturado' => moneda($totalGeneral),
            'Total pagado'    => moneda($totalPagado),
            'SALDO PENDIENTE' => moneda($totalSaldo),
        ],
    ]);
}

// ============================================================
// FACTURAS POR CLIENTE
// ============================================================
function datos_facturas_cliente()
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $clienteId = (int)($_GET['cliente_id'] ?? 0);
    $vendedorId = (int)($_GET['vendedor_id'] ?? 0);
    $estado = trim($_GET['estado'] ?? '');

    $condiciones = [
        'DATE(f.fecha_emision) >= :desde',
        'DATE(f.fecha_emision) <= :hasta',
        "f.estado != 'anulada'",
    ];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($clienteId > 0) {
        $condiciones[] = 'f.cliente_id = :cliente_id';
        $params[':cliente_id'] = $clienteId;
    }
    if ($vendedorId > 0) {
        $condiciones[] = 'v.usuario_id = :vendedor_id';
        $params[':vendedor_id'] = $vendedorId;
    }
    if (in_array($estado, ['emitida', 'parcial', 'pagada'], true)) {
        $condiciones[] = 'f.estado = :estado';
        $params[':estado'] = $estado;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT
            c.nombre AS cliente, c.nit AS cliente_nit, c.telefono AS cliente_telefono,
            COUNT(f.id) AS num_facturas,
            COALESCE(SUM(f.total), 0) AS total_facturado,
            COALESCE(SUM(COALESCE((SELECT SUM(fp.monto) FROM facturas_pagos fp WHERE fp.factura_id = f.id), 0)), 0) AS total_pagado,
            MAX(f.fecha_emision) AS ultima_factura
        FROM facturas f
        JOIN clientes c ON c.id = f.cliente_id
        JOIN ventas v ON v.id = f.venta_id
        $where
        GROUP BY c.id, c.nombre, c.nit, c.telefono
        ORDER BY total_facturado DESC
    ", $params);

    $filas = [];
    $totalFacturado = 0;
    $totalPagado = 0;
    $totalSaldo = 0;

    foreach ($datos as $d) {
        $saldo = (float)$d['total_facturado'] - (float)$d['total_pagado'];
        $totalFacturado += (float)$d['total_facturado'];
        $totalPagado += (float)$d['total_pagado'];
        $totalSaldo += $saldo;

        $filas[] = [
            $d['cliente'],
            $d['cliente_nit'] ?: '—',
            $d['cliente_telefono'] ?: '—',
            (string)$d['num_facturas'],
            moneda($d['total_facturado']),
            moneda($d['total_pagado']),
            moneda($saldo),
            fecha($d['ultima_factura'], 'd/m/Y'),
        ];
    }

    Response::ok([
        'titulo' => 'Facturación por Cliente',
        'subtitulo' => "Desde $desde hasta $hasta · " . count($datos) . " cliente(s)",
        'columnas' => ['Cliente', 'NIT', 'Teléfono', 'Facturas', 'Total facturado', 'Pagado', 'Saldo', 'Última factura'],
        'filas' => $filas,
        'totales' => [
            'Total facturado' => moneda($totalFacturado),
            'Total pagado'    => moneda($totalPagado),
            'SALDO PENDIENTE' => moneda($totalSaldo),
        ],
    ]);
}

// ============================================================
// FACTURAS POR VENDEDOR
// ============================================================
function datos_facturas_vendedor()
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $clienteId = (int)($_GET['cliente_id'] ?? 0);
    $vendedorId = (int)($_GET['vendedor_id'] ?? 0);
    $estado = trim($_GET['estado'] ?? '');

    $condiciones = [
        'DATE(f.fecha_emision) >= :desde',
        'DATE(f.fecha_emision) <= :hasta',
        "f.estado != 'anulada'",
    ];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($clienteId > 0) {
        $condiciones[] = 'f.cliente_id = :cliente_id';
        $params[':cliente_id'] = $clienteId;
    }
    if ($vendedorId > 0) {
        $condiciones[] = 'v.usuario_id = :vendedor_id';
        $params[':vendedor_id'] = $vendedorId;
    }
    if (in_array($estado, ['emitida', 'parcial', 'pagada'], true)) {
        $condiciones[] = 'f.estado = :estado';
        $params[':estado'] = $estado;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT
            u.nombre AS vendedor,
            pv.nombre AS pv,
            COUNT(f.id) AS num_facturas,
            COALESCE(SUM(f.total), 0) AS total_facturado,
            COALESCE(SUM(COALESCE((SELECT SUM(fp.monto) FROM facturas_pagos fp WHERE fp.factura_id = f.id), 0)), 0) AS total_pagado,
            MAX(f.fecha_emision) AS ultima_factura
        FROM facturas f
        JOIN clientes c ON c.id = f.cliente_id
        JOIN ventas v ON v.id = f.venta_id
        JOIN usuarios u ON u.id = v.usuario_id
        JOIN puntos_venta pv ON pv.id = v.punto_venta_id
        $where
        GROUP BY u.id, u.nombre, pv.nombre
        ORDER BY total_facturado DESC
    ", $params);

    $filas = [];
    $totalFacturado = 0;
    $totalPagado = 0;
    $totalSaldo = 0;

    foreach ($datos as $i => $d) {
        $saldo = (float)$d['total_facturado'] - (float)$d['total_pagado'];
        $totalFacturado += (float)$d['total_facturado'];
        $totalPagado += (float)$d['total_pagado'];
        $totalSaldo += $saldo;

        $filas[] = [
            (string)($i + 1),
            $d['vendedor'],
            $d['pv'],
            (string)$d['num_facturas'],
            moneda($d['total_facturado']),
            moneda($d['total_pagado']),
            moneda($saldo),
            fecha($d['ultima_factura'], 'd/m/Y'),
        ];
    }

    Response::ok([
        'titulo' => 'Facturación por Vendedor',
        'subtitulo' => "Desde $desde hasta $hasta · " . count($datos) . " vendedor(es)",
        'columnas' => ['#', 'Vendedor', 'PV', 'Facturas', 'Total facturado', 'Pagado', 'Saldo', 'Última'],
        'filas' => $filas,
        'totales' => [
            'Total facturado' => moneda($totalFacturado),
            'Total pagado'    => moneda($totalPagado),
            'SALDO PENDIENTE' => moneda($totalSaldo),
        ],
    ]);
}

// ============================================================
// COMPROBANTES POR FECHAS
// ============================================================
function datos_comprobantes_fechas()
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $vendedorId = (int)($_GET['vendedor_id'] ?? 0);
    $pvId = (int)($_GET['pv_id'] ?? 0);
    $estado = trim($_GET['estado'] ?? '');

    $condiciones = [
        'DATE(cv.created_at) >= :desde',
        'DATE(cv.created_at) <= :hasta',
    ];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($vendedorId > 0) {
        $condiciones[] = 'cv.emitido_por = :vendedor_id';
        $params[':vendedor_id'] = $vendedorId;
    }
    if ($pvId > 0) {
        $condiciones[] = 'v.punto_venta_id = :pv_id';
        $params[':pv_id'] = $pvId;
    }
    if (in_array($estado, ['emitido', 'anulado'], true)) {
        $condiciones[] = 'cv.estado = :estado';
        $params[':estado'] = $estado;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT
            cv.folio, cv.created_at, cv.nombre_comprador,
            cv.telefono_comprador, cv.total, cv.estado,
            v.folio AS venta_folio,
            pv.nombre AS pv,
            u.nombre AS emitido_por
        FROM comprobantes_venta cv
        JOIN ventas v ON v.id = cv.venta_id
        JOIN puntos_venta pv ON pv.id = v.punto_venta_id
        JOIN usuarios u ON u.id = cv.emitido_por
        $where
        ORDER BY cv.created_at DESC
        LIMIT 500
    ", $params);

    $filas = [];
    foreach ($datos as $d) {
        $filas[] = [
            $d['folio'],
            fecha($d['created_at'], 'd/m/Y H:i'),
            $d['nombre_comprador'] ?: 'Consumidor final',
            $d['telefono_comprador'] ?: '—',
            $d['venta_folio'],
            $d['pv'],
            $d['emitido_por'],
            ucfirst($d['estado']),
            moneda($d['total']),
        ];
    }

    $totalGeneral = 0;
    $totalEmitidos = 0;
    $totalAnulados = 0;
    foreach ($datos as $d) {
        if ($d['estado'] === 'anulado') {
            $totalAnulados++;
            continue;
        }
        $totalEmitidos++;
        $totalGeneral += (float)$d['total'];
    }

    Response::ok([
        'titulo' => 'Reporte de Comprobantes',
        'subtitulo' => "Desde $desde hasta $hasta · " . count($datos) . " comprobante(s)",
        'columnas' => ['Folio', 'Fecha', 'Comprador', 'Teléfono', 'Venta', 'PV', 'Emitido por', 'Estado', 'Total'],
        'filas' => $filas,
        'totales' => [
            'Comprobantes emitidos' => $totalEmitidos,
            'Comprobantes anulados' => $totalAnulados,
            'TOTAL'                 => moneda($totalGeneral),
        ],
    ]);
}

// ============================================================
// COMPROBANTES POR VENDEDOR
// ============================================================
function datos_comprobantes_vendedor()
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $vendedorId = (int)($_GET['vendedor_id'] ?? 0);
    $pvId = (int)($_GET['pv_id'] ?? 0);

    $condiciones = [
        'DATE(cv.created_at) >= :desde',
        'DATE(cv.created_at) <= :hasta',
        "cv.estado = 'emitido'",
    ];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($vendedorId > 0) {
        $condiciones[] = 'cv.emitido_por = :vendedor_id';
        $params[':vendedor_id'] = $vendedorId;
    }
    if ($pvId > 0) {
        $condiciones[] = 'v.punto_venta_id = :pv_id';
        $params[':pv_id'] = $pvId;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT
            u.nombre AS vendedor,
            pv.nombre AS pv,
            COUNT(cv.id) AS num_comprobantes,
            COALESCE(SUM(cv.total), 0) AS total_general,
            MAX(cv.created_at) AS ultimo_comprobante
        FROM comprobantes_venta cv
        JOIN ventas v ON v.id = cv.venta_id
        JOIN usuarios u ON u.id = cv.emitido_por
        JOIN puntos_venta pv ON pv.id = v.punto_venta_id
        $where
        GROUP BY u.id, u.nombre, pv.nombre
        ORDER BY total_general DESC
    ", $params);

    $filas = [];
    $totalComprobantes = 0;
    $totalMonto = 0;

    foreach ($datos as $i => $d) {
        $totalComprobantes += (int)$d['num_comprobantes'];
        $totalMonto += (float)$d['total_general'];

        $filas[] = [
            (string)($i + 1),
            $d['vendedor'],
            $d['pv'],
            (string)$d['num_comprobantes'],
            moneda($d['total_general']),
            fecha($d['ultimo_comprobante'], 'd/m/Y'),
        ];
    }

    Response::ok([
        'titulo' => 'Comprobantes por Vendedor',
        'subtitulo' => "Desde $desde hasta $hasta · " . count($datos) . " vendedor(es)",
        'columnas' => ['#', 'Vendedor', 'PV', 'Comprobantes', 'Total', 'Último'],
        'filas' => $filas,
        'totales' => [
            'Total comprobantes' => $totalComprobantes,
            'TOTAL MONTO'        => moneda($totalMonto),
        ],
    ]);
}

// ============================================================
// COMPROBANTES POR PV
// ============================================================
function datos_comprobantes_pv()
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $pvId = (int)($_GET['pv_id'] ?? 0);

    $condiciones = [
        'DATE(cv.created_at) >= :desde',
        'DATE(cv.created_at) <= :hasta',
        "cv.estado = 'emitido'",
    ];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($pvId > 0) {
        $condiciones[] = 'v.punto_venta_id = :pv_id';
        $params[':pv_id'] = $pvId;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT
            pv.nombre AS pv,
            pv.direccion AS direccion,
            COUNT(cv.id) AS num_comprobantes,
            COALESCE(SUM(cv.total), 0) AS total_general,
            COUNT(DISTINCT cv.emitido_por) AS num_vendedores
        FROM comprobantes_venta cv
        JOIN ventas v ON v.id = cv.venta_id
        JOIN puntos_venta pv ON pv.id = v.punto_venta_id
        $where
        GROUP BY pv.id, pv.nombre, pv.direccion
        ORDER BY total_general DESC
    ", $params);

    $filas = [];
    $totalComprobantes = 0;
    $totalMonto = 0;

    foreach ($datos as $d) {
        $totalComprobantes += (int)$d['num_comprobantes'];
        $totalMonto += (float)$d['total_general'];

        $filas[] = [
            $d['pv'],
            $d['direccion'] ?: '—',
            (string)$d['num_vendedores'],
            (string)$d['num_comprobantes'],
            moneda($d['total_general']),
        ];
    }

    Response::ok([
        'titulo' => 'Comprobantes por Punto de Venta',
        'subtitulo' => "Desde $desde hasta $hasta · " . count($datos) . " PV",
        'columnas' => ['Punto de venta', 'Dirección', 'Vendedores', 'Comprobantes', 'Total'],
        'filas' => $filas,
        'totales' => [
            'Total comprobantes' => $totalComprobantes,
            'TOTAL MONTO'        => moneda($totalMonto),
        ],
    ]);
}