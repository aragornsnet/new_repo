<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Inventario del Almacén';
$subtituloPagina = 'Stock actual del almacén central';
$paginaActiva = 'inventario';
$scriptsExtra = [
    BASE_URL . 'public/js/almacen_inventario.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Administrador', 'Almacenero'])) {
    redirigir('views/403.php');
}

$categorias = Database::fetchAll("
    SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre
");

$unidades = Database::fetchAll("
    SELECT nombre, abreviatura FROM unidades_medida WHERE activo = 1 ORDER BY orden
");
?>

<div class="page-header">
    <div>
        <h2 class="page-title">📦 Inventario del almacén</h2>
        <p class="page-subtitle">
            Total: <span id="total-items">—</span> registros
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>views/almacen/entradas.php" class="btn btn-success">
            <i class="bi bi-box-arrow-in-down"></i> Registrar entrada
        </a>
        <a href="<?= BASE_URL ?>views/almacen/entradas.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nuevo producto
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar producto</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Nombre o código de barras...">
            </div>
        </div>
        <div class="field">
            <label>Categoría</label>
            <select id="filtro-cat">
                <option value="">Todas</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= h($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Estado</label>
            <select id="filtro-estado">
                <option value="">Todos</option>
                <option value="ok">Solo OK</option>
                <option value="bajo">Solo stock bajo</option>
                <option value="negativo">Solo negativo</option>
            </select>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary btn-sm" onclick="AlmacenInventario.cargar()">
            <i class="bi bi-funnel-fill"></i> Aplicar filtros
        </button>
        <button class="btn btn-secondary btn-sm" onclick="AlmacenInventario.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-box-seam"></i> Stock del almacén
            <span class="badge badge-neutral ml-2" id="total-items-2">—</span>
        </div>
    </div>
    <div id="tabla-inventario">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: EDITAR PRODUCTO -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-editar-producto">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-pencil-square"></i> Editar producto
            </div>
            <button class="modal-close" onclick="document.getElementById('modal-editar-producto').classList.remove('active')">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="edit-id">

            <div class="form-grid">
                <div class="field span-2">
                    <label for="edit-nombre">Nombre <span class="req">*</span></label>
                    <input type="text" id="edit-nombre" required maxlength="120">
                </div>

                <div class="field">
                    <label for="edit-codigo">Código de barras</label>
                    <input type="text" id="edit-codigo" maxlength="50">
                    <div class="help">Opcional. Único si se especifica.</div>
                </div>

                <div class="field">
                    <label for="edit-categoria">Categoría <span class="req">*</span></label>
                    <select id="edit-categoria" required>
                        <option value="">Selecciona categoría</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= h($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="edit-unidad">Unidad de medida <span class="req">*</span></label>
                    <select id="edit-unidad" required>
                        <option value="">Selecciona unidad</option>
                        <?php foreach ($unidades as $u): ?>
                            <option value="<?= h($u['nombre']) ?>"><?= h($u['nombre']) ?> (<?= h($u['abreviatura']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="edit-stock-min">Stock mínimo</label>
                    <input type="number" id="edit-stock-min" min="0" value="5">
                </div>

                <div class="field span-full">
                    <label for="edit-descripcion">Descripción</label>
                    <textarea id="edit-descripcion" maxlength="255" style="min-height:60px;"></textarea>
                </div>
            </div>

            <div class="alert alert-info mt-3">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    El precio, costo y estado (activo/inactivo) los gestiona el administrador.
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="document.getElementById('modal-editar-producto').classList.remove('active')">
                Cancelar
            </button>
            <button class="btn btn-primary" id="btn-guardar-edicion" onclick="AlmacenInventario.guardarEdicion()">
                <i class="bi bi-check-lg"></i> Guardar
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: AJUSTAR STOCK -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-ajustar-stock">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-wrench-adjustable text-warning"></i> Ajustar stock
            </div>
            <button class="modal-close" onclick="document.getElementById('modal-ajustar-stock').classList.remove('active')">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="ajuste-producto-id">

            <div id="ajuste-producto-info" style="padding:12px;background:var(--surface-2);border-radius:var(--radius-md);margin-bottom:16px;">
                <div style="font-weight:600;" id="ajuste-producto-nombre">—</div>
                <div class="text-xs text-muted" id="ajuste-producto-stock">—</div>
            </div>

            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    <strong>Ajuste = corrección directa</strong><br>
                    Establece el stock al valor exacto que indiques. Úsalo para corregir mermas, errores de conteo o daños.
                </div>
            </div>

            <div class="field mb-3">
                <label for="ajuste-nuevo-stock">Nuevo stock <span class="req">*</span></label>
                <input type="number" id="ajuste-nuevo-stock" min="0" value="0" required>
                <div class="help">Valor exacto que tendrá el stock (0 o mayor)</div>
            </div>

            <div class="field">
                <label for="ajuste-motivo">Motivo <span class="req">*</span></label>
                <select id="ajuste-motivo" required>
                    <option value="">Selecciona motivo</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="document.getElementById('modal-ajustar-stock').classList.remove('active')">
                Cancelar
            </button>
            <button class="btn btn-warning" id="btn-guardar-ajuste" onclick="AlmacenInventario.guardarAjuste()">
                <i class="bi bi-check-lg"></i> Aplicar ajuste
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: VER DETALLE -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-detalle">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-info-circle"></i> Detalle del producto
            </div>
            <button class="modal-close" onclick="document.getElementById('modal-detalle').classList.remove('active')">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body" id="detalle-contenido">
            <div class="empty-state"><div class="spinner"></div></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>