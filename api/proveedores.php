<?php
/**
 * IPV - API de Proveedores
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

ApiBootstrap::iniciar(['Administrador', 'Almacenero', 'Supervisor']);

$accion = $_GET['accion'] ?? 'listar';

// Helper: solo Admin puede escribir
function exigirEscritura(): void {
    if (!Auth::esAdmin()) {
        Response::prohibido('Solo el administrador puede modificar proveedores');
    }
}

switch ($accion) {

    // ============================================================
    // LISTAR
    // ============================================================
    case 'listar':
        $q      = trim($_GET['q'] ?? '');
        $estado = trim($_GET['estado'] ?? '');

        $condiciones = [];
        $params = [];

        if ($q !== '') {
            $condiciones[] = '(p.nombre LIKE :q1 OR p.nit LIKE :q2 OR p.contacto LIKE :q3 OR p.email LIKE :q4)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
            $params[':q3'] = "%$q%";
            $params[':q4'] = "%$q%";
        }
        if ($estado === 'activos') {
            $condiciones[] = 'p.activo = 1';
        } elseif ($estado === 'inactivos') {
            $condiciones[] = 'p.activo = 0';
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $proveedores = Database::fetchAll("
            SELECT
                p.id, p.nombre, p.nit, p.tipo_persona,
                p.direccion, p.telefono, p.email, p.contacto,
                p.notas, p.activo, p.created_at,
                (SELECT COUNT(*) FROM contratos_proveedor c
                    WHERE c.proveedor_id = p.id
                      AND c.estado IN ('activo','por_renovar')) AS contratos_activos,
                (SELECT COUNT(*) FROM contratos_proveedor c
                    WHERE c.proveedor_id = p.id) AS contratos_total
            FROM proveedores p
            $where
            ORDER BY p.activo DESC, p.nombre ASC
        ", $params);

        Response::ok($proveedores);
        break;

    // ============================================================
    // OBTENER
    // ============================================================
    case 'obtener':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $prov = Database::fetchOne("
            SELECT * FROM proveedores WHERE id = ?
        ", [$id]);

        if (!$prov) Response::noEncontrado('Proveedor no encontrado');

        $prov['contratos'] = Database::fetchAll("
            SELECT
                c.id, c.num_contrato, c.fecha_inicio, c.fecha_caducidad,
                c.estado, c.monto, c.moneda,
                CASE
                    WHEN c.estado = 'activo' AND c.fecha_caducidad < CURDATE() THEN 'por_renovar'
                    ELSE c.estado
                END AS estado_display
            FROM contratos_proveedor c
            WHERE c.proveedor_id = ?
            ORDER BY c.fecha_caducidad DESC
        ", [$id]);

        Response::ok($prov);
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

        // Verificar duplicado por NIT (si se especificó)
        if ($nit !== '') {
            $existe = Database::fetchValue(
                "SELECT COUNT(*) FROM proveedores WHERE nit = ?",
                [$nit]
            );
            if ($existe) {
                Response::validacion(['nit' => 'Ya existe un proveedor con ese NIT']);
            }
        }

        try {
            Database::begin();

            $id = Database::insert('proveedores', [
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

            Auditoria::registrar('proveedor_creado', 'proveedores', $id, [
                'nombre' => $nombre,
                'nit'    => $nit,
            ]);

            Database::commit();

            Response::ok(['id' => $id], 'Proveedor creado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error crear proveedor: ' . $e->getMessage());
            Response::servidor('No se pudo crear el proveedor');
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

        $prov = Database::fetchOne("SELECT * FROM proveedores WHERE id = ?", [$id]);
        if (!$prov) Response::noEncontrado('Proveedor no encontrado');

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
                "SELECT COUNT(*) FROM proveedores WHERE nit = ? AND id != ?",
                [$nit, $id]
            );
            if ($existe) {
                Response::validacion(['nit' => 'Otro proveedor ya tiene ese NIT']);
            }
        }

        try {
            Database::begin();

            Database::update('proveedores', [
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

            Auditoria::registrar('proveedor_actualizado', 'proveedores', $id, [
                'antes'   => ['nombre' => $prov['nombre'], 'activo' => $prov['activo']],
                'despues' => ['nombre' => $nombre, 'activo' => $activo],
            ]);

            Database::commit();

            Response::ok(null, 'Proveedor actualizado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error actualizar proveedor: ' . $e->getMessage());
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

        $prov = Database::fetchOne("SELECT * FROM proveedores WHERE id = ?", [$id]);
        if (!$prov) Response::noEncontrado('Proveedor no encontrado');

        $nuevo = $prov['activo'] ? 0 : 1;

        Database::update('proveedores', ['activo' => $nuevo], 'id = :id', [':id' => $id]);

        Auditoria::registrar(
            $nuevo ? 'proveedor_activado' : 'proveedor_desactivado',
            'proveedores', $id, null
        );

        Response::ok(
            ['activo' => $nuevo],
            $nuevo ? 'Proveedor activado' : 'Proveedor desactivado'
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

        $prov = Database::fetchOne("SELECT * FROM proveedores WHERE id = ?", [$id]);
        if (!$prov) Response::noEncontrado('Proveedor no encontrado');

        $contratos = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM contratos_proveedor WHERE proveedor_id = ?",
            [$id]
        );

        if ($contratos > 0) {
            Response::error(
                'No se puede eliminar: tiene ' . $contratos . ' contrato(s) asociado(s). ' .
                'Puedes desactivarlo en su lugar.'
            );
        }

        try {
            Database::begin();

            Database::delete('proveedores', 'id = :id', [':id' => $id]);

            Auditoria::registrar('proveedor_eliminado', 'proveedores', $id, [
                'nombre' => $prov['nombre'],
            ]);

            Database::commit();

            Response::ok(null, 'Proveedor eliminado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error eliminar proveedor: ' . $e->getMessage());
            Response::servidor('No se pudo eliminar');
        }
        break;

    // ============================================================
    // CATÁLOGOS (para selectores)
    // ============================================================
    case 'catalogos':
        $soloActivos = ($_GET['solo_activos'] ?? '1') === '1';

        $where = $soloActivos ? 'WHERE activo = 1' : '';

        $proveedores = Database::fetchAll("
            SELECT id, nombre, nit FROM proveedores $where ORDER BY nombre
        ");

        Response::ok([
            'proveedores' => $proveedores,
        ]);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}