<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Caja del Día';
$subtituloPagina = 'Consolidado por vendedor y por punto de venta';
$paginaActiva = 'caja';
$scriptsExtra = [
    BASE_URL . 'public/js/caja_dia.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Supervisor', 'Administrador'])) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">💰 Caja del día</h2>
        <p class="page-subtitle">Consolidado de ventas por vendedor y por punto de venta</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary" onclick="CajaDia.exportar()">
            <i class="bi bi-file-earmark-excel"></i> Exportar
        </button>
    </div>
</div>

<!-- Filtros de fecha y PV -->
<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Desde</label>
            <input type="date" id="filtro-desde" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" id="filtro-hasta" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="field">
            <label>Punto de venta</label>
            <select id="filtro-pv">
                <option value="">Todos los PV</option>
            </select>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn btn-primary" onclick="CajaDia.cargar()">
                <i class="bi bi-search"></i> Consultar
            </button>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-ghost btn-sm" onclick="CajaDia.hoy()">Hoy</button>
        <button class="btn btn-ghost btn-sm" onclick="CajaDia.ayer()">Ayer</button>
        <button class="btn btn-ghost btn-sm" onclick="CajaDia.semana()">Últimos 7 días</button>
        <button class="btn btn-ghost btn-sm" onclick="CajaDia.mes()">Este mes</button>
    </div>
</div>

<!-- Totales generales -->
<div class="grid-stats mb-4">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon primary"><i class="bi bi-cart-check-fill"></i></div>
        </div>
        <div class="stat-card-label">Num. ventas</div>
        <div class="stat-card-value" id="tot-num">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon success"><i class="bi bi-cash-coin"></i></div>
        </div>
        <div class="stat-card-label">Total efectivo</div>
        <div class="stat-card-value" id="tot-efectivo">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon info"><i class="bi bi-bank"></i></div>
        </div>
        <div class="stat-card-label">Total transferencias</div>
        <div class="stat-card-value" id="tot-transf">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon warning"><i class="bi bi-cash-stack"></i></div>
        </div>
        <div class="stat-card-label">Total general</div>
        <div class="stat-card-value" id="tot-general">—</div>
    </div>
</div>

<!-- Por vendedor -->
<div class="card mb-4">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-people-fill"></i> Consolidado por vendedor</div>
    </div>
    <div id="tabla-vendedor">
        <div class="empty-state"><div class="spinner"></div></div>
    </div>
</div>

<!-- Por PV -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-shop"></i> Consolidado por punto de venta</div>
    </div>
    <div id="tabla-pv">
        <div class="empty-state"><div class="spinner"></div></div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>