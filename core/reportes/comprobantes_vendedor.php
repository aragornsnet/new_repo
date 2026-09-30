<?php
/**
 * Reporte: Comprobantes agrupados por vendedor
 */

function generar_comprobantes_vendedor($formato)
{
    $desde      = $_GET['desde'] ?? date('Y-m-01');
    $hasta      = $_GET['hasta'] ?? date('Y-m-d');
    $vendedorId = (int)($_GET['vendedor_id'] ?? 0);
    $pvId       = (int)($_GET['pv_id'] ?? 0);
    $estado     = trim($_GET['estado'] ?? '');

    $condiciones = [
        'DATE(cv.created_at) >= :desde',
        'DATE(cv.created_at) <= :hasta',
        "cv.estado = 'emitido'",
    ];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($vendedorId > 0) {
        $condiciones[] = 'cv.emitido_por = :vendedor_id';
        $params[':vendedor_id'] = $vendedorId;
    }
    if ($pvId > 0) {
        $condiciones[] = 'v.punto_venta_id = :pv_id';
        $params[':pv_id'] = $pvId;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT
            u.id AS vendedor_id,
            u.nombre AS vendedor,
            pv.nombre AS pv,
            COUNT(cv.id) AS num_comprobantes,
            COALESCE(SUM(cv.total), 0) AS total_general,
            MAX(cv.created_at) AS ultimo_comprobante
        FROM comprobantes_venta cv
        JOIN ventas v ON v.id = cv.venta_id
        JOIN usuarios u ON u.id = cv.emitido_por
        JOIN puntos_venta pv ON pv.id = v.punto_venta_id
        $where
        GROUP BY u.id, u.nombre, pv.nombre
        ORDER BY total_general DESC
    ", $params);

    $totalComprobantes = 0;
    $totalMonto = 0;

    foreach ($datos as $d) {
        $totalComprobantes += (int)$d['num_comprobantes'];
        $totalMonto += (float)$d['total_general'];
    }

    $titulo = 'Comprobantes por Vendedor';
    $subtitulo = "Desde $desde hasta $hasta · " . count($datos) . " vendedor(es)";

    if ($formato === 'pdf') {
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        $pdf->bloqueResumen([
            ['label' => 'Vendedores',    'valor' => (string)count($datos)],
            ['label' => 'Comprobantes',  'valor' => (string)$totalComprobantes],
            ['label' => 'Total',         'valor' => moneda($totalMonto)],
        ]);

        $columnas = ['#', 'Vendedor', 'Punto de venta', 'Comprobantes', 'Total', 'Último'];
        $anchos = [0.6, 3, 2.5, 1.6, 2, 2];
        $alineaciones = ['C', 'L', 'L', 'C', 'R', 'C'];

        $filas = [];
        foreach ($datos as $i => $d) {
            $filas[] = [
                (string)($i + 1),
                $d['vendedor'],
                $d['pv'],
                (string)$d['num_comprobantes'],
                moneda($d['total_general']),
                fecha($d['ultimo_comprobante'], 'd/m/Y'),
            ];
        }

        $pdf->generarTabla($columnas, $filas, [
            'anchos' => $anchos,
            'alineaciones' => $alineaciones,
        ]);

        $pdf->bloqueTotales([
            'Total comprobantes' => $totalComprobantes,
            'TOTAL MONTO'        => moneda($totalMonto),
        ]);

        $pdf->Output('comprobantes_vendedor_' . date('Ymd_His') . '.pdf', 'I');
        exit;

    } else {
        $excel = new ExcelBase('comprobantes_vendedor', $titulo, $subtitulo);

        $columnas = ['#', 'Vendedor', 'PV', 'Comprobantes', 'Total', 'Último comprobante'];
        $filas = [];
        foreach ($datos as $i => $d) {
            $filas[] = [
                $i + 1,
                $d['vendedor'],
                $d['pv'],
                (int)$d['num_comprobantes'],
                number_format((float)$d['total_general'], 2, '.', ''),
                fecha($d['ultimo_comprobante'], 'd/m/Y H:i'),
            ];
        }

        $excel->generar($columnas, $filas, [
            'totales' => [
                'Total comprobantes' => $totalComprobantes,
                'Total monto'        => moneda($totalMonto),
            ],
        ]);
    }
}