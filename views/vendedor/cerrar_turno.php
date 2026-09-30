<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Cerrar Turno';
$subtituloPagina = 'Cierre de caja y cuadre';
$paginaActiva = 'cerrar';
$scriptsExtra = [
    BASE_URL . 'public/js/cerrar_turno.js',
];
$cssExtra = [
    BASE_URL . 'public/css/pos.css',
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

<div class="container" style="max-width: 900px; margin: 0 auto;">

    <div class="page-header">
        <div>
            <h2 class="page-title">🔒 Cerrar turno</h2>
            <p class="page-subtitle">Cuadre de caja y cierre</p>
        </div>
        <div class="page-actions">
            <a href="<?= BASE_URL ?>views/vendedor/dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div id="loading" class="loading-overlay">
        <div class="spinner spinner-lg"></div>
    </div>

    <!-- Contenido dinámico -->
    <div id="contenido-cierre">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>

</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>