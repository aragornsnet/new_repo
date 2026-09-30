<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Mis Ventas';
$subtituloPagina = 'Ventas de tu turno actual';
$paginaActiva = 'ventas';
$scriptsExtra = [
    BASE_URL . 'public/js/mis_ventas.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Vendedor', 'Supervisor', 'Administrador'])) {
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
        <h2 class="page-title">🧾 Mis ventas</h2>
        <p class="page-subtitle">
            <?= h($turnoAbierto['pv']) ?> · Turno #<?= (int) $turnoAbierto['id'] ?>
        </p>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary" onclick="MisVentas.abrirPendientesComprobante()">
            <i class="bi bi-receipt-cutoff"></i> Emitir comprobante
        </button>
        <a href="<?= BASE_URL ?>views/vendedor/pos.php" class="btn btn-primary">
            <i class="bi bi-cart-plus"></i> Nueva venta
        </a>
        <button class="btn btn-secondary" onclick="MisVentas.exportar()">
            <i class="bi bi-file-earmark-excel"></i> Exportar
        </button>
    </div>
</div>

<!-- Stats del turno -->
<div class="grid-stats mb-4">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon primary"><i class="bi bi-receipt"></i></div>
        </div>
        <div class="stat-card-label">Ventas del turno</div>
        <div class="stat-card-value" id="tot-num">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon success"><i class="bi bi-cash-coin"></i></div>
        </div>
        <div class="stat-card-label">Total vendido</div>
        <div class="stat-card-value" id="tot-total">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon warning"><i class="bi bi-cash"></i></div>
        </div>
        <div class="stat-card-label">Efectivo</div>
        <div class="stat-card-value" id="tot-efectivo">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon info"><i class="bi bi-bank"></i></div>
        </div>
        <div class="stat-card-label">Transferencias</div>
        <div class="stat-card-value" id="tot-transf">—</div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar folio</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Ej: V-20260915...">
            </div>
        </div>
        <div class="field">
            <label>Método de pago</label>
            <select id="filtro-metodo">
                <option value="">Todos</option>
                <option value="efectivo">Efectivo</option>
                <option value="transferencia">Transferencia</option>
            </select>
        </div>
        <div class="field">
            <label>Estado</label>
            <select id="filtro-estado">
                <option value="">Todas</option>
                <option value="completada">Completadas</option>
                <option value="cancelada">Canceladas</option>
            </select>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn btn-secondary btn-sm" onclick="MisVentas.limpiar()">
                <i class="bi bi-x-circle"></i> Limpiar
            </button>
        </div>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-list-ul"></i> Ventas
            <span class="badge badge-neutral ml-2" id="total-ventas">—</span>
        </div>
    </div>
    <div id="tabla-ventas">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>