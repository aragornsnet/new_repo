<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Auditoría';
$subtituloPagina = 'Bitácora de todas las acciones del sistema';
$paginaActiva = 'auditoria';
$scriptsExtra = [
    BASE_URL . 'public/libs/chartjs/chart.min.js',
    BASE_URL . 'public/js/auditoria.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::esAdmin()) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">🛡️ Auditoría</h2>
        <p class="page-subtitle">Registro completo de las acciones realizadas en el sistema</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary" onclick="Auditoria.exportar()">
            <i class="bi bi-file-earmark-excel"></i> Exportar CSV
        </button>
    </div>
</div>

<div class="grid-stats">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon primary"><i class="bi bi-journal-text"></i></div>
        </div>
        <div class="stat-card-label">Total de acciones</div>
        <div class="stat-card-value" id="stat-total">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon success"><i class="bi bi-calendar-day"></i></div>
        </div>
        <div class="stat-card-label">Hoy</div>
        <div class="stat-card-value" id="stat-hoy">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon warning"><i class="bi bi-calendar-week"></i></div>
        </div>
        <div class="stat-card-label">Últimos 7 días</div>
        <div class="stat-card-value" id="stat-semana">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon info"><i class="bi bi-activity"></i></div>
        </div>
        <div class="stat-card-label">Actividad</div>
        <canvas id="chart-actividad" style="max-height:60px;"></canvas>
    </div>
</div>

<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Acción, IP, detalle...">
            </div>
        </div>
        <div class="field">
            <label>Usuario</label>
            <select id="filtro-usuario">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="field">
            <label>Acción</label>
            <select id="filtro-accion">
                <option value="">Todas</option>
            </select>
        </div>
        <div class="field">
            <label>Tabla</label>
            <select id="filtro-tabla">
                <option value="">Todas</option>
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
        <button class="btn btn-primary btn-sm" onclick="Auditoria.cargar()">
            <i class="bi bi-funnel-fill"></i> Aplicar filtros
        </button>
        <button class="btn btn-secondary btn-sm" onclick="Auditoria.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
    </div>
</div>

<div class="card">
    <div id="tabla-auditoria">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
    <div id="paginacion" class="mt-4"></div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>