<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Mis Solicitudes de Traslado';
$subtituloPagina = 'Solicitudes al almacén central';
$paginaActiva = 'solicitudes';
$scriptsExtra = [
    BASE_URL . 'public/js/supervisor_solicitudes.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Administrador', 'Supervisor'])) {
    redirigir('views/403.php');
}

// Cargar PVs activos (excluyendo el almacén)
$puntos = Database::fetchAll("
    SELECT id, nombre FROM puntos_venta 
    WHERE activo = 1 AND es_almacen = 0 
    ORDER BY nombre
");

// Cargar categorías para el modal
$categorias = Database::fetchAll("
    SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre
");
?>

<div class="page-header">
    <div>
        <h2 class="page-title">🔄 Solicitudes de traslado</h2>
        <p class="page-subtitle">
            Total: <span id="total-solicitudes">—</span> solicitudes
        </p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="SupervisorSolicitudes.abrirNueva()">
            <i class="bi bi-plus-lg"></i> Nueva solicitud
        </button>
    </div>
</div>

<div class="alert alert-info mb-4">
    <i class="bi bi-info-circle-fill"></i>
    <div class="alert-body">
        <strong>¿Cómo funciona?</strong> Crea una solicitud indicando qué productos necesitas.
        El almacenero la revisará, aprobará y despachará. Cuando llegue la mercancía, <strong>confirma la recepción</strong>
        y el stock de tu PV se actualizará.
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Folio o motivo...">
            </div>
        </div>
        <div class="field">
            <label>Estado</label>
            <select id="filtro-estado">
                <option value="">Todos</option>
                <option value="solicitado">Solicitados</option>
                <option value="aprobado">Aprobados</option>
                <option value="despachado">Despachados</option>
                <option value="despachado_parcial">Despachados parcial</option>
                <option value="recibido">Recibidos</option>
                <option value="rechazado">Rechazados</option>
                <option value="cancelado">Cancelados</option>
            </select>
        </div>
        <div class="field">
            <label>Desde</label>
            <input type="date" id="filtro-desde">
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" id="filtro-hasta">
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary btn-sm" onclick="SupervisorSolicitudes.cargar()">
            <i class="bi bi-funnel-fill"></i> Aplicar filtros
        </button>
        <button class="btn btn-secondary btn-sm" onclick="SupervisorSolicitudes.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-list-ul"></i> Solicitudes
            <span class="badge badge-neutral ml-2" id="total-solicitudes-2">—</span>
        </div>
    </div>
    <div id="tabla-solicitudes">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: CREAR NUEVA SOLICITUD -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-nueva">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-plus-circle-fill text-primary"></i> Nueva solicitud
            </div>
            <button class="modal-close" onclick="SupervisorSolicitudes.cerrarNueva()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="form-solicitud" class="form" novalidate>

                <!-- Datos generales -->
                <div class="form-grid">
                    <div class="field">
                        <label for="sol-pv">Punto de venta <span class="req">*</span></label>
                        <select id="sol-pv" required>
                            <option value="">Selecciona PV</option>
                            <?php foreach ($puntos as $p): ?>
                                <option value="<?= (int)$p['id'] ?>"><?= h($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="sol-motivo">Motivo <span class="req">*</span></label>
                        <select id="sol-motivo" required>
                            <option value="">Selecciona motivo</option>
                            <option value="Reposición de stock">Reposición de stock</option>
                            <option value="Productos agotados">Productos agotados</option>
                            <option value="Evento especial">Evento especial</option>
                            <option value="Temporada alta">Temporada alta</option>
                            <option value="Otro (especificar)">Otro (especificar)</option>
                        </select>
                    </div>

                    <div class="field span-full">
                        <label for="sol-descripcion">Descripción / Notas</label>
                        <textarea id="sol-descripcion" maxlength="500" placeholder="Notas adicionales (opcional)" style="min-height:60px;"></textarea>
                    </div>
                </div>

                <hr style="margin:var(--space-4) 0;border:none;border-top:1px solid var(--border);">

                <!-- Buscador de productos -->
                <h4 style="font-size:15px;margin-bottom:10px;">
                    <i class="bi bi-box-seam text-primary"></i> Productos a solicitar
                </h4>

                <div class="form-grid mb-3">
                    <div class="field span-2">
                        <label for="prod-buscar">Buscar producto</label>
                        <div class="search-input">
                            <i class="bi bi-search"></i>
                            <input type="text" id="prod-buscar" placeholder="Nombre o código..."
                                   autocomplete="off" oninput="SupervisorSolicitudes.buscarProducto()">
                        </div>
                    </div>
                    <div class="field">
                        <label for="prod-categoria">Categoría</label>
                        <select id="prod-categoria" onchange="SupervisorSolicitudes.cargarProductosDisponibles()">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= h($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="prod-resultados-wrap" style="display:none;margin-bottom:16px;">
                    <div id="prod-resultados"
                         style="max-height:280px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius-md);"></div>
                </div>

                <!-- Carrito de la solicitud -->
                <div id="carrito-wrap">
                    <div class="empty-state" style="padding:30px 15px;background:var(--surface-2);border-radius:var(--radius-md);">
                        <i class="bi bi-cart-x" style="font-size:40px;"></i>
                        <p class="text-muted mt-2">Aún no has agregado productos</p>
                    </div>
                </div>

            </form>
        </div>
        <div class="modal-footer">
            <div style="flex:1;display:flex;align-items:center;gap:12px;">
                <span class="text-sm text-muted">Productos:</span>
                <strong id="sol-total-items">0</strong>
                <span class="text-sm text-muted">· Unidades:</span>
                <strong id="sol-total-unidades">0</strong>
            </div>
            <button class="btn btn-secondary" onclick="SupervisorSolicitudes.cerrarNueva()">Cancelar</button>
            <button class="btn btn-primary" id="btn-crear-solicitud" onclick="SupervisorSolicitudes.crear()">
                <i class="bi bi-check-lg"></i> Crear solicitud
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: VER DETALLE -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-detalle">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-arrow-left-right"></i>
                <span id="det-titulo">Solicitud</span>
            </div>
            <button class="modal-close" onclick="SupervisorSolicitudes.cerrarDetalle()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body" id="det-contenido">
            <div class="empty-state"><div class="spinner"></div></div>
        </div>
        <div class="modal-footer" id="det-footer"></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: CANCELAR -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-cancelar">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-x-circle-fill text-warning"></i> Cancelar solicitud
            </div>
            <button class="modal-close" onclick="document.getElementById('modal-cancelar').classList.remove('active')">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="can-id">
            <p class="text-muted mb-3">
                Indica el motivo de la cancelación. El almacenero recibirá una notificación.
            </p>
            <div class="field">
                <label for="can-motivo">Motivo <span class="req">*</span></label>
                <textarea id="can-motivo" maxlength="255" placeholder="Ej: Ya no necesito los productos, error en la solicitud, etc." style="min-height:80px;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="document.getElementById('modal-cancelar').classList.remove('active')">
                Cancelar
            </button>
            <button class="btn btn-warning" id="btn-confirmar-cancelar" onclick="SupervisorSolicitudes.confirmarCancelar()">
                <i class="bi bi-x-lg"></i> Cancelar solicitud
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>