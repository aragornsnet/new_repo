<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Contratos de Proveedor';
$subtituloPagina = 'Gestión de contratos y seguimiento de vencimientos';
$paginaActiva = 'contratos';
$scriptsExtra = [
    BASE_URL . 'public/js/contratos.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Administrador', 'Almacenero', 'Supervisor'])) {
    redirigir('views/403.php');
}

$puedeEditar = Auth::esAdmin();

// Cargar proveedores activos
$proveedores = Database::fetchAll("
    SELECT id, nombre, nit FROM proveedores WHERE activo = 1 ORDER BY nombre
");
?>

<div class="page-header">
    <div>
        <h2 class="page-title">📄 Contratos de proveedor</h2>
        <p class="page-subtitle">Total: <span id="total-contratos">—</span> contratos</p>
    </div>
    <div class="page-actions">
        <?php if ($puedeEditar): ?>
            <button class="btn btn-primary" onclick="Contratos.abrirNuevo()">
                <i class="bi bi-plus-lg"></i> Nuevo contrato
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!$puedeEditar): ?>
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle-fill"></i>
        <div>Modo solo lectura. Solo el administrador puede crear o modificar contratos.</div>
    </div>
<?php endif; ?>

<!-- Resumen rápido -->
<div class="grid-stats mb-4">
    <a href="#" class="stat-card" onclick="Contratos.atajo('activos'); return false;" style="text-decoration:none;">
        <div class="stat-card-header">
            <div class="stat-card-icon success"><i class="bi bi-check-circle-fill"></i></div>
        </div>
        <div class="stat-card-label">Activos</div>
        <div class="stat-card-value" id="res-activos">—</div>
    </a>

    <a href="#" class="stat-card" onclick="Contratos.atajo('por_vencer'); return false;" style="text-decoration:none;">
        <div class="stat-card-header">
            <div class="stat-card-icon warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
        </div>
        <div class="stat-card-label">Por vencer (30 días)</div>
        <div class="stat-card-value" id="res-por-vencer">—</div>
    </a>

    <a href="#" class="stat-card" onclick="Contratos.atajo('por_renovar'); return false;" style="text-decoration:none;">
        <div class="stat-card-header">
            <div class="stat-card-icon danger"><i class="bi bi-hourglass-split"></i></div>
        </div>
        <div class="stat-card-label">Por renovar (vencidos)</div>
        <div class="stat-card-value" id="res-por-renovar">—</div>
    </a>

    <a href="#" class="stat-card" onclick="Contratos.atajo('archivados'); return false;" style="text-decoration:none;">
        <div class="stat-card-header">
            <div class="stat-card-icon info"><i class="bi bi-archive-fill"></i></div>
        </div>
        <div class="stat-card-label">Archivados</div>
        <div class="stat-card-value" id="res-archivados">—</div>
    </a>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Nº de contrato, proveedor o descripción...">
            </div>
        </div>
        <div class="field">
            <label>Proveedor</label>
            <select id="filtro-proveedor">
                <option value="">Todos</option>
                <?php foreach ($proveedores as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"><?= h($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Estado</label>
            <select id="filtro-estado">
                <option value="">Todos</option>
                <option value="activos">Solo activos</option>
                <option value="por_vencer">Por vencer (≤30 días)</option>
                <option value="por_renovar">Por renovar (vencidos)</option>
                <option value="renovado">Renovados</option>
                <option value="cancelado">Cancelados</option>
                <option value="no_renovado">No renovados</option>
                <option value="archivados">Archivados (todos)</option>
            </select>
        </div>
        <div class="field">
            <label>Vigencia desde</label>
            <input type="date" id="filtro-desde">
        </div>
        <div class="field">
            <label>Vence hasta</label>
            <input type="date" id="filtro-hasta">
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary btn-sm" onclick="Contratos.cargar()">
            <i class="bi bi-funnel-fill"></i> Aplicar filtros
        </button>
        <button class="btn btn-secondary btn-sm" onclick="Contratos.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-list-ul"></i> Contratos
            <span class="badge badge-neutral ml-2" id="total-contratos-2">—</span>
        </div>
    </div>
    <div id="tabla-contratos">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: CREAR / EDITAR CONTRATO                              -->
<!-- ═══════════════════════════════════════════════════════════ -->
<?php if ($puedeEditar): ?>
<div class="modal-backdrop" id="modal-contrato">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-file-earmark-text"></i>
                <span id="con-modal-titulo">Nuevo contrato</span>
            </div>
            <button class="modal-close" onclick="Contratos.cerrarModal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="form-contrato" class="form" novalidate>
                <input type="hidden" id="con-id">

                <div class="form-grid">
                    <div class="field">
                        <label for="con-num">Nº de contrato <span class="req">*</span></label>
                        <input type="text" id="con-num" maxlength="50" required
                               placeholder="Ej: CT-2026-0001 o el número del proveedor">
                        <div class="help">Puede contener letras, números y símbolos (guiones, puntos, etc.)</div>
                    </div>

                    <div class="field">
                        <label for="con-proveedor">Proveedor <span class="req">*</span></label>
                        <select id="con-proveedor" required>
                            <option value="">Selecciona proveedor</option>
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?= (int)$p['id'] ?>">
                                    <?= h($p['nombre']) ?><?= $p['nit'] ? ' (' . h($p['nit']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="con-inicio">Fecha inicio <span class="req">*</span></label>
                        <input type="date" id="con-inicio" required>
                    </div>

                    <div class="field">
                        <label for="con-caducidad">Fecha caducidad <span class="req">*</span></label>
                        <input type="date" id="con-caducidad" required>
                    </div>

                    <div class="field">
                        <label for="con-monto">Monto pactado</label>
                        <input type="number" id="con-monto" step="0.01" min="0" placeholder="0.00">
                    </div>

                    <div class="field">
                        <label for="con-moneda">Moneda</label>
                        <select id="con-moneda">
                            <option value="CUP">CUP</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="con-forma-pago">Forma de pago</label>
                        <select id="con-forma-pago">
                            <option value="contado">Contado</option>
                            <option value="credito">Crédito</option>
                            <option value="mixto">Mixto</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="con-plazo">Plazo de pago (días)</label>
                        <input type="number" id="con-plazo" min="0" placeholder="Ej: 30">
                    </div>

                    <div class="field span-full">
                        <label for="con-descripcion">Descripción</label>
                        <textarea id="con-descripcion" maxlength="1000"
                                  placeholder="Descripción general del contrato" style="min-height:80px;"></textarea>
                    </div>

                    <div class="field span-full">
                        <label for="con-observaciones">Observaciones</label>
                        <textarea id="con-observaciones" maxlength="1000"
                                  placeholder="Observaciones internas" style="min-height:60px;"></textarea>
                    </div>
                </div>

                <hr style="margin:var(--space-4) 0;border:none;border-top:1px solid var(--border);">

                <!-- Productos -->
                <h4 style="font-size:15px;margin-bottom:12px;">
                    <i class="bi bi-box-seam text-primary"></i> Productos vinculados
                    <span class="badge badge-neutral ml-2" id="con-total-productos">0</span>
                </h4>

                <div class="form-grid mb-3">
                    <div class="field span-2">
                        <label for="con-buscar-prod">Buscar producto</label>
                        <div class="search-input">
                            <i class="bi bi-search"></i>
                            <input type="text" id="con-buscar-prod"
                                   placeholder="Nombre o código de barras..."
                                   autocomplete="off"
                                   oninput="Contratos.buscarProducto()">
                        </div>
                    </div>
                </div>

                <div id="con-resultados-wrap" style="display:none;margin-bottom:16px;">
                    <div id="con-resultados"
                         style="max-height:260px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius-md);"></div>
                </div>

                <div id="con-lista-productos">
                    <div class="empty-state" style="padding:20px;background:var(--surface-2);border-radius:var(--radius-md);">
                        <i class="bi bi-box" style="font-size:32px;"></i>
                        <p class="text-muted mt-2">Aún no has agregado productos</p>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="Contratos.cerrarModal()">Cancelar</button>
            <button class="btn btn-primary" id="btn-guardar-contrato" onclick="Contratos.guardar()">
                <i class="bi bi-check-lg"></i> Guardar
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: VER DETALLE DEL CONTRATO                             -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-detalle-contrato">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-file-earmark-text"></i>
                <span id="det-titulo">Contrato</span>
            </div>
            <button class="modal-close" onclick="Contratos.cerrarDetalle()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body" id="det-contenido">
            <div class="empty-state"><div class="spinner"></div></div>
        </div>
        <div class="modal-footer" id="det-footer">
            <button class="btn btn-secondary" onclick="Contratos.cerrarDetalle()">Cerrar</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: RENOVAR CONTRATO                                     -->
<!-- ═══════════════════════════════════════════════════════════ -->
<?php if ($puedeEditar): ?>
<div class="modal-backdrop" id="modal-renovar">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-arrow-clockwise text-success"></i> Renovar contrato
            </div>
            <button class="modal-close" onclick="document.getElementById('modal-renovar').classList.remove('active')">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="ren-id">

            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    Se creará un <strong>nuevo contrato</strong> copiando los datos del actual.
                    El contrato anterior se marcará como <strong>renovado</strong>.
                </div>
            </div>

            <div class="field mb-3">
                <label for="ren-num">Nº del nuevo contrato <span class="req">*</span></label>
                <input type="text" id="ren-num" maxlength="50" required
                       placeholder="Ej: CT-2026-0002">
            </div>

            <div class="field mb-3">
                <label for="ren-inicio">Fecha inicio <span class="req">*</span></label>
                <input type="date" id="ren-inicio" required>
            </div>

            <div class="field">
                <label for="ren-caducidad">Fecha caducidad <span class="req">*</span></label>
                <input type="date" id="ren-caducidad" required>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="document.getElementById('modal-renovar').classList.remove('active')">
                Cancelar
            </button>
            <button class="btn btn-success" id="btn-confirmar-renovar" onclick="Contratos.confirmarRenovar()">
                <i class="bi bi-check-lg"></i> Renovar
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: CANCELAR CONTRATO                                    -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-cancelar">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-x-circle-fill text-danger"></i> Cancelar contrato
            </div>
            <button class="modal-close" onclick="document.getElementById('modal-cancelar').classList.remove('active')">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="can-id">
            <p class="text-muted mb-3">
                Indica el motivo de la cancelación. Esta acción archiva el contrato y detiene los avisos.
            </p>
            <div class="field">
                <label for="can-motivo">Motivo <span class="req">*</span></label>
                <textarea id="can-motivo" maxlength="500" required
                          placeholder="Ej: Terminación anticipada por mutuo acuerdo"
                          style="min-height:80px;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="document.getElementById('modal-cancelar').classList.remove('active')">
                Cancelar
            </button>
            <button class="btn btn-danger" id="btn-confirmar-cancelar" onclick="Contratos.confirmarCancelar()">
                <i class="bi bi-x-lg"></i> Cancelar contrato
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: NO RENOVAR                                           -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-no-renovar">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-archive-fill text-warning"></i> No renovar contrato
            </div>
            <button class="modal-close" onclick="document.getElementById('modal-no-renovar').classList.remove('active')">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="nr-id">
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    El contrato se <strong>archivará</strong> y <strong>dejará de generar avisos</strong>.
                    Podrás reactivarlo después si es necesario.
                </div>
            </div>
            <div class="field">
                <label for="nr-motivo">Motivo <span class="req">*</span></label>
                <textarea id="nr-motivo" maxlength="500" required
                          placeholder="Ej: El proveedor cerró operaciones"
                          style="min-height:80px;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="document.getElementById('modal-no-renovar').classList.remove('active')">
                Cancelar
            </button>
            <button class="btn btn-warning" id="btn-confirmar-no-renovar" onclick="Contratos.confirmarNoRenovar()">
                <i class="bi bi-archive"></i> Archivar contrato
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/footer.php'; ?>