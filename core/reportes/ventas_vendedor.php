<?php
/**
 * Reporte: Ventas por vendedor
 */

function generar_ventas_vendedor($formato)
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $pvId = (int)($_GET['pv_id'] ?? 0);

    $sql = "
        SELECT 
            u.nombre AS vendedor,
            pv.nombre AS pv,
            COUNT(DISTINCT v.id) AS num_ventas,
            COALESCE(SUM(DISTINCT v.total), 0) AS total,
            COALESCE(AVG(DISTINCT v.total), 0) AS ticket_promedio,
            COALESCE(SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END), 0) AS efectivo,
            COALESCE(SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END), 0) AS transferencia
        FROM usuarios u
        JOIN puntos_venta pv ON pv.id = u.punto_venta_id
        LEFT JOIN ventas v ON v.usuario_id = u.id 
            AND v.estado = 'completada'
            AND DATE(v.fecha) >= :desde 
            AND DATE(v.fecha) <= :hasta
            " . ($pvId > 0 ? "AND v.punto_venta_id = :pv_id" : "") . "
        LEFT JOIN pagos_venta p ON p.venta_id = v.id
        WHERE u.rol_id = 3
        GROUP BY u.id, u.nombre, pv.nombre
        HAVING num_ventas > 0
        ORDER BY total DESC
    ";

    $params = [':desde' => $desde, ':hasta' => $hasta];
    if ($pvId > 0) {
        $params[':pv_id'] = $pvId;
    }

    $datos = Database::fetchAll($sql, $params);

    $titulo = 'Ventas por Vendedor';
    $subtitulo = "Desde $desde hasta $hasta";

    if ($formato === 'pdf') {
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        $columnas = ['#', 'Vendedor', 'PV', 'Ventas', 'Ticket Prom.', 'Total'];
        $anchos = [0.6, 3, 2.5, 1.2, 2, 2];
        $alineaciones = ['C', 'L', 'L', 'C', 'R', 'R'];

        $filas = [];
        foreach ($datos as $i => $d) {
            $filas[] = [
                $i + 1,
                $d['vendedor'],
                $d['pv'],
                $d['num_ventas'],
                moneda($d['ticket_promedio']),
                moneda($d['total']),
            ];
        }

        $pdf->generarTabla($columnas, $filas, [
            'anchos' => $anchos,
            'alineaciones' => $alineaciones,
        ]);

        $totalGeneral = array_sum(array_column($datos, 'total'));
        $pdf->bloqueTotales([
            'TOTAL GENERAL' => moneda($totalGeneral),
        ]);

        $pdf->Output('ventas_vendedor_' . date('Ymd_His') . '.pdf', 'I');
        exit;

    } else {
        $excel = new ExcelBase('ventas_vendedor', $titulo, $subtitulo);

        $columnas = ['#', 'Vendedor', 'PV', 'Ventas', 'Ticket Promedio', 'Total'];
        $filas = [];
        foreach ($datos as $i => $d) {
            $filas[] = [
                $i + 1,
                $d['vendedor'],
                $d['pv'],
                $d['num_ventas'],
                number_format((float)$d['ticket_promedio'], 2, '.', ''),
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