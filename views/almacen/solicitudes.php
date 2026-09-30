<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Solicitudes de Traslado';
$subtituloPagina = 'Gestión de solicitudes de los puntos de venta';
$paginaActiva = 'solicitudes';
$scriptsExtra = [
    BASE_URL . 'public/js/almacen_solicitudes.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Administrador', 'Almacenero'])) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">🔄 Solicitudes de traslado</h2>
        <p class="page-subtitle">
            Total: <span id="total-solicitudes">—</span> solicitudes
        </p>
    </div>
</div>

<div class="alert alert-info mb-4">
    <i class="bi bi-info-circle-fill"></i>
    <div class="alert-body">
        <strong>Flujo:</strong> los supervisores crean solicitudes desde sus PV. Como almacenero puedes
        <strong>aprobar</strong> (total o parcial), <strong>rechazar</strong> con motivo, y
        <strong>despachar</strong> la mercancía. El PV confirma la recepción.
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Folio, PV o motivo...">
            </div>
        </div>
        <div class="field">
            <label>Estado</label>
            <select id="filtro-estado">
                <option value="">Todos</option>
                <option value="solicitado">Solicitados</option>
                <option value="aprobado">Aprobados</option>
                <option value="despachado">Despachados</option>
                <option value="despachado_parcial">Despachados parcial</option>
                <option value="recibido">Recibidos</option>
                <option value="rechazado">Rechazados</option>
                <option value="cancelado">Cancelados</option>
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
        <button class="btn btn-primary btn-sm" onclick="AlmacenSolicitudes.cargar()">
            <i class="bi bi-funnel-fill"></i> Aplicar filtros
        </button>
        <button class="btn btn-secondary btn-sm" onclick="AlmacenSolicitudes.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-list-ul"></i> Solicitudes
            <span class="badge badge-neutral ml-2" id="total-solicitudes-2">—</span>
        </div>
    </div>
    <div id="tabla-solicitudes">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: VER DETALLE + ACCIONES -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-solicitud">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-arrow-left-right"></i>
                <span id="sol-titulo">Solicitud</span>
            </div>
            <button class="modal-close" onclick="AlmacenSolicitudes.cerrarModal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body" id="sol-contenido">
            <div class="empty-state"><div class="spinner"></div></div>
        </div>
        <div class="modal-footer" id="sol-footer"></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: RECHAZAR -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-rechazar">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-x-circle-fill text-danger"></i> Rechazar solicitud
            </div>
            <button class="modal-close" onclick="document.getElementById('modal-rechazar').classList.remove('active')">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="rech-id">
            <p class="text-muted mb-3">
                Indica el motivo del rechazo. El supervisor recibirá una notificación.
            </p>
            <div class="field">
                <label for="rech-motivo">Motivo <span class="req">*</span></label>
                <textarea id="rech-motivo" maxlength="255" placeholder="Ej: No hay stock suficiente, productos descontinuados, etc." style="min-height:80px;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="document.getElementById('modal-rechazar').classList.remove('active')">
                Cancelar
            </button>
            <button class="btn btn-danger" id="btn-confirmar-rechazo" onclick="AlmacenSolicitudes.confirmarRechazo()">
                <i class="bi bi-x-lg"></i> Rechazar
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>