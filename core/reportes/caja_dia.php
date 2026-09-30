<?php
/**
 * Reporte: Caja del día por vendedor
 */

function generar_caja_dia($formato)
{
    $desde = $_GET['desde'] ?? date('Y-m-d');
    $hasta = $_GET['hasta'] ?? $desde;
    $pvId = (int)($_GET['pv_id'] ?? 0);

    $condiciones = ["v.estado = 'completada'", 'DATE(v.fecha) >= :desde', 'DATE(v.fecha) <= :hasta'];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($pvId > 0) {
        $condiciones[] = 'v.punto_venta_id = :pv_id';
        $params[':pv_id'] = $pvId;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT 
            u.nombre AS vendedor,
            pv.nombre AS pv,
            DATE(v.fecha) AS dia,
            SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END) AS efectivo,
            SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END) AS transferencia,
            SUM(p.monto) AS total,
            COUNT(DISTINCT v.id) AS num_ventas
        FROM ventas v
        JOIN usuarios u ON u.id = v.usuario_id
        JOIN puntos_venta pv ON pv.id = v.punto_venta_id
        JOIN pagos_venta p ON p.venta_id = v.id
        $where
        GROUP BY u.id, u.nombre, pv.nombre, DATE(v.fecha)
        ORDER BY u.nombre, dia
    ", $params);

    $titulo = 'Caja del Día';
    $subtitulo = ($desde === $hasta) ? "Día: $desde" : "Desde $desde hasta $hasta";

    if ($formato === 'pdf') {
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        $columnas = ['Vendedor', 'PV', 'Día', 'Ventas', 'Efectivo', 'Transf.', 'Total'];
        $anchos = [2.5, 2, 2, 1, 2, 2, 2];
        $alineaciones = ['L', 'L', 'C', 'C', 'R', 'R', 'R'];

        $filas = [];
        foreach ($datos as $d) {
            $filas[] = [
                $d['vendedor'],
                $d['pv'],
                fecha($d['dia'], 'd/m/Y'),
                $d['num_ventas'],
                moneda($d['efectivo']),
                moneda($d['transferencia']),
                moneda($d['total']),
            ];
        }

        $pdf->generarTabla($columnas, $filas, [
            'anchos' => $anchos,
            'alineaciones' => $alineaciones,
        ]);

        $pdf->bloqueTotales([
            'Total efectivo' => moneda(array_sum(array_column($datos, 'efectivo'))),
            'Total transferencias' => moneda(array_sum(array_column($datos, 'transferencia'))),
            'TOTAL GENERAL' => moneda(array_sum(array_column($datos, 'total'))),
        ]);

        $pdf->Output('caja_dia_' . date('Ymd_His') . '.pdf', 'I');
        exit;

    } else {
        $excel = new ExcelBase('caja_dia', $titulo, $subtitulo);

        $columnas = ['Vendedor', 'PV', 'Día', 'Ventas', 'Efectivo', 'Transferencia', 'Total'];
        $filas = [];
        foreach ($datos as $d) {
            $filas[] = [
                $d['vendedor'],
                $d['pv'],
                fecha($d['dia'], 'd/m/Y'),
                $d['num_ventas'],
                number_format((float)$d['efectivo'], 2, '.', ''),
                number_format((float)$d['transferencia'], 2, '.', ''),
                number_format((float)$d['total'], 2, '.', ''),
            ];
        }

        $excel->generar($columnas, $filas);
    }
}