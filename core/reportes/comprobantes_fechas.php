<?php
/**
 * Reporte: Comprobantes por rango de fechas
 */

function generar_comprobantes_fechas($formato)
{
    $desde      = $_GET['desde'] ?? date('Y-m-01');
    $hasta      = $_GET['hasta'] ?? date('Y-m-d');
    $vendedorId = (int)($_GET['vendedor_id'] ?? 0);
    $pvId       = (int)($_GET['pv_id'] ?? 0);
    $estado     = trim($_GET['estado'] ?? '');

    $condiciones = [
        'DATE(cv.created_at) >= :desde',
        'DATE(cv.created_at) <= :hasta',
    ];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($vendedorId > 0) {
        $condiciones[] = 'cv.emitido_por = :vendedor_id';
        $params[':vendedor_id'] = $vendedorId;
    }
    if ($pvId > 0) {
        $condiciones[] = 'v.punto_venta_id = :pv_id';
        $params[':pv_id'] = $pvId;
    }
    if (in_array($estado, ['emitido', 'anulado'], true)) {
        $condiciones[] = 'cv.estado = :estado';
        $params[':estado'] = $estado;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT
            cv.folio, cv.created_at, cv.nombre_comprador,
            cv.telefono_comprador, cv.total, cv.estado,
            v.folio AS venta_folio,
            pv.nombre AS pv,
            u.nombre AS emitido_por
        FROM comprobantes_venta cv
        JOIN ventas v ON v.id = cv.venta_id
        JOIN puntos_venta pv ON pv.id = v.punto_venta_id
        JOIN usuarios u ON u.id = cv.emitido_por
        $where
        ORDER BY cv.created_at DESC
        LIMIT 5000
    ", $params);

    $totalGeneral = 0;
    $totalEmitidos = 0;
    $totalAnulados = 0;

    foreach ($datos as $d) {
        if ($d['estado'] === 'anulado') {
            $totalAnulados++;
            continue;
        }
        $totalEmitidos++;
        $totalGeneral += (float)$d['total'];
    }

    $titulo = 'Reporte de Comprobantes';
    $subtitulo = "Desde $desde hasta $hasta · " . count($datos) . " comprobante(s)";

    if ($formato === 'pdf') {
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        $pdf->bloqueResumen([
            ['label' => 'Emitidos',  'valor' => (string)$totalEmitidos],
            ['label' => 'Anulados',  'valor' => (string)$totalAnulados],
            ['label' => 'Total',     'valor' => moneda($totalGeneral)],
        ]);

        $columnas = ['Folio', 'Fecha', 'Comprador', 'Teléfono', 'Venta', 'PV', 'Emitido por', 'Estado', 'Total'];
        $anchos = [1.6, 1.6, 2.5, 1.5, 1.6, 1.8, 2, 1.4, 1.6];
        $alineaciones = ['L', 'C', 'L', 'L', 'L', 'L', 'L', 'C', 'R'];

        $filas = [];
        foreach ($datos as $d) {
            $filas[] = [
                $d['folio'],
                fecha($d['created_at'], 'd/m/Y H:i'),
                $d['nombre_comprador'] ?: 'Consumidor final',
                $d['telefono_comprador'] ?: '—',
                $d['venta_folio'],
                $d['pv'],
                $d['emitido_por'],
                ucfirst($d['estado']),
                moneda($d['total']),
            ];
        }

        $pdf->generarTabla($columnas, $filas, [
            'anchos' => $anchos,
            'alineaciones' => $alineaciones,
        ]);

        $pdf->bloqueTotales([
            'Comprobantes emitidos' => $totalEmitidos,
            'Comprobantes anulados' => $totalAnulados,
            'TOTAL'                 => moneda($totalGeneral),
        ]);

        $pdf->Output('comprobantes_fechas_' . date('Ymd_His') . '.pdf', 'I');
        exit;

    } else {
        $excel = new ExcelBase('comprobantes_fechas', $titulo, $subtitulo);

        $columnas = ['Folio', 'Fecha', 'Comprador', 'Documento', 'Teléfono', 'Dirección', 'Venta', 'PV', 'Emitido por', 'Estado', 'Total'];
        $filas = [];
        foreach ($datos as $d) {
            $filas[] = [
                $d['folio'],
                fecha($d['created_at'], 'd/m/Y H:i'),
                $d['nombre_comprador'] ?: 'Consumidor final',
                $d['documento_comprador'] ?? '',
                $d['telefono_comprador'] ?? '',
                $d['direccion_comprador'] ?? '',
                $d['venta_folio'],
                $d['pv'],
                $d['emitido_por'],
                ucfirst($d['estado']),
                number_format((float)$d['total'], 2, '.', ''),
            ];
        }

        $excel->generar($columnas, $filas, [
            'totales' => [
                'Comprobantes emitidos' => $totalEmitidos,
                'Comprobantes anulados' => $totalAnulados,
                'Total'                 => moneda($totalGeneral),
            ],
        ]);
    }
}