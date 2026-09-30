<?php
/**
 * IPV - API del Almacén
 *
 * Rol: Administrador o Almacenero.
 * El almacenero NO ve precio ni costo. Solo gestiona nombre, código,
 * descripción, categoría, unidad de medida, stock mínimo y cantidad.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';
require_once __DIR__ . '/../core/Notificacion.php';

ApiBootstrap::iniciar(['Administrador', 'Almacenero']);

$accion = $_GET['accion'] ?? 'listar';

function obtenerAlmacenId(): int
{
    $id = (int) Config::int('almacen_id', 0);
    if ($id > 0) {
        $existe = Database::fetchValue(
            "SELECT COUNT(*) FROM puntos_venta WHERE id = ? AND es_almacen = 1 AND activo = 1",
            [$id]
        );
        if ($existe) return $id;
    }

    $id = (int) Database::fetchValue(
        "SELECT id FROM puntos_venta WHERE es_almacen = 1 AND activo = 1 LIMIT 1",
        [],
        0
    );

    if ($id > 0) {
        try {
            $existeClave = Database::fetchValue(
                "SELECT COUNT(*) FROM configuracion WHERE clave = 'almacen_id'"
            );
            if ($existeClave) {
                Database::update('configuracion', ['valor' => $id], 'clave = :clave', [':clave' => 'almacen_id']);
            } else {
                Database::insert('configuracion', [
                    'clave' => 'almacen_id', 'valor' => $id, 'tipo' => 'numero',
                    'categoria' => 'almacen', 'descripcion' => 'ID del PV que actúa como almacén',
                ]);
            }
        } catch (Throwable $e) {
            error_log('No se pudo guardar almacen_id: ' . $e->getMessage());
        }
        return $id;
    }

    try {
        $id = Database::insert('puntos_venta', [
            'nombre' => 'Almacén Central', 'direccion' => null, 'telefono' => null,
            'activo' => 1, 'es_almacen' => 1,
        ]);
        try {
            $existeClave = Database::fetchValue(
                "SELECT COUNT(*) FROM configuracion WHERE clave = 'almacen_id'"
            );
            if ($existeClave) {
                Database::update('configuracion', ['valor' => $id], 'clave = :clave', [':clave' => 'almacen_id']);
            } else {
                Database::insert('configuracion', [
                    'clave' => 'almacen_id', 'valor' => $id, 'tipo' => 'numero',
                    'categoria' => 'almacen', 'descripcion' => 'ID del PV que actúa como almacén',
                ]);
            }
        } catch (Throwable $e) {
            error_log('No se pudo guardar almacen_id tras crear almacén: ' . $e->getMessage());
        }
        return $id;
    } catch (Throwable $e) {
        error_log('Error auto-creando almacén: ' . $e->getMessage());
    }

    Response::error('No se pudo configurar el almacén. Contacta al administrador.');
    exit;
}

function validarUnidadMedida(string $unidad): void
{
    $valida = Database::fetchValue(
        "SELECT COUNT(*) FROM unidades_medida WHERE nombre = ? AND activo = 1",
        [$unidad]
    );
    if (!$valida) Response::validacion(['unidad_medida' => 'Unidad de medida no válida']);
}

switch ($accion) {

    case 'listar':
        $almacenId = obtenerAlmacenId();

        $q      = trim($_GET['q'] ?? '');
        $catId  = (int)($_GET['categoria_id'] ?? 0);
        $filtro = trim($_GET['filtro'] ?? '');

        $condiciones = ['p.activo = 1', 'spv.punto_venta_id = :almacen_id'];
        $params = [':almacen_id' => $almacenId];

        if ($q !== '') {
            $condiciones[] = '(p.nombre LIKE :q1 OR p.codigo_barras LIKE :q2)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
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
                p.id AS producto_id,
                p.codigo_barras,
                p.nombre AS producto,
                p.stock_minimo,
                p.unidad_medida,
                c.id AS categoria_id,
                c.nombre AS categoria,
                spv.stock,
                CASE
                    WHEN spv.stock < 0 THEN 'negativo'
                    WHEN spv.stock <= p.stock_minimo THEN 'bajo'
                    ELSE 'ok'
                END AS estado_stock
            FROM stock_punto_venta spv
            JOIN productos p ON p.id = spv.producto_id
            LEFT JOIN categorias c ON c.id = p.categoria_id
            $where
            ORDER BY p.nombre
        ", $params);

        Response::ok($stock);
        break;

    case 'obtener':
        $almacenId = obtenerAlmacenId();
        $productoId = (int)($_GET['producto_id'] ?? 0);

        if ($productoId <= 0) Response::error('ID de producto inválido');

        $producto = Database::fetchOne("
            SELECT
                p.id, p.codigo_barras, p.nombre, p.descripcion,
                p.stock_minimo, p.unidad_medida, p.activo,
                c.id AS categoria_id, c.nombre AS categoria,
                COALESCE(spv.stock, 0) AS stock_almacen
            FROM productos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = :almacen_id
            WHERE p.id = :pid
        ", [':almacen_id' => $almacenId, ':pid' => $productoId]);

        if (!$producto) Response::noEncontrado('Producto no encontrado');

        $producto['movimientos'] = Database::fetchAll("
            SELECT
                m.id, m.tipo, m.cantidad, m.motivo, m.descripcion,
                m.valor_anterior, m.valor_nuevo, m.fecha,
                p.unidad_medida,
                u.nombre AS usuario
            FROM movimientos m
            JOIN productos p ON p.id = m.producto_id
            JOIN usuarios u ON u.id = m.usuario_id
            WHERE m.producto_id = ? AND m.punto_venta_id = ?
            ORDER BY m.fecha DESC
            LIMIT 20
        ", [$productoId, $almacenId]);

        Response::ok($producto);
        break;

    case 'productos':
        $almacenId = obtenerAlmacenId();

        $q      = trim($_GET['q'] ?? '');
        $catId  = (int)($_GET['categoria_id'] ?? 0);

        $condiciones = ['p.activo = 1'];
        $params = [];

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

        $productos = Database::fetchAll("
            SELECT
                p.id, p.codigo_barras, p.nombre, p.stock_minimo, p.unidad_medida,
                c.nombre AS categoria,
                c.id AS categoria_id,
                COALESCE(spv.stock, 0) AS stock_almacen
            FROM productos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = :almacen_id
            $where
            ORDER BY p.nombre
            LIMIT 500
        ", array_merge($params, [':almacen_id' => $almacenId]));

        Response::ok($productos);
        break;

    // ⭐ NUEVA acción: crear producto + entrada inicial atómica
    case 'crear_producto_con_entrada':
        $almacenId = obtenerAlmacenId();

        $body = jsonBody();

        $codigo        = trim($body['codigo_barras'] ?? '');
        $nombre        = trim($body['nombre'] ?? '');
        $descripcion   = trim($body['descripcion'] ?? '');
        $stockMinimo   = (int)($body['stock_minimo'] ?? 5);
        $unidadMedida  = trim($body['unidad_medida'] ?? 'Unidad');
        $catId         = (int)($body['categoria_id'] ?? 0);
        $cantidadIni   = (int)($body['cantidad_inicial'] ?? 0);
        $motivoEntrada = trim($body['motivo_entrada'] ?? 'Alta inicial de producto');
        $contratoId    = (int)($body['contrato_id'] ?? 0);
        $numeroFactura = trim($body['numero_factura'] ?? '');

        $v = new Validador([
            'nombre'           => $nombre,
            'stock_minimo'     => $stockMinimo,
            'cantidad_inicial' => $cantidadIni,
        ]);
        $v->requerido('nombre', 'nombre')->min('nombre', 2)->max('nombre', 120);
        $v->max('descripcion', 255);
        $v->entero('stock_minimo')->mayorIgual('stock_minimo', 0);
        $v->entero('cantidad_inicial')->mayorIgual('cantidad_inicial', 1);
        $v->max('codigo_barras', 50);
        $v->max('numero_factura', 100);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        if ($codigo !== '') {
            $existe = Database::fetchValue(
                "SELECT COUNT(*) FROM productos WHERE codigo_barras = ?",
                [$codigo]
            );
            if ($existe) {
                Response::validacion(['codigo_barras' => 'Ya existe un producto con ese código']);
            }
        }

        if ($catId <= 0) {
            Response::validacion(['categoria_id' => 'Debes seleccionar una categoría']);
        }
        $existe = Database::fetchValue("SELECT COUNT(*) FROM categorias WHERE id = ? AND activo = 1", [$catId]);
        if (!$existe) {
            Response::validacion(['categoria_id' => 'Categoría no válida o inactiva']);
        }

        if ($unidadMedida === '') $unidadMedida = 'Unidad';
        validarUnidadMedida($unidadMedida);

        // ⭐ Validar contrato si se especificó
        $contratoNumero = null;
        if ($contratoId > 0) {
            $contrato = Database::fetchOne("
                SELECT id, num_contrato, estado
                FROM contratos_proveedor
                WHERE id = ?
            ", [$contratoId]);

            if (!$contrato) {
                Response::validacion(['contrato_id' => 'El contrato especificado no existe']);
            }

            if (in_array($contrato['estado'], ['cancelado', 'no_renovado'], true)) {
                Response::validacion([
                    'contrato_id' => 'El contrato está ' . $contrato['estado'] . ' y no admite entradas',
                ]);
            }

            $contratoNumero = $contrato['num_contrato'];
        }

        try {
            Database::begin();

            // 1) Crear producto
            $productoId = Database::insert('productos', [
                'codigo_barras' => $codigo ?: null,
                'nombre'        => $nombre,
                'descripcion'   => $descripcion ?: null,
                'precio'        => 0,
                'costo'         => 0,
                'stock_minimo'  => $stockMinimo,
                'unidad_medida' => $unidadMedida,
                'categoria_id'  => $catId,
                'activo'        => 1,
                'created_by'    => Auth::id(),
            ]);

            // 2) Stock inicial en el almacén
            Database::insert('stock_punto_venta', [
                'producto_id'    => $productoId,
                'punto_venta_id' => $almacenId,
                'stock'          => $cantidadIni,
            ]);

            // 3) ⭐ Vincular al contrato (si se especificó)
            if ($contratoId > 0) {
                Database::insert('contratos_productos', [
                    'contrato_id' => $contratoId,
                    'producto_id' => $productoId,
                ]);
            }

            // 4) Movimiento de entrada
            $movId = Database::insert('movimientos', [
                'producto_id'    => $productoId,
                'punto_venta_id' => $almacenId,
                'turno_id'       => null,
                'tipo'           => 'entrada',
                'cantidad'       => $cantidadIni,
                'motivo'         => $motivoEntrada,
                'descripcion'    => 'Entrada inicial al crear el producto',
                'valor_anterior' => 0,
                'valor_nuevo'    => $cantidadIni,
                'usuario_id'     => Auth::id(),
                'contrato_id'    => $contratoId > 0 ? $contratoId : null,
                'num_contrato'   => $contratoNumero,
                'numero_factura' => $numeroFactura ?: null,
            ]);

            Auditoria::registrar('producto_creado_almacen', 'productos', $productoId, [
                'nombre'           => $nombre,
                'cantidad_inicial' => $cantidadIni,
                'unidad_medida'    => $unidadMedida,
                'almacen_id'       => $almacenId,
                'contrato'         => $contratoNumero,
                'numero_factura'   => $numeroFactura,
            ]);

            Notificacion::crearParaAdmins(
                'producto_sin_precio',
                'Producto pendiente de precio',
                $nombre . ' (' . $cantidadIni . ' ' . $unidadMedida . ') — asígnale precio y costo desde el módulo Productos',
                'views/admin/productos.php',
                'tag-fill',
                'warning'
            );

            // ⭐ NUEVA: notificar si falta contrato o factura
            self_notificar_entrada_sin_documentos(
                $nombre,
                $cantidadIni,
                $unidadMedida,
                $contratoNumero,
                $numeroFactura,
                null
            );

            Database::commit();

            Response::ok([
                'id'               => $productoId,
                'movimiento_id'    => $movId,
                'cantidad_inicial' => $cantidadIni,
                'unidad_medida'    => $unidadMedida,
                'contrato'         => $contratoNumero,
                'numero_factura'   => $numeroFactura,
            ], 'Producto creado con entrada inicial al almacén');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error crear producto con entrada: ' . $e->getMessage());
            Response::servidor('No se pudo crear el producto: ' . $e->getMessage());
        }
        break;

    // Alias por compatibilidad
    case 'crear_producto':
        // Redirigir al nuevo endpoint
        $_GET['accion'] = 'crear_producto_con_entrada';
        header('Location: ' . $_SERVER['REQUEST_URI'] . '&accion=crear_producto_con_entrada');
        exit;

    case 'actualizar_producto':
        $body = jsonBody();
        $productoId = (int)($body['id'] ?? 0);

        if ($productoId <= 0) Response::error('ID inválido');

        $producto = Database::fetchOne("SELECT * FROM productos WHERE id = ?", [$productoId]);
        if (!$producto) Response::noEncontrado('Producto no encontrado');

        $codigo       = trim($body['codigo_barras'] ?? '');
        $nombre       = trim($body['nombre'] ?? '');
        $descripcion  = trim($body['descripcion'] ?? '');
        $stockMinimo  = (int)($body['stock_minimo'] ?? 5);
        $unidadMedida = trim($body['unidad_medida'] ?? 'Unidad');
        $catId        = (int)($body['categoria_id'] ?? 0);

        // ⚠️ El almacenero NO puede cambiar el estado activo/inactivo
        // Solo el admin lo puede hacer desde api/productos.php?accion=cambiar_estado

        $v = new Validador([
            'nombre'       => $nombre,
            'stock_minimo' => $stockMinimo,
        ]);
        $v->requerido('nombre', 'nombre')->min('nombre', 2)->max('nombre', 120);
        $v->max('descripcion', 255);
        $v->entero('stock_minimo')->mayorIgual('stock_minimo', 0);
        $v->max('codigo_barras', 50);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        if ($codigo !== '') {
            $existe = Database::fetchValue(
                "SELECT COUNT(*) FROM productos WHERE codigo_barras = ? AND id != ?",
                [$codigo, $productoId]
            );
            if ($existe) Response::validacion(['codigo_barras' => 'Ese código ya está en uso']);
        }

        if ($catId <= 0) {
            Response::validacion(['categoria_id' => 'Debes seleccionar una categoría']);
        }
        $existe = Database::fetchValue("SELECT COUNT(*) FROM categorias WHERE id = ? AND activo = 1", [$catId]);
        if (!$existe) Response::validacion(['categoria_id' => 'Categoría no válida o inactiva']);

        if ($unidadMedida === '') $unidadMedida = 'Unidad';
        validarUnidadMedida($unidadMedida);

        try {
            Database::begin();

            Database::update('productos', [
                'codigo_barras' => $codigo ?: null,
                'nombre'        => $nombre,
                'descripcion'   => $descripcion ?: null,
                'stock_minimo'  => $stockMinimo,
                'unidad_medida' => $unidadMedida,
                'categoria_id'  => $catId,
            ], 'id = :id', [':id' => $productoId]);

            Auditoria::registrar('producto_actualizado_almacen', 'productos', $productoId, [
                'antes'   => ['nombre' => $producto['nombre']],
                'despues' => ['nombre' => $nombre, 'unidad_medida' => $unidadMedida],
            ]);

            Database::commit();

            Response::ok(null, 'Producto actualizado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error actualizar producto almacén: ' . $e->getMessage());
            Response::servidor('No se pudo actualizar el producto');
        }
        break;

    case 'ajustar_stock':
        $almacenId = obtenerAlmacenId();
        $body = jsonBody();

        $productoId = (int)($body['producto_id'] ?? 0);
        $nuevoStock = (int)($body['nuevo_stock'] ?? -1);
        $motivo     = trim($body['motivo'] ?? '');

        if ($productoId <= 0) Response::validacion(['producto_id' => 'Producto requerido']);
        // ✅ Solo 0 o mayor (Opción 2)
        if ($nuevoStock < 0) Response::validacion(['nuevo_stock' => 'El stock debe ser 0 o mayor']);
        if (mb_strlen($motivo) < 3) Response::validacion(['motivo' => 'El motivo es obligatorio (mínimo 3 caracteres)']);

        $producto = Database::fetchOne("SELECT * FROM productos WHERE id = ? AND activo = 1", [$productoId]);
        if (!$producto) Response::noEncontrado('Producto no encontrado o inactivo');

        $unidad = $producto['unidad_medida'] ?: 'Unidad';

        $stockActual = Database::fetchOne("
            SELECT stock FROM stock_punto_venta
            WHERE producto_id = ? AND punto_venta_id = ?
        ", [$productoId, $almacenId]);

        $stockAnterior = $stockActual ? (int)$stockActual['stock'] : 0;
        $diferencia = $nuevoStock - $stockAnterior;

        if ($diferencia === 0) {
            Response::error('El nuevo stock es igual al actual');
        }

        try {
            Database::begin();

            if ($stockActual) {
                Database::update('stock_punto_venta',
                    ['stock' => $nuevoStock],
                    'producto_id = :pid AND punto_venta_id = :pvid',
                    [':pid' => $productoId, ':pvid' => $almacenId]
                );
            } else {
                Database::insert('stock_punto_venta', [
                    'producto_id'    => $productoId,
                    'punto_venta_id' => $almacenId,
                    'stock'          => $nuevoStock,
                ]);
            }

            $movId = Database::insert('movimientos', [
                'producto_id'    => $productoId,
                'punto_venta_id' => $almacenId,
                'turno_id'       => null,
                'tipo'           => 'ajuste',
                'cantidad'       => $diferencia,
                'motivo'         => 'Ajuste manual: ' . $motivo,
                'valor_anterior' => $stockAnterior,
                'valor_nuevo'    => $nuevoStock,
                'usuario_id'     => Auth::id(),
            ]);

            Auditoria::registrar('ajuste_stock_almacen', 'movimientos', $movId, [
                'producto'       => $producto['nombre'],
                'unidad'         => $unidad,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => $nuevoStock,
                'diferencia'     => $diferencia,
                'motivo'         => $motivo,
            ]);

            Database::commit();

            Response::ok([
                'movimiento_id'  => $movId,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => $nuevoStock,
                'diferencia'     => $diferencia,
                'unidad_medida'  => $unidad,
            ], 'Stock ajustado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error ajustar stock almacén: ' . $e->getMessage());
            Response::servidor('No se pudo ajustar el stock: ' . $e->getMessage());
        }
        break;

    case 'eliminar_producto':
        $body = jsonBody();
        $productoId = (int)($body['id'] ?? 0);

        if ($productoId <= 0) Response::error('ID inválido');

        $producto = Database::fetchOne("SELECT * FROM productos WHERE id = ?", [$productoId]);
        if (!$producto) Response::noEncontrado('Producto no encontrado');

        $ventas = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM detalle_ventas WHERE producto_id = ?", [$productoId]
        );
        $movimientos = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM movimientos WHERE producto_id = ?", [$productoId]
        );

        if ($ventas > 0 || $movimientos > 0) {
            Response::error(
                'No se puede eliminar: tiene ventas o movimientos asociados. ' .
                'Puedes desactivarlo en su lugar.'
            );
        }

        try {
            Database::begin();

            Database::delete('stock_punto_venta', 'producto_id = :id', [':id' => $productoId]);
            Database::delete('precios_historial', 'producto_id = :id', [':id' => $productoId]);
            Database::delete('productos', 'id = :id', [':id' => $productoId]);

            Auditoria::registrar('producto_eliminado_almacen', 'productos', $productoId, [
                'nombre' => $producto['nombre'],
            ]);

            Database::commit();

            Response::ok(null, 'Producto eliminado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error eliminar producto almacén: ' . $e->getMessage());
            Response::servidor('No se pudo eliminar el producto');
        }
        break;

    case 'entrada':
        $almacenId = obtenerAlmacenId();
        $body = jsonBody();

        $productoId     = (int)($body['producto_id'] ?? 0);
        $cantidad       = (int)($body['cantidad'] ?? 0);
        $motivo         = trim($body['motivo'] ?? '');
        $referencia     = trim($body['referencia'] ?? '');
        $descripcion    = trim($body['descripcion'] ?? '');
        $contratoId     = (int)($body['contrato_id'] ?? 0);
        $numeroFactura  = trim($body['numero_factura'] ?? '');

        $v = new Validador([
            'producto_id' => $productoId,
            'cantidad'    => $cantidad,
            'motivo'      => $motivo,
        ]);
        $v->requerido('producto_id', 'producto')->entero('producto_id')->mayorIgual('producto_id', 1);
        $v->requerido('cantidad', 'cantidad')->entero('cantidad')->mayorIgual('cantidad', 1);
        $v->requerido('motivo', 'motivo')->min('motivo', 3)->max('motivo', 200);
        $v->max('descripcion', 255);
        $v->max('referencia', 100);
        $v->max('numero_factura', 100);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        $producto = Database::fetchOne("SELECT * FROM productos WHERE id = ? AND activo = 1", [$productoId]);
        if (!$producto) Response::noEncontrado('Producto no encontrado o inactivo');

        // ⭐ Validar contrato si se especificó
        $contratoNumero = null;
        if ($contratoId > 0) {
            $contrato = Database::fetchOne("
                SELECT id, num_contrato, estado, fecha_caducidad
                FROM contratos_proveedor
                WHERE id = ?
            ", [$contratoId]);

            if (!$contrato) {
                Response::validacion(['contrato_id' => 'El contrato especificado no existe']);
            }

            if (in_array($contrato['estado'], ['cancelado', 'no_renovado'], true)) {
                Response::validacion([
                    'contrato_id' => 'El contrato está ' . $contrato['estado'] . ' y no admite entradas',
                ]);
            }

            // Verificar que el producto esté vinculado al contrato
            $vinculado = Database::fetchValue("
                SELECT COUNT(*) FROM contratos_productos
                WHERE contrato_id = ? AND producto_id = ?
            ", [$contratoId, $productoId]);

            if (!$vinculado) {
                Response::validacion([
                    'contrato_id' => 'El producto no está vinculado a este contrato',
                ]);
            }

            $contratoNumero = $contrato['num_contrato'];
        }

        $unidad = $producto['unidad_medida'] ?: 'Unidad';

        $stockActual = Database::fetchOne("
            SELECT stock FROM stock_punto_venta
            WHERE producto_id = ? AND punto_venta_id = ?
        ", [$productoId, $almacenId]);

        $stockAnterior = $stockActual ? (int)$stockActual['stock'] : 0;
        $stockNuevo = $stockAnterior + $cantidad;

        try {
            Database::begin();

            if ($stockActual) {
                Database::update('stock_punto_venta',
                    ['stock' => $stockNuevo],
                    'producto_id = :pid AND punto_venta_id = :pvid',
                    [':pid' => $productoId, ':pvid' => $almacenId]
                );
            } else {
                Database::insert('stock_punto_venta', [
                    'producto_id'    => $productoId,
                    'punto_venta_id' => $almacenId,
                    'stock'          => $stockNuevo,
                ]);
            }

            $motivoCompleto = $referencia ? "$motivo · Ref: $referencia" : $motivo;

            $movId = Database::insert('movimientos', [
                'producto_id'    => $productoId,
                'punto_venta_id' => $almacenId,
                'turno_id'       => null,
                'tipo'           => 'entrada',
                'cantidad'       => $cantidad,
                'motivo'         => $motivoCompleto,
                'descripcion'    => $descripcion ?: null,
                'valor_anterior' => $stockAnterior,
                'valor_nuevo'    => $stockNuevo,
                'usuario_id'     => Auth::id(),
                'contrato_id'    => $contratoId > 0 ? $contratoId : null,
                'num_contrato'   => $contratoNumero,
                'numero_factura' => $numeroFactura ?: null,
            ]);

            Auditoria::registrar('entrada_almacen', 'movimientos', $movId, [
                'producto'       => $producto['nombre'],
                'cantidad'       => $cantidad,
                'unidad'         => $unidad,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => $stockNuevo,
                'motivo'         => $motivoCompleto,
                'contrato'       => $contratoNumero,
                'numero_factura' => $numeroFactura,
            ]);

            // ⭐ NUEVA: notificar si falta contrato o factura
            self_notificar_entrada_sin_documentos(
                $producto['nombre'],
                $cantidad,
                $unidad,
                $contratoNumero,
                $numeroFactura,
                $pv ?? null
            );

            Database::commit();

            Response::ok([
                'movimiento_id'  => $movId,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => $stockNuevo,
                'unidad_medida'  => $unidad,
                'contrato'       => $contratoNumero,
                'numero_factura' => $numeroFactura,
            ], 'Entrada registrada correctamente');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error entrada almacén: ' . $e->getMessage());
            Response::servidor('No se pudo registrar la entrada: ' . $e->getMessage());
        }
        break;

    case 'historial_entradas':
        $almacenId = obtenerAlmacenId();

        $q      = trim($_GET['q'] ?? '');
        $desde  = trim($_GET['desde'] ?? '');
        $hasta  = trim($_GET['hasta'] ?? '');

        $condiciones = [
            "m.tipo = 'entrada'",
            'm.punto_venta_id = :almacen_id',
        ];
        $params = [':almacen_id' => $almacenId];

        if ($q !== '') {
            $condiciones[] = '(p.nombre LIKE :q1 OR p.codigo_barras LIKE :q2 OR m.motivo LIKE :q3 OR m.descripcion LIKE :q4 OR m.num_contrato LIKE :q5 OR m.numero_factura LIKE :q6)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
            $params[':q3'] = "%$q%";
            $params[':q4'] = "%$q%";
            $params[':q5'] = "%$q%";
            $params[':q6'] = "%$q%";
        }
        if ($desde !== '') {
            $condiciones[] = 'm.fecha >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        }
        if ($hasta !== '') {
            $condiciones[] = 'm.fecha <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        $where = 'WHERE ' . implode(' AND ', $condiciones);

        $movimientos = Database::fetchAll("
            SELECT
                m.id, m.cantidad, m.motivo, m.descripcion,
                m.valor_anterior, m.valor_nuevo, m.fecha,
                m.contrato_id, m.num_contrato, m.numero_factura,
                p.id AS producto_id, p.nombre AS producto, p.codigo_barras,
                p.unidad_medida,
                u.id AS usuario_id, u.nombre AS usuario
            FROM movimientos m
            JOIN productos p ON p.id = m.producto_id
            JOIN usuarios u ON u.id = m.usuario_id
            $where
            ORDER BY m.fecha DESC
            LIMIT 500
        ", $params);

        Response::ok($movimientos);
        break;

    case 'catalogos':
        $almacenId = obtenerAlmacenId();

        $categorias = Database::fetchAll("
            SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre
        ");

        $unidades = Database::fetchAll("
            SELECT nombre, abreviatura FROM unidades_medida WHERE activo = 1 ORDER BY orden
        ");

        $almacen = Database::fetchOne("
            SELECT id, nombre, direccion FROM puntos_venta WHERE id = ?
        ", [$almacenId]);

        $motivosEntrada = [
            'Compra a proveedor',
            'Reabastecimiento',
            'Devolución de cliente',
            'Ajuste por conteo físico',
            'Producción propia',
            'Otro',
        ];

        $motivosAjuste = [
            'Corrección por conteo físico',
            'Merma - Producto dañado',
            'Merma - Producto vencido',
            'Robo o pérdida',
            'Error de captura',
            'Devolución a proveedor',
            'Otro (especificar)',
        ];

        Response::ok([
            'almacen'         => $almacen,
            'categorias'      => $categorias,
            'unidades_medida' => $unidades,
            'motivos_entrada' => $motivosEntrada,
            'motivos_ajuste'  => $motivosAjuste,
        ]);
        break;

    // ============================================================
    // TRAZABILIDAD — Listado de productos con agregados
    // ============================================================
    case 'trazabilidad_listar':
        $almacenId = obtenerAlmacenId();

        $q       = trim($_GET['q'] ?? '');
        $catId   = (int)($_GET['categoria_id'] ?? 0);
        $soloCon = ($_GET['con_movimientos'] ?? '0') === '1';
        $soloDes = ($_GET['con_descuadres'] ?? '0') === '1';

        $condiciones = ['p.activo = 1'];
        $params = [
            ':almacen_id'  => $almacenId,
            ':almacen_id2' => $almacenId,
        ];

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

        $having = [];
        if ($soloCon) $having[] = 'num_movimientos > 0';
        if ($soloDes) $having[] = 'total_ajustes <> 0';
        $havingSql = $having ? 'HAVING ' . implode(' AND ', $having) : '';

        $productos = Database::fetchAll("
            SELECT
                p.id AS producto_id,
                p.codigo_barras,
                p.nombre AS producto,
                p.unidad_medida,
                c.id AS categoria_id,
                c.nombre AS categoria,
                COALESCE(spv.stock, 0) AS stock_actual,
                COALESCE(SUM(CASE WHEN m.tipo = 'entrada' THEN m.cantidad ELSE 0 END), 0) AS total_entradas,
                COALESCE(SUM(CASE WHEN m.tipo = 'transferencia' THEN m.cantidad ELSE 0 END), 0) AS total_salidas,
                COALESCE(SUM(CASE WHEN m.tipo = 'ajuste' THEN m.cantidad ELSE 0 END), 0) AS total_ajustes,
                COUNT(m.id) AS num_movimientos,
                MAX(m.fecha) AS ultimo_movimiento_fecha
            FROM productos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = :almacen_id
            LEFT JOIN movimientos m ON m.producto_id = p.id AND m.punto_venta_id = :almacen_id2
            $where
            GROUP BY p.id, p.codigo_barras, p.nombre, p.unidad_medida, c.id, c.nombre, spv.stock
            $havingSql
            ORDER BY p.nombre
            LIMIT 1000
        ", $params);

        Response::ok($productos);
        break;

    // ============================================================
    // TRAZABILIDAD — Expediente de un producto
    // ============================================================
    case 'trazabilidad_producto':
        $almacenId = obtenerAlmacenId();
        $productoId = (int)($_GET['producto_id'] ?? 0);
        if ($productoId <= 0) Response::error('ID de producto inválido');

        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');
        $tipo  = trim($_GET['tipo'] ?? '');
        $limit = min(max((int)($_GET['limit'] ?? 500), 1), 2000);

        // Info del producto + resumen
        $producto = Database::fetchOne("
            SELECT
                p.id, p.codigo_barras, p.nombre, p.descripcion,
                p.unidad_medida, p.stock_minimo,
                c.nombre AS categoria,
                COALESCE(spv.stock, 0) AS stock_actual
            FROM productos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = ?
            WHERE p.id = ?
        ", [$almacenId, $productoId]);

        if (!$producto) Response::noEncontrado('Producto no encontrado');

        $producto['resumen'] = Database::fetchOne("
            SELECT
                COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN cantidad ELSE 0 END), 0) AS total_entradas,
                COALESCE(SUM(CASE WHEN tipo = 'transferencia' THEN cantidad ELSE 0 END), 0) AS total_salidas,
                COALESCE(SUM(CASE WHEN tipo = 'ajuste' THEN cantidad ELSE 0 END), 0) AS total_ajustes,
                COUNT(*) AS num_movimientos,
                MIN(fecha) AS primer_movimiento,
                MAX(fecha) AS ultimo_movimiento
            FROM movimientos
            WHERE producto_id = ? AND punto_venta_id = ?
        ", [$productoId, $almacenId]);

        // Timeline
        $condiciones = ['m.producto_id = :pid', 'm.punto_venta_id = :almacen_id'];
        $params = [':pid' => $productoId, ':almacen_id' => $almacenId];

        if ($desde !== '') {
            $condiciones[] = 'm.fecha >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        }
        if ($hasta !== '') {
            $condiciones[] = 'm.fecha <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }
        if (in_array($tipo, ['entrada', 'transferencia', 'ajuste'], true)) {
            $condiciones[] = 'm.tipo = :tipo';
            $params[':tipo'] = $tipo;
        }

        $where = 'WHERE ' . implode(' AND ', $condiciones);

        $producto['movimientos'] = Database::fetchAll("
            SELECT
                m.id, m.tipo, m.cantidad, m.motivo, m.descripcion,
                m.valor_anterior, m.valor_nuevo, m.fecha,
                u.nombre AS usuario
            FROM movimientos m
            JOIN usuarios u ON u.id = m.usuario_id
            $where
            ORDER BY m.fecha DESC, m.id DESC
            LIMIT $limit
        ", $params);

        Response::ok($producto);
        break;

    // ============================================================
    // CONTRATOS DISPONIBLES PARA UN PRODUCTO
    // ============================================================
    case 'contratos_para_producto':
        $productoId = (int)($_GET['producto_id'] ?? 0);
        if ($productoId <= 0) Response::error('Producto requerido');

        $contratos = Database::fetchAll("
            SELECT
                c.id, c.num_contrato,
                c.fecha_caducidad,
                p.nombre AS proveedor,
                CASE
                    WHEN c.estado = 'activo' AND c.fecha_caducidad < CURDATE() THEN 'por_renovar'
                    ELSE c.estado
                END AS estado_display
            FROM contratos_proveedor c
            JOIN proveedores p ON p.id = c.proveedor_id
            JOIN contratos_productos cp ON cp.contrato_id = c.id
            WHERE cp.producto_id = ?
              AND c.estado IN ('activo', 'por_renovar')
              AND c.estado NOT IN ('cancelado', 'no_renovado')
            ORDER BY c.fecha_caducidad ASC
        ", [$productoId]);

        Response::ok($contratos);
        break;

    // ============================================================
    // VINCULAR MOVIMIENTO A CONTRATO (solo Admin)
    // ============================================================
    case 'vincular_movimiento_contrato':
        if (!Auth::esAdmin()) {
            Response::prohibido('Solo el administrador puede vincular movimientos a contratos');
        }

        $body = jsonBody();
        $movimientoId = (int)($body['movimiento_id'] ?? 0);
        $contratoId   = (int)($body['contrato_id'] ?? 0);
        $numeroFactura = trim($body['numero_factura'] ?? '');

        if ($movimientoId <= 0) Response::validacion(['movimiento_id' => 'Movimiento inválido']);
        if ($contratoId <= 0) Response::validacion(['contrato_id' => 'Contrato inválido']);
        if (mb_strlen($numeroFactura) > 100) {
            Response::validacion(['numero_factura' => 'Máximo 100 caracteres']);
        }

        // Verificar movimiento
        $mov = Database::fetchOne("
            SELECT id, producto_id, punto_venta_id, tipo, num_contrato, numero_factura
            FROM movimientos
            WHERE id = ?
        ", [$movimientoId]);

        if (!$mov) Response::noEncontrado('Movimiento no encontrado');
        if ($mov['tipo'] !== 'entrada') {
            Response::error('Solo se pueden vincular movimientos de tipo entrada');
        }
        if (!empty($mov['num_contrato'])) {
            Response::error('Este movimiento ya tiene un contrato vinculado: ' . $mov['num_contrato']);
        }

        // Verificar contrato
        $contrato = Database::fetchOne("
            SELECT id, num_contrato, estado
            FROM contratos_proveedor
            WHERE id = ?
        ", [$contratoId]);

        if (!$contrato) Response::noEncontrado('Contrato no encontrado');

        if (in_array($contrato['estado'], ['cancelado', 'no_renovado'], true)) {
            Response::error('El contrato está ' . $contrato['estado'] . ' y no admite movimientos');
        }

        // Verificar que el producto esté vinculado al contrato
        $vinculado = Database::fetchValue("
            SELECT COUNT(*) FROM contratos_productos
            WHERE contrato_id = ? AND producto_id = ?
        ", [$contratoId, (int)$mov['producto_id']]);

        if (!$vinculado) {
            Response::error('El producto de este movimiento no está vinculado al contrato seleccionado');
        }

        try {
            Database::begin();

            $datosUpdate = [
                'contrato_id'  => $contratoId,
                'num_contrato' => $contrato['num_contrato'],
            ];

            if ($numeroFactura !== '') {
                $datosUpdate['numero_factura'] = $numeroFactura;
            }

            Database::update('movimientos', $datosUpdate, 'id = :id', [':id' => $movimientoId]);

            Auditoria::registrar('movimiento_vinculado_contrato', 'movimientos', $movimientoId, [
                'contrato'         => $contrato['num_contrato'],
                'numero_factura'   => $numeroFactura ?: null,
                'num_contrato_prev'=> $mov['num_contrato'],
                'factura_previa'   => $mov['numero_factura'],
            ]);

            Database::commit();

            Response::ok([
                'movimiento_id' => $movimientoId,
                'num_contrato'  => $contrato['num_contrato'],
            ], 'Movimiento vinculado al contrato');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error vincular movimiento: ' . $e->getMessage());
            Response::servidor('No se pudo vincular el movimiento');
        }
        break;

    // ============================================================
    // CONTRATOS VIGENTES (todos los activos o por renovar)
    // Se usa en el modo "crear producto" del almacenero.
    // ============================================================
    case 'contratos_vigentes':
        $contratos = Database::fetchAll("
            SELECT
                c.id, c.num_contrato,
                c.fecha_caducidad,
                p.nombre AS proveedor,
                CASE
                    WHEN c.estado = 'activo' AND c.fecha_caducidad < CURDATE() THEN 'por_renovar'
                    ELSE c.estado
                END AS estado_display
            FROM contratos_proveedor c
            JOIN proveedores p ON p.id = c.proveedor_id
            WHERE c.estado IN ('activo', 'por_renovar')
            ORDER BY c.fecha_caducidad ASC
        ");

        Response::ok($contratos);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}

/**
 * Notifica a Admin + Supervisor cuando una entrada al almacén se registra
 * sin contrato o sin número de factura.
 *
 * Se llama desde:
 *   - case 'entrada'
 *   - case 'crear_producto_con_entrada'
 *
 * Genera hasta 2 notificaciones por entrada (una por cada faltante).
 * Cada notificación incluye el ID del movimiento para poder trazarla.
 */
