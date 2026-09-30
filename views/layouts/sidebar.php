<?php
/**
 * IPV - Sidebar según rol
 */
$rolActual = Auth::rolId();
$paginaActiva = $paginaActiva ?? '';

// ⭐ Badge de contratos por atender (solo Admin y Supervisor)
$contratosAlerta = 0;
if (in_array($rolActual, [1, 2], true)) {
    try {
        $contratosAlerta = (int) Database::fetchValue("
            SELECT COUNT(*) FROM contratos_proveedor
            WHERE (estado = 'activo' AND fecha_caducidad >= CURDATE()
                   AND fecha_caducidad <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
               OR estado = 'por_renovar'
        ", [], 0);
    } catch (Throwable $e) {
        $contratosAlerta = 0;
    }
}

function navItem(string $href, string $icono, string $texto, string $activa, string $actual, ?string $badge = null): void {
    $clase = $activa === $actual ? 'nav-item active' : 'nav-item';
    echo '<a href="' . BASE_URL . $href . '" class="' . $clase . '">';
    echo '<i class="bi ' . $icono . '"></i>';
    echo '<span>' . htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') . '</span>';
    if ($badge !== null) {
        echo '<span class="badge badge-danger">' . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    echo '</a>';
}
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <?php if ($logo ?? false): ?>
            <img src="<?= h(BASE_URL . ltrim($logo, '/')) ?>" alt="Logo">
        <?php else: ?>
            <div class="brand-icon"><i class="bi bi-box-seam-fill"></i></div>
        <?php endif; ?>
        <div class="brand-text">
            <div class="brand-name"><?= h($nombreNegocio ?? NEGOCIO_NOMBRE) ?></div>
            <div class="brand-sub">IPV</div>
        </div>
    </div>

    <nav class="sidebar-nav" id="sidebar-nav">
        <?php if ($rolActual === 1): // ADMIN ?>
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>
                <?php
                navItem('views/dashboards/admin.php', 'bi-speedometer2', 'Dashboard', 'dashboard', $paginaActiva);
                ?>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Gestión</div>
                <?php
                navItem('views/admin/usuarios.php',     'bi-people-fill',       'Usuarios',         'usuarios',    $paginaActiva);
                navItem('views/admin/puntos_venta.php', 'bi-shop',              'Puntos de venta',  'puntos',      $paginaActiva);
                navItem('views/admin/productos.php',    'bi-box-seam',          'Productos',        'productos',   $paginaActiva);
                navItem('views/admin/categorias.php',   'bi-tags-fill',         'Categorías',       'categorias',  $paginaActiva);
                navItem('views/admin/proveedores.php',  'bi-building',          'Proveedores',      'proveedores', $paginaActiva);
                navItem('views/admin/contratos.php',    'bi-file-earmark-text', 'Contratos',        'contratos',   $paginaActiva);
                navItem('views/admin/clientes.php',     'bi-person-badge',      'Clientes',         'clientes',    $paginaActiva);
                ?>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Operaciones</div>
                <?php
                navItem('views/admin/ventas.php',         'bi-cart-check-fill',   'Ventas',         'ventas',         $paginaActiva);
                navItem('views/inventario/index.php',     'bi-box-seam',          'Inventario',     'inventario',     $paginaActiva);
                navItem('views/admin/solicitudes.php',    'bi-arrow-left-right',  'Solicitudes',    'solicitudes',    $paginaActiva);
                navItem('views/admin/turnos.php',         'bi-clock-history',     'Turnos',         'turnos',         $paginaActiva);
                navItem('views/admin/transferencias.php', 'bi-bank',              'Transferencias', 'transferencias', $paginaActiva);
                navItem('views/admin/facturas.php',       'bi-receipt',           'Facturas',       'facturas',       $paginaActiva);
                navItem('views/admin/comprobantes.php',   'bi-receipt-cutoff',    'Comprobantes',   'comprobantes',   $paginaActiva);
                ?>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Almacén</div>
                <?php
                navItem('views/almacen/dashboard.php',   'bi-box-seam-fill',     'Dashboard',   'almacen-dashboard',  $paginaActiva);
                navItem('views/almacen/inventario.php',  'bi-stack',             'Inventario',  'almacen-inventario', $paginaActiva);
                navItem('views/almacen/entradas.php',    'bi-box-arrow-in-down', 'Entradas',    'almacen-entradas',   $paginaActiva);
                navItem('views/almacen/solicitudes.php', 'bi-arrow-left-right',  'Solicitudes', 'almacen-solicitudes',$paginaActiva);
                navItem('views/almacen/trazabilidad.php','bi-search-heart',     'Trazabilidad','almacen-trazabilidad', $paginaActiva);
                ?>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Análisis</div>
                <?php
                navItem('views/admin/reportes.php',  'bi-graph-up-arrow', 'Reportes',  'reportes',  $paginaActiva);
                navItem('views/admin/auditoria.php', 'bi-shield-check',   'Auditoría', 'auditoria', $paginaActiva);
                ?>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Sistema</div>
                <?php
                navItem('views/admin/configuracion.php',  'bi-gear-fill',         'Configuración',   'config',       $paginaActiva);
                navItem('views/admin/divisas.php',        'bi-currency-exchange', 'Divisas',         'divisas',      $paginaActiva);
                navItem('views/admin/personalizacion.php','bi-palette-fill',      'Personalización', 'personalizar', $paginaActiva);
                navItem('views/admin/backup.php',         'bi-hdd-fill',          'Backup',          'backup',       $paginaActiva);
                navItem('views/admin/logs.php',           'bi-file-earmark-text', 'Logs',            'logs',         $paginaActiva);
                navItem('views/licencia.php',             'bi-shield-check',      'Licencia',        'licencia',     $paginaActiva);
                navItem('views/admin/sistema.php',        'bi-cpu-fill',          'Sistema',         'sistema',      $paginaActiva);
                ?>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Ayuda</div>
                <?php
                navItem('views/ayuda.php?doc=usuario', 'bi-book',        'Manual de Usuario', 'ayuda-usuario', $paginaActiva);
                navItem('views/ayuda.php?doc=tecnico', 'bi-tools',       'Manual Técnico',    'ayuda-tecnico', $paginaActiva);
                navItem('views/ayuda.php?doc=readme',  'bi-info-circle', 'Acerca de IPV',     'ayuda-readme',  $paginaActiva);
                ?>
            </div>

        <?php elseif ($rolActual === 2): // SUPERVISOR ?>
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>
                <?php navItem('views/supervisor/dashboard.php', 'bi-speedometer2', 'Dashboard', 'dashboard', $paginaActiva); ?>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Operaciones</div>
                <?php
                navItem('views/supervisor/solicitudes.php',    'bi-arrow-left-right',  'Solicitudes',    'solicitudes',    $paginaActiva);
                navItem('views/inventario/bajas.php',          'bi-box-arrow-up',      'Bajas',          'bajas',          $paginaActiva);
                navItem('views/inventario/ajustes.php',        'bi-wrench-adjustable', 'Ajustes',        'ajustes',        $paginaActiva);
                navItem('views/inventario/movimientos.php',    'bi-list-ul',           'Movimientos',    'movimientos',    $paginaActiva);
                navItem('views/supervisor/ventas.php',         'bi-cart-check-fill',   'Ventas del día', 'ventas',         $paginaActiva);
                navItem('views/supervisor/turnos.php',         'bi-clock-history',     'Turnos',         'turnos',         $paginaActiva);
                navItem('views/supervisor/caja.php',           'bi-cash-stack',        'Caja del día',   'caja',           $paginaActiva);
                navItem('views/supervisor/transferencias.php', 'bi-bank',              'Transferencias', 'transferencias', $paginaActiva);
                navItem('views/inventario/index.php',          'bi-box-seam',          'Inventario',     'inventario',     $paginaActiva);
                navItem('views/admin/facturas.php',            'bi-receipt',           'Facturas',       'facturas',       $paginaActiva);
                navItem('views/admin/comprobantes.php',        'bi-receipt-cutoff',    'Comprobantes',   'comprobantes',   $paginaActiva);
                ?>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Análisis</div>
                <?php
                navItem('views/supervisor/ranking.php',  'bi-trophy-fill',    'Ranking',  'ranking',  $paginaActiva);
                navItem('views/supervisor/reportes.php', 'bi-graph-up-arrow', 'Reportes', 'reportes', $paginaActiva);
                ?>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Proveedores</div>
                <?php
                navItem('views/admin/proveedores.php', 'bi-building',           'Proveedores', 'proveedores', $paginaActiva);
                navItem('views/admin/contratos.php',   'bi-file-earmark-text',  'Contratos',   'contratos',   $paginaActiva, $contratosAlerta > 0 ? (string)$contratosAlerta : null);
                navItem('views/admin/clientes.php',    'bi-person-badge',       'Clientes',    'clientes',    $paginaActiva);
                ?>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Ayuda</div>
                <?php
                navItem('views/ayuda.php?doc=usuario', 'bi-book',        'Manual de Usuario', 'ayuda-usuario', $paginaActiva);
                navItem('views/ayuda.php?doc=readme',  'bi-info-circle', 'Acerca de IPV',     'ayuda-readme',  $paginaActiva);
                ?>
            </div>

        <?php elseif ($rolActual === 3): // VENDEDOR ?>
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>
                <?php navItem('views/vendedor/dashboard.php', 'bi-speedometer2', 'Inicio', 'dashboard', $paginaActiva); ?>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Mi turno</div>
                <?php
                navItem('views/vendedor/dashboard.php',        'bi-speedometer2',      'Inicio',           'dashboard',    $paginaActiva);
                navItem('views/vendedor/pos.php',              'bi-cart-check-fill',   'Punto de venta',   'pos',          $paginaActiva);
                navItem('views/vendedor/mis_ventas.php',       'bi-receipt',           'Mis ventas',       'ventas',       $paginaActiva);
                navItem('views/vendedor/mi_caja.php',          'bi-cash-coin',         'Mi caja',          'caja',         $paginaActiva);
                navItem('views/vendedor/mis_reportes.php',     'bi-graph-up-arrow',    'Reportes',         'reportes',     $paginaActiva);
                navItem('views/vendedor/cerrar_turno.php',     'bi-stop-circle-fill',  'Cerrar turno',     'cerrar',       $paginaActiva);
                navItem('views/vendedor/mis_comprobantes.php', 'bi-receipt-cutoff',    'Mis comprobantes', 'comprobantes', $paginaActiva);
                ?>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Clientes</div>
                <?php
                navItem('views/admin/clientes.php', 'bi-person-badge', 'Clientes mayoristas', 'clientes', $paginaActiva);
                ?>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Ayuda</div>
                <?php
                navItem('views/ayuda.php?doc=usuario', 'bi-book',        'Manual de Usuario', 'ayuda-usuario', $paginaActiva);
                navItem('views/ayuda.php?doc=readme',  'bi-info-circle', 'Acerca de IPV',     'ayuda-readme',  $paginaActiva);
                ?>
            </div>

        <?php elseif ($rolActual === 4): // ALMACENERO ?>
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>
                <?php navItem('views/almacen/dashboard.php', 'bi-speedometer2', 'Dashboard', 'dashboard', $paginaActiva); ?>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Almacén</div>
                <?php
                navItem('views/almacen/inventario.php',  'bi-box-seam',              'Inventario',   'inventario',   $paginaActiva);
                navItem('views/almacen/categorias.php',  'bi-bookmark-fill',         'Categorías',   'categorias',   $paginaActiva);
                navItem('views/almacen/entradas.php',    'bi-box-arrow-in-down',     'Entradas',     'entradas',     $paginaActiva);
                navItem('views/almacen/solicitudes.php', 'bi-arrow-left-right',      'Solicitudes',  'solicitudes',  $paginaActiva);
                navItem('views/admin/proveedores.php',   'bi-building',              'Proveedores',  'proveedores',  $paginaActiva);
                navItem('views/admin/contratos.php',     'bi-file-earmark-text',     'Contratos',    'contratos',    $paginaActiva, $contratosAlerta > 0 ? (string)$contratosAlerta : null);
                ?>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Ayuda</div>
                <?php
                navItem('views/ayuda.php?doc=usuario', 'bi-book',        'Manual de Usuario', 'ayuda-usuario', $paginaActiva);
                navItem('views/ayuda.php?doc=readme',  'bi-info-circle', 'Acerca de IPV',     'ayuda-readme',  $paginaActiva);
                ?>
            </div>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="avatar" style="background: <?= colorAvatar($usuario['nombre']) ?>">
                <?= h(inicial($usuario['nombre'])) ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= h($usuario['nombre']) ?></div>
                <div class="sidebar-user-role"><?= h($usuario['rol']) ?></div>
            </div>
        </div>
    </div>
</aside>