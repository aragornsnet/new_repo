/**
 * IPV - Ajustes de inventario
 */

const Ajustes = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let catalogoPVs = [];
    let catalogoMotivos = [];
    let productoSeleccionado = null;

    async function cargarCatalogos() {
        const res = await Api.get('api/ajustes.php?accion=catalogos');
        if (!res.success) return;

        catalogoPVs = res.data.puntos_venta;
        catalogoMotivos = res.data.motivos;

        const optionsPVs = '<option value="">Selecciona PV</option>' +
            catalogoPVs.map(p => `<option value="${p.id}">${App.escapeHtml(p.nombre)}</option>`).join('');

        document.getElementById('filtro-pv').innerHTML = '<option value="">Todos los PV</option>' +
            catalogoPVs.map(p => `<option value="${p.id}">${App.escapeHtml(p.nombre)}</option>`).join('');

        document.getElementById('a-pv').innerHTML = optionsPVs;

        const optionsMotivos = '<option value="">Selecciona motivo</option>' +
            catalogoMotivos.map(m => `<option value="${m}">${App.escapeHtml(m)}</option>`).join('');

        document.getElementById('a-motivo').innerHTML = optionsMotivos;
    }

    async function cargar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const pvId = document.getElementById('filtro-pv')?.value || '';
        const desde = document.getElementById('filtro-desde')?.value || '';
        const hasta = document.getElementById('filtro-hasta')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (pvId) params.append('pv_id', pvId);
        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);

        const cont = document.getElementById('tabla-ajustes');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/ajustes.php?accion=listar&${params}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        document.getElementById('total-ajustes').textContent = res.data.length;
        render(res.data);
    }

    function render(lista) {
        const cont = document.getElementById('tabla-ajustes');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>Sin ajustes</h3>
                <p>No hay ajustes registrados con los filtros actuales.</p>
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Unidad</th>
                            <th>Punto de venta</th>
                            <th class="text-right">Cambio</th>
                            <th class="text-right">Stock</th>
                            <th>Motivo</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(m => {
                            const diff = parseInt(m.cantidad);
                            const signo = diff > 0 ? '+' : '';
                            const color = diff > 0 ? 'var(--success)' : 'var(--danger)';
                            const unidad = m.unidad_medida || 'Unidad';
                            return `
                                <tr>
                                    <td class="text-muted text-xs" style="white-space:nowrap;">${App.formatDate(m.fecha)}</td>
                                    <td>
                                        <div style="font-weight:600;">${App.escapeHtml(m.producto)}</div>
                                        <div class="text-xs text-muted">${App.escapeHtml(m.codigo_barras || '—')}</div>
                                    </td>
                                    <td class="text-muted text-sm">${App.escapeHtml(unidad)}</td>
                                    <td>${App.escapeHtml(m.pv)}</td>
                                    <td class="text-right"><strong style="color:${color};">${signo}${diff} ${App.escapeHtml(unidad)}</strong></td>
                                    <td class="text-right text-sm">
                                        <span class="text-muted">${m.valor_anterior}</span>
                                        <i class="bi bi-arrow-right text-xs"></i>
                                        <strong>${m.valor_nuevo}</strong>
                                    </td>
                                    <td class="text-sm">
                                        <div style="font-weight:500;">${App.escapeHtml(m.motivo || '—')}</div>
                                        ${m.descripcion ? `<div class="text-xs text-muted">${App.escapeHtml(m.descripcion.substring(0, 80))}${m.descripcion.length > 80 ? '...' : ''}</div>` : ''}
                                    </td>
                                    <td class="text-muted text-sm">${App.escapeHtml(m.usuario)}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function abrirNuevo() {
        productoSeleccionado = null;
        document.getElementById('a-pv').value = '';
        document.getElementById('a-buscar').value = '';
        document.getElementById('a-nuevo-valor').value = '0';
        document.getElementById('a-motivo').value = '';
        document.getElementById('a-descripcion').value = '';
        document.getElementById('a-resultados-wrap').style.display = 'none';
        document.getElementById('a-seleccionado-wrap').style.display = 'none';
        document.getElementById('a-producto-id').value = '';
        document.getElementById('a-stock-actual').value = '';
        document.getElementById('a-diferencia-wrap').style.display = 'none';

        document.getElementById('modal-ajuste').classList.add('active');
    }

    function cerrarModal() {
        document.getElementById('modal-ajuste').classList.remove('active');
    }

    const buscarProductoDebounced = App.debounce(async () => {
        const pvId = document.getElementById('a-pv').value;
        const q = document.getElementById('a-buscar').value.trim();

        if (!pvId || q.length < 2) {
            document.getElementById('a-resultados-wrap').style.display = 'none';
            return;
        }

        const res = await Api.get(`api/bajas.php?accion=productos_por_pv&pv_id=${pvId}&q=${encodeURIComponent(q)}`);
        if (!res.success || !res.data.length) {
            document.getElementById('a-resultados-wrap').style.display = 'none';
            return;
        }

        const cont = document.getElementById('a-resultados');
        cont.innerHTML = res.data.slice(0, 20).map(p => `
            <div onclick="Ajustes.seleccionarProducto(${p.id})"
                 style="padding:12px 16px;cursor:pointer;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;"
                 onmouseover="this.style.background='var(--surface-2)'"
                 onmouseout="this.style.background=''">
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:13px;">${App.escapeHtml(p.nombre)}</div>
                    <div class="text-xs text-muted">${App.escapeHtml(p.codigo_barras || 'Sin código')} · Stock: ${p.stock} ${App.escapeHtml(p.unidad_medida || 'Unidad')}</div>
                </div>
                <i class="bi bi-wrench text-warning"></i>
            </div>
        `).join('');

        document.getElementById('a-resultados-wrap').style.display = '';
    }, 300);

    function buscarProducto() {
        buscarProductoDebounced();
    }

    async function seleccionarProducto(id) {
        const pvId = document.getElementById('a-pv').value;
        const res = await Api.get(`api/entradas.php?accion=productos_por_pv&pv_id=${pvId}`);
        if (!res.success) return;

        const producto = res.data.find(p => p.id == id);
        if (!producto) return;

        const unidad = producto.unidad_medida || 'Unidad';
        productoSeleccionado = producto;
        document.getElementById('a-producto-id').value = producto.id;
        document.getElementById('a-sel-nombre').textContent = producto.nombre;
        document.getElementById('a-sel-info').textContent = `${producto.codigo_barras || 'Sin código'} · Stock: ${producto.stock} ${unidad}`;
        document.getElementById('a-stock-actual').value = producto.stock;
        document.getElementById('a-nuevo-valor').value = producto.stock;
        document.getElementById('a-seleccionado-wrap').style.display = '';
        document.getElementById('a-resultados-wrap').style.display = 'none';
        document.getElementById('a-buscar').value = '';

        calcularDiferencia();
    }

    function limpiarProducto() {
        productoSeleccionado = null;
        document.getElementById('a-producto-id').value = '';
        document.getElementById('a-seleccionado-wrap').style.display = 'none';
        document.getElementById('a-buscar').value = '';
        document.getElementById('a-stock-actual').value = '';
        document.getElementById('a-diferencia-wrap').style.display = 'none';
    }

    function calcularDiferencia() {
        if (!productoSeleccionado) return;

        const stockActual = parseInt(productoSeleccionado.stock);
        const nuevoValor = parseInt(document.getElementById('a-nuevo-valor').value) || 0;
        const diff = nuevoValor - stockActual;
        const unidad = productoSeleccionado.unidad_medida || 'Unidad';
        const cont = document.getElementById('a-diferencia');

        if (diff === 0) {
            cont.innerHTML = `<div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> El nuevo valor es igual al actual. No hay cambio.</div>`;
        } else if (diff > 0) {
            cont.innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-arrow-up-circle-fill"></i>
                    <div>
                        <strong>Se sumarán ${diff} ${App.escapeHtml(unidad)}</strong><br>
                        <span class="text-sm">Stock pasará de ${stockActual} a ${nuevoValor} ${App.escapeHtml(unidad)}</span>
                    </div>
                </div>
            `;
        } else {
            cont.innerHTML = `
                <div class="alert alert-warning">
                    <i class="bi bi-arrow-down-circle-fill"></i>
                    <div>
                        <strong>Se restarán ${Math.abs(diff)} ${App.escapeHtml(unidad)}</strong><br>
                        <span class="text-sm">Stock pasará de ${stockActual} a ${nuevoValor} ${App.escapeHtml(unidad)}</span>
                    </div>
                </div>
            `;
        }

        document.getElementById('a-diferencia-wrap').style.display = '';
    }

    async function guardar() {
        const pvId = parseInt(document.getElementById('a-pv').value);
        const productoId = parseInt(document.getElementById('a-producto-id').value);
        const nuevoValor = parseInt(document.getElementById('a-nuevo-valor').value);
        const motivo = document.getElementById('a-motivo').value;
        const descripcion = document.getElementById('a-descripcion').value.trim();

        if (!pvId) { Toast.warning('Falta PV', 'Selecciona un punto de venta'); return; }
        if (!productoId) { Toast.warning('Falta producto', 'Selecciona un producto'); return; }
        if (isNaN(nuevoValor) || nuevoValor < 0) { Toast.warning('Valor inválido', 'El nuevo stock debe ser 0 o mayor'); return; }
        if (!motivo) { Toast.warning('Falta motivo', 'Selecciona un motivo'); return; }
        if (descripcion.length < 3) { Toast.warning('Falta descripción', 'La descripción es obligatoria'); return; }

        const stockActual = parseInt(productoSeleccionado.stock);
        const diff = nuevoValor - stockActual;
        const unidad = productoSeleccionado.unidad_medida || 'Unidad';

        if (diff === 0) {
            Toast.info('Sin cambios', 'El nuevo valor es igual al actual');
            return;
        }

        if (Math.abs(diff) > 10) {
            const ok = await App.confirmar(
                `El ajuste cambiará el stock en ${diff > 0 ? '+' : ''}${diff} ${unidad} (${stockActual} → ${nuevoValor}). ¿Estás seguro?`,
                '⚠️ Ajuste grande'
            );
            if (!ok) return;
        }

        const btn = document.getElementById('btn-guardar-ajuste');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Aplicando...';

        const res = await Api.post('api/ajustes.php?accion=registrar', {
            producto_id: productoId,
            punto_venta_id: pvId,
            nuevo_valor: nuevoValor,
            motivo, descripcion,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Aplicar ajuste';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Ajuste aplicado', `Stock: ${res.data.stock_anterior} → ${res.data.stock_nuevo} ${unidad}`);
        cerrarModal();
        cargar();
    }

    function limpiar() {
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-pv').value = '';
        document.getElementById('filtro-desde').value = '';
        document.getElementById('filtro-hasta').value = '';
        cargar();
    }

    document.addEventListener('DOMContentLoaded', () => {
        cargarCatalogos().then(cargar);

        const debounced = App.debounce(cargar, 400);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-pv')?.addEventListener('change', cargar);
        document.getElementById('filtro-desde')?.addEventListener('change', cargar);
        document.getElementById('filtro-hasta')?.addEventListener('change', cargar);
    });

    return {
        cargar, limpiar,
        abrirNuevo, cerrarModal, buscarProducto, seleccionarProducto, limpiarProducto,
        calcularDiferencia, guardar,
    };
})();

window.Ajustes = Ajustes;