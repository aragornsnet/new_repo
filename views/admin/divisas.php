<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Divisas';
$subtituloPagina = 'Tasas de cambio y configuración';
$paginaActiva = 'divisas';
$scriptsExtra = [
    BASE_URL . 'public/js/divisas.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::esAdmin()) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">💱 Divisas</h2>
        <p class="page-subtitle">Gestiona las tasas de cambio de las divisas activas</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary" onclick="Divisas.recargar()">
            <i class="bi bi-arrow-clockwise"></i> Recargar
        </button>
    </div>
</div>

<div id="divisas-resumen" class="grid-stats mb-4">
    <div class="empty-state"><div class="spinner spinner-lg"></div></div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-currency-exchange"></i> Divisas configuradas</div>
    </div>
    <div id="tabla-divisas">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<div class="modal-backdrop" id="modal-historial-divisa">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-clock-history"></i> <span id="hist-divisa-titulo">Historial</span></div>
            <button class="modal-close" onclick="document.getElementById('modal-historial-divisa').classList.remove('active')">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body" id="hist-divisa-contenido">
            <div class="empty-state"><div class="spinner"></div></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>