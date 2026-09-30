<?php
/**
 * Reporte: Facturas agrupadas por cliente
 */

function generar_facturas_cliente($formato)
{
    $desde      = $_GET['desde'] ?? date('Y-m-01');
    $hasta      = $_GET['hasta'] ?? date('Y-m-d');
    $clienteId  = (int)($_GET['cliente_id'] ?? 0);
    $vendedorId = (int)($_GET['vendedor_id'] ?? 0);
    $estado     = trim($_GET['estado'] ?? '');

    $condiciones = [
        'DATE(f.fecha_emision) >= :desde',
        'DATE(f.fecha_emision) <= :hasta',
        "f.estado != 'anulada'",
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
    if (in_array($estado, ['emitida', 'parcial', 'pagada'], true)) {
        $condiciones[] = 'f.estado = :estado';
        $params[':estado'] = $estado;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT
            c.id AS cliente_id,
            c.nombre AS cliente,
            c.nit AS cliente_nit,
            c.telefono AS cliente_telefono,
            c.email AS cliente_email,
            COUNT(f.id) AS num_facturas,
            COALESCE(SUM(f.total), 0) AS total_facturado,
            COALESCE(SUM(COALESCE((SELECT SUM(fp.monto) FROM facturas_pagos fp WHERE fp.factura_id = f.id), 0)), 0) AS total_pagado,
            MIN(f.fecha_emision) AS primera_factura,
            MAX(f.fecha_emision) AS ultima_factura
        FROM facturas f
        JOIN clientes c ON c.id = f.cliente_id
        JOIN ventas v ON v.id = f.venta_id
        $where
        GROUP BY c.id, c.nombre, c.nit, c.telefono, c.email
        ORDER BY total_facturado DESC
    ", $params);

    // Calcular saldo
    $totalFacturado = 0;
    $totalPagado    = 0;
    $totalSaldo     = 0;
    $totalFacturas  = 0;

    foreach ($datos as &$d) {
        $d['saldo'] = (float)$d['total_facturado'] - (float)$d['total_pagado'];
        $totalFacturado += (float)$d['total_facturado'];
        $totalPagado    += (float)$d['total_pagado'];
        $totalSaldo     += $d['saldo'];
        $totalFacturas  += (int)$d['num_facturas'];
    }
    unset($d);

    $titulo = 'Facturación por Cliente';
    $subtitulo = "Desde $desde hasta $hasta · " . count($datos) . " cliente(s)";

    if ($formato === 'pdf') {
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        $pdf->bloqueResumen([
            ['label' => 'Clientes',   'valor' => (string)count($datos)],
            ['label' => 'Facturas',   'valor' => (string)$totalFacturas],
            ['label' => 'Facturado',  'valor' => moneda($totalFacturado)],
            ['label' => 'Saldo',      'valor' => moneda($totalSaldo)],
        ]);

        $columnas = ['Cliente', 'NIT', 'Teléfono', 'Facturas', 'Total facturado', 'Pagado', 'Saldo', 'Última factura'];
        $anchos = [3.2, 1.8, 1.6, 1.2, 2, 1.8, 1.8, 1.8];
        $alineaciones = ['L', 'L', 'L', 'C', 'R', 'R', 'R', 'C'];

        $filas = [];
        foreach ($datos as $d) {
            $filas[] = [
                $d['cliente'],
                $d['cliente_nit'] ?: '—',
                $d['cliente_telefono'] ?: '—',
                (string)$d['num_facturas'],
                moneda($d['total_facturado']),
                moneda($d['total_pagado']),
                moneda($d['saldo']),
                fecha($d['ultima_factura'], 'd/m/Y'),
            ];
        }

        $pdf->generarTabla($columnas, $filas, [
            'anchos' => $anchos,
            'alineaciones' => $alineaciones,
        ]);

        $pdf->bloqueTotales([
            'Total facturado' => moneda($totalFacturado),
            'Total pagado'    => moneda($totalPagado),
            'SALDO PENDIENTE' => moneda($totalSaldo),
        ]);

        $pdf->Output('facturas_cliente_' . date('Ymd_His') . '.pdf', 'I');
        exit;

    } else {
        $excel = new ExcelBase('facturas_cliente', $titulo, $subtitulo);

        $columnas = ['Cliente', 'NIT', 'Teléfono', 'Email', 'Facturas', 'Total facturado', 'Pagado', 'Saldo', 'Primera factura', 'Última factura'];
        $filas = [];
        foreach ($datos as $d) {
            $filas[] = [
                $d['cliente'],
                $d['cliente_nit'] ?: '',
                $d['cliente_telefono'] ?: '',
                $d['cliente_email'] ?: '',
                (int)$d['num_facturas'],
                number_format((float)$d['total_facturado'], 2, '.', ''),
                number_format((float)$d['total_pagado'], 2, '.', ''),
                number_format($d['saldo'], 2, '.', ''),
                fecha($d['primera_factura'], 'd/m/Y'),
                fecha($d['ultima_factura'], 'd/m/Y'),
            ];
        }

        $excel->generar($columnas, $filas, [
            'totales' => [
                'Total facturado' => moneda($totalFacturado),
                'Total pagado'    => moneda($totalPagado),
                'Saldo pendiente' => moneda($totalSaldo),
            ],
        ]);
    }
}