<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Entradas de Inventario';
$subtituloPagina = 'Registrar entrada de productos al stock';
$paginaActiva = 'entradas';
$scriptsExtra = [
    BASE_URL . 'public/js/entradas.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Supervisor', 'Administrador'])) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">📥 Entradas de inventario</h2>
        <p class="page-subtitle">Registra la entrada de productos al stock de cualquier punto de venta</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary" onclick="Entradas.abrirMasiva()">
            <i class="bi bi-list-check"></i> Carga masiva
        </button>
        <button class="btn btn-primary" onclick="Entradas.abrirNueva()">
            <i class="bi bi-plus-lg"></i> Nueva entrada
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Producto, motivo, referencia...">
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
        <button class="btn btn-primary btn-sm" onclick="Entradas.cargar()">
            <i class="bi bi-funnel-fill"></i> Aplicar filtros
        </button>
        <button class="btn btn-secondary btn-sm" onclick="Entradas.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-clock-history"></i> Entradas registradas
            <span class="badge badge-neutral ml-2" id="total-entradas">—</span>
        </div>
    </div>
    <div id="tabla-entradas">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<!-- Modal entrada individual -->
<div class="modal-backdrop" id="modal-entrada">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-box-arrow-in-down"></i> Nueva entrada</div>
            <button class="modal-close" onclick="Entradas.cerrarModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form id="form-entrada" class="form" novalidate>
                <div class="form-grid">
                    <div class="field span-full">
                        <label for="e-pv">Punto de venta <span class="req">*</span></label>
                        <select id="e-pv" required onchange="Entradas.limpiarProducto()">
                            <option value="">Selecciona PV</option>
                        </select>
                    </div>

                    <div class="field span-2">
                        <label for="e-buscar">Buscar producto</label>
                        <div class="search-input">
                            <i class="bi bi-search"></i>
                            <input type="text" id="e-buscar" placeholder="Nombre o código de barras..." autocomplete="off" oninput="Entradas.buscarProducto()">
                        </div>
                    </div>

                    <div class="field span-full" id="e-resultados-wrap" style="display:none;">
                        <div id="e-resultados" style="max-height:200px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius-md);"></div>
                    </div>

                    <div class="field span-full" id="e-seleccionado-wrap" style="display:none;">
                        <div style="padding:12px;background:var(--success-light);border:1px solid var(--success);border-radius:var(--radius-md);display:flex;align-items:center;gap:12px;">
                            <i class="bi bi-check-circle-fill" style="font-size:24px;color:var(--success);"></i>
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:600;" id="e-sel-nombre">—</div>
                                <div class="text-xs text-muted" id="e-sel-info">—</div>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="Entradas.limpiarProducto()">
                                <i class="bi bi-x-circle"></i> Cambiar
                            </button>
                        </div>
                        <input type="hidden" id="e-producto-id">
                    </div>

                    <div class="field">
                        <label for="e-cantidad">Cantidad <span class="req">*</span></label>
                        <input type="number" id="e-cantidad" min="1" value="1" required>
                        <div class="help" id="e-stock-help"></div>
                    </div>

                    <div class="field">
                        <label for="e-motivo">Motivo <span class="req">*</span></label>
                        <select id="e-motivo" required>
                            <option value="">Selecciona motivo</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="e-referencia">Referencia</label>
                        <input type="text" id="e-referencia" maxlength="100" placeholder="Nº factura, guía, etc.">
                        <div class="help">Opcional</div>
                    </div>

                    <div class="field span-full">
                        <label for="e-descripcion">Descripción / Notas</label>
                        <textarea id="e-descripcion" maxlength="255" placeholder="Notas adicionales (opcional)" style="min-height:60px;"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="Entradas.cerrarModal()">Cancelar</button>
            <button class="btn btn-success" id="btn-guardar-entrada" onclick="Entradas.guardar()">
                <i class="bi bi-check-lg"></i> Registrar entrada
            </button>
        </div>
    </div>
</div>

<!-- Modal carga masiva -->
<div class="modal-backdrop" id="modal-masiva">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-list-check"></i> Carga masiva de entradas</div>
            <button class="modal-close" onclick="Entradas.cerrarMasiva()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-grid mb-4">
                <div class="field">
                    <label for="m-pv">Punto de venta <span class="req">*</span></label>
                    <select id="m-pv" required onchange="Entradas.cargarProductosMasiva()">
                        <option value="">Selecciona PV</option>
                    </select>
                </div>
                <div class="field">
                    <label for="m-motivo">Motivo <span class="req">*</span></label>
                    <select id="m-motivo" required>
                        <option value="">Selecciona motivo</option>
                    </select>
                </div>
                <div class="field">
                    <label for="m-referencia">Referencia</label>
                    <input type="text" id="m-referencia" maxlength="100" placeholder="Nº factura, guía, etc.">
                </div>
                <div class="field" style="display:flex;align-items:flex-end;">
                    <button class="btn btn-secondary" onclick="Entradas.limpiarCantidades()">
                        <i class="bi bi-x-circle"></i> Limpiar
                    </button>
                </div>
            </div>

            <div class="field mb-3">
                <div class="search-input">
                    <i class="bi bi-search"></i>
                    <input type="text" id="m-buscar" placeholder="Filtrar productos..." oninput="Entradas.cargarProductosMasiva()">
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
            <button class="btn btn-secondary" onclick="Entradas.cerrarMasiva()">Cancelar</button>
            <button class="btn btn-success" id="btn-guardar-masiva" onclick="Entradas.guardarMasiva()">
                <i class="bi bi-check-lg"></i> Aplicar entradas
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>