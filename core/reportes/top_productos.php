<?php
/**
 * Reporte: Top productos más vendidos
 */

function generar_top_productos($formato)
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $limit = min(max((int)($_GET['limit'] ?? 20), 1), 100);

    $datos = Database::fetchAll("
        SELECT 
            p.nombre AS producto,
            p.codigo_barras,
            p.unidad_medida,
            c.nombre AS categoria,
            SUM(dv.cantidad) AS unidades,
            SUM(dv.subtotal) AS total,
            COUNT(DISTINCT v.id) AS num_ventas
        FROM detalle_ventas dv
        JOIN productos p ON p.id = dv.producto_id
        JOIN ventas v ON v.id = dv.venta_id
        LEFT JOIN categorias c ON c.id = p.categoria_id
        WHERE v.estado = 'completada'
          AND DATE(v.fecha) >= :desde
          AND DATE(v.fecha) <= :hasta
        GROUP BY p.id, p.nombre, p.codigo_barras, p.unidad_medida, c.nombre
        ORDER BY unidades DESC
        LIMIT $limit
    ", [':desde' => $desde, ':hasta' => $hasta]);

    $titulo = "Top $limit Productos Más Vendidos";
    $subtitulo = "Desde $desde hasta $hasta";

    if ($formato === 'pdf') {
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        $columnas = ['#', 'Producto', 'Unidad', 'Categoría', 'Unidades', 'Ventas', 'Total'];
        $anchos = [0.6, 3.5, 1.2, 2, 1.5, 1.2, 2];
        $alineaciones = ['C', 'L', 'C', 'L', 'C', 'C', 'R'];

        $filas = [];
        foreach ($datos as $i => $d) {
            $unidad = $d['unidad_medida'] ?? 'Unidad';

            $filas[] = [
                $i + 1,
                $d['producto'],
                $unidad,
                $d['categoria'] ?? '—',
                $d['unidades'] . ' ' . $unidad,
                $d['num_ventas'],
                moneda($d['total']),
            ];
        }

        $pdf->generarTabla($columnas, $filas, [
            'anchos' => $anchos,
            'alineaciones' => $alineaciones,
        ]);

        $pdf->bloqueTotales([
            'Total unidades' => array_sum(array_column($datos, 'unidades')),
            'Total ingresos' => moneda(array_sum(array_column($datos, 'total'))),
        ]);

        $pdf->Output('top_productos_' . date('Ymd_His') . '.pdf', 'I');
        exit;

    } else {
        $excel = new ExcelBase('top_productos', $titulo, $subtitulo);

        $columnas = ['#', 'Producto', 'Código', 'Unidad', 'Categoría', 'Unidades', 'Ventas', 'Total'];
        $filas = [];
        foreach ($datos as $i => $d) {
            $unidad = $d['unidad_medida'] ?? 'Unidad';

            $filas[] = [
                $i + 1,
                $d['producto'],
                $d['codigo_barras'] ?? '',
                $unidad,
                $d['categoria'] ?? '',
                $d['unidades'] . ' ' . $unidad,
                $d['num_ventas'],
                number_format((float)$d['total'], 2, '.', ''),
            ];
        }

        $excel->generar($columnas, $filas, [
            'totales' => [
                'Total unidades' => array_sum(array_column($datos, 'unidades')),
                'Total ingresos' => moneda(array_sum(array_column($datos, 'total'))),
            ],
        ]);
    }
}