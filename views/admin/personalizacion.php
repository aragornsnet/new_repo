<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Personalización';
$subtituloPagina = 'Apariencia y configuración del sistema';
$paginaActiva = 'personalizar';
$scriptsExtra = [
    BASE_URL . 'public/js/personalizacion.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::esAdmin()) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">🎨 Personalización</h2>
        <p class="page-subtitle">Personaliza la apariencia, marca y configuración del sistema</p>
    </div>
</div>

<div class="tabs" id="tabs-personalizacion">
    <button class="tab active" data-tab="identidad">
        <i class="bi bi-building"></i> Identidad
    </button>
    <button class="tab" data-tab="contacto">
        <i class="bi bi-telephone"></i> Contacto
    </button>
    <button class="tab" data-tab="colores">
        <i class="bi bi-palette"></i> Colores
    </button>
    <button class="tab" data-tab="moneda">
        <i class="bi bi-cash-coin"></i> Moneda y formato
    </button>
    <button class="tab" data-tab="tema">
        <i class="bi bi-paint-bucket"></i> Tema
    </button>
    <button class="tab" data-tab="login">
        <i class="bi bi-box-arrow-in-right"></i> Login
    </button>
    <button class="tab" data-tab="documentos">
        <i class="bi bi-file-pdf"></i> Documentos
    </button>
</div>

<div id="contenido-personalizacion">
    <div class="empty-state"><div class="spinner spinner-lg"></div></div>
</div>

<div class="save-bar" id="save-bar" style="display:none;">
    <div class="save-bar-inner">
        <div>
            <i class="bi bi-exclamation-circle-fill" style="color:var(--warning);"></i>
            <span>Tienes cambios sin guardar</span>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-secondary btn-sm" onclick="Personalizacion.descartarCambios()">
                <i class="bi bi-arrow-counterclockwise"></i> Descartar
            </button>
            <button class="btn btn-primary btn-sm" onclick="Personalizacion.guardar()">
                <i class="bi bi-check-lg"></i> Guardar cambios
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>