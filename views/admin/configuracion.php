<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Configuración';
$subtituloPagina = 'Parámetros del sistema';
$paginaActiva = 'config';
$scriptsExtra = [
    BASE_URL . 'public/js/configuracion.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::esAdmin()) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">⚙️ Configuración del sistema</h2>
        <p class="page-subtitle">Ajusta el comportamiento del sistema según las necesidades del negocio</p>
    </div>
</div>

<div class="alert alert-info mb-4">
    <i class="bi bi-info-circle-fill"></i>
    <div class="alert-body">
        Los cambios se aplican inmediatamente después de guardar. La apariencia visual (colores, logo, tipografía) se configura en <a href="<?= BASE_URL ?>views/admin/personalizacion.php">Personalización</a>.
    </div>
</div>

<div class="tabs" id="tabs-config">
    <button class="tab active" data-tab="seguridad">
        <i class="bi bi-shield-lock"></i> Seguridad
    </button>
    <button class="tab" data-tab="pos">
        <i class="bi bi-cart-check"></i> POS
    </button>
    <button class="tab" data-tab="turno">
        <i class="bi bi-clock-history"></i> Turnos
    </button>
    <button class="tab" data-tab="inventario">
        <i class="bi bi-box-seam"></i> Inventario
    </button>
    <button class="tab" data-tab="divisas">
        <i class="bi bi-currency-exchange"></i> Divisas
    </button>
    <button class="tab" data-tab="transferencias">
        <i class="bi bi-bank"></i> Transferencias
    </button>
    <button class="tab" data-tab="notificaciones">
        <i class="bi bi-bell"></i> Notificaciones
    </button>
</div>

<div id="contenido-config">
    <div class="empty-state"><div class="spinner spinner-lg"></div></div>
</div>

<div class="save-bar" id="save-bar" style="display:none;">
    <div class="save-bar-inner">
        <div>
            <i class="bi bi-exclamation-circle-fill" style="color:var(--warning);"></i>
            <span>Tienes cambios sin guardar</span>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-secondary btn-sm" onclick="Configuracion.descartarCambios()">
                <i class="bi bi-arrow-counterclockwise"></i> Descartar
            </button>
            <button class="btn btn-primary btn-sm" onclick="Configuracion.guardar()">
                <i class="bi bi-check-lg"></i> Guardar cambios
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>