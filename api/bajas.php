<?php
/**
 * IPV - API de Bajas de inventario (Supervisor y Administrador)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';
require_once __DIR__ . '/../core/Notificacion.php';

ApiBootstrap::iniciar(['Supervisor', 'Administrador']);

$accion = $_GET['accion'] ?? 'listar';

const MOTIVOS_BAJA = [
    'Merma - Producto vencido',
    'Merma - Producto dañado',
    'Robo o pérdida',
    'Daño en manipulación',
    'Error de conteo',
    'Devolución al proveedor',
    'Otro (especificar en descripción)',
];

switch ($accion) {

    case 'listar':
        $q = trim($_GET['q'] ?? '');
        $pvId = (int)($_GET['pv_id'] ?? 0);
        $usuarioId = (int)($_GET['usuario_id'] ?? 0);
        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');

        $condiciones = ["m.tipo = 'baja'"];
        $params = [];

        if ($q !== '') {
            $condiciones[] = '(p.nombre LIKE :q1 OR p.codigo_barras LIKE :q2 OR m.motivo LIKE :q3 OR m.descripcion LIKE :q4)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
            $params[':q3'] = "%$q%";
            $params[':q4'] = "%$q%";
        }
        if ($pvId > 0) {
            $condiciones[] = 'm.punto_venta_id = :pv_id';
            $params[':pv_id'] = $pvId;
        }
        if ($usuarioId > 0) {
            $condiciones[] = 'm.usuario_id = :usuario_id';
            $params[':usuario_id'] = $usuarioId;
        }
        if ($desde !== '') {
            $condiciones[] = 'm.fecha >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        } else {
            $condiciones[] = 'DATE(m.fecha) = CURDATE()';
        }
        if ($hasta !== '') {
            $condiciones[] = 'm.fecha <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        $where = 'WHERE ' . implode(' AND ', $condiciones);

        $bajas = Database::fetchAll("
            SELECT 
                m.id, m.cantidad, m.motivo, m.descripcion, m.valor_anterior, m.valor_nuevo, m.fecha,
                p.id AS producto_id, p.nombre AS producto, p.codigo_barras,
                p.unidad_medida,
                pv.id AS pv_id, pv.nombre AS pv,
                u.id AS usuario_id, u.nombre AS usuario
            FROM movimientos m
            JOIN productos p ON p.id = m.producto_id
            JOIN puntos_venta pv ON pv.id = m.punto_venta_id
            JOIN usuarios u ON u.id = m.usuario_id
            $where
            ORDER BY m.fecha DESC
            LIMIT 500
        ", $params);

        Response::ok($bajas);
        break;

    case 'productos_por_pv':
        $pvId = (int)($_GET['pv_id'] ?? 0);
        if ($pvId <= 0) Response::error('PV requerido');

        $q = trim($_GET['q'] ?? '');

        $condiciones = ['p.activo = 1', 'pv.es_almacen = 0', 'COALESCE(spv.stock, 0) > 0'];
        $params = [':pv_id' => $pvId];

        if ($q !== '') {
            $condiciones[] = '(p.nombre LIKE :q1 OR p.codigo_barras LIKE :q2)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
        }

        $where = 'WHERE ' . implode(' AND ', $condiciones);

        $productos = Database::fetchAll("
            SELECT 
                p.id, p.codigo_barras, p.nombre, p.precio, p.costo,
                p.unidad_medida,
                COALESCE(spv.stock, 0) AS stock,
                c.nombre AS categoria
            FROM productos p
            JOIN puntos_venta pv ON pv.id = :pv_id
            LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = pv.id
            LEFT JOIN categorias c ON c.id = p.categoria_id
            $where
            ORDER BY p.nombre
        ", $params);

        Response::ok($productos);
        break;

    case 'buscar_codigo':
        $codigo = trim($_GET['codigo'] ?? '');
        $pvId = (int)($_GET['pv_id'] ?? 0);

        if ($codigo === '') Response::error('Código requerido');
        if ($pvId <= 0) Response::error('PV requerido');

        $producto = Database::fetchOne("
            SELECT 
                p.id, p.codigo_barras, p.nombre, p.precio, p.costo,
                p.unidad_medida,
                COALESCE(spv.stock, 0) AS stock,
                c.nombre AS categoria
            FROM productos p
            JOIN puntos_venta pv ON pv.id = ?
            LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = pv.id
            LEFT JOIN categorias c ON c.id = p.categoria_id
            WHERE p.codigo_barras = ? AND p.activo = 1 AND COALESCE(spv.stock, 0) > 0
        ", [$pvId, $codigo]);

        if (!$producto) Response::noEncontrado('Producto no encontrado o sin stock');

        Response::ok($producto);
        break;

    case 'registrar':
        $body = jsonBody();

        $productoId = (int)($body['producto_id'] ?? 0);
        $pvId = (int)($body['punto_venta_id'] ?? 0);
        $cantidad = (int)($body['cantidad'] ?? 0);
        $motivo = trim($body['motivo'] ?? '');
        $descripcion = trim($body['descripcion'] ?? '');

        $v = new Validador([
            'producto_id' => $productoId,
            'punto_venta_id' => $pvId,
            'cantidad' => $cantidad,
            'motivo' => $motivo,
            'descripcion' => $descripcion,
        ]);
        $v->requerido('producto_id', 'producto')->entero('producto_id')->mayorIgual('producto_id', 1);
        $v->requerido('punto_venta_id', 'punto de venta')->entero('punto_venta_id')->mayorIgual('punto_venta_id', 1);
        $v->requerido('cantidad', 'cantidad')->entero('cantidad')->mayorIgual('cantidad', 1);
        $v->requerido('motivo', 'motivo')->min('motivo', 3)->max('motivo', 200);
        $v->requerido('descripcion', 'descripción')->min('descripcion', 3)->max('descripcion', 255);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        if (!in_array($motivo, MOTIVOS_BAJA, true)) {
            Response::validacion(['motivo' => 'Motivo no válido']);
        }

        $producto = Database::fetchOne("SELECT * FROM productos WHERE id = ? AND activo = 1", [$productoId]);
        if (!$producto) Response::noEncontrado('Producto no encontrado o inactivo');

        $pv = Database::fetchOne("SELECT * FROM puntos_venta WHERE id = ? AND activo = 1 AND es_almacen = 0", [$pvId]);
        if (!$pv) Response::noEncontrado('Punto de venta no encontrado');

        $stockActual = Database::fetchOne("
            SELECT COALESCE(spv.stock, 0) AS stock
            FROM productos p
            LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = ?
            WHERE p.id = ?
        ", [$pvId, $productoId]);

        $stockAnterior = $stockActual ? (int)$stockActual['stock'] : 0;
        $stockNuevo = $stockAnterior - $cantidad;
        $unidad = $producto['unidad_medida'] ?: 'Unidad';

        try {
            Database::begin();

            Database::query("
                INSERT INTO stock_punto_venta (producto_id, punto_venta_id, stock)
                VALUES (:pid, :pvid, :stock)
                ON DUPLICATE KEY UPDATE stock = :stock2
            ", [
                ':pid'    => $productoId,
                ':pvid'   => $pvId,
                ':stock'  => $stockNuevo,
                ':stock2' => $stockNuevo,
            ]);

            $turnoAbierto = Database::fetchOne("
                SELECT id FROM turnos WHERE punto_venta_id = ? AND estado = 'abierto' LIMIT 1
            ", [$pvId]);

            $turnoId = $turnoAbierto ? (int)$turnoAbierto['id'] : null;

            $movId = Database::insert('movimientos', [
                'producto_id'    => $productoId,
                'punto_venta_id' => $pvId,
                'turno_id'       => $turnoId,
                'tipo'           => 'baja',
                'cantidad'       => $cantidad,
                'motivo'         => $motivo,
                'descripcion'    => $descripcion,
                'valor_anterior' => $stockAnterior,
                'valor_nuevo'    => $stockNuevo,
                'usuario_id'     => Auth::id(),
            ]);

            if ($turnoId) {
                $ti = Database::fetchOne("
                    SELECT id, bajas FROM turno_inventario 
                    WHERE turno_id = ? AND producto_id = ?
                ", [$turnoId, $productoId]);

                if ($ti) {
                    Database::update('turno_inventario',
                        ['bajas' => (int)$ti['bajas'] + $cantidad],
                        'id = :id',
                        [':id' => $ti['id']]
                    );
                }
            }

            Auditoria::registrar('baja_inventario', 'movimientos', $movId, [
                'producto'       => $producto['nombre'],
                'pv'             => $pv['nombre'],
                'cantidad'       => $cantidad,
                'unidad'         => $unidad,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => $stockNuevo,
                'motivo'         => $motivo,
                'descripcion'    => $descripcion,
            ]);

            if ($stockNuevo < 0) {
                Notificacion::crearParaSupervisores(
                    'stock_negativo',
                    'Stock negativo',
                    $producto['nombre'] . ' quedó en ' . $stockNuevo . ' ' . $unidad . ' en ' . $pv['nombre'],
                    'views/inventario/index.php?filtro=negativo',
                    'x-octagon-fill',
                    'danger'
                );
            } elseif ($stockNuevo <= (int)$producto['stock_minimo']) {
                Notificacion::crearParaSupervisores(
                    'stock_bajo',
                    'Stock bajo',
                    $producto['nombre'] . ' quedó en ' . $stockNuevo . ' ' . $unidad . ' (mínimo: ' . $producto['stock_minimo'] . ' ' . $unidad . ') en ' . $pv['nombre'],
                    'views/inventario/index.php?filtro=bajo',
                    'exclamation-triangle-fill',
                    'warning'
                );
            }

            Database::commit();

            Response::ok([
                'movimiento_id'  => $movId,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => $stockNuevo,
                'unidad_medida'  => $unidad,
                'alerta'         => $stockNuevo < 0 ? 'El stock quedó en negativo' : null,
            ], 'Baja registrada correctamente');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error baja: ' . $e->getMessage());
            Response::servidor('No se pudo registrar la baja: ' . $e->getMessage());
        }
        break;

    case 'registrar_masivo':
        $body = jsonBody();

        $pvId = (int)($body['punto_venta_id'] ?? 0);
        $motivo = trim($body['motivo'] ?? '');
        $descripcion = trim($body['descripcion'] ?? '');
        $items = $body['items'] ?? [];

        if ($pvId <= 0) Response::error('PV requerido');
        if ($motivo === '') Response::error('Motivo requerido');
        if ($descripcion === '') Response::error('Descripción requerida para carga masiva');
        if (empty($items) || !is_array($items)) Response::error('No hay productos para procesar');
        if (!in_array($motivo, MOTIVOS_BAJA, true)) Response::error('Motivo no válido');

        $pv = Database::fetchOne("SELECT * FROM puntos_venta WHERE id = ? AND activo = 1 AND es_almacen = 0", [$pvId]);
        if (!$pv) Response::noEncontrado('Punto de venta no encontrado');

        $procesados = 0;
        $errores = [];
        $negativos = [];
        $bajos = [];

        try {
            Database::begin();

            $turnoAbierto = Database::fetchOne("
                SELECT id FROM turnos WHERE punto_venta_id = ? AND estado = 'abierto' LIMIT 1
            ", [$pvId]);
            $turnoId = $turnoAbierto ? (int)$turnoAbierto['id'] : null;

            foreach ($items as $item) {
                $productoId = (int)($item['producto_id'] ?? 0);
                $cantidad = (int)($item['cantidad'] ?? 0);

                if ($productoId <= 0 || $cantidad <= 0) continue;

                $producto = Database::fetchOne(
                    "SELECT id, nombre, stock_minimo, unidad_medida FROM productos WHERE id = ? AND activo = 1",
                    [$productoId]
                );
                if (!$producto) {
                    $errores[] = "Producto #$productoId no encontrado";
                    continue;
                }

                $unidad = $producto['unidad_medida'] ?: 'Unidad';

                $stockActual = Database::fetchOne("
                    SELECT COALESCE(spv.stock, 0) AS stock
                    FROM productos p
                    LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = ?
                    WHERE p.id = ?
                ", [$pvId, $productoId]);

                $stockAnterior = $stockActual ? (int)$stockActual['stock'] : 0;
                $stockNuevo = $stockAnterior - $cantidad;

                Database::query("
                    INSERT INTO stock_punto_venta (producto_id, punto_venta_id, stock)
                    VALUES (:pid, :pvid, :stock)
                    ON DUPLICATE KEY UPDATE stock = :stock2
                ", [
                    ':pid'    => $productoId,
                    ':pvid'   => $pvId,
                    ':stock'  => $stockNuevo,
                    ':stock2' => $stockNuevo,
                ]);

                Database::insert('movimientos', [
                    'producto_id'    => $productoId,
                    'punto_venta_id' => $pvId,
                    'turno_id'       => $turnoId,
                    'tipo'           => 'baja',
                    'cantidad'       => $cantidad,
                    'motivo'         => $motivo,
                    'descripcion'    => $descripcion,
                    'valor_anterior' => $stockAnterior,
                    'valor_nuevo'    => $stockNuevo,
                    'usuario_id'     => Auth::id(),
                ]);

                if ($turnoId) {
                    $ti = Database::fetchOne("
                        SELECT id, bajas FROM turno_inventario 
                        WHERE turno_id = ? AND producto_id = ?
                    ", [$turnoId, $productoId]);

                    if ($ti) {
                        Database::update('turno_inventario',
                            ['bajas' => (int)$ti['bajas'] + $cantidad],
                            'id = :id',
                            [':id' => $ti['id']]
                        );
                    }
                }

                if ($stockNuevo < 0) {
                    $negativos[] = $producto['nombre'] . ' (' . $stockNuevo . ' ' . $unidad . ')';
                } elseif ($stockNuevo <= (int)$producto['stock_minimo']) {
                    $bajos[] = $producto['nombre'] . ' (' . $stockNuevo . ' ' . $unidad . ')';
                }

                $procesados++;
            }

            Auditoria::registrar('baja_masiva', 'movimientos', null, [
                'pv'          => $pv['nombre'],
                'motivo'      => $motivo,
                'descripcion' => $descripcion,
                'procesados'  => $procesados,
                'total_items' => count($items),
                'errores'     => $errores,
            ]);

            if (!empty($negativos)) {
                Notificacion::crearParaSupervisores(
                    'stock_negativo',
                    'Stock negativo (' . count($negativos) . ')',
                    $pv['nombre'] . ': ' . implode(', ', array_slice($negativos, 0, 3)) . (count($negativos) > 3 ? '...' : ''),
                    'views/inventario/index.php?filtro=negativo',
                    'x-octagon-fill',
                    'danger'
                );
            }

            if (!empty($bajos) && empty($negativos)) {
                Notificacion::crearParaSupervisores(
                    'stock_bajo',
                    'Stock bajo (' . count($bajos) . ')',
                    $pv['nombre'] . ': ' . implode(', ', array_slice($bajos, 0, 3)) . (count($bajos) > 3 ? '...' : ''),
                    'views/inventario/index.php?filtro=bajo',
                    'exclamation-triangle-fill',
                    'warning'
                );
            }

            Database::commit();

            Response::ok([
                'procesados' => $procesados,
                'errores'    => $errores,
            ], "Se registraron $procesados baja(s)");

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error baja masiva: ' . $e->getMessage());
            Response::servidor('No se pudieron registrar las bajas: ' . $e->getMessage());
        }
        break;

    case 'catalogos':
        $pvs = Database::fetchAll("
            SELECT id, nombre FROM puntos_venta WHERE activo = 1 AND es_almacen = 0 ORDER BY nombre
        ");

        Response::ok([
            'puntos_venta' => $pvs,
            'motivos' => MOTIVOS_BAJA,
        ]);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}