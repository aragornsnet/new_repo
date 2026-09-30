<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Turnos Globales';
$subtituloPagina = 'Todos los turnos de todos los puntos de venta';
$paginaActiva = 'turnos';
$scriptsExtra = [
    BASE_URL . 'public/js/turnos.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Administrador', 'Supervisor'])) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">🕐 Turnos globales</h2>
        <p class="page-subtitle">Todos los turnos del sistema</p>
    </div>
</div>

<!-- Resumen del día -->
<div class="grid-stats mb-4">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon primary"><i class="bi bi-clock-history"></i></div>
        </div>
        <div class="stat-card-label">Total turnos hoy</div>
        <div class="stat-card-value" id="res-total">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon success"><i class="bi bi-play-circle-fill"></i></div>
        </div>
        <div class="stat-card-label">Abiertos ahora</div>
        <div class="stat-card-value" id="res-abiertos">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon info"><i class="bi bi-stop-circle-fill"></i></div>
        </div>
        <div class="stat-card-label">Cerrados hoy</div>
        <div class="stat-card-value" id="res-cerrados">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
        </div>
        <div class="stat-card-label">Forzados</div>
        <div class="stat-card-value" id="res-forzados">—</div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Vendedor o PV...">
            </div>
        </div>
        <div class="field">
            <label>Punto de venta</label>
            <select id="filtro-pv">
                <option value="">Todos los PV</option>
            </select>
        </div>
        <div class="field">
            <label>Vendedor</label>
            <select id="filtro-vendedor">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="field">
            <label>Estado</label>
            <select id="filtro-estado">
                <option value="">Todos</option>
                <option value="abierto">Abiertos</option>
                <option value="cerrado">Cerrados</option>
            </select>
        </div>
        <div class="field">
            <label>Desde</label>
            <input type="date" id="filtro-desde">
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" id="filtro-hasta">
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary btn-sm" onclick="Turnos.cargar()">
            <i class="bi bi-funnel-fill"></i> Aplicar filtros
        </button>
        <button class="btn btn-secondary btn-sm" onclick="Turnos.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-list-ul"></i> Turnos
            <span class="badge badge-neutral ml-2" id="total-turnos">—</span>
        </div>
    </div>
    <div id="tabla-turnos">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>