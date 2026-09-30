/**
 * IPV - Entradas al Almacén
 * Modo buscar: entrada a producto existente
 * Modo crear:  crea producto nuevo + entrada inicial
 */

const AlmacenEntradas = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let catalogoMotivos = [];
    let productoSeleccionado = null;
    let modoCrear = false;
    let contratosProducto = [];
    let contratosVigentes = [];

    // ═══════════════════════════════════════════════════════════
    // CATÁLOGOS
    // ═══════════════════════════════════════════════════════════
    async function cargarCatalogos() {
        const res = await Api.get('api/almacen.php?accion=catalogos');
        if (!res.success) return;

        catalogoMotivos = res.data.motivos_entrada || [];

        const select = document.getElementById('e-motivo');
        if (select) {
            select.innerHTML = '<option value="">Selecciona motivo</option>' +
                catalogoMotivos.map(m => `<option value="${App.escapeHtml(m)}">${App.escapeHtml(m)}</option>`).join('');
        }

        // ⭐ Cargar contratos vigentes (para el modo crear)
        const resContratos = await Api.get('api/almacen.php?accion=contratos_vigentes');
        if (resContratos.success) {
            contratosVigentes = resContratos.data || [];
            renderSelectContratosVigentes();
        }
    }

    // ⭐ NUEVO: poblar el select de contratos en el modo crear
    function renderSelectContratosVigentes() {
        const sel = document.getElementById('p-contrato');
        if (!sel) return;

        let html = '<option value="">Sin contrato</option>';
        contratosVigentes.forEach(c => {
            const vence = c.fecha_caducidad ? new Date(c.fecha_caducidad) : null;
            const hoy = new Date();
            let info = '';
            if (vence) {
                const dias = Math.floor((vence - hoy) / 86400000);
                if (c.estado_display === 'por_renovar') info = ' · ⚠️ VENCIDO';
                else if (dias <= 30) info = ` · ⚠️ Vence en ${dias}d`;
            }
            html += `<option value="${c.id}">${App.escapeHtml(c.num_contrato)} · ${App.escapeHtml(c.proveedor)}${info}</option>`;
        });
        sel.innerHTML = html;
    }

    // ═══════════════════════════════════════════════════════════
    // LISTAR HISTORIAL
    // ═══════════════════════════════════════════════════════════
    async function cargar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const desde = document.getElementById('filtro-desde')?.value || '';
        const hasta = document.getElementById('filtro-hasta')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);

        const cont = document.getElementById('tabla-entradas');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/almacen.php?accion=historial_entradas&${params}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            cont.innerHTML = `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        const lista = res.data;

        const t1 = document.getElementById('total-entradas');
        const t2 = document.getElementById('total-entradas-2');
        if (t1) t1.textContent = lista.length;
        if (t2) t2.textContent = lista.length;

        render(lista);
    }

    function render(lista) {
        const cont = document.getElementById('tabla-entradas');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>Sin entradas</h3>
                <p>No hay entradas registradas con los filtros actuales.</p>
                <button class="btn btn-success btn-sm mt-3" onclick="AlmacenEntradas.abrirNueva()">
                    <i class="bi bi-plus-lg"></i> Registrar primera entrada
                </button>
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
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">Stock</th>
                            <th>Motivo</th>
                            <th>Contrato</th>
                            <th>Nº Factura</th>
                            <th>Usuario</th>
                            <th class="text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(m => {
                            const unidad = m.unidad_medida || 'Unidad';
                            return `
                                <tr>
                                    <td class="text-muted text-xs" style="white-space:nowrap;">
                                        ${App.formatDate(m.fecha)}
                                    </td>
                                    <td>
                                        <div style="font-weight:600;">${App.escapeHtml(m.producto)}</div>
                                        <div class="text-xs text-muted">${App.escapeHtml(m.codigo_barras || '—')}</div>
                                    </td>
                                    <td class="text-muted text-sm">${App.escapeHtml(unidad)}</td>
                                    <td class="text-right">
                                        <span class="badge badge-success">
                                            <i class="bi bi-arrow-up"></i> +${m.cantidad} ${App.escapeHtml(unidad)}
                                        </span>
                                    </td>
                                    <td class="text-right text-sm">
                                        <span class="text-muted">${m.valor_anterior}</span>
                                        <i class="bi bi-arrow-right text-xs"></i>
                                        <strong>${m.valor_nuevo}</strong>
                                    </td>
                                    <td class="text-sm">
                                        <div style="font-weight:500;">${App.escapeHtml(m.motivo || '—')}</div>
                                        ${m.descripcion
                                            ? `<div class="text-xs text-muted">${App.escapeHtml(m.descripcion)}</div>`
                                            : ''}
                                    </td>
                                    <td class="text-sm">
                                        ${m.num_contrato
                                            ? `<span class="badge badge-info"><i class="bi bi-file-earmark-text"></i> ${App.escapeHtml(m.num_contrato)}</span>`
                                            : '<span class="text-muted text-xs">—</span>'}
                                    </td>
                                    <td class="text-muted text-xs" style="font-family:var(--font-mono);">
                                        ${m.numero_factura ? App.escapeHtml(m.numero_factura) : '—'}
                                    </td>
                                    <td class="text-muted text-sm">${App.escapeHtml(m.usuario)}</td>
                                    <td class="text-right">
                                        ${(!m.num_contrato && window.CURRENT_USER && window.CURRENT_USER.rol === 'Administrador')
                                            ? `<button class="btn btn-ghost btn-icon"
                                                       onclick="AlmacenEntradas.vincularContrato(${m.id}, ${m.producto_id})"
                                                       title="Vincular a contrato"
                                                       style="color:var(--primary);">
                                                   <i class="bi bi-link-45deg"></i>
                                               </button>`
                                            : ''}
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
    // ABRIR / CERRAR MODAL
    // ═══════════════════════════════════════════════════════════
    function abrirNueva() {
        productoSeleccionado = null;
        modoCrear = false;
        contratosProducto = [];

        // Reset modo buscar
        document.getElementById('e-buscar').value = '';
        document.getElementById('e-cantidad').value = '1';
        document.getElementById('e-motivo').value = '';
        document.getElementById('e-referencia').value = '';
        document.getElementById('e-descripcion').value = '';
        document.getElementById('e-resultados-wrap').style.display = 'none';
        document.getElementById('e-seleccionado-wrap').style.display = 'none';
        document.getElementById('e-producto-id').value = '';
        document.getElementById('e-stock-help').textContent = '';

        // NUEVOS CAMPOS
        const selContrato = document.getElementById('e-contrato');
        if (selContrato) selContrato.innerHTML = '<option value="">Sin contrato</option>';
        const inpFactura = document.getElementById('e-num-factura');
        if (inpFactura) inpFactura.value = '';
        const helpContrato = document.getElementById('e-contrato-help');
        if (helpContrato) helpContrato.textContent = 'Solo aparecen los contratos vinculados al producto seleccionado';

        // Reset modo crear
        document.getElementById('p-nombre').value = '';
        document.getElementById('p-codigo').value = '';
        document.getElementById('p-categoria').value = '';
        document.getElementById('p-unidad').value = '';
        document.getElementById('p-stock-min').value = '5';
        document.getElementById('p-descripcion').value = '';
        document.getElementById('p-cantidad').value = '1';
        document.getElementById('p-motivo').value = 'Alta inicial de producto';

        // ⭐ NUEVO: reset de contrato y factura
        const selContratoCrear = document.getElementById('p-contrato');
        if (selContratoCrear) selContratoCrear.value = '';
        const inpFacturaCrear = document.getElementById('p-num-factura');
        if (inpFacturaCrear) inpFacturaCrear.value = '';

        aplicarModo();

        document.getElementById('modal-entrada').classList.add('active');
        setTimeout(() => document.getElementById('e-buscar')?.focus(), 100);
    }

    function cerrarModal() {
        document.getElementById('modal-entrada').classList.remove('active');
    }

    function aplicarModo() {
        if (modoCrear) {
            document.getElementById('modo-buscar').style.display = 'none';
            document.getElementById('modo-crear').style.display = '';
            document.getElementById('seccion-cantidad-buscar').style.display = 'none';
            document.getElementById('btn-guardar-entrada').innerHTML = '<i class="bi bi-check-lg"></i> Crear producto y registrar entrada';
        } else {
            document.getElementById('modo-buscar').style.display = '';
            document.getElementById('modo-crear').style.display = 'none';
            document.getElementById('seccion-cantidad-buscar').style.display = productoSeleccionado ? '' : 'none';
            document.getElementById('btn-guardar-entrada').innerHTML = '<i class="bi bi-check-lg"></i> Registrar entrada';
        }
    }

    function cambiarModoCrear() {
        modoCrear = true;
        aplicarModo();
        setTimeout(() => document.getElementById('p-nombre')?.focus(), 100);
    }

    function cambiarModoBuscar() {
        modoCrear = false;
        aplicarModo();
        setTimeout(() => document.getElementById('e-buscar')?.focus(), 100);
    }

    // ═══════════════════════════════════════════════════════════
    // BÚSQUEDA DE PRODUCTO
    // ═══════════════════════════════════════════════════════════
    const buscarProductoDebounced = App.debounce(async () => {
        const q = document.getElementById('e-buscar').value.trim();

        if (q.length < 2) {
            document.getElementById('e-resultados-wrap').style.display = 'none';
            return;
        }

        const res = await Api.get(`api/almacen.php?accion=productos&q=${encodeURIComponent(q)}`);
        if (!res.success || !res.data.length) {
            const cont = document.getElementById('e-resultados');
            cont.innerHTML = `
                <div style="padding:20px 16px;text-align:center;">
                    <i class="bi bi-search" style="font-size:32px;color:var(--text-light);"></i>
                    <p class="text-muted mt-2 mb-3">No se encontró ningún producto</p>
                    <button type="button" class="btn btn-primary btn-sm" onclick="AlmacenEntradas.crearConBusqueda('${App.escapeHtml(q).replace(/'/g, "\\'")}')">
                        <i class="bi bi-plus-circle"></i> Crear producto con "${App.escapeHtml(q)}"
                    </button>
                </div>
            `;
            document.getElementById('e-resultados-wrap').style.display = '';
            return;
        }

        const cont = document.getElementById('e-resultados');
        cont.innerHTML = res.data.slice(0, 20).map(p => {
            const unidad = p.unidad_medida || 'Unidad';
            return `
                <div onclick="AlmacenEntradas.seleccionarProducto(${p.id})"
                     style="padding:12px 16px;cursor:pointer;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;transition:background .15s;"
                     onmouseover="this.style.background='var(--surface-2)'"
                     onmouseout="this.style.background=''">
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;">${App.escapeHtml(p.nombre)}</div>
                        <div class="text-xs text-muted">
                            ${App.escapeHtml(p.codigo_barras || 'Sin código')} ·
                            Stock actual: <strong>${p.stock_almacen} ${App.escapeHtml(unidad)}</strong>
                        </div>
                    </div>
                    <i class="bi bi-plus-circle text-success"></i>
                </div>
            `;
        }).join('');

        document.getElementById('e-resultados-wrap').style.display = '';
    }, 300);

    function buscarProducto() {
        buscarProductoDebounced();
    }

    function crearConBusqueda(texto) {
        modoCrear = true;
        aplicarModo();

        if (/^\d+$/.test(texto)) {
            document.getElementById('p-codigo').value = texto;
            document.getElementById('p-nombre').value = '';
            document.getElementById('p-nombre').focus();
        } else {
            document.getElementById('p-nombre').value = texto;
            document.getElementById('p-codigo').value = '';
            document.getElementById('p-codigo').focus();
        }
    }

    async function seleccionarProducto(id) {
        const res = await Api.get(`api/almacen.php?accion=obtener&producto_id=${id}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        productoSeleccionado = res.data;

        const unidad = productoSeleccionado.unidad_medida || 'Unidad';
        document.getElementById('e-producto-id').value = productoSeleccionado.id;
        document.getElementById('e-sel-nombre').textContent = productoSeleccionado.nombre;
        document.getElementById('e-sel-info').textContent =
            `${productoSeleccionado.codigo_barras || 'Sin código'} · Stock actual: ${productoSeleccionado.stock_almacen} ${unidad}`;
        document.getElementById('e-seleccionado-wrap').style.display = '';
        document.getElementById('e-resultados-wrap').style.display = 'none';
        document.getElementById('e-buscar').value = '';
        document.getElementById('seccion-cantidad-buscar').style.display = '';

        actualizarStockHelp();

        // ⭐ Cargar contratos vinculados al producto
        await cargarContratosProducto(productoSeleccionado.id);
    }

    function limpiarProducto() {
        productoSeleccionado = null;
        contratosProducto = [];
        document.getElementById('e-producto-id').value = '';
        document.getElementById('e-seleccionado-wrap').style.display = 'none';
        document.getElementById('e-buscar').value = '';
        document.getElementById('e-stock-help').textContent = '';
        document.getElementById('seccion-cantidad-buscar').style.display = 'none';

        const selContrato = document.getElementById('e-contrato');
        if (selContrato) selContrato.innerHTML = '<option value="">Sin contrato</option>';
        const inpFactura = document.getElementById('e-num-factura');
        if (inpFactura) inpFactura.value = '';
    }

    // ⭐ NUEVO: cargar contratos vinculados al producto
    async function cargarContratosProducto(productoId) {
        const sel = document.getElementById('e-contrato');
        const help = document.getElementById('e-contrato-help');

        if (!sel) return;

        sel.innerHTML = '<option value="">Cargando...</option>';
        contratosProducto = [];

        const res = await Api.get(`api/almacen.php?accion=contratos_para_producto&producto_id=${productoId}`);

        if (!res.success || !res.data.length) {
            sel.innerHTML = '<option value="">Sin contratos disponibles</option>';
            if (help) {
                help.innerHTML = `
                    <span style="color:var(--warning);font-weight:600;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Este producto no está vinculado a ningún contrato.
                    </span>
                    <br>
                    <span class="text-muted">
                        Si necesitas asociarlo, créalo primero en <a href="${BASE_URL}views/admin/contratos.php" target="_blank">Contratos</a>.
                    </span>
                `;
            }
            return;
        }

        contratosProducto = res.data;

        let html = '<option value="">Sin contrato</option>';
        res.data.forEach(c => {
            const vence = c.fecha_caducidad ? new Date(c.fecha_caducidad) : null;
            const hoy = new Date();
            let info = '';
            if (vence) {
                const dias = Math.floor((vence - hoy) / 86400000);
                if (c.estado_display === 'por_renovar') info = ' · ⚠️ VENCIDO';
                else if (dias <= 30) info = ` · ⚠️ Vence en ${dias}d`;
            }
            html += `<option value="${c.id}">${App.escapeHtml(c.num_contrato)} · ${App.escapeHtml(c.proveedor)}${info}</option>`;
        });
        sel.innerHTML = html;

        if (help) {
            help.innerHTML = `
                <i class="bi bi-check-circle-fill" style="color:var(--success);"></i>
                <strong>${res.data.length}</strong> contrato(s) disponible(s) para este producto
            `;
        }
    }

    function actualizarStockHelp() {
        const help = document.getElementById('e-stock-help');
        if (!help || !productoSeleccionado) return;

        const stockActual = parseInt(productoSeleccionado.stock_almacen) || 0;
        const cantidad = parseInt(document.getElementById('e-cantidad').value) || 0;
        const nuevo = stockActual + cantidad;
        const unidad = productoSeleccionado.unidad_medida || 'Unidad';

        help.textContent = `Stock actual: ${stockActual} ${unidad} → quedará en ${nuevo} ${unidad}`;
    }

    // ═══════════════════════════════════════════════════════════
    // GUARDAR (dispatch entre modo buscar / crear)
    // ═══════════════════════════════════════════════════════════
    async function guardar() {
        if (modoCrear) {
            await guardarCrearProducto();
        } else {
            await guardarEntradaExistente();
        }
    }

    // ─── Modo: entrada a producto existente ───
    async function guardarEntradaExistente() {
        const productoId = parseInt(document.getElementById('e-producto-id').value);
        const cantidad = parseInt(document.getElementById('e-cantidad').value);
        const motivo = document.getElementById('e-motivo').value;
        const referencia = document.getElementById('e-referencia').value.trim();
        const descripcion = document.getElementById('e-descripcion').value.trim();
        const contratoId = parseInt(document.getElementById('e-contrato').value) || 0;
        const numeroFactura = document.getElementById('e-num-factura').value.trim();

        if (!productoId) {
            Toast.warning('Falta producto', 'Selecciona un producto');
            return;
        }
        if (!cantidad || cantidad < 1) {
            Toast.warning('Cantidad inválida', 'Debe ser al menos 1');
            return;
        }
        if (!motivo) {
            Toast.warning('Falta motivo', 'Selecciona un motivo');
            return;
        }

        const btn = document.getElementById('btn-guardar-entrada');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Registrando...';

        const res = await Api.post('api/almacen.php?accion=entrada', {
            producto_id: productoId,
            cantidad,
            motivo,
            referencia,
            descripcion,
            contrato_id: contratoId,
            numero_factura: numeroFactura,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Registrar entrada';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        const unidad = res.data.unidad_medida || 'Unidad';
        Toast.success('Entrada registrada', `Stock: ${res.data.stock_anterior} → ${res.data.stock_nuevo} ${unidad}`);
        cerrarModal();
        cargar();
    }

    // ─── Modo: crear producto nuevo ───
        async function guardarCrearProducto() {
        const nombre = document.getElementById('p-nombre').value.trim();
        const codigo = document.getElementById('p-codigo').value.trim();
        const categoria = document.getElementById('p-categoria').value;
        const unidad = document.getElementById('p-unidad').value;
        const stockMin = parseInt(document.getElementById('p-stock-min').value) || 0;
        const descripcion = document.getElementById('p-descripcion').value.trim();
        const cantidad = parseInt(document.getElementById('p-cantidad').value) || 0;
        const motivo = document.getElementById('p-motivo').value.trim();
        const contratoId = parseInt(document.getElementById('p-contrato')?.value) || 0;
        const numeroFactura = document.getElementById('p-num-factura')?.value.trim() || '';

        if (!nombre || nombre.length < 2) {
            Toast.warning('Falta nombre', 'El nombre es obligatorio (mínimo 2 caracteres)');
            document.getElementById('p-nombre').focus();
            return;
        }
        if (!categoria) {
            Toast.warning('Falta categoría', 'Selecciona una categoría');
            document.getElementById('p-categoria').focus();
            return;
        }
        if (!unidad) {
            Toast.warning('Falta unidad', 'Selecciona una unidad de medida');
            document.getElementById('p-unidad').focus();
            return;
        }
        if (!cantidad || cantidad < 1) {
            Toast.warning('Cantidad inválida', 'La cantidad inicial debe ser al menos 1');
            document.getElementById('p-cantidad').focus();
            return;
        }

        const btn = document.getElementById('btn-guardar-entrada');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Creando producto...';

        const res = await Api.post('api/almacen.php?accion=crear_producto_con_entrada', {
            nombre,
            codigo_barras: codigo,
            descripcion,
            stock_minimo: stockMin,
            unidad_medida: unidad,
            categoria_id: categoria,
            cantidad_inicial: cantidad,
            motivo_entrada: motivo || 'Alta inicial de producto',
            contrato_id: contratoId,
            numero_factura: numeroFactura,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Crear producto y registrar entrada';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        const unidadResp = res.data.unidad_medida || unidad;
        Toast.success('Producto creado', `Entrada inicial de ${cantidad} ${unidadResp} al almacén`);
        Toast.info('Aviso', 'El precio y costo los asigna el administrador');

        cerrarModal();
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // FILTROS
    // ═══════════════════════════════════════════════════════════
    function limpiar() {
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-desde').value = '';
        document.getElementById('filtro-hasta').value = '';
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // ⭐ VINCULAR CONTRATO A UN MOVIMIENTO EXISTENTE (solo Admin)
    // ═══════════════════════════════════════════════════════════
    async function vincularContrato(movimientoId, productoId) {
        // Verificar rol en cliente (el backend también valida)
        if (window.CURRENT_USER && window.CURRENT_USER.rol !== 'Administrador') {
            Toast.warning('Sin permisos', 'Solo el administrador puede vincular movimientos a contratos');
            return;
        }

        // Cargar contratos del producto
        const res = await Api.get(`api/almacen.php?accion=contratos_para_producto&producto_id=${productoId}`);
        if (!res.success || !res.data.length) {
            Toast.warning(
                'Sin contratos',
                'Este producto no está vinculado a ningún contrato. Créalo primero en Contratos.'
            );
            return;
        }

        const contratos = res.data;

        // Construir modal
        const modalId = 'modal-vincular-contrato';
        document.getElementById(modalId)?.remove();

        const opcionesHtml = contratos.map(c => {
            let info = '';
            if (c.estado_display === 'por_renovar') info = ' · ⚠️ VENCIDO';
            return `<option value="${c.id}">${App.escapeHtml(c.num_contrato)} · ${App.escapeHtml(c.proveedor)}${info}</option>`;
        }).join('');

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal">
                    <div class="modal-header">
                        <div class="modal-title">
                            <i class="bi bi-link-45deg text-primary"></i>
                            Vincular movimiento a contrato
                        </div>
                        <button class="modal-close" onclick="document.getElementById('${modalId}').remove()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill"></i>
                            <div>
                                Se asociará el contrato seleccionado al movimiento histórico.
                                <strong>Esta acción queda registrada en auditoría.</strong>
                            </div>
                        </div>

                        <div class="field mb-3">
                            <label for="vin-contrato">Contrato <span class="req">*</span></label>
                            <select id="vin-contrato" required>
                                <option value="">Selecciona contrato</option>
                                ${opcionesHtml}
                            </select>
                        </div>

                        <div class="field">
                            <label for="vin-factura">Nº factura de adquisición (opcional)</label>
                            <input type="text" id="vin-factura" maxlength="100"
                                   placeholder="Ej: FAC-2026-1234">
                            <div class="help">Dejar vacío para no modificar</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.getElementById('${modalId}').remove()">
                            Cancelar
                        </button>
                        <button class="btn btn-primary" id="btn-confirmar-vincular"
                                onclick="AlmacenEntradas.confirmarVincular(${movimientoId})">
                            <i class="bi bi-check-lg"></i> Vincular
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    async function confirmarVincular(movimientoId) {
        const sel = document.getElementById('vin-contrato');
        const contratoId = parseInt(sel.value) || 0;
        const numeroFactura = document.getElementById('vin-factura')?.value.trim() || '';

        if (!contratoId) {
            Toast.warning('Falta contrato', 'Selecciona un contrato');
            return;
        }

        const btn = document.getElementById('btn-confirmar-vincular');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Vinculando...';

        const res = await Api.post('api/almacen.php?accion=vincular_movimiento_contrato', {
            movimiento_id: movimientoId,
            contrato_id: contratoId,
            numero_factura: numeroFactura,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Vincular';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Vinculado', 'Contrato asociado al movimiento');
        document.getElementById('modal-vincular-contrato')?.remove();
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // EVENTOS
    // ═══════════════════════════════════════════════════════════
    document.addEventListener('DOMContentLoaded', () => {
        cargarCatalogos().then(cargar);

        const debounced = App.debounce(cargar, 400);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-desde')?.addEventListener('change', cargar);
        document.getElementById('filtro-hasta')?.addEventListener('change', cargar);

        // Abrir modal automáticamente si viene ?nuevo=1
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('nuevo') === '1') {
            setTimeout(() => {
                abrirNueva();
                cambiarModoCrear();
            }, 300);
        }
    });

    return {
        cargar,
        limpiar,
        abrirNueva,
        cerrarModal,
        cambiarModoCrear,
        cambiarModoBuscar,
        buscarProducto,
        crearConBusqueda,
        seleccionarProducto,
        limpiarProducto,
        actualizarStockHelp,
        cargarContratosProducto,
        renderSelectContratosVigentes,
        guardar,
        vincularContrato,
        confirmarVincular,
    };
})();

window.AlmacenEntradas = AlmacenEntradas;