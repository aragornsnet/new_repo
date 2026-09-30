<?php
/**
 * Reporte: Ventas por rango de fechas
 */

function generar_ventas_fechas($formato)
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $pvId = (int)($_GET['pv_id'] ?? 0);
    $vendedorId = (int)($_GET['vendedor_id'] ?? 0);

    $condiciones = ["v.estado = 'completada'", 'DATE(v.fecha) >= :desde', 'DATE(v.fecha) <= :hasta'];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($pvId > 0) {
        $condiciones[] = 'v.punto_venta_id = :pv_id';
        $params[':pv_id'] = $pvId;
    }
    if ($vendedorId > 0) {
        $condiciones[] = 'v.usuario_id = :vendedor_id';
        $params[':vendedor_id'] = $vendedorId;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $ventas = Database::fetchAll("
        SELECT 
            v.folio, v.fecha, v.total, v.moneda,
            pv.nombre AS pv,
            u.nombre AS vendedor,
            (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'efectivo') AS efectivo,
            (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'transferencia') AS transferencia
        FROM ventas v
        JOIN puntos_venta pv ON pv.id = v.punto_venta_id
        JOIN usuarios u ON u.id = v.usuario_id
        $where
        ORDER BY v.fecha DESC
    ", $params);

    $totalEfectivo = 0;
    $totalTransf = 0;
    $totalGeneral = 0;
    foreach ($ventas as $v) {
        $totalEfectivo += (float) $v['efectivo'];
        $totalTransf += (float) $v['transferencia'];
        $totalGeneral += (float) $v['total'];
    }

    $titulo = 'Reporte de Ventas';
    $subtitulo = "Desde $desde hasta $hasta · " . count($ventas) . " ventas";

    if ($formato === 'pdf') {
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        $pdf->bloqueResumen([
            ['label' => 'Total Ventas', 'valor' => count($ventas)],
            ['label' => 'Total General', 'valor' => moneda($totalGeneral)],
            ['label' => 'Efectivo', 'valor' => moneda($totalEfectivo)],
            ['label' => 'Transferencias', 'valor' => moneda($totalTransf)],
        ]);

        $columnas = ['Folio', 'Fecha', 'PV', 'Vendedor', 'Efectivo', 'Transf.', 'Total'];
        $anchos = [2, 2, 2, 2.5, 1.8, 1.8, 1.8];
        $alineaciones = ['L', 'C', 'L', 'L', 'R', 'R', 'R'];

        $filas = [];
        foreach ($ventas as $v) {
            $filas[] = [
                $v['folio'],
                fecha($v['fecha'], 'd/m/Y H:i'),
                $v['pv'],
                $v['vendedor'],
                moneda($v['efectivo']),
                moneda($v['transferencia']),
                moneda($v['total']),
            ];
        }

        $pdf->generarTabla($columnas, $filas, [
            'anchos' => $anchos,
            'alineaciones' => $alineaciones,
        ]);

        $pdf->bloqueTotales([
            'Total efectivo' => moneda($totalEfectivo),
            'Total transferencias' => moneda($totalTransf),
            'TOTAL GENERAL' => moneda($totalGeneral),
        ]);

        $pdf->Output('ventas_' . date('Ymd_His') . '.pdf', 'I');
        exit;

    } else {
        $excel = new ExcelBase('ventas', $titulo, $subtitulo);

        $columnas = ['Folio', 'Fecha', 'PV', 'Vendedor', 'Efectivo', 'Transferencia', 'Total'];
        $filas = [];
        foreach ($ventas as $v) {
            $filas[] = [
                $v['folio'],
                fecha($v['fecha'], 'd/m/Y H:i'),
                $v['pv'],
                $v['vendedor'],
                number_format((float)$v['efectivo'], 2, '.', ''),
                number_format((float)$v['transferencia'], 2, '.', ''),
                number_format((float)$v['total'], 2, '.', ''),
            ];
        }

        $excel->generar($columnas, $filas, [
            'totales' => [
                'Total efectivo' => moneda($totalEfectivo),
                'Total transferencias' => moneda($totalTransf),
                'TOTAL GENERAL' => moneda($totalGeneral),
            ],
        ]);
    }
}