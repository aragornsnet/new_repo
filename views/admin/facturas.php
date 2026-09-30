<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Facturas';
$subtituloPagina = 'Gestión de facturas al por mayor';
$paginaActiva = 'facturas';
$scriptsExtra = [
    BASE_URL . 'public/js/facturas.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Administrador', 'Supervisor'])) {
    redirigir('views/403.php');
}

$puedeEmitir = Auth::esAdmin() || Auth::esSupervisor();
?>

<div class="page-header">
    <div>
        <h2 class="page-title">🧾 Facturas</h2>
        <p class="page-subtitle">Total: <span id="total-facturas">—</span> facturas</p>
    </div>
    <div class="page-actions">
        <?php if ($puedeEmitir): ?>
            <button class="btn btn-success" onclick="Facturas.abrirPendientes()">
                <i class="bi bi-receipt"></i> Emitir nueva factura
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Resumen -->
<div class="grid-stats mb-4">
    <a href="#" class="stat-card" onclick="Facturas.atajo('emitida'); return false;" style="text-decoration:none;">
        <div class="stat-card-header">
            <div class="stat-card-icon warning"><i class="bi bi-hourglass-split"></i></div>
        </div>
        <div class="stat-card-label">Emitidas</div>
        <div class="stat-card-value" id="res-emitidas">—</div>
    </a>

    <a href="#" class="stat-card" onclick="Facturas.atajo('parcial'); return false;" style="text-decoration:none;">
        <div class="stat-card-header">
            <div class="stat-card-icon info"><i class="bi bi-pie-chart-fill"></i></div>
        </div>
        <div class="stat-card-label">Parcialmente pagadas</div>
        <div class="stat-card-value" id="res-parciales">—</div>
    </a>

    <a href="#" class="stat-card" onclick="Facturas.atajo('pagada'); return false;" style="text-decoration:none;">
        <div class="stat-card-header">
            <div class="stat-card-icon success"><i class="bi bi-check-circle-fill"></i></div>
        </div>
        <div class="stat-card-label">Pagadas</div>
        <div class="stat-card-value" id="res-pagadas">—</div>
    </a>

    <a href="#" class="stat-card" onclick="Facturas.atajo('anulada'); return false;" style="text-decoration:none;">
        <div class="stat-card-header">
            <div class="stat-card-icon danger"><i class="bi bi-x-circle-fill"></i></div>
        </div>
        <div class="stat-card-label">Anuladas</div>
        <div class="stat-card-value" id="res-anuladas">—</div>
    </a>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Folio factura, folio venta, cliente o NIT...">
            </div>
        </div>
        <div class="field">
            <label>Estado</label>
            <select id="filtro-estado">
                <option value="">Todos</option>
                <option value="emitida">Emitidas</option>
                <option value="parcial">Parciales</option>
                <option value="pagada">Pagadas</option>
                <option value="anulada">Anuladas</option>
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
        <div class="field">
            <label>Vencimiento</label>
            <select id="filtro-vencidas">
                <option value="">Todas</option>
                <option value="1">Solo vencidas</option>
            </select>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary btn-sm" onclick="Facturas.cargar()">
            <i class="bi bi-funnel-fill"></i> Aplicar filtros
        </button>
        <button class="btn btn-secondary btn-sm" onclick="Facturas.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-list-ul"></i> Facturas
            <span class="badge badge-neutral ml-2" id="total-facturas-2">—</span>
        </div>
    </div>
    <div id="tabla-facturas">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: VENTAS PENDIENTES DE FACTURAR                        -->
<!-- ═══════════════════════════════════════════════════════════ -->
<?php if ($puedeEmitir): ?>
<div class="modal-backdrop" id="modal-pendientes">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-receipt-cutoff"></i> Ventas pendientes de facturar
            </div>
            <button class="modal-close" onclick="Facturas.cerrarPendientes()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    Estas son las ventas que los vendedores marcaron como "Requiere factura".
                    Selecciona una para emitir su factura.
                </div>
            </div>

            <div class="field mb-3">
                <div class="search-input">
                    <i class="bi bi-search"></i>
                    <input type="text" id="pend-buscar" placeholder="Buscar por folio, cliente o NIT..."
                           oninput="Facturas.buscarPendientes()">
                </div>
            </div>

            <div id="lista-pendientes">
                <div class="empty-state"><div class="spinner"></div></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="Facturas.cerrarPendientes()">Cerrar</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: EMITIR FACTURA                                       -->
