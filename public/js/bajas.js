/**
 * IPV - Bajas de inventario
 */

const Bajas = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let catalogoPVs = [];
    let catalogoMotivos = [];
    let productoSeleccionado = null;
    let productosMasiva = [];

    // ============================================================
    // CATÁLOGOS
    // ============================================================
    async function cargarCatalogos() {
        const res = await Api.get('api/bajas.php?accion=catalogos');
        if (!res.success) return;

        catalogoPVs = res.data.puntos_venta;
        catalogoMotivos = res.data.motivos;

        const optionsPVs = '<option value="">Selecciona PV</option>' +
            catalogoPVs.map(p => `<option value="${p.id}">${App.escapeHtml(p.nombre)}</option>`).join('');

        document.getElementById('filtro-pv').innerHTML = '<option value="">Todos los PV</option>' +
            catalogoPVs.map(p => `<option value="${p.id}">${App.escapeHtml(p.nombre)}</option>`).join('');

        document.getElementById('b-pv').innerHTML = optionsPVs;
        document.getElementById('m-pv').innerHTML = optionsPVs;

        const optionsMotivos = '<option value="">Selecciona motivo</option>' +
            catalogoMotivos.map(m => `<option value="${m}">${App.escapeHtml(m)}</option>`).join('');

        document.getElementById('b-motivo').innerHTML = optionsMotivos;
        document.getElementById('m-motivo').innerHTML = optionsMotivos;
    }

    // ============================================================
    // LISTAR BAJAS
    // ============================================================
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

        const cont = document.getElementById('tabla-bajas');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/bajas.php?accion=listar&${params}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        document.getElementById('total-bajas').textContent = res.data.length;
        render(res.data);
    }

    function render(lista) {
        const cont = document.getElementById('tabla-bajas');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>Sin bajas</h3>
                <p>No hay bajas registradas con los filtros actuales.</p>
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
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">Stock</th>
                            <th>Motivo</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(m => {
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
                                    <td class="text-right">
                                        <span class="badge badge-danger">
                                            <i class="bi bi-arrow-down"></i> -${m.cantidad} ${App.escapeHtml(unidad)}
                                        </span>
                                    </td>
                                    <td class="text-right text-sm">
                                        <span class="text-muted">${m.valor_anterior}</span>
                                        <i class="bi bi-arrow-right text-xs"></i>
                                        <strong style="${parseInt(m.valor_nuevo) < 0 ? 'color:var(--danger);' : ''}">${m.valor_nuevo}</strong>
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

    // ============================================================
    // MODAL INDIVIDUAL
    // ============================================================
    function abrirNueva() {
        productoSeleccionado = null;
        document.getElementById('b-pv').value = '';
        document.getElementById('b-buscar').value = '';
        document.getElementById('b-cantidad').value = '1';
        document.getElementById('b-motivo').value = '';
        document.getElementById('b-descripcion').value = '';
        document.getElementById('b-resultados-wrap').style.display = 'none';
        document.getElementById('b-seleccionado-wrap').style.display = 'none';
        document.getElementById('b-producto-id').value = '';
        document.getElementById('b-stock-help').textContent = '';

        document.getElementById('modal-baja').classList.add('active');
    }

    function cerrarModal() {
        document.getElementById('modal-baja').classList.remove('active');
    }

    // ============================================================
    // BUSCAR PRODUCTO
    // ============================================================
    const buscarProductoDebounced = App.debounce(async () => {
        const pvId = document.getElementById('b-pv').value;
        const q = document.getElementById('b-buscar').value.trim();

        if (!pvId || q.length < 2) {
            document.getElementById('b-resultados-wrap').style.display = 'none';
            return;
        }

        const res = await Api.get(`api/bajas.php?accion=productos_por_pv&pv_id=${pvId}&q=${encodeURIComponent(q)}`);
        if (!res.success || !res.data.length) {
            document.getElementById('b-resultados-wrap').style.display = 'none';
            return;
        }

        const cont = document.getElementById('b-resultados');
        cont.innerHTML = res.data.slice(0, 20).map(p => `
            <div onclick="Bajas.seleccionarProducto(${p.id})"
                 style="padding:12px 16px;cursor:pointer;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;transition:background .15s;"
                 onmouseover="this.style.background='var(--surface-2)'"
                 onmouseout="this.style.background=''">
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:13px;">${App.escapeHtml(p.nombre)}</div>
                    <div class="text-xs text-muted">${App.escapeHtml(p.codigo_barras || 'Sin código')} · Stock: ${p.stock} ${App.escapeHtml(p.unidad_medida || 'Unidad')}</div>
                </div>
                <i class="bi bi-dash-circle text-danger"></i>
            </div>
        `).join('');

        document.getElementById('b-resultados-wrap').style.display = '';
    }, 300);

    function buscarProducto() {
        buscarProductoDebounced();
    }

    async function seleccionarProducto(id) {
        const pvId = document.getElementById('b-pv').value;
        const res = await Api.get(`api/bajas.php?accion=productos_por_pv&pv_id=${pvId}`);
        if (!res.success) return;

        const producto = res.data.find(p => p.id == id);
        if (!producto) return;

        const unidad = producto.unidad_medida || 'Unidad';
        productoSeleccionado = producto;
        document.getElementById('b-producto-id').value = producto.id;
        document.getElementById('b-sel-nombre').textContent = producto.nombre;
        document.getElementById('b-sel-info').textContent = `${producto.codigo_barras || 'Sin código'} · Stock actual: ${producto.stock} ${unidad}`;
        document.getElementById('b-stock-help').textContent = `Stock actual: ${producto.stock} ${unidad} → quedará en ${parseInt(producto.stock) - parseInt(document.getElementById('b-cantidad').value || 1)} ${unidad}`;
        document.getElementById('b-seleccionado-wrap').style.display = '';
        document.getElementById('b-resultados-wrap').style.display = 'none';
        document.getElementById('b-buscar').value = '';
    }

    function limpiarProducto() {
        productoSeleccionado = null;
        document.getElementById('b-producto-id').value = '';
        document.getElementById('b-seleccionado-wrap').style.display = 'none';
        document.getElementById('b-buscar').value = '';
        document.getElementById('b-stock-help').textContent = '';
    }

    // ============================================================
    // GUARDAR BAJA INDIVIDUAL
    // ============================================================
    async function guardar() {
        const pvId = parseInt(document.getElementById('b-pv').value);
        const productoId = parseInt(document.getElementById('b-producto-id').value);
        const cantidad = parseInt(document.getElementById('b-cantidad').value);
        const motivo = document.getElementById('b-motivo').value;
        const descripcion = document.getElementById('b-descripcion').value.trim();

        if (!pvId) { Toast.warning('Falta PV', 'Selecciona un punto de venta'); return; }
        if (!productoId) { Toast.warning('Falta producto', 'Selecciona un producto'); return; }
        if (!cantidad || cantidad < 1) { Toast.warning('Cantidad inválida', 'Debe ser al menos 1'); return; }
        if (!motivo) { Toast.warning('Falta motivo', 'Selecciona un motivo'); return; }
        if (descripcion.length < 3) { Toast.warning('Falta descripción', 'La descripción es obligatoria'); return; }

        const unidad = productoSeleccionado?.unidad_medida || 'Unidad';

        if (productoSeleccionado) {
            const stockNuevo = parseInt(productoSeleccionado.stock) - cantidad;
            if (stockNuevo < 0) {
                const ok = await App.confirmar(
                    `El stock quedará en NEGATIVO (${stockNuevo} ${unidad}). ¿Continuar?`,
                    '⚠️ Stock negativo'
                );
                if (!ok) return;
            }
        }

        const btn = document.getElementById('btn-guardar-baja');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Registrando...';

        const res = await Api.post('api/bajas.php?accion=registrar', {
            producto_id: productoId,
            punto_venta_id: pvId,
            cantidad, motivo, descripcion,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Registrar baja';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        if (res.data.alerta) {
            Toast.warning('Atención', res.data.alerta);
        }
        Toast.success('Baja registrada', `Nuevo stock: ${res.data.stock_nuevo} ${unidad}`);
        cerrarModal();
        cargar();
    }

    // ============================================================
    // CARGA MASIVA
    // ============================================================
    function abrirMasiva() {
        document.getElementById('m-pv').value = '';
        document.getElementById('m-motivo').value = '';
        document.getElementById('m-descripcion').value = '';
        document.getElementById('m-buscar').value = '';
        document.getElementById('m-tabla').innerHTML = `
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p class="text-muted">Selecciona un punto de venta para cargar los productos</p>
            </div>
        `;
        productosMasiva = [];
        document.getElementById('modal-masiva').classList.add('active');
    }

    function cerrarMasiva() {
        document.getElementById('modal-masiva').classList.remove('active');
    }

    async function cargarProductosMasiva() {
        const pvId = document.getElementById('m-pv').value;
        const q = document.getElementById('m-buscar').value.trim();

        if (!pvId) return;

        const params = new URLSearchParams();
        params.append('pv_id', pvId);
        if (q) params.append('q', q);

        const cont = document.getElementById('m-tabla');
        cont.innerHTML = '<div class="empty-state"><div class="spinner"></div></div>';

        const res = await Api.get(`api/bajas.php?accion=productos_por_pv&${params}`);
        if (!res.success) {
            cont.innerHTML = `<div class="alert alert-danger">${res.message}</div>`;
            return;
        }

        productosMasiva = res.data;

        const cantidadesGuardadas = {};
        document.querySelectorAll('[data-cantidad-id]').forEach(inp => {
            const id = inp.dataset.cantidadId;
            const val = parseInt(inp.value) || 0;
            if (val > 0) cantidadesGuardadas[id] = val;
        });

        renderMasiva(cantidadesGuardadas);
    }

    function renderMasiva(cantidadesGuardadas = {}) {
        const cont = document.getElementById('m-tabla');

        if (!productosMasiva.length) {
            cont.innerHTML = `<div class="empty-state"><i class="bi bi-inbox"></i><p>Sin productos con stock</p></div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap" style="max-height:450px;overflow-y:auto;">
                <table class="tabla">
                    <thead style="position:sticky;top:0;background:var(--surface-2);z-index:1;">
                        <tr>
                            <th>Producto</th>
                            <th class="text-right" style="width:100px;">Stock actual</th>
                            <th class="text-right" style="width:120px;">Cantidad a restar</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${productosMasiva.map(p => {
                            const cantGuardada = cantidadesGuardadas[p.id] || '';
                            const unidad = p.unidad_medida || 'Unidad';
                            return `
                                <tr>
                                    <td>
                                        <div style="font-weight:600;font-size:13px;">${App.escapeHtml(p.nombre)}</div>
                                        <div class="text-xs text-muted">${App.escapeHtml(p.codigo_barras || 'Sin código')} · ${App.escapeHtml(unidad)}</div>
                                    </td>
                                    <td class="text-right"><strong>${p.stock} ${App.escapeHtml(unidad)}</strong></td>
                                    <td class="text-right">
                                        <input type="number" min="0" value="${cantGuardada}" placeholder="0"
                                               data-cantidad-id="${p.id}"
                                               oninput="Bajas.actualizarTotal()"
                                               style="width:90px;text-align:center;font-weight:600;padding:6px 8px;border:1px solid var(--border);border-radius:6px;">
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;

        actualizarTotal();
    }

    function actualizarTotal() {
        let total = 0;
        document.querySelectorAll('[data-cantidad-id]').forEach(inp => {
            const val = parseInt(inp.value) || 0;
            if (val > 0) total++;
        });
        document.getElementById('m-total-items').textContent = total;
    }

    function limpiarCantidades() {
        document.querySelectorAll('[data-cantidad-id]').forEach(inp => {
            inp.value = '';
        });
        actualizarTotal();
    }

    async function guardarMasiva() {
        const pvId = parseInt(document.getElementById('m-pv').value);
        const motivo = document.getElementById('m-motivo').value;
        const descripcion = document.getElementById('m-descripcion').value.trim();

        if (!pvId) { Toast.warning('Falta PV', 'Selecciona un punto de venta'); return; }
        if (!motivo) { Toast.warning('Falta motivo', 'Selecciona un motivo'); return; }
        if (descripcion.length < 3) { Toast.warning('Falta descripción', 'Obligatoria en carga masiva'); return; }

        const items = [];
        document.querySelectorAll('[data-cantidad-id]').forEach(inp => {
            const productoId = parseInt(inp.dataset.cantidadId);
            const cantidad = parseInt(inp.value) || 0;
            if (cantidad > 0) items.push({ producto_id: productoId, cantidad });
        });

        if (items.length === 0) {
            Toast.warning('Sin cantidades', 'Ingresa al menos una cantidad');
            return;
        }

        const ok = await App.confirmar(
            `¿Registrar ${items.length} baja(s) por un total de ${items.reduce((a, b) => a + b.cantidad, 0)} unidades?`,
            '⚠️ Confirmar bajas masivas'
        );
        if (!ok) return;

        const btn = document.getElementById('btn-guardar-masiva');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Procesando...';

        const res = await Api.post('api/bajas.php?accion=registrar_masivo', {
            punto_venta_id: pvId,
            motivo, descripcion, items,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Aplicar bajas';

        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Bajas registradas', `${res.data.procesados} producto(s) actualizado(s)`);
        if (res.data.errores && res.data.errores.length) {
            Toast.warning('Algunos errores', res.data.errores.join('<br>'));
        }

        cerrarMasiva();
        cargar();
    }

    // ============================================================
    // FILTROS
    // ============================================================
    function limpiar() {
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-pv').value = '';
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
        document.getElementById('filtro-pv')?.addEventListener('change', cargar);
        document.getElementById('filtro-desde')?.addEventListener('change', cargar);
        document.getElementById('filtro-hasta')?.addEventListener('change', cargar);

        document.getElementById('b-cantidad')?.addEventListener('input', () => {
            if (productoSeleccionado) {
                const cant = parseInt(document.getElementById('b-cantidad').value) || 0;
                const unidad = productoSeleccionado.unidad_medida || 'Unidad';
                document.getElementById('b-stock-help').textContent = `Stock actual: ${productoSeleccionado.stock} ${unidad} → quedará en ${parseInt(productoSeleccionado.stock) - cant} ${unidad}`;
            }
        });
    });

    return {
        cargar, limpiar,
        abrirNueva, cerrarModal, buscarProducto, seleccionarProducto, limpiarProducto, guardar,
        abrirMasiva, cerrarMasiva, cargarProductosMasiva, limpiarCantidades, actualizarTotal, guardarMasiva,
    };
})();

window.Bajas = Bajas;