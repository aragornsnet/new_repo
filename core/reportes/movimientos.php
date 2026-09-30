<?php
/**
 * Reporte: Movimientos de inventario
 */

function generar_movimientos($formato)
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $tipo = trim($_GET['tipo_mov'] ?? '');
    $pvId = (int)($_GET['pv_id'] ?? 0);

    $condiciones = ['DATE(m.fecha) >= :desde', 'DATE(m.fecha) <= :hasta'];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($tipo !== '') {
        $condiciones[] = 'm.tipo = :tipo';
        $params[':tipo'] = $tipo;
    }
    if ($pvId > 0) {
        $condiciones[] = 'm.punto_venta_id = :pv_id';
        $params[':pv_id'] = $pvId;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT 
            m.fecha, m.tipo, m.cantidad, m.motivo, m.descripcion,
            m.valor_anterior, m.valor_nuevo,
            p.nombre AS producto,
            p.unidad_medida,
            pv.nombre AS pv,
            u.nombre AS usuario
        FROM movimientos m
        JOIN productos p ON p.id = m.producto_id
        JOIN puntos_venta pv ON pv.id = m.punto_venta_id
        JOIN usuarios u ON u.id = m.usuario_id
        $where
        ORDER BY m.fecha DESC
        LIMIT 2000
    ", $params);

    $titulo = 'Movimientos de Inventario';
    $subtitulo = "Desde $desde hasta $hasta";

    if ($formato === 'pdf') {
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        $columnas = ['Fecha', 'Tipo', 'Producto', 'Unidad', 'PV', 'Cant.', 'Stock', 'Usuario'];
        $anchos = [2, 1.5, 2.5, 1.2, 1.8, 1.6, 2, 2];
        $alineaciones = ['C', 'C', 'L', 'C', 'L', 'C', 'C', 'L'];

        $filas = [];
        foreach ($datos as $d) {
            $unidad = $d['unidad_medida'] ?? 'Unidad';

            $filas[] = [
                fecha($d['fecha'], 'd/m/Y H:i'),
                ucfirst($d['tipo']),
                $d['producto'],
                $unidad,
                $d['pv'],
                ($d['cantidad'] > 0 ? '+' : '') . $d['cantidad'] . ' ' . $unidad,
                $d['valor_anterior'] . ' → ' . $d['valor_nuevo'],
                $d['usuario'],
            ];
        }

        $pdf->generarTabla($columnas, $filas, [
            'anchos' => $anchos,
            'alineaciones' => $alineaciones,
        ]);

        $pdf->bloqueTotales([
            'Total movimientos' => count($datos),
        ]);

        $pdf->Output('movimientos_' . date('Ymd_His') . '.pdf', 'I');
        exit;

    } else {
        $excel = new ExcelBase('movimientos', $titulo, $subtitulo);

        $columnas = ['Fecha', 'Tipo', 'Producto', 'Unidad', 'PV', 'Cantidad', 'Stock antes', 'Stock después', 'Motivo', 'Usuario'];
        $filas = [];
        foreach ($datos as $d) {
            $unidad = $d['unidad_medida'] ?? 'Unidad';

            $filas[] = [
                fecha($d['fecha'], 'd/m/Y H:i'),
                ucfirst($d['tipo']),
                $d['producto'],
                $unidad,
                $d['pv'],
                $d['cantidad'] . ' ' . $unidad,
                $d['valor_anterior'],
                $d['valor_nuevo'],
                $d['motivo'] ?? '',
                $d['usuario'],
            ];
        }

        $excel->generar($columnas, $filas, [
            'totales' => ['Total' => count($datos)],
        ]);
    }
}