<?php
/**
 * IPV - Generación de PDF para comprobantes de venta (minorista)
 * Se invoca desde api/comprobantes.php?accion=pdf&id=X
 */

require_once __DIR__ . '/../ReporteBase.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Config.php';
require_once __DIR__ . '/../helpers.php';

function generar_comprobante_pdf(int $comprobanteId, string $modo = 'download'): void
{
    // Cargar comprobante + venta + PV + usuario
    $c = Database::fetchOne("
        SELECT
            cv.*,
            v.folio AS venta_folio, v.fecha AS venta_fecha,
            v.moneda AS venta_moneda, v.subtotal AS venta_subtotal,
            pv.nombre AS pv, pv.direccion AS pv_direccion, pv.telefono AS pv_telefono,
            u.nombre AS emitido_por_nombre,
            ua.nombre AS anulado_por_nombre
        FROM comprobantes_venta cv
        JOIN ventas v ON v.id = cv.venta_id
        JOIN puntos_venta pv ON pv.id = v.punto_venta_id
        LEFT JOIN usuarios u ON u.id = cv.emitido_por
        LEFT JOIN usuarios ua ON ua.id = cv.anulado_por
        WHERE cv.id = ?
    ", [$comprobanteId]);

    if (!$c) {
        http_response_code(404);
        die('Comprobante no encontrado');
    }

    // Detalle de la venta
    $detalle = Database::fetchAll("
        SELECT
            dv.cantidad, dv.precio_unitario, dv.subtotal,
            p.nombre AS producto, p.codigo_barras, p.unidad_medida
        FROM detalle_ventas dv
        JOIN productos p ON p.id = dv.producto_id
        WHERE dv.venta_id = ?
        ORDER BY dv.id
    ", [(int)$c['venta_id']]);

    // ============================================================
    // Crear PDF
    // ============================================================
    $subtitulo = 'Comprobante ' . $c['folio'] . ' · ' . date('d/m/Y H:i', strtotime($c['created_at']));

    $pdf = new ReporteBase('COMPROBANTE DE VENTA', $subtitulo);
    $pdf->AddPage();

    // ============================================================
    // Bloque: Comprador + Datos del comprobante
    // ============================================================
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(15, 23, 42);

    $pdf->Cell(93, 6, 'DATOS DEL COMPRADOR', 0, 0, 'L');
    $pdf->Cell(93, 6, 'DATOS DEL COMPROBANTE', 0, 1, 'L');

    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(51, 65, 85);

    $nombreComprador = $c['nombre_comprador'] ?: 'Consumidor final';
    $pdf->Cell(93, 5, 'Nombre: ' . $nombreComprador, 0, 0, 'L');
    $pdf->Cell(93, 5, 'Folio: ' . $c['folio'], 0, 1, 'L');

    $pdf->Cell(93, 5, 'Documento: ' . ($c['documento_comprador'] ?: '—'), 0, 0, 'L');
    $pdf->Cell(93, 5, 'Fecha emisión: ' . date('d/m/Y H:i', strtotime($c['created_at'])), 0, 1, 'L');

    $pdf->Cell(93, 5, 'Teléfono: ' . ($c['telefono_comprador'] ?: '—'), 0, 0, 'L');
    $pdf->Cell(93, 5, 'Venta vinculada: ' . $c['venta_folio'], 0, 1, 'L');

    $pdf->Cell(93, 5, 'Dirección: ' . ($c['direccion_comprador'] ?: '—'), 0, 0, 'L');
    $pdf->Cell(93, 5, 'Punto de venta: ' . $c['pv'], 0, 1, 'L');

    $pdf->Cell(93, 5, '', 0, 0, 'L');
    $pdf->Cell(93, 5, 'Estado: ' . strtoupper($c['estado']), 0, 1, 'L');

    $pdf->Ln(6);

    // ============================================================
    // Bloque: Detalle
    // ============================================================
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(0, 6, 'DETALLE DE PRODUCTOS', 0, 1, 'L');

    $columnas = ['Producto', 'Unidad', 'Cant.', 'Precio', 'Subtotal'];
    $anchos = [4, 1.2, 1, 1.6, 1.8];
    $alineaciones = ['L', 'C', 'C', 'R', 'R'];

    $filas = [];
    foreach ($detalle as $d) {
        $filas[] = [
            $d['producto'],
            $d['unidad_medida'] ?: 'Unidad',
            (string)$d['cantidad'],
            moneda($d['precio_unitario']),
            moneda($d['subtotal']),
        ];
    }

    $pdf->generarTabla($columnas, $filas, [
        'anchos' => $anchos,
        'alineaciones' => $alineaciones,
    ]);

    $pdf->Ln(4);

    // ============================================================
    // Bloque: Totales
    // ============================================================
    $totales = [];
    $totales['Subtotal'] = moneda($c['venta_subtotal']);
    $totales['TOTAL'] = moneda($c['total']);

    $pdf->bloqueTotales($totales);

    // ============================================================
    // Observaciones
    // ============================================================
    if (!empty($c['observaciones'])) {
        $pdf->Ln(6);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(0, 6, 'OBSERVACIONES', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(51, 65, 85);
        $pdf->MultiCell(0, 4, $c['observaciones'], 0, 'L');
    }

    // ============================================================
    // Anulación
    // ============================================================
    if ($c['estado'] === 'anulado') {
        $pdf->Ln(6);
        $pdf->SetFillColor(254, 226, 226);
        $pdf->SetTextColor(153, 27, 27);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'COMPROBANTE ANULADO', 1, 1, 'C', true);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, 'Motivo: ' . ($c['motivo_anulacion'] ?: '—'), 0, 1, 'L');
        $pdf->Cell(0, 5, 'Por: ' . ($c['anulado_por_nombre'] ?: '—')
            . ' · ' . ($c['fecha_anulacion'] ? date('d/m/Y H:i', strtotime($c['fecha_anulacion'])) : '—'), 0, 1, 'L');
    }

    // ============================================================
    // Footer
    // ============================================================
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(0, 4, 'Documento generado por el sistema IPV', 0, 1, 'C');
    $pdf->Cell(0, 4, 'Este documento no es una factura fiscal', 0, 1, 'C');

    // ============================================================
    // Salida (controlamos las cabeceras manualmente)
    // ============================================================
    $nombreArchivo = 'Comprobante_' . $c['folio'] . '.pdf';

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