<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';

ApiBootstrap::iniciar(['Administrador']);

$accion = $_GET['accion'] ?? 'listar';

switch ($accion) {

    case 'listar':
        $busqueda = trim($_GET['q'] ?? '');
        $estado = $_GET['estado'] ?? '';

        $condiciones = [];
        $params = [];

        if ($busqueda !== '') {
            $condiciones[] = '(pv.nombre LIKE :q1 OR pv.direccion LIKE :q2)';
            $params[':q1'] = "%$busqueda%";
            $params[':q2'] = "%$busqueda%";
        }
        if ($estado === 'activos') {
            $condiciones[] = 'pv.activo = 1';
        } elseif ($estado === 'inactivos') {
            $condiciones[] = 'pv.activo = 0';
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $puntos = Database::fetchAll("
            SELECT 
                pv.id, pv.nombre, pv.direccion, pv.telefono, pv.activo, pv.es_almacen, pv.created_at,
                (SELECT COUNT(*) FROM usuarios u WHERE u.punto_venta_id = pv.id AND u.activo = 1) AS num_vendedores,
                (SELECT COUNT(*) FROM stock_punto_venta s WHERE s.punto_venta_id = pv.id AND s.stock > 0) AS productos_con_stock,
                (SELECT COALESCE(SUM(s.stock), 0) FROM stock_punto_venta s WHERE s.punto_venta_id = pv.id) AS stock_total,
                (SELECT COALESCE(SUM(v.total), 0) FROM ventas v 
                    WHERE v.punto_venta_id = pv.id 
                      AND MONTH(v.fecha) = MONTH(CURDATE()) 
                      AND YEAR(v.fecha) = YEAR(CURDATE())
                      AND v.estado = 'completada') AS ventas_mes,
                (SELECT COUNT(*) FROM ventas v 
                    WHERE v.punto_venta_id = pv.id 
                      AND DATE(v.fecha) = CURDATE()
                      AND v.estado = 'completada') AS ventas_hoy
            FROM puntos_venta pv
            $where
            ORDER BY pv.activo DESC, pv.nombre ASC
        ", $params);

        Response::ok($puntos);
        break;

    case 'obtener':
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $pv = Database::fetchOne("
            SELECT id, nombre, direccion, telefono, activo, es_almacen, created_at
            FROM puntos_venta WHERE id = ?
        ", [$id]);

        if (!$pv) Response::noEncontrado('Punto de venta no encontrado');

        $pv['vendedores'] = Database::fetchAll("
            SELECT id, nombre, email, activo FROM usuarios 
            WHERE punto_venta_id = ? AND rol_id = 3
            ORDER BY activo DESC, nombre
        ", [$id]);

        $pv['turnos_abiertos'] = Database::fetchAll("
            SELECT t.id, u.nombre AS vendedor, t.fecha_apertura, t.monto_inicial
            FROM turnos t
            JOIN usuarios u ON u.id = t.usuario_id
            WHERE t.punto_venta_id = ? AND t.estado = 'abierto'
            ORDER BY t.fecha_apertura DESC
        ", [$id]);

        Response::ok($pv);
        break;

    case 'crear':
        $body = jsonBody();

        $nombre = trim($body['nombre'] ?? '');
        $direccion = trim($body['direccion'] ?? '');
        $telefono = trim($body['telefono'] ?? '');
        $activo = isset($body['activo']) ? (int) $body['activo'] : 1;

        $v = new Validador(['nombre' => $nombre, 'direccion' => $direccion, 'telefono' => $telefono]);
        $v->requerido('nombre', 'nombre')->min('nombre', 3)->max('nombre', 100);
        $v->max('direccion', 200);
        $v->max('telefono', 30);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        $existe = Database::fetchValue(
            "SELECT COUNT(*) FROM puntos_venta WHERE LOWER(nombre) = LOWER(?)", [$nombre]
        );
        if ($existe) Response::validacion(['nombre' => 'Ya existe un punto de venta con ese nombre']);

        try {
            Database::begin();

            $id = Database::insert('puntos_venta', [
                'nombre'    => $nombre,
                'direccion' => $direccion ?: null,
                'telefono'  => $telefono ?: null,
                'activo'    => $activo,
            ]);

            // ✅ NO se crean filas de stock.
            // El stock del PV se crea cuando reciba un traslado desde el almacén.

            Auditoria::registrar('pv_creado', 'puntos_venta', $id, [
                'nombre' => $nombre, 'direccion' => $direccion,
            ]);

            Database::commit();
            Response::ok(['id' => $id], 'Punto de venta creado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error crear PV: ' . $e->getMessage());
            Response::servidor('No se pudo crear el punto de venta');
        }
        break;

    case 'actualizar':
        $body = jsonBody();
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $pvActual = Database::fetchOne("SELECT * FROM puntos_venta WHERE id = ?", [$id]);
        if (!$pvActual) Response::noEncontrado('Punto de venta no encontrado');

        $nombre = trim($body['nombre'] ?? '');
        $direccion = trim($body['direccion'] ?? '');
        $telefono = trim($body['telefono'] ?? '');
        $activo = isset($body['activo']) ? (int) $body['activo'] : 1;

        $v = new Validador(['nombre' => $nombre]);
        $v->requerido('nombre', 'nombre')->min('nombre', 3)->max('nombre', 100);
        $v->max('direccion', 200);
        $v->max('telefono', 30);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        $existe = Database::fetchValue(
            "SELECT COUNT(*) FROM puntos_venta WHERE LOWER(nombre) = LOWER(?) AND id != ?",
            [$nombre, $id]
        );
        if ($existe) Response::validacion(['nombre' => 'Ya existe otro PV con ese nombre']);

        if ($activo === 0 && $pvActual['activo']) {
            $turnos = (int) Database::fetchValue(
                "SELECT COUNT(*) FROM turnos WHERE punto_venta_id = ? AND estado = 'abierto'", [$id]
            );
            if ($turnos > 0) Response::error('No se puede desactivar: hay turnos abiertos');
        }

        try {
            Database::begin();

            Database::update('puntos_venta', [
                'nombre'    => $nombre,
                'direccion' => $direccion ?: null,
                'telefono'  => $telefono ?: null,
                'activo'    => $activo,
            ], 'id = :id', [':id' => $id]);

            Auditoria::registrar('pv_actualizado', 'puntos_venta', $id, [
                'antes'   => ['nombre' => $pvActual['nombre'], 'activo' => $pvActual['activo']],
                'despues' => compact('nombre', 'activo'),
            ]);

            Database::commit();
            Response::ok(null, 'Punto de venta actualizado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error actualizar PV: ' . $e->getMessage());
            Response::servidor('No se pudo actualizar');
        }
        break;

    case 'cambiar_estado':
        $body = jsonBody();
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $pv = Database::fetchOne("SELECT * FROM puntos_venta WHERE id = ?", [$id]);
        if (!$pv) Response::noEncontrado('PV no encontrado');

        $nuevo = $pv['activo'] ? 0 : 1;

        if ($nuevo === 0) {
            $turnos = (int) Database::fetchValue(
                "SELECT COUNT(*) FROM turnos WHERE punto_venta_id = ? AND estado = 'abierto'", [$id]
            );
            if ($turnos > 0) Response::error('No se puede desactivar: hay turnos abiertos');
        }

        Database::update('puntos_venta', ['activo' => $nuevo], 'id = :id', [':id' => $id]);

        Auditoria::registrar($nuevo ? 'pv_activado' : 'pv_desactivado', 'puntos_venta', $id, null);

        Response::ok(['activo' => $nuevo], $nuevo ? 'Punto de venta activado' : 'Punto de venta desactivado');
        break;

    case 'eliminar':
        $body = jsonBody();
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $pv = Database::fetchOne("SELECT * FROM puntos_venta WHERE id = ?", [$id]);
        if (!$pv) Response::noEncontrado('PV no encontrado');

        $vendedores = (int) Database::fetchValue("SELECT COUNT(*) FROM usuarios WHERE punto_venta_id = ?", [$id]);
        $ventas = (int) Database::fetchValue("SELECT COUNT(*) FROM ventas WHERE punto_venta_id = ?", [$id]);
        $turnos = (int) Database::fetchValue("SELECT COUNT(*) FROM turnos WHERE punto_venta_id = ?", [$id]);
        $movimientos = (int) Database::fetchValue("SELECT COUNT(*) FROM movimientos WHERE punto_venta_id = ?", [$id]);

        if ($vendedores || $ventas || $turnos || $movimientos) {
            Response::error(
                'No se puede eliminar: tiene dependencias. Puedes desactivarlo en su lugar.'
            );
        }

        try {
            Database::begin();

            Database::delete('stock_punto_venta', 'punto_venta_id = :id', [':id' => $id]);
            Database::delete('puntos_venta', 'id = :id', [':id' => $id]);

            Auditoria::registrar('pv_eliminado', 'puntos_venta', $id, [
                'nombre' => $pv['nombre'],
            ]);

            Database::commit();
            Response::ok(null, 'Punto de venta eliminado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error eliminar PV: ' . $e->getMessage());
            Response::servidor('No se pudo eliminar');
        }
        break;

    default:
        Response::error('Acción no reconocida', 404);
}