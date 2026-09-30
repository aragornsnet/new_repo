/**
 * IPV - Entradas de inventario
 */

const Entradas = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let catalogoPVs = [];
    let catalogoMotivos = [];
    let productoSeleccionado = null;
    let productosMasiva = [];

    // ============================================================
    // CATÁLOGOS
    // ============================================================
    async function cargarCatalogos() {
        const res = await Api.get('api/entradas.php?accion=catalogos');
        if (!res.success) return;

        catalogoPVs = res.data.puntos_venta;
        catalogoMotivos = res.data.motivos;

        const optionsPVs = '<option value="">Selecciona PV</option>' +
            catalogoPVs.map(p => `<option value="${p.id}">${App.escapeHtml(p.nombre)}</option>`).join('');

        document.getElementById('filtro-pv').innerHTML = '<option value="">Todos los PV</option>' +
            catalogoPVs.map(p => `<option value="${p.id}">${App.escapeHtml(p.nombre)}</option>`).join('');

        document.getElementById('e-pv').innerHTML = optionsPVs;
        document.getElementById('m-pv').innerHTML = optionsPVs;

        const optionsMotivos = '<option value="">Selecciona motivo</option>' +
            catalogoMotivos.map(m => `<option value="${m}">${App.escapeHtml(m)}</option>`).join('');

        document.getElementById('e-motivo').innerHTML = optionsMotivos;
        document.getElementById('m-motivo').innerHTML = optionsMotivos;
    }

    // ============================================================
    // LISTAR ENTRADAS
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

        const cont = document.getElementById('tabla-entradas');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/entradas.php?accion=listar&${params}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        document.getElementById('total-entradas').textContent = res.data.length;
        render(res.data);
    }

    function render(lista) {
        const cont = document.getElementById('tabla-entradas');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>Sin entradas</h3>
                <p>No hay entradas registradas con los filtros actuales.</p>
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
                            <th>Punto de venta</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">Stock</th>
                            <th>Motivo</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(m => `
                            <tr>
                                <td class="text-muted text-xs" style="white-space:nowrap;">${App.formatDate(m.fecha)}</td>
                                <td>
                                    <div style="font-weight:600;">${App.escapeHtml(m.producto)}</div>
                                    <div class="text-xs text-muted">${App.escapeHtml(m.codigo_barras || '—')}</div>
                                </td>
                                <td>${App.escapeHtml(m.pv)}</td>
                                <td class="text-right">
                                    <span class="badge badge-success">
                                        <i class="bi bi-arrow-up"></i> +${m.cantidad}
                                    </span>
                                </td>
                                <td class="text-right text-sm">
                                    <span class="text-muted">${m.valor_anterior}</span>
                                    <i class="bi bi-arrow-right text-xs"></i>
                                    <strong>${m.valor_nuevo}</strong>
                                </td>
                                <td class="text-muted text-sm">${App.escapeHtml(m.motivo || '—')}</td>
                                <td class="text-muted text-sm">${App.escapeHtml(m.usuario)}</td>
                            </tr>
                        `).join('')}
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
        document.getElementById('e-pv').value = '';
        document.getElementById('e-buscar').value = '';
        document.getElementById('e-cantidad').value = '1';
        document.getElementById('e-motivo').value = '';
        document.getElementById('e-referencia').value = '';
        document.getElementById('e-descripcion').value = '';
        document.getElementById('e-resultados-wrap').style.display = 'none';
        document.getElementById('e-seleccionado-wrap').style.display = 'none';
        document.getElementById('e-producto-id').value = '';
        document.getElementById('e-stock-help').textContent = '';

        document.getElementById('modal-entrada').classList.add('active');
    }

    function cerrarModal() {
        document.getElementById('modal-entrada').classList.remove('active');
    }

    // ============================================================
    // BUSCAR PRODUCTO (con debounce)
    // ============================================================
    const buscarProductoDebounced = App.debounce(async () => {
        const pvId = document.getElementById('e-pv').value;
        const q = document.getElementById('e-buscar').value.trim();

        if (!pvId) {
            document.getElementById('e-resultados-wrap').style.display = 'none';
            return;
        }

        if (q.length < 2) {
            document.getElementById('e-resultados-wrap').style.display = 'none';
            return;
        }

        const res = await Api.get(`api/entradas.php?accion=productos_por_pv&pv_id=${pvId}&q=${encodeURIComponent(q)}`);
        if (!res.success || !res.data.length) {
            document.getElementById('e-resultados-wrap').style.display = 'none';
            return;
        }

        const cont = document.getElementById('e-resultados');
        cont.innerHTML = res.data.slice(0, 20).map(p => `
            <div onclick="Entradas.seleccionarProducto(${p.id})"
                 style="padding:12px 16px;cursor:pointer;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;transition:background .15s;"
                 onmouseover="this.style.background='var(--surface-2)'"
                 onmouseout="this.style.background=''">
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:13px;">${App.escapeHtml(p.nombre)}</div>
                    <div class="text-xs text-muted">${App.escapeHtml(p.codigo_barras || 'Sin código')} · Stock: ${p.stock}</div>
                </div>
                <i class="bi bi-plus-circle text-primary"></i>
            </div>
        `).join('');

        document.getElementById('e-resultados-wrap').style.display = '';
    }, 300);

    function buscarProducto() {
        buscarProductoDebounced();
    }

    async function seleccionarProducto(id) {
        const pvId = document.getElementById('e-pv').value;
        const res = await Api.get(`api/entradas.php?accion=productos_por_pv&pv_id=${pvId}`);
        if (!res.success) return;

        const producto = res.data.find(p => p.id == id);
        if (!producto) return;

        productoSeleccionado = producto;
        document.getElementById('e-producto-id').value = producto.id;
        document.getElementById('e-sel-nombre').textContent = producto.nombre;
        document.getElementById('e-sel-info').textContent = `${producto.codigo_barras || 'Sin código'} · Stock actual: ${producto.stock}`;
        document.getElementById('e-stock-help').textContent = `Stock actual: ${producto.stock} → quedará en ${parseInt(producto.stock) + parseInt(document.getElementById('e-cantidad').value || 1)}`;
        document.getElementById('e-seleccionado-wrap').style.display = '';
        document.getElementById('e-resultados-wrap').style.display = 'none';
        document.getElementById('e-buscar').value = '';
    }

    function limpiarProducto() {
        productoSeleccionado = null;
        document.getElementById('e-producto-id').value = '';
        document.getElementById('e-seleccionado-wrap').style.display = 'none';
        document.getElementById('e-buscar').value = '';
        document.getElementById('e-stock-help').textContent = '';
    }

    // ============================================================
    // GUARDAR ENTRADA
    // ============================================================
    async function guardar() {
        const pvId = parseInt(document.getElementById('e-pv').value);
        const productoId = parseInt(document.getElementById('e-producto-id').value);
        const cantidad = parseInt(document.getElementById('e-cantidad').value);
        const motivo = document.getElementById('e-motivo').value;
        const referencia = document.getElementById('e-referencia').value.trim();
        const descripcion = document.getElementById('e-descripcion').value.trim();

        if (!pvId) { Toast.warning('Falta PV', 'Selecciona un punto de venta'); return; }
        if (!productoId) { Toast.warning('Falta producto', 'Selecciona un producto'); return; }
        if (!cantidad || cantidad < 1) { Toast.warning('Cantidad inválida', 'Debe ser al menos 1'); return; }
        if (!motivo) { Toast.warning('Falta motivo', 'Selecciona un motivo'); return; }

        const btn = document.getElementById('btn-guardar-entrada');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Registrando...';

        const res = await Api.post('api/entradas.php?accion=registrar', {
            producto_id: productoId,
            punto_venta_id: pvId,
            cantidad, motivo, referencia, descripcion,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Registrar entrada';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Entrada registrada', `Nuevo stock: ${res.data.stock_nuevo}`);
        cerrarModal();
        cargar();
    }

    // ============================================================
    // CARGA MASIVA
    // ============================================================
    function abrirMasiva() {
        document.getElementById('m-pv').value = '';
        document.getElementById('m-motivo').value = '';
        document.getElementById('m-referencia').value = '';
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

        const res = await Api.get(`api/entradas.php?accion=productos_por_pv&${params}`);
        if (!res.success) {
            cont.innerHTML = `<div class="alert alert-danger">${res.message}</div>`;
            return;
        }

        productosMasiva = res.data;

        // Preservar cantidades ya ingresadas
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
            cont.innerHTML = `<div class="empty-state"><i class="bi bi-inbox"></i><p>Sin productos</p></div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap" style="max-height:450px;overflow-y:auto;">
                <table class="tabla">
                    <thead style="position:sticky;top:0;background:var(--surface-2);z-index:1;">
                        <tr>
                            <th>Producto</th>
                            <th class="text-right" style="width:100px;">Stock actual</th>
                            <th class="text-right" style="width:120px;">Cantidad a sumar</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${productosMasiva.map(p => {
                            const cantGuardada = cantidadesGuardadas[p.id] || '';
                            return `
                                <tr>
                                    <td>
                                        <div style="font-weight:600;font-size:13px;">${App.escapeHtml(p.nombre)}</div>
                                        <div class="text-xs text-muted">${App.escapeHtml(p.codigo_barras || 'Sin código')}</div>
                                    </td>
                                    <td class="text-right"><strong>${p.stock}</strong></td>
                                    <td class="text-right">
                                        <input type="number" min="0" value="${cantGuardada}" placeholder="0"
                                               data-cantidad-id="${p.id}"
                                               oninput="Entradas.actualizarTotal()"
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
        const referencia = document.getElementById('m-referencia').value.trim();

        if (!pvId) { Toast.warning('Falta PV', 'Selecciona un punto de venta'); return; }
        if (!motivo) { Toast.warning('Falta motivo', 'Selecciona un motivo'); return; }

        const items = [];
        document.querySelectorAll('[data-cantidad-id]').forEach(inp => {
            const productoId = parseInt(inp.dataset.cantidadId);
            const cantidad = parseInt(inp.value) || 0;
            if (cantidad > 0) {
                items.push({ producto_id: productoId, cantidad });
            }
        });

        if (items.length === 0) {
            Toast.warning('Sin cantidades', 'Ingresa al menos una cantidad mayor a 0');
            return;
        }

        const ok = await App.confirmar(
            `¿Registrar ${items.length} entrada(s) por un total de ${items.reduce((a, b) => a + b.cantidad, 0)} unidades?`,
            'Confirmar carga masiva'
        );
        if (!ok) return;

        const btn = document.getElementById('btn-guardar-masiva');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Procesando...';

        const res = await Api.post('api/entradas.php?accion=registrar_masivo', {
            punto_venta_id: pvId,
            motivo, referencia, items,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Aplicar entradas';

        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Entradas registradas', `${res.data.procesados} producto(s) actualizado(s)`);
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

        // Actualizar stock help en el modal
        document.getElementById('e-cantidad')?.addEventListener('input', () => {
            if (productoSeleccionado) {
                const cant = parseInt(document.getElementById('e-cantidad').value) || 0;
                document.getElementById('e-stock-help').textContent = `Stock actual: ${productoSeleccionado.stock} → quedará en ${parseInt(productoSeleccionado.stock) + cant}`;
            }
        });
    });

    return {
        cargar, limpiar,
        abrirNueva, cerrarModal, buscarProducto, seleccionarProducto, limpiarProducto, guardar,
        abrirMasiva, cerrarMasiva, cargarProductosMasiva, limpiarCantidades, actualizarTotal, guardarMasiva,
    };
})();

window.Entradas = Entradas;