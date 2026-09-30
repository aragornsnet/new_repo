<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Inicio';
$subtituloPagina = 'Tu punto de venta';
$paginaActiva = 'dashboard';
$scriptsExtra = [
    BASE_URL . 'public/js/vendedor_dashboard.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Vendedor', 'Supervisor', 'Administrador'])) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">👋 Hola, <?= h($usuario['nombre']) ?></h2>
        <p class="page-subtitle">Punto de venta: <strong><?= h($usuario['pv_nombre'] ?? 'Sin asignar') ?></strong></p>
    </div>
</div>

<!-- Loading -->
<div id="loading" class="loading-overlay">
    <div class="spinner spinner-lg"></div>
</div>

<!-- Contenido dinámico -->
<div id="contenido-dashboard">
    <div class="empty-state"><div class="spinner spinner-lg"></div></div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>