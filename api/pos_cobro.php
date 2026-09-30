<?php
/**
 * IPV - API del cobro avanzado del POS
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';
require_once __DIR__ . '/../core/Notificacion.php';

ApiBootstrap::iniciar(['Vendedor', 'Supervisor', 'Administrador']);

$accion = $_GET['accion'] ?? 'sugerir_vuelto';
$vendedorId = Auth::id();

function turnoActivo(int $vendedorId): ?array
{
    return Database::fetchOne("
        SELECT t.*, pv.nombre AS pv
        FROM turnos t
        JOIN puntos_venta pv ON pv.id = t.punto_venta_id
        WHERE t.usuario_id = ? AND t.estado = 'abierto'
        LIMIT 1
    ", [$vendedorId]);
}

switch ($accion) {

    case 'sugerir_vuelto':
        $monto = (float)($_GET['monto'] ?? 0);
        $moneda = trim($_GET['moneda'] ?? 'CUP');

        if ($monto <= 0) {
            Response::ok(['sugerencia' => [], 'total' => 0]);
            return;
        }

        $denoms = Database::fetchAll("
            SELECT valor, tipo
            FROM denominaciones
            WHERE moneda = ? AND tipo = 'billete' AND activo = 1
            ORDER BY valor DESC
        ", [$moneda]);

        if (empty($denoms)) {
            Response::ok(['sugerencia' => [], 'total' => 0]);
            return;
        }

        $restante = round($monto, 2);
        $sugerencia = [];

        foreach ($denoms as $d) {
            $valor = (float) $d['valor'];
            if ($valor <= 0) continue;

            $cantidad = (int) floor($restante / $valor);
            if ($cantidad > 0) {
                $sugerencia[] = [
                    'valor'    => $valor,
                    'tipo'     => $d['tipo'],
                    'cantidad' => $cantidad,
                    'subtotal' => $cantidad * $valor,
                ];
                $restante = round($restante - ($cantidad * $valor), 2);
            }

            if ($restante <= 0) break;
        }

        Response::ok([
            'sugerencia' => $sugerencia,
            'total'      => $monto,
            'restante'   => $restante,
        ]);
        break;

    case 'registrar':
        $turno = turnoActivo($vendedorId);
        if (!$turno) Response::error('No tienes un turno abierto', 403);

        $body = jsonBody();
        $items = $body['items'] ?? [];
        $metodoPago = trim($body['metodo_pago'] ?? 'efectivo');
        $denominacionesRecibidas = $body['denominaciones'] ?? [];
        $montoRecibidoEfectivo = (float)($body['monto_recibido_efectivo'] ?? 0);
        $montoTransferencia = (float)($body['monto_transferencia'] ?? 0);
        $datosTransferencia = $body['transferencia'] ?? [];

        $monedaVenta = trim($body['moneda'] ?? 'CUP');
        $tasaAplicada = (float)($body['tasa_aplicada'] ?? 0);
        $totalDivisa = (float)($body['total_divisa'] ?? 0);
        $montoRecibidoDivisa = (float)($body['monto_recibido_divisa'] ?? 0);

        // ⭐ Requiere factura
        $requiereFactura = !empty($body['requiere_factura']);
        $clienteId = (int)($body['cliente_id'] ?? 0);

        if (empty($items) || !is_array($items)) {
            Response::error('No hay productos en el carrito');
        }

        $itemsLimpios = [];
        foreach ($items as $item) {
            $pid = (int)($item['producto_id'] ?? 0);
            $cant = (int)($item['cantidad'] ?? 0);
            if ($pid > 0 && $cant > 0) {
                $itemsLimpios[] = ['producto_id' => $pid, 'cantidad' => $cant];
            }
        }

        if (empty($itemsLimpios)) {
            Response::error('Los productos del carrito no son válidos');
        }

        $permitirNegativo = Config::bool('pos_permitir_stock_negativo', true);
        $turnoId = (int) $turno['id'];
        $pvIdTurno = (int) $turno['punto_venta_id'];

        try {
            Database::begin();

            $productosValidos = [];
            $subtotalGeneral = 0;

            foreach ($itemsLimpios as $item) {
                $prod = Database::fetchOne("
                    SELECT p.id, p.nombre, p.precio,
                        COALESCE(spv.stock, 0) AS stock
                    FROM productos p
                    LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id AND spv.punto_venta_id = ?
                    WHERE p.id = ? AND p.activo = 1
                ", [$pvIdTurno, $item['producto_id']]);

                if (!$prod) {
                    throw new Exception("Producto #{$item['producto_id']} no encontrado");
                }

                $stockActual = (int) $prod['stock'];
                $cantidad = $item['cantidad'];

                if (!$permitirNegativo) {
                    if ($stockActual <= 0) {
                        throw new Exception("'{$prod['nombre']}' no tiene stock disponible");
                    }
                    if ($cantidad > $stockActual) {
                        throw new Exception("Stock insuficiente para '{$prod['nombre']}'. Disponible: $stockActual, solicitado: $cantidad");
                    }
                }

                $precio = (float) $prod['precio'];
                $subtotal = $precio * $cantidad;
                $subtotalGeneral += $subtotal;

                $productosValidos[] = [
                    'producto_id' => (int) $prod['id'],
                    'nombre' => $prod['nombre'],
                    'cantidad' => $cantidad,
                    'precio' => $precio,
                    'subtotal' => $subtotal,
                    'stock_actual' => $stockActual,
                ];
            }

            $total = round($subtotalGeneral, 2);

            if (!in_array($metodoPago, ['efectivo', 'transferencia', 'mixto'], true)) {
                throw new Exception('Método de pago no válido');
            }

            $esVentaDivisa = ($monedaVenta !== 'CUP');

            if ($esVentaDivisa) {
                if ($metodoPago !== 'efectivo') {
                    throw new Exception('Las ventas en divisa solo se pueden cobrar en efectivo');
                }
                if ($tasaAplicada <= 0) {
                    throw new Exception('Tasa de cambio inválida');
                }
                if ($montoRecibidoDivisa < $totalDivisa) {
                    throw new Exception("El monto recibido en divisa ({$montoRecibidoDivisa}) es menor al total ({$totalDivisa})");
                }
                $equivalenteRecibidoCup = round($montoRecibidoDivisa * $tasaAplicada, 2);
                $cambioCup = round($equivalenteRecibidoCup - $total, 2);
            } else {
                $pagoEfectivo = 0;
                $pagoTransferencia = 0;
                $cambioCup = 0;

                if ($metodoPago === 'efectivo') {
                    if (!empty($denominacionesRecibidas) && is_array($denominacionesRecibidas)) {
                        $sumaConteo = 0;
                        foreach ($denominacionesRecibidas as $d) {
                            $denomId = (int)($d['denom_id'] ?? 0);
                            $cantidad = (int)($d['cantidad'] ?? 0);
                            if ($denomId <= 0 || $cantidad <= 0) continue;

                            $denom = Database::fetchOne("
                                SELECT valor FROM denominaciones WHERE id = ? AND moneda = 'CUP'
                            ", [$denomId]);

                            if ($denom) $sumaConteo += (float)$denom['valor'] * $cantidad;
                        }
                        $pagoEfectivo = round($sumaConteo, 2);
                    } else {
                        $pagoEfectivo = round($montoRecibidoEfectivo, 2);
                    }

                    if ($pagoEfectivo < $total) {
                        throw new Exception("El efectivo recibido ({$pagoEfectivo}) es menor al total ({$total})");
                    }

                    $cambioCup = round($pagoEfectivo - $total, 2);

                } elseif ($metodoPago === 'transferencia') {
                    $pagoTransferencia = $total;

                    if (empty($datosTransferencia['referencia'])) {
                        throw new Exception('Referencia de transferencia requerida');
                    }

                    $configComprobante = Config::get('transf_comprobante_adjunto', 'opcional');
                    if ($configComprobante === 'obligatorio' && empty($datosTransferencia['comprobante'])) {
                        throw new Exception('Debes adjuntar el comprobante de la transferencia');
                    }

                } elseif ($metodoPago === 'mixto') {
                    $pagoEfectivo = round($montoRecibidoEfectivo, 2);
                    $pagoTransferencia = round($montoTransferencia, 2);

                    if ($pagoEfectivo <= 0 && $pagoTransferencia <= 0) {
                        throw new Exception('Debes indicar al menos un monto');
                    }

                    if (round($pagoEfectivo + $pagoTransferencia, 2) < $total) {
                        throw new Exception("El total pagado es menor al total de la venta");
                    }

                    $cambioCup = round(max(0, $pagoEfectivo + $pagoTransferencia - $total), 2);

                    if ($cambioCup > $pagoEfectivo) {
                        throw new Exception("El vuelto no puede superar el efectivo recibido");
                    }

                    if (empty($datosTransferencia['referencia'])) {
                        throw new Exception('Referencia de transferencia requerida');
                    }

                    $configComprobante = Config::get('transf_comprobante_adjunto', 'opcional');
                    if ($configComprobante === 'obligatorio' && empty($datosTransferencia['comprobante'])) {
                        throw new Exception('Debes adjuntar el comprobante de la transferencia');
                    }
                }
            }

            // ⭐ Validar cliente si requiere factura
            if ($requiereFactura) {
                if ($clienteId <= 0) {
                    throw new Exception('Debes seleccionar un cliente para emitir la factura');
                }

                $cliExiste = Database::fetchValue(
                    "SELECT COUNT(*) FROM clientes WHERE id = ? AND activo = 1",
                    [$clienteId]
                );
                if (!$cliExiste) {
                    throw new Exception('Cliente no encontrado o inactivo');
                }
            } else {
                $clienteId = null;
            }

            $folio = generarFolio();

            $requiereComprobanteFlag = !empty($body['requiere_comprobante']);

            $ventaData = [
                'folio'                => $folio,
                'turno_id'             => $turnoId,
                'punto_venta_id'       => $pvIdTurno,
                'usuario_id'           => $vendedorId,
                'cliente_id'           => $clienteId,
                'requiere_factura'     => $requiereFactura ? 1 : 0,
                'requiere_comprobante' => $requiereComprobanteFlag ? 1 : 0,
                'subtotal'             => $total,
                'total'                => $total,
                'moneda'               => $esVentaDivisa ? $monedaVenta : 'CUP',
                'estado'               => 'completada',
            ];

            if ($esVentaDivisa) {
                $ventaData['tasa_aplicada'] = $tasaAplicada;
                $ventaData['total_divisa'] = $totalDivisa;
                $ventaData['equivalente_cup'] = $total;
            }

            $ventaId = Database::insert('ventas', $ventaData);

            foreach ($productosValidos as $prod) {
                Database::insert('detalle_ventas', [
                    'venta_id'         => $ventaId,
                    'producto_id'      => $prod['producto_id'],
                    'cantidad'         => $prod['cantidad'],
                    'precio_unitario'  => $prod['precio'],
                    'subtotal'         => $prod['subtotal'],
                ]);

                $stockNuevo = $prod['stock_actual'] - $prod['cantidad'];
                Database::query("
                    INSERT INTO stock_punto_venta (producto_id, punto_venta_id, stock)
                    VALUES (:pid, :pvid, :stock)
                    ON DUPLICATE KEY UPDATE stock = :stock2
                ", [
                    ':pid'    => $prod['producto_id'],
                    ':pvid'   => $pvIdTurno,
                    ':stock'  => $stockNuevo,
                    ':stock2' => $stockNuevo,
                ]);

                Database::query("
                    UPDATE turno_inventario 
                    SET ventas = ventas + :cantidad
                    WHERE turno_id = :turno AND producto_id = :pid
                ", [
                    ':cantidad' => $prod['cantidad'],
                    ':turno'    => $turnoId,
                    ':pid'      => $prod['producto_id'],
                ]);

                if ($stockNuevo < 0) {
                    Notificacion::crearParaSupervisores(
                        'stock_negativo',
                        'Stock negativo',
                        $prod['nombre'] . ' quedó en ' . $stockNuevo,
                        'views/inventario/index.php?filtro=negativo',
                        'x-octagon-fill',
                        'danger'
                    );
                }
            }

            $pagoEfectivoId = null;

            if ($esVentaDivisa) {
                $pagoEfectivoId = Database::insert('pagos_venta', [
                    'venta_id'      => $ventaId,
                    'metodo'        => 'efectivo',
                    'monto'         => $total,
                    'moneda'        => $monedaVenta,
                    'monto_divisa'  => $totalDivisa,
                ]);
            } else {
                if (isset($pagoEfectivo) && $pagoEfectivo > 0) {
                    $efectivoParaVenta = min($pagoEfectivo, $total);
                    $pagoEfectivoId = Database::insert('pagos_venta', [
                        'venta_id'  => $ventaId,
                        'metodo'    => 'efectivo',
                        'monto'     => $efectivoParaVenta,
                        'moneda'    => 'CUP',
                    ]);
                }

                if (isset($pagoTransferencia) && $pagoTransferencia > 0) {
                    Database::insert('pagos_venta', [
                        'venta_id'        => $ventaId,
                        'metodo'          => 'transferencia',
                        'metodo_detalle'  => $datosTransferencia['metodo_detalle'] ?? 'Transferencia',
                        'monto'           => $pagoTransferencia,
                        'moneda'          => 'CUP',
                        'referencia'      => $datosTransferencia['referencia'] ?? null,
                        'ultimos_digitos' => $datosTransferencia['ultimos_digitos'] ?? null,
                        'titular'         => $datosTransferencia['titular'] ?? null,
                        'banco'           => $datosTransferencia['banco'] ?? null,
                        'comprobante'     => $datosTransferencia['comprobante'] ?? null,
                    ]);

                    Notificacion::crearParaSupervisores(
                        'transferencia_pendiente',
                        'Nueva transferencia pendiente',
                        'Venta ' . $folio . ' - ' . number_format($pagoTransferencia, 2),
                        'views/supervisor/transferencias.php',
                        'bank',
                        'warning'
                    );
                }
            }

            // Denominaciones recibidas (solo CUP)
            if (!$esVentaDivisa && !empty($denominacionesRecibidas) && is_array($denominacionesRecibidas) && $pagoEfectivoId) {
                foreach ($denominacionesRecibidas as $d) {
                    $denomId = (int)($d['denom_id'] ?? 0);
                    $cantidad = (int)($d['cantidad'] ?? 0);
                    if ($denomId <= 0 || $cantidad <= 0) continue;

                    $denom = Database::fetchOne("SELECT valor FROM denominaciones WHERE id = ?", [$denomId]);
                    if (!$denom) continue;

                    Database::insert('venta_denominaciones', [
                        'venta_id'         => $ventaId,
                        'pago_id'          => $pagoEfectivoId,
                        'denominacion_id'  => $denomId,
                        'cantidad'         => $cantidad,
                        'subtotal'         => (float)$denom['valor'] * $cantidad,
                        'tipo_movimiento'  => 'recibido',
                    ]);
                }
            }

            // Denominaciones en divisa
            $denominacionesDivisa = $body['denominaciones_divisa'] ?? [];
            if ($esVentaDivisa && !empty($denominacionesDivisa) && is_array($denominacionesDivisa) && $pagoEfectivoId) {
                foreach ($denominacionesDivisa as $d) {
                    $denomId = (int)($d['denom_id'] ?? 0);
                    $cantidad = (int)($d['cantidad'] ?? 0);
                    if ($denomId <= 0 || $cantidad <= 0) continue;

                    $denom = Database::fetchOne("SELECT valor FROM denominaciones WHERE id = ?", [$denomId]);
                    if (!$denom) continue;

                    Database::insert('venta_denominaciones', [
                        'venta_id'         => $ventaId,
                        'pago_id'          => $pagoEfectivoId,
                        'denominacion_id'  => $denomId,
                        'cantidad'         => $cantidad,
                        'subtotal'         => (float)$denom['valor'] * $cantidad,
                        'tipo_movimiento'  => 'recibido',
                    ]);
                }
            }

            // Vuelto sugerido (una sola vez)
            if ($cambioCup > 0 && $pagoEfectivoId) {
                $sugerenciaVuelto = calcularVuelto($cambioCup);
                foreach ($sugerenciaVuelto as $s) {
                    Database::insert('venta_denominaciones', [
                        'venta_id'         => $ventaId,
                        'pago_id'          => $pagoEfectivoId,
                        'denominacion_id'  => $s['denom_id'],
                        'cantidad'         => $s['cantidad'],
                        'subtotal'         => $s['subtotal'],
                        'tipo_movimiento'  => 'vuelto',
                    ]);
                }
            }

            Database::query("
                UPDATE turnos 
                SET total_ventas = total_ventas + :total 
                WHERE id = :turno
            ", [':total' => $total, ':turno' => $turnoId]);

            // ⭐ Emitir comprobante si se pidió
            $requiereComprobante = !empty($body['requiere_comprobante']);
            $comprobanteFolio = null;

            if ($requiereComprobante) {
                $nombreComprador = trim($body['nombre_comprador'] ?? '');
                $documentoComprador = trim($body['documento_comprador'] ?? '');
                $telefonoComprador = trim($body['telefono_comprador'] ?? '');
                $direccionComprador = trim($body['direccion_comprador'] ?? '');

                // Validar longitudes
                if (mb_strlen($nombreComprador) > 150) $nombreComprador = mb_substr($nombreComprador, 0, 150);
                if (mb_strlen($documentoComprador) > 50) $documentoComprador = mb_substr($documentoComprador, 0, 50);
                if (mb_strlen($telefonoComprador) > 50) $telefonoComprador = mb_substr($telefonoComprador, 0, 50);
                if (mb_strlen($direccionComprador) > 255) $direccionComprador = mb_substr($direccionComprador, 0, 255);

                $comprobanteFolio = generarFolioComprobante();

                Database::insert('comprobantes_venta', [
                    'folio'               => $comprobanteFolio,
                    'venta_id'            => $ventaId,
                    'nombre_comprador'    => $nombreComprador ?: null,
                    'documento_comprador' => $documentoComprador ?: null,
                    'telefono_comprador'  => $telefonoComprador ?: null,
                    'direccion_comprador' => $direccionComprador ?: null,
                    'total'               => $total,
                    'moneda'              => $esVentaDivisa ? $monedaVenta : 'CUP',
                    'estado'              => 'emitido',
                    'emitido_por'         => $vendedorId,
                ]);
            }

            Auditoria::registrar('venta_creada', 'ventas', $ventaId, [
                'folio'            => $folio,
                'total_cup'        => $total,
                'moneda'           => $esVentaDivisa ? $monedaVenta : 'CUP',
                'total_divisa'     => $esVentaDivisa ? $totalDivisa : null,
                'tasa'             => $esVentaDivisa ? $tasaAplicada : null,
                'metodo'           => $metodoPago,
                'cambio_cup'       => $cambioCup,
                'num_items'        => count($productosValidos),
                'turno_id'         => $turnoId,
                'requiere_factura' => $requiereFactura,
                'cliente_id'       => $clienteId,
            ]);

            // ⭐ Notificar al Admin/Supervisor si la venta requiere factura
            if ($requiereFactura && $clienteId) {
                $clienteNombre = Database::fetchValue(
                    "SELECT nombre FROM clientes WHERE id = ?",
                    [$clienteId]
                );

                Notificacion::crearParaRol(
                    2,
                    'venta_requiere_factura',
                    'Venta pendiente de facturar',
                    $folio . ' · ' . $clienteNombre . ' · ' . number_format($total, 2),
                    'views/admin/facturas.php',
                    'receipt',
                    'info'
                );

                Notificacion::crearParaAdmins(
                    'venta_requiere_factura',
                    'Venta pendiente de facturar',
                    $folio . ' · ' . $clienteNombre . ' · ' . number_format($total, 2),
                    'views/admin/facturas.php',
                    'receipt',
                    'info'
                );
            }

            Database::commit();

            $sugerenciaVuelto = $cambioCup > 0 ? calcularVuelto($cambioCup) : [];

            Response::ok([
                'venta_id'         => $ventaId,
                'folio'            => $folio,
                'total'            => $total,
                'moneda'           => $esVentaDivisa ? $monedaVenta : 'CUP',
                'total_divisa'     => $esVentaDivisa ? $totalDivisa : null,
                'tasa_aplicada'    => $esVentaDivisa ? $tasaAplicada : null,
                'cambio'           => $cambioCup,
                'items'            => $productosValidos,
                'vuelto_sugerido'  => $sugerenciaVuelto,
                'requiere_factura' => $requiereFactura,
                'comprobante_folio' => $comprobanteFolio ?? null,
            ], 'Venta registrada correctamente');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error cobro avanzado: ' . $e->getMessage());
            Response::error($e->getMessage());
        }
        break;

    default:
        Response::error('Acción no reconocida', 404);
}

function calcularVuelto(float $monto): array
{
    $denoms = Database::fetchAll("
        SELECT id, valor, tipo
        FROM denominaciones
        WHERE moneda = 'CUP' AND tipo = 'billete' AND activo = 1
        ORDER BY valor DESC
    ");

    $restante = round($monto, 2);
    $resultado = [];

    foreach ($denoms as $d) {
        $valor = (float) $d['valor'];
        if ($valor <= 0) continue;

        $cantidad = (int) floor($restante / $valor);
        if ($cantidad > 0) {
            $resultado[] = [
                'denom_id'  => (int) $d['id'],
                'valor'     => $valor,
                'tipo'      => $d['tipo'],
                'cantidad'  => $cantidad,
                'subtotal'  => $cantidad * $valor,
            ];
            $restante = round($restante - ($cantidad * $valor), 2);
        }

        if ($restante <= 0) break;
    }

    return $resultado;
}

function generarFolioComprobante(): string
{
    $anio = date('Y');
    $prefijo = 'C-' . $anio . '-';

    $ultimo = Database::fetchValue("
        SELECT folio FROM comprobantes_venta
        WHERE folio LIKE ?
        ORDER BY folio DESC
        LIMIT 1
    ", [$prefijo . '%']);

    if (!$ultimo) {
        return $prefijo . '00001';
    }

    $num = (int) substr($ultimo, strlen($prefijo));
    $num++;

    return $prefijo . str_pad($num, 5, '0', STR_PAD_LEFT);
}