<?php
/**
 * IPV - API de Turnos para Vendedor (abrir, cerrar, consultar, resumen cierre)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';
require_once __DIR__ . '/../core/Notificacion.php';

ApiBootstrap::iniciar(['Vendedor', 'Supervisor', 'Administrador']);

$accion = $_GET['accion'] ?? 'estado';
$vendedorId = Auth::id();
$pvId = Auth::puntoVentaId();

switch ($accion) {

    case 'estado':
        $turno = Database::fetchOne("
            SELECT 
                t.id, t.fecha_apertura, t.monto_inicial, t.estado,
                pv.nombre AS pv
            FROM turnos t
            JOIN puntos_venta pv ON pv.id = t.punto_venta_id
            WHERE t.usuario_id = ? AND t.estado = 'abierto'
            ORDER BY t.fecha_apertura DESC
            LIMIT 1
        ", [$vendedorId]);

        Response::ok(['turno' => $turno]);
        break;

    case 'abrir':
        if (!$pvId) {
            Response::error('No tienes un punto de venta asignado. Contacta al administrador.');
        }

        $turnoAbierto = Database::fetchOne("
            SELECT id FROM turnos 
            WHERE usuario_id = ? AND estado = 'abierto'
            LIMIT 1
        ", [$vendedorId]);

        if ($turnoAbierto) {
            Response::error('Ya tienes un turno abierto. Debes cerrarlo antes de abrir uno nuevo.');
        }

        $turnoOtroVendedor = Database::fetchOne("
            SELECT t.id, u.nombre AS vendedor
            FROM turnos t
            JOIN usuarios u ON u.id = t.usuario_id
            WHERE t.punto_venta_id = ? AND t.estado = 'abierto'
            LIMIT 1
        ", [$pvId]);

        if ($turnoOtroVendedor) {
            Response::error('El punto de venta ya tiene un turno abierto por ' . $turnoOtroVendedor['vendedor'] . '. Espera a que lo cierre.');
        }

        $pv = Database::fetchOne("
            SELECT id, nombre FROM puntos_venta WHERE id = ? AND activo = 1
        ", [$pvId]);

        if (!$pv) {
            Response::error('El punto de venta no existe o está inactivo');
        }

        $body = jsonBody();
        $montoInicial = (float)($body['monto_inicial'] ?? 0);
        $observaciones = trim($body['observaciones'] ?? '');

        $v = new Validador(['monto_inicial' => $montoInicial]);
        $v->decimal('monto_inicial')->mayorIgual('monto_inicial', 0);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        try {
            Database::begin();

            $turnoId = Database::insert('turnos', [
                'punto_venta_id'  => $pvId,
                'usuario_id'      => $vendedorId,
                'fecha_apertura'  => date('Y-m-d H:i:s'),
                'monto_inicial'   => $montoInicial,
                'estado'          => 'abierto',
                'observaciones'   => $observaciones ?: null,
            ]);

            $productos = Database::fetchAll("
                SELECT spv.producto_id, spv.stock
                FROM stock_punto_venta spv
                JOIN productos p ON p.id = spv.producto_id
                WHERE spv.punto_venta_id = ? AND p.activo = 1
            ", [$pvId]);

            foreach ($productos as $p) {
                Database::insert('turno_inventario', [
                    'turno_id'           => $turnoId,
                    'producto_id'        => $p['producto_id'],
                    'existencia_inicial' => (int) $p['stock'],
                    'entradas'           => 0,
                    'ventas'             => 0,
                    'bajas'              => 0,
                    'ajustes'            => 0,
                ]);
            }

            Auditoria::registrar('turno_abierto', 'turnos', $turnoId, [
                'pv'            => $pv['nombre'],
                'monto_inicial' => $montoInicial,
                'productos'     => count($productos),
                'observaciones' => $observaciones,
            ]);

            Database::commit();

            Response::ok([
                'turno_id' => $turnoId,
                'productos_cargados' => count($productos),
            ], 'Turno abierto correctamente');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error abrir turno: ' . $e->getMessage());
            Response::servidor('No se pudo abrir el turno: ' . $e->getMessage());
        }
        break;

    case 'resumen_cierre':
        $turno = Database::fetchOne("
            SELECT t.*, pv.nombre AS pv
            FROM turnos t
            JOIN puntos_venta pv ON pv.id = t.punto_venta_id
            WHERE t.usuario_id = ? AND t.estado = 'abierto'
            LIMIT 1
        ", [$vendedorId]);

        if (!$turno) Response::error('No tienes un turno abierto');

        $turnoId = (int) $turno['id'];

        $caja = Database::fetchOne("
            SELECT 
                COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END), 0) AS total_efectivo,
                COALESCE(SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END), 0) AS total_transferencia,
                COALESCE(SUM(p.monto), 0) AS total_general
            FROM pagos_venta p
            JOIN ventas v ON v.id = p.venta_id
            WHERE v.turno_id = ? AND v.estado = 'completada'
        ", [$turnoId]);

        $numVentas = (int) Database::fetchValue("
            SELECT COUNT(*) FROM ventas 
            WHERE turno_id = ? AND estado = 'completada'
        ", [$turnoId], 0);

        $ventasPorMoneda = Database::fetchAll("
            SELECT 
                v.moneda,
                COUNT(*) AS num_ventas,
                COALESCE(SUM(v.total), 0) AS total_cup,
                COALESCE(SUM(v.total_divisa), 0) AS total_divisa,
                MAX(v.tasa_aplicada) AS tasa
            FROM ventas v
            WHERE v.turno_id = ? AND v.estado = 'completada' AND v.moneda != 'CUP'
            GROUP BY v.moneda
        ", [$turnoId]);

        $efectivoTeorico = (float)$turno['monto_inicial'] + (float)$caja['total_efectivo'];

        $usdEsperado = 0;
        foreach ($ventasPorMoneda as $vm) {
            if ($vm['moneda'] === 'USD') {
                $usdEsperado = (float) $vm['total_divisa'];
                break;
            }
        }

        $denomsCup = Database::fetchAll("
            SELECT id, valor, tipo
            FROM denominaciones
            WHERE moneda = 'CUP' AND tipo = 'billete' AND activo = 1
            ORDER BY valor DESC
        ");

        $denomsUsd = Database::fetchAll("
            SELECT id, valor, tipo
            FROM denominaciones
            WHERE moneda = 'USD' AND tipo = 'billete' AND activo = 1
            ORDER BY valor DESC
        ");

        Response::ok([
            'turno' => [
                'id'             => $turnoId,
                'pv'             => $turno['pv'],
                'fecha_apertura' => $turno['fecha_apertura'],
                'monto_inicial'  => (float) $turno['monto_inicial'],
                'duracion_seg'   => time() - strtotime($turno['fecha_apertura']),
            ],
            'resumen' => [
                'num_ventas'         => $numVentas,
                'total_vendido'      => (float) $caja['total_general'],
                'total_efectivo'     => (float) $caja['total_efectivo'],
                'total_transferencia'=> (float) $caja['total_transferencia'],
                'efectivo_teorico'   => $efectivoTeorico,
                'usd_esperado'       => $usdEsperado,
            ],
            'ventas_por_moneda' => $ventasPorMoneda,
            'denominaciones_cup' => $denomsCup,
            'denominaciones_usd' => $denomsUsd,
        ]);
        break;

    case 'cerrar':
        $turno = Database::fetchOne("
            SELECT * FROM turnos 
            WHERE usuario_id = ? AND estado = 'abierto'
            LIMIT 1
        ", [$vendedorId]);

        if (!$turno) {
            Response::error('No tienes un turno abierto');
        }

        $body = jsonBody();

        $montoFinalCup = (float)($body['monto_final_cup'] ?? 0);
        $montoFinalUsd = (float)($body['monto_final_usd'] ?? 0);
        $observaciones = trim($body['observaciones'] ?? '');
        $conteoInventario = $body['conteo_inventario'] ?? null;

        $conteoCup = $body['conteo_cup'] ?? [];
        $conteoUsd = $body['conteo_usd'] ?? [];

        $turnoId = (int) $turno['id'];
        $pvIdTurno = (int) $turno['punto_venta_id'];

        try {
            Database::begin();

            $caja = Database::fetchOne("
                SELECT 
                    COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END), 0) AS total_efectivo,
                    COALESCE(SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END), 0) AS total_transferencia,
                    COALESCE(SUM(p.monto), 0) AS total_general
                FROM pagos_venta p
                JOIN ventas v ON v.id = p.venta_id
                WHERE v.turno_id = ? AND v.estado = 'completada'
            ", [$turnoId]);

            $numVentas = (int) Database::fetchValue("
                SELECT COUNT(*) FROM ventas 
                WHERE turno_id = ? AND estado = 'completada'
            ", [$turnoId], 0);

            $efectivoTeorico = (float)$turno['monto_inicial'] + (float)$caja['total_efectivo'];
            $descuadreCup = round($montoFinalCup - $efectivoTeorico, 2);

            $usdEsperado = (float) Database::fetchValue("
                SELECT COALESCE(SUM(total_divisa), 0) FROM ventas
                WHERE turno_id = ? AND estado = 'completada' AND moneda = 'USD'
            ", [$turnoId], 0);

            $descuadreUsd = round($montoFinalUsd - $usdEsperado, 2);

            $hayDescuadre = (abs($descuadreCup) > 0.01) || (abs($descuadreUsd) > 0.01);
            if ($hayDescuadre && strlen($observaciones) < 5) {
                throw new Exception('Debes indicar observaciones cuando hay descuadre (mínimo 5 caracteres)');
            }

            $totalDescuadresInv = 0;
            $cierreConConteo = 0;

            if (is_array($conteoInventario) && !empty($conteoInventario)) {
                $cierreConConteo = 1;

                foreach ($conteoInventario as $productoId => $cantidadReal) {
                    $productoId = (int) $productoId;
                    $cantidadReal = (int) $cantidadReal;

                    $ti = Database::fetchOne("
                        SELECT * FROM turno_inventario 
                        WHERE turno_id = ? AND producto_id = ?
                    ", [$turnoId, $productoId]);

                    if (!$ti) continue;

                    $teorica = (int)$ti['existencia_inicial'] 
                             + (int)$ti['entradas'] 
                             - (int)$ti['ventas'] 
                             - (int)$ti['bajas'] 
                             + (int)$ti['ajustes'];

                    $descuadre = $cantidadReal - $teorica;
                    $totalDescuadresInv += $descuadre;

                    Database::update('turno_inventario', [
                        'existencia_final' => $cantidadReal,
                        'descuadre'        => $descuadre,
                    ], 'id = :id', [':id' => $ti['id']]);

                    if ($descuadre !== 0) {
                        Database::query("
                            UPDATE stock_punto_venta 
                            SET stock = stock + :descuadre
                            WHERE producto_id = :pid AND punto_venta_id = :pvid
                        ", [
                            ':descuadre' => $descuadre,
                            ':pid' => $productoId,
                            ':pvid' => $pvIdTurno,
                        ]);

                        Database::insert('movimientos', [
                            'producto_id'    => $productoId,
                            'punto_venta_id' => $pvIdTurno,
                            'turno_id'       => $turnoId,
                            'tipo'           => 'ajuste',
                            'cantidad'       => $descuadre,
                            'motivo'         => 'Descuadre en cierre de turno',
                            'descripcion'    => "Conteo físico: $cantidadReal, Teórico: $teorica",
                            'valor_anterior' => $teorica,
                            'valor_nuevo'    => $cantidadReal,
                            'usuario_id'     => $vendedorId,
                        ]);
                    }
                }
            } else {
                $inventario = Database::fetchAll("
                    SELECT * FROM turno_inventario WHERE turno_id = ?
                ", [$turnoId]);

                foreach ($inventario as $item) {
                    $existenciaFinal = (int)$item['existencia_inicial'] 
                                     + (int)$item['entradas'] 
                                     - (int)$item['ventas'] 
                                     - (int)$item['bajas'] 
                                     + (int)$item['ajustes'];

                    Database::update('turno_inventario', [
                        'existencia_final' => $existenciaFinal,
                        'descuadre' => 0,
                    ], 'id = :id', [':id' => $item['id']]);
                }
            }

            Database::update('turnos', [
                'fecha_cierre'              => date('Y-m-d H:i:s'),
                'monto_final_efectivo'      => $montoFinalCup,
                'monto_final_transferencia' => (float)$caja['total_transferencia'],
                'total_ventas'              => (float)$caja['total_general'],
                'estado'                    => 'cerrado',
                'cierre_con_conteo'         => $cierreConConteo,
                'total_descuadres'          => $descuadreCup,
                'observaciones_cierre'      => $observaciones ?: null,
            ], 'id = :id', [':id' => $turnoId]);

            Auditoria::registrar('turno_cerrado', 'turnos', $turnoId, [
                'num_ventas'        => $numVentas,
                'total_ventas'      => (float)$caja['total_general'],
                'total_efectivo'    => (float)$caja['total_efectivo'],
                'total_transf'      => (float)$caja['total_transferencia'],
                'efectivo_teorico'  => $efectivoTeorico,
                'efectivo_contado'  => $montoFinalCup,
                'descuadre_cup'     => $descuadreCup,
                'usd_esperado'      => $usdEsperado,
                'usd_contado'       => $montoFinalUsd,
                'descuadre_usd'     => $descuadreUsd,
                'con_conteo_inv'    => $cierreConConteo,
                'descuadres_inv'    => $totalDescuadresInv,
                'conteo_cup'        => $conteoCup,
                'conteo_usd'        => $conteoUsd,
                'observaciones'     => $observaciones,
            ]);

            // ⭐ Notificar si hay descuadre
            if ($hayDescuadre) {
                $pvNombre = Database::fetchValue(
                    "SELECT nombre FROM puntos_venta WHERE id = ?",
                    [$pvIdTurno]
                );

                $detalle = [];
                if (abs($descuadreCup) > 0.01) {
                    $signo = $descuadreCup > 0 ? 'sobran' : 'faltan';
                    $detalle[] = 'CUP: ' . $signo . ' ' . number_format(abs($descuadreCup), 2);
                }
                if (abs($descuadreUsd) > 0.01) {
                    $signo = $descuadreUsd > 0 ? 'sobran' : 'faltan';
                    $detalle[] = 'USD: ' . $signo . ' ' . number_format(abs($descuadreUsd), 2);
                }

                Notificacion::crearParaSupervisores(
                    'turno_descuadre',
                    'Descuadre en cierre de turno',
                    Auth::user()['nombre'] . ' (' . $pvNombre . ') - ' . implode(', ', $detalle),
                    'views/supervisor/turnos.php',
                    'exclamation-triangle-fill',
                    'warning'
                );
            }

            Database::commit();

            Response::ok([
                'turno_id'         => $turnoId,
                'num_ventas'       => $numVentas,
                'total_ventas'     => (float)$caja['total_general'],
                'efectivo_teorico' => $efectivoTeorico,
                'efectivo_contado' => $montoFinalCup,
                'descuadre_cup'    => $descuadreCup,
                'usd_esperado'     => $usdEsperado,
                'usd_contado'      => $montoFinalUsd,
                'descuadre_usd'    => $descuadreUsd,
                'con_conteo_inv'   => $cierreConConteo,
                'descuadres_inv'   => $totalDescuadresInv,
            ], 'Turno cerrado correctamente');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error cerrar turno: ' . $e->getMessage());
            Response::error($e->getMessage());
        }
        break;

    default:
        Response::error('Acción no reconocida', 404);
}