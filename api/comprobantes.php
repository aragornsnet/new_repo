<?php
/**
 * IPV - API de Comprobantes de Venta (minorista)
 *
 * Permisos:
 *   - Admin: escritura total
 *   - Supervisor: escritura total
 *   - Vendedor: puede emitir y anular sus propios comprobantes
 *   - Almacenero: solo lectura
 *
 * Flujo:
 *   1. Vendedor cobra en POS marcando "Emitir comprobante" + datos opcionales del comprador.
 *   2. El comprobante se emite automáticamente al confirmar el cobro.
 *   3. También se puede emitir después desde "Mis ventas" o "Ventas del día".
 *   4. Se puede descargar/imprimir en PDF.
 *   5. Se puede anular (solo si la venta sigue completada).
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';
require_once __DIR__ . '/../core/Notificacion.php';

ApiBootstrap::iniciar(['Administrador', 'Supervisor', 'Vendedor', 'Almacenero']);

$accion = $_GET['accion'] ?? 'listar';

function exigirEscritura(): void
{
    if (!Auth::esAdmin() && !Auth::esSupervisor() && !Auth::esVendedor()) {
        Response::prohibido('No tienes permiso para gestionar comprobantes');
    }
}

/**
 * Genera el siguiente folio secuencial C-YYYY-NNNNN.
 */
