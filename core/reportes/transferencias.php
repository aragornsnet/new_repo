<?php
/**
 * Reporte: Transferencias verificadas / rechazadas
 */

function generar_transferencias($formato)
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $estado = trim($_GET['estado_transf'] ?? '');

    $condiciones = ["p.metodo = 'transferencia'", 'DATE(v.fecha) >= :desde', 'DATE(v.fecha) <= :hasta'];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($estado === 'pendiente') {
        $condiciones[] = 'p.verificado = 0 AND p.motivo_rechazo IS NULL';
    } elseif ($estado === 'verificada') {
        $condiciones[] = 'p.verificado = 1';
    } elseif ($estado === 'rechazada') {
        $condiciones[] = 'p.motivo_rechazo IS NOT NULL';
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT 
            v.folio, v.fecha,
            p.metodo_detalle, p.monto, p.referencia, p.ultimos_digitos,
            p.verificado, p.fecha_verificacion, p.motivo_rechazo,
            pv.nombre AS pv,
            u.nombre AS vendedor,
            uv.nombre AS verificado_por
        FROM pagos_venta p
        JOIN ventas v ON v.id = p.venta_id
        JOIN puntos_venta pv ON pv.id = v.punto_venta_id
        JOIN usuarios u ON u.id = v.usuario_id
        LEFT JOIN usuarios uv ON uv.id = p.verificado_por
        $where
        ORDER BY v.fecha DESC
        LIMIT 2000
    ", $params);

    $titulo = 'Reporte de Transferencias';
    $subtitulo = "Desde $desde hasta $hasta";

    if ($formato === 'pdf') {
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        $columnas = ['Folio', 'Fecha', 'Vendedor', 'PV', 'Método', 'Ref.', 'Monto', 'Estado'];
        $anchos = [1.8, 2, 2, 1.8, 2, 1.8, 1.8, 1.6];
        $alineaciones = ['L', 'C', 'L', 'L', 'L', 'C', 'R', 'C'];

        $filas = [];
        foreach ($datos as $d) {
            $estadoTxt = $d['motivo_rechazo'] ? 'Rechazada' : ($d['verificado'] ? 'Verificada' : 'Pendiente');
            $filas[] = [
                $d['folio'],
                fecha($d['fecha'], 'd/m/Y'),
                $d['vendedor'],
                $d['pv'],
                $d['metodo_detalle'] ?? 'Transferencia',
                $d['referencia'] ?? '—',
                moneda($d['monto']),
                $estadoTxt,
            ];
        }

        $pdf->generarTabla($columnas, $filas, [
            'anchos' => $anchos,
            'alineaciones' => $alineaciones,
        ]);

        $totalMonto = array_sum(array_column($datos, 'monto'));
        $pdf->bloqueTotales([
            'Total transferencias' => count($datos),
            'Monto total' => moneda($totalMonto),
        ]);

        $pdf->Output('transferencias_' . date('Ymd_His') . '.pdf', 'I');
        exit;

    } else {
        $excel = new ExcelBase('transferencias', $titulo, $subtitulo);

        $columnas = ['Folio', 'Fecha', 'Vendedor', 'PV', 'Método', 'Referencia', 'Últimos 4', 'Monto', 'Estado', 'Verificado por', 'Fecha Verificación', 'Motivo Rechazo'];
        $filas = [];
        foreach ($datos as $d) {
            $estadoTxt = $d['motivo_rechazo'] ? 'Rechazada' : ($d['verificado'] ? 'Verificada' : 'Pendiente');
            $filas[] = [
                $d['folio'],
                fecha($d['fecha'], 'd/m/Y'),
                $d['vendedor'],
                $d['pv'],
                $d['metodo_detalle'] ?? '',
                $d['referencia'] ?? '',
                $d['ultimos_digitos'] ?? '',
                number_format((float)$d['monto'], 2, '.', ''),
                $estadoTxt,
                $d['verificado_por'] ?? '',
                $d['fecha_verificacion'] ? fecha($d['fecha_verificacion'], 'd/m/Y H:i') : '',
                $d['motivo_rechazo'] ?? '',
            ];
        }

        $excel->generar($columnas, $filas);
    }
}