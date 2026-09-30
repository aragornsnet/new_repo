<?php
/**
 * IPV - Generación de PDF para ticket de venta (impresión térmica)
 * Se invoca desde api/pos_ticket.php?accion=pdf&venta_id=X
 *
 * El tamaño del papel se toma de la configuración `pos_ticket_tamano`:
 *   - 58mm  → 58x297mm (rollo térmico estándar 58mm)
 *   - 80mm  → 80x297mm (rollo térmico estándar 80mm)
 *   - A4    → tamaño A4 normal (fallback)
 */

require_once __DIR__ . '/../ReporteBase.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Config.php';
require_once __DIR__ . '/../helpers.php';

function generar_ticket_venta(int $ventaId, string $modo = 'inline'): void
{
    // Cargar venta + PV + vendedor + cliente
    $v = Database::fetchOne("
        SELECT
            v.id, v.folio, v.fecha, v.subtotal, v.total, v.moneda,
            v.tasa_aplicada, v.total_divisa, v.estado,
            v.motivo_cancelacion, v.fecha_cancelacion,
            pv.nombre AS pv, pv.direccion AS pv_direccion, pv.telefono AS pv_telefono,
            u.nombre AS vendedor,
            c.nombre AS cliente, c.nit AS cliente_nit,
            t.id AS turno_id
        FROM ventas v
        JOIN puntos_venta pv ON pv.id = v.punto_venta_id
        JOIN usuarios u ON u.id = v.usuario_id
        JOIN turnos t ON t.id = v.turno_id
        LEFT JOIN clientes c ON c.id = v.cliente_id
        WHERE v.id = ?
    ", [$ventaId]);

    if (!$v) {
        http_response_code(404);
        die('Venta no encontrada');
    }

    // Detalle
    $detalle = Database::fetchAll("
        SELECT
            dv.cantidad, dv.precio_unitario, dv.subtotal,
            p.nombre AS producto, p.codigo_barras, p.unidad_medida
        FROM detalle_ventas dv
        JOIN productos p ON p.id = dv.producto_id
        WHERE dv.venta_id = ?
        ORDER BY dv.id
    ", [$ventaId]);

    // Pagos
    $pagos = Database::fetchAll("
        SELECT metodo, metodo_detalle, monto, moneda, monto_divisa, referencia
        FROM pagos_venta
        WHERE venta_id = ?
        ORDER BY id
    ", [$ventaId]);

    // Comprobante (si existe)
    $comprobante = Database::fetchOne("
        SELECT folio, nombre_comprador, estado
        FROM comprobantes_venta
        WHERE venta_id = ?
    ", [$ventaId]);

    // Datos del negocio
    $nombreNegocio = Config::get('empresa_nombre', NEGOCIO_NOMBRE);
    $direccion     = Config::get('empresa_direccion', '');
    $telefono      = Config::get('empresa_telefono', '');
    $eslogan       = Config::get('empresa_eslogan', '');

    $simbolo       = Config::get('moneda_simbolo', '$');
    $decimales     = Config::int('moneda_decimales', 2);

    // Tamaño del papel
    $tamano = Config::get('pos_ticket_tamano', '80mm');
    $medidas = [
        '58mm' => ['ancho' => 58, 'margen' => 3],
        '80mm' => ['ancho' => 80, 'margen' => 4],
        'A4'   => ['ancho' => 210, 'margen' => 12],
    ];
    $config = $medidas[$tamano] ?? $medidas['80mm'];

    $anchoPagina = $config['ancho'];
    $margen = $config['margen'];
    $anchoUtil = $anchoPagina - ($margen * 2);

    // ============================================================
    // Crear PDF
    // ============================================================
    require_once __DIR__ . '/../public/libs/tcpdf/tcpdf.php';

    $pdf = new TCPDF('P', 'mm', [$anchoPagina, 297], true, 'UTF-8', false);
    $pdf->SetCreator($nombreNegocio);
    $pdf->SetAuthor($nombreNegocio);
    $pdf->SetTitle('Ticket ' . $v['folio']);

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins($margen, $margen, $margen);
    $pdf->SetAutoPageBreak(true, 5);
    $pdf->AddPage();

    // ============================================================
    // Encabezado: datos del negocio
    // ============================================================
    $pdf->SetFont('helvetica', 'B', $tamano === '58mm' ? 9 : 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($anchoUtil, 5, $nombreNegocio, 0, 1, 'C');

    if ($eslogan) {
        $pdf->SetFont('helvetica', 'I', $tamano === '58mm' ? 7 : 8);
        $pdf->Cell($anchoUtil, 3, $eslogan, 0, 1, 'C');
    }

    if ($direccion) {
        $pdf->SetFont('helvetica', '', $tamano === '58mm' ? 7 : 8);
        $pdf->MultiCell($anchoUtil, 3, $direccion, 0, 'C');
    }

    if ($telefono) {
        $pdf->SetFont('helvetica', '', $tamano === '58mm' ? 7 : 8);
        $pdf->Cell($anchoUtil, 3, 'Tel: ' . $telefono, 0, 1, 'C');
    }

    $pdf->Ln(1);

    // Línea separadora
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.2);
    $pdf->Line($margen, $pdf->GetY(), $anchoPagina - $margen, $pdf->GetY());
    $pdf->Ln(2);

    // ============================================================
    // Info del ticket
    // ============================================================
    $pdf->SetFont('helvetica', '', $tamano === '58mm' ? 7 : 8);

    $pdf->Cell($anchoUtil, 3, 'Folio: ' . $v['folio'], 0, 1, 'L');
    $pdf->Cell($anchoUtil, 3, 'Fecha: ' . fecha($v['fecha'], 'd/m/Y H:i'), 0, 1, 'L');
    $pdf->Cell($anchoUtil, 3, 'Vendedor: ' . $v['vendedor'], 0, 1, 'L');

    if ($v['cliente']) {
        $pdf->Cell($anchoUtil, 3, 'Cliente: ' . $v['cliente'], 0, 1, 'L');
        if ($v['cliente_nit']) {
            $pdf->Cell($anchoUtil, 3, 'NIT: ' . $v['cliente_nit'], 0, 1, 'L');
        }
    }

    if ($comprobante) {
        $pdf->SetFont('helvetica', 'B', $tamano === '58mm' ? 7 : 8);
        $pdf->Cell($anchoUtil, 3, 'Comprobante: ' . $comprobante['folio'], 0, 1, 'L');
        if ($comprobante['nombre_comprador']) {
            $pdf->SetFont('helvetica', '', $tamano === '58mm' ? 7 : 8);
            $pdf->Cell($anchoUtil, 3, 'Comprador: ' . $comprobante['nombre_comprador'], 0, 1, 'L');
        }
    }

    $pdf->Ln(1);
    $pdf->Line($margen, $pdf->GetY(), $anchoPagina - $margen, $pdf->GetY());
    $pdf->Ln(2);

    // ============================================================
    // Detalle de productos
    // ============================================================
    $fuenteDet = $tamano === '58mm' ? 7 : 8;

    // Anchos de columna según tamaño
    if ($tamano === '58mm') {
        $wProd = 30;
        $wCant = 8;
        $wPrecio = 12;
        $wSub = $anchoUtil - $wProd - $wCant - $wPrecio;
    } else {
        $wProd = 42;
        $wCant = 10;
        $wPrecio = 14;
        $wSub = $anchoUtil - $wProd - $wCant - $wPrecio;
    }

    // Encabezado tabla
    $pdf->SetFont('helvetica', 'B', $fuenteDet);
    $pdf->Cell($wProd, 4, 'Producto', 0, 0, 'L');
    $pdf->Cell($wCant, 4, 'Cant', 0, 0, 'C');
    $pdf->Cell($wPrecio, 4, 'Precio', 0, 0, 'R');
    $pdf->Cell($wSub, 4, 'Total', 0, 1, 'R');

    $pdf->Line($margen, $pdf->GetY(), $anchoPagina - $margen, $pdf->GetY());
    $pdf->Ln(1);

    // Filas
    $pdf->SetFont('helvetica', '', $fuenteDet);

    foreach ($detalle as $d) {
        $unidad = $d['unidad_medida'] ?: 'ud';
        $cantidadStr = $d['cantidad'] . ' ' . $unidad;

        // Producto (permite salto de línea)
        $yInicial = $pdf->GetY();
        $pdf->MultiCell($wProd, 3, $d['producto'], 0, 'L', false, 0, '', $yInicial, true, 0, false, true, 3, 'T');

        $yDespues = $pdf->GetY();

        // Cantidad, precio, subtotal en la misma línea del primer renglón
        $pdf->SetXY($margen + $wProd, $yInicial);
        $pdf->Cell($wCant, 3, $cantidadStr, 0, 0, 'C');

        $pdf->Cell($wPrecio, 3, $this_simbolo_precio($d['precio_unitario'], $simbolo, $decimales), 0, 0, 'R');
        $pdf->Cell($wSub, 3, $this_simbolo_precio($d['subtotal'], $simbolo, $decimales), 0, 1, 'R');

        // Si el producto tiene más de una línea, avanzar hasta el final
        if ($pdf->GetY() < $yDespues) {
            $pdf->SetY($yDespues);
        }
    }

    $pdf->Ln(1);
    $pdf->Line($margen, $pdf->GetY(), $anchoPagina - $margen, $pdf->GetY());
    $pdf->Ln(2);

    // ============================================================
    // Totales
    // ============================================================
    $pdf->SetFont('helvetica', '', $tamano === '58mm' ? 8 : 9);

    $pdf->Cell($anchoUtil * 0.6, 4, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell($anchoUtil * 0.4, 4, $this_simbolo_precio($v['subtotal'], $simbolo, $decimales), 0, 1, 'R');

    $pdf->SetFont('helvetica', 'B', $tamano === '58mm' ? 10 : 11);
    $pdf->Cell($anchoUtil * 0.6, 5, 'TOTAL:', 0, 0, 'R');
    $pdf->Cell($anchoUtil * 0.4, 5, $this_simbolo_precio($v['total'], $simbolo, $decimales), 0, 1, 'R');

    // Si es venta en divisa, mostrar la info
    if ($v['moneda'] && $v['moneda'] !== 'CUP' && $v['total_divisa']) {
        $pdf->SetFont('helvetica', '', $tamano === '58mm' ? 7 : 8);
        $pdf->Cell($anchoUtil, 3, 'Moneda: ' . $v['moneda'] . ' ' . number_format((float)$v['total_divisa'], 2), 0, 1, 'R');
        if ($v['tasa_aplicada']) {
            $pdf->Cell($anchoUtil, 3, 'Tasa: ' . number_format((float)$v['tasa_aplicada'], 2) . ' CUP/' . $v['moneda'], 0, 1, 'R');
        }
    }

    $pdf->Ln(2);

    // ============================================================
    // Pagos
    // ============================================================
    if (!empty($pagos)) {
        $pdf->SetFont('helvetica', 'B', $tamano === '58mm' ? 7 : 8);
        $pdf->Cell($anchoUtil, 3, 'Pagos:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', $tamano === '58mm' ? 7 : 8);

        foreach ($pagos as $p) {
            $metodoTxt = ucfirst($p['metodo']);
            if ($p['metodo_detalle']) {
                $metodoTxt = $p['metodo_detalle'];
            }

            $pdf->Cell($anchoUtil * 0.6, 3, $metodoTxt, 0, 0, 'L');
            $pdf->Cell($anchoUtil * 0.4, 3, $this_simbolo_precio($p['monto'], $simbolo, $decimales), 0, 1, 'R');
        }
    }

    // ============================================================
    // Estado cancelada
    // ============================================================
    if ($v['estado'] === 'cancelada') {
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', $tamano === '58mm' ? 9 : 10);
        $pdf->SetTextColor(220, 38, 38);
        $pdf->Cell($anchoUtil, 5, '*** VENTA CANCELADA ***', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', $tamano === '58mm' ? 7 : 8);
        $pdf->MultiCell($anchoUtil, 3, 'Motivo: ' . ($v['motivo_cancelacion'] ?: '—'), 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
    }

    // ============================================================
    // Pie
    // ============================================================
    $pdf->Ln(3);
    $pdf->Line($margen, $pdf->GetY(), $anchoPagina - $margen, $pdf->GetY());
    $pdf->Ln(2);

    $pdf->SetFont('helvetica', 'I', $tamano === '58mm' ? 7 : 8);
    $pdf->MultiCell($anchoUtil, 3, '¡Gracias por su compra!', 0, 'C');
    $pdf->Cell($anchoUtil, 3, 'Sistema IPV', 0, 1, 'C');

    // ============================================================
    // Salida
    // ============================================================
    $nombreArchivo = 'Ticket_' . $v['folio'] . '.pdf';

    while (ob_get_level()) {
        ob_end_clean();
    }

    $contenido = $pdf->Output($nombreArchivo, 'S');

    if ($modo === 'inline') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
        header('Content-Length: ' . strlen($contenido));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
    } else {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Content-Length: ' . strlen($contenido));
    }

    echo $contenido;
    exit;
}

/**
 * Helper para formatear con símbolo y decimales, sin depender de `moneda()`
 * (porque `moneda()` puede variar según config y aquí queremos un formato fijo).
 */
function this_simbolo_precio($valor, string $simbolo, int $decimales): string
{
    return $simbolo . number_format((float)$valor, $decimales, '.', ',');
}