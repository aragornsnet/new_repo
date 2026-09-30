<?php
/**
 * Reporte: Auditoría filtrada
 */

function generar_auditoria($formato)
{
    $desde = $_GET['desde'] ?? date('Y-m-d');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $usuarioId = (int)($_GET['usuario_id'] ?? 0);
    $accion = trim($_GET['accion_filtro'] ?? '');

    $condiciones = ['DATE(a.fecha) >= :desde', 'DATE(a.fecha) <= :hasta'];
    $params = [':desde' => $desde, ':hasta' => $hasta];

    if ($usuarioId > 0) {
        $condiciones[] = 'a.usuario_id = :usuario_id';
        $params[':usuario_id'] = $usuarioId;
    }
    if ($accion !== '') {
        $condiciones[] = 'a.accion = :accion';
        $params[':accion'] = $accion;
    }

    $where = 'WHERE ' . implode(' AND ', $condiciones);

    $datos = Database::fetchAll("
        SELECT 
            a.fecha, a.accion, a.tabla_afectada, a.registro_id,
            a.ip, a.detalle,
            u.nombre AS usuario
        FROM auditoria a
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        $where
        ORDER BY a.fecha DESC
        LIMIT 5000
    ", $params);

    $titulo = 'Reporte de Auditoría';
    $subtitulo = "Desde $desde hasta $hasta · " . count($datos) . " registros";

    if ($formato === 'pdf') {
        $pdf = new ReporteBase($titulo, $subtitulo);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', '', 7);

        $columnas = ['Fecha', 'Usuario', 'Acción', 'Tabla', 'IP'];
        $anchos = [2.5, 2, 3, 2, 2];
        $alineaciones = ['C', 'L', 'L', 'L', 'C'];

        $filas = [];
        foreach (array_slice($datos, 0, 100) as $d) {
            $filas[] = [
                fecha($d['fecha'], 'd/m H:i'),
                $d['usuario'] ?? 'Sistema',
                $d['accion'],
                $d['tabla_afectada'] ?? '—',
                $d['ip'] ?? '—',
            ];
        }

        $pdf->generarTabla($columnas, $filas, [
            'anchos' => $anchos,
            'alineaciones' => $alineaciones,
        ]);

        if (count($datos) > 100) {
            $pdf->Ln(4);
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->Cell(0, 4, 'Mostrando los primeros 100 registros. Use Excel para ver todos.', 0, 1, 'C');
        }

        $pdf->Output('auditoria_' . date('Ymd_His') . '.pdf', 'I');
        exit;

    } else {
        $excel = new ExcelBase('auditoria', $titulo, $subtitulo);

        $columnas = ['Fecha', 'Usuario', 'Acción', 'Tabla', 'Registro ID', 'IP', 'Detalle'];
        $filas = [];
        foreach ($datos as $d) {
            $filas[] = [
                fecha($d['fecha'], 'd/m/Y H:i:s'),
                $d['usuario'] ?? 'Sistema',
                $d['accion'],
                $d['tabla_afectada'] ?? '',
                $d['registro_id'] ?? '',
                $d['ip'] ?? '',
                $d['detalle'] ?? '',
            ];
        }

        $excel->generar($columnas, $filas, [
            'totales' => ['Total registros' => count($datos)],
        ]);
    }
}