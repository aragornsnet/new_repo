/**
 * IPV - Turnos (Supervisor)
 */

const Turnos = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let lista = [];

    async function cargarCatalogos() {
        const res = await Api.get('api/turnos.php?accion=catalogos');
        if (!res.success) return;

        const { puntos_venta, vendedores } = res.data;

        document.getElementById('filtro-pv').innerHTML = '<option value="">Todos los PV</option>' +
            puntos_venta.map(p => `<option value="${p.id}">${App.escapeHtml(p.nombre)}</option>`).join('');

        document.getElementById('filtro-vendedor').innerHTML = '<option value="">Todos</option>' +
            vendedores.map(v => `<option value="${v.id}">${App.escapeHtml(v.nombre)}</option>`).join('');
    }

    async function cargar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const pvId = document.getElementById('filtro-pv')?.value || '';
        const vendedorId = document.getElementById('filtro-vendedor')?.value || '';
        const estado = document.getElementById('filtro-estado')?.value || '';
        const desde = document.getElementById('filtro-desde')?.value || '';
        const hasta = document.getElementById('filtro-hasta')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (pvId) params.append('pv_id', pvId);
        if (vendedorId) params.append('vendedor_id', vendedorId);
        if (estado) params.append('estado', estado);
        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);

        const cont = document.getElementById('tabla-turnos');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/turnos.php?accion=listar&${params}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        lista = res.data;
        document.getElementById('total-turnos').textContent = lista.length;
        render();
    }

    async function cargarResumen() {
        const res = await Api.get('api/turnos.php?accion=resumen_dia');
        if (!res.success) return;

        const r = res.data;
        document.getElementById('res-total').textContent = r.total_turnos || 0;
        document.getElementById('res-abiertos').textContent = r.abiertos || 0;
        document.getElementById('res-cerrados').textContent = r.cerrados || 0;
        document.getElementById('res-forzados').textContent = r.forzados || 0;
    }

    function render() {
        const cont = document.getElementById('tabla-turnos');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>Sin turnos</h3>
                <p>No hay turnos que coincidan con los filtros.</p>
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Vendedor</th>
                            <th>Punto de venta</th>
                            <th>Apertura</th>
                            <th>Cierre</th>
                            <th class="text-right">Ventas</th>
                            <th class="text-right">Total</th>
                            <th>Estado</th>
                            <th class="text-right">Ver</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(t => {
                            const abierto = t.estado === 'abierto';
                            return `
                                <tr>
                                    <td class="text-muted">#${t.id}</td>
                                    <td><strong>${App.escapeHtml(t.vendedor)}</strong></td>
                                    <td>${App.escapeHtml(t.pv)}</td>
                                    <td class="text-muted text-xs">${App.formatDate(t.fecha_apertura)}</td>
                                    <td class="text-muted text-xs">${t.fecha_cierre ? App.formatDate(t.fecha_cierre) : '<span class="text-success">—</span>'}</td>
                                    <td class="text-right">${t.num_ventas}</td>
                                    <td class="text-right"><strong>${App.formatMoney(t.total_vendido)}</strong></td>
                                    <td>
                                        ${abierto
                                            ? '<span class="badge badge-success"><i class="bi bi-circle-fill"></i> Abierto</span>'
                                            : (t.forzado == 1
                                                ? '<span class="badge badge-warning"><i class="bi bi-exclamation-triangle-fill"></i> Forzado</span>'
                                                : '<span class="badge badge-neutral">Cerrado</span>')}
                                    </td>
                                    <td class="text-right">
                                        <button class="btn btn-ghost btn-icon" onclick="Turnos.verDetalle(${t.id})" title="Ver detalle">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    async function verDetalle(id) {
        const res = await Api.get(`api/turnos.php?accion=obtener&id=${id}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        const t = res.data;
        const modalId = 'modal-turno-detalle';

        const duracion = t.fecha_cierre
            ? calcularDuracion(t.fecha_apertura, t.fecha_cierre)
            : calcularDuracion(t.fecha_apertura, null);

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal modal-xl">
                    <div class="modal-header">
                        <div class="modal-title">
                            <i class="bi bi-clock-history"></i> Turno #${t.id} - ${App.escapeHtml(t.vendedor)}
                        </div>
                        <button class="modal-close" onclick="document.getElementById('${modalId}').remove()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="grid-3 mb-4">
                            <div><div class="text-xs text-muted">Vendedor</div><div style="font-weight:600;">${App.escapeHtml(t.vendedor)}</div></div>
                            <div><div class="text-xs text-muted">Punto de venta</div><div style="font-weight:600;">${App.escapeHtml(t.pv)}</div></div>
                            <div><div class="text-xs text-muted">Estado</div><div>${t.estado === 'abierto' ? '<span class="badge badge-success">Abierto</span>' : '<span class="badge badge-neutral">Cerrado</span>'}</div></div>
                            <div><div class="text-xs text-muted">Apertura</div><div style="font-weight:600;">${App.formatDate(t.fecha_apertura)}</div></div>
                            <div><div class="text-xs text-muted">Cierre</div><div style="font-weight:600;">${t.fecha_cierre ? App.formatDate(t.fecha_cierre) : '—'}</div></div>
                            <div><div class="text-xs text-muted">Duración</div><div style="font-weight:600;">${duracion}</div></div>
                            <div><div class="text-xs text-muted">Monto inicial</div><div style="font-weight:600;">${App.formatMoney(t.monto_inicial)}</div></div>
                            <div><div class="text-xs text-muted">Total ventas</div><div style="font-weight:600;color:var(--primary);">${App.formatMoney(t.total_vendido)}</div></div>
                            <div><div class="text-xs text-muted">Num. ventas</div><div style="font-weight:600;">${t.num_ventas}</div></div>
                        </div>

                        ${t.forzado == 1 ? `
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <div>
                                    <strong>Turno forzado</strong>
                                    <div class="text-sm">Por: ${App.escapeHtml(t.forzado_por_nombre || '—')} · ${App.formatDate(t.fecha_forzado)}</div>
                                    <div class="text-sm">Justificación: ${App.escapeHtml(t.justificacion_forzado || '—')}</div>
                                </div>
                            </div>
                        ` : ''}

                        <h4 style="font-size:15px;margin-bottom:10px;"><i class="bi bi-cash-stack text-primary"></i> Resumen de pagos</h4>
                        <div class="grid-3 mb-4">
                            <div style="padding:12px;background:var(--success-light);border-radius:var(--radius-md);">
                                <div class="text-xs text-muted">Efectivo</div>
                                <div style="font-weight:700;font-size:18px;color:var(--success);">${App.formatMoney(t.resumen_pagos.total_efectivo)}</div>
                            </div>
                            <div style="padding:12px;background:var(--info-light);border-radius:var(--radius-md);">
                                <div class="text-xs text-muted">Transferencia</div>
                                <div style="font-weight:700;font-size:18px;color:var(--info);">${App.formatMoney(t.resumen_pagos.total_transferencia)}</div>
                            </div>
                            <div style="padding:12px;background:var(--primary-light);border-radius:var(--radius-md);">
                                <div class="text-xs text-muted">Total</div>
                                <div style="font-weight:700;font-size:18px;color:var(--primary);">${App.formatMoney(parseFloat(t.resumen_pagos.total_efectivo) + parseFloat(t.resumen_pagos.total_transferencia))}</div>
                            </div>
                        </div>

                        ${t.fecha_cierre ? `
                            <h4 style="font-size:15px;margin-bottom:10px;"><i class="bi bi-check-circle text-primary"></i> Cierre</h4>
                            <div class="grid-3 mb-4">
                                <div><div class="text-xs text-muted">Efectivo final (declarado)</div><div style="font-weight:600;">${App.formatMoney(t.monto_final_efectivo)}</div></div>
                                <div><div class="text-xs text-muted">Transferencia final</div><div style="font-weight:600;">${App.formatMoney(t.monto_final_transferencia)}</div></div>
                                <div><div class="text-xs text-muted">Con conteo físico</div><div>${t.cierre_con_conteo == 1 ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-neutral">No</span>'}</div></div>
                                <div><div class="text-xs text-muted">Descuadres</div><div style="font-weight:600;color:${parseInt(t.total_descuadres) !== 0 ? 'var(--danger)' : 'var(--success)'};">${t.total_descuadres}</div></div>
                                ${t.observaciones_cierre ? `<div style="grid-column: span 3;"><div class="text-xs text-muted">Observaciones</div><div>${App.escapeHtml(t.observaciones_cierre)}</div></div>` : ''}
                            </div>
                        ` : ''}

                        <h4 style="font-size:15px;margin-bottom:10px;"><i class="bi bi-cart-check text-primary"></i> Ventas del turno (${t.ventas.length})</h4>
                        ${t.ventas.length === 0 ? `
                            <p class="text-muted text-sm">Sin ventas en este turno.</p>
                        ` : `
                            <div class="tabla-wrap mb-4" style="max-height:300px;overflow-y:auto;">
                                <table class="tabla">
                                    <thead style="position:sticky;top:0;background:var(--surface-2);">
                                        <tr>
                                            <th>Folio</th>
                                            <th>Hora</th>
                                            <th class="text-right">Efectivo</th>
                                            <th class="text-right">Transferencia</th>
                                            <th class="text-right">Total</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${t.ventas.map(v => `
                                            <tr style="${v.estado === 'cancelada' ? 'opacity:.5;' : ''}">
                                                <td>${App.escapeHtml(v.folio)}</td>
                                                <td class="text-muted text-xs">${App.formatDate(v.fecha)}</td>
                                                <td class="text-right text-success">${App.formatMoney(v.efectivo)}</td>
                                                <td class="text-right text-info">${App.formatMoney(v.transferencia)}</td>
                                                <td class="text-right"><strong>${App.formatMoney(v.total)}</strong></td>
                                                <td>${v.estado === 'completada' ? '<span class="badge badge-success">OK</span>' : '<span class="badge badge-danger">Cancelada</span>'}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `}

                        ${t.inventario && t.inventario.length > 0 ? `
                            <h4 style="font-size:15px;margin-bottom:10px;"><i class="bi bi-box-seam text-primary"></i> Inventario del turno (conteo)</h4>
                            <div class="tabla-wrap" style="max-height:300px;overflow-y:auto;">
                                <table class="tabla">
                                    <thead style="position:sticky;top:0;background:var(--surface-2);">
                                        <tr>
                                            <th>Producto</th>
                                            <th class="text-right">Inicial</th>
                                            <th class="text-right">Entradas</th>
                                            <th class="text-right">Ventas</th>
                                            <th class="text-right">Bajas</th>
                                            <th class="text-right">Ajustes</th>
                                            <th class="text-right">Final</th>
                                            <th class="text-right">Descuadre</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${t.inventario.map(i => `
                                            <tr>
                                                <td><strong>${App.escapeHtml(i.producto)}</strong></td>
                                                <td class="text-right">${i.existencia_inicial}</td>
                                                <td class="text-right text-success">+${i.entradas}</td>
                                                <td class="text-right text-danger">-${i.ventas}</td>
                                                <td class="text-right text-danger">-${i.bajas}</td>
                                                <td class="text-right">${i.ajustes >= 0 ? '+' : ''}${i.ajustes}</td>
                                                <td class="text-right"><strong>${i.existencia_final ?? '—'}</strong></td>
                                                <td class="text-right" style="color:${i.descuadre !== 0 ? 'var(--danger)' : 'var(--success)'};font-weight:600;">${i.descuadre > 0 ? '+' : ''}${i.descuadre}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        ` : ''}
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.getElementById('${modalId}').remove()">Cerrar</button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    function calcularDuracion(desde, hasta) {
        const d1 = new Date(desde.replace(' ', 'T'));
        const d2 = hasta ? new Date(hasta.replace(' ', 'T')) : new Date();
        const diff = Math.floor((d2 - d1) / 1000);
        const horas = Math.floor(diff / 3600);
        const min = Math.floor((diff % 3600) / 60);
        return `${horas}h ${min}min`;
    }

    function limpiar() {
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-pv').value = '';
        document.getElementById('filtro-vendedor').value = '';
        document.getElementById('filtro-estado').value = '';
        document.getElementById('filtro-desde').value = '';
        document.getElementById('filtro-hasta').value = '';
        cargar();
    }

    document.addEventListener('DOMContentLoaded', () => {
        cargarCatalogos();
        cargarResumen();
        cargar();

        const debounced = App.debounce(cargar, 400);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-pv')?.addEventListener('change', cargar);
        document.getElementById('filtro-vendedor')?.addEventListener('change', cargar);
        document.getElementById('filtro-estado')?.addEventListener('change', cargar);
        document.getElementById('filtro-desde')?.addEventListener('change', cargar);
        document.getElementById('filtro-hasta')?.addEventListener('change', cargar);
    });

    return { cargar, cargarResumen, limpiar, verDetalle };
})();

window.Turnos = Turnos;