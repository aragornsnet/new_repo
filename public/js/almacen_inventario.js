/**
 * IPV - Inventario del Almacén
 * El almacenero NO ve precio ni costo.
 * Incluye: ver detalle, registrar entrada, ajustar stock, editar producto.
 */

const AlmacenInventario = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let lista = [];
    let catalogoMotivosAjuste = [];

    // ═══════════════════════════════════════════════════════════
    // CARGAR
    // ═══════════════════════════════════════════════════════════
    async function cargarCatalogos() {
        const res = await Api.get('api/almacen.php?accion=catalogos');
        if (!res.success) return;

        catalogoMotivosAjuste = res.data.motivos_ajuste || [];

        const select = document.getElementById('ajuste-motivo');
        if (select) {
            select.innerHTML = '<option value="">Selecciona motivo</option>' +
                catalogoMotivosAjuste.map(m => `<option value="${App.escapeHtml(m)}">${App.escapeHtml(m)}</option>`).join('');
        }
    }

    async function cargar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const cat = document.getElementById('filtro-cat')?.value || '';
        const estado = document.getElementById('filtro-estado')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (cat) params.append('categoria_id', cat);
        if (estado) params.append('filtro', estado);

        const cont = document.getElementById('tabla-inventario');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/almacen.php?accion=listar&${params}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            cont.innerHTML = `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        lista = res.data;

        const t1 = document.getElementById('total-items');
        const t2 = document.getElementById('total-items-2');
        if (t1) t1.textContent = lista.length;
        if (t2) t2.textContent = lista.length;

        render();
    }

    // ═══════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════
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
                            <th>Unidad</th>
                            <th class="text-right">Stock</th>
                            <th class="text-right">Mín.</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(item => {
                            const stock = parseInt(item.stock);
                            const min = parseInt(item.stock_minimo);
                            const unidad = item.unidad_medida || 'Unidad';

                            let badge = '<span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> OK</span>';
                            if (item.estado_stock === 'negativo') {
                                badge = '<span class="badge badge-danger"><i class="bi bi-x-octagon-fill"></i> Negativo</span>';
                            } else if (item.estado_stock === 'bajo') {
                                badge = '<span class="badge badge-warning"><i class="bi bi-exclamation-triangle-fill"></i> Bajo</span>';
                            }

                            return `
                                <tr>
                                    <td>
                                        <div style="font-weight:600;">${App.escapeHtml(item.producto)}</div>
                                        <div class="text-xs text-muted">${App.escapeHtml(item.codigo_barras || '—')}</div>
                                    </td>
                                    <td>${item.categoria
                                        ? `<span class="badge badge-neutral">${App.escapeHtml(item.categoria)}</span>`
                                        : '<span class="text-light">—</span>'}</td>
                                    <td class="text-muted text-sm">${App.escapeHtml(unidad)}</td>
                                    <td class="text-right">
                                        <strong style="${stock < 0 ? 'color:var(--danger);' : ''}">${stock}</strong>
                                    </td>
                                    <td class="text-right text-muted">${min} ${App.escapeHtml(unidad)}</td>
                                    <td>${badge}</td>
                                    <td class="text-right">
                                        <div class="d-flex gap-1" style="justify-content:flex-end;">
                                            <button class="btn btn-ghost btn-icon"
                                                    onclick="AlmacenInventario.verDetalle(${item.producto_id})"
                                                    title="Ver detalle">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-ghost btn-icon"
                                                    onclick="AlmacenInventario.abrirEditar(${item.producto_id})"
                                                    title="Editar producto">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-ghost btn-icon"
                                                    onclick="AlmacenInventario.abrirAjustar(${item.producto_id})"
                                                    title="Ajustar stock"
                                                    style="color:var(--warning);">
                                                <i class="bi bi-wrench-adjustable"></i>
                                            </button>
                                            <button class="btn btn-success btn-icon"
                                                    onclick="AlmacenInventario.abrirEntrada(${item.producto_id})"
                                                    title="Registrar entrada">
                                                <i class="bi bi-plus-lg"></i>
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
    async function verDetalle(productoId) {
        const res = await Api.get(`api/almacen.php?accion=obtener&producto_id=${productoId}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        const p = res.data;
        const unidad = p.unidad_medida || 'Unidad';

        document.getElementById('detalle-contenido').innerHTML = `
            <div class="grid-2 mb-4">
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
                    <div class="text-xs text-muted">Stock mínimo</div>
                    <div style="font-weight:600;">${p.stock_minimo} ${App.escapeHtml(unidad)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Stock en almacén</div>
                    <div style="font-weight:700;font-size:18px;color:${parseInt(p.stock_almacen) < 0 ? 'var(--danger)' : 'var(--success)'};">
                        ${p.stock_almacen} ${App.escapeHtml(unidad)}
                    </div>
                </div>
            </div>

            ${p.descripcion ? `<div class="mb-4"><div class="text-xs text-muted">Descripción</div><div>${App.escapeHtml(p.descripcion)}</div></div>` : ''}

            <h4 style="font-size:15px;margin-bottom:10px;">
                <i class="bi bi-clock-history text-warning"></i> Últimos movimientos en el almacén
            </h4>

            ${p.movimientos.length === 0 ? `
                <p class="text-muted text-sm">Sin movimientos registrados.</p>
            ` : `
                <div class="tabla-wrap">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th class="text-right">Cant.</th>
                                <th class="text-right">Stock</th>
                                <th>Motivo</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${p.movimientos.map(m => {
                                const tipos = {
                                    entrada:       '<span class="badge badge-success">Entrada</span>',
                                    salida:        '<span class="badge badge-warning">Salida</span>',
                                    baja:          '<span class="badge badge-danger">Baja</span>',
                                    ajuste:        '<span class="badge badge-info">Ajuste</span>',
                                    transferencia: '<span class="badge badge-neutral">Transf.</span>',
                                };
                                const u = m.unidad_medida || unidad;
                                return `
                                    <tr>
                                        <td class="text-muted text-xs">${App.formatDate(m.fecha)}</td>
                                        <td>${tipos[m.tipo] || m.tipo}</td>
                                        <td class="text-right"><strong>${m.cantidad} ${App.escapeHtml(u)}</strong></td>
                                        <td class="text-right text-sm">
                                            <span class="text-muted">${m.valor_anterior}</span>
                                            <i class="bi bi-arrow-right text-xs"></i>
                                            <strong>${m.valor_nuevo}</strong>
                                        </td>
                                        <td class="text-muted text-sm">${App.escapeHtml(m.motivo || m.descripcion || '—')}</td>
                                        <td class="text-muted text-sm">${App.escapeHtml(m.usuario)}</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            `}
        `;

        document.getElementById('modal-detalle').classList.add('active');
    }

    // ═══════════════════════════════════════════════════════════
    // EDITAR PRODUCTO
    // ═══════════════════════════════════════════════════════════
    function abrirEditar(productoId) {
        const item = lista.find(x => x.producto_id == productoId);
        if (!item) return;

        // Necesitamos más datos (descripción). Hacemos fetch completo.
        Api.get(`api/almacen.php?accion=obtener&producto_id=${productoId}`).then(res => {
            if (!res.success) {
                Toast.error('Error', res.message);
                return;
            }

            const p = res.data;
            document.getElementById('edit-id').value = p.id;
            document.getElementById('edit-nombre').value = p.nombre;
            document.getElementById('edit-codigo').value = p.codigo_barras || '';
            document.getElementById('edit-categoria').value = p.categoria_id || '';
            document.getElementById('edit-unidad').value = p.unidad_medida || 'Unidad';
            document.getElementById('edit-stock-min').value = p.stock_minimo;
            document.getElementById('edit-descripcion').value = p.descripcion || '';

            document.getElementById('modal-editar-producto').classList.add('active');
        });
    }

    async function guardarEdicion() {
        const id = parseInt(document.getElementById('edit-id').value);
        const nombre = document.getElementById('edit-nombre').value.trim();
        const codigo = document.getElementById('edit-codigo').value.trim();
        const categoria = document.getElementById('edit-categoria').value;
        const unidad = document.getElementById('edit-unidad').value;
        const stockMin = parseInt(document.getElementById('edit-stock-min').value) || 0;
        const descripcion = document.getElementById('edit-descripcion').value.trim();

        if (!nombre || nombre.length < 2) {
            Toast.warning('Falta nombre', 'El nombre es obligatorio');
            return;
        }
        if (!categoria) {
            Toast.warning('Falta categoría', 'Selecciona una categoría');
            return;
        }
        if (!unidad) {
            Toast.warning('Falta unidad', 'Selecciona una unidad de medida');
            return;
        }

        const btn = document.getElementById('btn-guardar-edicion');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Guardando...';

        const res = await Api.post('api/almacen.php?accion=actualizar_producto', {
            id,
            nombre,
            codigo_barras: codigo,
            descripcion,
            stock_minimo: stockMin,
            unidad_medida: unidad,
            categoria_id: categoria,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Producto actualizado', 'Los cambios se guardaron');
        document.getElementById('modal-editar-producto').classList.remove('active');
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // AJUSTAR STOCK
    // ═══════════════════════════════════════════════════════════
    function abrirAjustar(productoId) {
        const item = lista.find(x => x.producto_id == productoId);
        if (!item) return;

        const unidad = item.unidad_medida || 'Unidad';

        document.getElementById('ajuste-producto-id').value = item.producto_id;
        document.getElementById('ajuste-producto-nombre').textContent = item.producto;
        document.getElementById('ajuste-producto-stock').textContent =
            `Stock actual: ${item.stock} ${unidad}`;
        document.getElementById('ajuste-nuevo-stock').value = item.stock;

        document.getElementById('modal-ajustar-stock').classList.add('active');
        setTimeout(() => document.getElementById('ajuste-nuevo-stock')?.select(), 100);
    }

    async function guardarAjuste() {
        const productoId = parseInt(document.getElementById('ajuste-producto-id').value);
        const nuevoStock = parseInt(document.getElementById('ajuste-nuevo-stock').value);
        const motivo = document.getElementById('ajuste-motivo').value;

        if (isNaN(nuevoStock) || nuevoStock < 0) {
            Toast.warning('Valor inválido', 'El stock debe ser 0 o mayor');
            return;
        }
        if (!motivo) {
            Toast.warning('Falta motivo', 'Selecciona un motivo');
            return;
        }

        const item = lista.find(x => x.producto_id == productoId);
        const unidad = item?.unidad_medida || 'Unidad';
        const diff = nuevoStock - (item ? parseInt(item.stock) : 0);

        if (diff === 0) {
            Toast.info('Sin cambios', 'El nuevo stock es igual al actual');
            return;
        }

        // Confirmar si es una reducción grande
        if (diff < 0 && Math.abs(diff) > 10) {
            const ok = await App.confirmar(
                `Se reducirá el stock en ${Math.abs(diff)} ${unidad}. ¿Estás seguro?`,
                '⚠️ Reducción grande'
            );
            if (!ok) return;
        }

        const btn = document.getElementById('btn-guardar-ajuste');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Aplicando...';

        const res = await Api.post('api/almacen.php?accion=ajustar_stock', {
            producto_id: productoId,
            nuevo_stock: nuevoStock,
            motivo,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Aplicar ajuste';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Stock ajustado', `${res.data.stock_anterior} → ${res.data.stock_nuevo} ${unidad}`);
        document.getElementById('modal-ajustar-stock').classList.remove('active');
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // ENTRADA RÁPIDA
    // ═══════════════════════════════════════════════════════════
    async function abrirEntrada(productoId) {
        // Redirige a la página de entradas con el producto preseleccionado
        window.location.href = BASE_URL + 'views/almacen/entradas.php?producto_id=' + productoId;
    }

    // ═══════════════════════════════════════════════════════════
    // FILTROS
    // ═══════════════════════════════════════════════════════════
    function limpiar() {
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-cat').value = '';
        document.getElementById('filtro-estado').value = '';
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // EVENTOS
    // ═══════════════════════════════════════════════════════════
    document.addEventListener('DOMContentLoaded', () => {
        cargarCatalogos().then(cargar);

        const debounced = App.debounce(cargar, 400);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-cat')?.addEventListener('change', cargar);
        document.getElementById('filtro-estado')?.addEventListener('change', cargar);

        const urlParams = new URLSearchParams(window.location.search);
        const filtroUrl = urlParams.get('filtro');
        if (filtroUrl) {
            const sel = document.getElementById('filtro-estado');
            if (sel) {
                sel.value = filtroUrl;
                cargar();
            }
        }
    });

    return {
        cargar,
        limpiar,
        verDetalle,
        abrirEditar,
        guardarEdicion,
        abrirAjustar,
        guardarAjuste,
        abrirEntrada,
    };
})();

window.AlmacenInventario = AlmacenInventario;