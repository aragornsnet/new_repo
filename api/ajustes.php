<?php
/**
 * IPV - API de Ajustes de inventario (Supervisor y Administrador)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';
require_once __DIR__ . '/../core/Notificacion.php';

ApiBootstrap::iniciar(['Supervisor', 'Administrador']);

$accion = $_GET['accion'] ?? 'listar';

const MOTIVOS_AJUSTE = [
    'Corrección de captura',
    'Corrección por conteo físico',
    'Error de sistema',
    'Sincronización',
    'Devolución de cliente',
    'Otro (especificar)',
];

switch ($accion) {

    case 'listar':
        $q = trim($_GET['q'] ?? '');
        $pvId = (int)($_GET['pv_id'] ?? 0);
        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');

        $condiciones = ["m.tipo = 'ajuste'"];
        $params = [];

        if ($q !== '') {
            $condiciones[] = '(p.nombre LIKE :q1 OR p.codigo_barras LIKE :q2 OR m.motivo LIKE :q3 OR m.descripcion LIKE :q4)';
            $params[':q1'] = "%$q%";
            $params[':q2'] = "%$q%";
            $params[':q3'] = "%$q%";
            $params[':q4'] = "%$q%";
        }
        if ($pvId > 0) {
            $condiciones[] = 'm.punto_venta_id = :pv_id';
            $params[':pv_id'] = $pvId;
        }
        if ($desde !== '') {
            $condiciones[] = 'm.fecha >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        } else {
            $condiciones[] = 'DATE(m.fecha) = CURDATE()';
        }
        if ($hasta !== '') {
            $condiciones[] = 'm.fecha <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        $where = 'WHERE ' . implode(' AND ', $condiciones);

        $ajustes = Database::fetchAll("
            SELECT 
                m.id, m.cantidad, m.motivo, m.descripcion, m.valor_anterior, m.valor_nuevo, m.fecha,
                p.id AS producto_id, p.nombre AS producto, p.codigo_barras,
                p.unidad_medida,
                pv.id AS pv_id, pv.nombre AS pv,
                u.id AS usuario_id, u.nombre AS usuario
            FROM movimientos m
            JOIN productos p ON p.id = m.producto_id
            JOIN puntos_venta pv ON pv.id = m.punto_venta_id
            JOIN usuarios u ON u.id = m.usuario_id
            $where
            ORDER BY m.fecha DESC
            LIMIT 500
        ", $params);

        Response::ok($ajustes);
        break;

    case 'registrar':
        $body = jsonBody();

        $productoId = (int)($body['producto_id'] ?? 0);
        $pvId = (int)($body['punto_venta_id'] ?? 0);
        $nuevoValor = (int)($body['nuevo_valor'] ?? -1);
        $motivo = trim($body['motivo'] ?? '');
        $descripcion = trim($body['descripcion'] ?? '');

        $v = new Validador([
            'producto_id' => $productoId,
            'punto_venta_id' => $pvId,
            'motivo' => $motivo,
            'descripcion' => $descripcion,
        ]);
        $v->requerido('producto_id', 'producto')->entero('producto_id')->mayorIgual('producto_id', 1);
        $v->requerido('punto_venta_id', 'punto de venta')->entero('punto_venta_id')->mayorIgual('punto_venta_id', 1);
        $v->requerido('motivo', 'motivo')->min('motivo', 3)->max('motivo', 200);
        $v->requerido('descripcion', 'descripción')->min('descripcion', 3)->max('descripcion', 255);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        if ($nuevoValor < 0) {
            Response::validacion(['nuevo_valor' => 'El nuevo valor debe ser 0 o mayor']);
        }

        if (!in_array($motivo, MOTIVOS_AJUSTE, true)) {
            Response::validacion(['motivo' => 'Motivo no válido']);
        }

        if (Auth::esSupervisor() && !Config::bool('supervisor_puede_ajustar', true)) {
            Response::prohibido('Los supervisores no pueden hacer ajustes de inventario');
        }

        $producto = Database::fetchOne("SELECT * FROM productos WHERE id = ? AND activo = 1", [$productoId]);
        if (!$producto) Response::noEncontrado('Producto no encontrado');

        $pv = Database::fetchOne("SELECT * FROM puntos_venta WHERE id = ? AND activo = 1", [$pvId]);
        if (!$pv) Response::noEncontrado('Punto de venta no encontrado');

        $stockActual = Database::fetchOne("
            SELECT stock FROM stock_punto_venta WHERE producto_id = ? AND punto_venta_id = ?
        ", [$productoId, $pvId]);

        if (!$stockActual) {
            Response::error('No hay registro de stock para este producto en este PV');
        }

        $stockAnterior = (int) $stockActual['stock'];
        $diferencia = $nuevoValor - $stockAnterior;
        $unidad = $producto['unidad_medida'] ?: 'Unidad';

        if ($diferencia === 0) {
            Response::error('El nuevo valor es igual al actual. No hay cambio que aplicar.');
        }

        try {
            Database::begin();

            Database::update('stock_punto_venta',
                ['stock' => $nuevoValor],
                'producto_id = :pid AND punto_venta_id = :pvid',
                [':pid' => $productoId, ':pvid' => $pvId]
            );

            $turnoAbierto = Database::fetchOne("
                SELECT id FROM turnos WHERE punto_venta_id = ? AND estado = 'abierto' LIMIT 1
            ", [$pvId]);
            $turnoId = $turnoAbierto ? (int)$turnoAbierto['id'] : null;

            $movId = Database::insert('movimientos', [
                'producto_id'    => $productoId,
                'punto_venta_id' => $pvId,
                'turno_id'       => $turnoId,
                'tipo'           => 'ajuste',
                'cantidad'       => $diferencia,
                'motivo'         => $motivo,
                'descripcion'    => $descripcion,
                'valor_anterior' => $stockAnterior,
                'valor_nuevo'    => $nuevoValor,
                'usuario_id'     => Auth::id(),
            ]);

            if ($turnoId) {
                $ti = Database::fetchOne("
                    SELECT id, ajustes FROM turno_inventario 
                    WHERE turno_id = ? AND producto_id = ?
                ", [$turnoId, $productoId]);

                if ($ti) {
                    Database::update('turno_inventario',
                        ['ajustes' => (int)$ti['ajustes'] + $diferencia],
                        'id = :id',
                        [':id' => $ti['id']]
                    );
                }
            }

            Auditoria::registrar('ajuste_inventario', 'movimientos', $movId, [
                'producto'       => $producto['nombre'],
                'pv'             => $pv['nombre'],
                'unidad'         => $unidad,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => $nuevoValor,
                'diferencia'     => $diferencia,
                'motivo'         => $motivo,
                'descripcion'    => $descripcion,
            ]);

            if ($nuevoValor < 0) {
                Notificacion::crearParaSupervisores(
                    'stock_negativo',
                    'Stock negativo por ajuste',
                    $producto['nombre'] . ' quedó en ' . $nuevoValor . ' ' . $unidad . ' en ' . $pv['nombre'],
                    'views/inventario/index.php?filtro=negativo',
                    'x-octagon-fill',
                    'danger'
                );
            } elseif ($nuevoValor <= (int)$producto['stock_minimo']) {
                Notificacion::crearParaSupervisores(
                    'stock_bajo',
                    'Stock bajo por ajuste',
                    $producto['nombre'] . ' quedó en ' . $nuevoValor . ' ' . $unidad . ' (mínimo: ' . $producto['stock_minimo'] . ' ' . $unidad . ') en ' . $pv['nombre'],
                    'views/inventario/index.php?filtro=bajo',
                    'exclamation-triangle-fill',
                    'warning'
                );
            }

            if (abs($diferencia) >= 10) {
                Notificacion::crearParaAdmins(
                    'ajuste_grande',
                    'Ajuste de inventario grande',
                    $producto['nombre'] . ' en ' . $pv['nombre'] . ': ' . ($diferencia > 0 ? '+' : '') . $diferencia . ' ' . $unidad . ' (' . $stockAnterior . ' → ' . $nuevoValor . ')',
                    'views/inventario/ajustes.php',
                    'wrench-adjustable',
                    'info'
                );
            }

            Database::commit();

            Response::ok([
                'movimiento_id'  => $movId,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => $nuevoValor,
                'diferencia'     => $diferencia,
                'unidad_medida'  => $unidad,
            ], 'Ajuste registrado correctamente');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error ajuste: ' . $e->getMessage());
            Response::servidor('No se pudo registrar el ajuste: ' . $e->getMessage());
        }
        break;

    case 'catalogos':
        $pvs = Database::fetchAll("
            SELECT id, nombre FROM puntos_venta WHERE activo = 1 ORDER BY nombre
        ");

        Response::ok([
            'puntos_venta' => $pvs,
            'motivos' => MOTIVOS_AJUSTE,
        ]);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}