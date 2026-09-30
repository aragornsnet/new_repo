<?php
$tituloPagina = 'Inicio';
$subtituloPagina = 'Tu punto de venta';
$paginaActiva = 'dashboard';
require __DIR__ . '/../layouts/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title">👋 Hola, <?= h($usuario['nombre']) ?></h2>
        <p class="page-subtitle">Punto de venta: <strong><?= h($usuario['pv_nombre'] ?? 'Sin asignar') ?></strong></p>
    </div>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle-fill"></i>
    <div class="alert-body">
        <div class="alert-title">Bloque 3 completado</div>
        El POS completo, apertura/cierre de turno, calculadora y conteo por denominación se implementan en el <strong>Bloque 6</strong>.
    </div>
</div>

<div class="grid-stats">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon primary"><i class="bi bi-cash-coin"></i></div>
        </div>
        <div class="stat-card-label">Efectivo del día</div>
        <div class="stat-card-value">$0.00</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon success"><i class="bi bi-cart-check-fill"></i></div>
        </div>
        <div class="stat-card-label">Mis ventas hoy</div>
        <div class="stat-card-value">0</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon warning"><i class="bi bi-clock-history"></i></div>
        </div>
        <div class="stat-card-label">Estado del turno</div>
        <div class="stat-card-value text-lg">Sin turno</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon info"><i class="bi bi-box-seam"></i></div>
        </div>
        <div class="stat-card-label">Productos disponibles</div>
        <div class="stat-card-value">15</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-play-circle-fill"></i> Acciones rápidas</div>
    </div>
    <p class="text-muted mb-3">Estas acciones estarán operativas en el Bloque 6.</p>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-primary" disabled><i class="bi bi-play-circle"></i> Abrir turno</button>
        <button class="btn btn-secondary" disabled><i class="bi bi-cart"></i> Ir al POS</button>
        <button class="btn btn-secondary" disabled><i class="bi bi-receipt"></i> Mis ventas</button>
        <button class="btn btn-secondary" disabled><i class="bi bi-cash-coin"></i> Mi caja</button>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>