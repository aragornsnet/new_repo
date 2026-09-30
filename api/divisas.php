<?php
/**
 * IPV - API de Divisas y Tasas de Cambio (solo Administrador)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';

ApiBootstrap::iniciar(['Administrador']);

$accion = $_GET['accion'] ?? 'listar';

switch ($accion) {

    // ============================================================
    // LISTAR divisas con su última tasa
    // ============================================================
    case 'listar':
        $divisas = Database::fetchAll("
            SELECT d.id, d.codigo, d.nombre, d.simbolo, d.activo, d.orden,
                   (SELECT tasa FROM tasas_cambio WHERE divisa_id = d.id ORDER BY fecha DESC LIMIT 1) AS tasa_actual,
                   (SELECT origen FROM tasas_cambio WHERE divisa_id = d.id ORDER BY fecha DESC LIMIT 1) AS origen,
                   (SELECT fuente FROM tasas_cambio WHERE divisa_id = d.id ORDER BY fecha DESC LIMIT 1) AS fuente,
                   (SELECT fecha FROM tasas_cambio WHERE divisa_id = d.id ORDER BY fecha DESC LIMIT 1) AS fecha_tasa
            FROM divisas d
            ORDER BY d.orden, d.codigo
        ");

        Response::ok([
            'divisas' => $divisas,
            'config' => [
                'habilitadas'      => Config::bool('divisas_habilitadas', false),
                'auto_update'      => Config::bool('divisas_auto_update', false),
                'permitir_manual'  => Config::bool('divisas_permitir_manual', true),
                'manual_horas'     => Config::int('divisas_manual_duracion_horas', 6),
                'auto_frecuencia'  => Config::get('divisas_auto_frecuencia', 'hora'),
                'auto_fuente'      => Config::get('divisas_auto_fuente', 'eltoque'),
            ],
        ]);
        break;

    // ============================================================
    // ACTUALIZAR tasa manualmente
    // ============================================================
    case 'actualizar_tasa':
        $body = jsonBody();
        $divisaId = (int)($body['divisa_id'] ?? 0);
        $tasa     = (float)($body['tasa'] ?? 0);
        $motivo   = trim($body['motivo'] ?? '');

        if ($divisaId <= 0) Response::validacion(['divisa_id' => 'Divisa requerida']);
        if ($tasa <= 0)     Response::validacion(['tasa' => 'La tasa debe ser mayor a 0']);
        if (mb_strlen($motivo) < 3) Response::validacion(['motivo' => 'El motivo es obligatorio (mínimo 3 caracteres)']);

        if (!Config::bool('divisas_permitir_manual', true)) {
            Response::prohibido('La sobrescritura manual está deshabilitada');
        }

        $divisa = Database::fetchOne("SELECT id, codigo, nombre FROM divisas WHERE id = ?", [$divisaId]);
        if (!$divisa) Response::noEncontrado('Divisa no encontrada');

        $tasaAnterior = Database::fetchValue("
            SELECT tasa FROM tasas_cambio WHERE divisa_id = ? ORDER BY fecha DESC LIMIT 1
        ", [$divisaId]);

        try {
            Database::begin();

            $tasaId = Database::insert('tasas_cambio', [
                'divisa_id'  => $divisaId,
                'tasa'       => $tasa,
                'origen'     => 'manual',
                'fuente'     => 'Manual',
                'usuario_id' => Auth::id(),
                'motivo'     => $motivo,
            ]);

            Auditoria::registrar('tasa_actualizada_manual', 'tasas_cambio', $tasaId, [
                'divisa'        => $divisa['codigo'],
                'tasa_anterior' => $tasaAnterior ? (float)$tasaAnterior : null,
                'tasa_nueva'    => $tasa,
                'motivo'        => $motivo,
            ]);

            Database::commit();

            Response::ok([
                'tasa_id'       => $tasaId,
                'tasa_anterior' => $tasaAnterior ? (float)$tasaAnterior : null,
                'tasa_nueva'    => $tasa,
            ], 'Tasa actualizada correctamente');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error actualizar tasa: ' . $e->getMessage());
            Response::servidor('No se pudo actualizar la tasa');
        }
        break;

    // ============================================================
    // HISTORIAL de tasas de una divisa
    // ============================================================
    case 'historial':
        $divisaId = (int)($_GET['divisa_id'] ?? 0);
        $limit    = min(max((int)($_GET['limit'] ?? 100), 1), 500);

        if ($divisaId <= 0) Response::error('Divisa requerida');

        $sql = "
            SELECT t.id, t.tasa, t.origen, t.fuente, t.motivo, t.fecha,
                   u.nombre AS usuario
            FROM tasas_cambio t
            LEFT JOIN usuarios u ON u.id = t.usuario_id
            WHERE t.divisa_id = :divisa_id
            ORDER BY t.fecha DESC
            LIMIT :limite
        ";

        $stmt = Database::get()->prepare($sql);
        $stmt->bindValue(':divisa_id', $divisaId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $historial = $stmt->fetchAll();

        Response::ok($historial);
        break;

    // ============================================================
    // CAMBIAR estado activo/inactivo de una divisa
    // ============================================================
    case 'cambiar_estado':
        $body = jsonBody();
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) Response::error('ID inválido');

        $d = Database::fetchOne("SELECT * FROM divisas WHERE id = ?", [$id]);
        if (!$d) Response::noEncontrado('Divisa no encontrada');

        $nuevo = $d['activo'] ? 0 : 1;
        Database::update('divisas', ['activo' => $nuevo], 'id = :id', [':id' => $id]);

        Auditoria::registrar($nuevo ? 'divisa_activada' : 'divisa_desactivada', 'divisas', $id, null);

        Response::ok(['activo' => $nuevo], $nuevo ? 'Divisa activada' : 'Divisa desactivada');
        break;

    // ============================================================
    // CATÁLOGOS
    // ============================================================
    case 'catalogos':
        Response::ok([
            'divisas' => Database::fetchAll("SELECT id, codigo, nombre, simbolo FROM divisas ORDER BY orden, codigo"),
        ]);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}