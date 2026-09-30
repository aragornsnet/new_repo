<?php
/**
 * IPV - API de Facturas (ventas al por mayor)
 *
 * Permisos:
 *   - Admin: escritura total
 *   - Supervisor: escritura total
 *   - Vendedor / Almacenero: solo lectura
 *
 * Flujo:
 *   1. Vendedor cobra en POS marcando "Requiere factura" + cliente.
 *   2. Supervisor/Admin emite la factura desde aquí.
 *   3. La factura queda 1:1 con la venta (inmutable).
 *   4. Se registran pagos (parciales o totales).
 *   5. Se puede anular (solo si no está pagada).
 *   6. Se puede descargar en PDF.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';
require_once __DIR__ . '/../core/Notificacion.php';
require_once __DIR__ . '/../core/Config.php';

ApiBootstrap::iniciar(['Administrador', 'Supervisor', 'Vendedor', 'Almacenero']);

$accion = $_GET['accion'] ?? 'listar';

function exigirFacturacion(): void {
    if (!Auth::esAdmin() && !Auth::esSupervisor()) {
        Response::prohibido('Solo supervisores y administradores pueden gestionar facturas');
    }
}

/**
 * Genera el siguiente folio secuencial F-YYYY-NNNN.
 */
function generarFolioFactura(): string
{
    $anio = date('Y');
    $prefijo = 'F-' . $anio . '-';

    $ultimo = Database::fetchValue("
        SELECT folio FROM facturas
        WHERE folio LIKE ?
        ORDER BY folio DESC
        LIMIT 1
    ", [$prefijo . '%']);

    if (!$ultimo) {
        return $prefijo . '0001';
    }

    // Extraer el número final
    $num = (int) substr($ultimo, strlen($prefijo));
    $num++;

    return $prefijo . str_pad($num, 4, '0', STR_PAD_LEFT);
}

switch ($accion) {

    // ============================================================
    // LISTAR
    // ============================================================
    case 'listar':
        $q          = trim($_GET['q'] ?? '');
        $estado     = trim($_GET['estado'] ?? '');
        $clienteId  = (int)($_GET['cliente_id'] ?? 0);
        $desde      = trim($_GET['desde'] ?? '');
        $hasta      = trim($_GET['hasta'] ?? '');
        $vencidas   = ($_GET['vencidas'] ?? '0') === '1';

        $condiciones = [];
        $params = [];

        if ($q !== '') {
            $condiciones[] = '(f.folio LIKE :q1 OR v.folio LIKE :q2 OR c.nombre LIKE :q3 OR c.nit LIKE :q4)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
            $params[':q3'] = "%$q%";
            $params[':q4'] = "%$q%";
        }
        if ($clienteId > 0) {
            $condiciones[] = 'f.cliente_id = :cliente_id';
            $params[':cliente_id'] = $clienteId;
        }
        if ($estado !== '') {
            $condiciones[] = 'f.estado = :estado';
            $params[':estado'] = $estado;
        }
        if ($desde !== '') {
            $condiciones[] = 'DATE(f.fecha_emision) >= :desde';
            $params[':desde'] = $desde;
        }
        if ($hasta !== '') {
            $condiciones[] = 'DATE(f.fecha_emision) <= :hasta';
            $params[':hasta'] = $hasta;
        }
        if ($vencidas) {
            $condiciones[] = "f.estado IN ('emitida', 'parcial') AND f.fecha_vencimiento < CURDATE()";
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $facturas = Database::fetchAll("
            SELECT
                f.id, f.folio, f.venta_id, f.cliente_id,
                f.fecha_emision, f.fecha_vencimiento,
                f.moneda, f.subtotal, f.descuento, f.impuesto, f.total,
                f.estado, f.observaciones,
                c.nombre AS cliente, c.nit AS cliente_nit,
                v.folio AS venta_folio,
                pv.nombre AS pv,
                u.nombre AS emitida_por_nombre,
                COALESCE((SELECT SUM(p.monto) FROM facturas_pagos p WHERE p.factura_id = f.id), 0) AS total_pagado
            FROM facturas f
            JOIN clientes c ON c.id = f.cliente_id
            JOIN ventas v ON v.id = f.venta_id
            JOIN puntos_venta pv ON pv.id = v.punto_venta_id
            LEFT JOIN usuarios u ON u.id = f.emitida_por
            $where
            ORDER BY f.fecha_emision DESC
            LIMIT 1000
        ", $params);

        // Calcular saldo pendiente + días vencidos
        foreach ($facturas as &$f) {
            $f['saldo_pendiente'] = (float)$f['total'] - (float)$f['total_pagado'];

            if (in_array($f['estado'], ['emitida', 'parcial'], true) && $f['fecha_vencimiento']) {
                $hoy = strtotime(date('Y-m-d'));
                $venc = strtotime($f['fecha_vencimiento']);
                $f['dias_vencidos'] = $venc < $hoy ? (int) floor(($hoy - $venc) / 86400) : null;
            } else {
                $f['dias_vencidos'] = null;
            }
        }
        unset($f);

        Response::ok($facturas);
        break;

    // ============================================================
    // OBTENER
    // ============================================================
    case 'obtener':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $f = Database::fetchOne("
            SELECT
                f.*,
                c.nombre AS cliente, c.nit AS cliente_nit,
                c.direccion AS cliente_direccion, c.telefono AS cliente_telefono,
                c.email AS cliente_email, c.tipo_persona AS cliente_tipo_persona,
                v.folio AS venta_folio, v.fecha AS venta_fecha,
                pv.nombre AS pv, pv.direccion AS pv_direccion,
                u.nombre AS emitida_por_nombre,
                ua.nombre AS anulada_por_nombre
            FROM facturas f
            JOIN clientes c ON c.id = f.cliente_id
            JOIN ventas v ON v.id = f.venta_id
            JOIN puntos_venta pv ON pv.id = v.punto_venta_id
            LEFT JOIN usuarios u ON u.id = f.emitida_por
            LEFT JOIN usuarios ua ON ua.id = f.anulada_por
            WHERE f.id = ?
        ", [$id]);

        if (!$f) Response::noEncontrado('Factura no encontrada');

        // Detalle de la venta asociada
        $f['detalle'] = Database::fetchAll("
            SELECT
                dv.id, dv.cantidad, dv.precio_unitario, dv.subtotal,
                p.nombre AS producto, p.codigo_barras, p.unidad_medida
            FROM detalle_ventas dv
            JOIN productos p ON p.id = dv.producto_id
            WHERE dv.venta_id = ?
            ORDER BY dv.id
        ", [(int)$f['venta_id']]);

        // Pagos
        $f['pagos'] = Database::fetchAll("
            SELECT
                fp.id, fp.fecha, fp.monto, fp.metodo, fp.referencia, fp.notas,
                u.nombre AS usuario
            FROM facturas_pagos fp
            LEFT JOIN usuarios u ON u.id = fp.usuario_id
            WHERE fp.factura_id = ?
            ORDER BY fp.fecha ASC
        ", [$id]);

        $f['total_pagado'] = 0;
        foreach ($f['pagos'] as $p) {
            $f['total_pagado'] += (float)$p['monto'];
        }
        $f['saldo_pendiente'] = (float)$f['total'] - $f['total_pagado'];

        Response::ok($f);
        break;

    // ============================================================
    // VENTAS PENDIENTES DE FACTURAR
    // ============================================================
    case 'pendientes_facturar':
        $q = trim($_GET['q'] ?? '');

        $condiciones = [
            'v.estado = \'completada\'',
            'v.requiere_factura = 1',
            'v.cliente_id IS NOT NULL',
            'NOT EXISTS (SELECT 1 FROM facturas f WHERE f.venta_id = v.id)',
        ];
        $params = [];

        if ($q !== '') {
            $condiciones[] = '(v.folio LIKE :q1 OR c.nombre LIKE :q2 OR c.nit LIKE :q3)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
            $params[':q3'] = "%$q%";
        }

        $where = 'WHERE ' . implode(' AND ', $condiciones);

        $ventas = Database::fetchAll("
            SELECT
                v.id, v.folio, v.fecha, v.total, v.moneda,
                v.cliente_id,
                c.nombre AS cliente, c.nit AS cliente_nit,
                pv.nombre AS pv,
                u.nombre AS vendedor
            FROM ventas v
            JOIN clientes c ON c.id = v.cliente_id
            JOIN puntos_venta pv ON pv.id = v.punto_venta_id
            JOIN usuarios u ON u.id = v.usuario_id
            $where
            ORDER BY v.fecha DESC
            LIMIT 500
        ", $params);

        Response::ok($ventas);
        break;

    // ============================================================
    // EMITIR FACTURA
    // ============================================================
    case 'emitir':
        exigirFacturacion();

        $body = jsonBody();

        $ventaId          = (int)($body['venta_id'] ?? 0);
        $fechaVencimiento = trim($body['fecha_vencimiento'] ?? '');
        $descuento        = (float)($body['descuento'] ?? 0);
        $impuesto         = (float)($body['impuesto'] ?? 0);
        $observaciones    = trim($body['observaciones'] ?? '');

        if ($ventaId <= 0) {
            Response::validacion(['venta_id' => 'Venta requerida']);
        }

        // Verificar venta
        $venta = Database::fetchOne("
            SELECT v.*, c.nombre AS cliente, c.nit AS cliente_nit
            FROM ventas v
            JOIN clientes c ON c.id = v.cliente_id
            WHERE v.id = ?
        ", [$ventaId]);

        if (!$venta) Response::noEncontrado('Venta no encontrada');
        if ($venta['estado'] !== 'completada') {
            Response::error('La venta no está completada');
        }
        if (!$venta['cliente_id']) {
            Response::error('La venta no tiene cliente asignado');
        }

        $yaFacturada = Database::fetchValue(
            "SELECT COUNT(*) FROM facturas WHERE venta_id = ?",
            [$ventaId]
        );
        if ($yaFacturada) {
            Response::error('Esta venta ya tiene una factura emitida');
        }

        // Validar fecha de vencimiento
        if ($fechaVencimiento !== '') {
            $dt = DateTime::createFromFormat('Y-m-d', $fechaVencimiento);
            if (!$dt || $dt->format('Y-m-d') !== $fechaVencimiento) {
                Response::validacion(['fecha_vencimiento' => 'Fecha inválida']);
            }
            if (strtotime($fechaVencimiento) < strtotime(date('Y-m-d'))) {
                Response::validacion(['fecha_vencimiento' => 'La fecha de vencimiento no puede ser anterior a hoy']);
            }
        }

        if ($descuento < 0) Response::validacion(['descuento' => 'El descuento no puede ser negativo']);
        if ($impuesto < 0) Response::validacion(['impuesto' => 'El impuesto no puede ser negativo']);

        try {
            Database::begin();

            $folio = generarFolioFactura();

            $subtotal = (float)$venta['subtotal'];
            $total = $subtotal - $descuento + $impuesto;

            if ($total < 0) {
                throw new Exception('El descuento no puede ser mayor al subtotal');
            }

            $facturaId = Database::insert('facturas', [
                'folio'             => $folio,
                'venta_id'          => $ventaId,
                'cliente_id'        => (int)$venta['cliente_id'],
                'fecha_emision'     => date('Y-m-d H:i:s'),
                'fecha_vencimiento' => $fechaVencimiento ?: null,
                'moneda'            => $venta['moneda'] ?: 'CUP',
                'subtotal'          => $subtotal,
                'descuento'         => $descuento,
                'impuesto'          => $impuesto,
                'total'             => $total,
                'estado'            => 'emitida',
                'observaciones'     => $observaciones ?: null,
                'emitida_por'       => Auth::id(),
            ]);

            Auditoria::registrar('factura_emitida', 'facturas', $facturaId, [
                'folio'    => $folio,
                'venta'    => $venta['folio'],
                'cliente'  => $venta['cliente'],
                'total'    => $total,
            ]);

            Notificacion::crearParaAdmins(
                'factura_emitida',
                'Factura emitida',
                $folio . ' · ' . $venta['cliente'] . ' · ' . number_format($total, 2),
                'views/admin/facturas.php',
                'receipt',
                'info'
            );

            Database::commit();

            Response::ok([
                'id'    => $facturaId,
                'folio' => $folio,
                'total' => $total,
            ], 'Factura emitida correctamente');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error emitir factura: ' . $e->getMessage());
            Response::error('No se pudo emitir la factura: ' . $e->getMessage());
        }
        break;

    // ============================================================
    // REGISTRAR PAGO
    // ============================================================
    case 'registrar_pago':
        exigirFacturacion();

        $body = jsonBody();

        $facturaId  = (int)($body['factura_id'] ?? 0);
        $monto      = (float)($body['monto'] ?? 0);
        $metodo     = trim($body['metodo'] ?? 'efectivo');
        $referencia = trim($body['referencia'] ?? '');
        $notas      = trim($body['notas'] ?? '');
        $fecha      = trim($body['fecha'] ?? date('Y-m-d H:i:s'));

        if ($facturaId <= 0) Response::validacion(['factura_id' => 'Factura requerida']);
        if ($monto <= 0) Response::validacion(['monto' => 'El monto debe ser mayor a 0']);
        if (!in_array($metodo, ['efectivo', 'transferencia', 'otro'], true)) {
            Response::validacion(['metodo' => 'Método no válido']);
        }
        if (mb_strlen($referencia) > 100) {
            Response::validacion(['referencia' => 'Máximo 100 caracteres']);
        }
        if (mb_strlen($notas) > 255) {
            Response::validacion(['notas' => 'Máximo 255 caracteres']);
        }

        $factura = Database::fetchOne("SELECT * FROM facturas WHERE id = ?", [$facturaId]);
        if (!$factura) Response::noEncontrado('Factura no encontrada');
        if ($factura['estado'] === 'anulada') {
            Response::error('No se puede pagar una factura anulada');
        }
        if ($factura['estado'] === 'pagada') {
            Response::error('La factura ya está pagada');
        }

        // Calcular saldo actual
        $totalPagado = (float) Database::fetchValue("
            SELECT COALESCE(SUM(monto), 0) FROM facturas_pagos WHERE factura_id = ?
        ", [$facturaId], 0);

        $saldo = (float)$factura['total'] - $totalPagado;

        if ($monto > $saldo) {
            Response::validacion([
                'monto' => 'El monto excede el saldo pendiente (' . number_format($saldo, 2) . ')'
            ]);
        }

        try {
            Database::begin();

            Database::insert('facturas_pagos', [
                'factura_id' => $facturaId,
                'fecha'      => $fecha,
                'monto'      => $monto,
                'metodo'     => $metodo,
                'referencia' => $referencia ?: null,
                'notas'      => $notas ?: null,
                'usuario_id' => Auth::id(),
            ]);

            // Actualizar estado
            $nuevoTotalPagado = $totalPagado + $monto;
            $nuevoEstado = $nuevoTotalPagado >= (float)$factura['total'] ? 'pagada' : 'parcial';

            Database::update('facturas',
                ['estado' => $nuevoEstado],
                'id = :id',
                [':id' => $facturaId]
            );

            Auditoria::registrar('factura_pago_registrado', 'facturas', $facturaId, [
                'folio'        => $factura['folio'],
                'monto'        => $monto,
                'metodo'       => $metodo,
                'estado_nuevo' => $nuevoEstado,
            ]);

            Database::commit();

            Response::ok([
                'factura_id'     => $facturaId,
                'nuevo_estado'   => $nuevoEstado,
                'total_pagado'   => $nuevoTotalPagado,
                'saldo_pendiente'=> (float)$factura['total'] - $nuevoTotalPagado,
            ], 'Pago registrado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error registrar pago: ' . $e->getMessage());
            Response::servidor('No se pudo registrar el pago');
        }
        break;

    // ============================================================
    // ANULAR FACTURA
    // ============================================================
    case 'anular':
        exigirFacturacion();

        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        $motivo = trim($body['motivo'] ?? '');

        if ($id <= 0) Response::error('ID inválido');
        if (mb_strlen($motivo) < 5) {
            Response::validacion(['motivo' => 'El motivo debe tener al menos 5 caracteres']);
        }

        $f = Database::fetchOne("SELECT * FROM facturas WHERE id = ?", [$id]);
        if (!$f) Response::noEncontrado('Factura no encontrada');

        if ($f['estado'] === 'anulada') {
            Response::error('La factura ya está anulada');
        }

        // No permitir anular si tiene pagos
        $pagos = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM facturas_pagos WHERE factura_id = ?",
            [$id]
        );
        if ($pagos > 0) {
            Response::error('No se puede anular una factura con pagos registrados');
        }

        try {
            Database::begin();

            Database::update('facturas', [
                'estado'           => 'anulada',
                'anulada_por'      => Auth::id(),
                'fecha_anulacion'  => date('Y-m-d H:i:s'),
                'motivo_anulacion' => $motivo,
            ], 'id = :id', [':id' => $id]);

            Auditoria::registrar('factura_anulada', 'facturas', $id, [
                'folio'  => $f['folio'],
                'motivo' => $motivo,
            ]);

            Database::commit();

            Response::ok(null, 'Factura anulada');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error anular factura: ' . $e->getMessage());
            Response::servidor('No se pudo anular');
        }
        break;

    // ============================================================
    // CATÁLOGOS
    // ============================================================
    case 'catalogos':
        Response::ok([
            'estados' => [
                ['valor' => 'emitida', 'nombre' => 'Emitida'],
                ['valor' => 'parcial', 'nombre' => 'Parcial'],
                ['valor' => 'pagada',  'nombre' => 'Pagada'],
                ['valor' => 'anulada', 'nombre' => 'Anulada'],
            ],
            'metodos_pago' => [
                ['valor' => 'efectivo',      'nombre' => 'Efectivo'],
                ['valor' => 'transferencia', 'nombre' => 'Transferencia'],
                ['valor' => 'otro',          'nombre' => 'Otro'],
            ],
            'plazo_dias_default' => Config::int('factura_plazo_dias_default', 30),
        ]);
        break;

    // ============================================================
    // DESCARGAR PDF
    // ============================================================
    case 'pdf':

        error_log('API_FACTURAS_PDF: entrando en case pdf, id=' . ($_GET['id'] ?? 'null') . ' modo=' . ($_GET['modo'] ?? 'null'));

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        // Modo: 'inline' (previsualización) o 'download' (descarga)
        $modo = $_GET['modo'] ?? 'download';
        if (!in_array($modo, ['inline', 'download'], true)) {
            $modo = 'download';
        }

        $existe = Database::fetchValue(
            "SELECT COUNT(*) FROM facturas WHERE id = ?",
            [$id]
        );
        if (!$existe) Response::noEncontrado('Factura no encontrada');

        require_once __DIR__ . '/../core/reportes/factura.php';
        generar_factura_pdf($id, $modo);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}