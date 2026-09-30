<?php
/**
 * IPV - API de Entradas de inventario (Supervisor y Administrador)
 *
 * NOTA (Fase 7): con el módulo Almacén, las entradas directas al PV ya no son
 * el flujo principal. Este endpoint se mantiene como fallback para ajustes
 * administrativos puntuales. Se usa LEFT JOIN para tratar productos sin fila
 * de stock como stock 0 (Opción 2 del módulo Almacén).
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

switch ($accion) {

    case 'listar':
        $q = trim($_GET['q'] ?? '');
        $pvId = (int)($_GET['pv_id'] ?? 0);
        $usuarioId = (int)($_GET['usuario_id'] ?? 0);
        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');

        $condiciones = ["m.tipo = 'entrada'"];
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

        $movimientos = Database::fetchAll("
            SELECT 
                m.id, m.cantidad, m.motivo, m.descripcion, m.valor_anterior, m.valor_nuevo, m.fecha,
                p.id AS producto_id, p.nombre AS producto, p.codigo_barras,
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

        Response::ok($movimientos);
        break;

    // ============================================================
    // PRODUCTOS por PV (para autocompletar)
    // ⭐ Fase 7: LEFT JOIN + COALESCE para tratar NULL como 0
    // ============================================================
    case 'productos_por_pv':
        $pvId = (int)($_GET['pv_id'] ?? 0);
        if ($pvId <= 0) Response::error('PV requerido');

        $q = trim($_GET['q'] ?? '');

        $condiciones = ['p.activo = 1', 'pv.es_almacen = 0'];
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
                COALESCE(spv.stock, 0) AS stock,
                c.nombre AS categoria,
                c.id AS categoria_id
            FROM productos p
            JOIN puntos_venta pv ON pv.id = :pv_id
            LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = pv.id
            LEFT JOIN categorias c ON c.id = p.categoria_id
            $where
            ORDER BY p.nombre
        ", $params);

        Response::ok($productos);
        break;

    // ============================================================
    // BUSCAR por código
    // ⭐ Fase 7: LEFT JOIN
    // ============================================================
    case 'buscar_codigo':
        $codigo = trim($_GET['codigo'] ?? '');
        $pvId = (int)($_GET['pv_id'] ?? 0);

        if ($codigo === '') Response::error('Código requerido');
        if ($pvId <= 0) Response::error('PV requerido');

        $producto = Database::fetchOne("
            SELECT 
                p.id, p.codigo_barras, p.nombre, p.precio, p.costo, p.stock_minimo,
                COALESCE(spv.stock, 0) AS stock,
                c.nombre AS categoria,
                pv.nombre AS pv
            FROM productos p
            JOIN puntos_venta pv ON pv.id = ?
            LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = pv.id
            LEFT JOIN categorias c ON c.id = p.categoria_id
            WHERE p.codigo_barras = ? AND p.activo = 1
        ", [$pvId, $codigo]);

        if (!$producto) Response::noEncontrado('Producto no encontrado en este PV');

        Response::ok($producto);
        break;

    // ============================================================
    // REGISTRAR entrada individual
    // ============================================================
    case 'registrar':
        $body = jsonBody();

        $productoId = (int)($body['producto_id'] ?? 0);
        $pvId = (int)($body['punto_venta_id'] ?? 0);
        $cantidad = (int)($body['cantidad'] ?? 0);
        $motivo = trim($body['motivo'] ?? '');
        $descripcion = trim($body['descripcion'] ?? '');
        $referencia = trim($body['referencia'] ?? '');

        $v = new Validador([
            'producto_id' => $productoId,
            'punto_venta_id' => $pvId,
            'cantidad' => $cantidad,
            'motivo' => $motivo,
        ]);
        $v->requerido('producto_id', 'producto')->entero('producto_id')->mayorIgual('producto_id', 1);
        $v->requerido('punto_venta_id', 'punto de venta')->entero('punto_venta_id')->mayorIgual('punto_venta_id', 1);
        $v->requerido('cantidad', 'cantidad')->entero('cantidad')->mayorIgual('cantidad', 1);
        $v->requerido('motivo', 'motivo')->min('motivo', 3)->max('motivo', 200);
        $v->max('descripcion', 255);
        $v->max('referencia', 100);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        $producto = Database::fetchOne("SELECT * FROM productos WHERE id = ? AND activo = 1", [$productoId]);
        if (!$producto) Response::noEncontrado('Producto no encontrado o inactivo');

        $pv = Database::fetchOne("SELECT * FROM puntos_venta WHERE id = ? AND activo = 1 AND es_almacen = 0", [$pvId]);
        if (!$pv) Response::noEncontrado('Punto de venta no encontrado o inválido');

        // ⭐ Fase 7: LEFT JOIN + COALESCE
        $stockActual = Database::fetchOne("
            SELECT COALESCE(spv.stock, 0) AS stock
            FROM productos p
            LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = ?
            WHERE p.id = ?
        ", [$pvId, $productoId]);

        $stockAnterior = $stockActual ? (int)$stockActual['stock'] : 0;
        $stockNuevo = $stockAnterior + $cantidad;

        try {
            Database::begin();

            // ⭐ Fase 7: INSERT ... ON DUPLICATE KEY UPDATE
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

            $motivoCompleto = $referencia ? "$motivo · Ref: $referencia" : $motivo;

            $movId = Database::insert('movimientos', [
                'producto_id'    => $productoId,
                'punto_venta_id' => $pvId,
                'turno_id'       => $turnoId,
                'tipo'           => 'entrada',
                'cantidad'       => $cantidad,
                'motivo'         => $motivoCompleto,
                'descripcion'    => $descripcion ?: null,
                'valor_anterior' => $stockAnterior,
                'valor_nuevo'    => $stockNuevo,
                'usuario_id'     => Auth::id(),
            ]);

            if ($turnoId) {
                $ti = Database::fetchOne("
                    SELECT id, entradas FROM turno_inventario 
                    WHERE turno_id = ? AND producto_id = ?
                ", [$turnoId, $productoId]);

                if ($ti) {
                    Database::update('turno_inventario',
                        ['entradas' => (int)$ti['entradas'] + $cantidad],
                        'id = :id',
                        [':id' => $ti['id']]
                    );
                }
            }

            Auditoria::registrar('entrada_inventario', 'movimientos', $movId, [
                'producto'       => $producto['nombre'],
                'pv'             => $pv['nombre'],
                'cantidad'       => $cantidad,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => $stockNuevo,
                'motivo'         => $motivoCompleto,
                'referencia'     => $referencia,
            ]);

            if ($stockNuevo < 0) {
                Notificacion::crearParaSupervisores(
                    'stock_negativo',
                    'Stock aún negativo tras entrada',
                    $producto['nombre'] . ' en ' . $pv['nombre'] . ': ' . $stockNuevo,
                    'views/inventario/index.php?filtro=negativo',
                    'x-octagon-fill',
                    'danger'
                );
            }

            Database::commit();

            Response::ok([
                'movimiento_id'  => $movId,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => $stockNuevo,
            ], 'Entrada registrada correctamente');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error entrada: ' . $e->getMessage());
            Response::servidor('No se pudo registrar la entrada: ' . $e->getMessage());
        }
        break;

    // ============================================================
    // REGISTRAR entrada masiva
    // ============================================================
    case 'registrar_masivo':
        $body = jsonBody();

        $pvId = (int)($body['punto_venta_id'] ?? 0);
        $motivo = trim($body['motivo'] ?? '');
        $referencia = trim($body['referencia'] ?? '');
        $items = $body['items'] ?? [];

        if ($pvId <= 0) Response::error('PV requerido');
        if ($motivo === '') Response::error('Motivo requerido');
        if (empty($items) || !is_array($items)) Response::error('No hay productos para procesar');

        $pv = Database::fetchOne("SELECT * FROM puntos_venta WHERE id = ? AND activo = 1 AND es_almacen = 0", [$pvId]);
        if (!$pv) Response::noEncontrado('Punto de venta no encontrado');

        $procesados = 0;
        $errores = [];
        $siguenNegativos = [];

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

                $producto = Database::fetchOne("SELECT id, nombre FROM productos WHERE id = ? AND activo = 1", [$productoId]);
                if (!$producto) {
                    $errores[] = "Producto #$productoId no encontrado";
                    continue;
                }

                // ⭐ Fase 7: LEFT JOIN
                $stockActual = Database::fetchOne("
                    SELECT COALESCE(spv.stock, 0) AS stock
                    FROM productos p
                    LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = ?
                    WHERE p.id = ?
                ", [$pvId, $productoId]);

                $stockAnterior = $stockActual ? (int)$stockActual['stock'] : 0;
                $stockNuevo = $stockAnterior + $cantidad;

                // ⭐ Fase 7: INSERT ... ON DUPLICATE KEY UPDATE
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

                $motivoCompleto = $referencia ? "$motivo · Ref: $referencia" : $motivo;

                Database::insert('movimientos', [
                    'producto_id'    => $productoId,
                    'punto_venta_id' => $pvId,
                    'turno_id'       => $turnoId,
                    'tipo'           => 'entrada',
                    'cantidad'       => $cantidad,
                    'motivo'         => $motivoCompleto,
                    'valor_anterior' => $stockAnterior,
                    'valor_nuevo'    => $stockNuevo,
                    'usuario_id'     => Auth::id(),
                ]);

                if ($turnoId) {
                    $ti = Database::fetchOne("
                        SELECT id, entradas FROM turno_inventario 
                        WHERE turno_id = ? AND producto_id = ?
                    ", [$turnoId, $productoId]);

                    if ($ti) {
                        Database::update('turno_inventario',
                            ['entradas' => (int)$ti['entradas'] + $cantidad],
                            'id = :id',
                            [':id' => $ti['id']]
                        );
                    }
                }

                if ($stockNuevo < 0) {
                    $siguenNegativos[] = $producto['nombre'] . ' (' . $stockNuevo . ')';
                }

                $procesados++;
            }

            Auditoria::registrar('entrada_masiva', 'movimientos', null, [
                'pv'          => $pv['nombre'],
                'motivo'      => $motivo,
                'referencia'  => $referencia,
                'procesados'  => $procesados,
                'total_items' => count($items),
                'errores'     => $errores,
            ]);

            if (!empty($siguenNegativos)) {
                Notificacion::crearParaSupervisores(
                    'stock_negativo',
                    'Productos aún en negativo (' . count($siguenNegativos) . ')',
                    $pv['nombre'] . ': ' . implode(', ', array_slice($siguenNegativos, 0, 3)) . (count($siguenNegativos) > 3 ? '...' : ''),
                    'views/inventario/index.php?filtro=negativo',
                    'x-octagon-fill',
                    'danger'
                );
            }

            Database::commit();

            Response::ok([
                'procesados' => $procesados,
                'errores'    => $errores,
            ], "Se registraron $procesados entrada(s)");

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error entrada masiva: ' . $e->getMessage());
            Response::servidor('No se pudieron registrar las entradas: ' . $e->getMessage());
        }
        break;

    case 'catalogos':
        $pvs = Database::fetchAll("
            SELECT id, nombre FROM puntos_venta WHERE activo = 1 AND es_almacen = 0 ORDER BY nombre
        ");

        $motivos = [
            'Compra a proveedor',
            'Reabastecimiento',
            'Devolución de cliente',
            'Traslado de otro PV',
            'Ajuste por conteo físico',
            'Producción propia',
            'Otro',
        ];

        Response::ok([
            'puntos_venta' => $pvs,
            'motivos' => $motivos,
        ]);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}