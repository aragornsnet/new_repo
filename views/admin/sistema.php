<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Sistema';
$subtituloPagina = 'Información técnica y diagnóstico';
$paginaActiva = 'sistema';
$scriptsExtra = [
    BASE_URL . 'public/js/sistema.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::esAdmin()) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">🖥️ Información del sistema</h2>
        <p class="page-subtitle">Diagnóstico técnico y estado del servidor</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary" onclick="Sistema.recargar()">
            <i class="bi bi-arrow-clockwise"></i> Recargar
        </button>
    </div>
</div>

<div id="contenido-sistema">
    <div class="empty-state"><div class="spinner spinner-lg"></div></div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>