<?php
/**
 * Reporte: Comprobantes agrupados por punto de venta
 */

function generar_comprobantes_pv($formato)
{
    $desde      = $_GET['desde'] ?? date('Y-m-01');
    $hasta      = $_GET['hasta'] ?? date('Y-m-d');
    $pvId       = (int)($_GET['pv_id'] ?? 0);

    $condiciones = [
        'DATE(cv.created_at) >= :desde',
        'DATE(cv.created_at) <= :hasta',
        "cv.estado = 'emitido'",
    ];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($pvId > 0) {
        $condiciones[] = 'v.punto_venta_id = :pv_id';
        $params[':pv_id'] = $pvId;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT
            pv.id AS pv_id,
            pv.nombre AS pv,
            pv.direccion AS direccion,
            COUNT(cv.id) AS num_comprobantes,
            COALESCE(SUM(cv.total), 0) AS total_general,
            COUNT(DISTINCT cv.emitido_por) AS num_vendedores
        FROM comprobantes_venta cv
        JOIN ventas v ON v.id = cv.venta_id
        JOIN puntos_venta pv ON pv.id = v.punto_venta_id
        $where
        GROUP BY pv.id, pv.nombre, pv.direccion
        ORDER BY total_general DESC
    ", $params);

    $totalComprobantes = 0;
    $totalMonto = 0;

    foreach ($datos as $d) {
        $totalComprobantes += (int)$d['num_comprobantes'];
        $totalMonto += (float)$d['total_general'];
    }

    $titulo = 'Comprobantes por Punto de Venta';
    $subtitulo = "Desde $desde hasta $hasta · " . count($datos) . " PV";

    if ($formato === 'pdf') {
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        $pdf->bloqueResumen([
            ['label' => 'Puntos de venta', 'valor' => (string)count($datos)],
            ['label' => 'Comprobantes',    'valor' => (string)$totalComprobantes],
            ['label' => 'Total',           'valor' => moneda($totalMonto)],
        ]);

        $columnas = ['Punto de venta', 'Dirección', 'Vendedores', 'Comprobantes', 'Total'];
        $anchos = [3, 3.5, 1.5, 2, 2];
        $alineaciones = ['L', 'L', 'C', 'C', 'R'];

        $filas = [];
        foreach ($datos as $d) {
            $filas[] = [
                $d['pv'],
                $d['direccion'] ?: '—',
                (string)$d['num_vendedores'],
                (string)$d['num_comprobantes'],
                moneda($d['total_general']),
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

        $pdf->Output('comprobantes_pv_' . date('Ymd_His') . '.pdf', 'I');
        exit;

    } else {
        $excel = new ExcelBase('comprobantes_pv', $titulo, $subtitulo);

        $columnas = ['Punto de venta', 'Dirección', 'Vendedores', 'Comprobantes', 'Total'];
        $filas = [];
        foreach ($datos as $d) {
            $filas[] = [
                $d['pv'],
                $d['direccion'] ?: '',
                (int)$d['num_vendedores'],
                (int)$d['num_comprobantes'],
                number_format((float)$d['total_general'], 2, '.', ''),
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