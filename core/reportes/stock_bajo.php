<?php
/**
 * Reporte: Productos con stock bajo o negativo
 */

function generar_stock_bajo($formato)
{
    $filtro = trim($_GET['filtro_stock'] ?? 'bajo');
    $pvId = (int)($_GET['pv_id'] ?? 0);

    $condiciones = ['p.activo = 1', 'pv.activo = 1'];
    $params = [];

    if ($filtro === 'negativo') {
        $condiciones[] = 'spv.stock < 0';
    } elseif ($filtro === 'bajo') {
        $condiciones[] = 'spv.stock <= p.stock_minimo AND spv.stock >= 0';
    } else {
        $condiciones[] = 'spv.stock <= p.stock_minimo';
    }

    if ($pvId > 0) {
        $condiciones[] = 'pv.id = :pv_id';
        $params[':pv_id'] = $pvId;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT 
            p.nombre AS producto, p.codigo_barras,
            p.unidad_medida,
            spv.stock, p.stock_minimo,
            c.nombre AS categoria,
            pv.nombre AS pv
        FROM stock_punto_venta spv
        JOIN productos p ON p.id = spv.producto_id
        JOIN puntos_venta pv ON pv.id = spv.punto_venta_id
        LEFT JOIN categorias c ON c.id = p.categoria_id
        $where
        ORDER BY spv.stock ASC, p.nombre
    ", $params);

    $titulo = 'Productos con Stock ' . ($filtro === 'negativo' ? 'Negativo' : 'Bajo');
    $subtitulo = count($datos) . ' productos encontrados';

    if ($formato === 'pdf') {
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        $columnas = ['Producto', 'Código', 'Unidad', 'Categoría', 'PV', 'Stock', 'Mín.', 'Estado'];
        $anchos = [3, 1.8, 1.2, 1.8, 1.8, 1.4, 1.4, 1.6];
        $alineaciones = ['L', 'L', 'C', 'L', 'L', 'C', 'C', 'C'];

        $filas = [];
        foreach ($datos as $d) {
            $estado = $d['stock'] < 0 ? 'NEGATIVO' : ($d['stock'] <= $d['stock_minimo'] ? 'BAJO' : 'OK');
            $unidad = $d['unidad_medida'] ?? 'Unidad';

            $filas[] = [
                $d['producto'],
                $d['codigo_barras'] ?? '—',
                $unidad,
                $d['categoria'] ?? '—',
                $d['pv'],
                $d['stock'] . ' ' . $unidad,
                $d['stock_minimo'] . ' ' . $unidad,
                $estado,
            ];
        }

        $pdf->generarTabla($columnas, $filas, [
            'anchos' => $anchos,
            'alineaciones' => $alineaciones,
        ]);

        $pdf->Output('stock_bajo_' . date('Ymd_His') . '.pdf', 'I');
        exit;

    } else {
        $excel = new ExcelBase('stock_bajo', $titulo, $subtitulo);

        $columnas = ['Producto', 'Código', 'Unidad', 'Categoría', 'PV', 'Stock', 'Mínimo', 'Estado'];
        $filas = [];
        foreach ($datos as $d) {
            $estado = $d['stock'] < 0 ? 'NEGATIVO' : ($d['stock'] <= $d['stock_minimo'] ? 'BAJO' : 'OK');
            $unidad = $d['unidad_medida'] ?? 'Unidad';

            $filas[] = [
                $d['producto'],
                $d['codigo_barras'] ?? '',
                $unidad,
                $d['categoria'] ?? '',
                $d['pv'],
                $d['stock'] . ' ' . $unidad,
                $d['stock_minimo'] . ' ' . $unidad,
                $estado,
            ];
        }

        $excel->generar($columnas, $filas, [
            'totales' => ['Total productos' => count($datos)],
        ]);
    }
}