function self_notificar_entrada_sin_documentos(
    string $producto,
    int $cantidad,
    string $unidad,
    ?string $contratoNumero,
    string $numeroFactura,
    ?array $pv
): void {
    try {
        $faltantes = [];

        if (empty($contratoNumero)) {
            $faltantes[] = 'sin contrato';
        }
        if ($numeroFactura === '') {
            $faltantes[] = 'sin factura';
        }

        if (empty($faltantes)) return;

        $usuario = Auth::user();
        $nombreUsuario = $usuario['nombre'] ?? 'Sistema';
        $nombrePv = $pv['nombre'] ?? 'Almacén';

        // Título y mensaje según qué falte
        if (count($faltantes) === 2) {
            $titulo = 'Entrada sin contrato ni factura';
            $detalle = 'sin contrato ni factura';
        } elseif (in_array('sin contrato', $faltantes, true)) {
            $titulo = 'Entrada sin contrato';
            $detalle = 'sin contrato';
        } else {
            $titulo = 'Entrada sin factura';
            $detalle = 'sin factura';
        }

        $mensaje = $producto . ' · ' . $cantidad . ' ' . $unidad
                 . ' · ' . $nombrePv
                 . ' · ' . $detalle
                 . ' · por ' . $nombreUsuario;

        Notificacion::crearParaSupervisores(
            'entrada_sin_documentos',
            $titulo,
            $mensaje,
            'views/almacen/entradas.php',
            'exclamation-triangle-fill',
            'warning'
        );

    } catch (Throwable $e) {
        error_log('self_notificar_entrada_sin_documentos error: ' . $e->getMessage());
    }
}