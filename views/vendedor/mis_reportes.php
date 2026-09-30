<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Mis Reportes';
$subtituloPagina = 'Descarga tus reportes';
$paginaActiva = 'reportes';
$scriptsExtra = [
    BASE_URL . 'public/js/reportes.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Vendedor'])) {
    redirigir('views/403.php');
}

// Verificar turno abierto
$turnoAbierto = Database::fetchOne("
    SELECT t.id, pv.nombre AS pv
    FROM turnos t
    JOIN puntos_venta pv ON pv.id = t.punto_venta_id
    WHERE t.usuario_id = ? AND t.estado = 'abierto'
    LIMIT 1
", [Auth::id()]);

if (!$turnoAbierto) {
    redirigir('views/vendedor/dashboard.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">📊 Mis reportes</h2>
        <p class="page-subtitle">Descarga tus reportes en PDF o Excel</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-file-earmark-bar-graph"></i> Tipo de reporte</div>
    </div>

    <div class="grid-3">
        <button class="btn btn-secondary reporte-btn" data-tipo="mis_ventas" onclick="Reportes.seleccionar('mis_ventas')">
            <i class="bi bi-receipt"></i> Mis ventas
        </button>
        <button class="btn btn-secondary reporte-btn" data-tipo="mi_caja" onclick="Reportes.seleccionar('mi_caja')">
            <i class="bi bi-cash-coin"></i> Mi caja (turno actual)
        </button>
    </div>
</div>

<div class="card mb-4" id="filtros-wrap" style="display:none;">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-funnel-fill"></i> Filtros: <span id="reporte-nombre">—</span></div>
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

<script>
    window.REPORTES_DATA = {};
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>