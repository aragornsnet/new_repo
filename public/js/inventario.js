/**
 * IPV - Inventario (vista Supervisor)
 */

const Inventario = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let lista = [];

    async function cargarCatalogos() {
        const res = await Api.get('api/inventario.php?accion=catalogos');
        if (!res.success) return;

        const { puntos_venta, categorias } = res.data;

        const selPV = document.getElementById('filtro-pv');
        selPV.innerHTML = '<option value="">Todos los PV</option>' +
            puntos_venta.map(p => `<option value="${p.id}">${App.escapeHtml(p.nombre)}</option>`).join('');

        const selCat = document.getElementById('filtro-cat');
        selCat.innerHTML = '<option value="">Todas</option>' +
            categorias.map(c => `<option value="${c.id}">${App.escapeHtml(c.nombre)}</option>`).join('');
    }

    async function cargarResumen() {
        const cont = document.getElementById('resumen-pv');
        cont.innerHTML = '<div class="empty-state"><div class="spinner"></div></div>';

        const res = await Api.get('api/inventario.php?accion=resumen_pv');
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        const pvs = res.data;

        cont.innerHTML = pvs.map(pv => `
            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-card-icon primary"><i class="bi bi-shop"></i></div>
                    ${pv.turnos_abiertos > 0 ? '<span class="badge badge-success"><i class="bi bi-circle-fill"></i> Turno abierto</span>' : ''}
                </div>
                <div style="font-weight:700;font-size:15px;">${App.escapeHtml(pv.nombre)}</div>
                <div class="stat-card-label">${App.escapeHtml(pv.direccion || 'Sin dirección')}</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;">
                    <div>
                        <div class="text-xs text-muted">Productos</div>
                        <div style="font-weight:600;">${pv.num_productos}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted">Stock total</div>
                        <div style="font-weight:600;">${App.formatNumber(pv.stock_total)}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted">Vendedores</div>
                        <div style="font-weight:600;">${pv.num_vendedores}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted">Alertas</div>
                        <div style="font-weight:600;">
                            ${pv.items_negativo > 0 ? `<span class="badge badge-danger">${pv.items_negativo} neg</span>` : ''}
                            ${pv.items_bajo > 0 ? `<span class="badge badge-warning">${pv.items_bajo} bajo</span>` : ''}
                            ${pv.items_bajo == 0 && pv.items_negativo == 0 ? '<span class="badge badge-success">OK</span>' : ''}
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    async function cargar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const pv = document.getElementById('filtro-pv')?.value || '';
        const cat = document.getElementById('filtro-cat')?.value || '';
        const filtro = document.getElementById('filtro-estado')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (pv) params.append('pv_id', pv);
        if (cat) params.append('categoria_id', cat);
        if (filtro) params.append('filtro', filtro);

        const cont = document.getElementById('tabla-inventario');
        cont.innerHTML = '<div class="empty-state"><div class="spinner"></div></div>';

        const res = await Api.get(`api/inventario.php?accion=listar&${params}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        lista = res.data;
        document.getElementById('total-items').textContent = lista.length;
        render();
    }

    function render() {
        const cont = document.getElementById('tabla-inventario');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>Sin resultados</h3>
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
                            <th>Punto de venta</th>
                            <th class="text-right">Precio</th>
                            <th class="text-right">Costo</th>
                            <th class="text-right">Stock</th>
                            <th>Unidad</th>
                            <th class="text-right">Mínimo</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(item => {
                            const stock = parseInt(item.stock);
                            const unidad = item.unidad_medida || 'Unidad';
                            let badgeEstado = '<span class="badge badge-success">OK</span>';
                            if (item.estado_stock === 'negativo') badgeEstado = '<span class="badge badge-danger"><i class="bi bi-x-octagon-fill"></i> Negativo</span>';
                            else if (item.estado_stock === 'bajo') badgeEstado = '<span class="badge badge-warning"><i class="bi bi-exclamation-triangle-fill"></i> Bajo</span>';

                            return `
                                <tr>
                                    <td>
                                        <div style="font-weight:600;">${App.escapeHtml(item.producto)}</div>
                                        <div class="text-xs text-muted">${App.escapeHtml(item.codigo_barras || '—')}</div>
                                    </td>
                                    <td>${item.categoria ? `<span class="badge badge-neutral">${App.escapeHtml(item.categoria)}</span>` : '—'}</td>
                                    <td>${App.escapeHtml(item.pv)}</td>
                                    <td class="text-right">${App.formatMoney(item.precio)}</td>
                                    <td class="text-right text-muted">${App.formatMoney(item.costo)}</td>
                                    <td class="text-right"><strong>${stock}</strong></td>
                                    <td class="text-muted text-sm">${App.escapeHtml(unidad)}</td>
                                    <td class="text-right text-muted">${item.stock_minimo} ${App.escapeHtml(unidad)}</td>
                                    <td>${badgeEstado}</td>
                                    <td class="text-right">
                                        <button class="btn btn-ghost btn-icon" onclick="Inventario.verDetalle(${item.producto_id})" title="Ver detalle">
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

    async function verDetalle(productoId) {
        const res = await Api.get(`api/inventario.php?accion=detalle_producto&producto_id=${productoId}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        const p = res.data;
        const unidad = p.unidad_medida || 'Unidad';
        const modalId = 'modal-inv-detalle';

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal modal-xl">
                    <div class="modal-header">
                        <div class="modal-title"><i class="bi bi-box-seam"></i> ${App.escapeHtml(p.nombre)}</div>
                        <button class="modal-close" onclick="document.getElementById('${modalId}').remove()"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="grid-3 mb-4">
                            <div><div class="text-xs text-muted">Código</div><div style="font-weight:600;">${App.escapeHtml(p.codigo_barras || '—')}</div></div>
                            <div><div class="text-xs text-muted">Categoría</div><div style="font-weight:600;">${App.escapeHtml(p.categoria || 'Sin categoría')}</div></div>
                            <div><div class="text-xs text-muted">Unidad</div><div style="font-weight:600;">${App.escapeHtml(unidad)}</div></div>
                            <div><div class="text-xs text-muted">Precio</div><div style="font-weight:700;color:var(--primary);">${App.formatMoney(p.precio)}</div></div>
                            <div><div class="text-xs text-muted">Costo</div><div style="font-weight:600;">${App.formatMoney(p.costo)}</div></div>
                            <div><div class="text-xs text-muted">Stock mínimo</div><div style="font-weight:600;">${p.stock_minimo} ${App.escapeHtml(unidad)}</div></div>
                            <div><div class="text-xs text-muted">Estado</div><div>${p.activo == 1 ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-neutral">Inactivo</span>'}</div></div>
                        </div>

                        <h4 style="font-size:14px;margin-bottom:10px;"><i class="bi bi-shop text-primary"></i> Stock por punto de venta</h4>
                        <div class="tabla-wrap mb-4">
                            <table class="tabla">
                                <thead><tr><th>Punto de venta</th><th class="text-right">Stock</th><th>Estado</th></tr></thead>
                                <tbody>
                                    ${p.stock_por_pv.map(s => {
                                        const st = parseInt(s.stock) || 0;
                                        const minimo = parseInt(s.stock_minimo) || 0;
                                        let badge = '<span class="badge badge-success">OK</span>';
                                        if (st < 0) badge = '<span class="badge badge-danger">Negativo</span>';
                                        else if (st <= minimo) badge = '<span class="badge badge-warning">Bajo</span>';
                                        return `
                                            <tr>
                                                <td><strong>${App.escapeHtml(s.pv)}</strong></td>
                                                <td class="text-right"><strong>${st} ${App.escapeHtml(unidad)}</strong></td>
                                                <td>${badge}</td>
                                            </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>

                        <h4 style="font-size:14px;margin-bottom:10px;"><i class="bi bi-clock-history text-warning"></i> Últimos movimientos</h4>
                        ${p.movimientos.length === 0 ? `
                            <p class="text-muted text-sm">Sin movimientos registrados.</p>
                        ` : `
                            <div class="tabla-wrap">
                                <table class="tabla">
                                    <thead><tr><th>Fecha</th><th>Tipo</th><th class="text-right">Cant.</th><th>PV</th><th>Usuario</th><th>Motivo</th></tr></thead>
                                    <tbody>
                                        ${p.movimientos.map(m => {
                                            const badge = {
                                                entrada: '<span class="badge badge-success">Entrada</span>',
                                                salida:  '<span class="badge badge-warning">Salida</span>',
                                                baja:    '<span class="badge badge-danger">Baja</span>',
                                                ajuste:  '<span class="badge badge-info">Ajuste</span>',
                                                transferencia: '<span class="badge badge-neutral">Transf.</span>',
                                            }[m.tipo] || '';
                                            return `
                                                <tr>
                                                    <td class="text-muted text-xs">${App.formatDate(m.fecha)}</td>
                                                    <td>${badge}</td>
                                                    <td class="text-right"><strong>${m.cantidad} ${App.escapeHtml(m.unidad_medida || unidad)}</strong></td>
                                                    <td>${App.escapeHtml(m.pv)}</td>
                                                    <td class="text-muted">${App.escapeHtml(m.usuario)}</td>
                                                    <td class="text-muted text-sm">${App.escapeHtml(m.motivo || m.descripcion || '—')}</td>
                                                </tr>
                                            `;
                                        }).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `}
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.getElementById('${modalId}').remove()">Cerrar</button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    function limpiar() {
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-pv').value = '';
        document.getElementById('filtro-cat').value = '';
        document.getElementById('filtro-estado').value = '';
        cargar();
    }

    document.addEventListener('DOMContentLoaded', () => {
        cargarCatalogos();
        cargarResumen();
        cargar();

        const params = new URLSearchParams(window.location.search);
        const filtro = params.get('filtro');
        if (filtro) {
            document.getElementById('filtro-estado').value = filtro;
            cargar();
        }

        const debounced = App.debounce(cargar, 400);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-pv')?.addEventListener('change', cargar);
        document.getElementById('filtro-cat')?.addEventListener('change', cargar);
        document.getElementById('filtro-estado')?.addEventListener('change', cargar);
    });

    return { cargar, limpiar, verDetalle };
})();

window.Inventario = Inventario;