/**
 * IPV - Gestión de Contratos de Proveedor
 */

const Contratos = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let lista = [];
    let modoEdicion = false;
    let puedeEditar = false;
    let productosSeleccionados = {};   // { producto_id: {id, nombre, codigo_barras, unidad_medida} }
    let productosCache = {};

    // ═══════════════════════════════════════════════════════════
    // LISTAR
    // ═══════════════════════════════════════════════════════════
    async function cargar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const provId = document.getElementById('filtro-proveedor')?.value || '';
        const estado = document.getElementById('filtro-estado')?.value || '';
        const desde = document.getElementById('filtro-desde')?.value || '';
        const hasta = document.getElementById('filtro-hasta')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (provId) params.append('proveedor_id', provId);
        if (estado) params.append('estado', estado);
        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);

        const cont = document.getElementById('tabla-contratos');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get('api/contratos.php?accion=listar&' + params);
        if (!res.success) {
            Toast.error('Error', res.message);
            cont.innerHTML = `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        lista = res.data;
        document.getElementById('total-contratos').textContent = lista.length;
        document.getElementById('total-contratos-2').textContent = lista.length;

        actualizarResumen();
        render();
    }

    // ═══════════════════════════════════════════════════════════
    // RESUMEN (tarjetas arriba)
    // ═══════════════════════════════════════════════════════════
    async function actualizarResumen() {
        // Se pide al backend para que el conteo sea real sin importar filtros
        const res = await Api.get('api/contratos.php?accion=listar');
        if (!res.success) return;

        let activos = 0, porVencer = 0, porRenovar = 0, archivados = 0;

        res.data.forEach(c => {
            const est = c.estado_display;
            if (est === 'activo') activos++;
            else if (est === 'por_vencer') porVencer++;
            else if (est === 'por_renovar') porRenovar++;
            else if (['renovado', 'cancelado', 'no_renovado'].includes(est)) archivados++;
        });

        document.getElementById('res-activos').textContent = activos;
        document.getElementById('res-por-vencer').textContent = porVencer;
        document.getElementById('res-por-renovar').textContent = porRenovar;
        document.getElementById('res-archivados').textContent = archivados;
    }

    // ═══════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════
    function render() {
        const cont = document.getElementById('tabla-contratos');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-file-earmark-text"></i>
                <h3>Sin contratos</h3>
                <p>No hay contratos que coincidan con los filtros.</p>
                ${puedeEditar ? `<button class="btn btn-primary btn-sm mt-3" onclick="Contratos.abrirNuevo()">
                    <i class="bi bi-plus-lg"></i> Crear primer contrato
                </button>` : ''}
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Nº contrato</th>
                            <th>Proveedor</th>
                            <th>Vigencia</th>
                            <th class="text-right">Monto</th>
                            <th class="text-right">Productos</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(c => {
                            const badge = badgeEstado(c.estado_display);
                            const esVencido = c.estado_display === 'por_renovar';
                            const dias = c.dias_restantes;

                            let alertaDias = '';
                            if (dias !== null && dias >= 0 && dias <= 30 && c.estado_display === 'por_vencer') {
                                alertaDias = `<div class="text-xs" style="color:var(--warning);font-weight:600;">
                                    <i class="bi bi-clock"></i> Vence en ${dias} día${dias !== 1 ? 's' : ''}
                                </div>`;
                            } else if (esVencido && dias !== null) {
                                const abs = Math.abs(dias);
                                alertaDias = `<div class="text-xs" style="color:var(--danger);font-weight:600;">
                                    <i class="bi bi-exclamation-triangle-fill"></i> Venció hace ${abs} día${abs !== 1 ? 's' : ''}
                                </div>`;
                            }

                            return `
                                <tr style="${['renovado','cancelado','no_renovado'].includes(c.estado_display) ? 'opacity:.7;' : ''}">
                                    <td>
                                        <strong>${App.escapeHtml(c.num_contrato)}</strong>
                                        ${c.contrato_anterior_num
                                            ? `<div class="text-xs text-muted">Renueva a ${App.escapeHtml(c.contrato_anterior_num)}</div>`
                                            : ''}
                                    </td>
                                    <td>
                                        <div style="font-weight:600;">${App.escapeHtml(c.proveedor)}</div>
                                        ${c.proveedor_nit ? `<div class="text-xs text-muted">${App.escapeHtml(c.proveedor_nit)}</div>` : ''}
                                    </td>
                                    <td class="text-muted text-sm">
                                        ${App.formatDate(c.fecha_inicio).split(' ')[0]}
                                        <i class="bi bi-arrow-right text-xs"></i>
                                        ${App.formatDate(c.fecha_caducidad).split(' ')[0]}
                                        ${alertaDias}
                                    </td>
                                    <td class="text-right">
                                        ${c.monto ? App.formatMoney(c.monto) : '—'}
                                        ${c.monto ? `<div class="text-xs text-muted">${App.escapeHtml(c.moneda)}</div>` : ''}
                                    </td>
                                    <td class="text-right">
                                        <span class="badge badge-info">${c.num_productos}</span>
                                    </td>
                                    <td><span class="badge badge-${badge.color}">${badge.label}</span></td>
                                    <td class="text-right">
                                        <div class="d-flex gap-1" style="justify-content:flex-end;">
                                            <button class="btn btn-ghost btn-icon"
                                                    onclick="Contratos.verDetalle(${c.id})"
                                                    title="Ver detalle">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            ${puedeEditar && c.estado_display === 'activo' ? `
                                                <button class="btn btn-ghost btn-icon"
                                                        onclick="Contratos.editar(${c.id})"
                                                        title="Editar">
                                                    <i class="bi bi-pencil"></i>
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

    function badgeEstado(estado) {
        const map = {
            activo:      { color: 'success', label: 'Activo' },
            por_vencer:  { color: 'warning', label: 'Por vencer' },
            por_renovar: { color: 'danger',  label: 'Por renovar' },
            renovado:    { color: 'info',    label: 'Renovado' },
            cancelado:   { color: 'neutral', label: 'Cancelado' },
            no_renovado: { color: 'neutral', label: 'No renovado' },
        };
        return map[estado] || { color: 'neutral', label: estado };
    }

    // ═══════════════════════════════════════════════════════════
    // ATAJOS (para las tarjetas del resumen)
    // ═══════════════════════════════════════════════════════════
    function atajo(estado) {
        document.getElementById('filtro-estado').value = estado;
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-proveedor').value = '';
        document.getElementById('filtro-desde').value = '';
        document.getElementById('filtro-hasta').value = '';
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // ABRIR / CERRAR MODAL DE CREAR / EDITAR
    // ═══════════════════════════════════════════════════════════
    function abrirNuevo() {
        modoEdicion = false;
        productosSeleccionados = {};
        productosCache = {};

        document.getElementById('con-modal-titulo').textContent = 'Nuevo contrato';
        document.getElementById('con-id').value = '';
        document.getElementById('con-num').value = '';
        document.getElementById('con-proveedor').value = '';
        document.getElementById('con-inicio').value = new Date().toISOString().split('T')[0];
        document.getElementById('con-caducidad').value = '';
        document.getElementById('con-monto').value = '';
        document.getElementById('con-moneda').value = 'CUP';
        document.getElementById('con-forma-pago').value = 'contado';
        document.getElementById('con-plazo').value = '';
        document.getElementById('con-descripcion').value = '';
        document.getElementById('con-observaciones').value = '';
        document.getElementById('con-buscar-prod').value = '';
        document.getElementById('con-resultados-wrap').style.display = 'none';

        renderListaProductos();
        document.getElementById('modal-contrato').classList.add('active');
        setTimeout(() => document.getElementById('con-num')?.focus(), 100);
    }

    async function editar(id) {
        // Buscar en la lista actual para tener datos básicos
        const c = lista.find(x => x.id == id);
        if (!c) return;

        // Cargar detalle completo (con productos)
        const res = await Api.get(`api/contratos.php?accion=obtener&id=${id}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        const contrato = res.data;
        modoEdicion = true;
        productosSeleccionados = {};
        productosCache = {};

        document.getElementById('con-modal-titulo').textContent = 'Editar contrato';
        document.getElementById('con-id').value = contrato.id;
        document.getElementById('con-num').value = contrato.num_contrato;
        document.getElementById('con-proveedor').value = contrato.proveedor_id;
        document.getElementById('con-inicio').value = contrato.fecha_inicio.split(' ')[0];
        document.getElementById('con-caducidad').value = contrato.fecha_caducidad.split(' ')[0];
        document.getElementById('con-monto').value = contrato.monto || '';
        document.getElementById('con-moneda').value = contrato.moneda || 'CUP';
        document.getElementById('con-forma-pago').value = contrato.forma_pago || 'contado';
        document.getElementById('con-plazo').value = contrato.plazo_dias || '';
        document.getElementById('con-descripcion').value = contrato.descripcion || '';
        document.getElementById('con-observaciones').value = contrato.observaciones || '';
        document.getElementById('con-buscar-prod').value = '';
        document.getElementById('con-resultados-wrap').style.display = 'none';

        // Cargar productos vinculados
        contrato.productos.forEach(p => {
            productosSeleccionados[p.producto_id] = {
                id: p.producto_id,
                nombre: p.producto,
                codigo_barras: p.codigo_barras,
                unidad_medida: p.unidad_medida,
            };
            productosCache[p.producto_id] = {
                id: p.producto_id,
                nombre: p.producto,
                codigo_barras: p.codigo_barras,
                unidad_medida: p.unidad_medida,
            };
        });

        renderListaProductos();
        document.getElementById('modal-contrato').classList.add('active');
    }

    function cerrarModal() {
        document.getElementById('modal-contrato')?.classList.remove('active');
    }

    // ═══════════════════════════════════════════════════════════
    // BUSCAR PRODUCTOS
    // ═══════════════════════════════════════════════════════════
    const buscarProductoDebounced = App.debounce(async () => {
        const q = document.getElementById('con-buscar-prod').value.trim();
        if (q.length < 2) {
            document.getElementById('con-resultados-wrap').style.display = 'none';
            return;
        }

        const res = await Api.get(`api/contratos.php?accion=productos_disponibles&q=${encodeURIComponent(q)}&limit=30`);
        if (!res.success || !res.data.length) {
            document.getElementById('con-resultados-wrap').style.display = 'none';
            return;
        }

        // Cachear
        res.data.forEach(p => { productosCache[p.id] = p; });

        const cont = document.getElementById('con-resultados');
        cont.innerHTML = res.data.map(p => {
            const ya = productosSeleccionados[p.id];
            const unidad = p.unidad_medida || 'Unidad';
            return `
                <div onclick="Contratos.toggleProducto(${p.id})"
                     style="padding:12px 16px;cursor:pointer;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;transition:background .15s;"
                     onmouseover="this.style.background='var(--surface-2)'"
                     onmouseout="this.style.background=''">
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;">${App.escapeHtml(p.nombre)}</div>
                        <div class="text-xs text-muted">
                            ${App.escapeHtml(p.codigo_barras || 'Sin código')} · ${App.escapeHtml(unidad)}
                            ${p.categoria ? ' · ' + App.escapeHtml(p.categoria) : ''}
                        </div>
                    </div>
                    <i class="bi bi-${ya ? 'check-circle-fill text-success' : 'plus-circle text-primary'}"></i>
                </div>
            `;
        }).join('');

        document.getElementById('con-resultados-wrap').style.display = '';
    }, 300);

    function buscarProducto() {
        buscarProductoDebounced();
    }

    function toggleProducto(productoId) {
        if (productosSeleccionados[productoId]) {
            delete productosSeleccionados[productoId];
        } else {
            const p = productosCache[productoId];
            if (!p) return;
            productosSeleccionados[productoId] = {
                id: p.id,
                nombre: p.nombre,
                codigo_barras: p.codigo_barras,
                unidad_medida: p.unidad_medida,
            };
        }
        renderListaProductos();
        buscarProducto(); // refresca los íconos
    }

    function eliminarProducto(productoId) {
        delete productosSeleccionados[productoId];
        renderListaProductos();
        buscarProducto();
    }

    function renderListaProductos() {
        const cont = document.getElementById('con-lista-productos');
        const items = Object.values(productosSeleccionados);

        document.getElementById('con-total-productos').textContent = items.length;

        if (!items.length) {
            cont.innerHTML = `
                <div class="empty-state" style="padding:20px;background:var(--surface-2);border-radius:var(--radius-md);">
                    <i class="bi bi-box" style="font-size:32px;"></i>
                    <p class="text-muted mt-2">Aún no has agregado productos</p>
                </div>
            `;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Código</th>
                            <th>Unidad</th>
                            <th class="text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${items.map(p => `
                            <tr>
                                <td><strong>${App.escapeHtml(p.nombre)}</strong></td>
                                <td class="text-muted text-xs">${App.escapeHtml(p.codigo_barras || '—')}</td>
                                <td class="text-muted text-sm">${App.escapeHtml(p.unidad_medida || 'Unidad')}</td>
                                <td class="text-right">
                                    <button type="button" class="btn btn-ghost btn-icon"
                                            onclick="Contratos.eliminarProducto(${p.id})"
                                            style="color:var(--danger);">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    // ═══════════════════════════════════════════════════════════
    // GUARDAR
    // ═══════════════════════════════════════════════════════════
    async function guardar() {
        const id = document.getElementById('con-id').value;
        const numContrato = document.getElementById('con-num').value.trim();
        const proveedorId = document.getElementById('con-proveedor').value;
        const fechaInicio = document.getElementById('con-inicio').value;
        const fechaCaduca = document.getElementById('con-caducidad').value;
        const monto = document.getElementById('con-monto').value;
        const moneda = document.getElementById('con-moneda').value;
        const formaPago = document.getElementById('con-forma-pago').value;
        const plazo = document.getElementById('con-plazo').value;
        const descripcion = document.getElementById('con-descripcion').value.trim();
        const observaciones = document.getElementById('con-observaciones').value.trim();
        const productos = Object.keys(productosSeleccionados).map(Number);

        if (!numContrato) {
            Toast.warning('Falta número', 'El número de contrato es obligatorio');
            document.getElementById('con-num').focus();
            return;
        }
        if (!proveedorId) {
            Toast.warning('Falta proveedor', 'Selecciona un proveedor');
            document.getElementById('con-proveedor').focus();
            return;
        }
        if (!fechaInicio || !fechaCaduca) {
            Toast.warning('Faltan fechas', 'Las fechas de inicio y caducidad son obligatorias');
            return;
        }
        if (new Date(fechaCaduca) <= new Date(fechaInicio)) {
            Toast.warning('Fechas inválidas', 'La fecha de caducidad debe ser posterior a la de inicio');
            return;
        }

        const payload = {
            num_contrato: numContrato,
            proveedor_id: parseInt(proveedorId),
            fecha_inicio: fechaInicio,
            fecha_caducidad: fechaCaduca,
            monto: monto || null,
            moneda,
            forma_pago: formaPago,
            plazo_dias: plazo ? parseInt(plazo) : null,
            descripcion,
            observaciones,
            productos,
        };

        const btn = document.getElementById('btn-guardar-contrato');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Guardando...';

        let res;
        if (modoEdicion) {
            payload.id = parseInt(id);
            res = await Api.post('api/contratos.php?accion=actualizar', payload);
        } else {
            res = await Api.post('api/contratos.php?accion=crear', payload);
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Listo', res.message);
        cerrarModal();
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // VER DETALLE
    // ═══════════════════════════════════════════════════════════
    async function verDetalle(id) {
        document.getElementById('modal-detalle-contrato').classList.add('active');
        document.getElementById('det-contenido').innerHTML =
            '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/contratos.php?accion=obtener&id=${id}`);
        if (!res.success) {
            document.getElementById('det-contenido').innerHTML =
                `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        const c = res.data;
        const badge = badgeEstado(c.estado_display);

        document.getElementById('det-titulo').textContent = `Contrato ${c.num_contrato}`;

        document.getElementById('det-contenido').innerHTML = `
            <div class="grid-3 mb-4">
                <div>
                    <div class="text-xs text-muted">Nº contrato</div>
                    <div style="font-weight:700;">${App.escapeHtml(c.num_contrato)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Estado</div>
                    <div><span class="badge badge-${badge.color}">${badge.label}</span></div>
                </div>
                <div>
                    <div class="text-xs text-muted">Proveedor</div>
                    <div style="font-weight:600;">${App.escapeHtml(c.proveedor)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Vigencia</div>
                    <div style="font-weight:600;">
                        ${App.formatDate(c.fecha_inicio).split(' ')[0]}
                        <i class="bi bi-arrow-right text-xs"></i>
                        ${App.formatDate(c.fecha_caducidad).split(' ')[0]}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted">Monto</div>
                    <div style="font-weight:600;">${c.monto ? App.formatMoney(c.monto) + ' ' + App.escapeHtml(c.moneda) : '—'}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Forma de pago</div>
                    <div style="font-weight:600;">
                        ${c.forma_pago === 'contado' ? 'Contado' : (c.forma_pago === 'credito' ? 'Crédito' : 'Mixto')}
                        ${c.plazo_dias ? ` · ${c.plazo_dias} días` : ''}
                    </div>
                </div>
                ${c.contrato_anterior_num ? `
                    <div>
                        <div class="text-xs text-muted">Renueva a</div>
                        <div style="font-weight:600;">${App.escapeHtml(c.contrato_anterior_num)}</div>
                    </div>
                ` : ''}
            </div>

            ${c.descripcion ? `
                <div class="mb-4">
                    <div class="text-xs text-muted">Descripción</div>
                    <div>${App.escapeHtml(c.descripcion)}</div>
                </div>
            ` : ''}

            ${c.observaciones ? `
                <div class="mb-4">
                    <div class="text-xs text-muted">Observaciones</div>
                    <div style="white-space:pre-wrap;">${App.escapeHtml(c.observaciones)}</div>
                </div>
            ` : ''}

            <h4 style="font-size:15px;margin-bottom:10px;">
                <i class="bi bi-box-seam text-primary"></i>
                Productos vinculados (${c.productos.length})
            </h4>

            ${c.productos.length === 0 ? `
                <div class="empty-state" style="padding:30px;">
                    <i class="bi bi-inbox" style="font-size:32px;"></i>
                    <p class="text-muted mt-2">Sin productos vinculados</p>
                </div>
            ` : `
                <div class="tabla-wrap">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Código</th>
                                <th>Unidad</th>
                                <th class="text-right">Precio</th>
                                <th class="text-right">Costo</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${c.productos.map(p => `
                                <tr>
                                    <td><strong>${App.escapeHtml(p.producto)}</strong></td>
                                    <td class="text-muted text-xs">${App.escapeHtml(p.codigo_barras || '—')}</td>
                                    <td class="text-muted text-sm">${App.escapeHtml(p.unidad_medida || 'Unidad')}</td>
                                    <td class="text-right">${App.formatMoney(p.precio)}</td>
                                    <td class="text-right text-muted">${App.formatMoney(p.costo)}</td>
                                    <td>${p.activo == 1
                                        ? '<span class="badge badge-success">Activo</span>'
                                        : '<span class="badge badge-neutral">Inactivo</span>'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `}
        `;

        // Footer con acciones según estado
        let footer = '';
        const est = c.estado_display;

        if (puedeEditar) {
            if (est === 'activo') {
                footer += `
                    <button class="btn btn-danger" onclick="Contratos.abrirCancelar(${c.id})">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </button>
                    <button class="btn btn-success" onclick="Contratos.abrirRenovar(${c.id})">
                        <i class="bi bi-arrow-clockwise"></i> Renovar
                    </button>
                `;
            } else if (est === 'por_vencer' || est === 'por_renovar') {
                footer += `
                    <button class="btn btn-warning" onclick="Contratos.abrirNoRenovar(${c.id})">
                        <i class="bi bi-archive"></i> No renovar
                    </button>
                    <button class="btn btn-success" onclick="Contratos.abrirRenovar(${c.id})">
                        <i class="bi bi-arrow-clockwise"></i> Renovar
                    </button>
                `;
            } else if (est === 'cancelado' || est === 'no_renovado') {
                footer += `
                    <button class="btn btn-secondary" onclick="Contratos.reactivar(${c.id})">
                        <i class="bi bi-arrow-counterclockwise"></i> Reactivar
                    </button>
                `;
            }
        }

        footer += `<button class="btn btn-secondary" onclick="Contratos.cerrarDetalle()">Cerrar</button>`;
        document.getElementById('det-footer').innerHTML = footer;
    }

    function cerrarDetalle() {
        document.getElementById('modal-detalle-contrato')?.classList.remove('active');
    }

    // ═══════════════════════════════════════════════════════════
    // RENOVAR
    // ═══════════════════════════════════════════════════════════
    function abrirRenovar(id) {
        const c = lista.find(x => x.id == id);
        if (!c) return;

        document.getElementById('ren-id').value = id;
        document.getElementById('ren-num').value = '';
        document.getElementById('ren-inicio').value = new Date().toISOString().split('T')[0];
        document.getElementById('ren-caducidad').value = '';

        document.getElementById('modal-renovar').classList.add('active');
        setTimeout(() => document.getElementById('ren-num')?.focus(), 100);
    }

    async function confirmarRenovar() {
        const id = parseInt(document.getElementById('ren-id').value);
        const numContrato = document.getElementById('ren-num').value.trim();
        const fechaInicio = document.getElementById('ren-inicio').value;
        const fechaCaduca = document.getElementById('ren-caducidad').value;

        if (!numContrato) { Toast.warning('Falta número', 'El número del nuevo contrato es obligatorio'); return; }
        if (!fechaInicio || !fechaCaduca) { Toast.warning('Faltan fechas', 'Ingresa ambas fechas'); return; }
        if (new Date(fechaCaduca) <= new Date(fechaInicio)) {
            Toast.warning('Fechas inválidas', 'La caducidad debe ser posterior al inicio');
            return;
        }

        const btn = document.getElementById('btn-confirmar-renovar');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Renovando...';

        const res = await Api.post('api/contratos.php?accion=renovar', {
            id,
            num_contrato: numContrato,
            fecha_inicio: fechaInicio,
            fecha_caducidad: fechaCaduca,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Renovar';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Contrato renovado', `Nuevo: ${res.data.num_contrato}`);
        document.getElementById('modal-renovar').classList.remove('active');
        cerrarDetalle();
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // CANCELAR
    // ═══════════════════════════════════════════════════════════
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

        const res = await Api.post('api/contratos.php?accion=cancelar', { id, motivo });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-x-lg"></i> Cancelar contrato';

        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Contrato cancelado', res.message);
        document.getElementById('modal-cancelar').classList.remove('active');
        cerrarDetalle();
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // NO RENOVAR
    // ═══════════════════════════════════════════════════════════
    function abrirNoRenovar(id) {
        document.getElementById('nr-id').value = id;
        document.getElementById('nr-motivo').value = '';
        document.getElementById('modal-no-renovar').classList.add('active');
        setTimeout(() => document.getElementById('nr-motivo')?.focus(), 100);
    }

    async function confirmarNoRenovar() {
        const id = parseInt(document.getElementById('nr-id').value);
        const motivo = document.getElementById('nr-motivo').value.trim();

        if (motivo.length < 5) {
            Toast.warning('Motivo muy corto', 'Mínimo 5 caracteres');
            return;
        }

        const btn = document.getElementById('btn-confirmar-no-renovar');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Archivando...';

        const res = await Api.post('api/contratos.php?accion=no_renovar', { id, motivo });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-archive"></i> Archivar contrato';

        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Contrato archivado', res.message);
        document.getElementById('modal-no-renovar').classList.remove('active');
        cerrarDetalle();
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // REACTIVAR
    // ═══════════════════════════════════════════════════════════
    async function reactivar(id) {
        const ok = await App.confirmar(
            '¿Reactivar este contrato? Se restaurará al estado activo o por_renovar según su fecha de caducidad.',
            'Reactivar contrato'
        );
        if (!ok) return;

        const res = await Api.post('api/contratos.php?accion=reactivar', { id });
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Contrato reactivado', res.message);
        cerrarDetalle();
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // FILTROS
    // ═══════════════════════════════════════════════════════════
    function limpiar() {
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-proveedor').value = '';
        document.getElementById('filtro-estado').value = '';
        document.getElementById('filtro-desde').value = '';
        document.getElementById('filtro-hasta').value = '';
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // EVENTOS
    // ═══════════════════════════════════════════════════════════
    document.addEventListener('DOMContentLoaded', () => {
        puedeEditar = !!document.getElementById('modal-contrato');
        cargar();

        const debounced = App.debounce(cargar, 400);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-proveedor')?.addEventListener('change', cargar);
        document.getElementById('filtro-estado')?.addEventListener('change', cargar);
        document.getElementById('filtro-desde')?.addEventListener('change', cargar);
        document.getElementById('filtro-hasta')?.addEventListener('change', cargar);
    });

    return {
        cargar,
        limpiar,
        atajo,
        abrirNuevo,
        editar,
        cerrarModal,
        guardar,
        verDetalle,
        cerrarDetalle,
        buscarProducto,
        toggleProducto,
        eliminarProducto,
        abrirRenovar,
        confirmarRenovar,
        abrirCancelar,
        confirmarCancelar,
        abrirNoRenovar,
        confirmarNoRenovar,
        reactivar,
    };
})();

window.Contratos = Contratos;