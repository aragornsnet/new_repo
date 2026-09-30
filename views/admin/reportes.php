<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Reportes';
$subtituloPagina = 'Genera reportes en PDF o Excel';
$paginaActiva = 'reportes';
$scriptsExtra = [
    BASE_URL . 'public/js/reportes.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Administrador'])) {
    redirigir('views/403.php');
}

$puntos = Database::fetchAll("SELECT id, nombre FROM puntos_venta WHERE activo = 1 ORDER BY nombre");
$vendedores = Database::fetchAll("
    SELECT u.id, u.nombre, pv.nombre AS pv
    FROM usuarios u
    LEFT JOIN puntos_venta pv ON pv.id = u.punto_venta_id
    WHERE u.rol_id = 3 AND u.activo = 1
    ORDER BY u.nombre
");
$usuarios = Database::fetchAll("
    SELECT id, nombre FROM usuarios WHERE activo = 1 ORDER BY nombre
");
$clientes = Database::fetchAll("
    SELECT id, nombre FROM clientes WHERE activo = 1 ORDER BY nombre
");
?>

<div class="page-header">
    <div>
        <h2 class="page-title">📊 Reportes</h2>
        <p class="page-subtitle">Genera reportes en PDF o Excel</p>
    </div>
</div>

<!-- Selector de reporte -->
<div class="card mb-4">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-file-earmark-bar-graph"></i> Tipo de reporte</div>
    </div>

    <div class="grid-3 mb-3">
        <button class="btn btn-secondary reporte-btn" data-tipo="ventas_fechas" onclick="Reportes.seleccionar('ventas_fechas')">
            <i class="bi bi-cart-check"></i> Ventas por fecha
        </button>
        <button class="btn btn-secondary reporte-btn" data-tipo="ventas_vendedor" onclick="Reportes.seleccionar('ventas_vendedor')">
            <i class="bi bi-people-fill"></i> Ventas por vendedor
        </button>
        <button class="btn btn-secondary reporte-btn" data-tipo="ventas_pv" onclick="Reportes.seleccionar('ventas_pv')">
            <i class="bi bi-shop"></i> Ventas por PV
        </button>
        <button class="btn btn-secondary reporte-btn" data-tipo="top_productos" onclick="Reportes.seleccionar('top_productos')">
            <i class="bi bi-trophy-fill"></i> Top productos
        </button>
        <button class="btn btn-secondary reporte-btn" data-tipo="caja_dia" onclick="Reportes.seleccionar('caja_dia')">
            <i class="bi bi-cash-stack"></i> Caja del día
        </button>
        <button class="btn btn-secondary reporte-btn" data-tipo="movimientos" onclick="Reportes.seleccionar('movimientos')">
            <i class="bi bi-arrow-left-right"></i> Movimientos de inventario
        </button>
        <button class="btn btn-secondary reporte-btn" data-tipo="turnos" onclick="Reportes.seleccionar('turnos')">
            <i class="bi bi-clock-history"></i> Turnos
        </button>
        <button class="btn btn-secondary reporte-btn" data-tipo="transferencias" onclick="Reportes.seleccionar('transferencias')">
            <i class="bi bi-bank"></i> Transferencias
        </button>
        <button class="btn btn-secondary reporte-btn" data-tipo="stock_bajo" onclick="Reportes.seleccionar('stock_bajo')">
            <i class="bi bi-exclamation-triangle"></i> Stock bajo
        </button>
        <button class="btn btn-secondary reporte-btn" data-tipo="auditoria" onclick="Reportes.seleccionar('auditoria')">
            <i class="bi bi-shield-check"></i> Auditoría
        </button>
        <button class="btn btn-secondary reporte-btn" data-tipo="facturas_fechas" onclick="Reportes.seleccionar('facturas_fechas')">
            <i class="bi bi-receipt"></i> Facturas por fecha
        </button>
        <button class="btn btn-secondary reporte-btn" data-tipo="facturas_cliente" onclick="Reportes.seleccionar('facturas_cliente')">
            <i class="bi bi-people-fill"></i> Facturas por cliente
        </button>
        <button class="btn btn-secondary reporte-btn" data-tipo="facturas_vendedor" onclick="Reportes.seleccionar('facturas_vendedor')">
            <i class="bi bi-person-badge"></i> Facturas por vendedor
        </button>
        <button class="btn btn-secondary reporte-btn" data-tipo="comprobantes_fechas" onclick="Reportes.seleccionar('comprobantes_fechas')">
            <i class="bi bi-receipt-cutoff"></i> Comprobantes por fecha
        </button>
        <button class="btn btn-secondary reporte-btn" data-tipo="comprobantes_vendedor" onclick="Reportes.seleccionar('comprobantes_vendedor')">
            <i class="bi bi-person-badge"></i> Comprobantes por vendedor
        </button>
        <button class="btn btn-secondary reporte-btn" data-tipo="comprobantes_pv" onclick="Reportes.seleccionar('comprobantes_pv')">
            <i class="bi bi-shop"></i> Comprobantes por PV
        </button>
    </div>
</div>

<!-- Filtros dinámicos -->
<div class="card mb-4" id="filtros-wrap" style="display:none;">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-funnel-fill"></i> Filtros del reporte: <span id="reporte-nombre">—</span></div>
    </div>

    <div id="filtros-contenido"></div>

    <div class="mt-4 d-flex gap-2" style="flex-wrap:wrap;">
        <button class="btn btn-primary" onclick="Reportes.ver()">
            <i class="bi bi-eye"></i> Ver vista previa
        </button>
        <button class="btn btn-danger" onclick="Reportes.generar('pdf')">
            <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
        </button>
        <button class="btn btn-success" onclick="Reportes.generar('excel')">
            <i class="bi bi-file-earmark-excel"></i> Descargar Excel
        </button>
    </div>
</div>

<!-- Datos para filtros -->
<script>
    window.REPORTES_DATA = {
        puntos: <?= json_encode($puntos) ?>,
        vendedores: <?= json_encode($vendedores) ?>,
        usuarios: <?= json_encode($usuarios) ?>,
        clientes: <?= json_encode($clientes) ?>,
    };
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>