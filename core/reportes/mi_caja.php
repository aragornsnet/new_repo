<?php
/**
 * Reporte: Mi caja (turno actual del vendedor)
 */

function generar_mi_caja($formato)
{
    $vendedorId = Auth::id();

    $turno = Database::fetchOne("
        SELECT t.*, pv.nombre AS pv
        FROM turnos t
        JOIN puntos_venta pv ON pv.id = t.punto_venta_id
        WHERE t.usuario_id = :vendedor_id AND t.estado = 'abierto'
        LIMIT 1
    ", [':vendedor_id' => $vendedorId]);

    if (!$turno) {
        die('No tienes un turno abierto');
    }

    $movimientos = Database::fetchAll("
        SELECT 
            v.folio, v.fecha, v.total,
            (SELECT COALESCE(SUM(p.monto), 0) FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'efectivo') AS efectivo
        FROM ventas v
        WHERE v.turno_id = :turno_id
          AND v.estado = 'completada'
          AND EXISTS (SELECT 1 FROM pagos_venta p WHERE p.venta_id = v.id AND p.metodo = 'efectivo')
        ORDER BY v.fecha ASC
    ", [':turno_id' => (int)$turno['id']]);

    $totalEfectivo = array_sum(array_column($movimientos, 'efectivo'));
    $efectivoTeorico = (float)$turno['monto_inicial'] + $totalEfectivo;

    $titulo = 'Mi Caja - Turno #' . $turno['id'];
    $subtitulo = $turno['pv'] . ' · Apertura: ' . fecha($turno['fecha_apertura'], 'd/m/Y H:i');

    if ($formato === 'pdf') {
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        $pdf->bloqueResumen([
            ['label' => 'Monto inicial', 'valor' => moneda($turno['monto_inicial'])],
            ['label' => 'Ventas efectivo', 'valor' => moneda($totalEfectivo)],
            ['label' => 'En caja ahora', 'valor' => moneda($efectivoTeorico)],
        ]);

        $pdf->Ln(4);

        $columnas = ['Folio', 'Fecha', 'Efectivo recibido', 'Total venta'];
        $anchos = [2, 3, 2.5, 2.5];
        $alineaciones = ['L', 'C', 'R', 'R'];

        $filas = [];
        foreach ($movimientos as $m) {
            $filas[] = [
                $m['folio'],
                fecha($m['fecha'], 'd/m/Y H:i'),
                moneda($m['efectivo']),
                moneda($m['total']),
            ];
        }

        $pdf->generarTabla($columnas, $filas, [
            'anchos' => $anchos,
            'alineaciones' => $alineaciones,
        ]);

        $pdf->bloqueTotales([
            'Monto inicial' => moneda($turno['monto_inicial']),
            'Ventas en efectivo' => moneda($totalEfectivo),
            'TOTAL EN CAJA' => moneda($efectivoTeorico),
        ]);

        $pdf->Output('mi_caja_' . date('Ymd_His') . '.pdf', 'I');
        exit;

    } else {
        $excel = new ExcelBase('mi_caja', $titulo, $subtitulo);

        $columnas = ['Folio', 'Fecha', 'Efectivo recibido', 'Total venta'];
        $filas = [];
        foreach ($movimientos as $m) {
            $filas[] = [
                $m['folio'],
                fecha($m['fecha'], 'd/m/Y H:i'),
                number_format((float)$m['efectivo'], 2, '.', ''),
                number_format((float)$m['total'], 2, '.', ''),
            ];
        }

        $excel->generar($columnas, $filas, [
            'totales' => [
                'Monto inicial' => moneda($turno['monto_inicial']),
                'Ventas efectivo' => moneda($totalEfectivo),
                'TOTAL EN CAJA' => moneda($efectivoTeorico),
            ],
        ]);
    }
}