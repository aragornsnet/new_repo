<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Mi Caja';
$subtituloPagina = 'Efectivo de tu turno actual';
$paginaActiva = 'caja';
$scriptsExtra = [
    BASE_URL . 'public/js/mi_caja.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Vendedor', 'Supervisor', 'Administrador'])) {
    redirigir('views/403.php');
}

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
        <h2 class="page-title">💰 Mi caja</h2>
        <p class="page-subtitle">
            <?= h($turnoAbierto['pv']) ?> · Turno #<?= (int) $turnoAbierto['id'] ?>
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>views/vendedor/cerrar_turno.php" class="btn btn-danger">
            <i class="bi bi-lock-fill"></i> Cerrar turno
        </a>
    </div>
</div>

<div id="loading" class="loading-overlay">
    <div class="spinner spinner-lg"></div>
</div>

<div id="contenido-caja">
    <div class="empty-state"><div class="spinner spinner-lg"></div></div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>