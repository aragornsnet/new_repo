<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Transferencias';
$subtituloPagina = 'Verificación de pagos por transferencia';
$paginaActiva = 'transferencias';
$scriptsExtra = [
    BASE_URL . 'public/js/transferencias.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Supervisor', 'Administrador'])) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">🏦 Transferencias</h2>
        <p class="page-subtitle">Verifica y rechaza transferencias pendientes</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-success" onclick="Transferencias.abrirVerificacionMasiva()">
            <i class="bi bi-check2-all"></i> Verificación masiva
        </button>
        <button class="btn btn-secondary" onclick="Transferencias.cargar()">
            <i class="bi bi-arrow-clockwise"></i> Recargar
        </button>
    </div>
</div>

<!-- Resumen -->
<div class="grid-stats mb-4">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon warning"><i class="bi bi-hourglass-split"></i></div>
        </div>
        <div class="stat-card-label">Pendientes</div>
        <div class="stat-card-value" id="res-pendientes">—</div>
        <div class="stat-card-trend" id="res-pend-monto">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon success"><i class="bi bi-check-circle-fill"></i></div>
        </div>
        <div class="stat-card-label">Verificadas</div>
        <div class="stat-card-value" id="res-verificadas">—</div>
        <div class="stat-card-trend" id="res-verif-monto">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon danger"><i class="bi bi-x-circle-fill"></i></div>
        </div>
        <div class="stat-card-label">Rechazadas</div>
        <div class="stat-card-value" id="res-rechazadas">—</div>
        <div class="stat-card-trend" id="res-rech-monto">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon info"><i class="bi bi-bank"></i></div>
        </div>
        <div class="stat-card-label">Total del día</div>
        <div class="stat-card-value" id="res-total">—</div>
        <div class="stat-card-trend"><i class="bi bi-info-circle"></i> Monto total</div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Folio, referencia o vendedor...">
            </div>
        </div>
        <div class="field">
            <label>Estado</label>
            <select id="filtro-estado">
                <option value="pendiente" selected>Pendientes</option>
                <option value="verificada">Verificadas</option>
                <option value="rechazada">Rechazadas</option>
                <option value="">Todas</option>
            </select>
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
            <label>Método</label>
            <select id="filtro-metodo">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="field">
            <label>Desde</label>
            <input type="date" id="filtro-desde" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" id="filtro-hasta" value="<?= date('Y-m-d') ?>">
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary btn-sm" onclick="Transferencias.cargar()">
            <i class="bi bi-funnel-fill"></i> Aplicar filtros
        </button>
        <button class="btn btn-secondary btn-sm" onclick="Transferencias.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-list-ul"></i> Transferencias
            <span class="badge badge-neutral ml-2" id="total-transf">—</span>
        </div>
    </div>
    <div id="tabla-transf">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<!-- Modal verificación masiva -->
<div class="modal-backdrop" id="modal-masiva">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-check2-all"></i> Verificación masiva</div>
            <button class="modal-close" onclick="Transferencias.cerrarMasiva()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div>Selecciona las transferencias que quieres verificar y confirma.</div>
            </div>
            <div id="lista-masiva">
                <div class="empty-state"><div class="spinner"></div></div>
            </div>
        </div>
        <div class="modal-footer">
            <div style="flex:1;display:flex;align-items:center;gap:12px;">
                <span class="text-sm text-muted">Seleccionadas:</span>
                <strong id="masiva-total">0</strong>
                <span class="text-sm text-muted">· Monto:</span>
                <strong id="masiva-monto">$0.00</strong>
            </div>
            <button class="btn btn-secondary" onclick="Transferencias.cerrarMasiva()">Cancelar</button>
            <button class="btn btn-success" id="btn-verificar-masiva" onclick="Transferencias.verificarMasiva()" disabled>
                <i class="bi bi-check-lg"></i> Verificar seleccionadas
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>