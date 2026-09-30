<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Mis Comprobantes';
$subtituloPagina = 'Comprobantes de tu turno actual';
$paginaActiva = 'comprobantes';
$scriptsExtra = [
    BASE_URL . 'public/js/comprobantes.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Vendedor'])) {
    redirigir('views/403.php');
}

// Verificar turno abierto
$turnoAbierto = Database::fetchOne("
    SELECT t.id, pv.nombre AS pv
    FROM turnos t
    JOIN puntos_venta pv ON pv.id = t.punto_venta_id
    WHERE t.usuario_id = ? AND t.estado = 'abierto'
    LIMIT 1
", [Auth::id()]);

if (!$turnoAbierto) {
    redirigir('views/vendedor/dashboard.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">📄 Mis comprobantes</h2>
        <p class="page-subtitle">
            <?= h($turnoAbierto['pv']) ?> · Turno #<?= (int) $turnoAbierto['id'] ?>
        </p>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Folio comprobante o comprador...">
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

<?php require __DIR__ . '/../layouts/footer.php'; ?>