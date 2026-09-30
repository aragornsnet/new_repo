<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Comprobantes';
$subtituloPagina = 'Comprobantes de venta emitidos';
$paginaActiva = 'comprobantes';
$scriptsExtra = [
    BASE_URL . 'public/js/comprobantes.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Administrador', 'Supervisor'])) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">📄 Comprobantes de venta</h2>
        <p class="page-subtitle">Total: <span id="total-comprobantes">—</span> comprobantes</p>
    </div>
</div>

<!-- Resumen -->
<div class="grid-stats mb-4">
    <a href="#" class="stat-card" onclick="Comprobantes.atajo('emitido'); return false;" style="text-decoration:none;">
        <div class="stat-card-header">
            <div class="stat-card-icon success"><i class="bi bi-check-circle-fill"></i></div>
        </div>
        <div class="stat-card-label">Emitidos</div>
        <div class="stat-card-value" id="res-emitidos">—</div>
    </a>

    <a href="#" class="stat-card" onclick="Comprobantes.atajo('anulado'); return false;" style="text-decoration:none;">
        <div class="stat-card-header">
            <div class="stat-card-icon danger"><i class="bi bi-x-circle-fill"></i></div>
        </div>
        <div class="stat-card-label">Anulados</div>
        <div class="stat-card-value" id="res-anulados">—</div>
    </a>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Folio comprobante, folio venta, comprador o teléfono...">
            </div>
        </div>
        <div class="field">
            <label>Estado</label>
            <select id="filtro-estado">
                <option value="">Todos</option>
                <option value="emitido">Emitidos</option>
                <option value="anulado">Anulados</option>
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
        <button class="btn btn-primary btn-sm" onclick="Comprobantes.cargar()">
            <i class="bi bi-funnel-fill"></i> Aplicar filtros
        </button>
        <button class="btn btn-secondary btn-sm" onclick="Comprobantes.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-list-ul"></i> Comprobantes
            <span class="badge badge-neutral ml-2" id="total-comprobantes-2">—</span>
        </div>
    </div>
    <div id="tabla-comprobantes">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<!-- Modal detalle -->
<div class="modal-backdrop" id="modal-detalle-comprobante">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-receipt-cutoff"></i>
                <span id="det-titulo">Comprobante</span>
            </div>
            <button class="modal-close" onclick="Comprobantes.cerrarDetalle()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body" id="det-contenido">
            <div class="empty-state"><div class="spinner"></div></div>
        </div>
        <div class="modal-footer" id="det-footer">
            <button class="btn btn-secondary" onclick="Comprobantes.cerrarDetalle()">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal vista previa PDF -->
<div class="modal-backdrop" id="modal-pdf-comprobante"></div>

<!-- Modal anular -->
<div class="modal-backdrop" id="modal-anular">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-x-circle-fill text-danger"></i> Anular comprobante
            </div>
            <button class="modal-close" onclick="document.getElementById('modal-anular').classList.remove('active')">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="anular-id">
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>Esta acción anula el comprobante. <strong>No se puede deshacer.</strong></div>
            </div>
            <div class="field">
                <label for="anular-motivo">Motivo <span class="req">*</span></label>
                <textarea id="anular-motivo" maxlength="255" required placeholder="Explica el motivo" style="min-height:80px;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="document.getElementById('modal-anular').classList.remove('active')">
                Cancelar
            </button>
            <button class="btn btn-danger" id="btn-confirmar-anular" onclick="Comprobantes.confirmarAnular()">
                <i class="bi bi-x-lg"></i> Anular
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>