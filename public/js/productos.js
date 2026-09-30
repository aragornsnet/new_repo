/**
 * IPV - Gestión de Productos (Admin)
 * El admin NO crea productos. Solo edita precio, costo, stock mínimo,
 * categoría, unidad, nombre, código, descripción y estado.
 */

const Productos = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let lista = [];
    let modoEdicion = false;
    let precioOriginal = 0;

    async function cargar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const cat = document.getElementById('filtro-cat')?.value || '';
        const estado = document.getElementById('filtro-estado')?.value || '';
        const stock = document.getElementById('filtro-stock')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (cat) params.append('categoria_id', cat);
        if (estado) params.append('estado', estado);
        if (stock) params.append('stock_bajo', stock);

        const cont = document.getElementById('tabla-prod');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/productos.php?accion=listar&${params}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            cont.innerHTML = `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        lista = res.data;
        document.getElementById('total-prod').textContent = lista.length;
        render();
    }

    function render() {
        const cont = document.getElementById('tabla-prod');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-box-seam"></i>
                <h3>Sin productos</h3>
                <p>Los productos se crean desde el módulo de Almacén.</p>
                <a href="${BASE_URL}views/almacen/entradas.php?nuevo=1" class="btn btn-primary btn-sm mt-3">
                    <i class="bi bi-plus-lg"></i> Ir a crear producto
                </a>
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Unidad</th>
                            <th class="text-right">Precio</th>
                            <th class="text-right">Costo</th>
                            <th class="text-right">Stock total</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(p => {
                            const activo = p.activo == 1;
                            const stockTotal = parseInt(p.stock_total) || 0;
                            const tieneAlerta = parseInt(p.alertas_bajo) > 0;
                            const tieneNegativo = parseInt(p.alertas_negativo) > 0;
                            const unidad = p.unidad_medida || 'Unidad';

                            let stockBadge = '';
                            if (tieneNegativo) stockBadge = `<span class="badge badge-danger"><i class="bi bi-x-octagon-fill"></i> ${stockTotal} ${App.escapeHtml(unidad)}</span>`;
                            else if (tieneAlerta) stockBadge = `<span class="badge badge-warning"><i class="bi bi-exclamation-triangle-fill"></i> ${stockTotal} ${App.escapeHtml(unidad)}</span>`;
                            else stockBadge = `<span class="badge badge-success">${stockTotal} ${App.escapeHtml(unidad)}</span>`;

                            const utilidad = p.precio > 0 && p.costo > 0
                                ? ((p.precio - p.costo) / p.precio * 100).toFixed(1) + '%'
                                : '—';

                            return `
                                <tr style="${!activo ? 'opacity:.6;' : ''}">
                                    <td class="text-muted text-xs">${App.escapeHtml(p.codigo_barras || '—')}</td>
                                    <td>
                                        <div style="font-weight:600;">${App.escapeHtml(p.nombre)}</div>
                                        ${p.descripcion ? `<div class="text-xs text-muted">${App.escapeHtml(p.descripcion.substring(0, 60))}${p.descripcion.length > 60 ? '...' : ''}</div>` : ''}
                                    </td>
                                    <td>${p.categoria ? `<span class="badge badge-neutral">${App.escapeHtml(p.categoria)}</span>` : '<span class="text-light">—</span>'}</td>
                                    <td class="text-muted text-sm">${App.escapeHtml(unidad)}</td>
                                    <td class="text-right"><strong>${App.formatMoney(p.precio)}</strong></td>
                                    <td class="text-right text-muted">${App.formatMoney(p.costo)}<div class="text-xs">${utilidad}</div></td>
                                    <td class="text-right">${stockBadge}</td>
                                    <td>${activo ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-neutral">Inactivo</span>'}</td>
                                    <td class="text-right">
                                        <div class="d-flex gap-1" style="justify-content:flex-end;">
                                            <button class="btn btn-ghost btn-icon" onclick="Productos.verDetalle(${p.id})" title="Ver detalle"><i class="bi bi-eye"></i></button>
                                            <button class="btn btn-ghost btn-icon" onclick="Productos.editar(${p.id})" title="Editar"><i class="bi bi-pencil"></i></button>
                                            <button class="btn btn-ghost btn-icon" onclick="Productos.cambiarEstado(${p.id})" title="${activo ? 'Desactivar' : 'Activar'}"><i class="bi bi-${activo ? 'toggle-on' : 'toggle-off'}"></i></button>
                                            <button class="btn btn-ghost btn-icon" onclick="Productos.eliminar(${p.id})" title="Eliminar" style="color:var(--danger);"><i class="bi bi-trash"></i></button>
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

    function abrirNuevo() {
        Toast.info('Crear productos', 'Los productos se crean desde el módulo de Almacén con entrada inicial obligatoria.');
        setTimeout(() => {
            window.location.href = BASE_URL + 'views/almacen/entradas.php?nuevo=1';
        }, 800);
    }

    function editar(id) {
        const p = lista.find(x => x.id == id);
        if (!p) return;

        modoEdicion = true;
        precioOriginal = parseFloat(p.precio);

        document.getElementById('modal-titulo').textContent = 'Editar producto';
        document.getElementById('prod-id').value = p.id;
        document.getElementById('prod-nombre').value = p.nombre;
        document.getElementById('prod-codigo').value = p.codigo_barras || '';
        document.getElementById('prod-descripcion').value = p.descripcion || '';
        document.getElementById('prod-precio').value = p.precio;
        document.getElementById('prod-costo').value = p.costo;
        document.getElementById('prod-stock-min').value = p.stock_minimo;
        document.getElementById('prod-categoria').value = p.categoria_id || '';
        document.getElementById('prod-unidad').value = p.unidad_medida || 'Unidad';
        document.getElementById('prod-activo').checked = p.activo == 1;
        document.getElementById('seccion-precio-cambio').style.display = 'none';

        document.getElementById('modal-prod').classList.add('active');
    }

    function cerrarModal() {
        document.getElementById('modal-prod').classList.remove('active');
    }

    function verificarCambioPrecio() {
        if (!modoEdicion) return;

        const precioActual = parseFloat(document.getElementById('prod-precio').value) || 0;
        const cambio = Math.abs(precioActual - precioOriginal) > 0.001;
        const seccion = document.getElementById('seccion-precio-cambio');
        const aviso = document.getElementById('aviso-precio');

        if (cambio) {
            seccion.style.display = '';
            const diff = precioActual - precioOriginal;
            const pct = precioOriginal > 0 ? (diff / precioOriginal * 100).toFixed(1) : '—';
            const signo = diff > 0 ? '+' : '';
            const clase = diff > 0 ? 'warning' : 'info';

            aviso.className = `alert alert-${clase}`;
            aviso.innerHTML = `<i class="bi bi-info-circle-fill"></i><div>
                El precio cambiará de <strong>${App.formatMoney(precioOriginal)}</strong>
                a <strong>${App.formatMoney(precioActual)}</strong> (${signo}${pct}%).
                <br><small>Quedará registrado en el historial.</small>
            </div>`;
        } else {
            seccion.style.display = 'none';
        }
    }

    async function guardar() {
        if (!modoEdicion) {
            Toast.warning('No permitido', 'Usa el módulo de Almacén para crear productos');
            return;
        }

        const id = document.getElementById('prod-id').value;
        const nombre = document.getElementById('prod-nombre').value.trim();
        const codigo = document.getElementById('prod-codigo').value.trim();
        const descripcion = document.getElementById('prod-descripcion').value.trim();
        const precio = parseFloat(document.getElementById('prod-precio').value);
        const costo = parseFloat(document.getElementById('prod-costo').value) || 0;
        const stockMin = parseInt(document.getElementById('prod-stock-min').value) || 0;
        const categoria = document.getElementById('prod-categoria').value;
        const unidad = document.getElementById('prod-unidad').value;
        const activo = document.getElementById('prod-activo').checked ? 1 : 0;
        const motivo = document.getElementById('prod-motivo').value.trim();

        if (!nombre || isNaN(precio)) {
            Toast.warning('Campos incompletos', 'Nombre y precio son obligatorios');
            return;
        }

        if (!categoria) {
            Toast.warning('Falta categoría', 'Debes seleccionar una categoría');
            document.getElementById('prod-categoria').focus();
            return;
        }

        if (!unidad) {
            Toast.warning('Falta unidad', 'Debes seleccionar una unidad de medida');
            document.getElementById('prod-unidad').focus();
            return;
        }

        const payload = {
            id: parseInt(id),
            nombre,
            codigo_barras: codigo,
            descripcion,
            precio,
            costo,
            stock_minimo: stockMin,
            unidad_medida: unidad,
            categoria_id: categoria,
            activo,
            motivo_cambio_precio: motivo,
        };

        const btn = document.getElementById('btn-guardar-prod');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Guardando...';

        const res = await Api.post('api/productos.php?accion=actualizar', payload);

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Producto actualizado', 'Los cambios se guardaron');

        if (res.data?.precio_cambio) {
            Toast.info('Precio actualizado', `De ${App.formatMoney(res.data.precio_anterior)} a ${App.formatMoney(precio)}`);
        }

        cerrarModal();
        cargar();
    }

    async function verDetalle(id) {
        const res = await Api.get(`api/productos.php?accion=obtener&id=${id}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        const p = res.data;
        const unidad = p.unidad_medida || 'Unidad';
        const modalId = 'modal-detalle-prod';

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal modal-xl">
                    <div class="modal-header">
                        <div class="modal-title"><i class="bi bi-box-seam"></i> ${App.escapeHtml(p.nombre)}</div>
                        <button class="modal-close" onclick="document.getElementById('${modalId}').remove()"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="grid-2 mb-4">
                            <div><div class="text-xs text-muted">Código</div><div style="font-weight:600;">${App.escapeHtml(p.codigo_barras || '—')}</div></div>
                            <div><div class="text-xs text-muted">Categoría</div><div style="font-weight:600;">${App.escapeHtml(p.categoria || 'Sin categoría')}</div></div>
                            <div><div class="text-xs text-muted">Unidad de medida</div><div style="font-weight:600;">${App.escapeHtml(unidad)}</div></div>
                            <div><div class="text-xs text-muted">Precio</div><div style="font-weight:700;font-size:18px;color:var(--primary);">${App.formatMoney(p.precio)}</div></div>
                            <div><div class="text-xs text-muted">Costo</div><div style="font-weight:600;">${App.formatMoney(p.costo)}</div></div>
                            <div><div class="text-xs text-muted">Stock mínimo</div><div style="font-weight:600;">${p.stock_minimo} ${App.escapeHtml(unidad)}</div></div>
                            <div><div class="text-xs text-muted">Estado</div><div>${p.activo == 1 ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-neutral">Inactivo</span>'}</div></div>
                        </div>

                        ${p.descripcion ? `<div class="mb-4"><div class="text-xs text-muted">Descripción</div><div>${App.escapeHtml(p.descripcion)}</div></div>` : ''}

                        <h4 style="font-size:15px;margin-bottom:10px;"><i class="bi bi-shop text-primary"></i> Stock por punto de venta</h4>
                        <div class="tabla-wrap mb-4">
                            <table class="tabla">
                                <thead><tr><th>Punto de venta</th><th class="text-right">Stock</th><th>Estado</th></tr></thead>
                                <tbody>
                                    ${p.stock_por_pv.map(s => {
                                        const stock = parseInt(s.stock);
                                        let badge = '<span class="badge badge-success">OK</span>';
                                        if (stock < 0) badge = '<span class="badge badge-danger">Negativo</span>';
                                        else if (stock <= p.stock_minimo) badge = '<span class="badge badge-warning">Bajo</span>';
                                        return `<tr><td><strong>${App.escapeHtml(s.pv)}</strong></td><td class="text-right"><strong>${stock} ${App.escapeHtml(unidad)}</strong></td><td>${badge}</td></tr>`;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>

                        <h4 style="font-size:15px;margin-bottom:10px;"><i class="bi bi-clock-history text-warning"></i> Historial de precios</h4>
                        ${p.historial_precios.length === 0 ? `<p class="text-muted text-sm">Sin cambios de precio registrados.</p>` : `
                            <div class="tabla-wrap mb-4">
                                <table class="tabla">
                                    <thead><tr><th>Fecha</th><th class="text-right">Anterior</th><th class="text-right">Nuevo</th><th class="text-right">Cambio</th><th>Usuario</th><th>Motivo</th></tr></thead>
                                    <tbody>
                                        ${p.historial_precios.map(h => {
                                            const diff = parseFloat(h.precio_nuevo) - parseFloat(h.precio_anterior);
                                            const signo = diff > 0 ? '+' : '';
                                            const color = diff > 0 ? 'var(--danger)' : (diff < 0 ? 'var(--success)' : 'var(--text-muted)');
                                            return `<tr><td class="text-muted text-xs">${App.formatDate(h.fecha)}</td><td class="text-right">${App.formatMoney(h.precio_anterior)}</td><td class="text-right"><strong>${App.formatMoney(h.precio_nuevo)}</strong></td><td class="text-right" style="color:${color};font-weight:600;">${signo}${App.formatMoney(diff)}</td><td class="text-muted">${App.escapeHtml(h.usuario)}</td><td class="text-muted text-sm">${App.escapeHtml(h.motivo || '—')}</td></tr>`;
                                        }).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `}

                        <h4 style="font-size:15px;margin-bottom:10px;"><i class="bi bi-arrow-left-right text-primary"></i> Movimientos recientes</h4>
                        ${p.movimientos_recientes.length === 0 ? `<p class="text-muted text-sm">Sin movimientos registrados.</p>` : `
                            <div class="tabla-wrap">
                                <table class="tabla">
                                    <thead><tr><th>Fecha</th><th>Tipo</th><th class="text-right">Cantidad</th><th>PV</th><th>Usuario</th><th>Motivo</th></tr></thead>
                                    <tbody>
                                        ${p.movimientos_recientes.map(m => {
                                            const tipoBadge = {
                                                entrada: '<span class="badge badge-success">Entrada</span>',
                                                salida:  '<span class="badge badge-warning">Salida</span>',
                                                baja:    '<span class="badge badge-danger">Baja</span>',
                                                ajuste:  '<span class="badge badge-info">Ajuste</span>',
                                                transferencia: '<span class="badge badge-neutral">Transf.</span>',
                                            }[m.tipo] || '';
                                            return `<tr><td class="text-muted text-xs">${App.formatDate(m.fecha)}</td><td>${tipoBadge}</td><td class="text-right"><strong>${m.cantidad} ${App.escapeHtml(unidad)}</strong></td><td>${App.escapeHtml(m.pv)}</td><td class="text-muted">${App.escapeHtml(m.usuario)}</td><td class="text-muted text-sm">${App.escapeHtml(m.motivo || '—')}</td></tr>`;
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

    async function cambiarEstado(id) {
        const p = lista.find(x => x.id == id);
        if (!p) return;

        const accion = p.activo ? 'desactivar' : 'activar';
        const ok = await App.confirmar(`¿${accion.charAt(0).toUpperCase() + accion.slice(1)} "${p.nombre}"?`);
        if (!ok) return;

        const res = await Api.post('api/productos.php?accion=cambiar_estado', { id });
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }
        Toast.success('Listo', res.message);
        cargar();
    }

    async function eliminar(id) {
        const p = lista.find(x => x.id == id);
        if (!p) return;

        const ok = await App.confirmar(`¿Eliminar "${p.nombre}"? Esta acción no se puede deshacer.`, '⚠️ Eliminar producto');
        if (!ok) return;

        const res = await Api.post('api/productos.php?accion=eliminar', { id });
        if (!res.success) {
            Toast.error('No se puede eliminar', res.message);
            return;
        }
        Toast.success('Eliminado', res.message);
        cargar();
    }

    async function verHistorialGlobal() {
        document.getElementById('modal-historial').classList.add('active');

        const sel = document.getElementById('hist-prod');
        sel.innerHTML = '<option value="">Todos los productos</option>' +
            lista.map(p => `<option value="${p.id}">${App.escapeHtml(p.nombre)}</option>`).join('');

        cargarHistorial();
    }

    function cerrarHistorial() {
        document.getElementById('modal-historial').classList.remove('active');
    }

    async function cargarHistorial() {
        const prodId = document.getElementById('hist-prod').value;
        const desde = document.getElementById('hist-desde').value;
        const hasta = document.getElementById('hist-hasta').value;

        const params = new URLSearchParams();
        if (prodId) params.append('producto_id', prodId);
        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);

        const cont = document.getElementById('hist-contenido');
        cont.innerHTML = '<div class="empty-state"><div class="spinner"></div></div>';

        const res = await Api.get(`api/productos.php?accion=historial_precios&${params}`);
        if (!res.success) {
            cont.innerHTML = `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        const hist = res.data;
        if (!hist.length) {
            cont.innerHTML = `<div class="empty-state"><i class="bi bi-inbox"></i><p>Sin cambios de precio.</p></div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead><tr><th>Fecha</th><th>Producto</th><th class="text-right">Anterior</th><th class="text-right">Nuevo</th><th class="text-right">Cambio</th><th>Usuario</th><th>Motivo</th></tr></thead>
                    <tbody>
                        ${hist.map(h => {
                            const diff = parseFloat(h.precio_nuevo) - parseFloat(h.precio_anterior);
                            const signo = diff > 0 ? '+' : '';
                            const color = diff > 0 ? 'var(--danger)' : (diff < 0 ? 'var(--success)' : 'var(--text-muted)');
                            return `<tr><td class="text-muted text-xs">${App.formatDate(h.fecha)}</td><td><strong>${App.escapeHtml(h.producto)}</strong></td><td class="text-right">${App.formatMoney(h.precio_anterior)}</td><td class="text-right"><strong>${App.formatMoney(h.precio_nuevo)}</strong></td><td class="text-right" style="color:${color};font-weight:600;">${signo}${App.formatMoney(diff)}</td><td class="text-muted">${App.escapeHtml(h.usuario)}</td><td class="text-muted text-sm">${App.escapeHtml(h.motivo || '—')}</td></tr>`;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    document.addEventListener('DOMContentLoaded', () => {
        cargar();

        const debounced = App.debounce(cargar, 400);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-cat')?.addEventListener('change', cargar);
        document.getElementById('filtro-estado')?.addEventListener('change', cargar);
        document.getElementById('filtro-stock')?.addEventListener('change', cargar);

        document.getElementById('prod-precio')?.addEventListener('input', verificarCambioPrecio);
    });

    return {
        cargar,
        abrirNuevo,
        editar,
        cerrarModal,
        guardar,
        verDetalle,
        cambiarEstado,
        eliminar,
        verHistorialGlobal,
        cerrarHistorial,
        cargarHistorial,
    };
})();

window.Productos = Productos;