<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Categorías';
$subtituloPagina = 'Clasificación de productos';
$paginaActiva = 'categorias';
$scriptsExtra = [
    BASE_URL . 'public/js/categorias.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::esAdmin()) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">🏷️ Categorías</h2>
        <p class="page-subtitle">Total: <span id="total-cat">—</span></p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="Categorias.abrirNuevo()">
            <i class="bi bi-plus-lg"></i> Nueva categoría
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Nombre o descripción...">
            </div>
        </div>
        <div class="field">
            <label>Estado</label>
            <select id="filtro-estado">
                <option value="">Todas</option>
                <option value="activos">Solo activas</option>
                <option value="inactivos">Solo inactivas</option>
            </select>
        </div>
    </div>
</div>

<div class="card">
    <div id="tabla-cat">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<div class="modal-backdrop" id="modal-cat">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-tag-fill"></i>
                <span id="modal-titulo">Nueva categoría</span>
            </div>
            <button class="modal-close" onclick="Categorias.cerrarModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form id="form-cat" class="form" novalidate>
                <input type="hidden" id="cat-id">
                <div class="field">
                    <label for="cat-nombre">Nombre <span class="req">*</span></label>
                    <input type="text" id="cat-nombre" maxlength="80" required placeholder="Ej: Bebidas">
                </div>
                <div class="field">
                    <label for="cat-descripcion">Descripción</label>
                    <textarea id="cat-descripcion" maxlength="150" placeholder="Breve descripción (opcional)"></textarea>
                </div>
                <div class="field">
                    <label class="check">
                        <input type="checkbox" id="cat-activo" checked>
                        <span>Categoría activa</span>
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="Categorias.cerrarModal()">Cancelar</button>
            <button class="btn btn-primary" id="btn-guardar-cat" onclick="Categorias.guardar()">
                <i class="bi bi-check-lg"></i> Guardar
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>