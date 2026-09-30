<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Trazabilidad del Almacén';
$subtituloPagina = 'Historial completo de cada producto';
$paginaActiva = 'almacen-trazabilidad';
$scriptsExtra = [
    BASE_URL . 'public/js/almacen_trazabilidad.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Administrador', 'Almacenero'])) {
    redirigir('views/403.php');
}

$categorias = Database::fetchAll("
    SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre
");
?>

<div class="page-header">
    <div>
        <h2 class="page-title">🔎 Trazabilidad del almacén</h2>
        <p class="page-subtitle">
            Total: <span id="total-productos">—</span> productos
        </p>
    </div>
</div>

<div class="alert alert-info mb-4">
    <i class="bi bi-info-circle-fill"></i>
    <div class="alert-body">
        Consulta el <strong>historial completo</strong> de cada producto en el almacén:
        entradas, ajustes y despachos (transferencias). Solo lectura, con fines de auditoría.
    </div>
</div>

<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar producto</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Nombre o código...">
            </div>
        </div>
        <div class="field">
            <label>Categoría</label>
            <select id="filtro-cat">
                <option value="">Todas</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= h($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Filtros</label>
            <select id="filtro-tipo">
                <option value="">Todos los productos</option>
                <option value="con_mov">Solo con movimientos</option>
                <option value="con_des">Solo con ajustes</option>
            </select>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-secondary btn-sm" onclick="AlmacenTrazabilidad.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar filtros
        </button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-list-ul"></i> Productos
            <span class="badge badge-neutral ml-2" id="total-productos-2">—</span>
        </div>
    </div>
    <div id="tabla-trazabilidad">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: EXPEDIENTE DEL PRODUCTO                              -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-expediente">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-clock-history"></i>
                <span id="exp-titulo">Expediente del producto</span>
            </div>
            <button class="modal-close" onclick="AlmacenTrazabilidad.cerrarExpediente()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body" id="exp-contenido">
            <div class="empty-state"><div class="spinner"></div></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="AlmacenTrazabilidad.cerrarExpediente()">Cerrar</button>
            <button class="btn btn-primary" onclick="AlmacenTrazabilidad.exportar()">
                <i class="bi bi-file-earmark-excel"></i> Exportar CSV
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>