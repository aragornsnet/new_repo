<?php
/**
 * IPV - API de Inventario (solo lectura para Supervisor/Admin)
 * Solo muestra productos que existen (o han existido) en cada PV.
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
        $pvId = (int)($_GET['pv_id'] ?? 0);
        $catId = (int)($_GET['categoria_id'] ?? 0);
        $filtro = $_GET['filtro'] ?? '';

        // Base: solo productos que tienen fila en stock_punto_venta del PV
        $condiciones = ['pv.activo = 1', 'p.activo = 1', 'pv.es_almacen = 0'];
        $params = [];

        if ($q !== '') {
            $condiciones[] = '(p.nombre LIKE :q1 OR p.codigo_barras LIKE :q2)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
        }
        if ($pvId > 0) {
            $condiciones[] = 'pv.id = :pv_id';
            $params[':pv_id'] = $pvId;
        }
        if ($catId > 0) {
            $condiciones[] = 'p.categoria_id = :cat_id';
            $params[':cat_id'] = $catId;
        }
        if ($filtro === 'bajo') {
            $condiciones[] = 'spv.stock <= p.stock_minimo AND spv.stock >= 0';
        } elseif ($filtro === 'negativo') {
            $condiciones[] = 'spv.stock < 0';
        } elseif ($filtro === 'ok') {
            $condiciones[] = 'spv.stock > p.stock_minimo';
        }

        $where = 'WHERE ' . implode(' AND ', $condiciones);

        $stock = Database::fetchAll("
            SELECT
                spv.id,
                p.id AS producto_id, p.codigo_barras, p.nombre AS producto,
                p.precio, p.costo, p.stock_minimo, p.unidad_medida,
                c.nombre AS categoria,
                pv.id AS pv_id, pv.nombre AS pv,
                spv.stock,
                CASE
                    WHEN spv.stock < 0 THEN 'negativo'
                    WHEN spv.stock <= p.stock_minimo THEN 'bajo'
                    ELSE 'ok'
                END AS estado_stock
            FROM stock_punto_venta spv
            JOIN productos p ON p.id = spv.producto_id
            JOIN puntos_venta pv ON pv.id = spv.punto_venta_id
            LEFT JOIN categorias c ON c.id = p.categoria_id
            $where
            ORDER BY pv.nombre, p.nombre
        ", $params);

        Response::ok($stock);
        break;

    case 'resumen_pv':
        // Todos los PV activos (aunque no tengan productos)
        $resumen = Database::fetchAll("
            SELECT
                pv.id, pv.nombre, pv.direccion,
                (SELECT COUNT(*) FROM stock_punto_venta s 
                    WHERE s.punto_venta_id = pv.id AND s.stock > 0) AS num_productos,
                (SELECT COALESCE(SUM(s.stock), 0) FROM stock_punto_venta s 
                    WHERE s.punto_venta_id = pv.id) AS stock_total,
                (SELECT COUNT(*) FROM stock_punto_venta s 
                    JOIN productos p ON p.id = s.producto_id
                    WHERE s.punto_venta_id = pv.id 
                      AND s.stock <= p.stock_minimo 
                      AND s.stock >= 0) AS items_bajo,
                (SELECT COUNT(*) FROM stock_punto_venta s 
                    WHERE s.punto_venta_id = pv.id AND s.stock < 0) AS items_negativo,
                (SELECT COUNT(*) FROM usuarios u 
                    WHERE u.punto_venta_id = pv.id AND u.rol_id = 3 AND u.activo = 1) AS num_vendedores,
                (SELECT COUNT(*) FROM turnos t 
                    WHERE t.punto_venta_id = pv.id AND t.estado = 'abierto') AS turnos_abiertos
            FROM puntos_venta pv
            WHERE pv.activo = 1 AND pv.es_almacen = 0
            ORDER BY pv.nombre
        ");

        Response::ok($resumen);
        break;

    case 'detalle_producto':
        $prodId = (int)($_GET['producto_id'] ?? 0);
        if ($prodId <= 0) Response::error('ID inválido');

        $producto = Database::fetchOne("
            SELECT p.*, c.nombre AS categoria
            FROM productos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            WHERE p.id = ?
        ", [$prodId]);

        if (!$producto) Response::noEncontrado('Producto no encontrado');

        // Stock por PV (solo los que tienen fila de stock)
        $producto['stock_por_pv'] = Database::fetchAll("
            SELECT
                pv.id AS pv_id, pv.nombre AS pv,
                spv.stock,
                p.stock_minimo
            FROM stock_punto_venta spv
            JOIN puntos_venta pv ON pv.id = spv.punto_venta_id
            JOIN productos p ON p.id = spv.producto_id
            WHERE spv.producto_id = ? 
              AND pv.activo = 1 
              AND pv.es_almacen = 0
            ORDER BY pv.nombre
        ", [$prodId]);

        $producto['movimientos'] = Database::fetchAll("
            SELECT
                m.id, m.tipo, m.cantidad, m.motivo, m.descripcion, m.fecha,
                p.unidad_medida,
                pv.nombre AS pv,
                u.nombre AS usuario
            FROM movimientos m
            JOIN puntos_venta pv ON pv.id = m.punto_venta_id
            JOIN usuarios u ON u.id = m.usuario_id
            JOIN productos p ON p.id = m.producto_id
            WHERE m.producto_id = ?
            ORDER BY m.fecha DESC
            LIMIT 20
        ", [$prodId]);

        Response::ok($producto);
        break;

    case 'catalogos':
        $pvs = Database::fetchAll("
            SELECT id, nombre FROM puntos_venta
            WHERE activo = 1 AND es_almacen = 0
            ORDER BY nombre
        ");
        $cats = Database::fetchAll("
            SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre
        ");

        Response::ok([
            'puntos_venta' => $pvs,
            'categorias'   => $cats,
        ]);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}