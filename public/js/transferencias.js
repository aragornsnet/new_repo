/**
 * IPV - Transferencias (Supervisor)
 */

const Transferencias = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let lista = [];
    let seleccionadas = new Set();

    // ============================================================
    // CATÁLOGOS
    // ============================================================
    async function cargarCatalogos() {
        const res = await Api.get('api/transferencias.php?accion=catalogos');
        if (!res.success) return;

        const { puntos_venta, vendedores, metodos } = res.data;

        document.getElementById('filtro-pv').innerHTML = '<option value="">Todos los PV</option>' +
            puntos_venta.map(p => `<option value="${p.id}">${App.escapeHtml(p.nombre)}</option>`).join('');

        document.getElementById('filtro-vendedor').innerHTML = '<option value="">Todos</option>' +
            vendedores.map(v => `<option value="${v.id}">${App.escapeHtml(v.nombre)}</option>`).join('');

        document.getElementById('filtro-metodo').innerHTML = '<option value="">Todos</option>' +
            metodos.map(m => `<option value="${m}">${App.escapeHtml(m)}</option>`).join('');
    }

    // ============================================================
    // CARGAR
    // ============================================================
    async function cargar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const estado = document.getElementById('filtro-estado')?.value || '';
        const pvId = document.getElementById('filtro-pv')?.value || '';
        const vendedorId = document.getElementById('filtro-vendedor')?.value || '';
        const metodo = document.getElementById('filtro-metodo')?.value || '';
        const desde = document.getElementById('filtro-desde')?.value || '';
        const hasta = document.getElementById('filtro-hasta')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (estado) params.append('estado', estado);
        if (pvId) params.append('pv_id', pvId);
        if (vendedorId) params.append('vendedor_id', vendedorId);
        if (metodo) params.append('metodo', metodo);
        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);

        const cont = document.getElementById('tabla-transf');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/transferencias.php?accion=listar&${params}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        lista = res.data.datos;

        // Actualizar resumen
        const r = res.data.resumen;
        document.getElementById('res-pendientes').textContent = r.pendientes;
        document.getElementById('res-pend-monto').textContent = App.formatMoney(r.monto_pendiente);
        document.getElementById('res-verificadas').textContent = r.verificadas;
        document.getElementById('res-verif-monto').textContent = App.formatMoney(r.monto_verificado);
        document.getElementById('res-rechazadas').textContent = r.rechazadas;
        document.getElementById('res-rech-monto').textContent = App.formatMoney(r.monto_rechazado);
        document.getElementById('res-total').textContent = App.formatMoney(r.monto_pendiente + r.monto_verificado + r.monto_rechazado);

        document.getElementById('total-transf').textContent = lista.length;
        render();
    }

    // ============================================================
    // RENDER
    // ============================================================
    function render() {
        const cont = document.getElementById('tabla-transf');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>Sin transferencias</h3>
                <p>No hay transferencias que coincidan con los filtros.</p>
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Fecha</th>
                            <th>Vendedor</th>
                            <th>PV</th>
                            <th>Método</th>
                            <th>Referencia</th>
                            <th class="text-right">Monto</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(t => {
                            const rechazada = !!t.motivo_rechazo;
                            const verificada = t.verificado == 1 && !rechazada;
                            const pendiente = !verificada && !rechazada;

                            let badge = '';
                            if (rechazada) badge = '<span class="badge badge-danger"><i class="bi bi-x-circle-fill"></i> Rechazada</span>';
                            else if (verificada) badge = '<span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> Verificada</span>';
                            else badge = '<span class="badge badge-warning"><i class="bi bi-hourglass-split"></i> Pendiente</span>';

                            return `
                                <tr style="${rechazada ? 'opacity:.7;' : ''}">
                                    <td><strong>${App.escapeHtml(t.folio)}</strong></td>
                                    <td class="text-muted text-xs" style="white-space:nowrap;">${App.formatDate(t.fecha)}</td>
                                    <td>${App.escapeHtml(t.vendedor)}</td>
                                    <td class="text-muted">${App.escapeHtml(t.pv)}</td>
                                    <td><span class="badge badge-info">${App.escapeHtml(t.metodo_detalle || 'Transferencia')}</span></td>
                                    <td class="text-muted text-xs">${App.escapeHtml(t.referencia || '—')}</td>
                                    <td class="text-right"><strong>${App.formatMoney(t.monto)}</strong></td>
                                    <td>${badge}</td>
                                    <td class="text-right">
                                        <div class="d-flex gap-1" style="justify-content:flex-end;">
                                            <button class="btn btn-ghost btn-icon" onclick="Transferencias.verDetalle(${t.pago_id})" title="Ver detalle">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            ${pendiente ? `
                                                <button class="btn btn-success btn-sm" onclick="Transferencias.verificar(${t.pago_id})" title="Verificar">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="Transferencias.abrirRechazar(${t.pago_id})" title="Rechazar">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            ` : ''}
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

    // ============================================================
    // VER DETALLE
    // ============================================================
    async function verDetalle(id) {
        const res = await Api.get(`api/transferencias.php?accion=obtener&id=${id}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        const t = res.data;
        const modalId = 'modal-transf-detalle';

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal modal-lg">
                    <div class="modal-header">
                        <div class="modal-title"><i class="bi bi-bank"></i> Transferencia - ${App.escapeHtml(t.folio)}</div>
                        <button class="modal-close" onclick="document.getElementById('${modalId}').remove()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        ${t.motivo_rechazo ? `
                            <div class="alert alert-danger">
                                <i class="bi bi-x-circle-fill"></i>
                                <div>
                                    <strong>RECHAZADA</strong>
                                    <div class="text-sm">Motivo: ${App.escapeHtml(t.motivo_rechazo)}</div>
                                    <div class="text-xs text-muted">Por: ${App.escapeHtml(t.verificado_por_nombre || '—')} · ${App.formatDate(t.fecha_verificacion)}</div>
                                </div>
                            </div>
                        ` : ''}

                        ${t.verificado == 1 && !t.motivo_rechazo ? `
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill"></i>
                                <div>
                                    <strong>VERIFICADA</strong>
                                    <div class="text-xs text-muted">Por: ${App.escapeHtml(t.verificado_por_nombre || '—')} · ${App.formatDate(t.fecha_verificacion)}</div>
                                </div>
                            </div>
                        ` : ''}

                        <div class="grid-2 mb-4">
                            <div><div class="text-xs text-muted">Folio venta</div><div style="font-weight:600;">${App.escapeHtml(t.folio)}</div></div>
                            <div><div class="text-xs text-muted">Fecha</div><div style="font-weight:600;">${App.formatDate(t.fecha)}</div></div>
                            <div><div class="text-xs text-muted">Vendedor</div><div style="font-weight:600;">${App.escapeHtml(t.vendedor)}</div></div>
                            <div><div class="text-xs text-muted">Punto de venta</div><div style="font-weight:600;">${App.escapeHtml(t.pv)}</div></div>
                        </div>

                        <h4 style="font-size:15px;margin-bottom:10px;"><i class="bi bi-bank text-primary"></i> Datos de la transferencia</h4>
                        <div class="grid-2 mb-4">
                            <div><div class="text-xs text-muted">Método</div><div style="font-weight:600;">${App.escapeHtml(t.metodo_detalle || 'Transferencia')}</div></div>
                            <div><div class="text-xs text-muted">Monto</div><div style="font-weight:700;font-size:18px;color:var(--primary);">${App.formatMoney(t.monto)}</div></div>
                            <div><div class="text-xs text-muted">Referencia</div><div style="font-weight:600;">${App.escapeHtml(t.referencia || '—')}</div></div>
                            <div><div class="text-xs text-muted">Últimos 4 dígitos</div><div style="font-weight:600;">${App.escapeHtml(t.ultimos_digitos || '—')}</div></div>
                            <div><div class="text-xs text-muted">Titular</div><div style="font-weight:600;">${App.escapeHtml(t.titular || '—')}</div></div>
                            <div><div class="text-xs text-muted">Banco</div><div style="font-weight:600;">${App.escapeHtml(t.banco || '—')}</div></div>
                        </div>

                        ${t.comprobante ? `
                            <h4 style="font-size:15px;margin-bottom:10px;"><i class="bi bi-image text-primary"></i> Comprobante adjunto</h4>
                            <div class="mb-4">
                                <a href="${BASE_URL}${t.comprobante}" target="_blank" style="display:block;">
                                    <img src="${BASE_URL}${t.comprobante}" alt="Comprobante" style="max-width:100%;max-height:400px;border-radius:var(--radius-md);border:1px solid var(--border);">
                                </a>
                                <div class="text-xs text-muted mt-2">Clic en la imagen para ampliar</div>
                            </div>
                        ` : `
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <div>No se adjuntó comprobante para esta transferencia.</div>
                            </div>
                        `}

                        <h4 style="font-size:15px;margin-bottom:10px;"><i class="bi bi-box-seam text-primary"></i> Productos de la venta</h4>
                        <div class="tabla-wrap mb-4">
                            <table class="tabla">
                                <thead><tr><th>Producto</th><th class="text-right">Cantidad</th><th class="text-right">Precio</th><th class="text-right">Subtotal</th></tr></thead>
                                <tbody>
                                    ${t.detalle_venta.map(d => `
                                        <tr>
                                            <td>${App.escapeHtml(d.producto)}</td>
                                            <td class="text-right">${d.cantidad}</td>
                                            <td class="text-right">${App.formatMoney(d.precio_unitario)}</td>
                                            <td class="text-right"><strong>${App.formatMoney(d.subtotal)}</strong></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>

                        ${t.otros_pagos.length > 0 ? `
                            <h4 style="font-size:15px;margin-bottom:10px;"><i class="bi bi-credit-card text-primary"></i> Otros pagos de esta venta (mixto)</h4>
                            <div class="tabla-wrap mb-4">
                                <table class="tabla">
                                    <thead><tr><th>Método</th><th>Detalle</th><th class="text-right">Monto</th></tr></thead>
                                    <tbody>
                                        ${t.otros_pagos.map(p => `
                                            <tr>
                                                <td><span class="badge badge-${p.metodo === 'efectivo' ? 'success' : 'info'}">${p.metodo === 'efectivo' ? '💵 Efectivo' : '🏦 ' + App.escapeHtml(p.metodo_detalle || 'Transferencia')}</span></td>
                                                <td class="text-muted text-sm">—</td>
                                                <td class="text-right"><strong>${App.formatMoney(p.monto)}</strong></td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        ` : ''}

                        <div class="card" style="background:var(--surface-2);">
                            <div style="display:flex;justify-content:space-between;padding:6px 0;">
                                <span>Total de la venta:</span><strong>${App.formatMoney(t.total)}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid var(--border);margin-top:6px;padding-top:10px;">
                                <span>Monto de esta transferencia:</span><strong style="color:var(--primary);font-size:18px;">${App.formatMoney(t.monto)}</strong>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        ${!t.verificado && !t.motivo_rechazo ? `
                            <button class="btn btn-danger" onclick="document.getElementById('${modalId}').remove(); Transferencias.abrirRechazar(${t.pago_id});">
                                <i class="bi bi-x-lg"></i> Rechazar
                            </button>
                            <button class="btn btn-success" onclick="document.getElementById('${modalId}').remove(); Transferencias.verificar(${t.pago_id});">
                                <i class="bi bi-check-lg"></i> Verificar
                            </button>
                        ` : ''}
                        <button class="btn btn-secondary" onclick="document.getElementById('${modalId}').remove()">Cerrar</button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    // ============================================================
    // VERIFICAR individual
    // ============================================================
    async function verificar(id) {
        const t = lista.find(x => x.pago_id == id);
        if (!t) return;

        const ok = await App.confirmar(
            `¿Verificar esta transferencia de ${App.formatMoney(t.monto)}?\n\nReferencia: ${t.referencia || '—'}\nVendedor: ${t.vendedor}`,
            '✅ Confirmar verificación'
        );
        if (!ok) return;

        const res = await Api.post('api/transferencias.php?accion=verificar', { id });
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Verificada', res.message);
        cargar();
    }

    // ============================================================
    // RECHAZAR
    // ============================================================
    async function abrirRechazar(id) {
        const t = lista.find(x => x.pago_id == id);
        if (!t) return;

        const motivo = prompt(
            `Motivo del rechazo (obligatorio):\n\nFolio: ${t.folio}\nMonto: ${App.formatMoney(t.monto)}\nReferencia: ${t.referencia || '—'}\n\nEj: "Referencia no coincide", "Monto incorrecto", "Comprobante ilegible"`
        );

        if (!motivo || motivo.trim().length < 5) {
            Toast.warning('Motivo requerido', 'Debe tener al menos 5 caracteres');
            return;
        }

        const ok = await App.confirmar(
            `¿Rechazar esta transferencia?\n\nMotivo: ${motivo}`,
            '⚠️ Confirmar rechazo'
        );
        if (!ok) return;

        const res = await Api.post('api/transferencias.php?accion=rechazar', { id, motivo: motivo.trim() });
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Rechazada', 'El administrador fue notificado');
        cargar();
    }

    // ============================================================
    // VERIFICACIÓN MASIVA
    // ============================================================
    async function abrirVerificacionMasiva() {
        seleccionadas = new Set();
        document.getElementById('modal-masiva').classList.add('active');
        actualizarContadorMasiva();

        // Cargar todas las pendientes (sin filtro de fecha)
        const res = await Api.get('api/transferencias.php?accion=listar&estado=pendiente&desde=' + fechaHace30Dias());
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        renderListaMasiva(res.data.datos);
    }

    function renderListaMasiva(datos) {
        const cont = document.getElementById('lista-masiva');

        if (!datos.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-check-circle" style="font-size:40px;color:var(--success);"></i>
                <p class="text-muted mt-2">No hay transferencias pendientes 🎉</p>
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap" style="max-height:450px;overflow-y:auto;">
                <table class="tabla">
                    <thead style="position:sticky;top:0;background:var(--surface-2);z-index:1;">
                        <tr>
                            <th style="width:40px;">
                                <input type="checkbox" onchange="Transferencias.seleccionarTodas(this.checked)">
                            </th>
                            <th>Folio</th>
                            <th>Fecha</th>
                            <th>Vendedor</th>
                            <th>Referencia</th>
                            <th class="text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${datos.map(t => `
                            <tr>
                                <td>
                                    <input type="checkbox" class="check-transf" value="${t.pago_id}"
                                           data-monto="${t.monto}"
                                           onchange="Transferencias.toggleSeleccion(${t.pago_id}, this.checked, ${t.monto})">
                                </td>
                                <td><strong>${App.escapeHtml(t.folio)}</strong></td>
                                <td class="text-muted text-xs">${App.formatDate(t.fecha)}</td>
                                <td>${App.escapeHtml(t.vendedor)}</td>
                                <td class="text-muted text-xs">${App.escapeHtml(t.referencia || '—')}</td>
                                <td class="text-right"><strong>${App.formatMoney(t.monto)}</strong></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function seleccionarTodas(checked) {
        document.querySelectorAll('.check-transf').forEach(cb => {
            cb.checked = checked;
            const id = parseInt(cb.value);
            const monto = parseFloat(cb.dataset.monto);
            if (checked) seleccionadas.add(id);
            else seleccionadas.delete(id);
        });
        actualizarContadorMasiva();
    }

    function toggleSeleccion(id, checked, monto) {
        if (checked) seleccionadas.add(id);
        else seleccionadas.delete(id);
        actualizarContadorMasiva();
    }

    function actualizarContadorMasiva() {
        const total = seleccionadas.size;

        // Calcular monto
        let monto = 0;
        document.querySelectorAll('.check-transf').forEach(cb => {
            const id = parseInt(cb.value);
            if (seleccionadas.has(id)) {
                monto += parseFloat(cb.dataset.monto);
            }
        });

        document.getElementById('masiva-total').textContent = total;
        document.getElementById('masiva-monto').textContent = App.formatMoney(monto);

        const btn = document.getElementById('btn-verificar-masiva');
        btn.disabled = total === 0;
    }

    async function verificarMasiva() {
        if (seleccionadas.size === 0) {
            Toast.warning('Sin selección', 'Selecciona al menos una transferencia');
            return;
        }

        const ok = await App.confirmar(
            `¿Verificar ${seleccionadas.size} transferencia(s)?\n\nTotal: ${document.getElementById('masiva-monto').textContent}`,
            '✅ Confirmar verificación masiva'
        );
        if (!ok) return;

        const btn = document.getElementById('btn-verificar-masiva');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Verificando...';

        const res = await Api.post('api/transferencias.php?accion=verificar_masivo', {
            ids: Array.from(seleccionadas)
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Verificar seleccionadas';

        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Verificadas', `${res.data.procesados} transferencia(s)`);
        if (res.data.errores && res.data.errores.length) {
            Toast.warning('Algunos errores', res.data.errores.join('<br>'));
        }

        cerrarMasiva();
        cargar();
    }

    function cerrarMasiva() {
        document.getElementById('modal-masiva').classList.remove('active');
    }

    function fechaHace30Dias() {
        const d = new Date();
        d.setDate(d.getDate() - 30);
        return d.toISOString().split('T')[0];
    }

    // ============================================================
    // FILTROS
    // ============================================================
    function limpiar() {
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-estado').value = 'pendiente';
        document.getElementById('filtro-pv').value = '';
        document.getElementById('filtro-vendedor').value = '';
        document.getElementById('filtro-metodo').value = '';
        document.getElementById('filtro-desde').value = '';
        document.getElementById('filtro-hasta').value = '';
        cargar();
    }

    // ============================================================
    // EVENTOS
    // ============================================================
    document.addEventListener('DOMContentLoaded', () => {
        cargarCatalogos().then(cargar);

        const debounced = App.debounce(cargar, 400);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-estado')?.addEventListener('change', cargar);
        document.getElementById('filtro-pv')?.addEventListener('change', cargar);
        document.getElementById('filtro-vendedor')?.addEventListener('change', cargar);
        document.getElementById('filtro-metodo')?.addEventListener('change', cargar);
        document.getElementById('filtro-desde')?.addEventListener('change', cargar);
        document.getElementById('filtro-hasta')?.addEventListener('change', cargar);
    });

    return {
        cargar, limpiar, verDetalle, verificar, abrirRechazar,
        abrirVerificacionMasiva, cerrarMasiva, seleccionarTodas, toggleSeleccion, verificarMasiva,
    };
})();

window.Transferencias = Transferencias;