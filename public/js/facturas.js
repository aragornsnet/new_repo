/**
 * IPV - Gestión de Facturas al por mayor
 */

const Facturas = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let lista = [];
    let puedeEmitir = false;
    let ventaSeleccionada = null;

    // ═══════════════════════════════════════════════════════════
    // LISTAR
    // ═══════════════════════════════════════════════════════════
    async function cargar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const estado = document.getElementById('filtro-estado')?.value || '';
        const desde = document.getElementById('filtro-desde')?.value || '';
        const hasta = document.getElementById('filtro-hasta')?.value || '';
        const vencidas = document.getElementById('filtro-vencidas')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (estado) params.append('estado', estado);
        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);
        if (vencidas) params.append('vencidas', vencidas);

        const cont = document.getElementById('tabla-facturas');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get('api/facturas.php?accion=listar&' + params);
        if (!res.success) {
            Toast.error('Error', res.message);
            cont.innerHTML = `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        lista = res.data;
        document.getElementById('total-facturas').textContent = lista.length;
        document.getElementById('total-facturas-2').textContent = lista.length;

        actualizarResumen();
        render();
    }

    // ═══════════════════════════════════════════════════════════
    // RESUMEN (tarjetas)
    // ═══════════════════════════════════════════════════════════
    async function actualizarResumen() {
        const res = await Api.get('api/facturas.php?accion=listar');
        if (!res.success) return;

        const counts = { emitida: 0, parcial: 0, pagada: 0, anulada: 0 };
        res.data.forEach(f => {
            if (counts[f.estado] !== undefined) counts[f.estado]++;
        });

        document.getElementById('res-emitidas').textContent = counts.emitida;
        document.getElementById('res-parciales').textContent = counts.parcial;
        document.getElementById('res-pagadas').textContent = counts.pagada;
        document.getElementById('res-anuladas').textContent = counts.anulada;
    }

    // ═══════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════
    function render() {
        const cont = document.getElementById('tabla-facturas');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-receipt"></i>
                <h3>Sin facturas</h3>
                <p>No hay facturas que coincidan con los filtros.</p>
                ${puedeEmitir ? `<button class="btn btn-success btn-sm mt-3" onclick="Facturas.abrirPendientes()">
                    <i class="bi bi-receipt"></i> Emitir primera factura
                </button>` : ''}
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Cliente</th>
                            <th>Emisión</th>
                            <th>Vencimiento</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Saldo</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(f => {
                            const badge = badgeEstado(f.estado);
                            const saldo = parseFloat(f.saldo_pendiente) || 0;
                            const vencida = f.dias_vencidos !== null && f.dias_vencidos > 0;

                            return `
                                <tr style="${f.estado === 'anulada' ? 'opacity:.6;' : ''}">
                                    <td>
                                        <strong>${App.escapeHtml(f.folio)}</strong>
                                        <div class="text-xs text-muted">Venta: ${App.escapeHtml(f.venta_folio)}</div>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;">${App.escapeHtml(f.cliente)}</div>
                                        ${f.cliente_nit ? `<div class="text-xs text-muted">${App.escapeHtml(f.cliente_nit)}</div>` : ''}
                                    </td>
                                    <td class="text-muted text-sm">
                                        ${App.formatDate(f.fecha_emision).split(' ')[0]}
                                    </td>
                                    <td class="text-muted text-sm">
                                        ${f.fecha_vencimiento ? App.formatDate(f.fecha_vencimiento).split(' ')[0] : '—'}
                                        ${vencida ? `<div class="text-xs" style="color:var(--danger);font-weight:600;">
                                            <i class="bi bi-exclamation-triangle-fill"></i> ${f.dias_vencidos}d vencida
                                        </div>` : ''}
                                    </td>
                                    <td class="text-right"><strong>${App.formatMoney(f.total)}</strong></td>
                                    <td class="text-right" style="${saldo > 0 ? 'color:var(--danger);font-weight:600;' : 'color:var(--success);'}">
                                        ${App.formatMoney(saldo)}
                                    </td>
                                    <td><span class="badge badge-${badge.color}">${badge.label}</span></td>
                                    <td class="text-right">
                                        <div class="d-flex gap-1" style="justify-content:flex-end;">
                                            <button class="btn btn-ghost btn-icon"
                                                    onclick="Facturas.verDetalle(${f.id})"
                                                    title="Ver detalle">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-ghost btn-icon"
                                                    onclick="Facturas.vistaPrevia(${f.id})"
                                                    title="Vista previa PDF">
                                                <i class="bi bi-file-earmark-pdf" style="color:var(--danger);"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function badgeEstado(estado) {
        const map = {
            emitida: { color: 'warning', label: 'Emitida' },
            parcial: { color: 'info',    label: 'Parcial' },
            pagada:  { color: 'success', label: 'Pagada' },
            anulada: { color: 'danger',  label: 'Anulada' },
        };
        return map[estado] || { color: 'neutral', label: estado };
    }

    // ═══════════════════════════════════════════════════════════
    // ATAJOS
    // ═══════════════════════════════════════════════════════════
    function atajo(estado) {
        document.getElementById('filtro-estado').value = estado;
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-desde').value = '';
        document.getElementById('filtro-hasta').value = '';
        document.getElementById('filtro-vencidas').value = '';
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // MODAL: VENTAS PENDIENTES DE FACTURAR
    // ═══════════════════════════════════════════════════════════
    async function abrirPendientes() {
        document.getElementById('modal-pendientes').classList.add('active');
        await cargarPendientes();
    }

    function cerrarPendientes() {
        document.getElementById('modal-pendientes')?.classList.remove('active');
    }

    async function cargarPendientes() {
        const q = document.getElementById('pend-buscar')?.value.trim() || '';
        const cont = document.getElementById('lista-pendientes');
        cont.innerHTML = '<div class="empty-state"><div class="spinner"></div></div>';

        const params = new URLSearchParams();
        if (q) params.append('q', q);

        const res = await Api.get('api/facturas.php?accion=pendientes_facturar&' + params);
        if (!res.success) {
            cont.innerHTML = `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        const ventas = res.data;

        if (!ventas.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-check-circle" style="font-size:48px;color:var(--success);"></i>
                <h3>No hay ventas pendientes</h3>
                <p>Todas las ventas que requieren factura ya fueron facturadas.</p>
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap" style="max-height:450px;overflow-y:auto;">
                <table class="tabla">
                    <thead style="position:sticky;top:0;background:var(--surface-2);z-index:1;">
                        <tr>
                            <th>Folio venta</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>PV</th>
                            <th>Vendedor</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${ventas.map(v => `
                            <tr>
                                <td><strong>${App.escapeHtml(v.folio)}</strong></td>
                                <td class="text-muted text-xs">${App.formatDate(v.fecha)}</td>
                                <td>
                                    <div style="font-weight:600;">${App.escapeHtml(v.cliente)}</div>
                                    ${v.cliente_nit ? `<div class="text-xs text-muted">${App.escapeHtml(v.cliente_nit)}</div>` : ''}
                                </td>
                                <td class="text-muted">${App.escapeHtml(v.pv)}</td>
                                <td class="text-muted text-sm">${App.escapeHtml(v.vendedor)}</td>
                                <td class="text-right"><strong>${App.formatMoney(v.total)}</strong></td>
                                <td class="text-right">
                                    <button class="btn btn-success btn-sm"
                                            onclick="Facturas.abrirEmitir(${v.id})">
                                        <i class="bi bi-receipt"></i> Emitir
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    const buscarPendientesDebounced = App.debounce(cargarPendientes, 400);
    function buscarPendientes() {
        buscarPendientesDebounced();
    }

    // ═══════════════════════════════════════════════════════════
    // MODAL: EMITIR FACTURA
    // ═══════════════════════════════════════════════════════════
    async function abrirEmitir(ventaId) {
        cerrarPendientes();

        document.getElementById('modal-emitir').classList.add('active');
        const cont = document.getElementById('emitir-contenido');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        // Cargar datos de la venta
        const res = await Api.get(`api/ventas_supervisor.php?accion=obtener&id=${ventaId}`);
        if (!res.success) {
            cont.innerHTML = `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        const v = res.data;
        ventaSeleccionada = v;

        // Sugerir fecha de vencimiento
        const cfgRes = await Api.get('api/facturas.php?accion=catalogos');
        const plazoDias = cfgRes.success ? (cfgRes.data.plazo_dias_default || 30) : 30;

        const hoy = new Date();
        const vencimiento = new Date(hoy.getTime() + plazoDias * 86400000);
        const vencimientoStr = vencimiento.toISOString().split('T')[0];

        cont.innerHTML = `
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    Se emitirá una factura vinculada a la venta <strong>${App.escapeHtml(v.folio)}</strong>.
                    Una vez emitida no se puede modificar.
                </div>
            </div>

            <div class="grid-3 mb-4">
                <div>
                    <div class="text-xs text-muted">Folio venta</div>
                    <div style="font-weight:600;">${App.escapeHtml(v.folio)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Cliente</div>
                    <div style="font-weight:600;">${App.escapeHtml(v.vendedor)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Punto de venta</div>
                    <div style="font-weight:600;">${App.escapeHtml(v.pv)}</div>
                </div>
            </div>

            <h4 style="font-size:15px;margin-bottom:10px;">
                <i class="bi bi-box-seam text-primary"></i> Productos de la venta
            </h4>

            <div class="tabla-wrap mb-4" style="max-height:250px;overflow-y:auto;">
                <table class="tabla">
                    <thead style="position:sticky;top:0;background:var(--surface-2);">
                        <tr>
                            <th>Producto</th>
                            <th class="text-right">Cant.</th>
                            <th class="text-right">Precio</th>
                            <th class="text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${v.detalle.map(d => `
                            <tr>
                                <td>${App.escapeHtml(d.producto)}</td>
                                <td class="text-right">${d.cantidad} ${App.escapeHtml(d.unidad_medida || '')}</td>
                                <td class="text-right">${App.formatMoney(d.precio_unitario)}</td>
                                <td class="text-right"><strong>${App.formatMoney(d.subtotal)}</strong></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>

            <hr style="margin:var(--space-4) 0;border:none;border-top:1px solid var(--border);">

            <div class="form-grid">
                <div class="field">
                    <label for="emitir-vencimiento">Fecha de vencimiento</label>
                    <input type="date" id="emitir-vencimiento" value="${vencimientoStr}">
                    <div class="help">Plazo por defecto: ${plazoDias} días</div>
                </div>

                <div class="field">
                    <label for="emitir-descuento">Descuento</label>
                    <input type="number" id="emitir-descuento" step="0.01" min="0" value="0"
                           oninput="Facturas.recalcularEmitir()">
                </div>

                <div class="field">
                    <label for="emitir-impuesto">Impuesto</label>
                    <input type="number" id="emitir-impuesto" step="0.01" min="0" value="0"
                           oninput="Facturas.recalcularEmitir()">
                </div>

                <div class="field span-full">
                    <label for="emitir-observaciones">Observaciones</label>
                    <textarea id="emitir-observaciones" maxlength="500" style="min-height:60px;"></textarea>
                </div>
            </div>

            <div style="padding:16px;background:var(--surface-2);border-radius:var(--radius-md);margin-top:16px;">
                <div style="display:flex;justify-content:space-between;padding:6px 0;">
                    <span>Subtotal:</span>
                    <strong id="emitir-subtotal">${App.formatMoney(v.subtotal)}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;">
                    <span>Descuento:</span>
                    <strong id="emitir-descuento-val">$0.00</strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;">
                    <span>Impuesto:</span>
                    <strong id="emitir-impuesto-val">$0.00</strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:10px 0;border-top:2px solid var(--border);margin-top:6px;font-size:18px;">
                    <span><strong>TOTAL:</strong></span>
                    <strong id="emitir-total" style="color:var(--primary);">${App.formatMoney(v.subtotal)}</strong>
                </div>
            </div>
        `;
    }

    function recalcularEmitir() {
        if (!ventaSeleccionada) return;

        const subtotal = parseFloat(ventaSeleccionada.subtotal) || 0;
        const descuento = parseFloat(document.getElementById('emitir-descuento').value) || 0;
        const impuesto = parseFloat(document.getElementById('emitir-impuesto').value) || 0;
        const total = subtotal - descuento + impuesto;

        document.getElementById('emitir-descuento-val').textContent = App.formatMoney(descuento);
        document.getElementById('emitir-impuesto-val').textContent = App.formatMoney(impuesto);
        document.getElementById('emitir-total').textContent = App.formatMoney(Math.max(0, total));
    }

    function cerrarEmitir() {
        document.getElementById('modal-emitir')?.classList.remove('active');
        ventaSeleccionada = null;
    }

    async function confirmarEmitir() {
        if (!ventaSeleccionada) return;

        const fechaVencimiento = document.getElementById('emitir-vencimiento').value;
        const descuento = parseFloat(document.getElementById('emitir-descuento').value) || 0;
        const impuesto = parseFloat(document.getElementById('emitir-impuesto').value) || 0;
        const observaciones = document.getElementById('emitir-observaciones').value.trim();

        const btn = document.getElementById('btn-confirmar-emitir');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Emitiendo...';

        const res = await Api.post('api/facturas.php?accion=emitir', {
            venta_id: ventaSeleccionada.id,
            fecha_vencimiento: fechaVencimiento,
            descuento,
            impuesto,
            observaciones,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Emitir factura';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Factura emitida', `Folio: ${res.data.folio}`);
        cerrarEmitir();
        cargar();

        // Preguntar si quiere ver la factura
        if (await App.confirmar('¿Quieres ver la factura ahora?', 'Factura emitida')) {
            verDetalle(res.data.id);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // VER DETALLE
    // ═══════════════════════════════════════════════════════════
    async function verDetalle(id) {
        document.getElementById('modal-detalle-factura').classList.add('active');
        document.getElementById('det-contenido').innerHTML =
            '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/facturas.php?accion=obtener&id=${id}`);
        if (!res.success) {
            document.getElementById('det-contenido').innerHTML =
                `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        const f = res.data;
        const badge = badgeEstado(f.estado);

        document.getElementById('det-titulo').textContent = `Factura ${f.folio}`;

        document.getElementById('det-contenido').innerHTML = `
            ${f.estado === 'anulada' ? `
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle-fill"></i>
                    <div>
                        <strong>FACTURA ANULADA</strong>
                        <div class="text-sm">Motivo: ${App.escapeHtml(f.motivo_anulacion || '—')}</div>
                        <div class="text-xs text-muted">Por: ${App.escapeHtml(f.anulada_por_nombre || '—')} · ${App.formatDate(f.fecha_anulacion)}</div>
                    </div>
                </div>
            ` : ''}

            <div class="grid-3 mb-4">
                <div>
                    <div class="text-xs text-muted">Folio</div>
                    <div style="font-weight:700;">${App.escapeHtml(f.folio)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Estado</div>
                    <div><span class="badge badge-${badge.color}">${badge.label}</span></div>
                </div>
                <div>
                    <div class="text-xs text-muted">Venta vinculada</div>
                    <div style="font-weight:600;">${App.escapeHtml(f.venta_folio)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Cliente</div>
                    <div style="font-weight:600;">${App.escapeHtml(f.cliente)}</div>
                    ${f.cliente_nit ? `<div class="text-xs text-muted">${App.escapeHtml(f.cliente_nit)}</div>` : ''}
                </div>
                <div>
                    <div class="text-xs text-muted">Emisión</div>
                    <div style="font-weight:600;">${App.formatDate(f.fecha_emision)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Vencimiento</div>
                    <div style="font-weight:600;">${f.fecha_vencimiento ? App.formatDate(f.fecha_vencimiento).split(' ')[0] : '—'}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Punto de venta</div>
                    <div style="font-weight:600;">${App.escapeHtml(f.pv)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Emitida por</div>
                    <div style="font-weight:600;">${App.escapeHtml(f.emitida_por_nombre || '—')}</div>
                </div>
            </div>

            <h4 style="font-size:15px;margin-bottom:10px;">
                <i class="bi bi-box-seam text-primary"></i> Productos
            </h4>
            <div class="tabla-wrap mb-4">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Unidad</th>
                            <th class="text-right">Cant.</th>
                            <th class="text-right">Precio</th>
                            <th class="text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${f.detalle.map(d => `
                            <tr>
                                <td><strong>${App.escapeHtml(d.producto)}</strong></td>
                                <td class="text-muted text-sm">${App.escapeHtml(d.unidad_medida || '—')}</td>
                                <td class="text-right">${d.cantidad}</td>
                                <td class="text-right">${App.formatMoney(d.precio_unitario)}</td>
                                <td class="text-right"><strong>${App.formatMoney(d.subtotal)}</strong></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>

            <h4 style="font-size:15px;margin-bottom:10px;">
                <i class="bi bi-cash-coin text-success"></i> Pagos (${f.pagos.length})
            </h4>

            ${f.pagos.length === 0 ? `
                <div class="empty-state" style="padding:20px;">
                    <p class="text-muted">Sin pagos registrados</p>
                </div>
            ` : `
                <div class="tabla-wrap mb-4">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Método</th>
                                <th>Referencia</th>
                                <th class="text-right">Monto</th>
                                <th>Registrado por</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${f.pagos.map(p => `
                                <tr>
                                    <td class="text-muted text-xs">${App.formatDate(p.fecha)}</td>
                                    <td><span class="badge badge-info">${App.escapeHtml(p.metodo)}</span></td>
                                    <td class="text-muted text-xs">${App.escapeHtml(p.referencia || '—')}</td>
                                    <td class="text-right"><strong>${App.formatMoney(p.monto)}</strong></td>
                                    <td class="text-muted text-sm">${App.escapeHtml(p.usuario || '—')}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `}

            <div style="padding:16px;background:var(--surface-2);border-radius:var(--radius-md);margin-top:16px;">
                <div style="display:flex;justify-content:space-between;padding:6px 0;">
                    <span>Subtotal:</span>
                    <strong>${App.formatMoney(f.subtotal)}</strong>
                </div>
                ${parseFloat(f.descuento) > 0 ? `
                    <div style="display:flex;justify-content:space-between;padding:6px 0;">
                        <span>Descuento:</span>
                        <strong style="color:var(--warning);">- ${App.formatMoney(f.descuento)}</strong>
                    </div>
                ` : ''}
                ${parseFloat(f.impuesto) > 0 ? `
                    <div style="display:flex;justify-content:space-between;padding:6px 0;">
                        <span>Impuesto:</span>
                        <strong>+ ${App.formatMoney(f.impuesto)}</strong>
                    </div>
                ` : ''}
                <div style="display:flex;justify-content:space-between;padding:10px 0;border-top:2px solid var(--border);margin-top:6px;">
                    <span><strong>TOTAL:</strong></span>
                    <strong style="color:var(--primary);font-size:18px;">${App.formatMoney(f.total)}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;">
                    <span>Pagado:</span>
                    <strong style="color:var(--success);">${App.formatMoney(f.total_pagado)}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid var(--border);">
                    <span><strong>SALDO PENDIENTE:</strong></span>
                    <strong style="color:${f.saldo_pendiente > 0 ? 'var(--danger)' : 'var(--success)'};font-size:16px;">
                        ${App.formatMoney(f.saldo_pendiente)}
                    </strong>
                </div>
            </div>

            ${f.observaciones ? `
                <div class="mt-4">
                    <div class="text-xs text-muted">Observaciones</div>
                    <div>${App.escapeHtml(f.observaciones)}</div>
                </div>
            ` : ''}
        `;

        // Footer con acciones
        let footer = '';

        footer += `
            <button class="btn btn-danger" onclick="Facturas.vistaPrevia(${f.id})">
                <i class="bi bi-eye"></i> Vista previa
            </button>
            <a href="${BASE_URL}api/facturas.php?accion=pdf&modo=download&id=${f.id}"
               class="btn btn-secondary">
                <i class="bi bi-download"></i> Descargar PDF
            </a>
        `;

        if (puedeEmitir && f.estado !== 'anulada' && f.estado !== 'pagada') {
            footer += `
                <button class="btn btn-success" onclick="Facturas.abrirPago(${f.id})">
                    <i class="bi bi-cash-coin"></i> Registrar pago
                </button>
            `;
        }

        if (puedeEmitir && f.estado !== 'anulada' && f.pagos.length === 0) {
            footer += `
                <button class="btn btn-danger" onclick="Facturas.abrirAnular(${f.id})">
                    <i class="bi bi-x-circle"></i> Anular
                </button>
            `;
        }

        footer += `<button class="btn btn-secondary" onclick="Facturas.cerrarDetalle()">Cerrar</button>`;
        document.getElementById('det-footer').innerHTML = footer;
    }

    function cerrarDetalle() {
        document.getElementById('modal-detalle-factura')?.classList.remove('active');
    }

    // ═══════════════════════════════════════════════════════════
    // REGISTRAR PAGO
    // ═══════════════════════════════════════════════════════════
    function abrirPago(facturaId) {
        const f = { id: facturaId };

        // Cargar datos de la factura para el saldo
        Api.get(`api/facturas.php?accion=obtener&id=${facturaId}`).then(res => {
            if (!res.success) {
                Toast.error('Error', res.message);
                return;
            }

            const fact = res.data;

            document.getElementById('pago-factura-id').value = facturaId;
            document.getElementById('pago-info').innerHTML = `
                <div style="display:flex;justify-content:space-between;">
                    <span class="text-xs text-muted">Factura:</span>
                    <strong>${App.escapeHtml(fact.folio)}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span class="text-xs text-muted">Total:</span>
                    <strong>${App.formatMoney(fact.total)}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span class="text-xs text-muted">Saldo pendiente:</span>
                    <strong style="color:var(--danger);">${App.formatMoney(fact.saldo_pendiente)}</strong>
                </div>
            `;

            document.getElementById('pago-monto').value = fact.saldo_pendiente;
            document.getElementById('pago-monto').max = fact.saldo_pendiente;
            document.getElementById('pago-monto-help').textContent = `Máximo: ${App.formatMoney(fact.saldo_pendiente)}`;
            document.getElementById('pago-referencia').value = '';
            document.getElementById('pago-notas').value = '';

            document.getElementById('modal-pago').classList.add('active');
            setTimeout(() => document.getElementById('pago-monto')?.focus(), 100);
        });
    }

    async function confirmarPago() {
        const facturaId = parseInt(document.getElementById('pago-factura-id').value);
        const monto = parseFloat(document.getElementById('pago-monto').value) || 0;
        const metodo = document.getElementById('pago-metodo').value;
        const referencia = document.getElementById('pago-referencia').value.trim();
        const notas = document.getElementById('pago-notas').value.trim();

        if (monto <= 0) {
            Toast.warning('Monto inválido', 'El monto debe ser mayor a 0');
            return;
        }

        const btn = document.getElementById('btn-confirmar-pago');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Registrando...';

        const res = await Api.post('api/facturas.php?accion=registrar_pago', {
            factura_id: facturaId,
            monto,
            metodo,
            referencia,
            notas,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Registrar pago';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Pago registrado', `Saldo pendiente: ${App.formatMoney(res.data.saldo_pendiente)}`);
        document.getElementById('modal-pago').classList.remove('active');
        cerrarDetalle();
        cargar();

        // Reabrir el detalle con datos actualizados
        verDetalle(facturaId);
    }

    // ═══════════════════════════════════════════════════════════
    // ANULAR
    // ═══════════════════════════════════════════════════════════
    function abrirAnular(facturaId) {
        document.getElementById('anular-factura-id').value = facturaId;
        document.getElementById('anular-motivo').value = '';
        document.getElementById('modal-anular').classList.add('active');
        setTimeout(() => document.getElementById('anular-motivo')?.focus(), 100);
    }

    async function confirmarAnular() {
        const facturaId = parseInt(document.getElementById('anular-factura-id').value);
        const motivo = document.getElementById('anular-motivo').value.trim();

        if (motivo.length < 5) {
            Toast.warning('Motivo muy corto', 'Mínimo 5 caracteres');
            return;
        }

        const btn = document.getElementById('btn-confirmar-anular');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Anulando...';

        const res = await Api.post('api/facturas.php?accion=anular', {
            id: facturaId,
            motivo,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-x-lg"></i> Anular factura';

        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Factura anulada', res.message);
        document.getElementById('modal-anular').classList.remove('active');
        cerrarDetalle();
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // FILTROS
    // ═══════════════════════════════════════════════════════════
    function limpiar() {
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-estado').value = '';
        document.getElementById('filtro-desde').value = '';
        document.getElementById('filtro-hasta').value = '';
        document.getElementById('filtro-vencidas').value = '';
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // EVENTOS
    // ═══════════════════════════════════════════════════════════
    document.addEventListener('DOMContentLoaded', () => {
        puedeEmitir = !!document.getElementById('modal-pendientes');
        cargar();

        const debounced = App.debounce(cargar, 400);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-estado')?.addEventListener('change', cargar);
        document.getElementById('filtro-desde')?.addEventListener('change', cargar);
        document.getElementById('filtro-hasta')?.addEventListener('change', cargar);
        document.getElementById('filtro-vencidas')?.addEventListener('change', cargar);
    });

    // ═══════════════════════════════════════════════════════════
    // VISTA PREVIA DEL PDF
    // ═══════════════════════════════════════════════════════════
    function vistaPrevia(facturaId) {
        const modalId = 'modal-pdf-preview';
        document.getElementById(modalId)?.remove();

        const urlPreview = `${BASE_URL}api/facturas.php?accion=pdf&modo=inline&id=${facturaId}`;
        const urlDownload = `${BASE_URL}api/facturas.php?accion=pdf&modo=download&id=${facturaId}`;

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal modal-xl" style="max-width:1100px;height:90vh;display:flex;flex-direction:column;">
                    <div class="modal-header">
                        <div class="modal-title">
                            <i class="bi bi-file-earmark-pdf text-danger"></i>
                            Vista previa de la factura
                        </div>
                        <button class="modal-close" onclick="Facturas.cerrarVistaPrevia()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body" style="padding:0;flex:1;overflow:hidden;background:var(--surface-3);">
                        <iframe
                            id="pdf-preview-iframe"
                            src="${urlPreview}"
                            style="width:100%;height:100%;border:0;background:#fff;"
                            title="Vista previa de la factura">
                        </iframe>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="Facturas.cerrarVistaPrevia()">
                            <i class="bi bi-x-lg"></i> Cerrar
                        </button>
                        <a href="${urlPreview}" target="_blank" class="btn btn-secondary">
                            <i class="bi bi-box-arrow-up-right"></i> Abrir en nueva pestaña
                        </a>
                        <a href="${urlDownload}" class="btn btn-danger" download>
                            <i class="bi bi-download"></i> Descargar PDF
                        </a>
                        <button class="btn btn-primary" onclick="Facturas.imprimirPdf()">
                            <i class="bi bi-printer"></i> Imprimir
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    function cerrarVistaPrevia() {
        document.getElementById('modal-pdf-preview')?.remove();
    }

    function imprimirPdf() {
        const iframe = document.getElementById('pdf-preview-iframe');
        if (!iframe) return;

        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch (e) {
            Toast.warning('Impresión bloqueada', 'Usa el botón de descarga y abre el PDF manualmente');
        }
    }

    return {
        cargar,
        limpiar,
        atajo,
        abrirPendientes,
        cerrarPendientes,
        buscarPendientes,
        abrirEmitir,
        recalcularEmitir,
        cerrarEmitir,
        confirmarEmitir,
        verDetalle,
        cerrarDetalle,
        abrirPago,
        confirmarPago,
        abrirAnular,
        confirmarAnular,
        vistaPrevia,
        cerrarVistaPrevia,
        imprimirPdf,
    };
})();

window.Facturas = Facturas;