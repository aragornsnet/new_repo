<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Ranking de Vendedores';
$subtituloPagina = 'Comparativa de rendimiento';
$paginaActiva = 'ranking';
$scriptsExtra = [
    BASE_URL . 'public/libs/chartjs/chart.min.js',
    BASE_URL . 'public/js/ranking.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Supervisor', 'Administrador'])) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">🏆 Ranking de vendedores</h2>
        <p class="page-subtitle">Comparativa de rendimiento entre vendedores</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary" onclick="Ranking.exportar()">
            <i class="bi bi-file-earmark-excel"></i> Exportar CSV
        </button>
    </div>
</div>

<!-- Atajos de fecha -->
<div class="card mb-4">
    <div class="d-flex gap-2 flex-wrap mb-3">
        <button class="btn btn-secondary btn-sm" onclick="Ranking.atajo('hoy')" data-atajo="hoy">
            <i class="bi bi-calendar-day"></i> Hoy
        </button>
        <button class="btn btn-secondary btn-sm" onclick="Ranking.atajo('semana')" data-atajo="semana">
            <i class="bi bi-calendar-week"></i> Esta semana
        </button>
        <button class="btn btn-primary btn-sm" onclick="Ranking.atajo('mes')" data-atajo="mes">
            <i class="bi bi-calendar-month"></i> Este mes
        </button>
        <button class="btn btn-secondary btn-sm" onclick="Ranking.atajo('mes_anterior')" data-atajo="mes_anterior">
            <i class="bi bi-calendar-minus"></i> Mes anterior
        </button>
        <button class="btn btn-secondary btn-sm" onclick="Ranking.atajo('año')" data-atajo="año">
            <i class="bi bi-calendar-range"></i> Este año
        </button>
    </div>

    <div class="form-grid">
        <div class="field">
            <label>Desde</label>
            <input type="date" id="filtro-desde">
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" id="filtro-hasta">
        </div>
        <div class="field">
            <label>Punto de venta</label>
            <select id="filtro-pv">
                <option value="">Todos los PV</option>
            </select>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;gap:8px;">
            <button class="btn btn-primary" onclick="Ranking.cargar()">
                <i class="bi bi-funnel-fill"></i> Aplicar
            </button>
        </div>
    </div>
</div>

<!-- Stats generales -->
<div class="grid-stats mb-4">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon primary"><i class="bi bi-cart-check-fill"></i></div>
        </div>
        <div class="stat-card-label">Total vendido</div>
        <div class="stat-card-value" id="tot-vendido">—</div>
        <div class="stat-card-trend" id="tot-comparativa">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon success"><i class="bi bi-receipt"></i></div>
        </div>
        <div class="stat-card-label">Num. ventas</div>
        <div class="stat-card-value" id="tot-ventas">—</div>
        <div class="stat-card-trend" id="tot-ventas-ant">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon info"><i class="bi bi-cash-coin"></i></div>
        </div>
        <div class="stat-card-label">Efectivo</div>
        <div class="stat-card-value" id="tot-efectivo">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon warning"><i class="bi bi-bank"></i></div>
        </div>
        <div class="stat-card-label">Transferencias</div>
        <div class="stat-card-value" id="tot-transf">—</div>
    </div>
</div>

<!-- Podium -->
<div class="card mb-4" id="podium-card" style="display:none;">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-trophy-fill text-warning"></i> Top 3 del período</div>
    </div>
    <div id="podium" class="d-flex gap-3" style="justify-content:center;align-items:flex-end;padding:20px;flex-wrap:wrap;"></div>
</div>

<!-- Gráfico comparativo -->
<div class="card mb-4">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-bar-chart-line-fill"></i> Comparativa de ventas</div>
    </div>
    <div style="position: relative; height: 320px;">
        <canvas id="chart-ranking"></canvas>
    </div>
</div>

<!-- Tabla detallada -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-list-ul"></i> Detalle por vendedor
            <span class="badge badge-neutral ml-2" id="total-vendedores">—</span>
        </div>
    </div>
    <div id="tabla-ranking">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>