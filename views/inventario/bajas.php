<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Bajas de Inventario';
$subtituloPagina = 'Registrar bajas de productos con motivo obligatorio';
$paginaActiva = 'bajas';
$scriptsExtra = [
    BASE_URL . 'public/js/bajas.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Supervisor', 'Administrador'])) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">📤 Bajas de inventario</h2>
        <p class="page-subtitle">Registra mermas, robos, daños o productos vencidos</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary" onclick="Bajas.abrirMasiva()">
            <i class="bi bi-list-check"></i> Carga masiva
        </button>
        <button class="btn btn-danger" onclick="Bajas.abrirNueva()">
            <i class="bi bi-plus-lg"></i> Nueva baja
        </button>
    </div>
</div>

<div class="alert alert-warning mb-4">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div class="alert-body">
        <div class="alert-title">Importante</div>
        Las bajas <strong>reducen el stock permanentemente</strong> y quedan registradas en auditoría con tu usuario, fecha y motivo. Úsalas solo cuando corresponda.
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Producto, motivo, descripción...">
            </div>
        </div>
        <div class="field">
            <label>Punto de venta</label>
            <select id="filtro-pv">
                <option value="">Todos los PV</option>
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
        <button class="btn btn-primary btn-sm" onclick="Bajas.cargar()">
            <i class="bi bi-funnel-fill"></i> Aplicar filtros
        </button>
        <button class="btn btn-secondary btn-sm" onclick="Bajas.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-clock-history"></i> Bajas registradas
            <span class="badge badge-neutral ml-2" id="total-bajas">—</span>
        </div>
    </div>
    <div id="tabla-bajas">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<!-- Modal baja individual -->
<div class="modal-backdrop" id="modal-baja">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-box-arrow-up"></i> Nueva baja</div>
            <button class="modal-close" onclick="Bajas.cerrarModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form id="form-baja" class="form" novalidate>
                <div class="form-grid">
                    <div class="field span-full">
                        <label for="b-pv">Punto de venta <span class="req">*</span></label>
                        <select id="b-pv" required onchange="Bajas.limpiarProducto()">
                            <option value="">Selecciona PV</option>
                        </select>
                    </div>

                    <div class="field span-2">
                        <label for="b-buscar">Buscar producto</label>
                        <div class="search-input">
                            <i class="bi bi-search"></i>
                            <input type="text" id="b-buscar" placeholder="Nombre o código de barras..." autocomplete="off" oninput="Bajas.buscarProducto()">
                        </div>
                    </div>

                    <div class="field span-full" id="b-resultados-wrap" style="display:none;">
                        <div id="b-resultados" style="max-height:200px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius-md);"></div>
                    </div>

                    <div class="field span-full" id="b-seleccionado-wrap" style="display:none;">
                        <div style="padding:12px;background:var(--danger-light);border:1px solid var(--danger);border-radius:var(--radius-md);display:flex;align-items:center;gap:12px;">
                            <i class="bi bi-exclamation-triangle-fill" style="font-size:24px;color:var(--danger);"></i>
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:600;" id="b-sel-nombre">—</div>
                                <div class="text-xs text-muted" id="b-sel-info">—</div>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="Bajas.limpiarProducto()">
                                <i class="bi bi-x-circle"></i> Cambiar
                            </button>
                        </div>
                        <input type="hidden" id="b-producto-id">
                    </div>

                    <div class="field">
                        <label for="b-cantidad">Cantidad <span class="req">*</span></label>
                        <input type="number" id="b-cantidad" min="1" value="1" required>
                        <div class="help" id="b-stock-help"></div>
                    </div>

                    <div class="field">
                        <label for="b-motivo">Motivo <span class="req">*</span></label>
                        <select id="b-motivo" required>
                            <option value="">Selecciona motivo</option>
                        </select>
                    </div>

                    <div class="field span-full">
                        <label for="b-descripcion">Descripción detallada <span class="req">*</span></label>
                        <textarea id="b-descripcion" maxlength="255" required placeholder="Explica con detalle el motivo de la baja (obligatorio para trazabilidad)" style="min-height:80px;"></textarea>
                        <div class="help">Obligatorio. Esta información queda en la auditoría del sistema.</div>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="Bajas.cerrarModal()">Cancelar</button>
            <button class="btn btn-danger" id="btn-guardar-baja" onclick="Bajas.guardar()">
                <i class="bi bi-check-lg"></i> Registrar baja
            </button>
        </div>
    </div>
</div>

<!-- Modal carga masiva -->
<div class="modal-backdrop" id="modal-masiva">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-list-check"></i> Carga masiva de bajas</div>
            <button class="modal-close" onclick="Bajas.cerrarMasiva()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>La descripción es <strong>obligatoria</strong> en carga masiva para tener trazabilidad completa.</div>
            </div>

            <div class="form-grid mb-4">
                <div class="field">
                    <label for="m-pv">Punto de venta <span class="req">*</span></label>
                    <select id="m-pv" required onchange="Bajas.cargarProductosMasiva()">
                        <option value="">Selecciona PV</option>
                    </select>
                </div>
                <div class="field">
                    <label for="m-motivo">Motivo <span class="req">*</span></label>
                    <select id="m-motivo" required>
                        <option value="">Selecciona motivo</option>
                    </select>
                </div>
                <div class="field span-2">
                    <label for="m-descripcion">Descripción general <span class="req">*</span></label>
                    <input type="text" id="m-descripcion" maxlength="255" required placeholder="Ej: Productos vencidos en estante 3">
                </div>
                <div class="field" style="display:flex;align-items:flex-end;">
                    <button class="btn btn-secondary" onclick="Bajas.limpiarCantidades()">
                        <i class="bi bi-x-circle"></i> Limpiar
                    </button>
                </div>
            </div>

            <div class="field mb-3">
                <div class="search-input">
                    <i class="bi bi-search"></i>
                    <input type="text" id="m-buscar" placeholder="Filtrar productos..." oninput="Bajas.cargarProductosMasiva()">
                </div>
            </div>

            <div id="m-tabla">
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p class="text-muted">Selecciona un punto de venta para cargar los productos</p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <div style="flex:1;display:flex;align-items:center;gap:12px;">
                <span class="text-sm text-muted">Total de productos con cantidad:</span>
                <strong id="m-total-items">0</strong>
            </div>
            <button class="btn btn-secondary" onclick="Bajas.cerrarMasiva()">Cancelar</button>
            <button class="btn btn-danger" id="btn-guardar-masiva" onclick="Bajas.guardarMasiva()">
                <i class="bi bi-check-lg"></i> Aplicar bajas
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>