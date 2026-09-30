<?php
/**
 * IPV - API del POS (Punto de Venta) para Vendedor
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';

ApiBootstrap::iniciar(['Vendedor', 'Supervisor', 'Administrador']);

$accion = $_GET['accion'] ?? 'productos';
$vendedorId = Auth::id();
$pvId = Auth::puntoVentaId();

function turnoActivo(int $vendedorId): ?array
{
    return Database::fetchOne("
        SELECT t.*, pv.nombre AS pv
        FROM turnos t
        JOIN puntos_venta pv ON pv.id = t.punto_venta_id
        WHERE t.usuario_id = ? AND t.estado = 'abierto'
        LIMIT 1
    ", [$vendedorId]);
}

switch ($accion) {

    case 'inicializar':
        $turno = turnoActivo($vendedorId);

        if (!$turno) {
            Response::error('No tienes un turno abierto. Abre un turno primero.', 403);
        }

        $pvIdTurno = (int) $turno['punto_venta_id'];

        $categorias = Database::fetchAll("
            SELECT c.id, c.nombre, COUNT(DISTINCT p.id) AS num_productos
            FROM categorias c
            INNER JOIN productos p ON p.categoria_id = c.id AND p.activo = 1
            LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id
                AND spv.punto_venta_id = :pv_id
            WHERE c.activo = 1
            AND EXISTS (
                SELECT 1 FROM movimientos m
                WHERE m.producto_id = p.id
                    AND m.punto_venta_id = :pv_id_entrada
                    AND m.tipo = 'entrada'
                LIMIT 1
            )
            " . (Config::bool('pos_mostrar_sin_stock', true) ? "" : "AND COALESCE(spv.stock, 0) > 0") . "
            GROUP BY c.id, c.nombre
            ORDER BY c.nombre
        ", [
            ':pv_id' => $pvIdTurno,
            ':pv_id_entrada' => $pvIdTurno,
        ]);

        $config = [
            'permitir_stock_negativo' => Config::bool('pos_permitir_stock_negativo', true),
            'mostrar_sin_stock'       => Config::bool('pos_mostrar_sin_stock', true),
            'imprimir_preguntar'      => Config::bool('pos_imprimir_preguntar', true),
            'moneda_simbolo'          => Config::get('moneda_simbolo', '$'),
            'moneda_codigo'           => Config::get('moneda_codigo', 'CUP'),
            'negocio_nombre'          => Config::get('empresa_nombre', NEGOCIO_NOMBRE),
            'transf_comprobante_adjunto' => Config::get('transf_comprobante_adjunto', 'opcional'),
        ];

        Response::ok([
            'turno' => [
                'id'             => (int) $turno['id'],
                'pv'             => $turno['pv'],
                'pv_id'          => $pvIdTurno,
                'fecha_apertura' => $turno['fecha_apertura'],
                'monto_inicial'  => (float) $turno['monto_inicial'],
            ],
            'categorias' => $categorias,
            'config' => $config,
        ]);
        break;

    case 'productos':
        $turno = turnoActivo($vendedorId);
        if (!$turno) Response::error('No tienes un turno abierto', 403);

        $pvIdTurno = (int) $turno['punto_venta_id'];

        $q = trim($_GET['q'] ?? '');
        $catId = (int)($_GET['categoria_id'] ?? 0);
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $porPagina = min(max((int)($_GET['por_pagina'] ?? 50), 1), 200);
        $offset = ($pagina - 1) * $porPagina;

        $mostrarSinStock = Config::bool('pos_mostrar_sin_stock', true);

        $condiciones = ['p.activo = 1'];
        $params = [':pv_id' => $pvIdTurno];

        $condiciones[] = "EXISTS (
            SELECT 1 FROM movimientos m
            WHERE m.producto_id = p.id
            AND m.punto_venta_id = :pv_id_entrada
            AND m.tipo = 'entrada'
            LIMIT 1
        )";
        $params[':pv_id_entrada'] = $pvIdTurno;

        if (!$mostrarSinStock) {
            $condiciones[] = 'COALESCE(spv.stock, 0) > 0';
        }

        if ($q !== '') {
            $condiciones[] = '(p.nombre LIKE :q1 OR p.codigo_barras LIKE :q2)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
        }

        if ($catId > 0) {
            $condiciones[] = 'p.categoria_id = :cat_id';
            $params[':cat_id'] = $catId;
        }

        $where = 'WHERE ' . implode(' AND ', $condiciones);

        $total = (int) Database::fetchValue("
            SELECT COUNT(*)
            FROM productos p
            LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = :pv_id
            $where
        ", $params, 0);

        $sql = "
            SELECT
                p.id, p.codigo_barras, p.nombre, p.precio, p.stock_minimo,
                p.unidad_medida,
                COALESCE(spv.stock, 0) AS stock
            FROM productos p
            LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = :pv_id
            $where
            ORDER BY p.nombre
            LIMIT :limite OFFSET :offset
        ";

        $stmt = Database::get()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $productos = $stmt->fetchAll();

        $codigoExacto = null;
        if ($q !== '' && preg_match('/^\d{6,}$/', $q)) {
            $codigoExacto = Database::fetchOne("
                SELECT
                    p.id, p.codigo_barras, p.nombre, p.precio, p.unidad_medida,
                    COALESCE(spv.stock, 0) AS stock
                FROM productos p
                LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = ?
                WHERE p.activo = 1 AND p.codigo_barras = ?
            ", [$pvIdTurno, $q]);
        }

        Response::ok([
            'productos' => $productos,
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_pags' => (int) ceil($total / $porPagina),
            'codigo_exacto' => $codigoExacto,
        ]);
        break;

    case 'buscar_codigo':
        $turno = turnoActivo($vendedorId);
        if (!$turno) Response::error('No tienes un turno abierto', 403);

        $codigo = trim($_GET['codigo'] ?? '');
        if ($codigo === '') Response::error('Código requerido');

        $producto = Database::fetchOne("
            SELECT
                p.id, p.codigo_barras, p.nombre, p.precio, p.stock_minimo,
                p.unidad_medida,
                COALESCE(spv.stock, 0) AS stock
            FROM productos p
            LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = ?
            WHERE p.activo = 1
              AND p.codigo_barras = ?
              AND EXISTS (
                SELECT 1 FROM movimientos m
                WHERE m.producto_id = p.id
                    AND m.punto_venta_id = ?
                    AND m.tipo = 'entrada'
                LIMIT 1
              )
        ", [(int)$turno['punto_venta_id'], $codigo, (int)$turno['punto_venta_id']]);

        if (!$producto) Response::noEncontrado('Producto no encontrado');

        Response::ok($producto);
        break;

    case 'registrar_venta':
        $turno = turnoActivo($vendedorId);
        if (!$turno) Response::error('No tienes un turno abierto', 403);

        $body = jsonBody();
        $items = $body['items'] ?? [];
        $metodoPago = trim($body['metodo_pago'] ?? 'efectivo');
        $montoRecibido = (float)($body['monto_recibido'] ?? 0);
        $imprimir = !empty($body['imprimir']);

        if (empty($items) || !is_array($items)) {
            Response::error('No hay productos en el carrito');
        }

        $itemsLimpios = [];
        foreach ($items as $item) {
            $pid = (int)($item['producto_id'] ?? 0);
            $cant = (int)($item['cantidad'] ?? 0);
            if ($pid > 0 && $cant > 0) {
                $itemsLimpios[] = ['producto_id' => $pid, 'cantidad' => $cant];
            }
        }

        if (empty($itemsLimpios)) {
            Response::error('Los productos del carrito no son válidos');
        }

        $turnoId = (int) $turno['id'];
        $pvIdTurno = (int) $turno['punto_venta_id'];
        $permitirNegativo = Config::bool('pos_permitir_stock_negativo', true);

        try {
            Database::begin();

            $productosValidos = [];
            $subtotalGeneral = 0;

            foreach ($itemsLimpios as $item) {
                $prod = Database::fetchOne("
                    SELECT p.id, p.nombre, p.precio, p.unidad_medida,
                           COALESCE(spv.stock, 0) AS stock
                    FROM productos p
                    LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = ?
                    WHERE p.id = ? AND p.activo = 1
                ", [$pvIdTurno, $item['producto_id']]);

                if (!$prod) {
                    throw new Exception("Producto #{$item['producto_id']} no encontrado");
                }

                $stockActual = (int) $prod['stock'];
                $cantidad = $item['cantidad'];

                if (!$permitirNegativo) {
                    if ($stockActual <= 0) {
                        throw new Exception("'{$prod['nombre']}' no tiene stock disponible");
                    }
                    if ($cantidad > $stockActual) {
                        throw new Exception("Stock insuficiente para '{$prod['nombre']}'");
                    }
                }

                $precio = (float) $prod['precio'];
                $subtotal = $precio * $cantidad;
                $subtotalGeneral += $subtotal;

                $productosValidos[] = [
                    'producto_id' => (int) $prod['id'],
                    'nombre' => $prod['nombre'],
                    'cantidad' => $cantidad,
                    'precio' => $precio,
                    'subtotal' => $subtotal,
                    'stock_actual' => $stockActual,
                    'unidad_medida' => $prod['unidad_medida'] ?: 'Unidad',
                ];
            }

            $total = $subtotalGeneral;

            $metodosPermitidos = ['efectivo', 'transferencia', 'mixto'];
            if (!in_array($metodoPago, $metodosPermitidos, true)) {
                throw new Exception('Método de pago no válido');
            }

            if ($metodoPago === 'efectivo' && $montoRecibido < $total) {
                throw new Exception("El monto recibido es menor al total");
            }

            $folio = generarFolio();

            $ventaId = Database::insert('ventas', [
                'folio'           => $folio,
                'turno_id'        => $turnoId,
                'punto_venta_id'  => $pvIdTurno,
                'usuario_id'      => $vendedorId,
                'subtotal'        => $total,
                'total'           => $total,
                'moneda'          => 'CUP',
                'estado'          => 'completada',
            ]);

            foreach ($productosValidos as $prod) {
                Database::insert('detalle_ventas', [
                    'venta_id'         => $ventaId,
                    'producto_id'      => $prod['producto_id'],
                    'cantidad'         => $prod['cantidad'],
                    'precio_unitario'  => $prod['precio'],
                    'subtotal'         => $prod['subtotal'],
                ]);

                $stockNuevo = $prod['stock_actual'] - $prod['cantidad'];

                Database::query("
                    INSERT INTO stock_punto_venta (producto_id, punto_venta_id, stock)
                    VALUES (:pid, :pvid, :stock)
                    ON DUPLICATE KEY UPDATE stock = :stock2
                ", [
                    ':pid'    => $prod['producto_id'],
                    ':pvid'   => $pvIdTurno,
                    ':stock'  => $stockNuevo,
                    ':stock2' => $stockNuevo,
                ]);

                Database::query("
                    UPDATE turno_inventario
                    SET ventas = ventas + :cantidad
                    WHERE turno_id = :turno AND producto_id = :pid
                ", [
                    ':cantidad' => $prod['cantidad'],
                    ':turno'    => $turnoId,
                    ':pid'      => $prod['producto_id'],
                ]);
            }

            $cambio = 0;
            if ($metodoPago === 'efectivo') {
                $cambio = max(0, $montoRecibido - $total);
                Database::insert('pagos_venta', [
                    'venta_id'  => $ventaId,
                    'metodo'    => 'efectivo',
                    'monto'     => $total,
                    'moneda'    => 'CUP',
                ]);
            } elseif ($metodoPago === 'transferencia') {
                Database::insert('pagos_venta', [
                    'venta_id'       => $ventaId,
                    'metodo'         => 'transferencia',
                    'metodo_detalle' => $body['metodo_detalle'] ?? 'Transferencia',
                    'monto'          => $total,
                    'moneda'         => 'CUP',
                    'referencia'     => $body['referencia'] ?? null,
                ]);
            }

            Database::query("
                UPDATE turnos
                SET total_ventas = total_ventas + :total
                WHERE id = :turno
            ", [':total' => $total, ':turno' => $turnoId]);

            Auditoria::registrar('venta_creada', 'ventas', $ventaId, [
                'folio'      => $folio,
                'total'      => $total,
                'metodo'     => $metodoPago,
                'num_items'  => count($productosValidos),
                'turno_id'   => $turnoId,
            ]);

            Database::commit();

            Response::ok([
                'venta_id' => $ventaId,
                'folio'    => $folio,
                'total'    => $total,
                'cambio'   => $cambio,
                'metodo'   => $metodoPago,
                'items'    => $productosValidos,
            ], 'Venta registrada correctamente');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error registrar venta: ' . $e->getMessage());
            Response::error($e->getMessage());
        }
        break;

    default:
        Response::error('Acción no reconocida', 404);
}