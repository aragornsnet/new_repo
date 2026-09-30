<?php
/**
 * Reporte: Turnos cerrados (con descuadres)
 */

function generar_turnos($formato)
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $estado = trim($_GET['estado_turno'] ?? 'cerrado');

    $condiciones = ['DATE(t.fecha_apertura) >= :desde', 'DATE(t.fecha_apertura) <= :hasta'];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($estado === 'abierto' || $estado === 'cerrado') {
        $condiciones[] = 't.estado = :estado';
        $params[':estado'] = $estado;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT 
            t.id, t.fecha_apertura, t.fecha_cierre,
            t.monto_inicial, t.monto_final_efectivo, t.total_ventas,
            t.total_descuadres, t.estado, t.cierre_con_conteo, t.forzado,
            pv.nombre AS pv,
            u.nombre AS vendedor
        FROM turnos t
        JOIN puntos_venta pv ON pv.id = t.punto_venta_id
        JOIN usuarios u ON u.id = t.usuario_id
        $where
        ORDER BY t.fecha_apertura DESC
        LIMIT 1000
    ", $params);

    $titulo = 'Reporte de Turnos';
    $subtitulo = "Desde $desde hasta $hasta";

    if ($formato === 'pdf') {
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        $columnas = ['#', 'Vendedor', 'PV', 'Apertura', 'Cierre', 'Inicial', 'Total', 'Descuadre'];
        $anchos = [0.6, 2.5, 2, 2, 2, 1.8, 1.8, 1.5];
        $alineaciones = ['C', 'L', 'L', 'C', 'C', 'R', 'R', 'R'];

        $filas = [];
        foreach ($datos as $d) {
            $filas[] = [
                $d['id'],
                $d['vendedor'],
                $d['pv'],
                fecha($d['fecha_apertura'], 'd/m/Y H:i'),
                $d['fecha_cierre'] ? fecha($d['fecha_cierre'], 'd/m/Y H:i') : '—',
                moneda($d['monto_inicial']),
                moneda($d['total_ventas']),
                number_format((float)$d['total_descuadres'], 2),
            ];
        }

        $pdf->generarTabla($columnas, $filas, [
            'anchos' => $anchos,
            'alineaciones' => $alineaciones,
        ]);

        $totalDesc = array_sum(array_column($datos, 'total_descuadres'));
        $pdf->bloqueTotales([
            'Total turnos' => count($datos),
            'Suma de descuadres' => number_format($totalDesc, 2),
        ]);

        $pdf->Output('turnos_' . date('Ymd_His') . '.pdf', 'I');
        exit;

    } else {
        $excel = new ExcelBase('turnos', $titulo, $subtitulo);

        $columnas = ['#', 'Vendedor', 'PV', 'Apertura', 'Cierre', 'Monto Inicial', 'Total Vendido', 'Descuadre', 'Estado', 'Forzado'];
        $filas = [];
        foreach ($datos as $d) {
            $filas[] = [
                $d['id'],
                $d['vendedor'],
                $d['pv'],
                fecha($d['fecha_apertura'], 'd/m/Y H:i'),
                $d['fecha_cierre'] ? fecha($d['fecha_cierre'], 'd/m/Y H:i') : '',
                number_format((float)$d['monto_inicial'], 2, '.', ''),
                number_format((float)$d['total_ventas'], 2, '.', ''),
                number_format((float)$d['total_descuadres'], 2, '.', ''),
                $d['estado'],
                $d['forzado'] ? 'Sí' : 'No',
            ];
        }

        $excel->generar($columnas, $filas);
    }
}