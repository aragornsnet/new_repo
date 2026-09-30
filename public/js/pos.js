/**
 * IPV - POS (Punto de Venta)
 */

const POS = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';
    const STORAGE_KEY = 'ipv_pos_carrito';
    const VISTA_KEY = 'ipv_pos_vista';

    let turno = null;
    let categorias = [];
    let config = {};
    let categoriaActual = 0;
    let paginaActual = 1;
    let totalPags = 1;
    let busquedaActual = '';
    let carrito = {};
    let productosCache = {};
    let vistaActual = localStorage.getItem(VISTA_KEY) || 'mosaico';

    async function init() {
        const res = await Api.get('api/pos.php?accion=inicializar');

        if (!res.success) {
            Toast.error('Error', res.message);
            setTimeout(() => window.location.href = BASE_URL + 'views/vendedor/dashboard.php', 2000);
            return;
        }

        turno = res.data.turno;
        categorias = res.data.categorias;
        config = res.data.config;

        if (window.POSCobro) {
            await POSCobro.cargarDivisas();
            POSCobro.renderBotonesDivisas();
        }

        restaurarCarrito();
        renderCategorias();
        aplicarVista(vistaActual);
        cargarProductos();

        setTimeout(() => document.getElementById('pos-q')?.focus(), 100);
        bindEventos();
    }

    function cambiarVista(vista) {
        if (!['mosaico', 'lista'].includes(vista)) return;
        vistaActual = vista;
        localStorage.setItem(VISTA_KEY, vista);
        aplicarVista(vista);
        renderProductos(Object.values(productosCache));
    }

    function aplicarVista(vista) {
        document.querySelectorAll('.pos-vista-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.vista === vista);
        });

        const cont = document.getElementById('pos-grid');
        if (!cont) return;

        if (vista === 'lista') {
            cont.classList.add('pos-lista');
            cont.classList.remove('pos-grid');
        } else {
            cont.classList.add('pos-grid');
            cont.classList.remove('pos-lista');
        }
    }

    function renderCategorias() {
        const cont = document.getElementById('pos-categorias');
        if (!cont) return;

        const totalProductos = categorias.reduce((sum, c) => sum + parseInt(c.num_productos), 0);

        let html = `
            <button class="pos-cat-btn ${categoriaActual === 0 ? 'active' : ''}" onclick="POS.filtrarCategoria(0)">
                <i class="bi bi-grid-3x3-gap-fill"></i> Todos
                <span class="badge">${totalProductos}</span>
            </button>
        `;

        categorias.forEach(c => {
            html += `
                <button class="pos-cat-btn ${categoriaActual == c.id ? 'active' : ''}" onclick="POS.filtrarCategoria(${c.id})">
                    ${App.escapeHtml(c.nombre)}
                    <span class="badge">${c.num_productos}</span>
                </button>
            `;
        });

        cont.innerHTML = html;
    }

    const cargarProductosDebounced = App.debounce(() => {
        paginaActual = 1;
        cargarProductos();
    }, 400);

    async function cargarProductos() {
        const cont = document.getElementById('pos-grid');
        cont.innerHTML = '<div class="empty-state" style="grid-column:1/-1;"><div class="spinner spinner-lg"></div></div>';

        const params = new URLSearchParams();
        params.append('pagina', paginaActual);
        params.append('por_pagina', 60);
        if (busquedaActual) params.append('q', busquedaActual);
        if (categoriaActual > 0) params.append('categoria_id', categoriaActual);

        const res = await Api.get('api/pos.php?accion=productos&' + params);

        if (!res.success) {
            cont.innerHTML = `<div class="alert alert-danger" style="grid-column:1/-1;">${res.message}</div>`;
            return;
        }

        totalPags = res.data.total_pags || 1;

        res.data.productos.forEach(p => {
            productosCache[p.id] = p;
        });

        if (res.data.codigo_exacto) {
            agregarAlCarrito(res.data.codigo_exacto.id);
            const qInput = document.getElementById('pos-q');
            if (qInput) qInput.value = '';
            busquedaActual = '';
            cargarProductos();
            return;
        }

        renderProductos(res.data.productos);
        renderPaginacion();
    }

    function renderProductos(productos) {
        if (vistaActual === 'lista') {
            renderProductosLista(productos);
        } else {
            renderProductosMosaico(productos);
        }
    }

    function renderProductosMosaico(productos) {
        const cont = document.getElementById('pos-grid');
        if (!cont) return;

        if (!productos.length) {
            cont.innerHTML = `
                <div class="empty-state" style="grid-column:1/-1;padding:60px 20px;">
                    <i class="bi bi-inbox" style="font-size:64px;"></i>
                    <p class="text-muted mt-3">No hay productos que coincidan</p>
                    ${busquedaActual ? `<button class="btn btn-secondary btn-sm mt-3" onclick="POS.limpiarBusqueda()">Limpiar búsqueda</button>` : ''}
                </div>
            `;
            return;
        }

        cont.innerHTML = productos.map(p => {
            const stock = parseInt(p.stock);
            const enCarrito = carrito[p.id]?.cantidad || 0;
            const sinStock = stock <= 0 && !config.permitir_stock_negativo;
            const unidad = p.unidad_medida || 'ud';

            let claseStock = 'ok';
            let iconoStock = 'bi-check-circle';
            let textoStock = `${stock} ${App.escapeHtml(unidad)}`;

            if (stock < 0) {
                claseStock = 'negativo';
                iconoStock = 'bi-x-octagon-fill';
                textoStock = `Negativo: ${stock} ${App.escapeHtml(unidad)}`;
            } else if (stock === 0) {
                claseStock = 'negativo';
                iconoStock = 'bi-exclamation-triangle-fill';
                textoStock = 'Sin stock';
            } else if (stock <= (p.stock_minimo || 5)) {
                claseStock = 'alerta';
                iconoStock = 'bi-exclamation-triangle-fill';
                textoStock = `${stock} ${App.escapeHtml(unidad)} (bajo)`;
            }

            let clasesCard = 'pos-producto-card';
            if (enCarrito > 0) clasesCard += ' agregado';
            if (sinStock) clasesCard += ' sin-stock';
            if (stock < 0) clasesCard += ' stock-negativo';
            else if (stock <= (p.stock_minimo || 5)) clasesCard += ' stock-bajo';

            return `
                <div class="${clasesCard}" onclick="POS.agregarAlCarrito(${p.id})">
                    ${enCarrito > 0 ? `<div class="pos-prod-badge-cantidad">${enCarrito}</div>` : ''}
                    <div class="pos-prod-nombre">${App.escapeHtml(p.nombre)}</div>
                    <div class="pos-prod-codigo">
                        <i class="bi bi-upc"></i> ${App.escapeHtml(p.codigo_barras || 'Sin código')}
                    </div>
                    <div class="pos-prod-precio">${App.formatMoney(p.precio)}</div>
                    <div class="pos-prod-stock ${claseStock}">
                        <i class="bi ${iconoStock}"></i> ${textoStock}
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderProductosLista(productos) {
        const cont = document.getElementById('pos-grid');
        if (!cont) return;

        if (!productos.length) {
            cont.innerHTML = `
                <div class="empty-state" style="padding:60px 20px;">
                    <i class="bi bi-inbox" style="font-size:64px;"></i>
                    <p class="text-muted mt-3">No hay productos que coincidan</p>
                    ${busquedaActual ? `<button class="btn btn-secondary btn-sm mt-3" onclick="POS.limpiarBusqueda()">Limpiar búsqueda</button>` : ''}
                </div>
            `;
            return;
        }

        cont.innerHTML = productos.map(p => {
            const stock = parseInt(p.stock);
            const enCarrito = carrito[p.id]?.cantidad || 0;
            const sinStock = stock <= 0 && !config.permitir_stock_negativo;
            const unidad = p.unidad_medida || 'ud';

            let claseStock = 'ok';
            let iconoStock = 'bi-check-circle';
            let textoStock = `${stock} ${App.escapeHtml(unidad)}`;

            if (stock < 0) {
                claseStock = 'negativo';
                iconoStock = 'bi-x-octagon-fill';
                textoStock = `Negativo: ${stock} ${App.escapeHtml(unidad)}`;
            } else if (stock === 0) {
                claseStock = 'negativo';
                iconoStock = 'bi-exclamation-triangle-fill';
                textoStock = 'Sin stock';
            } else if (stock <= (p.stock_minimo || 5)) {
                claseStock = 'alerta';
                iconoStock = 'bi-exclamation-triangle-fill';
                textoStock = `${stock} ${App.escapeHtml(unidad)} (bajo)`;
            }

            let clasesCard = 'pos-producto-card-h';
            if (enCarrito > 0) clasesCard += ' agregado';
            if (sinStock) clasesCard += ' sin-stock';
            if (stock < 0) clasesCard += ' stock-negativo';
            else if (stock <= (p.stock_minimo || 5)) clasesCard += ' stock-bajo';

            return `
                <div class="${clasesCard}" onclick="POS.agregarAlCarrito(${p.id})">
                    <div class="pos-prod-h-info">
                        <div class="pos-prod-h-nombre">${App.escapeHtml(p.nombre)}</div>
                        <div class="pos-prod-h-codigo">
                            <i class="bi bi-upc"></i> ${App.escapeHtml(p.codigo_barras || 'Sin código')}
                        </div>
                        <div class="pos-prod-h-stock ${claseStock}">
                            <i class="bi ${iconoStock}"></i> ${textoStock}
                        </div>
                    </div>
                    <div class="pos-prod-h-right">
                        <div class="pos-prod-h-precio">${App.formatMoney(p.precio)}</div>
                        ${enCarrito > 0 ? `<div class="pos-prod-h-badge"><i class="bi bi-cart-check-fill"></i> ${enCarrito}</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderPaginacion() {
        const cont = document.getElementById('pos-paginacion');
        if (!cont) return;

        if (totalPags <= 1) {
            cont.innerHTML = '';
            return;
        }

        const botones = [];

        if (paginaActual > 1) {
            botones.push(`<button onclick="POS.irPagina(${paginaActual - 1})"><i class="bi bi-chevron-left"></i></button>`);
        }

        const max = 5;
        let inicio = Math.max(1, paginaActual - 2);
        let fin = Math.min(totalPags, inicio + max - 1);
        if (fin - inicio < max - 1) inicio = Math.max(1, fin - max + 1);

        for (let i = inicio; i <= fin; i++) {
            botones.push(`<button class="${i === paginaActual ? 'active' : ''}" onclick="POS.irPagina(${i})">${i}</button>`);
        }

        if (paginaActual < totalPags) {
            botones.push(`<button onclick="POS.irPagina(${paginaActual + 1})"><i class="bi bi-chevron-right"></i></button>`);
        }

        cont.innerHTML = botones.join('');
    }

    function agregarAlCarrito(productoId) {
        const p = productosCache[productoId];
        if (!p) return;

        const stock = parseInt(p.stock);
        const enCarrito = carrito[productoId]?.cantidad || 0;

        if (!config.permitir_stock_negativo) {
            if (stock <= 0) {
                Toast.warning('Sin stock', `"${p.nombre}" no tiene unidades disponibles`);
                return;
            }
            if (enCarrito >= stock) {
                Toast.warning('Sin stock', `Solo hay ${stock} unidades de "${p.nombre}"`);
                return;
            }
        }

        if (carrito[productoId]) {
            carrito[productoId].cantidad++;
        } else {
            carrito[productoId] = {
                id: p.id,
                nombre: p.nombre,
                precio: parseFloat(p.precio),
                cantidad: 1,
                stock: stock,
                unidad_medida: p.unidad_medida || 'Unidad',
            };
        }

        guardarCarrito();
        renderCarrito();
        renderProductos(Object.values(productosCache));
    }

    function cambiarCantidad(productoId, delta) {
        const item = carrito[productoId];
        if (!item) return;

        const nuevaCantidad = item.cantidad + delta;

        if (nuevaCantidad <= 0) {
            eliminarDelCarrito(productoId);
            return;
        }

        if (delta > 0 && !config.permitir_stock_negativo && nuevaCantidad > item.stock) {
            Toast.warning('Sin stock', `Solo hay ${item.stock} unidades disponibles`);
            return;
        }

        item.cantidad = nuevaCantidad;
        guardarCarrito();
        renderCarrito();
        renderProductos(Object.values(productosCache));
    }

    function setCantidad(productoId, cantidad) {
        const item = carrito[productoId];
        if (!item) return;

        cantidad = parseInt(cantidad) || 0;

        if (cantidad <= 0) {
            eliminarDelCarrito(productoId);
            return;
        }

        if (!config.permitir_stock_negativo && cantidad > item.stock) {
            cantidad = Math.max(0, item.stock);
            Toast.warning('Cantidad ajustada', `Máximo disponible: ${item.stock}`);
            if (cantidad === 0) {
                eliminarDelCarrito(productoId);
                return;
            }
        }

        item.cantidad = cantidad;
        guardarCarrito();
        renderCarrito();
        renderProductos(Object.values(productosCache));
    }

    function eliminarDelCarrito(productoId) {
        delete carrito[productoId];
        guardarCarrito();
        renderCarrito();
        renderProductos(Object.values(productosCache));
    }

    function renderCarrito() {
        const cont = document.getElementById('carrito-items');
        const items = Object.values(carrito);

        const btnCobrar = document.getElementById('btn-cobrar');
        const btnCancelar = document.getElementById('btn-cancelar');
        const btnLimpiar = document.getElementById('btn-limpiar-carrito');
        const countBadge = document.getElementById('carrito-count');

        if (!items.length) {
            cont.innerHTML = `
                <div class="pos-carrito-vacio">
                    <i class="bi bi-cart-x"></i>
                    <p>El carrito está vacío</p>
                    <p class="text-muted text-xs">Agrega productos para empezar</p>
                </div>
            `;
            document.getElementById('carrito-total').textContent = App.formatMoney(0);
            if (countBadge) countBadge.textContent = '0';
            if (btnCobrar) btnCobrar.disabled = true;
            if (btnCancelar) btnCancelar.disabled = true;
            if (btnLimpiar) btnLimpiar.disabled = true;
            return;
        }

        cont.innerHTML = items.map(item => {
            const unidad = item.unidad_medida || 'Unidad';
            return `
                <div class="pos-carrito-item">
                    <div style="min-width:0;">
                        <div class="pos-carrito-item-nombre">${App.escapeHtml(item.nombre)}</div>
                        <div class="pos-carrito-item-precio">${App.formatMoney(item.precio)} / ${App.escapeHtml(unidad)}</div>
                    </div>
                    <div class="pos-carrito-item-acciones">
                        <div class="pos-cantidad-control">
                            <button class="pos-cantidad-btn" onclick="POS.cambiarCantidad(${item.id}, -1)">
                                <i class="bi bi-dash"></i>
                            </button>
                            <input type="number" class="pos-cantidad-input" value="${item.cantidad}" min="1"
                                   onchange="POS.setCantidad(${item.id}, this.value)">
                            <button class="pos-cantidad-btn" onclick="POS.cambiarCantidad(${item.id}, 1)">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                        <button class="pos-item-eliminar" onclick="POS.eliminarDelCarrito(${item.id})">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="pos-carrito-item-subtotal">
                        ${App.formatMoney(item.precio * item.cantidad)}
                        <div class="text-xs text-muted" style="font-weight:500;">
                            ${item.cantidad} ${App.escapeHtml(unidad)}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        const total = items.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
        document.getElementById('carrito-total').textContent = App.formatMoney(total);

        const totalItems = items.reduce((sum, item) => sum + item.cantidad, 0);
        if (countBadge) countBadge.textContent = totalItems;

        if (btnCobrar) btnCobrar.disabled = false;
        if (btnCancelar) btnCancelar.disabled = false;
        if (btnLimpiar) btnLimpiar.disabled = false;
    }

    function limpiarCarritoConConfirmacion() {
        if (!Object.keys(carrito).length) return;

        App.confirmar(
            '¿Cancelar la venta actual? Se perderán todos los productos del carrito.',
            '⚠️ Cancelar venta'
        ).then(ok => {
            if (ok) limpiarCarrito();
        });
    }

    function limpiarCarrito() {
        carrito = {};
        guardarCarrito();
        renderCarrito();
        renderProductos(Object.values(productosCache));
    }

    function guardarCarrito() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(carrito));
        } catch (e) { }
    }

    function restaurarCarrito() {
        try {
            const data = localStorage.getItem(STORAGE_KEY);
            if (data) {
                carrito = JSON.parse(data) || {};
                renderCarrito();
            }
        } catch (e) {
            carrito = {};
        }
    }

    function buscar(valor) {
        busquedaActual = valor.trim();
        cargarProductosDebounced();
    }

    function limpiarBusqueda() {
        const qInput = document.getElementById('pos-q');
        if (qInput) qInput.value = '';
        busquedaActual = '';
        paginaActual = 1;
        cargarProductos();
        qInput?.focus();
    }

    function filtrarCategoria(catId) {
        categoriaActual = catId;
        paginaActual = 1;
        renderCategorias();
        cargarProductos();
    }

    function irPagina(pagina) {
        paginaActual = pagina;
        cargarProductos();
        document.getElementById('pos-grid').scrollTop = 0;
    }

    function activarScanner() {
        const qInput = document.getElementById('pos-q');
        qInput?.focus();
        Toast.info('Lector activo', 'Escanea un código de barras ahora');
    }

    function bindEventos() {
        const qInput = document.getElementById('pos-q');
        const clearBtn = document.querySelector('.pos-buscador-clear');

        qInput?.addEventListener('input', (e) => {
            const valor = e.target.value;
            clearBtn?.classList.toggle('visible', valor.length > 0);
            buscar(valor);
        });

        qInput?.addEventListener('keydown', async (e) => {
            if (e.key === 'Enter') {
                const valor = qInput.value.trim();
                if (/^\d{6,}$/.test(valor)) {
                    e.preventDefault();
                    await procesarCodigoEscaneado(valor);
                }
            }
        });

        document.addEventListener('keydown', (e) => {
            const tag = e.target.tagName;
            const enInput = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
            const modalAbierto = document.querySelector('.modal-backdrop.active');

            if (e.key === 'F2') {
                e.preventDefault();
                qInput?.focus();
                return;
            }

            if (e.key === 'F4') {
                e.preventDefault();
                if (!modalAbierto && Object.keys(carrito).length > 0) {
                    abrirCobro();
                }
                return;
            }

            if (e.key === 'Escape' && !modalAbierto) {
                if (document.activeElement === qInput || busquedaActual) {
                    limpiarBusqueda();
                }
                return;
            }
        });
    }

    async function procesarCodigoEscaneado(codigo) {
        const res = await Api.get(`api/pos.php?accion=buscar_codigo&codigo=${encodeURIComponent(codigo)}`);

        if (!res.success) {
            Toast.warning('Código no encontrado', codigo);
            const qInput = document.getElementById('pos-q');
            if (qInput) {
                qInput.value = '';
                qInput.focus();
            }
            busquedaActual = '';
            cargarProductos();
            return;
        }

        productosCache[res.data.id] = res.data;
        agregarAlCarrito(res.data.id);
        Toast.success('Agregado', res.data.nombre);

        const qInput = document.getElementById('pos-q');
        if (qInput) {
            qInput.value = '';
            qInput.focus();
        }
        busquedaActual = '';
        cargarProductos();
    }

    async function abrirCobro() {
        const items = Object.values(carrito);
        if (!items.length) return;

        if (!config.permitir_stock_negativo) {
            const params = new URLSearchParams();
            params.append('pagina', 1);
            params.append('por_pagina', 200);

            const res = await Api.get('api/pos.php?accion=productos&' + params);
            if (res.success) {
                const stockActual = {};
                res.data.productos.forEach(p => {
                    stockActual[p.id] = parseInt(p.stock);
                });

                const invalidos = [];
                for (const item of items) {
                    const stock = stockActual[item.id];
                    if (stock === undefined) invalidos.push(`"${item.nombre}" (ya no existe)`);
                    else if (stock <= 0) invalidos.push(`"${item.nombre}" (sin stock)`);
                    else if (item.cantidad > stock) invalidos.push(`"${item.nombre}" (solo ${stock} disponibles)`);
                }

                if (invalidos.length > 0) {
                    Toast.error('Stock insuficiente', invalidos.join('<br>'));
                    cargarProductos();
                    return;
                }
            }
        }

        const total = items.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
        if (total <= 0) return;

        document.getElementById('cobro-total').textContent = App.formatMoney(total);

        if (window.POSCobro) {
            await POSCobro.cargarDivisas();
            POSCobro.renderBotonesDivisas();

            try {
                const resConfig = await Api.get('api/pos.php?accion=inicializar');
                if (resConfig.success) {
                    const cfg = resConfig.data.config?.transf_comprobante_adjunto || 'opcional';
                    POSCobro.setConfigComprobante(cfg);
                }
            } catch (e) {
                POSCobro.setConfigComprobante('opcional');
            }

            POSCobro.resetModal();
        }

        document.getElementById('modal-cobro').classList.add('active');
    }

    function cerrarCobro() {
        document.getElementById('modal-cobro').classList.remove('active');
    }

    async function confirmarCobroAvanzado() {
        const total = Object.values(carrito).reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
        const metodo = POSCobro.getMetodoActual();
        const moneda = POSCobro.getMonedaActual();

        const payload = {
            items: Object.values(carrito).map(item => ({
                producto_id: item.id,
                cantidad: item.cantidad,
            })),
            metodo_pago: metodo,
            moneda: moneda,
        };

        // ⭐ Requiere factura
        const requiereFactura = document.getElementById('requiere-factura')?.checked || false;
        const clienteId = POSCobro.getClienteFacturaId();

        if (requiereFactura && !clienteId) {
            Toast.warning('Falta cliente', 'Marca un cliente para emitir la factura');
            return;
        }

        payload.requiere_factura = requiereFactura;
        payload.cliente_id = clienteId;

        // ⭐ Comprobante
        const datosComprobante = POSCobro.getDatosComprobante();
        if (datosComprobante) {
            payload.requiere_comprobante = true;
            payload.nombre_comprador = datosComprobante.nombre;
            payload.documento_comprador = datosComprobante.documento;
            payload.telefono_comprador = datosComprobante.telefono;
            payload.direccion_comprador = datosComprobante.direccion;
        } else {
            payload.requiere_comprobante = false;
        }

        if (moneda !== 'CUP') {
            const tasa = POSCobro.getTasaActual();
            const totalDivisa = Math.round((total / tasa) * 100) / 100;
            const montoRecibido = parseFloat(document.getElementById('monto-recibido-divisa').value) || 0;

            if (montoRecibido < totalDivisa) {
                Toast.warning('Monto insuficiente', `Faltan ${(totalDivisa - montoRecibido).toFixed(2)} ${moneda}`);
                return;
            }

            payload.tasa_aplicada = tasa;
            payload.total_divisa = totalDivisa;
            payload.monto_recibido_divisa = montoRecibido;
            payload.metodo_pago = 'efectivo';

        } else if (metodo === 'efectivo') {
            const monto = parseFloat(document.getElementById('monto-recibido-simple').value) || 0;
            if (monto < total) {
                Toast.warning('Monto insuficiente', `Faltan ${App.formatMoney(total - monto)}`);
                return;
            }
            payload.monto_recibido_efectivo = monto;

        } else if (metodo === 'transferencia') {
            const ref = document.getElementById('referencia').value.trim();
            if (!ref) {
                Toast.warning('Falta referencia', 'Ingresa el número de referencia');
                return;
            }

            payload.monto_transferencia = total;
            payload.transferencia = {
                metodo_detalle: document.getElementById('metodo-detalle').value,
                referencia: ref,
                ultimos_digitos: document.getElementById('ultimos-digitos').value.trim(),
                titular: document.getElementById('titular').value.trim(),
                comprobante: POSCobro.getComprobantePath(),
            };

        } else if (metodo === 'mixto') {
            const montoEfectivo = parseFloat(document.getElementById('monto-recibido-simple').value) || 0;
            const montoTransf = parseFloat(document.getElementById('monto-transferencia').value) || 0;

            if (montoEfectivo + montoTransf < total) {
                Toast.warning('Monto insuficiente', `Faltan ${App.formatMoney(total - montoEfectivo - montoTransf)}`);
                return;
            }

            const ref = document.getElementById('referencia').value.trim();
            if (!ref) {
                Toast.warning('Falta referencia', 'Ingresa el número de referencia');
                return;
            }

            payload.monto_recibido_efectivo = montoEfectivo;
            payload.monto_transferencia = montoTransf;
            payload.transferencia = {
                metodo_detalle: document.getElementById('metodo-detalle').value,
                referencia: ref,
                ultimos_digitos: document.getElementById('ultimos-digitos').value.trim(),
                titular: document.getElementById('titular').value.trim(),
                comprobante: POSCobro.getComprobantePath(),
            };
        }

        const btn = document.getElementById('btn-confirmar-cobro');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Procesando...';

        const res = await Api.post('api/pos_cobro.php?accion=registrar', payload);

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Confirmar cobro';

        if (!res.success) {
            Toast.error('Error al cobrar', res.message);
            return;
        }

        cerrarCobro();
        mostrarExito(res.data);
        limpiarCarrito();

        // ⭐ Preguntar si quiere imprimir el ticket
        const preguntar = config.imprimir_preguntar === true || config.imprimir_preguntar == 1;
        if (preguntar) {
            setTimeout(async () => {
                const ok = await App.confirmar(
                    '¿Imprimir el ticket de la venta?',
                    '🖨️ Imprimir ticket'
                );
                if (ok) imprimirTicket(res.data.venta_id);
            }, 800);
        }
    }

    function mostrarExito(datos) {
        document.getElementById('exito-folio').textContent = 'Folio: ' + datos.folio;

        if (datos.moneda && datos.moneda !== 'CUP') {
            document.getElementById('exito-total').innerHTML = `
                ${datos.moneda} ${App.formatNumber(datos.total_divisa, 2)}
                <div style="font-size:12px;color:var(--text-muted);">${App.formatMoney(datos.total)} CUP</div>
            `;
        } else {
            document.getElementById('exito-total').textContent = App.formatMoney(datos.total);
        }

        document.getElementById('exito-cambio').textContent = App.formatMoney(datos.cambio);
        document.getElementById('exito-cambio-wrap').style.display = datos.cambio > 0 ? '' : 'none';

        // ⭐ Aviso de factura
        const vueltoCont = document.getElementById('exito-vuelto-sugerido');
        let htmlExtra = '';

        if (datos.requiere_factura) {
            htmlExtra += `
                <div style="text-align:left;padding:16px;background:var(--info-light);border-radius:var(--radius-md);margin-top:16px;border:1px solid var(--info);">
                    <div style="font-size:13px;font-weight:700;color:#075985;margin-bottom:4px;">
                        <i class="bi bi-receipt"></i> Venta marcada para facturar
                    </div>
                    <div style="font-size:12px;color:#075985;">
                        El supervisor emitirá la factura con folio fiscal desde el módulo de Facturas.
                    </div>
                </div>
            `;
        }

        if (datos.comprobante_folio) {
            htmlExtra += `
                <div style="text-align:left;padding:16px;background:var(--success-light);border-radius:var(--radius-md);margin-top:16px;border:1px solid var(--success);">
                    <div style="font-size:13px;font-weight:700;color:#166534;margin-bottom:4px;">
                        <i class="bi bi-receipt-cutoff"></i> Comprobante emitido
                    </div>
                    <div style="font-size:12px;color:#166534;">
                        Folio: <strong>${datos.comprobante_folio}</strong>
                    </div>
                </div>
            `;
        }

        if (vueltoCont && datos.vuelto_sugerido && datos.vuelto_sugerido.length) {
            htmlExtra += `
                <div style="text-align:left;padding:16px;background:var(--warning-light);border-radius:var(--radius-md);margin-top:16px;">
                    <div style="font-size:12px;font-weight:700;color:#92400e;margin-bottom:8px;text-transform:uppercase;">
                        <i class="bi bi-lightbulb-fill"></i> Cómo entregar el vuelto
                    </div>
                    ${datos.vuelto_sugerido.map(s => `
                        <div style="display:flex;justify-content:space-between;font-size:13px;color:#92400e;margin-bottom:4px;">
                            <span>${s.cantidad} × ${App.formatMoney(s.valor)} 💵</span>
                            <strong>${App.formatMoney(s.subtotal)}</strong>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        if (vueltoCont) vueltoCont.innerHTML = htmlExtra;

        // ⭐ Guardar el venta_id para el botón de imprimir
        const btnImprimir = document.getElementById('btn-imprimir-ticket');
        if (btnImprimir) {
            btnImprimir.dataset.ventaId = datos.venta_id;
        }

        document.getElementById('modal-exito').classList.add('active');
    }

    function cerrarExitoYNueva() {
        document.getElementById('modal-exito').classList.remove('active');
        setTimeout(() => document.getElementById('pos-q')?.focus(), 100);
    }

    function abrirAyuda() {
        const modalId = 'modal-ayuda-pos';

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal">
                    <div class="modal-header">
                        <div class="modal-title"><i class="bi bi-keyboard"></i> Atajos de teclado</div>
                        <button class="modal-close" onclick="document.getElementById('${modalId}').remove()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="tabla-wrap">
                            <table class="tabla">
                                <thead><tr><th>Tecla</th><th>Acción</th></tr></thead>
                                <tbody>
                                    <tr><td><kbd>F2</kbd></td><td>Enfocar el buscador</td></tr>
                                    <tr><td><kbd>F4</kbd></td><td>Cobrar</td></tr>
                                    <tr><td><kbd>Esc</kbd></td><td>Limpiar búsqueda</td></tr>
                                    <tr><td><kbd>Enter</kbd></td><td>Procesar código de barras</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.getElementById('${modalId}').remove()">Cerrar</button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    document.addEventListener('DOMContentLoaded', init);

    // ═══════════════════════════════════════════════════════════
    // IMPRIMIR TICKET
    // ═══════════════════════════════════════════════════════════
    function imprimirTicket(ventaId) {
        const modalId = 'modal-pdf-ticket';
        document.getElementById(modalId)?.remove();

        const urlPreview  = `${BASE_URL}api/pos_ticket.php?accion=pdf&modo=inline&venta_id=${ventaId}`;
        const urlDownload = `${BASE_URL}api/pos_ticket.php?accion=pdf&modo=download&venta_id=${ventaId}`;

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}" style="z-index:1100;">
                <div class="modal modal-xl" style="max-width:900px;height:90vh;display:flex;flex-direction:column;">
                    <div class="modal-header">
                        <div class="modal-title">
                            <i class="bi bi-receipt text-primary"></i>
                            Ticket de venta
                        </div>
                        <button class="modal-close" onclick="POS.cerrarTicketPreview()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body" style="padding:0;flex:1;overflow:hidden;background:var(--surface-3);">
                        <iframe id="pdf-ticket-iframe" src="${urlPreview}"
                                style="width:100%;height:100%;border:0;background:#fff;"
                                title="Vista previa del ticket"></iframe>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="POS.cerrarTicketPreview()">
                            <i class="bi bi-x-lg"></i> Cerrar
                        </button>
                        <a href="${urlDownload}" class="btn btn-danger" download>
                            <i class="bi bi-download"></i> Descargar PDF
                        </a>
                        <button class="btn btn-primary" onclick="POS.imprimirIframeTicket()">
                            <i class="bi bi-printer"></i> Imprimir
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    function cerrarTicketPreview() {
        document.getElementById('modal-pdf-ticket')?.remove();
    }

    function imprimirIframeTicket() {
        const iframe = document.getElementById('pdf-ticket-iframe');
        if (!iframe) return;
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch (e) {
            Toast.warning('Impresión bloqueada', 'Usa el botón de descarga y abre el PDF manualmente');
        }
    }

    function imprimirDesdeExito() {
        const btn = document.getElementById('btn-imprimir-ticket');
        const ventaId = btn?.dataset.ventaId;
        if (!ventaId) return;
        imprimirTicket(parseInt(ventaId));
    }

    return {
        agregarAlCarrito,
        cambiarCantidad,
        setCantidad,
        eliminarDelCarrito,
        limpiarCarrito,
        limpiarCarritoConConfirmacion,
        limpiarBusqueda,
        buscar,
        filtrarCategoria,
        irPagina,
        activarScanner,
        abrirCobro,
        cerrarCobro,
        confirmarCobroAvanzado,
        cerrarExitoYNueva,
        abrirAyuda,
        cambiarVista,
        imprimirTicket,
        imprimirDesdeExito,
        cerrarTicketPreview,
        imprimirIframeTicket,
        obtenerTotal: () => Object.values(carrito).reduce((sum, item) => sum + (item.precio * item.cantidad), 0),
    };
})();

window.POS = POS;