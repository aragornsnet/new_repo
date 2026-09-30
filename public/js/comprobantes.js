/**
 * IPV - Gestión de Comprobantes de Venta
 */

const Comprobantes = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let lista = [];
    let puedeAnular = false;

    // ═══════════════════════════════════════════════════════════
    // LISTAR
    // ═══════════════════════════════════════════════════════════
    async function cargar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const estado = document.getElementById('filtro-estado')?.value || '';
        const desde = document.getElementById('filtro-desde')?.value || '';
        const hasta = document.getElementById('filtro-hasta')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (estado) params.append('estado', estado);
        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);

        const cont = document.getElementById('tabla-comprobantes');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get('api/comprobantes.php?accion=listar&' + params);
        if (!res.success) {
            Toast.error('Error', res.message);
            cont.innerHTML = `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        lista = res.data;

        const total1 = document.getElementById('total-comprobantes');
        const total2 = document.getElementById('total-comprobantes-2');
        if (total1) total1.textContent = lista.length;
        if (total2) total2.textContent = lista.length;

        actualizarResumen();
        render();
    }

    // ═══════════════════════════════════════════════════════════
    // RESUMEN
    // ═══════════════════════════════════════════════════════════
    async function actualizarResumen() {
        const resEmitidos = document.getElementById('res-emitidos');
        const resAnulados = document.getElementById('res-anulados');
        if (!resEmitidos || !resAnulados) return;

        const res = await Api.get('api/comprobantes.php?accion=listar');
        if (!res.success) return;

        let emitidos = 0;
        let anulados = 0;

        res.data.forEach(c => {
            if (c.estado === 'emitido') emitidos++;
            else if (c.estado === 'anulado') anulados++;
        });

        resEmitidos.textContent = emitidos;
        resAnulados.textContent = anulados;
    }

    // ═══════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════
    function render() {
        const cont = document.getElementById('tabla-comprobantes');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-receipt-cutoff"></i>
                <h3>Sin comprobantes</h3>
                <p>No hay comprobantes que coincidan con los filtros.</p>
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Venta</th>
                            <th>Comprador</th>
                            <th>Fecha</th>
                            <th class="text-right">Total</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(c => {
                            const badge = c.estado === 'anulado'
                                ? '<span class="badge badge-danger"><i class="bi bi-x-circle-fill"></i> Anulado</span>'
                                : '<span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> Emitido</span>';

                            const comprador = c.nombre_comprador || 'Consumidor final';
                            const tel = c.telefono_comprador ? ` · ${c.telefono_comprador}` : '';

                            return `
                                <tr style="${c.estado === 'anulado' ? 'opacity:.6;' : ''}">
                                    <td>
                                        <strong>${App.escapeHtml(c.folio)}</strong>
                                    </td>
                                    <td class="text-muted text-sm">
                                        ${App.escapeHtml(c.venta_folio)}
                                    </td>
                                    <td>
                                        <div style="font-weight:600;">${App.escapeHtml(comprador)}</div>
                                        ${tel ? `<div class="text-xs text-muted">${App.escapeHtml(c.telefono_comprador)}</div>` : ''}
                                    </td>
                                    <td class="text-muted text-xs">${App.formatDate(c.created_at)}</td>
                                    <td class="text-right"><strong>${App.formatMoney(c.total)}</strong></td>
                                    <td>${badge}</td>
                                    <td class="text-right">
                                        <div class="d-flex gap-1" style="justify-content:flex-end;">
                                            <button class="btn btn-ghost btn-icon"
                                                    onclick="Comprobantes.verDetalle(${c.id})"
                                                    title="Ver detalle">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-ghost btn-icon"
                                                    onclick="Comprobantes.vistaPrevia(${c.id})"
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

    // ═══════════════════════════════════════════════════════════
    // ATAJOS
    // ═══════════════════════════════════════════════════════════
    function atajo(estado) {
        const sel = document.getElementById('filtro-estado');
        if (sel) sel.value = estado;

        const q = document.getElementById('filtro-q');
        if (q) q.value = '';

        const desde = document.getElementById('filtro-desde');
        if (desde) desde.value = '';

        const hasta = document.getElementById('filtro-hasta');
        if (hasta) hasta.value = '';

        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // VER DETALLE
    // ═══════════════════════════════════════════════════════════
    async function verDetalle(id) {
        document.getElementById('modal-detalle-comprobante').classList.add('active');
        document.getElementById('det-contenido').innerHTML =
            '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/comprobantes.php?accion=obtener&id=${id}`);
        if (!res.success) {
            document.getElementById('det-contenido').innerHTML =
                `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        const c = res.data;
        const badge = c.estado === 'anulado'
            ? '<span class="badge badge-danger">Anulado</span>'
            : '<span class="badge badge-success">Emitido</span>';

        document.getElementById('det-titulo').textContent = `Comprobante ${c.folio}`;

        document.getElementById('det-contenido').innerHTML = `
            ${c.estado === 'anulado' ? `
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle-fill"></i>
                    <div>
                        <strong>COMPROBANTE ANULADO</strong>
                        <div class="text-sm">Motivo: ${App.escapeHtml(c.motivo_anulacion || '—')}</div>
                        <div class="text-xs text-muted">Por: ${App.escapeHtml(c.anulado_por_nombre || '—')} · ${App.formatDate(c.fecha_anulacion)}</div>
                    </div>
                </div>
            ` : ''}

            <div class="grid-3 mb-4">
                <div>
                    <div class="text-xs text-muted">Folio</div>
                    <div style="font-weight:700;">${App.escapeHtml(c.folio)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Estado</div>
                    <div>${badge}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Venta vinculada</div>
                    <div style="font-weight:600;">${App.escapeHtml(c.venta_folio)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Comprador</div>
                    <div style="font-weight:600;">${App.escapeHtml(c.nombre_comprador || 'Consumidor final')}</div>
                    ${c.documento_comprador ? `<div class="text-xs text-muted">Doc: ${App.escapeHtml(c.documento_comprador)}</div>` : ''}
                </div>
                <div>
                    <div class="text-xs text-muted">Teléfono</div>
                    <div style="font-weight:600;">${App.escapeHtml(c.telefono_comprador || '—')}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Dirección</div>
                    <div style="font-weight:600;">${App.escapeHtml(c.direccion_comprador || '—')}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Emisión</div>
                    <div style="font-weight:600;">${App.formatDate(c.created_at)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Punto de venta</div>
                    <div style="font-weight:600;">${App.escapeHtml(c.pv)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Emitido por</div>
                    <div style="font-weight:600;">${App.escapeHtml(c.emitido_por_nombre || '—')}</div>
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
                        ${c.detalle.map(d => `
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

            <div style="padding:16px;background:var(--surface-2);border-radius:var(--radius-md);">
                <div style="display:flex;justify-content:space-between;padding:10px 0;border-top:2px solid var(--border);">
                    <span><strong>TOTAL:</strong></span>
                    <strong style="color:var(--primary);font-size:18px;">${App.formatMoney(c.total)}</strong>
                </div>
            </div>

            ${c.observaciones ? `
                <div class="mt-4">
                    <div class="text-xs text-muted">Observaciones</div>
                    <div>${App.escapeHtml(c.observaciones)}</div>
                </div>
            ` : ''}
        `;

        // Footer con acciones
        let footer = `
            <button class="btn btn-danger" onclick="Comprobantes.vistaPrevia(${c.id})">
                <i class="bi bi-eye"></i> Vista previa
            </button>
            <a href="${BASE_URL}api/comprobantes.php?accion=pdf&modo=download&id=${c.id}"
               class="btn btn-secondary">
                <i class="bi bi-download"></i> Descargar PDF
            </a>
        `;

        if (puedeAnular && c.estado === 'emitido') {
            footer += `
                <button class="btn btn-warning" onclick="Comprobantes.abrirAnular(${c.id})">
                    <i class="bi bi-x-circle"></i> Anular
                </button>
            `;
        }

        footer += `<button class="btn btn-secondary" onclick="Comprobantes.cerrarDetalle()">Cerrar</button>`;
        document.getElementById('det-footer').innerHTML = footer;
    }

    function cerrarDetalle() {
        document.getElementById('modal-detalle-comprobante')?.classList.remove('active');
    }

    // ═══════════════════════════════════════════════════════════
    // VISTA PREVIA PDF
    // ═══════════════════════════════════════════════════════════
    function vistaPrevia(id) {
        const modalId = 'modal-pdf-preview';
        document.getElementById(modalId)?.remove();

        const urlPreview = `${BASE_URL}api/comprobantes.php?accion=pdf&modo=inline&id=${id}`;
        const urlDownload = `${BASE_URL}api/comprobantes.php?accion=pdf&modo=download&id=${id}`;

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal modal-xl" style="max-width:1100px;height:90vh;display:flex;flex-direction:column;">
                    <div class="modal-header">
                        <div class="modal-title">
                            <i class="bi bi-file-earmark-pdf text-danger"></i>
                            Vista previa del comprobante
                        </div>
                        <button class="modal-close" onclick="Comprobantes.cerrarVistaPrevia()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body" style="padding:0;flex:1;overflow:hidden;background:var(--surface-3);">
                        <iframe
                            id="pdf-preview-iframe"
                            src="${urlPreview}"
                            style="width:100%;height:100%;border:0;background:#fff;"
                            title="Vista previa del comprobante">
                        </iframe>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="Comprobantes.cerrarVistaPrevia()">
                            <i class="bi bi-x-lg"></i> Cerrar
                        </button>
                        <a href="${urlPreview}" target="_blank" class="btn btn-secondary">
                            <i class="bi bi-box-arrow-up-right"></i> Abrir en nueva pestaña
                        </a>
                        <a href="${urlDownload}" class="btn btn-danger" download>
                            <i class="bi bi-download"></i> Descargar PDF
                        </a>
                        <button class="btn btn-primary" onclick="Comprobantes.imprimirPdf()">
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

    // ═══════════════════════════════════════════════════════════
    // ANULAR
    // ═══════════════════════════════════════════════════════════
    function abrirAnular(id) {
        document.getElementById('anular-id').value = id;
        document.getElementById('anular-motivo').value = '';
        document.getElementById('modal-anular').classList.add('active');
        setTimeout(() => document.getElementById('anular-motivo')?.focus(), 100);
    }

    async function confirmarAnular() {
        const id = parseInt(document.getElementById('anular-id').value);
        const motivo = document.getElementById('anular-motivo').value.trim();

        if (motivo.length < 5) {
            Toast.warning('Motivo muy corto', 'Mínimo 5 caracteres');
            return;
        }

        const btn = document.getElementById('btn-confirmar-anular');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Anulando...';

        const res = await Api.post('api/comprobantes.php?accion=anular', { id, motivo });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-x-lg"></i> Anular';

        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Comprobante anulado', res.message);
        document.getElementById('modal-anular').classList.remove('active');
        cerrarDetalle();
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // FILTROS
    // ═══════════════════════════════════════════════════════════
    function limpiar() {
        const q = document.getElementById('filtro-q');
        if (q) q.value = '';

        const estado = document.getElementById('filtro-estado');
        if (estado) estado.value = '';

        const desde = document.getElementById('filtro-desde');
        if (desde) desde.value = '';

        const hasta = document.getElementById('filtro-hasta');
        if (hasta) hasta.value = '';

        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // EVENTOS
    // ═══════════════════════════════════════════════════════════
    document.addEventListener('DOMContentLoaded', () => {
        // puedeAnular según rol
        const rol = window.CURRENT_USER?.rol || '';
        puedeAnular = ['Administrador', 'Supervisor', 'Vendedor'].includes(rol);

        if (document.getElementById('tabla-comprobantes')) {
            cargar();
        }

        const debounced = App.debounce(cargar, 400);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-estado')?.addEventListener('change', cargar);
        document.getElementById('filtro-desde')?.addEventListener('change', cargar);
        document.getElementById('filtro-hasta')?.addEventListener('change', cargar);
    });

    return {
        cargar,
        limpiar,
        atajo,
        verDetalle,
        cerrarDetalle,
        vistaPrevia,
        cerrarVistaPrevia,
        imprimirPdf,
        abrirAnular,
        confirmarAnular,
    };
})();

window.Comprobantes = Comprobantes;