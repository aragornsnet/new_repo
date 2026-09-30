<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Dashboard Almacén';
$subtituloPagina = 'Resumen del almacén central';
$paginaActiva = 'dashboard';
$scriptsExtra = [
    BASE_URL . 'public/js/almacen_dashboard.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Administrador', 'Almacenero'])) {
    redirigir('views/403.php');
}

// ============================================================
// Datos para el dashboard
// ============================================================
$almacenId = (int) Config::int('almacen_id', 0);

if ($almacenId <= 0) {
    $almacenId = (int) Database::fetchValue(
        "SELECT id FROM puntos_venta WHERE es_almacen = 1 AND activo = 1 LIMIT 1",
        [],
        0
    );
}

if ($almacenId <= 0) {
    echo '<div class="alert alert-danger">No hay un almacén configurado. Contacta al administrador.</div>';
    require __DIR__ . '/../layouts/footer.php';
    exit;
}

// Info del almacén
$almacen = Database::fetchOne("
    SELECT id, nombre, direccion FROM puntos_venta WHERE id = ?
", [$almacenId]);

// Total de productos activos
$totalProductos = (int) Database::fetchValue("
    SELECT COUNT(*) FROM productos WHERE activo = 1
", [], 0);

// Productos con stock bajo en el almacén
$productosBajos = (int) Database::fetchValue("
    SELECT COUNT(*) 
    FROM stock_punto_venta spv
    JOIN productos p ON p.id = spv.producto_id
    WHERE spv.punto_venta_id = ? AND p.activo = 1
      AND spv.stock <= p.stock_minimo AND spv.stock >= 0
", [$almacenId], 0);

// Productos con stock negativo
$productosNegativos = (int) Database::fetchValue("
    SELECT COUNT(*) 
    FROM stock_punto_venta spv
    JOIN productos p ON p.id = spv.producto_id
    WHERE spv.punto_venta_id = ? AND p.activo = 1
      AND spv.stock < 0
", [$almacenId], 0);

// Unidades totales en stock
$stockTotal = (int) Database::fetchValue("
    SELECT COALESCE(SUM(spv.stock), 0) 
    FROM stock_punto_venta spv
    JOIN productos p ON p.id = spv.producto_id
    WHERE spv.punto_venta_id = ? AND p.activo = 1
", [$almacenId], 0);

// Solicitudes pendientes (solicitado + aprobado)
$solicitudesPendientes = (int) Database::fetchValue("
    SELECT COUNT(*) FROM solicitudes_traslado
    WHERE estado IN ('solicitado', 'aprobado')
", [], 0);

// Entradas registradas hoy
$entradasHoy = (int) Database::fetchValue("
    SELECT COUNT(*) FROM movimientos
    WHERE punto_venta_id = ? AND tipo = 'entrada'
      AND DATE(fecha) = CURDATE()
", [$almacenId], 0);

// Últimas 5 entradas al almacén
$ultimasEntradas = Database::fetchAll("
    SELECT 
        m.id, m.cantidad, m.motivo, m.fecha,
        p.nombre AS producto,
        u.nombre AS usuario
    FROM movimientos m
    JOIN productos p ON p.id = m.producto_id
    JOIN usuarios u ON u.id = m.usuario_id
    WHERE m.punto_venta_id = ? AND m.tipo = 'entrada'
    ORDER BY m.fecha DESC
    LIMIT 5
", [$almacenId]);

// Últimas 5 solicitudes
$ultimasSolicitudes = Database::fetchAll("
    SELECT 
        s.id, s.folio, s.estado, s.total_unidades_solicitadas,
        s.total_unidades_despachadas, s.fecha_solicitud,
        pv.nombre AS pv,
        u.nombre AS solicitante
    FROM solicitudes_traslado s
    JOIN puntos_venta pv ON pv.id = s.punto_venta_id
    JOIN usuarios u ON u.id = s.solicitado_por
    ORDER BY s.fecha_solicitud DESC
    LIMIT 5
");
?>

<div class="page-header">
    <div>
        <h2 class="page-title">📦 Almacén Central</h2>
        <p class="page-subtitle">
            <?= h($almacen['nombre']) ?>
            <?php if ($almacen['direccion']): ?>
                · <?= h($almacen['direccion']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>views/almacen/entradas.php" class="btn btn-success">
            <i class="bi bi-box-arrow-in-down"></i> Nueva entrada
        </a>
        <a href="<?= BASE_URL ?>views/almacen/entradas.php?nuevo=1" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nuevo producto
        </a>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MÉTRICAS PRINCIPALES -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="grid-stats mb-4">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon primary"><i class="bi bi-box-seam"></i></div>
        </div>
        <div class="stat-card-label">Productos activos</div>
        <div class="stat-card-value"><?= number_format($totalProductos) ?></div>
        <div class="stat-card-trend"><i class="bi bi-info-circle"></i> En catálogo</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon success"><i class="bi bi-stack"></i></div>
        </div>
        <div class="stat-card-label">Unidades en almacén</div>
        <div class="stat-card-value"><?= number_format($stockTotal) ?></div>
        <div class="stat-card-trend"><i class="bi bi-info-circle"></i> Stock total</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
        </div>
        <div class="stat-card-label">Productos con stock bajo</div>
        <div class="stat-card-value"><?= number_format($productosBajos) ?></div>
        <div class="stat-card-trend">
            <a href="<?= BASE_URL ?>views/almacen/inventario.php?filtro=bajo">Ver detalle</a>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon danger"><i class="bi bi-x-octagon-fill"></i></div>
        </div>
        <div class="stat-card-label">Stock negativo</div>
        <div class="stat-card-value"><?= number_format($productosNegativos) ?></div>
        <div class="stat-card-trend">
            <a href="<?= BASE_URL ?>views/almacen/inventario.php?filtro=negativo">Ver detalle</a>
        </div>
    </div>
</div>

<div class="grid-stats mb-4">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon info"><i class="bi bi-arrow-left-right"></i></div>
        </div>
        <div class="stat-card-label">Solicitudes pendientes</div>
        <div class="stat-card-value"><?= number_format($solicitudesPendientes) ?></div>
        <div class="stat-card-trend">
            <a href="<?= BASE_URL ?>views/almacen/solicitudes.php?estado=solicitado">
                Ver solicitudes
            </a>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon success"><i class="bi bi-box-arrow-in-down"></i></div>
        </div>
        <div class="stat-card-label">Entradas hoy</div>
        <div class="stat-card-value"><?= number_format($entradasHoy) ?></div>
        <div class="stat-card-trend"><i class="bi bi-info-circle"></i> Movimientos</div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- ÚLTIMAS ENTRADAS -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="bi bi-box-arrow-in-down text-success"></i> Últimas entradas
            </div>
            <a href="<?= BASE_URL ?>views/almacen/entradas.php" class="btn btn-ghost btn-sm">
                Ver todas <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        <?php if (empty($ultimasEntradas)): ?>
            <div class="empty-state" style="padding:30px 15px;">
                <i class="bi bi-inbox" style="font-size:40px;"></i>
                <p class="text-muted mt-2">Sin entradas registradas</p>
            </div>
        <?php else: ?>
            <?php foreach ($ultimasEntradas as $e): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);">
                    <div style="width:36px;height:36px;border-radius:50%;background:var(--success-light);color:var(--success);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;">
                            <?= h($e['producto']) ?>
                        </div>
                        <div class="text-xs text-muted">
                            <?= h($e['motivo'] ?: 'Sin motivo') ?> · <?= h($e['usuario']) ?>
                        </div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-weight:700;font-size:14px;color:var(--success);">
                            +<?= (int)$e['cantidad'] ?>
                        </div>
                        <div class="text-xs text-muted"><?= fecha($e['fecha'], 'd/m H:i') ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="bi bi-arrow-left-right text-info"></i> Últimas solicitudes
            </div>
            <a href="<?= BASE_URL ?>views/almacen/solicitudes.php" class="btn btn-ghost btn-sm">
                Ver todas <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        <?php if (empty($ultimasSolicitudes)): ?>
            <div class="empty-state" style="padding:30px 15px;">
                <i class="bi bi-inbox" style="font-size:40px;"></i>
                <p class="text-muted mt-2">Sin solicitudes</p>
            </div>
        <?php else: ?>
            <?php
            $badges = [
                'solicitado'         => ['warning', 'Solicitado'],
                'aprobado'           => ['info',    'Aprobado'],
                'despachado'         => ['primary', 'Despachado'],
                'despachado_parcial' => ['warning', 'Parcial'],
                'recibido'           => ['success', 'Recibido'],
                'rechazado'          => ['danger',  'Rechazado'],
                'cancelado'          => ['neutral', 'Cancelado'],
            ];
            ?>
            <?php foreach ($ultimasSolicitudes as $s): ?>
                <?php [$color, $label] = $badges[$s['estado']] ?? ['neutral', $s['estado']]; ?>
                <div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);">
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;">
                            <?= h($s['folio']) ?>
                        </div>
                        <div class="text-xs text-muted">
                            <?= h($s['pv']) ?> · <?= h($s['solicitante']) ?>
                        </div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <span class="badge badge-<?= $color ?>"><?= $label ?></span>
                        <div class="text-xs text-muted mt-1">
                            <?= (int)$s['total_unidades_solicitadas'] ?> u.
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>