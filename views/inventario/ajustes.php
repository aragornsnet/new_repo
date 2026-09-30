<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Ajustes de Inventario';
$subtituloPagina = 'Corrección directa del stock';
$paginaActiva = 'ajustes';
$scriptsExtra = [
    BASE_URL . 'public/js/ajustes.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Supervisor', 'Administrador'])) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">🔧 Ajustes de inventario</h2>
        <p class="page-subtitle">Corrige el stock a un valor específico con motivo y descripción</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>views/inventario/movimientos.php" class="btn btn-secondary">
            <i class="bi bi-list-ul"></i> Ver movimientos
        </a>
        <button class="btn btn-warning" onclick="Ajustes.abrirNuevo()">
            <i class="bi bi-plus-lg"></i> Nuevo ajuste
        </button>
    </div>
</div>

<div class="alert alert-warning mb-4">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div class="alert-body">
        <div class="alert-title">Ajustes = corrección directa</div>
        El ajuste <strong>establece el stock al valor exacto</strong> que indiques, no suma ni resta. Úsalo solo para corregir errores de captura o conteo físico. Todo queda auditado.
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
        <button class="btn btn-primary btn-sm" onclick="Ajustes.cargar()">
            <i class="bi bi-funnel-fill"></i> Aplicar filtros
        </button>
        <button class="btn btn-secondary btn-sm" onclick="Ajustes.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-clock-history"></i> Ajustes registrados
            <span class="badge badge-neutral ml-2" id="total-ajustes">—</span>
        </div>
    </div>
    <div id="tabla-ajustes">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<!-- Modal ajuste -->
<div class="modal-backdrop" id="modal-ajuste">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-wrench-adjustable"></i> Nuevo ajuste de inventario</div>
            <button class="modal-close" onclick="Ajustes.cerrarModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form id="form-ajuste" class="form" novalidate>
                <div class="form-grid">
                    <div class="field span-full">
                        <label for="a-pv">Punto de venta <span class="req">*</span></label>
                        <select id="a-pv" required onchange="Ajustes.limpiarProducto()">
                            <option value="">Selecciona PV</option>
                        </select>
                    </div>

                    <div class="field span-2">
                        <label for="a-buscar">Buscar producto</label>
                        <div class="search-input">
                            <i class="bi bi-search"></i>
                            <input type="text" id="a-buscar" placeholder="Nombre o código..." autocomplete="off" oninput="Ajustes.buscarProducto()">
                        </div>
                    </div>

                    <div class="field span-full" id="a-resultados-wrap" style="display:none;">
                        <div id="a-resultados" style="max-height:200px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius-md);"></div>
                    </div>

                    <div class="field span-full" id="a-seleccionado-wrap" style="display:none;">
                        <div style="padding:12px;background:var(--warning-light);border:1px solid var(--warning);border-radius:var(--radius-md);display:flex;align-items:center;gap:12px;">
                            <i class="bi bi-wrench-adjustable" style="font-size:24px;color:var(--warning);"></i>
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:600;" id="a-sel-nombre">—</div>
                                <div class="text-xs text-muted" id="a-sel-info">—</div>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="Ajustes.limpiarProducto()">
                                <i class="bi bi-x-circle"></i> Cambiar
                            </button>
                        </div>
                        <input type="hidden" id="a-producto-id">
                    </div>

                    <div class="field">
                        <label for="a-stock-actual">Stock actual</label>
                        <input type="text" id="a-stock-actual" readonly style="background:var(--surface-2);font-weight:700;font-size:18px;">
                    </div>

                    <div class="field">
                        <label for="a-nuevo-valor">Nuevo stock <span class="req">*</span></label>
                        <input type="number" id="a-nuevo-valor" min="0" value="0" required oninput="Ajustes.calcularDiferencia()">
                        <div class="help">Valor exacto que tendrá el stock después del ajuste</div>
                    </div>

                    <div class="field span-full" id="a-diferencia-wrap" style="display:none;">
                        <div id="a-diferencia"></div>
                    </div>

                    <div class="field span-full">
                        <label for="a-motivo">Motivo <span class="req">*</span></label>
                        <select id="a-motivo" required>
                            <option value="">Selecciona motivo</option>
                        </select>
                    </div>

                    <div class="field span-full">
                        <label for="a-descripcion">Descripción detallada <span class="req">*</span></label>
                        <textarea id="a-descripcion" maxlength="255" required placeholder="Explica qué se corrigió y por qué (obligatorio)" style="min-height:80px;"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="Ajustes.cerrarModal()">Cancelar</button>
            <button class="btn btn-warning" id="btn-guardar-ajuste" onclick="Ajustes.guardar()">
                <i class="bi bi-check-lg"></i> Aplicar ajuste
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>