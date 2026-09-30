/**
 * IPV - Mis Ventas (vendedor)
 */

const MisVentas = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let lista = [];

    // ═══════════════════════════════════════════════════════════
    // CARGAR
    // ═══════════════════════════════════════════════════════════
    async function cargar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const metodo = document.getElementById('filtro-metodo')?.value || '';
        const estado = document.getElementById('filtro-estado')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (metodo) params.append('metodo', metodo);
        if (estado) params.append('estado', estado);

        const cont = document.getElementById('tabla-ventas');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/mis_ventas.php?accion=listar&${params}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        lista = res.data.datos;
        const t = res.data.totales;

        document.getElementById('tot-num').textContent = t.num_ventas;
        document.getElementById('tot-total').textContent = App.formatMoney(t.total);
        document.getElementById('tot-efectivo').textContent = App.formatMoney(t.total_efectivo);
        document.getElementById('tot-transf').textContent = App.formatMoney(t.total_transferencia);

        document.getElementById('total-ventas').textContent = lista.length;
        render();
    }

    function render() {
        const cont = document.getElementById('tabla-ventas');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>Sin ventas</h3>
                <p>No has hecho ventas en este turno todavía.</p>
                <a href="${BASE_URL}views/vendedor/pos.php" class="btn btn-primary btn-sm mt-3">
                    <i class="bi bi-cart-plus"></i> Empezar a vender
                </a>
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Hora</th>
                            <th class="text-right">Productos</th>
                            <th class="text-right">Efectivo</th>
                            <th class="text-right">Transferencia</th>
                            <th class="text-right">Total</th>
                            <th>Estado</th>
                            <th class="text-right">Ver</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(v => {
                            const cancelada = v.estado === 'cancelada';
                            const esDivisa = v.moneda && v.moneda !== 'CUP';
                            return `
                                <tr style="${cancelada ? 'opacity:.6;' : ''}">
                                    <td>
                                        <strong>${App.escapeHtml(v.folio)}</strong>
                                        ${esDivisa ? `<div class="text-xs text-info">${v.moneda} ${App.formatNumber(v.total_divisa, 2)}</div>` : ''}
                                    </td>
                                    <td class="text-muted text-xs">${App.formatDate(v.fecha)}</td>
                                    <td class="text-right">${v.num_productos}</td>
                                    <td class="text-right text-success">${App.formatMoney(v.pago_efectivo)}</td>
                                    <td class="text-right text-info">${App.formatMoney(v.pago_transferencia)}</td>
                                    <td class="text-right"><strong>${App.formatMoney(v.total)}</strong></td>
                                    <td>
                                        ${cancelada
                                            ? '<span class="badge badge-danger"><i class="bi bi-x-circle-fill"></i> Cancelada</span>'
                                            : '<span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> OK</span>'}
                                    </td>
                                    <td class="text-right">
                                        <div class="d-flex gap-1" style="justify-content:flex-end;">
                                            <button class="btn btn-ghost btn-icon" onclick="MisVentas.verDetalle(${v.id})" title="Ver detalle">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-ghost btn-icon" onclick="MisVentas.reimprimirTicket(${v.id})" title="Reimprimir ticket">
                                                <i class="bi bi-printer"></i>
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

    // ═══════════════════════════════════════════════════════════
    // VER DETALLE
    // ═══════════════════════════════════════════════════════════
    async function verDetalle(id) {
        const res = await Api.get(`api/mis_ventas.php?accion=detalle&id=${id}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        const v = res.data;
        const modalId = 'modal-venta-detalle';

        const esDivisa = v.moneda && v.moneda !== 'CUP';
        const symbolDivisa = esDivisa ? (v.moneda === 'USD' ? '$' : (v.moneda === 'EUR' ? '€' : v.moneda)) : '';

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal modal-lg">
                    <div class="modal-header">
                        <div class="modal-title"><i class="bi bi-receipt"></i> Venta ${App.escapeHtml(v.folio)}</div>
                        <button class="modal-close" onclick="document.getElementById('${modalId}').remove()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body">

                        ${v.estado === 'cancelada' ? `
                            <div class="alert alert-danger">
                                <i class="bi bi-x-circle-fill"></i>
                                <div>
                                    <strong>VENTA CANCELADA</strong>
                                    <div class="text-sm">Motivo: ${App.escapeHtml(v.motivo_cancelacion || '—')}</div>
                                </div>
                            </div>
                        ` : ''}

                        <div class="grid-3 mb-4">
                            <div><div class="text-xs text-muted">Fecha</div><div style="font-weight:600;">${App.formatDate(v.fecha)}</div></div>
                            <div><div class="text-xs text-muted">Productos</div><div style="font-weight:600;">${v.detalle.length}</div></div>
                            <div><div class="text-xs text-muted">Moneda</div><div style="font-weight:600;">${App.escapeHtml(v.moneda || 'CUP')}</div></div>
                        </div>

                        ${esDivisa ? `
                            <div class="card" style="background:var(--info-light);border:1px solid var(--info);margin-bottom:16px;">
                                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                                    <span class="text-sm text-muted">Total en CUP:</span>
                                    <strong>${App.formatMoney(v.total)}</strong>
                                </div>
                                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                                    <span class="text-sm text-muted">Tasa aplicada:</span>
                                    <strong>${App.formatNumber(v.tasa_aplicada, 2)} CUP/${v.moneda}</strong>
                                </div>
                                <div style="display:flex;justify-content:space-between;padding-top:8px;border-top:1px solid var(--info);">
                                    <span style="font-weight:700;">TOTAL EN DIVISA:</span>
                                    <strong style="font-size:18px;color:var(--primary);">${symbolDivisa}${App.formatNumber(v.total_divisa, 2)}</strong>
                                </div>
                            </div>
                        ` : ''}

                        <h4 style="font-size:14px;margin-bottom:10px;"><i class="bi bi-box-seam text-primary"></i> Productos</h4>
                        <div class="tabla-wrap mb-4">
                            <table class="tabla">
                                <thead><tr><th>Producto</th><th>Unidad</th><th class="text-right">Cant.</th><th class="text-right">Precio</th><th class="text-right">Subtotal</th></tr></thead>
                                <tbody>
                                    ${v.detalle.map(d => {
                                        const unidad = d.unidad_medida || 'Unidad';
                                        return `
                                            <tr>
                                                <td><strong>${App.escapeHtml(d.producto)}</strong></td>
                                                <td class="text-muted text-sm">${App.escapeHtml(unidad)}</td>
                                                <td class="text-right">${d.cantidad} ${App.escapeHtml(unidad)}</td>
                                                <td class="text-right">${App.formatMoney(d.precio_unitario)}</td>
                                                <td class="text-right"><strong>${App.formatMoney(d.subtotal)}</strong></td>
                                            </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>

                        <h4 style="font-size:14px;margin-bottom:10px;"><i class="bi bi-credit-card text-primary"></i> Pagos</h4>
                        <div class="tabla-wrap mb-4">
                            <table class="tabla">
                                <thead><tr><th>Método</th><th>Referencia</th><th class="text-right">Monto</th><th>Estado</th></tr></thead>
                                <tbody>
                                    ${v.pagos.map(p => `
                                        <tr>
                                            <td>
                                                ${p.metodo === 'efectivo' 
                                                    ? '<span class="badge badge-success">💵 Efectivo</span>' 
                                                    : '<span class="badge badge-info">🏦 ' + App.escapeHtml(p.metodo_detalle || 'Transferencia') + '</span>'}
                                            </td>
                                            <td class="text-muted text-xs">${App.escapeHtml(p.referencia || '—')}</td>
                                            <td class="text-right">
                                                <strong>${App.formatMoney(p.monto)}</strong>
                                                ${p.moneda && p.moneda !== 'CUP' ? `<div class="text-xs text-muted">${p.moneda} ${App.formatNumber(p.monto_divisa, 2)}</div>` : ''}
                                            </td>
                                            <td>
                                                ${p.metodo === 'transferencia' 
                                                    ? (p.verificado == 1 
                                                        ? '<span class="badge badge-success">Verificada</span>' 
                                                        : (p.motivo_rechazo 
                                                            ? '<span class="badge badge-danger">Rechazada</span>' 
                                                            : '<span class="badge badge-warning">Pendiente</span>'))
                                                    : '—'}
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>

                        <div class="card" style="background:var(--surface-2);">
                            <div style="display:flex;justify-content:space-between;padding:6px 0;">
                                <span>Subtotal:</span><strong>${App.formatMoney(v.subtotal)}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:2px solid var(--border);margin-top:6px;padding-top:10px;font-size:18px;">
                                <span><strong>TOTAL:</strong></span><strong style="color:var(--primary);">${App.formatMoney(v.total)}</strong>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.getElementById('${modalId}').remove()">Cerrar</button>
                        <button class="btn btn-primary"
                                onclick="document.getElementById('${modalId}').remove(); MisVentas.reimprimirTicket(${v.id});">
                            <i class="bi bi-printer"></i> Reimprimir ticket
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    // ═══════════════════════════════════════════════════════════
    // EMITIR COMPROBANTE DESPUÉS
    // ═══════════════════════════════════════════════════════════
    async function abrirPendientesComprobante() {
        const modalId = 'modal-pendientes-comprobante';
        document.getElementById(modalId)?.remove();

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal modal-lg">
                    <div class="modal-header">
                        <div class="modal-title">
                            <i class="bi bi-receipt-cutoff"></i> Ventas sin comprobante
                        </div>
                        <button class="modal-close" onclick="document.getElementById('${modalId}').remove()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill"></i>
                            <div>Emite un comprobante para una venta de tu turno que aún no lo tenga.</div>
                        </div>
                        <div class="field mb-3">
                            <div class="search-input">
                                <i class="bi bi-search"></i>
                                <input type="text" id="pend-comp-buscar" placeholder="Buscar por folio..."
                                       oninput="MisVentas.buscarPendientesComprobante()">
                            </div>
                        </div>
                        <div id="lista-pendientes-comp">
                            <div class="empty-state"><div class="spinner"></div></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.getElementById('${modalId}').remove()">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        await cargarPendientesComprobante();
    }

    async function cargarPendientesComprobante() {
        const q = document.getElementById('pend-comp-buscar')?.value.trim() || '';
        const cont = document.getElementById('lista-pendientes-comp');
        if (!cont) return;

        cont.innerHTML = '<div class="empty-state"><div class="spinner"></div></div>';

        const params = new URLSearchParams();
        if (q) params.append('q', q);

        const res = await Api.get('api/comprobantes.php?accion=ventas_sin_comprobante&' + params);
        if (!res.success) {
            cont.innerHTML = `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        const ventas = res.data;

        if (!ventas.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-check-circle" style="font-size:48px;color:var(--success);"></i>
                <h3>Todas tus ventas tienen comprobante</h3>
                <p>No hay ventas pendientes de comprobante.</p>
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap" style="max-height:400px;overflow-y:auto;">
                <table class="tabla">
                    <thead style="position:sticky;top:0;background:var(--surface-2);z-index:1;">
                        <tr>
                            <th>Folio</th>
                            <th>Fecha</th>
                            <th>PV</th>
                            <th class="text-right">Productos</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${ventas.map(v => `
                            <tr>
                                <td><strong>${App.escapeHtml(v.folio)}</strong></td>
                                <td class="text-muted text-xs">${App.formatDate(v.fecha)}</td>
                                <td class="text-muted">${App.escapeHtml(v.pv)}</td>
                                <td class="text-right">${v.num_productos}</td>
                                <td class="text-right"><strong>${App.formatMoney(v.total)}</strong></td>
                                <td class="text-right">
                                    <button class="btn btn-success btn-sm"
                                            onclick="MisVentas.abrirEmitirComprobante(${v.id})">
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

    const buscarPendientesComprobanteDebounced = App.debounce(cargarPendientesComprobante, 400);
    function buscarPendientesComprobante() {
        buscarPendientesComprobanteDebounced();
    }

    function abrirEmitirComprobante(ventaId) {
        const modalId = 'modal-emitir-comprobante';
        document.getElementById(modalId)?.remove();

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal">
                    <div class="modal-header">
                        <div class="modal-title">
                            <i class="bi bi-receipt-cutoff text-success"></i> Emitir comprobante
                        </div>
                        <button class="modal-close" onclick="document.getElementById('${modalId}').remove()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="ec-venta-id" value="${ventaId}">

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill"></i>
                            <div>Los datos del comprador son opcionales. Si no los das, se emite como "Consumidor final".</div>
                        </div>

                        <div class="form-grid">
                            <div class="field span-full">
                                <label for="ec-nombre">Nombre del comprador</label>
                                <input type="text" id="ec-nombre" maxlength="150" placeholder="Opcional">
                            </div>
                            <div class="field">
                                <label for="ec-documento">Documento</label>
                                <input type="text" id="ec-documento" maxlength="50" placeholder="Opcional">
                            </div>
                            <div class="field">
                                <label for="ec-telefono">Teléfono</label>
                                <input type="text" id="ec-telefono" maxlength="50" placeholder="Opcional">
                            </div>
                            <div class="field span-full">
                                <label for="ec-direccion">Dirección</label>
                                <input type="text" id="ec-direccion" maxlength="255" placeholder="Opcional">
                            </div>
                            <div class="field span-full">
                                <label for="ec-observaciones">Observaciones</label>
                                <textarea id="ec-observaciones" maxlength="255" style="min-height:60px;" placeholder="Opcional"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.getElementById('${modalId}').remove()">
                            Cancelar
                        </button>
                        <button class="btn btn-success" id="btn-emitir-comprobante" onclick="MisVentas.confirmarEmitirComprobante()">
                            <i class="bi bi-check-lg"></i> Emitir
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    async function confirmarEmitirComprobante() {
        const ventaId = parseInt(document.getElementById('ec-venta-id').value);
        const nombre = document.getElementById('ec-nombre').value.trim();
        const documento = document.getElementById('ec-documento').value.trim();
        const telefono = document.getElementById('ec-telefono').value.trim();
        const direccion = document.getElementById('ec-direccion').value.trim();
        const observaciones = document.getElementById('ec-observaciones').value.trim();

        const btn = document.getElementById('btn-emitir-comprobante');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Emitiendo...';

        const res = await Api.post('api/comprobantes.php?accion=emitir', {
            venta_id: ventaId,
            nombre_comprador: nombre,
            documento_comprador: documento,
            telefono_comprador: telefono,
            direccion_comprador: direccion,
            observaciones,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Emitir';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Comprobante emitido', `Folio: ${res.data.folio}`);
        document.getElementById('modal-emitir-comprobante')?.remove();
        document.getElementById('modal-pendientes-comprobante')?.remove();

        // Abrir vista previa del comprobante recién emitido
        abrirVistaPreviaComprobante(res.data.id);
    }

    // ═══════════════════════════════════════════════════════════
    // EXPORTAR
    // ═══════════════════════════════════════════════════════════
    function exportar() {
        Toast.info('Exportando...', 'Descargando CSV');
        window.location.href = BASE_URL + 'api/mis_ventas.php?accion=exportar';
    }

    // ═══════════════════════════════════════════════════════════
    // FILTROS
    // ═══════════════════════════════════════════════════════════
    function limpiar() {
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-metodo').value = '';
        document.getElementById('filtro-estado').value = '';
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // EVENTOS
    // ═══════════════════════════════════════════════════════════
    document.addEventListener('DOMContentLoaded', () => {
        cargar();

        const debounced = App.debounce(cargar, 400);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-metodo')?.addEventListener('change', cargar);
        document.getElementById('filtro-estado')?.addEventListener('change', cargar);
    });

    // ═══════════════════════════════════════════════════════════
    // VISTA PREVIA DEL COMPROBANTE
    // ═══════════════════════════════════════════════════════════
    function abrirVistaPreviaComprobante(comprobanteId) {
        const modalId = 'modal-pdf-comprobante';
        document.getElementById(modalId)?.remove();

        const urlPreview = `${BASE_URL}api/comprobantes.php?accion=pdf&modo=inline&id=${comprobanteId}`;
        const urlDownload = `${BASE_URL}api/comprobantes.php?accion=pdf&modo=download&id=${comprobanteId}`;

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal modal-xl" style="max-width:1100px;height:90vh;display:flex;flex-direction:column;">
                    <div class="modal-header">
                        <div class="modal-title">
                            <i class="bi bi-file-earmark-pdf text-danger"></i>
                            Vista previa del comprobante
                        </div>
                        <button class="modal-close" onclick="MisVentas.cerrarVistaPreviaComprobante()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body" style="padding:0;flex:1;overflow:hidden;background:var(--surface-3);">
                        <iframe
                            id="pdf-preview-comprobante-iframe"
                            src="${urlPreview}"
                            style="width:100%;height:100%;border:0;background:#fff;"
                            title="Vista previa del comprobante">
                        </iframe>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="MisVentas.cerrarVistaPreviaComprobante()">
                            <i class="bi bi-x-lg"></i> Cerrar
                        </button>
                        <a href="${urlPreview}" target="_blank" class="btn btn-secondary">
                            <i class="bi bi-box-arrow-up-right"></i> Abrir en nueva pestaña
                        </a>
                        <a href="${urlDownload}" class="btn btn-danger" download>
                            <i class="bi bi-download"></i> Descargar PDF
                        </a>
                        <button class="btn btn-primary" onclick="MisVentas.imprimirComprobante()">
                            <i class="bi bi-printer"></i> Imprimir
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    function cerrarVistaPreviaComprobante() {
        document.getElementById('modal-pdf-comprobante')?.remove();
    }

    function imprimirComprobante() {
        const iframe = document.getElementById('pdf-preview-comprobante-iframe');
        if (!iframe) return;

        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch (e) {
            Toast.warning('Impresión bloqueada', 'Usa el botón de descarga y abre el PDF manualmente');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // REIMPRIMIR TICKET
    // ═══════════════════════════════════════════════════════════
    function reimprimirTicket(ventaId) {
        const modalId = 'modal-pdf-ticket';
        document.getElementById(modalId)?.remove();

        const urlPreview = `${BASE_URL}api/pos_ticket.php?accion=pdf&modo=inline&venta_id=${ventaId}`;
        const urlDownload = `${BASE_URL}api/pos_ticket.php?accion=pdf&modo=download&venta_id=${ventaId}`;

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}" style="z-index:1100;">
                <div class="modal modal-xl" style="max-width:900px;height:90vh;display:flex;flex-direction:column;">
                    <div class="modal-header">
                        <div class="modal-title">
                            <i class="bi bi-receipt text-primary"></i>
                            Ticket de venta
                        </div>
                        <button class="modal-close" onclick="MisVentas.cerrarTicketPreview()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body" style="padding:0;flex:1;overflow:hidden;background:var(--surface-3);">
                        <iframe id="pdf-ticket-iframe" src="${urlPreview}"
                                style="width:100%;height:100%;border:0;background:#fff;"
                                title="Vista previa del ticket"></iframe>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="MisVentas.cerrarTicketPreview()">
                            <i class="bi bi-x-lg"></i> Cerrar
                        </button>
                        <a href="${urlDownload}" class="btn btn-danger" download>
                            <i class="bi bi-download"></i> Descargar PDF
                        </a>
                        <button class="btn btn-primary" onclick="MisVentas.imprimirIframeTicket()">
                            <i class="bi bi-printer"></i> Imprimir
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    function cerrarTicketPreview() {
        document.getElementById('modal-pdf-ticket')?.remove();
    }

    function imprimirIframeTicket() {
        const iframe = document.getElementById('pdf-ticket-iframe');
        if (!iframe) return;
        try {
            iframe.contentWindow

    return {
        cargar,
        verDetalle,
        exportar,
        limpiar,
        abrirPendientesComprobante,
        buscarPendientesComprobante,
        abrirEmitirComprobante,
        confirmarEmitirComprobante,
        abrirVistaPreviaComprobante,
        cerrarVistaPreviaComprobante,
        imprimirComprobante,
    };
})();

window.MisVentas = MisVentas;