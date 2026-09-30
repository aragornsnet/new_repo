<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Puntos de Venta';
$subtituloPagina = 'Gestión de sucursales';
$paginaActiva = 'puntos';
$scriptsExtra = [
    BASE_URL . 'public/js/puntos_venta.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::esAdmin()) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">🏪 Puntos de venta</h2>
        <p class="page-subtitle">Total: <span id="total-pv">—</span></p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="PuntosVenta.abrirNuevo()">
            <i class="bi bi-plus-lg"></i> Nuevo punto de venta
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Nombre o dirección...">
            </div>
        </div>
        <div class="field">
            <label>Estado</label>
            <select id="filtro-estado">
                <option value="">Todos</option>
                <option value="activos">Solo activos</option>
                <option value="inactivos">Solo inactivos</option>
            </select>
        </div>
    </div>
</div>

<div id="lista-pv">
    <div class="empty-state"><div class="spinner spinner-lg"></div></div>
</div>

<div class="modal-backdrop" id="modal-pv">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-shop"></i>
                <span id="modal-titulo">Nuevo punto de venta</span>
            </div>
            <button class="modal-close" onclick="PuntosVenta.cerrarModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form id="form-pv" class="form" novalidate>
                <input type="hidden" id="pv-id">

                <div class="form-grid">
                    <div class="field span-full">
                        <label for="pv-nombre">Nombre <span class="req">*</span></label>
                        <input type="text" id="pv-nombre" maxlength="100" required placeholder="Ej: PV Centro">
                    </div>

                    <div class="field span-full">
                        <label for="pv-direccion">Dirección</label>
                        <input type="text" id="pv-direccion" maxlength="200" placeholder="Calle, número, municipio">
                    </div>

                    <div class="field">
                        <label for="pv-telefono">Teléfono</label>
                        <input type="text" id="pv-telefono" maxlength="30" placeholder="+53 7 1234567">
                    </div>

                    <div class="field">
                        <label>Estado</label>
                        <label class="check">
                            <input type="checkbox" id="pv-activo" checked>
                            <span>Activo (puede recibir ventas y turnos)</span>
                        </label>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="PuntosVenta.cerrarModal()">Cancelar</button>
            <button class="btn btn-primary" id="btn-guardar-pv" onclick="PuntosVenta.guardar()">
                <i class="bi bi-check-lg"></i> Guardar
            </button>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="modal-detalle">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-info-circle"></i> Detalle del PV</div>
            <button class="modal-close" onclick="PuntosVenta.cerrarDetalle()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body" id="detalle-contenido">
            <div class="empty-state"><div class="spinner"></div></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>