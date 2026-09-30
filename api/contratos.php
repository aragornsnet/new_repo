<?php
/**
 * IPV - API de Contratos de Proveedor
 *
 * Permisos:
 *   - Admin: escritura total
 *   - Almacenero: solo lectura
 *   - Supervisor: solo lectura
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';
require_once __DIR__ . '/../core/Notificacion.php';

ApiBootstrap::iniciar(['Administrador', 'Almacenero', 'Supervisor']);

$accion = $_GET['accion'] ?? 'listar';

function exigirEscritura(): void {
    if (!Auth::esAdmin()) {
        Response::prohibido('Solo el administrador puede modificar contratos');
    }
}

/**
 * Calcula el estado display de un contrato combinando BD + fecha.
 */
function estadoDisplay(array $c): string {
    if (in_array($c['estado'], ['renovado', 'cancelado', 'no_renovado'], true)) {
        return $c['estado'];
    }
    if ($c['estado'] === 'por_renovar') return 'por_renovar';

    // estado === 'activo'
    $hoy = strtotime(date('Y-m-d'));
    $cad = strtotime($c['fecha_caducidad']);

    if ($cad < $hoy) return 'por_renovar'; // vencido

    $dias = (int) floor(($cad - $hoy) / 86400);
    if ($dias <= 30) return 'por_vencer';

    return 'activo';
}

