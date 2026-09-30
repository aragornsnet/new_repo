/**
 * IPV - Trazabilidad del almacén
 */

const AlmacenTrazabilidad = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let lista = [];
    let productoActual = null;

    // ═══════════════════════════════════════════════════════════
    // LISTAR
    // ═══════════════════════════════════════════════════════════
    async function cargar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const cat = document.getElementById('filtro-cat')?.value || '';
        const tipoFiltro = document.getElementById('filtro-tipo')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (cat) params.append('categoria_id', cat);
        if (tipoFiltro === 'con_mov') params.append('con_movimientos', '1');
        if (tipoFiltro === 'con_des') params.append('con_descuadres', '1');

        const cont = document.getElementById('tabla-trazabilidad');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get('api/almacen.php?accion=trazabilidad_listar&' + params);
        if (!res.success) {
            Toast.error('Error', res.message);
            cont.innerHTML = `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        lista = res.data;

        document.getElementById('total-productos').textContent = lista.length;
        document.getElementById('total-productos-2').textContent = lista.length;

        render();
    }

    function render() {
        const cont = document.getElementById('tabla-trazabilidad');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>Sin productos</h3>
                <p>No hay productos que coincidan con los filtros.</p>
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th class="text-right">Stock actual</th>
                            <th class="text-right">Entradas</th>
                            <th class="text-right">Salidas</th>
                            <th class="text-right">Ajustes</th>
                            <th class="text-right">Movs.</th>
                            <th>Último mov.</th>
                            <th class="text-right">Ver</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(p => {
                            const unidad = p.unidad_medida || 'Unidad';
                            const ajustes = parseInt(p.total_ajustes) || 0;
                            const colorAjuste = ajustes === 0
                                ? 'var(--text-muted)'
                                : (ajustes > 0 ? 'var(--success)' : 'var(--danger)');

                            return `
                                <tr>
                                    <td>
                                        <div style="font-weight:600;">${App.escapeHtml(p.producto)}</div>
                                        <div class="text-xs text-muted">${App.escapeHtml(p.codigo_barras || '—')}</div>
                                    </td>
                                    <td>${p.categoria ? `<span class="badge badge-neutral">${App.escapeHtml(p.categoria)}</span>` : '—'}</td>
                                    <td class="text-right"><strong>${p.stock_actual} ${App.escapeHtml(unidad)}</strong></td>
                                    <td class="text-right text-success">+${p.total_entradas}</td>
                                    <td class="text-right text-warning">-${p.total_salidas}</td>
                                    <td class="text-right" style="color:${colorAjuste};font-weight:600;">
                                        ${ajustes > 0 ? '+' : ''}${ajustes}
                                    </td>
                                    <td class="text-right">${p.num_movimientos}</td>
                                    <td class="text-muted text-xs">
                                        ${p.ultimo_movimiento_fecha ? App.formatDate(p.ultimo_movimiento_fecha) : '—'}
                                    </td>
                                    <td class="text-right">
                                        <button class="btn btn-ghost btn-icon"
                                                onclick="AlmacenTrazabilidad.verExpediente(${p.producto_id})"
                                                title="Ver expediente">
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

    // ═══════════════════════════════════════════════════════════
    // EXPEDIENTE
    // ═══════════════════════════════════════════════════════════
    async function verExpediente(productoId) {
        document.getElementById('modal-expediente').classList.add('active');
        document.getElementById('exp-contenido').innerHTML =
            '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/almacen.php?accion=trazabilidad_producto&producto_id=${productoId}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            document.getElementById('exp-contenido').innerHTML =
                `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        productoActual = res.data;
        const p = productoActual;

        document.getElementById('exp-titulo').textContent = p.nombre;

        const unidad = p.unidad_medida || 'Unidad';
        const r = p.resumen;

        document.getElementById('exp-contenido').innerHTML = `
            <div class="grid-3 mb-4">
                <div>
                    <div class="text-xs text-muted">Código</div>
                    <div style="font-weight:600;">${App.escapeHtml(p.codigo_barras || '—')}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Categoría</div>
                    <div style="font-weight:600;">${App.escapeHtml(p.categoria || 'Sin categoría')}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Unidad</div>
                    <div style="font-weight:600;">${App.escapeHtml(unidad)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Stock actual en almacén</div>
                    <div style="font-weight:700;font-size:18px;color:var(--primary);">
                        ${p.stock_actual} ${App.escapeHtml(unidad)}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted">Stock mínimo</div>
                    <div style="font-weight:600;">${p.stock_minimo} ${App.escapeHtml(unidad)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Total movimientos</div>
                    <div style="font-weight:600;">${r.num_movimientos}</div>
                </div>
            </div>

            ${p.descripcion ? `<div class="mb-4"><div class="text-xs text-muted">Descripción</div><div>${App.escapeHtml(p.descripcion)}</div></div>` : ''}

            <div class="grid-3 mb-4">
                <div style="padding:14px;background:var(--success-light);border-radius:var(--radius-md);">
                    <div class="text-xs text-muted">Total entradas</div>
                    <div style="font-weight:700;font-size:22px;color:var(--success);">+${r.total_entradas}</div>
                    <div class="text-xs text-muted">${App.escapeHtml(unidad)}</div>
                </div>
                <div style="padding:14px;background:var(--warning-light);border-radius:var(--radius-md);">
                    <div class="text-xs text-muted">Total salidas (despachos)</div>
                    <div style="font-weight:700;font-size:22px;color:var(--warning);">-${r.total_salidas}</div>
                    <div class="text-xs text-muted">${App.escapeHtml(unidad)}</div>
                </div>
                <div style="padding:14px;background:var(--info-light);border-radius:var(--radius-md);">
                    <div class="text-xs text-muted">Ajustes netos</div>
                    <div style="font-weight:700;font-size:22px;color:${r.total_ajustes === 0 ? 'var(--text-muted)' : (r.total_ajustes > 0 ? 'var(--success)' : 'var(--danger)')};">
                        ${r.total_ajustes > 0 ? '+' : ''}${r.total_ajustes}
                    </div>
                    <div class="text-xs text-muted">${App.escapeHtml(unidad)}</div>
                </div>
            </div>

            <div class="text-xs text-muted mb-4">
                Primer movimiento: <strong>${r.primer_movimiento ? App.formatDate(r.primer_movimiento) : '—'}</strong>
                · Último movimiento: <strong>${r.ultimo_movimiento ? App.formatDate(r.ultimo_movimiento) : '—'}</strong>
            </div>

            <div class="form-grid mb-3">
                <div class="field">
                    <label>Desde</label>
                    <input type="date" id="exp-desde">
                </div>
                <div class="field">
                    <label>Hasta</label>
                    <input type="date" id="exp-hasta">
                </div>
                <div class="field">
                    <label>Tipo</label>
                    <select id="exp-tipo">
                        <option value="">Todos</option>
                        <option value="entrada">Solo entradas</option>
                        <option value="transferencia">Solo salidas (despachos)</option>
                        <option value="ajuste">Solo ajustes</option>
                    </select>
                </div>
                <div class="field" style="display:flex;align-items:flex-end;">
                    <button class="btn btn-primary" onclick="AlmacenTrazabilidad.filtrarExpediente()">
                        <i class="bi bi-funnel-fill"></i> Filtrar
                    </button>
                </div>
            </div>

            <h4 style="font-size:15px;margin-bottom:12px;">
                <i class="bi bi-list-ul text-primary"></i>
                Historial de movimientos
                <span class="badge badge-neutral ml-2">${p.movimientos.length}</span>
            </h4>

            ${renderTimeline(p.movimientos, unidad)}
        `;

        setTimeout(() => {
            document.getElementById('exp-tipo')?.addEventListener('change', filtrarExpediente);
            document.getElementById('exp-desde')?.addEventListener('change', filtrarExpediente);
            document.getElementById('exp-hasta')?.addEventListener('change', filtrarExpediente);
        }, 50);
    }

    function renderTimeline(movimientos, unidad) {
        if (!movimientos.length) {
            return `<div class="empty-state" style="padding:40px 20px;">
                <i class="bi bi-inbox" style="font-size:40px;"></i>
                <p class="text-muted mt-2">Sin movimientos en el período seleccionado</p>
            </div>`;
        }

        const tipoInfo = {
            entrada:       { color: 'success', icono: 'box-arrow-in-down', label: 'Entrada' },
            transferencia: { color: 'warning', icono: 'truck',             label: 'Salida (despacho)' },
            ajuste:        { color: 'info',    icono: 'wrench-adjustable', label: 'Ajuste' },
            baja:          { color: 'danger',  icono: 'trash',             label: 'Baja' },
            salida:        { color: 'warning', icono: 'box-arrow-up',      label: 'Salida' },
        };

        return `
            <div class="tabla-wrap" style="max-height:500px;overflow-y:auto;">
                <table class="tabla">
                    <thead style="position:sticky;top:0;background:var(--surface-2);z-index:1;">
                        <tr>
                            <th style="width:140px;">Fecha</th>
                            <th>Tipo</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">Stock</th>
                            <th>Motivo</th>
                            <th>Detalle</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${movimientos.map(m => {
                            const info = tipoInfo[m.tipo] || { color: 'neutral', icono: 'circle', label: m.tipo };
                            const diff = parseInt(m.cantidad);
                            const signo = diff > 0 ? '+' : '';
                            const colorCant = m.tipo === 'entrada' ? 'var(--success)' :
                                             (m.tipo === 'transferencia' || m.tipo === 'baja' ? 'var(--danger)' :
                                             (diff > 0 ? 'var(--success)' : 'var(--danger)'));

                            return `
                                <tr>
                                    <td class="text-muted text-xs" style="white-space:nowrap;">
                                        ${App.formatDate(m.fecha)}
                                    </td>
                                    <td>
                                        <span class="badge badge-${info.color}">
                                            <i class="bi bi-${info.icono}"></i> ${info.label}
                                        </span>
                                    </td>
                                    <td class="text-right" style="font-weight:700;color:${colorCant};">
                                        ${signo}${diff} ${App.escapeHtml(unidad)}
                                    </td>
                                    <td class="text-right text-sm">
                                        <span class="text-muted">${m.valor_anterior}</span>
                                        <i class="bi bi-arrow-right text-xs"></i>
                                        <strong>${m.valor_nuevo}</strong>
                                    </td>
                                    <td class="text-sm">${App.escapeHtml(m.motivo || '—')}</td>
                                    <td class="text-muted text-sm">${App.escapeHtml(m.descripcion || '—')}</td>
                                    <td class="text-muted text-sm">${App.escapeHtml(m.usuario)}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    async function filtrarExpediente() {
        if (!productoActual) return;

        const desde = document.getElementById('exp-desde')?.value || '';
        const hasta = document.getElementById('exp-hasta')?.value || '';
        const tipo  = document.getElementById('exp-tipo')?.value || '';

        const params = new URLSearchParams();
        params.append('producto_id', productoActual.id);
        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);
        if (tipo)  params.append('tipo', tipo);

        const res = await Api.get('api/almacen.php?accion=trazabilidad_producto&' + params);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        productoActual.movimientos = res.data.movimientos;

        const unidad = productoActual.unidad_medida || 'Unidad';
        const cont = document.getElementById('exp-contenido');
        const tablaAntigua = cont.querySelector('.tabla-wrap');
        if (tablaAntigua) {
            tablaAntigua.outerHTML = renderTimeline(res.data.movimientos, unidad);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // EXPORTAR CSV
    // ═══════════════════════════════════════════════════════════
    function exportar() {
        if (!productoActual) return;

        const movs = productoActual.movimientos;
        if (!movs.length) {
            Toast.warning('Sin datos', 'No hay movimientos para exportar');
            return;
        }

        const unidad = productoActual.unidad_medida || 'Unidad';

        let csv = '\uFEFF';
        csv += `Trazabilidad del almacén - ${productoActual.nombre}\n`;
        csv += `Generado: ${new Date().toLocaleString()}\n\n`;
        csv += `Fecha;Tipo;Cantidad;Unidad;Stock antes;Stock después;Motivo;Detalle;Usuario\n`;

        movs.forEach(m => {
            const esc = (v) => `"${String(v || '').replace(/"/g, '""')}"`;
            csv += [
                esc(m.fecha),
                esc(m.tipo),
                m.cantidad,
                esc(unidad),
                m.valor_anterior,
                m.valor_nuevo,
                esc(m.motivo),
                esc(m.descripcion),
                esc(m.usuario),
            ].join(';') + '\n';
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `trazabilidad_${productoActual.nombre.replace(/\s+/g, '_')}_${new Date().toISOString().slice(0,10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);

        Toast.success('Exportado', 'Archivo descargado');
    }

    function cerrarExpediente() {
        document.getElementById('modal-expediente').classList.remove('active');
        productoActual = null;
    }

    function limpiar() {
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-cat').value = '';
        document.getElementById('filtro-tipo').value = '';
        cargar();
    }

    document.addEventListener('DOMContentLoaded', () => {
        cargar();

        const debounced = App.debounce(cargar, 400);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-cat')?.addEventListener('change', cargar);
        document.getElementById('filtro-tipo')?.addEventListener('change', cargar);
    });

    return {
        cargar,
        limpiar,
        verExpediente,
        cerrarExpediente,
        filtrarExpediente,
        exportar,
    };
})();

window.AlmacenTrazabilidad = AlmacenTrazabilidad;