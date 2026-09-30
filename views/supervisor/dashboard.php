<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Dashboard';
$subtituloPagina = 'Supervisión general de todos los puntos de venta';
$paginaActiva = 'dashboard';
$scriptsExtra = [
    BASE_URL . 'public/libs/chartjs/chart.min.js',
    BASE_URL . 'public/js/supervisor_dashboard.js',
];
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title">👋 Hola, <?= h($usuario['nombre']) ?></h2>
        <p class="page-subtitle">Resumen de la actividad del día · <?= fecha(date('Y-m-d'), 'l, d \d\e F \d\e Y') ?></p>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary" onclick="SupervisorDashboard.recargar()">
            <i class="bi bi-arrow-clockwise"></i> Recargar
        </button>
    </div>
</div>

<div id="loading-dashboard" class="loading-overlay">
    <div class="spinner spinner-lg"></div>
</div>

<div id="alertas-container" class="mb-4"></div>

<div class="grid-stats">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon primary"><i class="bi bi-cart-check-fill"></i></div>
        </div>
        <div class="stat-card-label">Ventas del día</div>
        <div class="stat-card-value" id="stat-ventas">—</div>
        <div class="stat-card-trend" id="stat-tickets">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon success"><i class="bi bi-cash-coin"></i></div>
        </div>
        <div class="stat-card-label">Efectivo</div>
        <div class="stat-card-value" id="stat-efectivo">—</div>
        <div class="stat-card-trend"><i class="bi bi-info-circle"></i> Caja del día</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon info"><i class="bi bi-bank"></i></div>
        </div>
        <div class="stat-card-label">Transferencias</div>
        <div class="stat-card-value" id="stat-transferencia">—</div>
        <div class="stat-card-trend"><i class="bi bi-info-circle"></i> Caja del día</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon warning"><i class="bi bi-clock-history"></i></div>
        </div>
        <div class="stat-card-label">Turnos abiertos</div>
        <div class="stat-card-value" id="stat-turnos">—</div>
        <div class="stat-card-trend"><i class="bi bi-info-circle"></i> Ahora mismo</div>
    </div>
</div>

<div class="grid-2 mb-4">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="bi bi-bar-chart-line-fill"></i> Ventas últimos 7 días</div>
        </div>
        <div style="position: relative; height: 240px;">
            <canvas id="chart-ventas"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="bi bi-trophy-fill"></i> Ranking de hoy</div>
        </div>
        <div id="ranking-hoy">
            <div class="empty-state"><div class="spinner"></div></div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-clock-history text-success"></i> Turnos abiertos ahora</div>
        <a href="<?= BASE_URL ?>views/supervisor/turnos.php" class="btn btn-ghost btn-sm">
            Ver todos <i class="bi bi-arrow-right"></i>
        </a>
    </div>
    <div id="turnos-abiertos">
        <div class="empty-state"><div class="spinner"></div></div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-bank text-warning"></i> Transferencias pendientes de verificar</div>
        <a href="<?= BASE_URL ?>views/supervisor/transferencias.php" class="btn btn-ghost btn-sm">
            Ver todas <i class="bi bi-arrow-right"></i>
        </a>
    </div>
    <div id="transf-pendientes">
        <div class="empty-state"><div class="spinner"></div></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-shop"></i> Ventas por punto (hoy)</div>
    </div>
    <div id="ventas-pv">
        <div class="empty-state"><div class="spinner"></div></div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>