function generarFolioComprobante(): string
{
    $anio = date('Y');
    $prefijo = 'C-' . $anio . '-';

    $ultimo = Database::fetchValue("
        SELECT folio FROM comprobantes_venta
        WHERE folio LIKE ?
        ORDER BY folio DESC
        LIMIT 1
    ", [$prefijo . '%']);

    if (!$ultimo) {
        return $prefijo . '00001';
    }

    $num = (int) substr($ultimo, strlen($prefijo));
    $num++;

    return $prefijo . str_pad($num, 5, '0', STR_PAD_LEFT);
}

switch ($accion) {

    // ============================================================
    // LISTAR
    // ============================================================
    case 'listar':
        $q          = trim($_GET['q'] ?? '');
        $estado     = trim($_GET['estado'] ?? '');
        $ventaId    = (int)($_GET['venta_id'] ?? 0);
        $desde      = trim($_GET['desde'] ?? '');
        $hasta      = trim($_GET['hasta'] ?? '');

        $condiciones = [];
        $params = [];

        if ($q !== '') {
            $condiciones[] = '(cv.folio LIKE :q1 OR v.folio LIKE :q2 OR cv.nombre_comprador LIKE :q3 OR cv.telefono_comprador LIKE :q4)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
            $params[':q3'] = "%$q%";
            $params[':q4'] = "%$q%";
        }
        if ($estado !== '') {
            $condiciones[] = 'cv.estado = :estado';
            $params[':estado'] = $estado;
        }
        if ($ventaId > 0) {
            $condiciones[] = 'cv.venta_id = :venta_id';
            $params[':venta_id'] = $ventaId;
        }
        if ($desde !== '') {
            $condiciones[] = 'DATE(cv.created_at) >= :desde';
            $params[':desde'] = $desde;
        }
        if ($hasta !== '') {
            $condiciones[] = 'DATE(cv.created_at) <= :hasta';
            $params[':hasta'] = $hasta;
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $comprobantes = Database::fetchAll("
            SELECT
                cv.id, cv.folio, cv.venta_id, cv.nombre_comprador,
                cv.documento_comprador, cv.telefono_comprador,
                cv.direccion_comprador, cv.observaciones,
                cv.total, cv.moneda, cv.estado,
                cv.created_at, cv.fecha_anulacion, cv.motivo_anulacion,
                v.folio AS venta_folio, v.fecha AS venta_fecha,
                pv.nombre AS pv,
                u.nombre AS emitido_por_nombre,
                ua.nombre AS anulado_por_nombre
            FROM comprobantes_venta cv
            JOIN ventas v ON v.id = cv.venta_id
            JOIN puntos_venta pv ON pv.id = v.punto_venta_id
            LEFT JOIN usuarios u ON u.id = cv.emitido_por
            LEFT JOIN usuarios ua ON ua.id = cv.anulado_por
            $where
            ORDER BY cv.created_at DESC
            LIMIT 1000
        ", $params);

        Response::ok($comprobantes);
        break;

    // ============================================================
    // OBTENER
    // ============================================================
    case 'obtener':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $c = Database::fetchOne("
            SELECT
                cv.*,
                v.folio AS venta_folio, v.fecha AS venta_fecha,
                v.moneda AS venta_moneda,
                pv.nombre AS pv, pv.direccion AS pv_direccion, pv.telefono AS pv_telefono,
                u.nombre AS emitido_por_nombre,
                ua.nombre AS anulado_por_nombre
            FROM comprobantes_venta cv
            JOIN ventas v ON v.id = cv.venta_id
            JOIN puntos_venta pv ON pv.id = v.punto_venta_id
            LEFT JOIN usuarios u ON u.id = cv.emitido_por
            LEFT JOIN usuarios ua ON ua.id = cv.anulado_por
            WHERE cv.id = ?
        ", [$id]);

        if (!$c) Response::noEncontrado('Comprobante no encontrado');

        // Detalle de la venta
        $c['detalle'] = Database::fetchAll("
            SELECT
                dv.id, dv.cantidad, dv.precio_unitario, dv.subtotal,
                p.nombre AS producto, p.codigo_barras, p.unidad_medida
            FROM detalle_ventas dv
            JOIN productos p ON p.id = dv.producto_id
            WHERE dv.venta_id = ?
            ORDER BY dv.id
        ", [(int)$c['venta_id']]);

        Response::ok($c);
        break;

    // ============================================================
    // EMITIR COMPROBANTE
    // ============================================================
    case 'emitir':
        exigirEscritura();

        $body = jsonBody();

        $ventaId       = (int)($body['venta_id'] ?? 0);
        $nombre        = trim($body['nombre_comprador'] ?? '');
        $documento     = trim($body['documento_comprador'] ?? '');
        $telefono      = trim($body['telefono_comprador'] ?? '');
        $direccion     = trim($body['direccion_comprador'] ?? '');
        $observaciones = trim($body['observaciones'] ?? '');

        if ($ventaId <= 0) {
            Response::validacion(['venta_id' => 'Venta requerida']);
        }

        // Validar longitudes
        if (mb_strlen($nombre) > 150) {
            Response::validacion(['nombre_comprador' => 'Máximo 150 caracteres']);
        }
        if (mb_strlen($documento) > 50) {
            Response::validacion(['documento_comprador' => 'Máximo 50 caracteres']);
        }
        if (mb_strlen($telefono) > 50) {
            Response::validacion(['telefono_comprador' => 'Máximo 50 caracteres']);
        }
        if (mb_strlen($direccion) > 255) {
            Response::validacion(['direccion_comprador' => 'Máximo 255 caracteres']);
        }
        if (mb_strlen($observaciones) > 255) {
            Response::validacion(['observaciones' => 'Máximo 255 caracteres']);
        }

        // Verificar venta
        $venta = Database::fetchOne("
            SELECT id, folio, total, moneda, estado, usuario_id, punto_venta_id
            FROM ventas WHERE id = ?
        ", [$ventaId]);

        if (!$venta) Response::noEncontrado('Venta no encontrada');
        if ($venta['estado'] !== 'completada') {
            Response::error('No se puede emitir comprobante de una venta no completada');
        }

        // Verificar que no exista ya un comprobante para esta venta
        $existe = Database::fetchValue(
            "SELECT COUNT(*) FROM comprobantes_venta WHERE venta_id = ?",
            [$ventaId]
        );
        if ($existe) {
            Response::error('Esta venta ya tiene un comprobante emitido');
        }

        // Si es vendedor, solo puede emitir comprobantes de sus propias ventas
        if (Auth::esVendedor() && (int)$venta['usuario_id'] !== Auth::id()) {
            Response::prohibido('Solo puedes emitir comprobantes de tus propias ventas');
        }

        try {
            Database::begin();

            $folio = generarFolioComprobante();

            $comprobanteId = Database::insert('comprobantes_venta', [
                'folio'                => $folio,
                'venta_id'             => $ventaId,
                'nombre_comprador'     => $nombre ?: null,
                'documento_comprador'  => $documento ?: null,
                'telefono_comprador'   => $telefono ?: null,
                'direccion_comprador'  => $direccion ?: null,
                'observaciones'        => $observaciones ?: null,
                'total'                => (float)$venta['total'],
                'moneda'               => $venta['moneda'] ?: 'CUP',
                'estado'               => 'emitido',
                'emitido_por'          => Auth::id(),
            ]);

            Auditoria::registrar('comprobante_emitido', 'comprobantes_venta', $comprobanteId, [
                'folio'         => $folio,
                'venta'         => $venta['folio'],
                'total'         => (float)$venta['total'],
                'tiene_datos'   => $nombre !== '' || $telefono !== '',
            ]);

            Database::commit();

            Response::ok([
                'id'    => $comprobanteId,
                'folio' => $folio,
                'total' => (float)$venta['total'],
            ], 'Comprobante emitido correctamente');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error emitir comprobante: ' . $e->getMessage());
            Response::servidor('No se pudo emitir el comprobante: ' . $e->getMessage());
        }
        break;

    // ============================================================
    // ANULAR COMPROBANTE
    // ============================================================
    case 'anular':
        exigirEscritura();

        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        $motivo = trim($body['motivo'] ?? '');

        if ($id <= 0) Response::error('ID inválido');
        if (mb_strlen($motivo) < 5) {
            Response::validacion(['motivo' => 'El motivo debe tener al menos 5 caracteres']);
        }
        if (mb_strlen($motivo) > 255) {
            Response::validacion(['motivo' => 'Máximo 255 caracteres']);
        }

        $c = Database::fetchOne("
            SELECT cv.*, v.usuario_id AS venta_usuario_id
            FROM comprobantes_venta cv
            JOIN ventas v ON v.id = cv.venta_id
            WHERE cv.id = ?
        ", [$id]);

        if (!$c) Response::noEncontrado('Comprobante no encontrado');
        if ($c['estado'] === 'anulado') {
            Response::error('El comprobante ya está anulado');
        }

        // Si es vendedor, solo puede anular sus propios comprobantes
        if (Auth::esVendedor() && (int)$c['venta_usuario_id'] !== Auth::id()) {
            Response::prohibido('Solo puedes anular tus propios comprobantes');
        }

        try {
            Database::begin();

            Database::update('comprobantes_venta', [
                'estado'           => 'anulado',
                'anulado_por'      => Auth::id(),
                'fecha_anulacion'  => date('Y-m-d H:i:s'),
                'motivo_anulacion' => $motivo,
            ], 'id = :id', [':id' => $id]);

            Auditoria::registrar('comprobante_anulado', 'comprobantes_venta', $id, [
                'folio'  => $c['folio'],
                'motivo' => $motivo,
            ]);

            Database::commit();

            Response::ok(null, 'Comprobante anulado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error anular comprobante: ' . $e->getMessage());
            Response::servidor('No se pudo anular');
        }
        break;

    // ============================================================
    // PDF
    // ============================================================
    case 'pdf':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        // Modo: 'inline' (previsualización) o 'download' (descarga)
        $modo = $_GET['modo'] ?? 'download';
        if (!in_array($modo, ['inline', 'download'], true)) {
            $modo = 'download';
        }

        $existe = Database::fetchValue(
            "SELECT COUNT(*) FROM comprobantes_venta WHERE id = ?",
            [$id]
        );
        if (!$existe) Response::noEncontrado('Comprobante no encontrado');

        require_once __DIR__ . '/../core/reportes/comprobante.php';
        generar_comprobante_pdf($id, $modo);
        break;

    // ============================================================
    // VENTAS SIN COMPROBANTE
    // (para emisión posterior desde "Mis ventas" o "Ventas del día")
    // ============================================================
    case 'ventas_sin_comprobante':
        $q = trim($_GET['q'] ?? '');

        $condiciones = [
            'v.estado = \'completada\'',
            'NOT EXISTS (SELECT 1 FROM comprobantes_venta cv WHERE cv.venta_id = v.id)',
        ];
        $params = [];

        // Si es vendedor, solo sus ventas
        if (Auth::esVendedor()) {
            $condiciones[] = 'v.usuario_id = :usuario_id';
            $params[':usuario_id'] = Auth::id();
        }

        if ($q !== '') {
            $condiciones[] = 'v.folio LIKE :q1';
            $params[':q1'] = "%$q%";
        }

        $where = 'WHERE ' . implode(' AND ', $condiciones);

        $ventas = Database::fetchAll("
            SELECT
                v.id, v.folio, v.fecha, v.total, v.moneda,
                pv.nombre AS pv,
                u.nombre AS vendedor,
                (SELECT COUNT(*) FROM detalle_ventas dv WHERE dv.venta_id = v.id) AS num_productos
            FROM ventas v
            JOIN puntos_venta pv ON pv.id = v.punto_venta_id
            JOIN usuarios u ON u.id = v.usuario_id
            $where
            ORDER BY v.fecha DESC
            LIMIT 500
        ", $params);

        Response::ok($ventas);
        break;

    // ============================================================
    // CATÁLOGOS
    // ============================================================
    case 'catalogos':
        Response::ok([
            'estados' => [
                ['valor' => 'emitido', 'nombre' => 'Emitido'],
                ['valor' => 'anulado', 'nombre' => 'Anulado'],
            ],
        ]);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}