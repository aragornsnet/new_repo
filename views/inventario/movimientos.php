<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Historial de Movimientos';
$subtituloPagina = 'Todos los movimientos de inventario';
$paginaActiva = 'movimientos';
$scriptsExtra = [
    BASE_URL . 'public/js/movimientos.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Supervisor', 'Administrador'])) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">📋 Historial de movimientos</h2>
        <p class="page-subtitle">Todas las entradas, bajas, ajustes y transferencias</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary" onclick="Movimientos.exportar()">
            <i class="bi bi-file-earmark-excel"></i> Exportar CSV
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Producto, motivo, descripción...">
            </div>
        </div>
        <div class="field">
            <label>Tipo</label>
            <select id="filtro-tipo">
                <option value="">Todos</option>
                <option value="entrada">Entradas</option>
                <option value="baja">Bajas</option>
                <option value="ajuste">Ajustes</option>
                <option value="transferencia">Transferencias</option>
                <option value="salida">Salidas</option>
            </select>
        </div>
        <div class="field">
            <label>Punto de venta</label>
            <select id="filtro-pv">
                <option value="">Todos los PV</option>
            </select>
        </div>
        <div class="field">
            <label>Usuario</label>
            <select id="filtro-usuario">
                <option value="">Todos</option>
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
        <button class="btn btn-primary btn-sm" onclick="Movimientos.cargar(1)">
            <i class="bi bi-funnel-fill"></i> Aplicar filtros
        </button>
        <button class="btn btn-secondary btn-sm" onclick="Movimientos.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-list-ul"></i> Movimientos
            <span class="badge badge-neutral ml-2" id="total-movimientos">—</span>
        </div>
    </div>
    <div id="tabla-movimientos">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
    <div id="paginacion" class="mt-4"></div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>