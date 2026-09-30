<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Entradas al Almacén';
$subtituloPagina = 'Registro de entradas de productos al almacén';
$paginaActiva = 'entradas';
$scriptsExtra = [
    BASE_URL . 'public/js/almacen_entradas.js',
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
        <h2 class="page-title">📥 Entradas al almacén</h2>
        <p class="page-subtitle">
            Total: <span id="total-entradas">—</span> entradas registradas
        </p>
    </div>
    <div class="page-actions">
        <button class="btn btn-success" onclick="AlmacenEntradas.abrirNueva()">
            <i class="bi bi-plus-lg"></i> Nueva entrada
        </button>
    </div>
</div>

<div class="alert alert-info mb-4">
    <i class="bi bi-info-circle-fill"></i>
    <div class="alert-body">
        <strong>Entradas al almacén:</strong> registra la llegada de mercancía al almacén central.
        Puedes <strong>buscar un producto existente</strong> o <strong>crear uno nuevo</strong> desde el mismo modal.
        Al registrar una entrada puedes asociar un <strong>contrato de proveedor</strong> y el <strong>número de factura de adquisición</strong>.
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="form-grid">
        <div class="field">
            <label>Buscar producto</label>
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" id="filtro-q" placeholder="Producto, código, motivo, factura o contrato...">
            </div>
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
        <button class="btn btn-primary btn-sm" onclick="AlmacenEntradas.cargar()">
            <i class="bi bi-funnel-fill"></i> Aplicar filtros
        </button>
        <button class="btn btn-secondary btn-sm" onclick="AlmacenEntradas.limpiar()">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-clock-history"></i> Historial de entradas
            <span class="badge badge-neutral ml-2" id="total-entradas-2">—</span>
        </div>
    </div>
    <div id="tabla-entradas">
        <div class="empty-state"><div class="spinner spinner-lg"></div></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL: NUEVA ENTRADA (buscar o crear producto)              -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modal-entrada">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title">
                <i class="bi bi-box-arrow-in-down text-success"></i> Nueva entrada
            </div>
            <button class="modal-close" onclick="AlmacenEntradas.cerrarModal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- MODO: BUSCAR PRODUCTO EXISTENTE                     -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div id="modo-buscar">
                <div class="form-grid">
                    <div class="field span-full">
                        <label for="e-buscar">Buscar producto <span class="req">*</span></label>
                        <div class="search-input">
                            <i class="bi bi-search"></i>
                            <input type="text" id="e-buscar" placeholder="Nombre o código de barras..."
                                   autocomplete="off" oninput="AlmacenEntradas.buscarProducto()">
                        </div>
                    </div>

                    <div class="field span-full" id="e-resultados-wrap" style="display:none;">
                        <div id="e-resultados"
                             style="max-height:260px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius-md);"></div>
                    </div>

                    <div class="field span-full" id="e-seleccionado-wrap" style="display:none;">
                        <div style="padding:12px;background:var(--success-light);border:1px solid var(--success);border-radius:var(--radius-md);display:flex;align-items:center;gap:12px;">
                            <i class="bi bi-check-circle-fill" style="font-size:24px;color:var(--success);"></i>
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:600;" id="e-sel-nombre">—</div>
                                <div class="text-xs text-muted" id="e-sel-info">—</div>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="AlmacenEntradas.limpiarProducto()">
                                <i class="bi bi-x-circle"></i> Cambiar
                            </button>
                        </div>
                        <input type="hidden" id="e-producto-id">
                    </div>
                </div>

                <!-- Botón para cambiar a modo "crear producto" -->
                <div class="mt-4" style="padding:16px;background:var(--surface-2);border-radius:var(--radius-md);text-align:center;">
                    <div class="text-sm text-muted mb-2">¿El producto no existe en el sistema?</div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="AlmacenEntradas.cambiarModoCrear()">
                        <i class="bi bi-plus-circle"></i> Crear producto nuevo
                    </button>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- MODO: CREAR PRODUCTO NUEVO                          -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div id="modo-crear" style="display:none;">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill"></i>
                    <div>
                        <strong>Producto nuevo</strong><br>
                        Al guardar se creará el producto y se registrará la entrada inicial al almacén.
                        El precio y costo los asigna el administrador después.
                    </div>
                </div>

                <div class="form-grid">
                    <div class="field span-2">
                        <label for="p-nombre">Nombre <span class="req">*</span></label>
                        <input type="text" id="p-nombre" required maxlength="120" placeholder="Ej: Coca-Cola 600ml">
                    </div>

                    <div class="field">
                        <label for="p-codigo">Código de barras</label>
                        <input type="text" id="p-codigo" maxlength="50" placeholder="7501000000001">
                        <div class="help">Opcional. Único si se especifica.</div>
                    </div>

                    <div class="field">
                        <label for="p-categoria">Categoría <span class="req">*</span></label>
                        <select id="p-categoria" required>
                            <option value="">Selecciona categoría</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= h($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="p-unidad">Unidad de medida <span class="req">*</span></label>
                        <select id="p-unidad" required>
                            <option value="">Selecciona unidad</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= h($u['nombre']) ?>"><?= h($u['nombre']) ?> (<?= h($u['abreviatura']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="p-stock-min">Stock mínimo</label>
                        <input type="number" id="p-stock-min" min="0" value="5">
                        <div class="help">Alerta cuando el stock baje de este valor.</div>
                    </div>

                    <div class="field span-full">
                        <label for="p-descripcion">Descripción</label>
                        <textarea id="p-descripcion" maxlength="255" placeholder="Descripción opcional" style="min-height:60px;"></textarea>
                    </div>

                    <div class="field">
                        <label for="p-cantidad">Cantidad inicial <span class="req">*</span></label>
                        <input type="number" id="p-cantidad" min="1" value="1" required>
                        <div class="help" id="p-cantidad-help">Stock que ingresará al almacén</div>
                    </div>

                    <div class="field">
                        <label for="p-motivo">Motivo de la entrada</label>
                        <input type="text" id="p-motivo" maxlength="200" value="Alta inicial de producto">
                    </div>

                    <!-- ⭐ NUEVO: contrato -->
                    <div class="field">
                        <label for="p-contrato">Contrato de proveedor</label>
                        <select id="p-contrato">
                            <option value="">Sin contrato</option>
                        </select>
                        <div class="help" id="p-contrato-help">
                            Selecciona el contrato bajo el cual llega esta mercancía (opcional)
                        </div>
                    </div>

                    <!-- ⭐ NUEVO: número de factura -->
                    <div class="field">
                        <label for="p-num-factura">Nº factura de adquisición</label>
                        <input type="text" id="p-num-factura" maxlength="100"
                               placeholder="Ej: FAC-2026-1234">
                        <div class="help">Número de factura emitida por el proveedor</div>
                    </div>
                </div>

                <div class="mt-4" style="padding:12px;background:var(--surface-2);border-radius:var(--radius-md);text-align:center;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="AlmacenEntradas.cambiarModoBuscar()">
                        <i class="bi bi-arrow-left"></i> Volver a buscar producto existente
                    </button>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- SECCIÓN DE CANTIDAD (solo visible en modo buscar)   -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div id="seccion-cantidad-buscar" style="display:none;margin-top:16px;">
                <hr style="margin:var(--space-4) 0;border:none;border-top:1px solid var(--border);">

                <div class="form-grid">
                    <div class="field">
                        <label for="e-cantidad">Cantidad <span class="req">*</span></label>
                        <input type="number" id="e-cantidad" min="1" value="1" required oninput="AlmacenEntradas.actualizarStockHelp()">
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
                        <input type="text" id="e-referencia" maxlength="100" placeholder="Ej: guía de despacho">
                        <div class="help">Opcional</div>
                    </div>

                    <!-- ⭐ NUEVO: contrato -->
                    <div class="field">
                        <label for="e-contrato">Contrato de proveedor</label>
                        <select id="e-contrato">
                            <option value="">Sin contrato</option>
                        </select>
                        <div class="help" id="e-contrato-help">
                            Solo aparecen los contratos vinculados al producto seleccionado
                        </div>
                    </div>

                    <!-- ⭐ NUEVO: número de factura -->
                    <div class="field">
                        <label for="e-num-factura">Nº factura de adquisición</label>
                        <input type="text" id="e-num-factura" maxlength="100"
                               placeholder="Ej: FAC-2026-1234">
                        <div class="help">Número de factura emitida por el proveedor</div>
                    </div>

                    <div class="field span-full">
                        <label for="e-descripcion">Descripción / Notas</label>
                        <textarea id="e-descripcion" maxlength="255"
                                  placeholder="Notas adicionales (opcional)" style="min-height:60px;"></textarea>
                    </div>
                </div>
            </div>

        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="AlmacenEntradas.cerrarModal()">Cancelar</button>
            <button class="btn btn-success" id="btn-guardar-entrada" onclick="AlmacenEntradas.guardar()">
                <i class="bi bi-check-lg"></i> Registrar entrada
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>