<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Productos';
$subtituloPagina = 'Catálogo de productos';
$paginaActiva = 'productos';
$scriptsExtra = [
    BASE_URL . 'public/js/productos.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::esAdmin()) {
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
        <h2 class="page-title">📦 Productos</h2>
        <p class="page-subtitle">Total: <span id="total-prod">—</span> productos</p>
    </div>
</div>

<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Nombre, código de barras...">
            </div>
        </div>
        <div class="field">
            <label>Categoría</label>
            <select id="filtro-cat">
                <option value="">Todas</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= h($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Estado</label>
            <select id="filtro-estado">
                <option value="">Todos</option>
                <option value="activos">Solo activos</option>
                <option value="inactivos">Solo inactivos</option>
            </select>
        </div>
        <div class="field">
            <label>Stock</label>
            <select id="filtro-stock">
                <option value="">Todos</option>
                <option value="1">Solo con stock bajo</option>
            </select>
        </div>
    </div>
</div>

<div class="card">
    <div id="tabla-prod">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<div class="modal-backdrop" id="modal-prod">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-box-seam"></i>
                <span id="modal-titulo">Editar producto</span>
            </div>
            <button class="modal-close" onclick="Productos.cerrarModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form id="form-prod" class="form" novalidate>
                <input type="hidden" id="prod-id">

                <div class="form-grid">
                    <div class="field span-2">
                        <label for="prod-nombre">Nombre <span class="req">*</span></label>
                        <input type="text" id="prod-nombre" required maxlength="120" placeholder="Ej: Coca-Cola 600ml">
                    </div>

                    <div class="field">
                        <label for="prod-codigo">Código de barras</label>
                        <input type="text" id="prod-codigo" maxlength="50" placeholder="7501000000001">
                        <div class="help">Opcional. Único si se especifica.</div>
                    </div>

                    <div class="field">
                        <label for="prod-categoria">Categoría <span class="req">*</span></label>
                        <select id="prod-categoria" required>
                            <option value="">Selecciona categoría</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"><?= h($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="prod-unidad">Unidad de medida <span class="req">*</span></label>
                        <select id="prod-unidad" required>
                            <option value="">Selecciona unidad</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= h($u['nombre']) ?>"><?= h($u['nombre']) ?> (<?= h($u['abreviatura']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field span-full">
                        <label for="prod-descripcion">Descripción</label>
                        <textarea id="prod-descripcion" maxlength="255" placeholder="Descripción opcional" style="min-height:60px;"></textarea>
                    </div>

                    <div class="field">
                        <label for="prod-precio">Precio de venta <span class="req">*</span></label>
                        <input type="number" id="prod-precio" step="0.01" min="0" required placeholder="0.00">
                    </div>

                    <div class="field">
                        <label for="prod-costo">Costo</label>
                        <input type="number" id="prod-costo" step="0.01" min="0" placeholder="0.00">
                        <div class="help">Precio de compra (para cálculo de utilidad).</div>
                    </div>

                    <div class="field">
                        <label for="prod-stock-min">Stock mínimo</label>
                        <input type="number" id="prod-stock-min" min="0" value="5">
                        <div class="help">Alerta cuando el stock baje de este valor.</div>
                    </div>

                    <div class="field">
                        <label>Estado</label>
                        <label class="check">
                            <input type="checkbox" id="prod-activo" checked>
                            <span>Producto activo</span>
                        </label>
                    </div>
                </div>

                <div id="seccion-precio-cambio" style="display:none;">
                    <div class="alert alert-warning mt-3" id="aviso-precio"></div>
                    <div class="field">
                        <label for="prod-motivo">Motivo del cambio de precio</label>
                        <input type="text" id="prod-motivo" maxlength="150" placeholder="Ej: Actualización por inflación, promoción, etc.">
                        <div class="help">Recomendado. Queda registrado en el historial.</div>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="Productos.cerrarModal()">Cancelar</button>
            <button class="btn btn-primary" id="btn-guardar-prod" onclick="Productos.guardar()">
                <i class="bi bi-check-lg"></i> Guardar
            </button>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="modal-historial">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-clock-history"></i> Historial de cambios de precio</div>
            <button class="modal-close" onclick="Productos.cerrarHistorial()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-grid mb-3">
                <div class="field">
                    <label>Producto</label>
                    <select id="hist-prod">
                        <option value="">Todos los productos</option>
                    </select>
                </div>
                <div class="field">
                    <label>Desde</label>
                    <input type="date" id="hist-desde">
                </div>
                <div class="field">
                    <label>Hasta</label>
                    <input type="date" id="hist-hasta">
                </div>
                <div class="field" style="display:flex; align-items:flex-end;">
                    <button class="btn btn-primary" onclick="Productos.cargarHistorial()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
            <div id="hist-contenido">
                <div class="empty-state"><div class="spinner"></div></div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>