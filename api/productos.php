<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';
require_once __DIR__ . '/../core/Notificacion.php';

ApiBootstrap::iniciar(['Administrador']);

$accion = $_GET['accion'] ?? 'listar';

switch ($accion) {

    case 'listar':
        $q = trim($_GET['q'] ?? '');
        $catId = (int) ($_GET['categoria_id'] ?? 0);
        $estado = $_GET['estado'] ?? '';
        $stockBajo = $_GET['stock_bajo'] ?? '';

        $condiciones = [];
        $params = [];

        if ($q !== '') {
            $condiciones[] = '(p.nombre LIKE :q1 OR p.codigo_barras LIKE :q2 OR p.descripcion LIKE :q3)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
            $params[':q3'] = "%$q%";
        }
        if ($catId > 0) {
            $condiciones[] = 'p.categoria_id = :cat_id';
            $params[':cat_id'] = $catId;
        }
        if ($estado === 'activos') {
            $condiciones[] = 'p.activo = 1';
        } elseif ($estado === 'inactivos') {
            $condiciones[] = 'p.activo = 0';
        }
        if ($stockBajo === '1') {
            $condiciones[] = "(
                SELECT COUNT(*) FROM stock_punto_venta s 
                WHERE s.producto_id = p.id AND s.stock <= p.stock_minimo AND s.stock >= 0
            ) > 0";
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $productos = Database::fetchAll("
            SELECT 
                p.id, p.codigo_barras, p.nombre, p.descripcion,
                p.precio, p.costo, p.stock_minimo, p.unidad_medida, p.activo, p.created_at,
                c.id AS categoria_id, c.nombre AS categoria,
                (SELECT COALESCE(SUM(s.stock), 0) FROM stock_punto_venta s WHERE s.producto_id = p.id) AS stock_total,
                (SELECT COUNT(*) FROM stock_punto_venta s WHERE s.producto_id = p.id AND s.stock <= p.stock_minimo AND s.stock >= 0) AS alertas_bajo,
                (SELECT COUNT(*) FROM stock_punto_venta s WHERE s.producto_id = p.id AND s.stock < 0) AS alertas_negativo
            FROM productos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            $where
            ORDER BY p.activo DESC, p.nombre ASC
        ", $params);

        Response::ok($productos);
        break;

    case 'obtener':
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $p = Database::fetchOne("
            SELECT p.*, c.nombre AS categoria
            FROM productos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            WHERE p.id = ?
        ", [$id]);

        if (!$p) Response::noEncontrado('Producto no encontrado');

        $p['stock_por_pv'] = Database::fetchAll("
            SELECT spv.id, spv.stock, pv.id AS pv_id, pv.nombre AS pv
            FROM stock_punto_venta spv
            JOIN puntos_venta pv ON pv.id = spv.punto_venta_id
            WHERE spv.producto_id = ?
            ORDER BY pv.nombre
        ", [$id]);

        $p['historial_precios'] = Database::fetchAll("
            SELECT ph.id, ph.precio_anterior, ph.precio_nuevo, ph.motivo, ph.fecha,
                   u.nombre AS usuario
            FROM precios_historial ph
            JOIN usuarios u ON u.id = ph.usuario_id
            WHERE ph.producto_id = ?
            ORDER BY ph.fecha DESC
            LIMIT 20
        ", [$id]);

        $p['movimientos_recientes'] = Database::fetchAll("
            SELECT m.id, m.tipo, m.cantidad, m.motivo, m.fecha,
                   m.valor_anterior, m.valor_nuevo,
                   pv.nombre AS pv, u.nombre AS usuario
            FROM movimientos m
            JOIN puntos_venta pv ON pv.id = m.punto_venta_id
            JOIN usuarios u ON u.id = m.usuario_id
            WHERE m.producto_id = ?
            ORDER BY m.fecha DESC
            LIMIT 10
        ", [$id]);

        Response::ok($p);
        break;

    case 'crear':
        Response::prohibido(
            'La creación de productos se realiza desde el módulo de Almacén ' .
            'para garantizar una entrada inicial al almacén.'
        );
        break;

    case 'actualizar':
        $body = jsonBody();
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $prod = Database::fetchOne("SELECT * FROM productos WHERE id = ?", [$id]);
        if (!$prod) Response::noEncontrado('Producto no encontrado');

        $codigo = trim($body['codigo_barras'] ?? '');
        $nombre = trim($body['nombre'] ?? '');
        $descripcion = trim($body['descripcion'] ?? '');
        $precio = (float) ($body['precio'] ?? 0);
        $costo = (float) ($body['costo'] ?? 0);
        $stockMinimo = (int) ($body['stock_minimo'] ?? 5);
        $unidadMedida = trim($body['unidad_medida'] ?? 'Unidad');
        $catId = (int) ($body['categoria_id'] ?? 0);
        $activo = isset($body['activo']) ? (int) $body['activo'] : 1;
        $motivoCambio = trim($body['motivo_cambio_precio'] ?? '');

        $v = new Validador([
            'codigo_barras' => $codigo, 'nombre' => $nombre,
            'precio' => $precio, 'costo' => $costo, 'stock_minimo' => $stockMinimo,
        ]);
        $v->requerido('nombre', 'nombre')->min('nombre', 2)->max('nombre', 120);
        $v->max('descripcion', 255);
        $v->requerido('precio', 'precio')->decimal('precio')->mayorIgual('precio', 0);
        $v->decimal('costo')->mayorIgual('costo', 0);
        $v->entero('stock_minimo')->mayorIgual('stock_minimo', 0);
        $v->max('codigo_barras', 50);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        if ($codigo !== '') {
            $existe = Database::fetchValue(
                "SELECT COUNT(*) FROM productos WHERE codigo_barras = ? AND id != ?",
                [$codigo, $id]
            );
            if ($existe) Response::validacion(['codigo_barras' => 'Ese código ya está en uso']);
        }

        if ($catId <= 0) {
            Response::validacion(['categoria_id' => 'Debes seleccionar una categoría']);
        }
        $existe = Database::fetchValue("SELECT COUNT(*) FROM categorias WHERE id = ?", [$catId]);
        if (!$existe) Response::validacion(['categoria_id' => 'Categoría no válida']);

        if ($unidadMedida === '') $unidadMedida = 'Unidad';
        $unidadValida = Database::fetchValue(
            "SELECT COUNT(*) FROM unidades_medida WHERE nombre = ? AND activo = 1",
            [$unidadMedida]
        );
        if (!$unidadValida) Response::validacion(['unidad_medida' => 'Unidad de medida no válida']);

        try {
            Database::begin();

            Database::update('productos', [
                'codigo_barras' => $codigo ?: null,
                'nombre'        => $nombre,
                'descripcion'   => $descripcion ?: null,
                'precio'        => $precio,
                'costo'         => $costo,
                'stock_minimo'  => $stockMinimo,
                'unidad_medida' => $unidadMedida,
                'categoria_id'  => $catId,
                'activo'        => $activo,
            ], 'id = :id', [':id' => $id]);

            $precioAnterior = (float) $prod['precio'];
            $precioCambio = abs($precioAnterior - $precio) > 0.001;

            if ($precioCambio) {
                Database::insert('precios_historial', [
                    'producto_id'     => $id,
                    'precio_anterior' => $precioAnterior,
                    'precio_nuevo'    => $precio,
                    'usuario_id'      => Auth::id(),
                    'motivo'          => $motivoCambio ?: null,
                ]);

                Notificacion::crearParaRol(
                    4,
                    'producto_precio_actualizado',
                    'Precio actualizado',
                    $nombre . ': ' . moneda($precioAnterior) . ' → ' . moneda($precio),
                    'views/almacen/inventario.php',
                    'tag-fill',
                    'info'
                );
            }

            Auditoria::registrar('producto_actualizado', 'productos', $id, [
                'antes' => [
                    'nombre' => $prod['nombre'],
                    'precio' => $precioAnterior,
                    'costo'  => (float) $prod['costo'],
                    'unidad_medida' => $prod['unidad_medida'],
                    'activo' => $prod['activo'],
                ],
                'despues' => [
                    'nombre' => $nombre,
                    'precio' => $precio,
                    'costo'  => $costo,
                    'unidad_medida' => $unidadMedida,
                    'activo' => $activo,
                ],
            ]);

            Database::commit();

            Response::ok([
                'precio_cambio' => $precioCambio,
                'precio_anterior' => $precioAnterior,
                'unidad_medida' => $unidadMedida,
            ], $precioCambio ? 'Producto actualizado (precio modificado)' : 'Producto actualizado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error actualizar producto: ' . $e->getMessage());
            Response::servidor('No se pudo actualizar');
        }
        break;

    case 'cambiar_estado':
        $body = jsonBody();
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $p = Database::fetchOne("SELECT * FROM productos WHERE id = ?", [$id]);
        if (!$p) Response::noEncontrado('Producto no encontrado');

        $nuevo = $p['activo'] ? 0 : 1;
        Database::update('productos', ['activo' => $nuevo], 'id = :id', [':id' => $id]);

        Auditoria::registrar($nuevo ? 'producto_activado' : 'producto_desactivado',
            'productos', $id, null);

        Response::ok(['activo' => $nuevo],
            $nuevo ? 'Producto activado' : 'Producto desactivado');
        break;

    case 'eliminar':
        $body = jsonBody();
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $p = Database::fetchOne("SELECT * FROM productos WHERE id = ?", [$id]);
        if (!$p) Response::noEncontrado('Producto no encontrado');

        $ventas = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM detalle_ventas WHERE producto_id = ?", [$id]
        );
        $movimientos = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM movimientos WHERE producto_id = ?", [$id]
        );

        if ($ventas > 0 || $movimientos > 0) {
            Response::error(
                'No se puede eliminar: tiene ventas o movimientos asociados. ' .
                'Puedes desactivarlo en su lugar.'
            );
        }

        try {
            Database::begin();

            Database::delete('stock_punto_venta', 'producto_id = :id', [':id' => $id]);
            Database::delete('precios_historial', 'producto_id = :id', [':id' => $id]);
            Database::delete('productos', 'id = :id', [':id' => $id]);

            Auditoria::registrar('producto_eliminado', 'productos', $id, [
                'nombre' => $p['nombre'],
            ]);

            Database::commit();
            Response::ok(null, 'Producto eliminado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error eliminar producto: ' . $e->getMessage());
            Response::servidor('No se pudo eliminar');
        }
        break;

    case 'buscar_codigo':
        $codigo = trim($_GET['codigo'] ?? '');
        if ($codigo === '') Response::error('Código requerido');

        $p = Database::fetchOne("
            SELECT p.id, p.codigo_barras, p.nombre, p.precio, p.unidad_medida, p.activo, c.nombre AS categoria
            FROM productos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            WHERE p.codigo_barras = ? AND p.activo = 1
        ", [$codigo]);

        if (!$p) Response::noEncontrado('Producto no encontrado');
        Response::ok($p);
        break;

    case 'categorias':
        $cats = Database::fetchAll("
            SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre
        ");
        Response::ok($cats);
        break;

    case 'unidades_medida':
        $unidades = Database::fetchAll("
            SELECT nombre, abreviatura FROM unidades_medida WHERE activo = 1 ORDER BY orden
        ");
        Response::ok($unidades);
        break;

    case 'historial_precios':
        $prodId = (int) ($_GET['producto_id'] ?? 0);
        $desde = $_GET['desde'] ?? '';
        $hasta = $_GET['hasta'] ?? '';
        $limit = min(max((int)($_GET['limit'] ?? 100), 1), 500);

        $condiciones = [];
        $params = [];

        if ($prodId > 0) {
            $condiciones[] = 'ph.producto_id = :prod_id';
            $params[':prod_id'] = $prodId;
        }
        if ($desde !== '') {
            $condiciones[] = 'ph.fecha >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        }
        if ($hasta !== '') {
            $condiciones[] = 'ph.fecha <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $sql = "
            SELECT 
                ph.id, ph.precio_anterior, ph.precio_nuevo, ph.motivo, ph.fecha,
                p.id AS producto_id, p.nombre AS producto, p.unidad_medida,
                u.nombre AS usuario
            FROM precios_historial ph
            JOIN productos p ON p.id = ph.producto_id
            JOIN usuarios u ON u.id = ph.usuario_id
            $where
            ORDER BY ph.fecha DESC
            LIMIT :limite
        ";

        $stmt = Database::get()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $historial = $stmt->fetchAll();

        Response::ok($historial);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}