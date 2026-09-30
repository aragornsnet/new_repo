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
        $busqueda = trim($_GET['q'] ?? '');
        $rolId = (int) ($_GET['rol_id'] ?? 0);
        $pvId = (int) ($_GET['pv_id'] ?? 0);
        $estado = $_GET['estado'] ?? '';

        $condiciones = [];
        $params = [];

        if ($busqueda !== '') {
            $condiciones[] = '(u.nombre LIKE :q1 OR u.email LIKE :q2)';
            $params[':q1'] = "%$busqueda%";
            $params[':q2'] = "%$busqueda%";
        }
        if ($rolId > 0) {
            $condiciones[] = 'u.rol_id = :rol_id';
            $params[':rol_id'] = $rolId;
        }
        if ($pvId > 0) {
            $condiciones[] = 'u.punto_venta_id = :pv_id';
            $params[':pv_id'] = $pvId;
        }
        if ($estado === 'activos') {
            $condiciones[] = 'u.activo = 1';
        } elseif ($estado === 'inactivos') {
            $condiciones[] = 'u.activo = 0';
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $usuarios = Database::fetchAll("
            SELECT 
                u.id, u.nombre, u.email, u.activo, u.ultimo_login, u.created_at,
                r.id AS rol_id, r.nombre AS rol,
                pv.id AS pv_id, pv.nombre AS pv
            FROM usuarios u
            JOIN roles r ON r.id = u.rol_id
            LEFT JOIN puntos_venta pv ON pv.id = u.punto_venta_id
            $where
            ORDER BY u.id DESC
        ", $params);

        Response::ok($usuarios);
        break;

    case 'obtener':
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $u = Database::fetchOne("
            SELECT 
                u.id, u.nombre, u.email, u.activo, u.rol_id, u.punto_venta_id,
                u.ultimo_login, u.created_at,
                r.nombre AS rol,
                pv.nombre AS pv
            FROM usuarios u
            JOIN roles r ON r.id = u.rol_id
            LEFT JOIN puntos_venta pv ON pv.id = u.punto_venta_id
            WHERE u.id = ?
        ", [$id]);

        if (!$u) Response::noEncontrado('Usuario no encontrado');

        $u['stats'] = Database::fetchOne("
            SELECT 
                COUNT(*) AS total_ventas,
                COALESCE(SUM(total), 0) AS monto_total
            FROM ventas
            WHERE usuario_id = ? AND estado = 'completada'
        ", [$id]);

        $u['ultimas_acciones'] = Database::fetchAll("
            SELECT accion, tabla_afectada, detalle, fecha
            FROM auditoria
            WHERE usuario_id = ?
            ORDER BY fecha DESC
            LIMIT 10
        ", [$id]);

        Response::ok($u);
        break;

    // ============================================================
    // CREAR usuario
    // ============================================================
    case 'crear':
        $body = jsonBody();

        $nombre = trim($body['nombre'] ?? '');
        $email = strtolower(trim($body['email'] ?? ''));
        $password = $body['password'] ?? '';
        $password2 = $body['password2'] ?? '';
        $rolId = (int) ($body['rol_id'] ?? 0);
        $pvId = $body['punto_venta_id'] ?? null;
        $activo = isset($body['activo']) ? (int) $body['activo'] : 1;

        $v = new Validador([
            'nombre' => $nombre, 'email' => $email,
            'password' => $password, 'password2' => $password2,
            'rol_id' => $rolId,
        ]);
        $v->requerido('nombre', 'nombre')->min('nombre', 3)->max('nombre', 100);
        $v->requerido('email', 'correo')->email('email');
        $v->requerido('password', 'contraseña')->password('password');
        $v->iguales('password', 'password2', 'Las contraseñas no coinciden');
        // ⭐ Ahora se permiten los roles 1, 2, 3 y 4
        $v->requerido('rol_id', 'rol')->enLista('rol_id', [1, 2, 3, 4]);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        // ============================================================
        // Reglas de asignación de PV según rol
        // ------------------------------------------------------------
        // Rol 1 (Admin), 2 (Supervisor), 4 (Almacenero): sin PV.
        // Rol 3 (Vendedor): PV obligatorio.
        // ============================================================
        if (in_array($rolId, [1, 2, 4], true) && !empty($pvId)) {
            Response::validacion(['punto_venta_id' => 'Admin, Supervisor y Almacenero no tienen PV asignado']);
        }
        if ($rolId === 3 && empty($pvId)) {
            Response::validacion(['punto_venta_id' => 'El Vendedor debe tener un PV asignado']);
        }

        $existe = Database::fetchValue("SELECT COUNT(*) FROM usuarios WHERE email = ?", [$email]);
        if ($existe) {
            Response::validacion(['email' => 'Este correo ya está registrado']);
        }

        try {
            Database::begin();

            $nuevoId = Database::insert('usuarios', [
                'nombre'         => $nombre,
                'email'          => $email,
                'password'       => password_hash($password, PASSWORD_DEFAULT),
                'rol_id'         => $rolId,
                'punto_venta_id' => $rolId === 3 ? $pvId : null,
                'activo'         => $activo,
            ]);

            Auditoria::registrar('usuario_creado', 'usuarios', $nuevoId, [
                'nombre' => $nombre,
                'email'  => $email,
                'rol_id' => $rolId,
            ]);

            Notificacion::crearParaAdmins(
                'usuario_creado',
                'Nuevo usuario creado',
                $nombre . ' (' . $email . ')',
                'views/admin/usuarios.php',
                'person-plus-fill',
                'success'
            );

            Database::commit();

            Response::ok(['id' => $nuevoId], 'Usuario creado correctamente');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error crear usuario: ' . $e->getMessage());
            Response::servidor('No se pudo crear el usuario');
        }
        break;

    // ============================================================
    // ACTUALIZAR usuario
    // ============================================================
    case 'actualizar':
        $body = jsonBody();
        $id = (int) ($body['id'] ?? 0);

        if ($id <= 0) Response::error('ID inválido');

        $usuarioActual = Database::fetchOne("SELECT * FROM usuarios WHERE id = ?", [$id]);
        if (!$usuarioActual) Response::noEncontrado('Usuario no encontrado');

        $nombre = trim($body['nombre'] ?? '');
        $email = strtolower(trim($body['email'] ?? ''));
        $rolId = (int) ($body['rol_id'] ?? 0);
        $pvId = $body['punto_venta_id'] ?? null;
        $activo = isset($body['activo']) ? (int) $body['activo'] : 1;

        $v = new Validador([
            'nombre' => $nombre, 'email' => $email, 'rol_id' => $rolId,
        ]);
        $v->requerido('nombre')->min('nombre', 3)->max('nombre', 100);
        $v->requerido('email')->email('email');
        // ⭐ Ahora se permiten los roles 1, 2, 3 y 4
        $v->requerido('rol_id')->enLista('rol_id', [1, 2, 3, 4]);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        // ============================================================
        // Reglas de asignación de PV según rol
        // ============================================================
        if (in_array($rolId, [1, 2, 4], true) && !empty($pvId)) {
            Response::validacion(['punto_venta_id' => 'Admin, Supervisor y Almacenero no tienen PV']);
        }
        if ($rolId === 3 && empty($pvId)) {
            Response::validacion(['punto_venta_id' => 'El Vendedor debe tener PV']);
        }

        $existe = Database::fetchValue(
            "SELECT COUNT(*) FROM usuarios WHERE email = ? AND id != ?",
            [$email, $id]
        );
        if ($existe) {
            Response::validacion(['email' => 'Este correo ya está en uso']);
        }

        // Validar que quede al menos un admin activo
        if (($rolId !== 1 || $activo === 0) && $usuarioActual['rol_id'] == 1) {
            $adminsActivos = (int) Database::fetchValue(
                "SELECT COUNT(*) FROM usuarios WHERE rol_id = 1 AND activo = 1 AND id != ?",
                [$id]
            );
            if ($adminsActivos === 0) {
                Response::validacion(['rol_id' => 'Debe existir al menos un administrador activo']);
            }
        }

        try {
            Database::begin();

            Database::update('usuarios', [
                'nombre'         => $nombre,
                'email'          => $email,
                'rol_id'         => $rolId,
                'punto_venta_id' => $rolId === 3 ? $pvId : null,
                'activo'         => $activo,
            ], 'id = :id', [':id' => $id]);

            Auditoria::registrar('usuario_actualizado', 'usuarios', $id, [
                'antes'  => [
                    'nombre' => $usuarioActual['nombre'],
                    'email'  => $usuarioActual['email'],
                    'rol_id' => $usuarioActual['rol_id'],
                    'activo' => $usuarioActual['activo'],
                ],
                'despues' => compact('nombre', 'email', 'rolId', 'activo'),
            ]);

            Database::commit();

            Response::ok(null, 'Usuario actualizado');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error actualizar usuario: ' . $e->getMessage());
            Response::servidor('No se pudo actualizar');
        }
        break;

    case 'reset_password':
        $body = jsonBody();
        $id = (int) ($body['id'] ?? 0);
        $password = $body['password'] ?? '';
        $password2 = $body['password2'] ?? '';

        if ($id <= 0) Response::error('ID inválido');

        $v = new Validador(['password' => $password, 'password2' => $password2]);
        $v->requerido('password', 'contraseña')->password('password');
        $v->iguales('password', 'password2', 'Las contraseñas no coinciden');

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        $existe = Database::fetchValue("SELECT COUNT(*) FROM usuarios WHERE id = ?", [$id]);
        if (!$existe) Response::noEncontrado('Usuario no encontrado');

        Database::update('usuarios', [
            'password'           => password_hash($password, PASSWORD_DEFAULT),
            'intentos_fallidos'  => 0,
            'bloqueado_hasta'    => null,
        ], 'id = :id', [':id' => $id]);

        Auditoria::registrar('password_reseteada', 'usuarios', $id, [
            'admin_id' => Auth::id(),
        ]);

        Response::ok(null, 'Contraseña actualizada');
        break;

    case 'cambiar_estado':
        $body = jsonBody();
        $id = (int) ($body['id'] ?? 0);

        if ($id <= 0) Response::error('ID inválido');
        if ($id === Auth::id()) {
            Response::error('No puedes desactivar tu propia cuenta');
        }

        $usuario = Database::fetchOne("SELECT * FROM usuarios WHERE id = ?", [$id]);
        if (!$usuario) Response::noEncontrado('Usuario no encontrado');

        $nuevoEstado = $usuario['activo'] ? 0 : 1;

        if ($usuario['rol_id'] == 1 && $nuevoEstado === 0) {
            $otros = (int) Database::fetchValue(
                "SELECT COUNT(*) FROM usuarios WHERE rol_id = 1 AND activo = 1 AND id != ?",
                [$id]
            );
            if ($otros === 0) {
                Response::error('Debe existir al menos un administrador activo');
            }
        }

        Database::update('usuarios', ['activo' => $nuevoEstado], 'id = :id', [':id' => $id]);

        Auditoria::registrar($nuevoEstado ? 'usuario_activado' : 'usuario_desactivado',
            'usuarios', $id, null);

        Response::ok(['activo' => $nuevoEstado],
            $nuevoEstado ? 'Usuario activado' : 'Usuario desactivado');
        break;

    case 'puntos_venta':
        $pvs = Database::fetchAll("
            SELECT id, nombre FROM puntos_venta WHERE activo = 1 AND es_almacen = 0 ORDER BY nombre
        ");
        Response::ok($pvs);
        break;

    case 'roles':
        $roles = Database::fetchAll("SELECT id, nombre FROM roles ORDER BY id");
        Response::ok($roles);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}