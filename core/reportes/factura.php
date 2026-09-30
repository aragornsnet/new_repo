<?php
/**
 * IPV - Generación de PDF para facturas
 * Se invoca desde api/facturas.php?accion=pdf&id=X
 */

require_once __DIR__ . '/../ReporteBase.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Config.php';
require_once __DIR__ . '/../helpers.php';

function generar_factura_pdf(int $facturaId, string $modo = 'download'): void
{
    error_log('FACTURA_PDF: id=' . $facturaId . ' modo=' . $modo);
    // Cargar factura completa
    $f = Database::fetchOne("
        SELECT
            f.*,
            c.nombre AS cliente, c.nit AS cliente_nit,
            c.direccion AS cliente_direccion, c.telefono AS cliente_telefono,
            c.email AS cliente_email, c.tipo_persona AS cliente_tipo_persona,
            c.contacto AS cliente_contacto,
            v.folio AS venta_folio, v.fecha AS venta_fecha,
            v.moneda AS venta_moneda,
            pv.nombre AS pv, pv.direccion AS pv_direccion, pv.telefono AS pv_telefono,
            u.nombre AS emitida_por_nombre,
            ua.nombre AS anulada_por_nombre
        FROM facturas f
        JOIN clientes c ON c.id = f.cliente_id
        JOIN ventas v ON v.id = f.venta_id
        JOIN puntos_venta pv ON pv.id = v.punto_venta_id
        LEFT JOIN usuarios u ON u.id = f.emitida_por
        LEFT JOIN usuarios ua ON ua.id = f.anulada_por
        WHERE f.id = ?
    ", [$facturaId]);

    if (!$f) {
        http_response_code(404);
        die('Factura no encontrada');
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
    ", [(int)$f['venta_id']]);

    // Pagos
    $pagos = Database::fetchAll("
        SELECT fp.fecha, fp.monto, fp.metodo, fp.referencia, fp.notas,
               u.nombre AS usuario
        FROM facturas_pagos fp
        LEFT JOIN usuarios u ON u.id = fp.usuario_id
        WHERE fp.factura_id = ?
        ORDER BY fp.fecha ASC
    ", [$facturaId]);

    $totalPagado = 0;
    foreach ($pagos as $p) {
        $totalPagado += (float)$p['monto'];
    }
    $saldo = (float)$f['total'] - $totalPagado;

    // ============================================================
    // Crear PDF
    // ============================================================
    $subtitulo = 'Factura ' . $f['folio'] . ' · ' . date('d/m/Y', strtotime($f['fecha_emision']));

    $pdf = new ReporteBase('FACTURA', $subtitulo);
    $pdf->AddPage();

    // ============================================================
    // Bloque: Cliente + Datos de la factura
    // ============================================================
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(15, 23, 42);

    $pdf->Cell(93, 6, 'DATOS DEL CLIENTE', 0, 0, 'L');
    $pdf->Cell(93, 6, 'DATOS DE LA FACTURA', 0, 1, 'L');

    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(51, 65, 85);

    $pdf->Cell(93, 5, 'Nombre: ' . $f['cliente'], 0, 0, 'L');
    $pdf->Cell(93, 5, 'Folio: ' . $f['folio'], 0, 1, 'L');

    $pdf->Cell(93, 5, 'NIT: ' . ($f['cliente_nit'] ?: '—'), 0, 0, 'L');
    $pdf->Cell(93, 5, 'Fecha emisión: ' . date('d/m/Y H:i', strtotime($f['fecha_emision'])), 0, 1, 'L');

    $pdf->Cell(93, 5, 'Dirección: ' . ($f['cliente_direccion'] ?: '—'), 0, 0, 'L');
    $pdf->Cell(93, 5, 'Fecha vencimiento: ' . ($f['fecha_vencimiento'] ? date('d/m/Y', strtotime($f['fecha_vencimiento'])) : '—'), 0, 1, 'L');

    $pdf->Cell(93, 5, 'Teléfono: ' . ($f['cliente_telefono'] ?: '—'), 0, 0, 'L');
    $pdf->Cell(93, 5, 'Venta vinculada: ' . $f['venta_folio'], 0, 1, 'L');

    if ($f['cliente_email']) {
        $pdf->Cell(93, 5, 'Email: ' . $f['cliente_email'], 0, 0, 'L');
    } else {
        $pdf->Cell(93, 5, '', 0, 0, 'L');
    }
    $pdf->Cell(93, 5, 'Punto de venta: ' . $f['pv'], 0, 1, 'L');

    $pdf->Cell(93, 5, '', 0, 0, 'L');
    $pdf->Cell(93, 5, 'Estado: ' . strtoupper($f['estado']), 0, 1, 'L');

    $pdf->Ln(6);

    // ============================================================
    // Bloque: Detalle (tabla de productos)
    // ============================================================
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(0, 6, 'DETALLE', 0, 1, 'L');

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
    $totales['Subtotal'] = moneda($f['subtotal']);

    if ((float)$f['descuento'] > 0) {
        $totales['Descuento'] = '- ' . moneda($f['descuento']);
    }
    if ((float)$f['impuesto'] > 0) {
        $totales['Impuesto'] = '+ ' . moneda($f['impuesto']);
    }
    $totales['TOTAL'] = moneda($f['total']);

    if ($totalPagado > 0) {
        $totales['Pagado'] = moneda($totalPagado);
    }
    if ($f['estado'] !== 'anulada') {
        $totales['SALDO PENDIENTE'] = moneda($saldo);
    }

    $pdf->bloqueTotales($totales);

    // ============================================================
    // Bloque: Pagos
    // ============================================================
    if (!empty($pagos)) {
        $pdf->Ln(6);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(0, 6, 'PAGOS REGISTRADOS', 0, 1, 'L');

        $colPagos = ['Fecha', 'Método', 'Referencia', 'Monto'];
        $anchPagos = [3, 2, 4, 2];
        $alinPagos = ['L', 'L', 'L', 'R'];

        $filasPagos = [];
        foreach ($pagos as $p) {
            $filasPagos[] = [
                date('d/m/Y', strtotime($p['fecha'])),
                ucfirst($p['metodo']),
                $p['referencia'] ?: '—',
                moneda($p['monto']),
            ];
        }

        $pdf->generarTabla($colPagos, $filasPagos, [
            'anchos' => $anchPagos,
            'alineaciones' => $alinPagos,
        ]);
    }

    // ============================================================
    // Observaciones
    // ============================================================
    if (!empty($f['observaciones'])) {
        $pdf->Ln(6);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(0, 6, 'OBSERVACIONES', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(51, 65, 85);
        $pdf->MultiCell(0, 4, $f['observaciones'], 0, 'L');
    }

    // ============================================================
    // Anulación
    // ============================================================
    if ($f['estado'] === 'anulada') {
        $pdf->Ln(6);
        $pdf->SetFillColor(254, 226, 226);
        $pdf->SetTextColor(153, 27, 27);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'FACTURA ANULADA', 1, 1, 'C', true);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, 'Motivo: ' . ($f['motivo_anulacion'] ?: '—'), 0, 1, 'L');
        $pdf->Cell(0, 5, 'Por: ' . ($f['anulada_por_nombre'] ?: '—')
            . ' · ' . ($f['fecha_anulacion'] ? date('d/m/Y H:i', strtotime($f['fecha_anulacion'])) : '—'), 0, 1, 'L');
    }

    // ============================================================
    // Footer con datos de la empresa
    // ============================================================
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(0, 4, 'Documento generado por el sistema IPV', 0, 1, 'C');

    // Salida
    $nombreArchivo = 'Factura_' . $f['folio'] . '.pdf';

    if ($modo === 'inline') {
        // Previsualización: el navegador muestra el PDF en el visor
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        // Limpiar cualquier buffer previo
        while (ob_get_level()) {
            ob_end_clean();
        }

        $pdf->Output($nombreArchivo, 'I');
    } else {
        // Descarga: fuerza la descarga del archivo
        $pdf->Output($nombreArchivo, 'D');
    }

    exit;
}