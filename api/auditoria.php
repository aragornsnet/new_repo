<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';

ApiBootstrap::iniciar(['Administrador']);

$accion = $_GET['accion'] ?? 'listar';

switch ($accion) {

    case 'listar':
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $porPagina = min(max((int)($_GET['por_pagina'] ?? 50), 10), 200);
        $usuarioId = (int)($_GET['usuario_id'] ?? 0);
        $accionFiltro = trim($_GET['accion_filtro'] ?? '');
        $tabla = trim($_GET['tabla'] ?? '');
        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');
        $busqueda = trim($_GET['q'] ?? '');

        $condiciones = [];
        $params = [];

        if ($usuarioId > 0) {
            $condiciones[] = 'a.usuario_id = :usuario_id';
            $params[':usuario_id'] = $usuarioId;
        }
        if ($accionFiltro !== '') {
            $condiciones[] = 'a.accion = :accion';
            $params[':accion'] = $accionFiltro;
        }
        if ($tabla !== '') {
            $condiciones[] = 'a.tabla_afectada = :tabla';
            $params[':tabla'] = $tabla;
        }
        if ($desde !== '') {
            $condiciones[] = 'a.fecha >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        }
        if ($hasta !== '') {
            $condiciones[] = 'a.fecha <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }
        if ($busqueda !== '') {
            $condiciones[] = '(a.detalle LIKE :q1 OR a.accion LIKE :q2 OR a.ip LIKE :q3)';
            $params[':q1'] = "%$busqueda%";
            $params[':q2'] = "%$busqueda%";
            $params[':q3'] = "%$busqueda%";
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $total = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM auditoria a $where", $params, 0
        );

        $offset = ($pagina - 1) * $porPagina;

        $filas = Database::fetchAll("
            SELECT 
                a.id, a.accion, a.tabla_afectada, a.registro_id,
                a.detalle, a.ip, a.user_agent, a.fecha,
                u.id AS usuario_id, u.nombre AS usuario, u.email AS usuario_email
            FROM auditoria a
            LEFT JOIN usuarios u ON u.id = a.usuario_id
            $where
            ORDER BY a.fecha DESC, a.id DESC
            LIMIT $porPagina OFFSET $offset
        ", $params);

        Response::ok([
            'datos'       => $filas,
            'total'       => $total,
            'pagina'      => $pagina,
            'por_pagina'  => $porPagina,
            'total_pags'  => (int) ceil($total / $porPagina),
        ]);
        break;

    case 'obtener':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $reg = Database::fetchOne("
            SELECT a.*, u.nombre AS usuario, u.email AS usuario_email
            FROM auditoria a
            LEFT JOIN usuarios u ON u.id = a.usuario_id
            WHERE a.id = ?
        ", [$id]);

        if (!$reg) Response::noEncontrado('Registro no encontrado');
        Response::ok($reg);
        break;

    case 'catalogos':
        $acciones = Database::fetchAll("
            SELECT accion, COUNT(*) AS total FROM auditoria GROUP BY accion ORDER BY total DESC
        ");
        $tablas = Database::fetchAll("
            SELECT tabla_afectada, COUNT(*) AS total FROM auditoria 
            WHERE tabla_afectada IS NOT NULL AND tabla_afectada != ''
            GROUP BY tabla_afectada ORDER BY total DESC
        ");
        $usuarios = Database::fetchAll("
            SELECT u.id, u.nombre, COUNT(a.id) AS total
            FROM usuarios u
            LEFT JOIN auditoria a ON a.usuario_id = u.id
            GROUP BY u.id, u.nombre
            HAVING total > 0
            ORDER BY total DESC
        ");

        Response::ok([
            'acciones' => $acciones,
            'tablas'   => $tablas,
            'usuarios' => $usuarios,
        ]);
        break;

    case 'estadisticas':
        $totalAcciones = (int) Database::fetchValue("SELECT COUNT(*) FROM auditoria");
        $hoy = (int) Database::fetchValue("SELECT COUNT(*) FROM auditoria WHERE DATE(fecha) = CURDATE()");
        $semana = (int) Database::fetchValue("SELECT COUNT(*) FROM auditoria WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");

        $topAcciones = Database::fetchAll("
            SELECT accion, COUNT(*) AS total FROM auditoria
            WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY accion ORDER BY total DESC LIMIT 10
        ");

        $topUsuarios = Database::fetchAll("
            SELECT u.nombre, COUNT(a.id) AS total
            FROM auditoria a
            JOIN usuarios u ON u.id = a.usuario_id
            WHERE a.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY u.id, u.nombre ORDER BY total DESC LIMIT 5
        ");

        $actividad7dias = Database::fetchAll("
            SELECT DATE(fecha) AS dia, COUNT(*) AS total FROM auditoria
            WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(fecha) ORDER BY dia ASC
        ");

        Response::ok([
            'total_acciones'  => $totalAcciones,
            'hoy'             => $hoy,
            'semana'          => $semana,
            'top_acciones'    => $topAcciones,
            'top_usuarios'    => $topUsuarios,
            'actividad_7dias' => $actividad7dias,
        ]);
        break;

    case 'exportar':
        $usuarioId = (int)($_GET['usuario_id'] ?? 0);
        $accionFiltro = trim($_GET['accion_filtro'] ?? '');
        $tabla = trim($_GET['tabla'] ?? '');
        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');
        $busqueda = trim($_GET['q'] ?? '');

        $condiciones = [];
        $params = [];

        if ($usuarioId > 0) {
            $condiciones[] = 'a.usuario_id = :usuario_id';
            $params[':usuario_id'] = $usuarioId;
        }
        if ($accionFiltro !== '') {
            $condiciones[] = 'a.accion = :accion';
            $params[':accion'] = $accionFiltro;
        }
        if ($tabla !== '') {
            $condiciones[] = 'a.tabla_afectada = :tabla';
            $params[':tabla'] = $tabla;
        }
        if ($desde !== '') {
            $condiciones[] = 'a.fecha >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        }
        if ($hasta !== '') {
            $condiciones[] = 'a.fecha <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }
        if ($busqueda !== '') {
            $condiciones[] = '(a.detalle LIKE :q1 OR a.accion LIKE :q2)';
            $params[':q1'] = "%$busqueda%";
            $params[':q2'] = "%$busqueda%";
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $filas = Database::fetchAll("
            SELECT a.id, a.fecha, u.nombre AS usuario, a.accion,
                   a.tabla_afectada, a.registro_id, a.ip, a.detalle
            FROM auditoria a
            LEFT JOIN usuarios u ON u.id = a.usuario_id
            $where
            ORDER BY a.fecha DESC LIMIT 10000
        ", $params);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="auditoria_' . date('Ymd_His') . '.csv"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['ID', 'Fecha', 'Usuario', 'Acción', 'Tabla', 'Registro ID', 'IP', 'Detalle']);

        foreach ($filas as $f) {
            fputcsv($output, [
                $f['id'], $f['fecha'], $f['usuario'] ?? 'Sistema',
                $f['accion'], $f['tabla_afectada'] ?? '',
                $f['registro_id'] ?? '', $f['ip'] ?? '', $f['detalle'] ?? '',
            ]);
        }
        fclose($output);
        exit;

    default:
        Response::error('Acción no reconocida', 404);
}