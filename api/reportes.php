<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

/**
 * IPV - API central de reportes
 * Dispatch según tipo y formato
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(300);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/ReporteBase.php';
require_once __DIR__ . '/../core/ExcelBase.php';
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/helpers.php';

Auth::iniciarSesion();
Auth::requireLogin();

$tipo = $_GET['tipo'] ?? '';
$formato = $_GET['formato'] ?? 'pdf';

// Verificar permisos según tipo de reporte
$permisosPorRol = [
    'Administrador' => [
        'ventas_fechas', 'ventas_vendedor', 'ventas_pv', 'top_productos',
        'caja_dia', 'movimientos', 'turnos', 'transferencias', 'stock_bajo', 'auditoria',
        'facturas_fechas', 'facturas_cliente', 'facturas_vendedor',
        'comprobantes_fechas', 'comprobantes_vendedor', 'comprobantes_pv',
    ],
    'Supervisor' => [
        'ventas_fechas', 'ventas_vendedor', 'ventas_pv', 'top_productos',
        'caja_dia', 'movimientos', 'turnos', 'transferencias', 'stock_bajo',
        'facturas_fechas', 'facturas_cliente', 'facturas_vendedor',
        'comprobantes_fechas', 'comprobantes_vendedor', 'comprobantes_pv',
    ],
    'Vendedor' => [
        'mis_ventas', 'mi_caja',
    ],
];

$rol = Auth::rol();
$tiposPermitidos = $permisosPorRol[$rol] ?? [];

if (!in_array($tipo, $tiposPermitidos, true)) {
    http_response_code(403);
    die('No tienes permiso para generar este reporte');
}

// Buscar el archivo del reporte
$archivoReporte = __DIR__ . '/../core/reportes/' . $tipo . '.php';

if (!file_exists($archivoReporte)) {
    http_response_code(404);
    die('Reporte no encontrado');
}

// Incluir el reporte (cada uno tiene una función generar_<tipo>)
require_once $archivoReporte;

$funcionGenerar = 'generar_' . $tipo;

if (!function_exists($funcionGenerar)) {
    http_response_code(500);
    die('Función de reporte no definida');
}

// Ejecutar el reporte
try {
    $funcionGenerar($formato);
} catch (Throwable $e) {
    error_log('Error en reporte ' . $tipo . ': ' . $e->getMessage());
    http_response_code(500);
    die('Error al generar el reporte: ' . $e->getMessage());
}