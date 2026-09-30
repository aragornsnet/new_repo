<?php
/**
 * PLANTILLA DE REPORTE
 * ----------------------------------------------------
 * Cada archivo de reporte debe seguir este patrón.
 *
 * El nombre del archivo debe coincidir con el "tipo"
 * que se envía en api/reportes.php?tipo=X
 *
 * Debe definir una función llamada generar_X($formato)
 * donde X es el nombre del archivo (sin .php).
 *
 * Formatos soportados: 'pdf', 'excel'
 */

function generar_ejemplo($formato)
{
    // 1. Recoger parámetros de la URL
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');

    // 2. Consultar la base de datos
    $datos = Database::fetchAll("
        SELECT * FROM tabla WHERE fecha BETWEEN ? AND ?
    ", [$desde, $hasta]);

    // 3. Preparar los datos según formato
    $titulo = 'Mi Reporte de Ejemplo';
    $subtitulo = "Del $desde al $hasta";

    if ($formato === 'pdf') {
        // === PDF ===
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        $columnas = ['ID', 'Nombre', 'Fecha', 'Total'];
        $anchos = [1, 3, 2, 2];
        $alineaciones = ['C', 'L', 'C', 'R'];

        $filas = [];
        foreach ($datos as $d) {
            $filas[] = [$d['id'], $d['nombre'], $d['fecha'], $d['total']];
        }

        $pdf->generarTabla($columnas, $filas, [
            'anchos' => $anchos,
            'alineaciones' => $alineaciones,
        ]);

        $pdf->bloqueTotales([
            'Total de registros' => count($datos),
        ]);

        $pdf->Output('reporte_ejemplo.pdf', 'I');
        exit;

    } elseif ($formato === 'excel') {
        // === EXCEL ===
        $excel = new ExcelBase('reporte_ejemplo', $titulo, $subtitulo);

        $columnas = ['ID', 'Nombre', 'Fecha', 'Total'];
        $filas = [];
        foreach ($datos as $d) {
            $filas[] = [$d['id'], $d['nombre'], $d['fecha'], $d['total']];
        }

        $excel->generar($columnas, $filas, [
            'totales' => [
                'Registros' => count($datos),
            ],
        ]);
    }
}