<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Inventario';
$subtituloPagina = 'Stock por punto de venta';
$paginaActiva = 'inventario';
$scriptsExtra = [
    BASE_URL . 'public/js/inventario.js',
];
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title">📦 Inventario</h2>
        <p class="page-subtitle">Vista de stock por producto y punto de venta</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>views/inventario/entradas.php" class="btn btn-success">
            <i class="bi bi-box-arrow-in-down"></i> Registrar entrada
        </a>
        <a href="<?= BASE_URL ?>views/inventario/bajas.php" class="btn btn-danger">
            <i class="bi bi-box-arrow-up"></i> Registrar baja
        </a>
    </div>
</div>

<div class="grid-stats mb-4" id="resumen-pv">
    <div class="empty-state"><div class="spinner spinner-lg"></div></div>
</div>

<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar producto</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Nombre o código de barras...">
            </div>
        </div>
        <div class="field">
            <label>Punto de venta</label>
            <select id="filtro-pv">
                <option value="">Todos los PV</option>
            </select>
        </div>
        <div class="field">
            <label>Categoría</label>
            <select id="filtro-cat">
                <option value="">Todas</option>
            </select>
        </div>
        <div class="field">
            <label>Estado</label>
            <select id="filtro-estado">
                <option value="">Todos</option>
                <option value="bajo">Solo stock bajo</option>
                <option value="negativo">Solo negativo</option>
                <option value="ok">Solo OK</option>
            </select>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-secondary btn-sm" onclick="Inventario.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar filtros
        </button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-box-seam"></i> Stock detallado
            <span class="badge badge-neutral ml-2" id="total-items">—</span>
        </div>
    </div>
    <div id="tabla-inventario">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>