<?php
/**
 * Reporte: Mis ventas (vendedor)
 */

function generar_mis_ventas($formato)
{
    $vendedorId = Auth::id();
    $desde = $_GET['desde'] ?? date('Y-m-d');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');

    $ventas = Database::fetchAll("
        SELECT 
            v.folio, v.fecha, v.total, v.moneda,
            (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'efectivo') AS efectivo,
            (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'transferencia') AS transferencia
        FROM ventas v
        WHERE v.usuario_id = :vendedor_id
          AND v.estado = 'completada'
          AND DATE(v.fecha) >= :desde
          AND DATE(v.fecha) <= :hasta
        ORDER BY v.fecha DESC
    ", [
        ':vendedor_id' => $vendedorId,
        ':desde' => $desde,
        ':hasta' => $hasta,
    ]);

    $usuario = Auth::user();
    $titulo = 'Mis Ventas';
    $subtitulo = ($usuario['nombre'] ?? '') . " · $desde" . ($desde !== $hasta ? " al $hasta" : "");

    $totalEfectivo = array_sum(array_column($ventas, 'efectivo'));
    $totalTransf = array_sum(array_column($ventas, 'transferencia'));
    $totalGeneral = array_sum(array_column($ventas, 'total'));

    if ($formato === 'pdf') {
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        $pdf->bloqueResumen([
            ['label' => 'Ventas', 'valor' => count($ventas)],
            ['label' => 'Efectivo', 'valor' => moneda($totalEfectivo)],
            ['label' => 'Transf.', 'valor' => moneda($totalTransf)],
            ['label' => 'Total', 'valor' => moneda($totalGeneral)],
        ]);

        $columnas = ['Folio', 'Fecha', 'Efectivo', 'Transf.', 'Total'];
        $anchos = [2, 3, 2, 2, 2];
        $alineaciones = ['L', 'C', 'R', 'R', 'R'];

        $filas = [];
        foreach ($ventas as $v) {
            $filas[] = [
                $v['folio'],
                fecha($v['fecha'], 'd/m/Y H:i'),
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
            'TOTAL GENERAL' => moneda($totalGeneral),
        ]);

        $pdf->Output('mis_ventas_' . date('Ymd_His') . '.pdf', 'I');
        exit;

    } else {
        $excel = new ExcelBase('mis_ventas', $titulo, $subtitulo);

        $columnas = ['Folio', 'Fecha', 'Efectivo', 'Transferencia', 'Total', 'Moneda'];
        $filas = [];
        foreach ($ventas as $v) {
            $filas[] = [
                $v['folio'],
                fecha($v['fecha'], 'd/m/Y H:i'),
                number_format((float)$v['efectivo'], 2, '.', ''),
                number_format((float)$v['transferencia'], 2, '.', ''),
                number_format((float)$v['total'], 2, '.', ''),
                $v['moneda'],
            ];
        }

        $excel->generar($columnas, $filas, [
            'totales' => [
                'Total' => moneda($totalGeneral),
            ],
        ]);
    }
}