switch ($accion) {

    // ============================================================
    // LISTAR
    // ============================================================
    case 'listar':
        $q          = trim($_GET['q'] ?? '');
        $estado     = trim($_GET['estado'] ?? '');
        $provId     = (int)($_GET['proveedor_id'] ?? 0);
        $desde      = trim($_GET['desde'] ?? '');
        $hasta      = trim($_GET['hasta'] ?? '');
        $vencen     = trim($_GET['vencen'] ?? ''); // '30' | '15' | '7'

        $condiciones = [];
        $params = [];

        if ($q !== '') {
            $condiciones[] = '(c.num_contrato LIKE :q1 OR p.nombre LIKE :q2 OR c.descripcion LIKE :q3)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
            $params[':q3'] = "%$q%";
        }
        if ($provId > 0) {
            $condiciones[] = 'c.proveedor_id = :prov_id';
            $params[':prov_id'] = $provId;
        }
        if ($desde !== '') {
            $condiciones[] = 'c.fecha_inicio >= :desde';
            $params[':desde'] = $desde;
        }
        if ($hasta !== '') {
            $condiciones[] = 'c.fecha_caducidad <= :hasta';
            $params[':hasta'] = $hasta;
        }

        // Filtro por estado
        if ($estado === 'activos') {
            $condiciones[] = "c.estado = 'activo' AND c.fecha_caducidad >= CURDATE()";
        } elseif ($estado === 'por_vencer') {
            $condiciones[] = "c.estado = 'activo'
                              AND c.fecha_caducidad >= CURDATE()
                              AND c.fecha_caducidad <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        } elseif ($estado === 'por_renovar') {
            $condiciones[] = "(c.estado = 'por_renovar'
                              OR (c.estado = 'activo' AND c.fecha_caducidad < CURDATE()))";
        } elseif ($estado === 'archivados') {
            $condiciones[] = "c.estado IN ('renovado','cancelado','no_renovado')";
        } elseif ($estado !== '') {
            $condiciones[] = 'c.estado = :estado';
            $params[':estado'] = $estado;
        }

        // Filtro por días para vencer
        if (in_array($vencen, ['30', '15', '7'], true)) {
            $condiciones[] = "c.estado = 'activo'
                              AND c.fecha_caducidad >= CURDATE()
                              AND c.fecha_caducidad <= DATE_ADD(CURDATE(), INTERVAL " . (int)$vencen . " DAY)";
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $contratos = Database::fetchAll("
            SELECT
                c.id, c.num_contrato, c.proveedor_id,
                c.fecha_inicio, c.fecha_caducidad,
                c.monto, c.moneda, c.forma_pago, c.plazo_dias,
                c.descripcion, c.documento, c.observaciones,
                c.estado, c.contrato_anterior_id,
                c.created_at, c.updated_at,
                p.nombre AS proveedor, p.nit AS proveedor_nit,
                u.nombre AS created_by_nombre,
                ca.num_contrato AS contrato_anterior_num,
                (SELECT COUNT(*) FROM contratos_productos cp
                    WHERE cp.contrato_id = c.id) AS num_productos
            FROM contratos_proveedor c
            JOIN proveedores p ON p.id = c.proveedor_id
            LEFT JOIN usuarios u ON u.id = c.created_by
            LEFT JOIN contratos_proveedor ca ON ca.id = c.contrato_anterior_id
            $where
            ORDER BY
                CASE c.estado
                    WHEN 'por_renovar' THEN 1
                    WHEN 'activo' THEN 2
                    ELSE 3
                END,
                c.fecha_caducidad ASC
            LIMIT 1000
        ", $params);

        // Añadir estado_display y días restantes
        foreach ($contratos as &$c) {
            $c['estado_display'] = estadoDisplay($c);
            if (in_array($c['estado'], ['activo', 'por_renovar'], true)) {
                $hoy = strtotime(date('Y-m-d'));
                $cad = strtotime($c['fecha_caducidad']);
                $c['dias_restantes'] = (int) floor(($cad - $hoy) / 86400);
            } else {
                $c['dias_restantes'] = null;
            }
        }
        unset($c);

        Response::ok($contratos);
        break;

    // ============================================================
    // OBTENER
    // ============================================================
    case 'obtener':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $c = Database::fetchOne("
            SELECT
                c.*,
                p.nombre AS proveedor, p.nit AS proveedor_nit,
                p.telefono AS proveedor_telefono, p.email AS proveedor_email,
                p.contacto AS proveedor_contacto,
                u.nombre AS created_by_nombre,
                ca.num_contrato AS contrato_anterior_num
            FROM contratos_proveedor c
            JOIN proveedores p ON p.id = c.proveedor_id
            LEFT JOIN usuarios u ON u.id = c.created_by
            LEFT JOIN contratos_proveedor ca ON ca.id = c.contrato_anterior_id
            WHERE c.id = ?
        ", [$id]);

        if (!$c) Response::noEncontrado('Contrato no encontrado');

        $c['estado_display'] = estadoDisplay($c);

        // Productos vinculados
        $c['productos'] = Database::fetchAll("
            SELECT
                cp.id, cp.producto_id,
                p.nombre AS producto, p.codigo_barras,
                p.unidad_medida, p.precio, p.costo, p.activo
            FROM contratos_productos cp
            JOIN productos p ON p.id = cp.producto_id
            WHERE cp.contrato_id = ?
            ORDER BY p.nombre
        ", [$id]);

        Response::ok($c);
        break;

    // ============================================================
    // CREAR
    // ============================================================
    case 'crear':
        exigirEscritura();

        $body = jsonBody();

        $numContrato  = trim($body['num_contrato'] ?? '');
        $proveedorId  = (int)($body['proveedor_id'] ?? 0);
        $fechaInicio  = trim($body['fecha_inicio'] ?? '');
        $fechaCaduca  = trim($body['fecha_caducidad'] ?? '');
        $monto        = $body['monto'] ?? null;
        $moneda       = trim($body['moneda'] ?? 'CUP');
        $formaPago    = trim($body['forma_pago'] ?? 'contado');
        $plazoDias    = isset($body['plazo_dias']) ? (int)$body['plazo_dias'] : null;
        $descripcion  = trim($body['descripcion'] ?? '');
        $observaciones = trim($body['observaciones'] ?? '');
        $productos    = $body['productos'] ?? [];

        $v = new Validador([
            'num_contrato'     => $numContrato,
            'proveedor_id'     => $proveedorId,
            'fecha_inicio'     => $fechaInicio,
            'fecha_caducidad'  => $fechaCaduca,
            'moneda'           => $moneda,
            'forma_pago'       => $formaPago,
        ]);
        $v->requerido('num_contrato', 'número de contrato')->min('num_contrato', 1)->max('num_contrato', 50);
        $v->requerido('proveedor_id', 'proveedor')->entero('proveedor_id')->mayorIgual('proveedor_id', 1);
        $v->requerido('fecha_inicio', 'fecha de inicio')->fecha('fecha_inicio');
        $v->requerido('fecha_caducidad', 'fecha de caducidad')->fecha('fecha_caducidad');
        $v->max('moneda', 5);
        $v->enLista('forma_pago', ['contado', 'credito', 'mixto']);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        if (strtotime($fechaCaduca) <= strtotime($fechaInicio)) {
            Response::validacion(['fecha_caducidad' => 'La fecha de caducidad debe ser posterior a la de inicio']);
        }

        if ($monto !== null && $monto !== '' && !is_numeric($monto)) {
            Response::validacion(['monto' => 'El monto debe ser numérico']);
        }

        // Proveedor existe y está activo
        $prov = Database::fetchOne("SELECT id, nombre FROM proveedores WHERE id = ? AND activo = 1", [$proveedorId]);
        if (!$prov) Response::noEncontrado('Proveedor no encontrado o inactivo');

        // num_contrato único
        $existe = Database::fetchValue(
            "SELECT COUNT(*) FROM contratos_proveedor WHERE num_contrato = ?",
            [$numContrato]
        );
        if ($existe) {
            Response::validacion(['num_contrato' => 'Ya existe un contrato con ese número']);
        }

        // Validar productos
        $productosLimpios = [];
        $vistos = [];
        if (is_array($productos)) {
            foreach ($productos as $pid) {
                $pid = (int)$pid;
                if ($pid <= 0) continue;
                if (isset($vistos[$pid])) continue;
                $vistos[$pid] = true;
                $productosLimpios[] = $pid;
            }
        }

        try {
            Database::begin();

            $contratoId = Database::insert('contratos_proveedor', [
                'num_contrato'   => $numContrato,
                'proveedor_id'   => $proveedorId,
                'fecha_inicio'   => $fechaInicio,
                'fecha_caducidad'=> $fechaCaduca,
                'monto'          => ($monto !== null && $monto !== '') ? (float)$monto : null,
                'moneda'         => $moneda,
                'forma_pago'     => $formaPago,
                'plazo_dias'     => $plazoDias,
                'descripcion'    => $descripcion ?: null,
                'observaciones'  => $observaciones ?: null,
                'estado'         => 'activo',
                'created_by'     => Auth::id(),
            ]);

            // Insertar productos
            foreach ($productosLimpios as $pid) {
                $existeProd = Database::fetchValue(
                    "SELECT COUNT(*) FROM productos WHERE id = ? AND activo = 1",
                    [$pid]
                );
                if (!$existeProd) continue;

                Database::insert('contratos_productos', [
                    'contrato_id' => $contratoId,
                    'producto_id' => $pid,
                ]);
            }

            Auditoria::registrar('contrato_creado', 'contratos_proveedor', $contratoId, [
                'num_contrato' => $numContrato,
                'proveedor'    => $prov['nombre'],
                'productos'    => count($productosLimpios),
            ]);

            Database::commit();

            Response::ok([
                'id'           => $contratoId,
                'num_contrato' => $numContrato,
            ], 'Contrato creado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error crear contrato: ' . $e->getMessage());
            Response::servidor('No se pudo crear el contrato: ' . $e->getMessage());
        }
        break;

    // ============================================================
    // ACTUALIZAR
    // ============================================================
    case 'actualizar':
        exigirEscritura();

        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $c = Database::fetchOne("SELECT * FROM contratos_proveedor WHERE id = ?", [$id]);
        if (!$c) Response::noEncontrado('Contrato no encontrado');

        if (in_array($c['estado'], ['renovado', 'cancelado', 'no_renovado'], true)) {
            Response::error('No se puede editar un contrato archivado');
        }

        $numContrato  = trim($body['num_contrato'] ?? '');
        $proveedorId  = (int)($body['proveedor_id'] ?? 0);
        $fechaInicio  = trim($body['fecha_inicio'] ?? '');
        $fechaCaduca  = trim($body['fecha_caducidad'] ?? '');
        $monto        = $body['monto'] ?? null;
        $moneda       = trim($body['moneda'] ?? 'CUP');
        $formaPago    = trim($body['forma_pago'] ?? 'contado');
        $plazoDias    = isset($body['plazo_dias']) ? (int)$body['plazo_dias'] : null;
        $descripcion  = trim($body['descripcion'] ?? '');
        $observaciones = trim($body['observaciones'] ?? '');
        $productos    = $body['productos'] ?? [];

        $v = new Validador([
            'num_contrato'    => $numContrato,
            'proveedor_id'    => $proveedorId,
            'fecha_inicio'    => $fechaInicio,
            'fecha_caducidad' => $fechaCaduca,
            'moneda'          => $moneda,
            'forma_pago'      => $formaPago,
        ]);
        $v->requerido('num_contrato', 'número de contrato')->min('num_contrato', 1)->max('num_contrato', 50);
        $v->requerido('proveedor_id', 'proveedor')->entero('proveedor_id')->mayorIgual('proveedor_id', 1);
        $v->requerido('fecha_inicio', 'fecha de inicio')->fecha('fecha_inicio');
        $v->requerido('fecha_caducidad', 'fecha de caducidad')->fecha('fecha_caducidad');
        $v->max('moneda', 5);
        $v->enLista('forma_pago', ['contado', 'credito', 'mixto']);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        if (strtotime($fechaCaduca) <= strtotime($fechaInicio)) {
            Response::validacion(['fecha_caducidad' => 'La fecha de caducidad debe ser posterior a la de inicio']);
        }

        $prov = Database::fetchOne("SELECT id FROM proveedores WHERE id = ? AND activo = 1", [$proveedorId]);
        if (!$prov) Response::noEncontrado('Proveedor no encontrado o inactivo');

        $existe = Database::fetchValue(
            "SELECT COUNT(*) FROM contratos_proveedor WHERE num_contrato = ? AND id != ?",
            [$numContrato, $id]
        );
        if ($existe) {
            Response::validacion(['num_contrato' => 'Otro contrato ya usa ese número']);
        }

        $productosLimpios = [];
        $vistos = [];
        if (is_array($productos)) {
            foreach ($productos as $pid) {
                $pid = (int)$pid;
                if ($pid <= 0) continue;
                if (isset($vistos[$pid])) continue;
                $vistos[$pid] = true;
                $productosLimpios[] = $pid;
            }
        }

        try {
            Database::begin();

            // Recalcular estado: si vencía y sigue vencido, marcar por_renovar
            $nuevoEstado = $c['estado'];
            if ($c['estado'] === 'activo') {
                if (strtotime($fechaCaduca) < strtotime(date('Y-m-d'))) {
                    $nuevoEstado = 'por_renovar';
                }
            }
            // Si el usuario extiende la fecha y estaba por_renovar, vuelve a activo
            if ($c['estado'] === 'por_renovar' && strtotime($fechaCaduca) >= strtotime(date('Y-m-d'))) {
                $nuevoEstado = 'activo';
            }

            Database::update('contratos_proveedor', [
                'num_contrato'    => $numContrato,
                'proveedor_id'    => $proveedorId,
                'fecha_inicio'    => $fechaInicio,
                'fecha_caducidad' => $fechaCaduca,
                'monto'           => ($monto !== null && $monto !== '') ? (float)$monto : null,
                'moneda'          => $moneda,
                'forma_pago'      => $formaPago,
                'plazo_dias'      => $plazoDias,
                'descripcion'     => $descripcion ?: null,
                'observaciones'   => $observaciones ?: null,
                'estado'          => $nuevoEstado,
            ], 'id = :id', [':id' => $id]);

            // Reemplazar productos
            Database::delete('contratos_productos', 'contrato_id = :id', [':id' => $id]);
            foreach ($productosLimpios as $pid) {
                $existeProd = Database::fetchValue(
                    "SELECT COUNT(*) FROM productos WHERE id = ? AND activo = 1",
                    [$pid]
                );
                if (!$existeProd) continue;

                Database::insert('contratos_productos', [
                    'contrato_id' => $id,
                    'producto_id' => $pid,
                ]);
            }

            Auditoria::registrar('contrato_actualizado', 'contratos_proveedor', $id, [
                'num_contrato' => $numContrato,
                'estado_antes' => $c['estado'],
                'estado_nuevo' => $nuevoEstado,
                'productos'    => count($productosLimpios),
            ]);

            Database::commit();

            Response::ok(['estado' => $nuevoEstado], 'Contrato actualizado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error actualizar contrato: ' . $e->getMessage());
            Response::servidor('No se pudo actualizar');
        }
        break;

    // ============================================================
    // CANCELAR (activo → cancelado)
    // ============================================================
    case 'cancelar':
        exigirEscritura();

        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        $motivo = trim($body['motivo'] ?? '');

        if ($id <= 0) Response::error('ID inválido');
        if (mb_strlen($motivo) < 5) {
            Response::validacion(['motivo' => 'El motivo debe tener al menos 5 caracteres']);
        }

        $c = Database::fetchOne("SELECT * FROM contratos_proveedor WHERE id = ?", [$id]);
        if (!$c) Response::noEncontrado('Contrato no encontrado');

        if ($c['estado'] !== 'activo') {
            Response::error('Solo se pueden cancelar contratos activos');
        }
        if (strtotime($c['fecha_caducidad']) < strtotime(date('Y-m-d'))) {
            Response::error('El contrato ya venció. Usa "No renovar" en su lugar.');
        }

        try {
            Database::begin();

            Database::update('contratos_proveedor', [
                'estado'        => 'cancelado',
                'observaciones' => trim($c['observaciones'] . "\n\n[CANCELADO] " . $motivo),
            ], 'id = :id', [':id' => $id]);

            Auditoria::registrar('contrato_cancelado', 'contratos_proveedor', $id, [
                'num_contrato' => $c['num_contrato'],
                'motivo'       => $motivo,
            ]);

            Database::commit();

            Response::ok(null, 'Contrato cancelado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error cancelar contrato: ' . $e->getMessage());
            Response::servidor('No se pudo cancelar');
        }
        break;

    // ============================================================
    // NO RENOVAR (por_renovar → no_renovado)
    // ============================================================
    case 'no_renovar':
        exigirEscritura();

        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        $motivo = trim($body['motivo'] ?? '');

        if ($id <= 0) Response::error('ID inválido');
        if (mb_strlen($motivo) < 5) {
            Response::validacion(['motivo' => 'El motivo debe tener al menos 5 caracteres']);
        }

        $c = Database::fetchOne("SELECT * FROM contratos_proveedor WHERE id = ?", [$id]);
        if (!$c) Response::noEncontrado('Contrato no encontrado');

        $estadoActual = estadoDisplay($c);
        if ($estadoActual !== 'por_renovar') {
            Response::error('Solo se pueden marcar como "no renovado" los contratos vencidos');
        }

        try {
            Database::begin();

            Database::update('contratos_proveedor', [
                'estado'        => 'no_renovado',
                'observaciones' => trim($c['observaciones'] . "\n\n[NO RENOVADO] " . $motivo),
            ], 'id = :id', [':id' => $id]);

            Auditoria::registrar('contrato_no_renovado', 'contratos_proveedor', $id, [
                'num_contrato' => $c['num_contrato'],
                'motivo'       => $motivo,
            ]);

            Database::commit();

            Response::ok(null, 'Contrato archivado como no renovado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error no_renovar contrato: ' . $e->getMessage());
            Response::servidor('No se pudo archivar');
        }
        break;

    // ============================================================
    // RENOVAR (crea uno nuevo y marca el viejo como renovado)
    // ============================================================
    case 'renovar':
        exigirEscritura();

        $body = jsonBody();
        $idViejo = (int)($body['id'] ?? 0);
        $numNuevo = trim($body['num_contrato'] ?? '');
        $fechaInicio = trim($body['fecha_inicio'] ?? '');
        $fechaCaduca = trim($body['fecha_caducidad'] ?? '');

        if ($idViejo <= 0) Response::error('ID inválido');
        if ($numNuevo === '') Response::validacion(['num_contrato' => 'El número de contrato es obligatorio']);
        if ($fechaInicio === '') Response::validacion(['fecha_inicio' => 'La fecha de inicio es obligatoria']);
        if ($fechaCaduca === '') Response::validacion(['fecha_caducidad' => 'La fecha de caducidad es obligatoria']);

        $v = new Validador(['num_contrato' => $numNuevo]);
        $v->min('num_contrato', 1)->max('num_contrato', 50);
        if ($v->falla()) Response::validacion($v->erroresPlanos());

        if (strtotime($fechaCaduca) <= strtotime($fechaInicio)) {
            Response::validacion(['fecha_caducidad' => 'La fecha de caducidad debe ser posterior a la de inicio']);
        }

        $viejo = Database::fetchOne("SELECT * FROM contratos_proveedor WHERE id = ?", [$idViejo]);
        if (!$viejo) Response::noEncontrado('Contrato original no encontrado');

        $estadoActual = estadoDisplay($viejo);
        if (!in_array($estadoActual, ['activo', 'por_vencer', 'por_renovar'], true)) {
            Response::error('Solo se pueden renovar contratos vigentes o vencidos');
        }

        $existe = Database::fetchValue(
            "SELECT COUNT(*) FROM contratos_proveedor WHERE num_contrato = ?",
            [$numNuevo]
        );
        if ($existe) {
            Response::validacion(['num_contrato' => 'Ya existe un contrato con ese número']);
        }

        try {
            Database::begin();

            // Crear el nuevo contrato (copia datos del viejo)
            $nuevoId = Database::insert('contratos_proveedor', [
                'num_contrato'         => $numNuevo,
                'proveedor_id'         => (int)$viejo['proveedor_id'],
                'fecha_inicio'         => $fechaInicio,
                'fecha_caducidad'      => $fechaCaduca,
                'monto'                => $viejo['monto'],
                'moneda'               => $viejo['moneda'],
                'forma_pago'           => $viejo['forma_pago'],
                'plazo_dias'           => $viejo['plazo_dias'],
                'descripcion'          => $viejo['descripcion'],
                'observaciones'        => null,
                'estado'               => 'activo',
                'contrato_anterior_id' => $idViejo,
                'created_by'           => Auth::id(),
            ]);

            // Copiar productos
            $productosViejos = Database::fetchAll(
                "SELECT producto_id FROM contratos_productos WHERE contrato_id = ?",
                [$idViejo]
            );
            foreach ($productosViejos as $p) {
                Database::insert('contratos_productos', [
                    'contrato_id' => $nuevoId,
                    'producto_id' => (int)$p['producto_id'],
                ]);
            }

            // Marcar el viejo como renovado
            Database::update('contratos_proveedor', [
                'estado' => 'renovado',
            ], 'id = :id', [':id' => $idViejo]);

            Auditoria::registrar('contrato_renovado', 'contratos_proveedor', $nuevoId, [
                'contrato_anterior' => $viejo['num_contrato'],
                'num_contrato_nuevo'=> $numNuevo,
                'productos_copiados' => count($productosViejos),
            ]);

            Database::commit();

            Response::ok([
                'id_viejo'     => $idViejo,
                'id_nuevo'     => $nuevoId,
                'num_contrato' => $numNuevo,
            ], 'Contrato renovado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error renovar contrato: ' . $e->getMessage());
            Response::servidor('No se pudo renovar: ' . $e->getMessage());
        }
        break;

    // ============================================================
    // REACTIVAR (solo Admin) - reactiva un contrato cancelado o no_renovado
    // ============================================================
    case 'reactivar':
        exigirEscritura();

        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $c = Database::fetchOne("SELECT * FROM contratos_proveedor WHERE id = ?", [$id]);
        if (!$c) Response::noEncontrado('Contrato no encontrado');

        if (!in_array($c['estado'], ['cancelado', 'no_renovado'], true)) {
            Response::error('Solo se pueden reactivar contratos cancelados o no renovados');
        }

        try {
            Database::begin();

            $nuevoEstado = strtotime($c['fecha_caducidad']) < strtotime(date('Y-m-d'))
                ? 'por_renovar'
                : 'activo';

            Database::update('contratos_proveedor', [
                'estado' => $nuevoEstado,
            ], 'id = :id', [':id' => $id]);

            Auditoria::registrar('contrato_reactivado', 'contratos_proveedor', $id, [
                'num_contrato' => $c['num_contrato'],
                'estado_nuevo' => $nuevoEstado,
            ]);

            Database::commit();

            Response::ok(['estado' => $nuevoEstado], 'Contrato reactivado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error reactivar contrato: ' . $e->getMessage());
            Response::servidor('No se pudo reactivar');
        }
        break;

    // ============================================================
    // PRODUCTOS DISPONIBLES PARA VINCULAR AL CONTRATO
    // (todos los productos activos)
    // ============================================================
    case 'productos_disponibles':
        $q = trim($_GET['q'] ?? '');
        $limite = min(max((int)($_GET['limit'] ?? 100), 1), 500);

        $condiciones = ['p.activo = 1'];
        $params = [];

        if ($q !== '') {
            $condiciones[] = '(p.nombre LIKE :q1 OR p.codigo_barras LIKE :q2)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
        }

        $where = 'WHERE ' . implode(' AND ', $condiciones);

        $productos = Database::fetchAll("
            SELECT
                p.id, p.nombre, p.codigo_barras,
                p.unidad_medida, p.precio, p.costo,
                c.nombre AS categoria
            FROM productos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            $where
            ORDER BY p.nombre
            LIMIT $limite
        ", $params);

        Response::ok($productos);
        break;

    // ============================================================
    // CONTRATOS POR VENCER (para dashboard / avisos)
    // ============================================================
    case 'por_vencer':
        $dias = (int)($_GET['dias'] ?? 30);
        $dias = min(max($dias, 1), 365);

        $contratos = Database::fetchAll("
            SELECT
                c.id, c.num_contrato, c.fecha_caducidad,
                p.nombre AS proveedor,
                DATEDIFF(c.fecha_caducidad, CURDATE()) AS dias_restantes
            FROM contratos_proveedor c
            JOIN proveedores p ON p.id = c.proveedor_id
            WHERE c.estado = 'activo'
              AND c.fecha_caducidad >= CURDATE()
              AND c.fecha_caducidad <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY c.fecha_caducidad ASC
        ", [$dias]);

        Response::ok([
            'total'     => count($contratos),
            'contratos' => $contratos,
        ]);
        break;

    // ============================================================
    // CATÁLOGOS
    // ============================================================
    case 'catalogos':
        Response::ok([
            'formas_pago' => [
                ['valor' => 'contado', 'nombre' => 'Contado'],
                ['valor' => 'credito', 'nombre' => 'Crédito'],
                ['valor' => 'mixto',   'nombre' => 'Mixto'],
            ],
            'estados' => [
                ['valor' => 'activo',       'nombre' => 'Activo'],
                ['valor' => 'por_vencer',   'nombre' => 'Por vencer'],
                ['valor' => 'por_renovar',  'nombre' => 'Por renovar'],
                ['valor' => 'renovado',     'nombre' => 'Renovado'],
                ['valor' => 'cancelado',    'nombre' => 'Cancelado'],
                ['valor' => 'no_renovado',  'nombre' => 'No renovado'],
            ],
        ]);
        break;

    // ============================================================
    // RESUMEN DE ALERTAS (para badge del sidebar)
    // ============================================================
    case 'alertas_resumen':
        $porVencer = (int) Database::fetchValue("
            SELECT COUNT(*) FROM contratos_proveedor
            WHERE estado = 'activo'
              AND fecha_caducidad >= CURDATE()
              AND fecha_caducidad <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ", [], 0);

        $porRenovar = (int) Database::fetchValue("
            SELECT COUNT(*) FROM contratos_proveedor
            WHERE estado = 'por_renovar'
        ", [], 0);

        Response::ok([
            'por_vencer'  => $porVencer,
            'por_renovar' => $porRenovar,
            'total'       => $porVencer + $porRenovar,
        ]);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}