/**
 * IPV - Solicitudes de Traslado (Supervisor)
 */

const SupervisorSolicitudes = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let lista = [];
    let solicitudActual = null;
    let productosDisponibles = [];
    let carrito = {};

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

        const cont = document.getElementById('tabla-solicitudes');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/solicitudes.php?accion=listar&${params}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            cont.innerHTML = `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        lista = res.data.datos;

        const t1 = document.getElementById('total-solicitudes');
        const t2 = document.getElementById('total-solicitudes-2');
        if (t1) t1.textContent = lista.length;
        if (t2) t2.textContent = lista.length;

        render();
    }

    function render() {
        const cont = document.getElementById('tabla-solicitudes');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>Sin solicitudes</h3>
                <p>No hay solicitudes que coincidan con los filtros.</p>
                <button class="btn btn-primary btn-sm mt-3" onclick="SupervisorSolicitudes.abrirNueva()">
                    <i class="bi bi-plus-lg"></i> Crear primera solicitud
                </button>
            </div>`;
            return;
        }

        const badges = {
            solicitado:         ['warning', 'Solicitado'],
            aprobado:           ['info',    'Aprobado'],
            despachado:         ['primary', 'Despachado'],
            despachado_parcial: ['warning', 'Parcial'],
            recibido:           ['success', 'Recibido'],
            rechazado:          ['danger',  'Rechazado'],
            cancelado:          ['neutral', 'Cancelado'],
        };

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Fecha</th>
                            <th>PV</th>
                            <th>Motivo</th>
                            <th class="text-right">Items</th>
                            <th class="text-right">Unidades</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(s => {
                            const [color, label] = badges[s.estado] || ['neutral', s.estado];
                            return `
                                <tr>
                                    <td><strong>${App.escapeHtml(s.folio)}</strong></td>
                                    <td class="text-muted text-xs">${App.formatDate(s.fecha_solicitud)}</td>
                                    <td>${App.escapeHtml(s.pv)}</td>
                                    <td class="text-muted text-sm">${App.escapeHtml(s.motivo || '—')}</td>
                                    <td class="text-right">${s.total_items}</td>
                                    <td class="text-right">
                                        <strong>${s.total_unidades_solicitadas}</strong>
                                        ${s.total_unidades_recibidas > 0
                                            ? `<div class="text-xs text-success">rec: ${s.total_unidades_recibidas}</div>`
                                            : s.total_unidades_despachadas > 0
                                                ? `<div class="text-xs text-info">desp: ${s.total_unidades_despachadas}</div>`
                                                : ''}
                                    </td>
                                    <td><span class="badge badge-${color}">${label}</span></td>
                                    <td class="text-right">
                                        <button class="btn btn-ghost btn-icon"
                                                onclick="SupervisorSolicitudes.verDetalle(${s.id})"
                                                title="Ver detalle">
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
        const res = await Api.get(`api/solicitudes.php?accion=obtener&id=${id}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        solicitudActual = res.data;
        const s = solicitudActual;

        document.getElementById('det-titulo').textContent = `Solicitud ${s.folio}`;

        const badges = {
            solicitado:         ['warning', 'Solicitado'],
            aprobado:           ['info',    'Aprobado'],
            despachado:         ['primary', 'Despachado'],
            despachado_parcial: ['warning', 'Despachado parcial'],
            recibido:           ['success', 'Recibido'],
            rechazado:          ['danger',  'Rechazado'],
            cancelado:          ['neutral', 'Cancelado'],
        };
        const [color, label] = badges[s.estado] || ['neutral', s.estado];

        let html = `
            <div class="grid-3 mb-4">
                <div>
                    <div class="text-xs text-muted">Folio</div>
                    <div style="font-weight:700;">${App.escapeHtml(s.folio)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Estado</div>
                    <div><span class="badge badge-${color}">${label}</span></div>
                </div>
                <div>
                    <div class="text-xs text-muted">PV destino</div>
                    <div style="font-weight:600;">${App.escapeHtml(s.pv)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Solicitado por</div>
                    <div style="font-weight:600;">${App.escapeHtml(s.solicitado_por_nombre)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Fecha solicitud</div>
                    <div style="font-weight:600;">${App.formatDate(s.fecha_solicitud)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Motivo</div>
                    <div style="font-weight:600;">${App.escapeHtml(s.motivo || '—')}</div>
                </div>
                ${s.descripcion ? `
                    <div style="grid-column: 1 / -1;">
                        <div class="text-xs text-muted">Descripción</div>
                        <div>${App.escapeHtml(s.descripcion)}</div>
                    </div>
                ` : ''}
            </div>
        `;

        if (s.estado === 'rechazado' && s.motivo_rechazo) {
            html += `
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle-fill"></i>
                    <div>
                        <strong>Rechazada por el almacén</strong>
                        <div>Motivo: ${App.escapeHtml(s.motivo_rechazo)}</div>
                    </div>
                </div>
            `;
        }
        if (s.estado === 'cancelado' && s.motivo_cancelacion) {
            html += `
                <div class="alert alert-warning">
                    <i class="bi bi-x-circle-fill"></i>
                    <div>
                        <strong>Cancelada</strong>
                        <div>Motivo: ${App.escapeHtml(s.motivo_cancelacion)}</div>
                    </div>
                </div>
            `;
        }
        if (s.observaciones_almacen) {
            html += `
                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill"></i>
                    <div>
                        <strong>Nota del almacén:</strong>
                        <div>${App.escapeHtml(s.observaciones_almacen)}</div>
                    </div>
                </div>
            `;
        }

        const puedeRecibir = s.estado === 'despachado' || s.estado === 'despachado_parcial';

        html += `
            <h4 style="font-size:15px;margin-bottom:10px;">
                <i class="bi bi-box-seam text-primary"></i> Productos
            </h4>
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Unidad</th>
                            <th class="text-right">Solicitado</th>
                            <th class="text-right">Aprobado</th>
                            <th class="text-right">Despachado</th>
                            <th class="text-right">Recibido</th>
                            ${puedeRecibir ? '<th class="text-right" style="width:140px;">Cant. recibida</th>' : ''}
                        </tr>
                    </thead>
                    <tbody>
                        ${s.detalle.map(d => {
                            const desp = parseInt(d.cantidad_despachada) || 0;
                            const unidad = d.unidad_medida || 'Unidad';
                            return `
                                <tr data-detalle-id="${d.id}">
                                    <td>
                                        <div style="font-weight:600;">${App.escapeHtml(d.producto)}</div>
                                        <div class="text-xs text-muted">${App.escapeHtml(d.codigo_barras || '—')}</div>
                                    </td>
                                    <td class="text-muted text-sm">${App.escapeHtml(unidad)}</td>
                                    <td class="text-right"><strong>${d.cantidad_solicitada} ${App.escapeHtml(unidad)}</strong></td>
                                    <td class="text-right">${d.cantidad_aprobada ? d.cantidad_aprobada + ' ' + App.escapeHtml(unidad) : '—'}</td>
                                    <td class="text-right">${d.cantidad_despachada ? d.cantidad_despachada + ' ' + App.escapeHtml(unidad) : '—'}</td>
                                    <td class="text-right">${d.cantidad_recibida ? d.cantidad_recibida + ' ' + App.escapeHtml(unidad) : '—'}</td>
                                    ${puedeRecibir ? `
                                        <td class="text-right">
                                            <input type="number"
                                                   class="calc-input recibir-input"
                                                   data-detalle-id="${d.id}"
                                                   data-max="${desp}"
                                                   value="${desp}"
                                                   min="0"
                                                   max="${desp}"
                                                   style="width:100px;text-align:center;padding:6px;"
                                                   ${desp === 0 ? 'disabled' : ''}>
                                        </td>
                                    ` : ''}
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;

        document.getElementById('det-contenido').innerHTML = html;

        let footer = '';

        if (s.estado === 'solicitado') {
            footer = `
                <button class="btn btn-warning" onclick="SupervisorSolicitudes.abrirCancelar(${s.id})">
                    <i class="bi bi-x-lg"></i> Cancelar solicitud
                </button>
            `;
        } else if (puedeRecibir) {
            footer = `
                <button class="btn btn-success" onclick="SupervisorSolicitudes.recibir(${s.id})">
                    <i class="bi bi-box-arrow-in-down"></i> Confirmar recepción
                </button>
            `;
        }

        footer += `
            <button class="btn btn-secondary" onclick="SupervisorSolicitudes.cerrarDetalle()">
                Cerrar
            </button>
        `;

        document.getElementById('det-footer').innerHTML = footer;

        document.getElementById('modal-detalle').classList.add('active');
    }

    function cerrarDetalle() {
        document.getElementById('modal-detalle').classList.remove('active');
        solicitudActual = null;
    }

    async function recibir(id) {
        const inputs = document.querySelectorAll('.recibir-input');
        const items = [];

        inputs.forEach(inp => {
            items.push({
                id: parseInt(inp.dataset.detalleId),
                cantidad_recibida: parseInt(inp.value) || 0,
            });
        });

        if (!items.length) {
            Toast.warning('Sin items', 'No hay productos para recibir');
            return;
        }

        const totalRecibido = items.reduce((sum, i) => sum + i.cantidad_recibida, 0);

        if (totalRecibido === 0) {
            Toast.warning('Nada recibido', 'Debes confirmar al menos 1 unidad');
            return;
        }

        let mensaje = `¿Confirmar la recepción de esta mercancía?\n\nTotal: ${totalRecibido} unidades\n`;
        mensaje += `\nEl stock se sumará al PV.`;

        const ok = await App.confirmar(mensaje, '📦 Confirmar recepción');
        if (!ok) return;

        const btn = document.querySelector('#det-footer .btn-success');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Confirmando...';
        }

        const res = await Api.post('api/solicitudes.php?accion=recibir', {
            id,
            items,
            observaciones: '',
        });

        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-box-arrow-in-down"></i> Confirmar recepción';
        }

        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Recepción confirmada', `${res.data.total_recibido} unidades sumadas al PV`);
        cargar();
        verDetalle(id);   // ⭐ reabrir el detalle
    }

    function abrirCancelar(id) {
        document.getElementById('can-id').value = id;
        document.getElementById('can-motivo').value = '';
        document.getElementById('modal-cancelar').classList.add('active');
        setTimeout(() => document.getElementById('can-motivo')?.focus(), 100);
    }

    async function confirmarCancelar() {
        const id = parseInt(document.getElementById('can-id').value);
        const motivo = document.getElementById('can-motivo').value.trim();

        if (motivo.length < 5) {
            Toast.warning('Motivo muy corto', 'Mínimo 5 caracteres');
            return;
        }

        const btn = document.getElementById('btn-confirmar-cancelar');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Cancelando...';

        const res = await Api.post('api/solicitudes.php?accion=cancelar', {
            id,
            motivo,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-x-lg"></i> Cancelar solicitud';

        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Cancelada', 'El almacén fue notificado');
        document.getElementById('modal-cancelar').classList.remove('active');
        cargar();
        verDetalle(id);   // ⭐ reabrir el detalle con el nuevo estado
    }

    function abrirNueva() {
        carrito = {};
        productosDisponibles = [];

        const selPV = document.getElementById('sol-pv');
        if (selPV) selPV.value = '';

        document.getElementById('sol-motivo').value = '';
        document.getElementById('sol-descripcion').value = '';
        document.getElementById('prod-buscar').value = '';
        document.getElementById('prod-categoria').value = '';
        document.getElementById('prod-resultados-wrap').style.display = 'none';

        renderCarrito();
        cargarProductosDisponibles();

        document.getElementById('modal-nueva').classList.add('active');
    }

    function cerrarNueva() {
        document.getElementById('modal-nueva').classList.remove('active');
        carrito = {};
    }

    async function cargarProductosDisponibles() {
        const q = document.getElementById('prod-buscar')?.value.trim() || '';
        const cat = document.getElementById('prod-categoria')?.value || '';

        const params = new URLSearchParams();
        params.append('solo_con_stock', '1');
        if (q) params.append('q', q);
        if (cat) params.append('categoria_id', cat);

        const res = await Api.get(`api/solicitudes.php?accion=productos_almacen&${params}`);
        if (!res.success) return;

        productosDisponibles = res.data;
        renderResultados();
    }

    const buscarProductoDebounced = App.debounce(() => {
        cargarProductosDisponibles();
    }, 300);

    function buscarProducto() {
        buscarProductoDebounced();
    }

    function renderResultados() {
        const cont = document.getElementById('prod-resultados');
        const wrap = document.getElementById('prod-resultados-wrap');

        if (!productosDisponibles.length) {
            wrap.style.display = 'none';
            return;
        }

        cont.innerHTML = productosDisponibles.slice(0, 30).map(p => {
            const stock = parseInt(p.stock_almacen) || 0;
            const enCarrito = carrito[p.id]?.cantidad || 0;
            const unidad = p.unidad_medida || 'Unidad';
            return `
                <div onclick="SupervisorSolicitudes.agregarAlCarrito(${p.id})"
                     style="padding:12px 16px;cursor:pointer;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;transition:background .15s;"
                     onmouseover="this.style.background='var(--surface-2)'"
                     onmouseout="this.style.background=''">
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;">${App.escapeHtml(p.nombre)}</div>
                        <div class="text-xs text-muted">
                            ${App.escapeHtml(p.codigo_barras || 'Sin código')} ·
                            Disponible: <strong>${stock} ${App.escapeHtml(unidad)}</strong>
                        </div>
                    </div>
                    ${enCarrito > 0
                        ? `<span class="badge badge-success">${enCarrito} en lista</span>`
                        : `<i class="bi bi-plus-circle text-primary"></i>`}
                </div>
            `;
        }).join('');

        wrap.style.display = '';
    }

    function agregarAlCarrito(productoId) {
        const p = productosDisponibles.find(x => x.id == productoId);
        if (!p) return;

        const stock = parseInt(p.stock_almacen) || 0;
        const enCarrito = carrito[productoId]?.cantidad || 0;
        const unidad = p.unidad_medida || 'Unidad';

        if (enCarrito >= stock) {
            Toast.warning('Sin stock', `Solo hay ${stock} ${unidad} disponibles en el almacén`);
            return;
        }

        if (carrito[productoId]) {
            carrito[productoId].cantidad++;
        } else {
            carrito[productoId] = {
                producto: p,
                cantidad: 1,
            };
        }

        renderCarrito();
        renderResultados();
    }

    function cambiarCantidad(productoId, delta) {
        const item = carrito[productoId];
        if (!item) return;

        const stock = parseInt(item.producto.stock_almacen) || 0;
        const nueva = item.cantidad + delta;

        if (nueva <= 0) {
            delete carrito[productoId];
        } else if (nueva > stock) {
            Toast.warning('Sin stock', `Máximo disponible: ${stock}`);
            return;
        } else {
            item.cantidad = nueva;
        }

        renderCarrito();
        renderResultados();
    }

    function setCantidad(productoId, cantidad) {
        const item = carrito[productoId];
        if (!item) return;

        const stock = parseInt(item.producto.stock_almacen) || 0;
        let val = parseInt(cantidad) || 0;

        if (val <= 0) {
            delete carrito[productoId];
        } else {
            if (val > stock) val = stock;
            item.cantidad = val;
        }

        renderCarrito();
        renderResultados();
    }

    function eliminarDelCarrito(productoId) {
        delete carrito[productoId];
        renderCarrito();
        renderResultados();
    }

    function renderCarrito() {
        const wrap = document.getElementById('carrito-wrap');
        const items = Object.values(carrito);

        const totalItemsEl = document.getElementById('sol-total-items');
        const totalUnidEl = document.getElementById('sol-total-unidades');

        if (!items.length) {
            wrap.innerHTML = `
                <div class="empty-state" style="padding:30px 15px;background:var(--surface-2);border-radius:var(--radius-md);">
                    <i class="bi bi-cart-x" style="font-size:40px;"></i>
                    <p class="text-muted mt-2">Aún no has agregado productos</p>
                </div>
            `;
            if (totalItemsEl) totalItemsEl.textContent = '0';
            if (totalUnidEl) totalUnidEl.textContent = '0';
            return;
        }

        let totalUnid = 0;
        wrap.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Unidad</th>
                            <th class="text-right">Disponible</th>
                            <th class="text-right" style="width:160px;">Cantidad</th>
                            <th class="text-right" style="width:80px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${items.map(item => {
                            totalUnid += item.cantidad;
                            const stock = parseInt(item.producto.stock_almacen) || 0;
                            const unidad = item.producto.unidad_medida || 'Unidad';
                            return `
                                <tr>
                                    <td>
                                        <div style="font-weight:600;">${App.escapeHtml(item.producto.nombre)}</div>
                                        <div class="text-xs text-muted">${App.escapeHtml(item.producto.codigo_barras || '—')}</div>
                                    </td>
                                    <td class="text-muted text-sm">${App.escapeHtml(unidad)}</td>
                                    <td class="text-right text-muted">${stock} ${App.escapeHtml(unidad)}</td>
                                    <td class="text-right">
                                        <div class="pos-cantidad-control" style="display:inline-flex;">
                                            <button class="pos-cantidad-btn" onclick="SupervisorSolicitudes.cambiarCantidad(${item.producto.id}, -1)">
                                                <i class="bi bi-dash"></i>
                                            </button>
                                            <input type="number"
                                                   class="pos-cantidad-input"
                                                   value="${item.cantidad}"
                                                   min="1"
                                                   max="${stock}"
                                                   onchange="SupervisorSolicitudes.setCantidad(${item.producto.id}, this.value)">
                                            <button class="pos-cantidad-btn" onclick="SupervisorSolicitudes.cambiarCantidad(${item.producto.id}, 1)">
                                                <i class="bi bi-plus"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <button class="btn btn-ghost btn-icon"
                                                onclick="SupervisorSolicitudes.eliminarDelCarrito(${item.producto.id})"
                                                style="color:var(--danger);">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;

        if (totalItemsEl) totalItemsEl.textContent = items.length;
        if (totalUnidEl) totalUnidEl.textContent = totalUnid;
    }

    async function crear() {
        const pvId = parseInt(document.getElementById('sol-pv').value);
        const motivo = document.getElementById('sol-motivo').value;
        const descripcion = document.getElementById('sol-descripcion').value.trim();

        if (!pvId) {
            Toast.warning('Falta PV', 'Selecciona un punto de venta');
            return;
        }

        if (!motivo) {
            Toast.warning('Falta motivo', 'Selecciona un motivo');
            return;
        }

        const items = Object.values(carrito).map(item => ({
            producto_id: item.producto.id,
            cantidad: item.cantidad,
        }));

        if (!items.length) {
            Toast.warning('Sin productos', 'Agrega al menos un producto a la solicitud');
            return;
        }

        const btn = document.getElementById('btn-crear-solicitud');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Creando...';

        const res = await Api.post('api/solicitudes.php?accion=crear', {
            punto_venta_id: pvId,
            motivo,
            descripcion,
            items,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Crear solicitud';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Solicitud creada', `Folio: ${res.data.folio}`);
        cerrarNueva();
        cargar();
    }

    function limpiar() {
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-estado').value = '';
        document.getElementById('filtro-desde').value = '';
        document.getElementById('filtro-hasta').value = '';
        cargar();
    }

    document.addEventListener('DOMContentLoaded', () => {
        cargar();

        const debounced = App.debounce(cargar, 400);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-estado')?.addEventListener('change', cargar);
        document.getElementById('filtro-desde')?.addEventListener('change', cargar);
        document.getElementById('filtro-hasta')?.addEventListener('change', cargar);
    });

    return {
        cargar,
        limpiar,
        verDetalle,
        cerrarDetalle,
        recibir,
        abrirCancelar,
        confirmarCancelar,
        abrirNueva,
        cerrarNueva,
        buscarProducto,
        cargarProductosDisponibles,
        agregarAlCarrito,
        cambiarCantidad,
        setCantidad,
        eliminarDelCarrito,
        crear,
    };
})();

window.SupervisorSolicitudes = SupervisorSolicitudes;