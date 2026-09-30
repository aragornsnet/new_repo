<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Dashboard';
$subtituloPagina = 'Vista general del sistema';
$paginaActiva = 'dashboard';
$scriptsExtra = [
    BASE_URL . 'public/libs/chartjs/chart.min.js',
    BASE_URL . 'public/js/dashboard_admin.js',
];
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title">👋 Hola, <?= h($usuario['nombre']) ?></h2>
        <p class="page-subtitle">Resumen de la actividad de hoy · <?= fecha(date('Y-m-d'), 'l, d \d\e F \d\e Y') ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>views/admin/usuarios.php" class="btn btn-secondary">
            <i class="bi bi-people-fill"></i> Usuarios
        </a>
        <button class="btn btn-primary" onclick="DashboardAdmin.recargar()">
            <i class="bi bi-arrow-clockwise"></i> Recargar
        </button>
    </div>
</div>

<div id="loading" class="loading-overlay">
    <div class="spinner spinner-lg"></div>
</div>

<div class="grid-stats">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon primary"><i class="bi bi-cart-check-fill"></i></div>
        </div>
        <div class="stat-card-label">Ventas del día</div>
        <div class="stat-card-value" id="stat-ventas-dia">—</div>
        <div class="stat-card-trend" id="stat-tickets">—</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon success"><i class="bi bi-cash-coin"></i></div>
        </div>
        <div class="stat-card-label">Efectivo hoy</div>
        <div class="stat-card-value" id="stat-efectivo">—</div>
        <div class="stat-card-trend"><i class="bi bi-info-circle"></i> Caja del día</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon info"><i class="bi bi-bank"></i></div>
        </div>
        <div class="stat-card-label">Transferencias hoy</div>
        <div class="stat-card-value" id="stat-transferencia">—</div>
        <div class="stat-card-trend"><i class="bi bi-info-circle"></i> Caja del día</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon warning"><i class="bi bi-graph-up-arrow"></i></div>
        </div>
        <div class="stat-card-label">Ventas del mes</div>
        <div class="stat-card-value" id="stat-ventas-mes">—</div>
        <div class="stat-card-trend" id="stat-variacion">—</div>
    </div>
</div>

<div class="grid-stats">
    <a href="<?= BASE_URL ?>views/inventario/index.php?filtro=bajo" class="stat-card" style="text-decoration:none;">
        <div class="stat-card-header">
            <div class="stat-card-icon warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
        </div>
        <div class="stat-card-label">Stock bajo</div>
        <div class="stat-card-value" id="alerta-stock-bajo">—</div>
    </a>

    <a href="<?= BASE_URL ?>views/inventario/index.php?filtro=negativo" class="stat-card" style="text-decoration:none;">
        <div class="stat-card-header">
            <div class="stat-card-icon danger"><i class="bi bi-x-octagon-fill"></i></div>
        </div>
        <div class="stat-card-label">Stock negativo</div>
        <div class="stat-card-value" id="alerta-stock-negativo">—</div>
    </a>

    <a href="<?= BASE_URL ?>views/admin/transferencias.php" class="stat-card" style="text-decoration:none;">
        <div class="stat-card-header">
            <div class="stat-card-icon info"><i class="bi bi-bank"></i></div>
        </div>
        <div class="stat-card-label">Transferencias pendientes</div>
        <div class="stat-card-value" id="alerta-transf">—</div>
    </a>

    <a href="<?= BASE_URL ?>views/admin/turnos.php" class="stat-card" style="text-decoration:none;">
        <div class="stat-card-header">
            <div class="stat-card-icon success"><i class="bi bi-clock-history"></i></div>
        </div>
        <div class="stat-card-label">Turnos abiertos</div>
        <div class="stat-card-value" id="alerta-turnos">—</div>
    </a>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="bi bi-bar-chart-line-fill"></i> Ventas últimos 7 días</div>
        </div>
        <div style="position: relative; height: 260px;">
            <canvas id="chart-ventas"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="bi bi-trophy-fill"></i> Top 5 productos del mes</div>
        </div>
        <div id="top-productos">
            <div class="empty-state"><div class="spinner"></div></div>
        </div>
    </div>
</div>

<div class="grid-2 mt-4">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="bi bi-people-fill"></i> Ranking vendedores (hoy)</div>
        </div>
        <div id="ranking-hoy">
            <div class="empty-state"><div class="spinner"></div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="bi bi-shop"></i> Ventas por punto (mes)</div>
        </div>
        <div id="ventas-pv">
            <div class="empty-state"><div class="spinner"></div></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>