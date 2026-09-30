<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Logs del sistema';
$subtituloPagina = 'Visor de logs y errores';
$paginaActiva = 'logs';
$scriptsExtra = [
    BASE_URL . 'public/js/logs.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::esAdmin()) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">📋 Logs del sistema</h2>
        <p class="page-subtitle">Visor de logs de errores y eventos</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary" onclick="Logs.recargar()">
            <i class="bi bi-arrow-clockwise"></i> Recargar
        </button>
    </div>
</div>

<!-- Estadísticas -->
<div class="grid-stats mb-4">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon primary"><i class="bi bi-file-earmark-text"></i></div>
        </div>
        <div class="stat-card-label">Archivos</div>
        <div class="stat-card-value" id="stat-archivos">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon info"><i class="bi bi-hdd"></i></div>
        </div>
        <div class="stat-card-label">Tamaño total</div>
        <div class="stat-card-value" id="stat-tamano">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon danger"><i class="bi bi-x-circle-fill"></i></div>
        </div>
        <div class="stat-card-label">Errores</div>
        <div class="stat-card-value" id="stat-errores">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
        </div>
        <div class="stat-card-label">Warnings</div>
        <div class="stat-card-value" id="stat-warnings">—</div>
    </div>
</div>

<!-- Selector de archivo -->
<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label for="archivo-select">Archivo de log</label>
            <select id="archivo-select" onchange="Logs.cambiarArchivo()">
                <option value="">Cargando...</option>
            </select>
        </div>
        <div class="field">
            <label for="filtro-nivel">Nivel</label>
            <select id="filtro-nivel" onchange="Logs.cargar(1)">
                <option value="">Todos</option>
                <option value="error">Errores</option>
                <option value="warning">Warnings</option>
                <option value="info">Info</option>
                <option value="debug">Debug</option>
            </select>
        </div>
        <div class="field span-2">
            <label for="filtro-q">Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Texto a buscar..." oninput="Logs.buscarDebounced()">
            </div>
        </div>
        <div class="field">
            <label>Orden</label>
            <select id="filtro-orden" onchange="Logs.cargar(1)">
                <option value="1">Más recientes primero</option>
                <option value="0">Más antiguos primero</option>
            </select>
        </div>
    </div>

    <div class="mt-3 d-flex gap-2 flex-wrap">
        <button class="btn btn-primary btn-sm" onclick="Logs.cargar(1)">
            <i class="bi bi-funnel-fill"></i> Aplicar filtros
        </button>
        <button class="btn btn-secondary btn-sm" onclick="Logs.limpiarFiltros()">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
        <button class="btn btn-secondary btn-sm" onclick="Logs.descargar()" id="btn-descargar" disabled>
            <i class="bi bi-download"></i> Descargar
        </button>
        <button class="btn btn-warning btn-sm" onclick="Logs.vaciar()" id="btn-vaciar" disabled>
            <i class="bi bi-eraser"></i> Vaciar
        </button>
        <button class="btn btn-danger btn-sm" onclick="Logs.eliminar()" id="btn-eliminar" disabled>
            <i class="bi bi-trash"></i> Eliminar
        </button>
    </div>
</div>

<!-- Info del archivo actual -->
<div id="info-archivo" class="mb-3"></div>

<!-- Visor de líneas -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-terminal"></i> Contenido
            <span class="badge badge-neutral ml-2" id="total-lineas">—</span>
        </div>
        <div class="text-muted text-sm" id="archivo-actual">—</div>
    </div>

    <div id="visor-logs">
        <div class="empty-state">
            <i class="bi bi-terminal"></i>
            <p class="text-muted">Selecciona un archivo para ver su contenido</p>
        </div>
    </div>

    <div id="paginacion" class="mt-4"></div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>