<?php
/**
 * Reporte: Ventas por punto de venta
 */

function generar_ventas_pv($formato)
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');

    $datos = Database::fetchAll("
        SELECT 
            pv.nombre AS pv,
            COUNT(DISTINCT v.id) AS num_ventas,
            COALESCE(SUM(DISTINCT v.total), 0) AS total,
            COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END), 0) AS efectivo,
            COALESCE(SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END), 0) AS transferencia
        FROM puntos_venta pv
        LEFT JOIN ventas v ON v.punto_venta_id = pv.id 
            AND v.estado = 'completada'
            AND DATE(v.fecha) >= :desde 
            AND DATE(v.fecha) <= :hasta
        LEFT JOIN pagos_venta p ON p.venta_id = v.id
        WHERE pv.activo = 1
        GROUP BY pv.id, pv.nombre
        ORDER BY total DESC
    ", [':desde' => $desde, ':hasta' => $hasta]);

    $titulo = 'Ventas por Punto de Venta';
    $subtitulo = "Desde $desde hasta $hasta";

    if ($formato === 'pdf') {
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        $columnas = ['Punto de venta', 'Ventas', 'Efectivo', 'Transferencia', 'Total'];
        $anchos = [3, 1, 2, 2, 2];
        $alineaciones = ['L', 'C', 'R', 'R', 'R'];

        $filas = [];
        foreach ($datos as $d) {
            $filas[] = [
                $d['pv'],
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
            'TOTAL GENERAL' => moneda(array_sum(array_column($datos, 'total'))),
        ]);

        $pdf->Output('ventas_pv_' . date('Ymd_His') . '.pdf', 'I');
        exit;

    } else {
        $excel = new ExcelBase('ventas_pv', $titulo, $subtitulo);

        $columnas = ['Punto de venta', 'Ventas', 'Efectivo', 'Transferencia', 'Total'];
        $filas = [];
        foreach ($datos as $d) {
            $filas[] = [
                $d['pv'],
                $d['num_ventas'],
                number_format((float)$d['efectivo'], 2, '.', ''),
                number_format((float)$d['transferencia'], 2, '.', ''),
                number_format((float)$d['total'], 2, '.', ''),
            ];
        }

        $excel->generar($columnas, $filas, [
            'totales' => [
                'Total general' => moneda(array_sum(array_column($datos, 'total'))),
            ],
        ]);
    }
}