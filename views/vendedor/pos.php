<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Punto de Venta';
$subtituloPagina = 'Cobra a los clientes';
$paginaActiva = 'pos';
$scriptsExtra = [
    BASE_URL . 'public/js/pos.js',
    BASE_URL . 'public/js/pos_cobro_avanzado.js',
];
$cssExtra = [
    BASE_URL . 'public/css/pos.css',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::tieneRol(['Vendedor', 'Supervisor', 'Administrador'])) {
    redirigir('views/403.php');
}

// Verificar turno abierto en PHP
$turnoAbierto = Database::fetchOne("
    SELECT t.id, pv.nombre AS pv
    FROM turnos t
    JOIN puntos_venta pv ON pv.id = t.punto_venta_id
    WHERE t.usuario_id = ? AND t.estado = 'abierto'
    LIMIT 1
", [Auth::id()]);

if (!$turnoAbierto) {
    redirigir('views/vendedor/dashboard.php');
}
?>

<div class="pos-wrapper">

    <!-- Cabecera compacta -->
    <div class="pos-header">
        <div class="pos-header-left">
            <a href="<?= BASE_URL ?>views/vendedor/dashboard.php" class="btn btn-ghost btn-icon" title="Volver">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <div class="pos-header-title">
                    <i class="bi bi-cart-check-fill"></i> POS
                </div>
                <div class="pos-header-sub">
                    <?= h($turnoAbierto['pv']) ?> · Turno #<?= (int) $turnoAbierto['id'] ?>
                </div>
            </div>
        </div>
        <div class="pos-header-right">
            <div class="pos-vista-toggle">
                <button class="pos-vista-btn active" data-vista="mosaico" onclick="POS.cambiarVista('mosaico')" title="Vista mosaico">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                </button>
                <button class="pos-vista-btn" data-vista="lista" onclick="POS.cambiarVista('lista')" title="Vista lista">
                    <i class="bi bi-list-ul"></i>
                </button>
            </div>
            <button class="btn btn-ghost btn-sm" onclick="POS.abrirAyuda()" title="Atajos de teclado">
                <i class="bi bi-keyboard"></i> Atajos
            </button>
            <button class="btn btn-danger btn-sm" onclick="POS.limpiarCarritoConConfirmacion()" title="Cancelar venta">
                <i class="bi bi-x-circle"></i> Cancelar
            </button>
        </div>
    </div>

    <!-- Cuerpo: 2 columnas -->
    <div class="pos-body">

        <!-- COLUMNA IZQUIERDA: productos -->
        <div class="pos-productos">

            <!-- Buscador -->
            <div class="pos-buscador">
                <div class="pos-buscador-input">
                    <i class="bi bi-search"></i>
                    <input type="text" id="pos-q" placeholder="Buscar por nombre o código de barras... (F2)" autocomplete="off">
                    <button class="pos-buscador-clear" onclick="POS.limpiarBusqueda()" title="Limpiar (Esc)">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>
                <button class="pos-barcode-btn" onclick="POS.activarScanner()" title="Activar lector de código de barras">
                    <i class="bi bi-upc-scan"></i>
                </button>
            </div>

            <!-- Tabs de categorías -->
            <div class="pos-categorias" id="pos-categorias">
                <div class="empty-state" style="padding:20px;">
                    <div class="spinner"></div>
                </div>
            </div>

            <!-- Grid de productos -->
            <div class="pos-grid" id="pos-grid">
                <div class="empty-state">
                    <div class="spinner spinner-lg"></div>
                    <p class="text-muted mt-3">Cargando productos...</p>
                </div>
            </div>

            <!-- Paginación -->
            <div class="pos-paginacion" id="pos-paginacion"></div>
        </div>

        <!-- COLUMNA DERECHA: carrito -->
        <div class="pos-carrito">
            <div class="pos-carrito-header">
                <div class="pos-carrito-title">
                    <i class="bi bi-cart-fill"></i> Carrito
                    <span class="pos-carrito-count" id="carrito-count">0</span>
                </div>
                <button class="btn btn-ghost btn-sm" onclick="POS.limpiarCarritoConConfirmacion()" id="btn-limpiar-carrito" disabled>
                    <i class="bi bi-trash"></i>
                </button>
            </div>

            <div class="pos-carrito-items" id="carrito-items">
                <div class="pos-carrito-vacio">
                    <i class="bi bi-cart-x"></i>
                    <p>El carrito está vacío</p>
                    <p class="text-muted text-xs">Agrega productos para empezar</p>
                </div>
            </div>

            <div class="pos-carrito-footer">
                <div class="pos-total">
                    <div class="pos-total-label">TOTAL</div>
                    <div class="pos-total-value" id="carrito-total">$0.00</div>
                </div>

                <div class="pos-acciones">
                    <button class="btn btn-secondary btn-block" onclick="POS.limpiarCarritoConConfirmacion()" id="btn-cancelar" disabled>
                        <i class="bi bi-x-circle"></i> Cancelar
                    </button>
                    <button class="btn btn-success btn-block pos-btn-cobrar" onclick="POS.abrirCobro()" id="btn-cobrar" disabled>
                        <i class="bi bi-cash-coin"></i> COBRAR <span class="text-xs" style="opacity:.8;">(F4)</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de cobro -->
<div class="modal-backdrop" id="modal-cobro">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-cash-coin"></i> Cobrar</div>
            <button class="modal-close" onclick="POS.cerrarCobro()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">

            <!-- Total a cobrar -->
            <div style="text-align:center;padding:16px 0;border-bottom:1px solid var(--border);margin-bottom:20px;">
                <div class="text-muted text-sm">Total a cobrar</div>
                <div style="font-size:42px;font-weight:800;color:var(--primary);" id="cobro-total">$0.00</div>
            </div>

            <!-- ⭐ NUEVO: Requiere factura -->
            <div class="field mb-3" style="padding:12px;background:var(--surface-2);border-radius:var(--radius-md);">
                <label class="check" style="font-weight:600;">
                    <input type="checkbox" id="requiere-factura" onchange="POSCobro.toggleRequiereFactura()">
                    <span>Requiere factura (cliente mayorista)</span>
                </label>

                <div id="factura-panel" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid var(--border);">
                    <div class="field mb-2">
                        <label for="factura-cliente">Cliente <span class="req">*</span></label>
                        <div class="d-flex gap-2">
                            <select id="factura-cliente" style="flex:1;">
                                <option value="">Selecciona cliente</option>
                            </select>
                            <button type="button" class="btn btn-secondary" onclick="POSCobro.abrirNuevoCliente()" title="Crear nuevo cliente">
                                <i class="bi bi-person-plus"></i>
                            </button>
                        </div>
                        <div class="help">Solo clientes mayoristas</div>
                    </div>
                </div>
            </div>

            <!-- ⭐ NUEVO: Emitir comprobante -->
            <div class="field mb-3" style="padding:12px;background:var(--surface-2);border-radius:var(--radius-md);">
                <label class="check" style="font-weight:600;">
                    <input type="checkbox" id="emitir-comprobante" onchange="POSCobro.toggleEmitirComprobante()">
                    <span>Emitir comprobante al cliente</span>
                </label>

                <div id="comprobante-panel" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid var(--border);">
                    <div class="alert alert-info" style="padding:8px 12px;font-size:12px;margin-bottom:10px;">
                        <i class="bi bi-info-circle-fill"></i>
                        Los datos del comprador son opcionales. Si no los das, se emite como "Consumidor final".
                    </div>

                    <div class="form-grid">
                        <div class="field">
                            <label for="comp-nombre">Nombre del comprador</label>
                            <input type="text" id="comp-nombre" maxlength="150" placeholder="Opcional">
                        </div>

                        <div class="field">
                            <label for="comp-documento">Documento</label>
                            <input type="text" id="comp-documento" maxlength="50" placeholder="Opcional">
                        </div>

                        <div class="field">
                            <label for="comp-telefono">Teléfono</label>
                            <input type="text" id="comp-telefono" maxlength="50" placeholder="Opcional">
                        </div>

                        <div class="field">
                            <label for="comp-direccion">Dirección</label>
                            <input type="text" id="comp-direccion" maxlength="255" placeholder="Opcional">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Método de pago -->
            <div class="field mb-3">
                <label>Método de pago</label>
                <div class="d-flex gap-2" style="flex-wrap:wrap;">
                    <button type="button" class="btn btn-success cobro-metodo active" data-metodo="efectivo" onclick="POSCobro.setMetodo('efectivo')" style="flex:1;min-width:110px;">
                        <i class="bi bi-cash"></i> Efectivo
                    </button>
                    <button type="button" class="btn btn-secondary cobro-metodo" data-metodo="transferencia" onclick="POSCobro.setMetodo('transferencia')" style="flex:1;min-width:110px;">
                        <i class="bi bi-bank"></i> Transferencia
                    </button>
                    <button type="button" class="btn btn-secondary cobro-metodo" data-metodo="mixto" onclick="POSCobro.setMetodo('mixto')" style="flex:1;min-width:110px;">
                        <i class="bi bi-shuffle"></i> Mixto
                    </button>
                </div>
            </div>

            <!-- Selector de moneda (CUP / Divisas) -->
            <div class="field mb-3" id="moneda-selector" style="display:none;">
                <label>Moneda de cobro</label>
                <div class="d-flex gap-2" id="moneda-botones">
                    <button type="button" class="btn btn-primary btn-sm moneda-btn active" data-moneda="CUP" onclick="POSCobro.setMoneda('CUP')">
                        <i class="bi bi-currency-dollar"></i> CUP
                    </button>
                </div>
            </div>

            <!-- EFECTIVO EN CUP -->
            <div id="cobro-efectivo">
                <div class="field mb-3">
                    <label for="monto-recibido-simple">Monto recibido <span class="req">*</span></label>
                    <input type="number" id="monto-recibido-simple" min="0" step="0.01" placeholder="0.00"
                           oninput="POSCobro.recalcularTodo()">
                </div>
            </div>

            <!-- TRANSFERENCIA -->
            <div id="cobro-transferencia" style="display:none;">
                <div class="field mb-3">
                    <label for="metodo-detalle">Método <span class="req">*</span></label>
                    <select id="metodo-detalle" onchange="POSCobro.actualizarEtiquetaUltimosDigitos()">
                        <option value="Transfermóvil">Transfermóvil</option>
                        <option value="EnZona">EnZona</option>
                        <option value="Tarjeta débito">Tarjeta débito</option>
                        <option value="Tarjeta crédito">Tarjeta crédito</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                <div class="field mb-3">
                    <label for="referencia">Número de referencia <span class="req">*</span></label>
                    <input type="text" id="referencia" maxlength="100" placeholder="Ej: 1234567890"
                           oninput="POSCobro.recalcularTodo()">
                </div>
                <div class="field mb-3">
                    <label for="ultimos-digitos" id="label-ultimos-digitos">Últimos 4 dígitos del teléfono</label>
                    <input type="text" id="ultimos-digitos" maxlength="4" placeholder="4567">
                    <div class="help" id="help-ultimos-digitos">Los últimos 4 dígitos del teléfono desde el que se realizó la transferencia</div>
                </div>
                <div class="field mb-3">
                    <label for="titular">Titular</label>
                    <input type="text" id="titular" maxlength="100" placeholder="Nombre del titular">
                </div>

                <!-- Comprobante -->
                <div class="field mb-3" id="comprobante-wrap" style="display:none;">
                    <label for="comprobante-input">Comprobante <span class="req" id="comprobante-req" style="display:none;">*</span></label>
                    <div id="comprobante-upload" class="upload-zone" style="padding:16px;text-align:center;cursor:pointer;" onclick="document.getElementById('comprobante-input').click()">
                        <i class="bi bi-cloud-arrow-up" style="font-size:32px;"></i>
                        <div class="upload-zone-text">Subir comprobante</div>
                        <div class="upload-zone-hint" id="comprobante-hint">JPG, PNG, WEBP o PDF · Máx 5 MB</div>
                    </div>
                    <input type="file" id="comprobante-input" accept=".jpg,.jpeg,.png,.webp,.pdf" style="display:none;"
                           onchange="POSCobro.subirComprobante(this)">
                    <div id="comprobante-preview" style="display:none;margin-top:12px;"></div>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill"></i>
                    <div>La transferencia quedará <strong>pendiente de verificación</strong> por el supervisor.</div>
                </div>
            </div>

            <!-- MIXTO: monto transferencia -->
            <div id="cobro-mixto-wrap" style="display:none;">
                <div class="field mb-3">
                    <label for="monto-transferencia">Monto de la transferencia <span class="req">*</span></label>
                    <input type="number" id="monto-transferencia" min="0" step="0.01" placeholder="0.00"
                           oninput="POSCobro.recalcularTodo()">
                </div>
            </div>

            <!-- DIVISA: panel de conversión -->
            <div id="divisa-panel" style="display:none;">
                <div class="card" style="background:var(--info-light);border:1px solid var(--info);padding:16px;border-radius:var(--radius-md);margin-bottom:16px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                        <span class="text-sm text-muted">Total en CUP:</span>
                        <strong id="divisa-total-cup">$0.00</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                        <span class="text-sm text-muted">Tasa aplicada:</span>
                        <strong id="divisa-tasa">—</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding-top:8px;border-top:1px solid var(--info);">
                        <span style="font-weight:700;">TOTAL EN DIVISA:</span>
                        <strong style="font-size:20px;color:var(--primary);" id="divisa-total">—</strong>
                    </div>
                    <div class="text-xs text-muted mt-2" id="divisa-fuente">Fuente: —</div>
                </div>

                <div class="field mb-3">
                    <label for="monto-recibido-divisa">Monto recibido en divisa <span class="req">*</span></label>
                    <input type="number" id="monto-recibido-divisa" min="0" step="0.01" placeholder="0.00"
                           oninput="POSCobro.calcularVueltoDivisa()">
                </div>

                <div class="cobro-cambio" id="divisa-vuelto-wrap">
                    <div class="cobro-cambio-label">VUELTO EN CUP</div>
                    <div class="cobro-cambio-value" id="divisa-vuelto">$0.00</div>
                </div>
            </div>

            <!-- RESUMEN DE PAGO -->
            <div class="cobro-resumen-row" style="margin-top:16px;">
                <div>
                    <div class="text-xs text-muted">Total recibido</div>
                    <div style="font-size:22px;font-weight:700;color:var(--success);" id="cobro-total-recibido">$0.00</div>
                </div>
                <div style="text-align:right;">
                    <div class="text-xs text-muted">Vuelto</div>
                    <div style="font-size:22px;font-weight:700;color:var(--warning);" id="cobro-cambio">$0.00</div>
                </div>
            </div>
            <div style="text-align:center;margin-top:12px;" id="cobro-estado-pago"></div>

        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="POS.cerrarCobro()">Cancelar</button>
            <button class="btn btn-success" id="btn-confirmar-cobro" onclick="POS.confirmarCobroAvanzado()" disabled>
                <i class="bi bi-check-lg"></i> Confirmar cobro
            </button>
        </div>
    </div>
</div>

<!-- Modal de éxito -->
<div class="modal-backdrop" id="modal-exito">
    <div class="modal" style="max-width:440px;">
        <div class="modal-body" style="text-align:center;padding:40px 24px;">
            <div style="width:96px;height:96px;border-radius:50%;background:var(--success-light);color:var(--success);display:flex;align-items:center;justify-content:center;font-size:52px;margin:0 auto 20px;">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <h2 style="font-size:24px;margin-bottom:8px;">¡Cobro exitoso!</h2>
            <div class="text-muted mb-4" id="exito-folio">Folio: —</div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;text-align:left;">
                <div style="padding:12px;background:var(--surface-2);border-radius:var(--radius-md);">
                    <div class="text-xs text-muted">Total</div>
                    <div style="font-weight:700;font-size:18px;color:var(--primary);" id="exito-total">$0.00</div>
                </div>
                <div style="padding:12px;background:var(--surface-2);border-radius:var(--radius-md);" id="exito-cambio-wrap">
                    <div class="text-xs text-muted">Vuelto</div>
                    <div style="font-weight:700;font-size:18px;color:var(--warning);" id="exito-cambio">$0.00</div>
                </div>
            </div>

            <div id="exito-vuelto-sugerido"></div>

            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-primary btn-block btn-lg" onclick="POS.imprimirDesdeExito()" id="btn-imprimir-ticket">
                    <i class="bi bi-printer"></i> Imprimir ticket
                </button>
                <button class="btn btn-success btn-block btn-lg" onclick="POS.cerrarExitoYNueva()">
                    <i class="bi bi-plus-circle"></i> Nueva venta
                </button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>