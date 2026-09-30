<?php
/**
 * IPV - API de Solicitudes de Traslado (Almacén → PV)
 *
 * Roles:
 *   - Supervisor/Administrador: crear, listar, cancelar (solicitado), recibir
 *   - Almacenero/Administrador:  listar todas, aprobar, rechazar, despachar
 *
 * Nota: los supervisores son GLOBALES (no tienen PV asignado).
 * Pueden ver, crear y gestionar solicitudes de CUALQUIER PV.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';
require_once __DIR__ . '/../core/Notificacion.php';

ApiBootstrap::iniciar(['Administrador', 'Supervisor', 'Almacenero']);

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

    if ($id <= 0) {
        Response::error('No hay un almacén configurado. Contacta al administrador.');
    }

    return $id;
}

function generarFolioSolicitud(): string
{
    $fecha = date('Ymd');
    $aleatorio = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    return "S-{$fecha}-{$aleatorio}";
}

function esAlmaceneroOAdmin(): bool
{
    return Auth::esAlmacenero() || Auth::esAdmin();
}

function esSupervisorOAdmin(): bool
{
    return Auth::esSupervisor() || Auth::esAdmin();
}

switch ($accion) {

    case 'listar':
        $estado    = trim($_GET['estado'] ?? '');
        $pvId      = (int)($_GET['pv_id'] ?? 0);
        $desde     = trim($_GET['desde'] ?? '');
        $hasta     = trim($_GET['hasta'] ?? '');
        $q         = trim($_GET['q'] ?? '');

        $condiciones = [];
        $params = [];

        if ($pvId > 0) {
            $condiciones[] = 's.punto_venta_id = :pv_id';
            $params[':pv_id'] = $pvId;
        }
        if ($estado !== '') {
            $condiciones[] = 's.estado = :estado';
            $params[':estado'] = $estado;
        }
        if ($desde !== '') {
            $condiciones[] = 'DATE(s.fecha_solicitud) >= :desde';
            $params[':desde'] = $desde;
        }
        if ($hasta !== '') {
            $condiciones[] = 'DATE(s.fecha_solicitud) <= :hasta';
            $params[':hasta'] = $hasta;
        }
        if ($q !== '') {
            $condiciones[] = '(s.folio LIKE :q1 OR s.motivo LIKE :q2 OR pv.nombre LIKE :q3)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
            $params[':q3'] = "%$q%";
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $solicitudes = Database::fetchAll("
            SELECT 
                s.id, s.folio, s.estado, s.motivo, s.descripcion,
                s.total_items, s.total_unidades_solicitadas,
                s.total_unidades_despachadas, s.total_unidades_recibidas,
                s.fecha_solicitud, s.fecha_aprobacion, s.fecha_despacho,
                s.fecha_recepcion, s.fecha_rechazo, s.fecha_cancelacion,
                s.motivo_rechazo, s.motivo_cancelacion,
                pv.id AS pv_id, pv.nombre AS pv,
                sol.nombre AS solicitado_por_nombre,
                alm.nombre AS aprobado_por_nombre,
                des.nombre AS despachado_por_nombre,
                rec.nombre AS recibido_por_nombre,
                rej.nombre AS rechazado_por_nombre,
                can.nombre AS cancelado_por_nombre
            FROM solicitudes_traslado s
            JOIN puntos_venta pv ON pv.id = s.punto_venta_id
            JOIN usuarios sol ON sol.id = s.solicitado_por
            LEFT JOIN usuarios alm ON alm.id = s.aprobado_por
            LEFT JOIN usuarios des ON des.id = s.despachado_por
            LEFT JOIN usuarios rec ON rec.id = s.recibido_por
            LEFT JOIN usuarios rej ON rej.id = s.rechazado_por
            LEFT JOIN usuarios can ON can.id = s.cancelado_por
            $where
            ORDER BY 
                CASE s.estado
                    WHEN 'solicitado' THEN 1
                    WHEN 'aprobado' THEN 2
                    WHEN 'despachado' THEN 3
                    WHEN 'despachado_parcial' THEN 4
                    ELSE 5
                END,
                s.fecha_solicitud DESC
            LIMIT 500
        ", $params);

        $contadores = Database::fetchAll("
            SELECT estado, COUNT(*) AS total 
            FROM solicitudes_traslado
            GROUP BY estado
        ");

        Response::ok([
            'datos'       => $solicitudes,
            'contadores'  => $contadores,
        ]);
        break;

    case 'obtener':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $solicitud = Database::fetchOne("
            SELECT 
                s.*,
                pv.nombre AS pv, pv.direccion AS pv_direccion,
                a.nombre AS almacen,
                sol.nombre AS solicitado_por_nombre,
                alm.nombre AS aprobado_por_nombre,
                des.nombre AS despachado_por_nombre,
                rec.nombre AS recibido_por_nombre,
                rej.nombre AS rechazado_por_nombre,
                can.nombre AS cancelado_por_nombre
            FROM solicitudes_traslado s
            JOIN puntos_venta pv ON pv.id = s.punto_venta_id
            JOIN puntos_venta a ON a.id = s.almacen_id
            JOIN usuarios sol ON sol.id = s.solicitado_por
            LEFT JOIN usuarios alm ON alm.id = s.aprobado_por
            LEFT JOIN usuarios des ON des.id = s.despachado_por
            LEFT JOIN usuarios rec ON rec.id = s.recibido_por
            LEFT JOIN usuarios rej ON rej.id = s.rechazado_por
            LEFT JOIN usuarios can ON can.id = s.cancelado_por
            WHERE s.id = ?
        ", [$id]);

        if (!$solicitud) Response::noEncontrado('Solicitud no encontrada');

        $solicitud['detalle'] = Database::fetchAll("
            SELECT 
                d.id, d.cantidad_solicitada, d.cantidad_aprobada,
                d.cantidad_despachada, d.cantidad_recibida,
                p.id AS producto_id, p.nombre AS producto, p.codigo_barras,
                p.unidad_medida,
                COALESCE(spv.stock, 0) AS stock_almacen_actual
            FROM solicitudes_detalle d
            JOIN productos p ON p.id = d.producto_id
            LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id 
                AND spv.punto_venta_id = ?
            WHERE d.solicitud_id = ?
            ORDER BY p.nombre
        ", [(int)$solicitud['almacen_id'], $id]);

        Response::ok($solicitud);
        break;

    case 'crear':
        if (!esSupervisorOAdmin()) {
            Response::prohibido('Solo supervisores y administradores pueden crear solicitudes');
        }

        $body = jsonBody();

        $pvDestino = (int)($body['punto_venta_id'] ?? 0);
        $motivo    = trim($body['motivo'] ?? '');
        $descripcion = trim($body['descripcion'] ?? '');
        $items     = $body['items'] ?? [];

        if ($pvDestino <= 0) Response::validacion(['punto_venta_id' => 'Debes elegir un punto de venta']);

        $pv = Database::fetchOne("
            SELECT id, nombre FROM puntos_venta 
            WHERE id = ? AND activo = 1 AND es_almacen = 0
        ", [$pvDestino]);

        if (!$pv) Response::noEncontrado('Punto de venta no encontrado o inválido');

        if ($motivo === '') Response::validacion(['motivo' => 'El motivo es obligatorio']);
        if (mb_strlen($motivo) < 3) Response::validacion(['motivo' => 'El motivo es muy corto']);
        if (!is_array($items) || empty($items)) {
            Response::validacion(['items' => 'Debes agregar al menos un producto']);
        }

        $almacenId = obtenerAlmacenId();

        $itemsLimpios = [];
        $vistos = [];
        foreach ($items as $item) {
            $pid = (int)($item['producto_id'] ?? 0);
            $cant = (int)($item['cantidad'] ?? 0);
            if ($pid <= 0 || $cant <= 0) continue;
            if (isset($vistos[$pid])) {
                Response::validacion(['items' => "Producto #$pid duplicado en la solicitud"]);
            }
            $vistos[$pid] = true;
            $itemsLimpios[] = ['producto_id' => $pid, 'cantidad' => $cant];
        }

        if (empty($itemsLimpios)) {
            Response::validacion(['items' => 'Los productos de la solicitud no son válidos']);
        }

        try {
            Database::begin();

            $folio = generarFolioSolicitud();

            $solicitudId = Database::insert('solicitudes_traslado', [
                'folio'                     => $folio,
                'almacen_id'                => $almacenId,
                'punto_venta_id'            => $pvDestino,
                'solicitado_por'            => Auth::id(),
                'estado'                    => 'solicitado',
                'motivo'                    => $motivo,
                'descripcion'               => $descripcion ?: null,
                'total_items'               => count($itemsLimpios),
                'total_unidades_solicitadas'=> array_sum(array_column($itemsLimpios, 'cantidad')),
            ]);

            $errores = [];
            foreach ($itemsLimpios as $item) {
                $prod = Database::fetchOne("
                    SELECT id, nombre FROM productos WHERE id = ? AND activo = 1
                ", [$item['producto_id']]);

                if (!$prod) {
                    $errores[] = "Producto #{$item['producto_id']} no encontrado";
                    continue;
                }

                Database::insert('solicitudes_detalle', [
                    'solicitud_id'        => $solicitudId,
                    'producto_id'         => $item['producto_id'],
                    'cantidad_solicitada' => $item['cantidad'],
                ]);
            }

            if (!empty($errores)) {
                throw new Exception(implode(' | ', $errores));
            }

            Auditoria::registrar('solicitud_creada', 'solicitudes_traslado', $solicitudId, [
                'folio' => $folio,
                'pv'    => $pv['nombre'],
                'items' => count($itemsLimpios),
            ]);

            Notificacion::crearParaRol(
                4,
                'solicitud_nueva',
                'Nueva solicitud de traslado',
                "$folio · {$pv['nombre']} · " . count($itemsLimpios) . ' producto(s)',
                'views/almacen/solicitudes.php',
                'arrow-left-right',
                'info'
            );

            Database::commit();

            Response::ok([
                'id'    => $solicitudId,
                'folio' => $folio,
            ], 'Solicitud creada correctamente');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error crear solicitud: ' . $e->getMessage());
            Response::servidor('No se pudo crear la solicitud: ' . $e->getMessage());
        }
        break;

    case 'aprobar':
        if (!esAlmaceneroOAdmin()) {
            Response::prohibido('Solo el almacenero puede aprobar solicitudes');
        }

        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        $items = $body['items'] ?? [];
        $observaciones = trim($body['observaciones'] ?? '');

        if ($id <= 0) Response::error('ID inválido');

        $solicitud = Database::fetchOne("SELECT * FROM solicitudes_traslado WHERE id = ?", [$id]);
        if (!$solicitud) Response::noEncontrado('Solicitud no encontrada');
        if ($solicitud['estado'] !== 'solicitado') {
            Response::error('Solo se pueden aprobar solicitudes en estado "solicitado"');
        }

        if (!is_array($items) || empty($items)) {
            Response::validacion(['items' => 'Debes indicar las cantidades aprobadas']);
        }

        try {
            Database::begin();

            $totalAprobado = 0;
            $itemsAprobados = 0;

            foreach ($items as $item) {
                $detalleId = (int)($item['id'] ?? 0);
                $cantAprobada = (int)($item['cantidad_aprobada'] ?? 0);

                if ($detalleId <= 0) continue;
                if ($cantAprobada < 0) $cantAprobada = 0;

                $det = Database::fetchOne("
                    SELECT id, producto_id, cantidad_solicitada 
                    FROM solicitudes_detalle 
                    WHERE id = ? AND solicitud_id = ?
                ", [$detalleId, $id]);

                if (!$det) continue;

                if ($cantAprobada > (int)$det['cantidad_solicitada']) {
                    $cantAprobada = (int)$det['cantidad_solicitada'];
                }

                Database::update('solicitudes_detalle',
                    ['cantidad_aprobada' => $cantAprobada],
                    'id = :id',
                    [':id' => $detalleId]
                );

                $totalAprobado += $cantAprobada;
                if ($cantAprobada > 0) $itemsAprobados++;
            }

            if ($totalAprobado === 0) {
                throw new Exception('Debes aprobar al menos 1 unidad');
            }

            Database::update('solicitudes_traslado', [
                'estado'             => 'aprobado',
                'aprobado_por'       => Auth::id(),
                'fecha_aprobacion'   => date('Y-m-d H:i:s'),
                'observaciones_almacen' => $observaciones ?: null,
            ], 'id = :id', [':id' => $id]);

            Auditoria::registrar('solicitud_aprobada', 'solicitudes_traslado', $id, [
                'folio'          => $solicitud['folio'],
                'total_aprobado' => $totalAprobado,
                'items'          => $itemsAprobados,
            ]);

            Notificacion::crear(
                (int)$solicitud['solicitado_por'],
                'solicitud_aprobada',
                'Solicitud aprobada',
                "{$solicitud['folio']} · $totalAprobado unidades aprobadas",
                'views/supervisor/solicitudes.php',
                'check-circle-fill',
                'success'
            );

            Database::commit();

            Response::ok([
                'id'             => $id,
                'total_aprobado' => $totalAprobado,
            ], 'Solicitud aprobada');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error aprobar solicitud: ' . $e->getMessage());
            Response::error($e->getMessage());
        }
        break;

    case 'rechazar':
        if (!esAlmaceneroOAdmin()) {
            Response::prohibido('Solo el almacenero puede rechazar solicitudes');
        }

        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        $motivo = trim($body['motivo'] ?? '');

        if ($id <= 0) Response::error('ID inválido');
        if ($motivo === '') Response::validacion(['motivo' => 'El motivo es obligatorio']);
        if (mb_strlen($motivo) < 5) Response::validacion(['motivo' => 'El motivo debe tener al menos 5 caracteres']);

        $solicitud = Database::fetchOne("SELECT * FROM solicitudes_traslado WHERE id = ?", [$id]);
        if (!$solicitud) Response::noEncontrado('Solicitud no encontrada');

        if (!in_array($solicitud['estado'], ['solicitado', 'aprobado'], true)) {
            Response::error('Solo se pueden rechazar solicitudes en estado "solicitado" o "aprobado"');
        }

        try {
            Database::begin();

            Database::update('solicitudes_traslado', [
                'estado'          => 'rechazado',
                'rechazado_por'   => Auth::id(),
                'fecha_rechazo'   => date('Y-m-d H:i:s'),
                'motivo_rechazo'  => $motivo,
            ], 'id = :id', [':id' => $id]);

            Auditoria::registrar('solicitud_rechazada', 'solicitudes_traslado', $id, [
                'folio'  => $solicitud['folio'],
                'motivo' => $motivo,
            ]);

            Notificacion::crear(
                (int)$solicitud['solicitado_por'],
                'solicitud_rechazada',
                'Solicitud rechazada',
                "{$solicitud['folio']} · Motivo: $motivo",
                'views/supervisor/solicitudes.php',
                'x-circle-fill',
                'danger'
            );

            Database::commit();

            Response::ok(null, 'Solicitud rechazada');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error rechazar solicitud: ' . $e->getMessage());
            Response::servidor('No se pudo rechazar la solicitud');
        }
        break;

    case 'despachar':
        if (!esAlmaceneroOAdmin()) {
            Response::prohibido('Solo el almacenero puede despachar solicitudes');
        }

        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        $observaciones = trim($body['observaciones'] ?? '');

        if ($id <= 0) Response::error('ID inválido');

        $solicitud = Database::fetchOne("SELECT * FROM solicitudes_traslado WHERE id = ?", [$id]);
        if (!$solicitud) Response::noEncontrado('Solicitud no encontrada');

        if ($solicitud['estado'] !== 'aprobado') {
            Response::error('Solo se pueden despachar solicitudes aprobadas');
        }

        $almacenId = (int)$solicitud['almacen_id'];

        $detalle = Database::fetchAll("
            SELECT d.id, d.producto_id, d.cantidad_solicitada, d.cantidad_aprobada,
                   p.nombre AS producto,
                   COALESCE(spv.stock, 0) AS stock_almacen
            FROM solicitudes_detalle d
            JOIN productos p ON p.id = d.producto_id
            LEFT JOIN stock_punto_venta spv ON spv.producto_id = d.producto_id 
                AND spv.punto_venta_id = ?
            WHERE d.solicitud_id = ? AND d.cantidad_aprobada > 0
        ", [$almacenId, $id]);

        if (empty($detalle)) {
            Response::error('No hay items aprobados para despachar');
        }

        $sinStock = [];
        foreach ($detalle as $d) {
            if ((int)$d['stock_almacen'] < (int)$d['cantidad_aprobada']) {
                $sinStock[] = "{$d['producto']} (disponible: {$d['stock_almacen']}, aprobado: {$d['cantidad_aprobada']})";
            }
        }
        if (!empty($sinStock)) {
            Response::error('Stock insuficiente en el almacén: ' . implode(' | ', $sinStock));
        }

        try {
            Database::begin();

            $totalDespachado = 0;
            $itemsDespachados = 0;
            $todoCompleto = true;

            foreach ($detalle as $d) {
                $cantDespachar = (int)$d['cantidad_aprobada'];
                $cantSolicitada = (int)$d['cantidad_solicitada'];

                if ($cantDespachar < $cantSolicitada) {
                    $todoCompleto = false;
                }

                $stockAnterior = (int)$d['stock_almacen'];
                $stockNuevo = $stockAnterior - $cantDespachar;

                Database::update('stock_punto_venta',
                    ['stock' => $stockNuevo],
                    'producto_id = :pid AND punto_venta_id = :pvid',
                    [':pid' => $d['producto_id'], ':pvid' => $almacenId]
                );

                Database::insert('movimientos', [
                    'producto_id'    => $d['producto_id'],
                    'punto_venta_id' => $almacenId,
                    'turno_id'       => null,
                    'tipo'           => 'transferencia',
                    'cantidad'       => $cantDespachar,
                    'motivo'         => 'Despacho a ' . $solicitud['folio'],
                    'descripcion'    => 'Salida del almacén hacia PV #' . $solicitud['punto_venta_id'],
                    'valor_anterior' => $stockAnterior,
                    'valor_nuevo'    => $stockNuevo,
                    'usuario_id'     => Auth::id(),
                ]);

                Database::update('solicitudes_detalle',
                    ['cantidad_despachada' => $cantDespachar],
                    'id = :id',
                    [':id' => $d['id']]
                );

                $totalDespachado += $cantDespachar;
                $itemsDespachados++;
            }

            $nuevoEstado = $todoCompleto ? 'despachado' : 'despachado_parcial';

            Database::update('solicitudes_traslado', [
                'estado'                       => $nuevoEstado,
                'despachado_por'               => Auth::id(),
                'fecha_despacho'               => date('Y-m-d H:i:s'),
                'total_unidades_despachadas'   => $totalDespachado,
                'observaciones_almacen'        => $observaciones ?: $solicitud['observaciones_almacen'],
            ], 'id = :id', [':id' => $id]);

            Auditoria::registrar('solicitud_despachada', 'solicitudes_traslado', $id, [
                'folio'            => $solicitud['folio'],
                'total_despachado' => $totalDespachado,
                'estado'           => $nuevoEstado,
            ]);

            Notificacion::crear(
                (int)$solicitud['solicitado_por'],
                'solicitud_despachada',
                'Solicitud despachada',
                "{$solicitud['folio']} · $totalDespachado unidades en camino",
                'views/supervisor/solicitudes.php',
                'truck',
                'info'
            );

            Database::commit();

            Response::ok([
                'id'               => $id,
                'total_despachado' => $totalDespachado,
                'estado'           => $nuevoEstado,
            ], 'Solicitud despachada');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error despachar solicitud: ' . $e->getMessage());
            Response::servidor('No se pudo despachar: ' . $e->getMessage());
        }
        break;

    case 'recibir':
        if (!esSupervisorOAdmin()) {
            Response::prohibido('Solo supervisores y administradores pueden confirmar recepción');
        }

        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        $observaciones = trim($body['observaciones'] ?? '');
        $itemsRecibidos = $body['items'] ?? [];

        if ($id <= 0) Response::error('ID inválido');

        $solicitud = Database::fetchOne("SELECT * FROM solicitudes_traslado WHERE id = ?", [$id]);
        if (!$solicitud) Response::noEncontrado('Solicitud no encontrada');

        if (!in_array($solicitud['estado'], ['despachado', 'despachado_parcial'], true)) {
            Response::error('Solo se pueden recibir solicitudes despachadas');
        }

        if (!is_array($itemsRecibidos) || empty($itemsRecibidos)) {
            Response::validacion(['items' => 'Debes indicar las cantidades recibidas']);
        }

        $pvId = (int)$solicitud['punto_venta_id'];

        try {
            Database::begin();

            $totalRecibido = 0;

            foreach ($itemsRecibidos as $item) {
                $detalleId = (int)($item['id'] ?? 0);
                $cantRecibida = (int)($item['cantidad_recibida'] ?? 0);

                if ($detalleId <= 0) continue;
                if ($cantRecibida < 0) $cantRecibida = 0;

                $det = Database::fetchOne("
                    SELECT id, producto_id, cantidad_despachada 
                    FROM solicitudes_detalle 
                    WHERE id = ? AND solicitud_id = ?
                ", [$detalleId, $id]);

                if (!$det) continue;

                if ($cantRecibida > (int)$det['cantidad_despachada']) {
                    $cantRecibida = (int)$det['cantidad_despachada'];
                }

                if ($cantRecibida === 0) {
                    Database::update('solicitudes_detalle',
                        ['cantidad_recibida' => 0],
                        'id = :id',
                        [':id' => $detalleId]
                    );
                    continue;
                }

                $stockActual = Database::fetchOne("
                    SELECT stock FROM stock_punto_venta
                    WHERE producto_id = ? AND punto_venta_id = ?
                ", [$det['producto_id'], $pvId]);

                if ($stockActual) {
                    $stockAnterior = (int)$stockActual['stock'];
                    Database::update('stock_punto_venta',
                        ['stock' => $stockAnterior + $cantRecibida],
                        'producto_id = :pid AND punto_venta_id = :pvid',
                        [':pid' => $det['producto_id'], ':pvid' => $pvId]
                    );
                } else {
                    $stockAnterior = 0;
                    Database::insert('stock_punto_venta', [
                        'producto_id'    => $det['producto_id'],
                        'punto_venta_id' => $pvId,
                        'stock'          => $cantRecibida,
                    ]);
                }

                Database::insert('movimientos', [
                    'producto_id'    => $det['producto_id'],
                    'punto_venta_id' => $pvId,
                    'turno_id'       => null,
                    'tipo'           => 'entrada',
                    'cantidad'       => $cantRecibida,
                    'motivo'         => 'Recepción de ' . $solicitud['folio'],
                    'descripcion'    => 'Entrada por traslado desde almacén',
                    'valor_anterior' => $stockAnterior,
                    'valor_nuevo'    => $stockAnterior + $cantRecibida,
                    'usuario_id'     => Auth::id(),
                ]);

                Database::update('solicitudes_detalle',
                    ['cantidad_recibida' => $cantRecibida],
                    'id = :id',
                    [':id' => $detalleId]
                );

                $totalRecibido += $cantRecibida;
            }

            Database::update('solicitudes_traslado', [
                'estado'                    => 'recibido',
                'recibido_por'              => Auth::id(),
                'fecha_recepcion'           => date('Y-m-d H:i:s'),
                'total_unidades_recibidas'  => $totalRecibido,
                'observaciones_recepcion'   => $observaciones ?: null,
            ], 'id = :id', [':id' => $id]);

            Auditoria::registrar('solicitud_recibida', 'solicitudes_traslado', $id, [
                'folio'          => $solicitud['folio'],
                'total_recibido' => $totalRecibido,
            ]);

            Notificacion::crearParaRol(
                4,
                'solicitud_recibida',
                'Solicitud recibida por PV',
                "{$solicitud['folio']} · $totalRecibido unidades confirmadas",
                'views/almacen/solicitudes.php',
                'check-circle-fill',
                'success'
            );

            Database::commit();

            Response::ok([
                'id'             => $id,
                'total_recibido' => $totalRecibido,
            ], 'Recepción confirmada');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error recibir solicitud: ' . $e->getMessage());
            Response::servidor('No se pudo confirmar la recepción: ' . $e->getMessage());
        }
        break;

    case 'cancelar':
        if (!esSupervisorOAdmin()) {
            Response::prohibido('No tienes permiso para cancelar solicitudes');
        }

        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        $motivo = trim($body['motivo'] ?? '');

        if ($id <= 0) Response::error('ID inválido');
        if ($motivo === '') Response::validacion(['motivo' => 'El motivo es obligatorio']);
        if (mb_strlen($motivo) < 5) Response::validacion(['motivo' => 'El motivo debe tener al menos 5 caracteres']);

        $solicitud = Database::fetchOne("SELECT * FROM solicitudes_traslado WHERE id = ?", [$id]);
        if (!$solicitud) Response::noEncontrado('Solicitud no encontrada');

        if ($solicitud['estado'] !== 'solicitado') {
            Response::error('Solo se pueden cancelar solicitudes en estado "solicitado"');
        }

        if (Auth::esSupervisor() && (int)$solicitud['solicitado_por'] !== Auth::id()) {
            Response::prohibido('Solo puedes cancelar tus propias solicitudes');
        }

        try {
            Database::begin();

            Database::update('solicitudes_traslado', [
                'estado'             => 'cancelado',
                'cancelado_por'      => Auth::id(),
                'fecha_cancelacion'  => date('Y-m-d H:i:s'),
                'motivo_cancelacion' => $motivo,
            ], 'id = :id', [':id' => $id]);

            Auditoria::registrar('solicitud_cancelada', 'solicitudes_traslado', $id, [
                'folio'  => $solicitud['folio'],
                'motivo' => $motivo,
            ]);

            Notificacion::crearParaRol(
                4,
                'solicitud_cancelada',
                'Solicitud cancelada',
                "{$solicitud['folio']} · Motivo: $motivo",
                'views/almacen/solicitudes.php',
                'x-circle-fill',
                'warning'
            );

            Database::commit();

            Response::ok(null, 'Solicitud cancelada');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error cancelar solicitud: ' . $e->getMessage());
            Response::servidor('No se pudo cancelar la solicitud');
        }
        break;

    case 'productos_almacen':
        if (!esSupervisorOAdmin()) {
            Response::prohibido('No tienes permiso');
        }

        $almacenId = obtenerAlmacenId();

        $q = trim($_GET['q'] ?? '');
        $catId = (int)($_GET['categoria_id'] ?? 0);
        $soloConStock = ($_GET['solo_con_stock'] ?? '1') === '1';

        $condiciones = ['p.activo = 1'];
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
        if ($soloConStock) {
            $condiciones[] = 'spv.stock > 0';
        }

        $where = 'WHERE ' . implode(' AND ', $condiciones);

        $productos = Database::fetchAll("
            SELECT 
                p.id, p.codigo_barras, p.nombre, p.precio, p.stock_minimo,
                p.unidad_medida,
                c.nombre AS categoria,
                c.id AS categoria_id,
                COALESCE(spv.stock, 0) AS stock_almacen
            FROM productos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = :almacen_id
            $where
            ORDER BY p.nombre
            LIMIT 500
        ", $params);

        Response::ok($productos);
        break;

    case 'catalogos':
        $pvs = Database::fetchAll("
            SELECT id, nombre FROM puntos_venta 
            WHERE activo = 1 AND es_almacen = 0 
            ORDER BY nombre
        ");

        $estados = [
            ['valor' => 'solicitado',        'nombre' => 'Solicitado'],
            ['valor' => 'aprobado',          'nombre' => 'Aprobado'],
            ['valor' => 'despachado',        'nombre' => 'Despachado'],
            ['valor' => 'despachado_parcial','nombre' => 'Despachado parcial'],
            ['valor' => 'recibido',          'nombre' => 'Recibido'],
            ['valor' => 'rechazado',         'nombre' => 'Rechazado'],
            ['valor' => 'cancelado',         'nombre' => 'Cancelado'],
        ];

        $motivos = [
            'Reposición de stock',
            'Productos agotados',
            'Evento especial',
            'Temporada alta',
            'Otro (especificar)',
        ];

        Response::ok([
            'puntos_venta' => $pvs,
            'estados'      => $estados,
            'motivos'      => $motivos,
        ]);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}