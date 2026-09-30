<?php
/**
 * Reporte: Facturas por rango de fechas
 */

function generar_facturas_fechas($formato)
{
    $desde      = $_GET['desde'] ?? date('Y-m-01');
    $hasta      = $_GET['hasta'] ?? date('Y-m-d');
    $clienteId  = (int)($_GET['cliente_id'] ?? 0);
    $vendedorId = (int)($_GET['vendedor_id'] ?? 0);
    $estado     = trim($_GET['estado'] ?? ''); // '', emitida, parcial, pagada, anulada

    $condiciones = [
        'DATE(f.fecha_emision) >= :desde',
        'DATE(f.fecha_emision) <= :hasta',
    ];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($clienteId > 0) {
        $condiciones[] = 'f.cliente_id = :cliente_id';
        $params[':cliente_id'] = $clienteId;
    }
    if ($vendedorId > 0) {
        $condiciones[] = 'v.usuario_id = :vendedor_id';
        $params[':vendedor_id'] = $vendedorId;
    }
    if (in_array($estado, ['emitida', 'parcial', 'pagada', 'anulada'], true)) {
        $condiciones[] = 'f.estado = :estado';
        $params[':estado'] = $estado;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT
            f.folio, f.fecha_emision, f.fecha_vencimiento,
            f.subtotal, f.descuento, f.impuesto, f.total,
            f.estado, f.moneda,
            c.nombre AS cliente, c.nit AS cliente_nit,
            v.folio AS venta_folio,
            pv.nombre AS pv,
            u.nombre AS vendedor,
            COALESCE((SELECT SUM(fp.monto) FROM facturas_pagos fp WHERE fp.factura_id = f.id), 0) AS total_pagado
        FROM facturas f
        JOIN clientes c ON c.id = f.cliente_id
        JOIN ventas v ON v.id = f.venta_id
        JOIN puntos_venta pv ON pv.id = v.punto_venta_id
        JOIN usuarios u ON u.id = v.usuario_id
        $where
        ORDER BY f.fecha_emision DESC
        LIMIT 5000
    ", $params);

    // Totales
    $totalGeneral   = 0;
    $totalPagado    = 0;
    $totalSaldo     = 0;
    $totalEmitidas  = 0;
    $totalAnuladas  = 0;

    foreach ($datos as $d) {
        if ($d['estado'] === 'anulada') {
            $totalAnuladas++;
            continue;
        }
        $totalEmitidas++;
        $totalGeneral += (float)$d['total'];
        $totalPagado  += (float)$d['total_pagado'];
        $totalSaldo   += ((float)$d['total'] - (float)$d['total_pagado']);
    }

    $titulo = 'Reporte de Facturación';
    $subtitulo = "Desde $desde hasta $hasta · " . count($datos) . " factura(s)";

    if ($formato === 'pdf') {
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        // Resumen
        $pdf->bloqueResumen([
            ['label' => 'Emitidas',  'valor' => (string)$totalEmitidas],
            ['label' => 'Total',     'valor' => moneda($totalGeneral)],
            ['label' => 'Pagado',    'valor' => moneda($totalPagado)],
            ['label' => 'Saldo',     'valor' => moneda($totalSaldo)],
        ]);

        $columnas = ['Folio', 'Emisión', 'Vence', 'Cliente', 'NIT', 'Venta', 'Estado', 'Total', 'Pagado', 'Saldo'];
        $anchos = [1.6, 1.6, 1.6, 3, 1.8, 1.8, 1.5, 1.5, 1.5, 1.5];
        $alineaciones = ['L', 'C', 'C', 'L', 'L', 'L', 'C', 'R', 'R', 'R'];

        $filas = [];
        foreach ($datos as $d) {
            $saldo = (float)$d['total'] - (float)$d['total_pagado'];
            $filas[] = [
                $d['folio'],
                fecha($d['fecha_emision'], 'd/m/Y'),
                $d['fecha_vencimiento'] ? fecha($d['fecha_vencimiento'], 'd/m/Y') : '—',
                $d['cliente'],
                $d['cliente_nit'] ?: '—',
                $d['venta_folio'],
                ucfirst($d['estado']),
                moneda($d['total']),
                moneda($d['total_pagado']),
                moneda($saldo),
            ];
        }

        $pdf->generarTabla($columnas, $filas, [
            'anchos' => $anchos,
            'alineaciones' => $alineaciones,
        ]);

        $pdf->bloqueTotales([
            'Facturas emitidas' => $totalEmitidas,
            'Facturas anuladas' => $totalAnuladas,
            'Total facturado'   => moneda($totalGeneral),
            'Total pagado'      => moneda($totalPagado),
            'SALDO PENDIENTE'   => moneda($totalSaldo),
        ]);

        $pdf->Output('facturas_fechas_' . date('Ymd_His') . '.pdf', 'I');
        exit;

    } else {
        $excel = new ExcelBase('facturas_fechas', $titulo, $subtitulo);

        $columnas = ['Folio', 'Emisión', 'Vence', 'Cliente', 'NIT', 'PV', 'Vendedor', 'Venta', 'Estado', 'Subtotal', 'Descuento', 'Impuesto', 'Total', 'Pagado', 'Saldo'];
        $filas = [];
        foreach ($datos as $d) {
            $saldo = (float)$d['total'] - (float)$d['total_pagado'];
            $filas[] = [
                $d['folio'],
                fecha($d['fecha_emision'], 'd/m/Y H:i'),
                $d['fecha_vencimiento'] ? fecha($d['fecha_vencimiento'], 'd/m/Y') : '',
                $d['cliente'],
                $d['cliente_nit'] ?: '',
                $d['pv'],
                $d['vendedor'],
                $d['venta_folio'],
                ucfirst($d['estado']),
                number_format((float)$d['subtotal'], 2, '.', ''),
                number_format((float)$d['descuento'], 2, '.', ''),
                number_format((float)$d['impuesto'], 2, '.', ''),
                number_format((float)$d['total'], 2, '.', ''),
                number_format((float)$d['total_pagado'], 2, '.', ''),
                number_format($saldo, 2, '.', ''),
            ];
        }

        $excel->generar($columnas, $filas, [
            'totales' => [
                'Total facturado'  => moneda($totalGeneral),
                'Total pagado'     => moneda($totalPagado),
                'Saldo pendiente'  => moneda($totalSaldo),
            ],
        ]);
    }
}