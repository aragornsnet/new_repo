<?php
/**
 * IPV - API de notificaciones
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Notificacion.php';

ApiBootstrap::iniciar([]);  // cualquier logueado

$accion    = $_GET['accion'] ?? 'listar';
$usuarioId = Auth::id();

switch ($accion) {

    case 'listar':
        $soloNoLeidas = ($_GET['no_leidas'] ?? '0') === '1';
        $limit = min(max((int)($_GET['limit'] ?? 20), 1), 100);

        $where = 'usuario_id = :uid';
        $params = [':uid' => $usuarioId];
        if ($soloNoLeidas) {
            $where .= ' AND leida = 0';
        }

        $sql = "SELECT id, tipo, titulo, mensaje, url, icono, color, leida, fecha_creacion
                FROM notificaciones
                WHERE $where
                ORDER BY fecha_creacion DESC
                LIMIT $limit";

        $notifs = Database::fetchAll($sql, $params);

        Response::ok([
            'notificaciones' => $notifs,
            'no_leidas'      => Notificacion::contarNoLeidas($usuarioId),
        ]);
        break;

    case 'contar':
        Response::ok([
            'no_leidas' => Notificacion::contarNoLeidas($usuarioId),
        ]);
        break;

    case 'marcar_leida':
        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $n = Database::fetchOne(
            "SELECT id FROM notificaciones WHERE id = ? AND usuario_id = ?",
            [$id, $usuarioId]
        );
        if (!$n) Response::noEncontrado('Notificación no encontrada');

        Database::update('notificaciones', [
            'leida'         => 1,
            'fecha_lectura' => date('Y-m-d H:i:s'),
        ], 'id = :id', [':id' => $id]);

        Response::ok(['no_leidas' => Notificacion::contarNoLeidas($usuarioId)], 'Marcada como leída');
        break;

    case 'marcar_todas_leidas':
        Database::query(
            "UPDATE notificaciones SET leida = 1, fecha_lectura = NOW()
             WHERE usuario_id = ? AND leida = 0",
            [$usuarioId]
        );
        Response::ok(['no_leidas' => 0], 'Todas marcadas como leídas');
        break;

    case 'eliminar':
        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        Database::delete('notificaciones',
            'id = :id AND usuario_id = :uid',
            [':id' => $id, ':uid' => $usuarioId]);

        Response::ok(['no_leidas' => Notificacion::contarNoLeidas($usuarioId)], 'Eliminada');
        break;

    case 'eliminar_leidas':
        Database::delete('notificaciones',
            'usuario_id = :uid AND leida = 1',
            [':uid' => $usuarioId]);
        Response::ok(null, 'Notificaciones leídas eliminadas');
        break;

    default:
        Response::error('Acción no reconocida', 404);
}