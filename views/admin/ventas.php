<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Ventas Globales';
$subtituloPagina = 'Todas las ventas de todos los puntos de venta';
$paginaActiva = 'ventas';
$scriptsExtra = [
    BASE_URL . 'public/js/ventas_supervisor.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Administrador', 'Supervisor'])) {
    redirigir('views/403.php');
}

$puntos = Database::fetchAll("SELECT id, nombre FROM puntos_venta WHERE activo = 1 ORDER BY nombre");
$vendedores = Database::fetchAll("
    SELECT u.id, u.nombre, pv.nombre AS pv
    FROM usuarios u
    LEFT JOIN puntos_venta pv ON pv.id = u.punto_venta_id
    WHERE u.rol_id = 3 AND u.activo = 1
    ORDER BY u.nombre
");
?>

<div class="page-header">
    <div>
        <h2 class="page-title">🛒 Ventas globales</h2>
        <p class="page-subtitle">Todas las ventas registradas en el sistema</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary" onclick="VentasSupervisor.exportar()">
            <i class="bi bi-file-earmark-excel"></i> Exportar CSV
        </button>
    </div>
</div>

<!-- Stats -->
<div class="grid-stats mb-4">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon primary"><i class="bi bi-cart-check-fill"></i></div>
        </div>
        <div class="stat-card-label">Total ventas</div>
        <div class="stat-card-value" id="stat-total">—</div>
        <div class="stat-card-trend" id="stat-num">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon success"><i class="bi bi-cash-coin"></i></div>
        </div>
        <div class="stat-card-label">Efectivo</div>
        <div class="stat-card-value" id="stat-efectivo">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon info"><i class="bi bi-bank"></i></div>
        </div>
        <div class="stat-card-label">Transferencias</div>
        <div class="stat-card-value" id="stat-transf">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon warning"><i class="bi bi-x-circle-fill"></i></div>
        </div>
        <div class="stat-card-label">Canceladas</div>
        <div class="stat-card-value" id="stat-canceladas">—</div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Folio o vendedor...">
            </div>
        </div>
        <div class="field">
            <label>Punto de venta</label>
            <select id="filtro-pv">
                <option value="">Todos los PV</option>
                <?php foreach ($puntos as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"><?= h($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Vendedor</label>
            <select id="filtro-vendedor">
                <option value="">Todos</option>
                <?php foreach ($vendedores as $v): ?>
                    <option value="<?= (int) $v['id'] ?>"><?= h($v['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
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
        <button class="btn btn-primary btn-sm" onclick="VentasSupervisor.cargar()">
            <i class="bi bi-funnel-fill"></i> Aplicar filtros
        </button>
        <button class="btn btn-secondary btn-sm" onclick="VentasSupervisor.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
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