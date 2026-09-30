<?php
/**
 * IPV - API de Clientes Mayoristas
 *
 * Permisos:
 *   - Admin: escritura total
 *   - Supervisor: escritura total (los supervisores emiten facturas)
 *   - Vendedor: puede crear/consultar (para asignar a una venta desde el POS)
 *   - Almacenero: solo lectura
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';

ApiBootstrap::iniciar(['Administrador', 'Supervisor', 'Vendedor', 'Almacenero']);

$accion = $_GET['accion'] ?? 'listar';

function puedeEscribir(): bool {
    return Auth::esAdmin() || Auth::esSupervisor() || Auth::esVendedor();
}

function exigirEscritura(): void {
    if (!puedeEscribir()) {
        Response::prohibido('No tienes permiso para modificar clientes');
    }
}

switch ($accion) {

    // ============================================================
    // LISTAR
    // ============================================================
    case 'listar':
        $q = trim($_GET['q'] ?? '');
        $estado = trim($_GET['estado'] ?? '');

        $condiciones = [];
        $params = [];

        if ($q !== '') {
            $condiciones[] = '(c.nombre LIKE :q1 OR c.nit LIKE :q2 OR c.contacto LIKE :q3 OR c.email LIKE :q4)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
            $params[':q3'] = "%$q%";
            $params[':q4'] = "%$q%";
        }
        if ($estado === 'activos') {
            $condiciones[] = 'c.activo = 1';
        } elseif ($estado === 'inactivos') {
            $condiciones[] = 'c.activo = 0';
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $clientes = Database::fetchAll("
            SELECT
                c.id, c.nombre, c.nit, c.tipo_persona,
                c.direccion, c.telefono, c.email, c.contacto,
                c.notas, c.activo, c.created_at,
                (SELECT COUNT(*) FROM facturas f
                    WHERE f.cliente_id = c.id
                      AND f.estado IN ('emitida', 'parcial')) AS facturas_pendientes,
                (SELECT COUNT(*) FROM facturas f
                    WHERE f.cliente_id = c.id) AS facturas_total,
                (SELECT COALESCE(SUM(f.total), 0) FROM facturas f
                    WHERE f.cliente_id = c.id
                      AND f.estado IN ('emitida', 'parcial')) AS saldo_pendiente
            FROM clientes c
            $where
            ORDER BY c.activo DESC, c.nombre ASC
        ", $params);

        Response::ok($clientes);
        break;

    // ============================================================
    // OBTENER
    // ============================================================
    case 'obtener':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $cli = Database::fetchOne("
            SELECT * FROM clientes WHERE id = ?
        ", [$id]);

        if (!$cli) Response::noEncontrado('Cliente no encontrado');

        $cli['facturas'] = Database::fetchAll("
            SELECT
                f.id, f.folio, f.fecha_emision, f.fecha_vencimiento,
                f.total, f.estado,
                COALESCE((SELECT SUM(p.monto) FROM facturas_pagos p WHERE p.factura_id = f.id), 0) AS total_pagado
            FROM facturas f
            WHERE f.cliente_id = ?
            ORDER BY f.fecha_emision DESC
            LIMIT 50
        ", [$id]);

        Response::ok($cli);
        break;

    // ============================================================
    // CREAR
    // ============================================================
    case 'crear':
        exigirEscritura();

        $body = jsonBody();

        $nombre      = trim($body['nombre'] ?? '');
        $nit         = trim($body['nit'] ?? '');
        $tipoPersona = trim($body['tipo_persona'] ?? 'juridica');
        $direccion   = trim($body['direccion'] ?? '');
        $telefono    = trim($body['telefono'] ?? '');
        $email       = trim($body['email'] ?? '');
        $contacto    = trim($body['contacto'] ?? '');
        $notas       = trim($body['notas'] ?? '');
        $activo      = isset($body['activo']) ? (int)$body['activo'] : 1;

        $v = new Validador([
            'nombre' => $nombre,
            'email'  => $email,
            'tipo_persona' => $tipoPersona,
        ]);
        $v->requerido('nombre', 'nombre')->min('nombre', 2)->max('nombre', 150);
        $v->max('nit', 50);
        $v->max('direccion', 255);
        $v->max('telefono', 50);
        $v->max('contacto', 120);
        $v->email('email')->max('email', 120);
        $v->enLista('tipo_persona', ['fisica', 'juridica']);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        if ($nit !== '') {
            $existe = Database::fetchValue(
                "SELECT COUNT(*) FROM clientes WHERE nit = ?",
                [$nit]
            );
            if ($existe) {
                Response::validacion(['nit' => 'Ya existe un cliente con ese NIT']);
            }
        }

        try {
            Database::begin();

            $id = Database::insert('clientes', [
                'nombre'       => $nombre,
                'nit'          => $nit ?: null,
                'tipo_persona' => $tipoPersona,
                'direccion'    => $direccion ?: null,
                'telefono'     => $telefono ?: null,
                'email'        => $email ?: null,
                'contacto'     => $contacto ?: null,
                'notas'        => $notas ?: null,
                'activo'       => $activo,
                'created_by'   => Auth::id(),
            ]);

            Auditoria::registrar('cliente_creado', 'clientes', $id, [
                'nombre' => $nombre,
                'nit'    => $nit,
            ]);

            Database::commit();

            Response::ok(['id' => $id], 'Cliente creado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error crear cliente: ' . $e->getMessage());
            Response::servidor('No se pudo crear el cliente');
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

        $cli = Database::fetchOne("SELECT * FROM clientes WHERE id = ?", [$id]);
        if (!$cli) Response::noEncontrado('Cliente no encontrado');

        $nombre      = trim($body['nombre'] ?? '');
        $nit         = trim($body['nit'] ?? '');
        $tipoPersona = trim($body['tipo_persona'] ?? 'juridica');
        $direccion   = trim($body['direccion'] ?? '');
        $telefono    = trim($body['telefono'] ?? '');
        $email       = trim($body['email'] ?? '');
        $contacto    = trim($body['contacto'] ?? '');
        $notas       = trim($body['notas'] ?? '');
        $activo      = isset($body['activo']) ? (int)$body['activo'] : 1;

        $v = new Validador([
            'nombre' => $nombre,
            'email'  => $email,
            'tipo_persona' => $tipoPersona,
        ]);
        $v->requerido('nombre', 'nombre')->min('nombre', 2)->max('nombre', 150);
        $v->max('nit', 50);
        $v->max('direccion', 255);
        $v->max('telefono', 50);
        $v->max('contacto', 120);
        $v->email('email')->max('email', 120);
        $v->enLista('tipo_persona', ['fisica', 'juridica']);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        if ($nit !== '') {
            $existe = Database::fetchValue(
                "SELECT COUNT(*) FROM clientes WHERE nit = ? AND id != ?",
                [$nit, $id]
            );
            if ($existe) {
                Response::validacion(['nit' => 'Otro cliente ya tiene ese NIT']);
            }
        }

        try {
            Database::begin();

            Database::update('clientes', [
                'nombre'       => $nombre,
                'nit'          => $nit ?: null,
                'tipo_persona' => $tipoPersona,
                'direccion'    => $direccion ?: null,
                'telefono'     => $telefono ?: null,
                'email'        => $email ?: null,
                'contacto'     => $contacto ?: null,
                'notas'        => $notas ?: null,
                'activo'       => $activo,
            ], 'id = :id', [':id' => $id]);

            Auditoria::registrar('cliente_actualizado', 'clientes', $id, [
                'antes'   => ['nombre' => $cli['nombre'], 'activo' => $cli['activo']],
                'despues' => ['nombre' => $nombre, 'activo' => $activo],
            ]);

            Database::commit();

            Response::ok(null, 'Cliente actualizado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error actualizar cliente: ' . $e->getMessage());
            Response::servidor('No se pudo actualizar');
        }
        break;

    // ============================================================
    // CAMBIAR ESTADO
    // ============================================================
    case 'cambiar_estado':
        exigirEscritura();

        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $cli = Database::fetchOne("SELECT * FROM clientes WHERE id = ?", [$id]);
        if (!$cli) Response::noEncontrado('Cliente no encontrado');

        $nuevo = $cli['activo'] ? 0 : 1;

        Database::update('clientes', ['activo' => $nuevo], 'id = :id', [':id' => $id]);

        Auditoria::registrar(
            $nuevo ? 'cliente_activado' : 'cliente_desactivado',
            'clientes', $id, null
        );

        Response::ok(
            ['activo' => $nuevo],
            $nuevo ? 'Cliente activado' : 'Cliente desactivado'
        );
        break;

    // ============================================================
    // ELIMINAR
    // ============================================================
    case 'eliminar':
        exigirEscritura();

        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $cli = Database::fetchOne("SELECT * FROM clientes WHERE id = ?", [$id]);
        if (!$cli) Response::noEncontrado('Cliente no encontrado');

        $facturas = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM facturas WHERE cliente_id = ?",
            [$id]
        );
        $ventas = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM ventas WHERE cliente_id = ?",
            [$id]
        );

        if ($facturas > 0 || $ventas > 0) {
            Response::error(
                'No se puede eliminar: tiene facturas o ventas asociadas. ' .
                'Puedes desactivarlo en su lugar.'
            );
        }

        try {
            Database::begin();

            Database::delete('clientes', 'id = :id', [':id' => $id]);

            Auditoria::registrar('cliente_eliminado', 'clientes', $id, [
                'nombre' => $cli['nombre'],
            ]);

            Database::commit();

            Response::ok(null, 'Cliente eliminado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error eliminar cliente: ' . $e->getMessage());
            Response::servidor('No se pudo eliminar');
        }
        break;

    // ============================================================
    // CATÁLOGOS
    // ============================================================
    case 'catalogos':
        $soloActivos = ($_GET['solo_activos'] ?? '1') === '1';

        $where = $soloActivos ? 'WHERE activo = 1' : '';

        $clientes = Database::fetchAll("
            SELECT id, nombre, nit, telefono, direccion
            FROM clientes $where
            ORDER BY nombre
        ");

        Response::ok([
            'clientes' => $clientes,
        ]);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}