<!-- ═══════════════════════════════════════════════════════════ -->
<?php if ($puedeEmitir): ?>
<div class="modal-backdrop" id="modal-emitir">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-file-earmark-text text-primary"></i> Emitir factura
            </div>
            <button class="modal-close" onclick="Facturas.cerrarEmitir()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body" id="emitir-contenido">
            <div class="empty-state"><div class="spinner"></div></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="Facturas.cerrarEmitir()">Cancelar</button>
            <button class="btn btn-success" id="btn-confirmar-emitir" onclick="Facturas.confirmarEmitir()">
                <i class="bi bi-check-lg"></i> Emitir factura
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: DETALLE DE FACTURA                                   -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-detalle-factura">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-file-earmark-text"></i>
                <span id="det-titulo">Factura</span>
            </div>
            <button class="modal-close" onclick="Facturas.cerrarDetalle()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body" id="det-contenido">
            <div class="empty-state"><div class="spinner"></div></div>
        </div>
        <div class="modal-footer" id="det-footer">
            <button class="btn btn-secondary" onclick="Facturas.cerrarDetalle()">Cerrar</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: REGISTRAR PAGO                                       -->
<!-- ═══════════════════════════════════════════════════════════ -->
<?php if ($puedeEmitir): ?>
<div class="modal-backdrop" id="modal-pago">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-cash-coin text-success"></i> Registrar pago
            </div>
            <button class="modal-close" onclick="document.getElementById('modal-pago').classList.remove('active')">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="pago-factura-id">

            <div id="pago-info" style="padding:12px;background:var(--surface-2);border-radius:var(--radius-md);margin-bottom:16px;"></div>

            <div class="field mb-3">
                <label for="pago-monto">Monto <span class="req">*</span></label>
                <input type="number" id="pago-monto" step="0.01" min="0.01" required>
                <div class="help" id="pago-monto-help"></div>
            </div>

            <div class="field mb-3">
                <label for="pago-metodo">Método <span class="req">*</span></label>
                <select id="pago-metodo">
                    <option value="efectivo">Efectivo</option>
                    <option value="transferencia">Transferencia</option>
                    <option value="otro">Otro</option>
                </select>
            </div>

            <div class="field mb-3">
                <label for="pago-referencia">Referencia</label>
                <input type="text" id="pago-referencia" maxlength="100" placeholder="Nº transferencia, cheque, etc.">
            </div>

            <div class="field">
                <label for="pago-notas">Notas</label>
                <textarea id="pago-notas" maxlength="255" style="min-height:60px;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="document.getElementById('modal-pago').classList.remove('active')">
                Cancelar
            </button>
            <button class="btn btn-success" id="btn-confirmar-pago" onclick="Facturas.confirmarPago()">
                <i class="bi bi-check-lg"></i> Registrar pago
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: ANULAR FACTURA                                       -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-anular">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-x-circle-fill text-danger"></i> Anular factura
            </div>
            <button class="modal-close" onclick="document.getElementById('modal-anular').classList.remove('active')">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="anular-factura-id">
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    Esta acción anula la factura de forma permanente.
                    <strong>No se puede deshacer.</strong>
                </div>
            </div>
            <div class="field">
                <label for="anular-motivo">Motivo <span class="req">*</span></label>
                <textarea id="anular-motivo" maxlength="255" required placeholder="Explica el motivo de la anulación"
                          style="min-height:80px;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="document.getElementById('modal-anular').classList.remove('active')">
                Cancelar
            </button>
            <button class="btn btn-danger" id="btn-confirmar-anular" onclick="Facturas.confirmarAnular()">
                <i class="bi bi-x-lg"></i> Anular factura
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/footer.php'; ?>