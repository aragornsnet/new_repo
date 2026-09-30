<?php
/**
 * IPV - API de Categorías (Administrador y Almacenero)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';

ApiBootstrap::iniciar(['Administrador', 'Almacenero']);

$accion = $_GET['accion'] ?? 'listar';

switch ($accion) {

    case 'listar':
        $busqueda = trim($_GET['q'] ?? '');
        $estado = $_GET['estado'] ?? '';

        $condiciones = [];
        $params = [];

        if ($busqueda !== '') {
            $condiciones[] = '(c.nombre LIKE :q1 OR c.descripcion LIKE :q2)';
            $params[':q1'] = "%$busqueda%";
            $params[':q2'] = "%$busqueda%";
        }
        if ($estado === 'activos') {
            $condiciones[] = 'c.activo = 1';
        } elseif ($estado === 'inactivos') {
            $condiciones[] = 'c.activo = 0';
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $categorias = Database::fetchAll("
            SELECT 
                c.id, c.nombre, c.descripcion, c.activo, c.created_at,
                (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id AND p.activo = 1) AS num_productos
            FROM categorias c
            $where
            ORDER BY c.activo DESC, c.nombre ASC
        ", $params);

        Response::ok($categorias);
        break;

    case 'obtener':
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $c = Database::fetchOne("
            SELECT id, nombre, descripcion, activo, created_at
            FROM categorias WHERE id = ?
        ", [$id]);

        if (!$c) Response::noEncontrado('Categoría no encontrada');

        $c['productos'] = Database::fetchAll("
            SELECT id, nombre, precio, activo
            FROM productos
            WHERE categoria_id = ?
            ORDER BY activo DESC, nombre
        ", [$id]);

        Response::ok($c);
        break;

    case 'crear':
        $body = jsonBody();
        $nombre = trim($body['nombre'] ?? '');
        $descripcion = trim($body['descripcion'] ?? '');
        $activo = isset($body['activo']) ? (int) $body['activo'] : 1;

        $v = new Validador(['nombre' => $nombre]);
        $v->requerido('nombre', 'nombre')->min('nombre', 2)->max('nombre', 80);
        $v->max('descripcion', 150);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        $existe = Database::fetchValue(
            "SELECT COUNT(*) FROM categorias WHERE LOWER(nombre) = LOWER(?)",
            [$nombre]
        );
        if ($existe) {
            Response::validacion(['nombre' => 'Ya existe una categoría con ese nombre']);
        }

        $id = Database::insert('categorias', [
            'nombre'      => $nombre,
            'descripcion' => $descripcion ?: null,
            'activo'      => $activo,
        ]);

        Auditoria::registrar('categoria_creada', 'categorias', $id, [
            'nombre' => $nombre,
        ]);

        Response::ok(['id' => $id], 'Categoría creada');
        break;

    case 'actualizar':
        $body = jsonBody();
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $cat = Database::fetchOne("SELECT * FROM categorias WHERE id = ?", [$id]);
        if (!$cat) Response::noEncontrado('Categoría no encontrada');

        $nombre = trim($body['nombre'] ?? '');
        $descripcion = trim($body['descripcion'] ?? '');
        $activo = isset($body['activo']) ? (int) $body['activo'] : 1;

        $v = new Validador(['nombre' => $nombre]);
        $v->requerido('nombre', 'nombre')->min('nombre', 2)->max('nombre', 80);
        $v->max('descripcion', 150);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        $existe = Database::fetchValue(
            "SELECT COUNT(*) FROM categorias WHERE LOWER(nombre) = LOWER(?) AND id != ?",
            [$nombre, $id]
        );
        if ($existe) {
            Response::validacion(['nombre' => 'Ya existe otra categoría con ese nombre']);
        }

        Database::update('categorias', [
            'nombre'      => $nombre,
            'descripcion' => $descripcion ?: null,
            'activo'      => $activo,
        ], 'id = :id', [':id' => $id]);

        Auditoria::registrar('categoria_actualizada', 'categorias', $id, [
            'antes'   => ['nombre' => $cat['nombre'], 'activo' => $cat['activo']],
            'despues' => compact('nombre', 'activo'),
        ]);

        Response::ok(null, 'Categoría actualizada');
        break;

    case 'cambiar_estado':
        $body = jsonBody();
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $c = Database::fetchOne("SELECT * FROM categorias WHERE id = ?", [$id]);
        if (!$c) Response::noEncontrado('Categoría no encontrada');

        $nuevo = $c['activo'] ? 0 : 1;
        Database::update('categorias', ['activo' => $nuevo], 'id = :id', [':id' => $id]);

        Auditoria::registrar($nuevo ? 'categoria_activada' : 'categoria_desactivada',
            'categorias', $id, null);

        Response::ok(['activo' => $nuevo],
            $nuevo ? 'Categoría activada' : 'Categoría desactivada');
        break;

    case 'eliminar':
        $body = jsonBody();
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $c = Database::fetchOne("SELECT * FROM categorias WHERE id = ?", [$id]);
        if (!$c) Response::noEncontrado('Categoría no encontrada');

        $productos = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM productos WHERE categoria_id = ?",
            [$id]
        );

        if ($productos > 0) {
            Response::error(
                "No se puede eliminar: tiene {$productos} producto(s) asociado(s). " .
                "Puedes desactivarla en su lugar."
            );
        }

        Database::delete('categorias', 'id = :id', [':id' => $id]);

        Auditoria::registrar('categoria_eliminada', 'categorias', $id, [
            'nombre' => $c['nombre'],
        ]);

        Response::ok(null, 'Categoría eliminada');
        break;

    default:
        Response::error('Acción no reconocida', 404);
}