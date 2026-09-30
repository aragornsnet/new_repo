/**
 * IPV - Cobro avanzado del POS (sin conteo por denominación)
 */

const POSCobro = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let divisas = [];
    let metodoActual = 'efectivo';
    let monedaActual = 'CUP';
    let tasaActual = 0;
    let configComprobante = 'opcional';
    let clientesCache = [];

    // ============================================================
    // CARGAR DIVISAS
    // ============================================================
    async function cargarDivisas() {
        const res = await Api.get('api/pos_divisas.php?accion=listar');
        if (!res.success) return false;

        divisas = res.data.divisas || [];
        return true;
    }

    // ============================================================
    // RECALCULAR TODO
    // ============================================================
    function recalcularTodo() {
        const totalVenta = POS.obtenerTotal ? POS.obtenerTotal() : 0;
        const metodo = metodoActual;
        const esDivisa = monedaActual !== 'CUP';

        let totalRecibido = 0;
        let totalTransferencia = 0;

        if (!esDivisa) {
            if (metodo === 'efectivo' || metodo === 'mixto') {
                const inputSimple = document.getElementById('monto-recibido-simple');
                totalRecibido = parseFloat(inputSimple?.value) || 0;
            }

            if (metodo === 'transferencia') {
                totalTransferencia = totalVenta;
            } else if (metodo === 'mixto') {
                const inputTransf = document.getElementById('monto-transferencia');
                totalTransferencia = parseFloat(inputTransf?.value) || 0;
            }
        } else {
            const inputDivisa = document.getElementById('monto-recibido-divisa');
            const montoDivisa = parseFloat(inputDivisa?.value) || 0;
            totalRecibido = montoDivisa * tasaActual;
        }

        const totalPagado = totalRecibido + totalTransferencia;
        const vuelto = Math.max(0, totalRecibido + totalTransferencia - totalVenta);

        const totalRecibidoEl = document.getElementById('cobro-total-recibido');
        if (totalRecibidoEl) totalRecibidoEl.textContent = App.formatMoney(totalPagado);

        const cambioEl = document.getElementById('cobro-cambio');
        if (cambioEl) {
            if (metodo === 'transferencia') cambioEl.textContent = App.formatMoney(0);
            else cambioEl.textContent = App.formatMoney(vuelto);
        }

        const estadoEl = document.getElementById('cobro-estado-pago');
        if (estadoEl && !esDivisa) {
            if (totalVenta <= 0) {
                estadoEl.innerHTML = '';
            } else if (totalPagado >= totalVenta) {
                const excedente = totalPagado - totalVenta;
                estadoEl.innerHTML = excedente > 0.001
                    ? `<span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> Pago completo · Vuelto: ${App.formatMoney(excedente)}</span>`
                    : `<span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> Pago exacto</span>`;
            } else {
                const falta = totalVenta - totalPagado;
                estadoEl.innerHTML = `<span class="badge badge-danger"><i class="bi bi-exclamation-triangle-fill"></i> Faltan: ${App.formatMoney(falta)}</span>`;
            }
        } else if (estadoEl) {
            estadoEl.innerHTML = '';
        }

        let puedeConfirmar = true;

        if (esDivisa) {
            const montoRecibidoDivisa = parseFloat(document.getElementById('monto-recibido-divisa')?.value) || 0;
            const totalDivisa = Math.round((totalVenta / tasaActual) * 100) / 100;
            puedeConfirmar = montoRecibidoDivisa >= totalDivisa;
        } else {
            puedeConfirmar = totalPagado >= totalVenta && totalVenta > 0;

            if (metodo === 'transferencia') {
                const ref = document.getElementById('referencia')?.value?.trim() || '';
                if (!ref) puedeConfirmar = false;

                if (configComprobante === 'obligatorio' && !document.getElementById('comprobante-file')?.value) {
                    puedeConfirmar = false;
                }
            }

            if (metodo === 'mixto') {
                const ref = document.getElementById('referencia')?.value?.trim() || '';
                if (!ref) puedeConfirmar = false;
                if (vuelto > totalRecibido) puedeConfirmar = false;

                if (configComprobante === 'obligatorio' && !document.getElementById('comprobante-file')?.value) {
                    puedeConfirmar = false;
                }
            }
        }

        // ⭐ Requiere factura: verificar cliente
        const chkFac = document.getElementById('requiere-factura');
        if (chkFac && chkFac.checked) {
            const clienteId = getClienteFacturaId();
            if (!clienteId) puedeConfirmar = false;
        }

        const btn = document.getElementById('btn-confirmar-cobro');
        if (btn) btn.disabled = !puedeConfirmar;
    }

    // ============================================================
    // MÉTODOS Y MONEDA
    // ============================================================
    function setMetodo(metodo) {
        metodoActual = metodo;

        document.querySelectorAll('.cobro-metodo').forEach(b => {
            const esActivo = b.dataset.metodo === metodo;
            b.classList.toggle('active', esActivo);
            b.classList.remove('btn-success', 'btn-secondary', 'btn-primary');
            b.classList.add(esActivo ? 'btn-success' : 'btn-secondary');
        });

        const esEfectivo = metodo === 'efectivo';
        const esTransferencia = metodo === 'transferencia';
        const esMixto = metodo === 'mixto';

        document.getElementById('cobro-efectivo').style.display = (esEfectivo || esMixto) ? '' : 'none';
        document.getElementById('cobro-transferencia').style.display = (esTransferencia || esMixto) ? '' : 'none';
        document.getElementById('cobro-mixto-wrap').style.display = esMixto ? '' : 'none';

        if (monedaActual !== 'CUP' && metodo !== 'efectivo') {
            setMoneda('CUP');
        }

        actualizarSelectorMoneda();
        recalcularTodo();
    }

    async function setMoneda(moneda) {
        monedaActual = moneda;

        document.querySelectorAll('.moneda-btn').forEach(b => {
            const esActivo = b.dataset.moneda === moneda;
            b.classList.toggle('active', esActivo);
            b.classList.remove('btn-primary', 'btn-secondary');
            b.classList.add(esActivo ? 'btn-primary' : 'btn-secondary');
        });

        const esDivisa = moneda !== 'CUP';

        if (esDivisa) {
            document.querySelectorAll('.cobro-metodo').forEach(b => {
                if (b.dataset.metodo !== 'efectivo') {
                    b.disabled = true;
                    b.style.opacity = .5;
                }
            });
            if (metodoActual !== 'efectivo') {
                metodoActual = 'efectivo';
                document.querySelectorAll('.cobro-metodo').forEach(b => {
                    const esEfectivo = b.dataset.metodo === 'efectivo';
                    b.classList.toggle('active', esEfectivo);
                    b.classList.remove('btn-success', 'btn-secondary', 'btn-primary');
                    b.classList.add(esEfectivo ? 'btn-success' : 'btn-secondary');
                });
            }
        } else {
            document.querySelectorAll('.cobro-metodo').forEach(b => {
                b.disabled = false;
                b.style.opacity = 1;
            });
        }

        document.getElementById('divisa-panel').style.display = esDivisa ? '' : 'none';
        document.getElementById('cobro-efectivo').style.display = esDivisa ? 'none' : '';
        document.getElementById('cobro-transferencia').style.display = 'none';
        document.getElementById('cobro-mixto-wrap').style.display = 'none';

        if (esDivisa) {
            const divisa = divisas.find(d => d.codigo === moneda);
            if (divisa) {
                tasaActual = divisa.tasa;

                const totalVenta = POS.obtenerTotal ? POS.obtenerTotal() : 0;
                const totalDivisaCalc = Math.round((totalVenta / tasaActual) * 100) / 100;

                document.getElementById('divisa-total-cup').textContent = App.formatMoney(totalVenta);
                document.getElementById('divisa-tasa').textContent = `${App.formatNumber(tasaActual)} CUP/${moneda}`;
                document.getElementById('divisa-total').textContent = `${divisa.simbolo}${App.formatNumber(totalDivisaCalc, 2)}`;
                document.getElementById('divisa-fuente').textContent = `Fuente: ${divisa.fuente || 'Configurada'} · ${divisa.origen === 'manual' ? 'Manual' : 'Automática'}`;

                document.getElementById('monto-recibido-divisa').value = '';
                document.getElementById('divisa-vuelto').textContent = App.formatMoney(0);
            }
        } else {
            tasaActual = 0;
        }

        recalcularTodo();
    }

    function actualizarSelectorMoneda() {
        const selector = document.getElementById('moneda-selector');
        if (!selector) return;

        const hayDivisas = divisas.length > 0;
        const esEfectivo = metodoActual === 'efectivo';

        selector.style.display = (hayDivisas && esEfectivo) ? '' : 'none';
    }

    function calcularVueltoDivisa() {
        if (monedaActual === 'CUP') return;

        const totalVenta = POS.obtenerTotal ? POS.obtenerTotal() : 0;
        const montoRecibido = parseFloat(document.getElementById('monto-recibido-divisa')?.value) || 0;

        const equivalenteRecibidoCup = montoRecibido * tasaActual;
        const vueltoCup = Math.max(0, equivalenteRecibidoCup - totalVenta);

        document.getElementById('divisa-vuelto').textContent = App.formatMoney(vueltoCup);
        recalcularTodo();
    }

    // ============================================================
    // ETIQUETA DINÁMICA ÚLTIMOS 4 DÍGITOS
    // ============================================================
    function actualizarEtiquetaUltimosDigitos() {
        const select = document.getElementById('metodo-detalle');
        const label = document.getElementById('label-ultimos-digitos');
        const help = document.getElementById('help-ultimos-digitos');
        const input = document.getElementById('ultimos-digitos');
        if (!select || !label || !input) return;

        const metodo = select.value;

        const textos = {
            'Transfermóvil': { label: 'Últimos 4 dígitos del teléfono', help: 'Los últimos 4 dígitos del teléfono desde el que se realizó la transferencia', placeholder: 'Ej: 4567' },
            'EnZona': { label: 'Últimos 4 dígitos del teléfono o tarjeta', help: 'Los últimos 4 dígitos del teléfono o tarjeta asociada a la transferencia', placeholder: 'Ej: 4567' },
            'Tarjeta débito': { label: 'Últimos 4 dígitos de la tarjeta', help: 'Los últimos 4 dígitos de la tarjeta con la que se realizó el pago', placeholder: 'Ej: 4532' },
            'Tarjeta crédito': { label: 'Últimos 4 dígitos de la tarjeta', help: 'Los últimos 4 dígitos de la tarjeta con la que se realizó el pago', placeholder: 'Ej: 4532' },
            'Otro': { label: 'Últimos 4 dígitos (referencia)', help: 'Los últimos 4 dígitos o el identificador del pago', placeholder: 'Ej: 4567' },
        };

        const config = textos[metodo] || textos['Otro'];
        label.textContent = config.label;
        if (help) help.textContent = config.help;
        input.placeholder = config.placeholder;
    }

    // ============================================================
    // COMPROBANTE
    // ============================================================
    async function subirComprobante(input) {
        if (!input.files || !input.files[0]) return;

        const archivo = input.files[0];
        const formData = new FormData();
        formData.append('comprobante', archivo);

        Toast.info('Subiendo...', archivo.name);

        const res = await Api.upload('api/pos_comprobante.php', formData);

        if (!res.success) {
            Toast.error('Error', res.message);
            input.value = '';
            return;
        }

        let hidden = document.getElementById('comprobante-file');
        if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.id = 'comprobante-file';
            document.body.appendChild(hidden);
        }
        hidden.value = res.data.ruta;

        const preview = document.getElementById('comprobante-preview');
        const esPdf = res.data.ext === 'pdf';

        preview.style.display = '';
        preview.innerHTML = `
            <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--success-light);border:1px solid var(--success);border-radius:var(--radius-md);">
                <i class="bi bi-${esPdf ? 'file-earmark-pdf' : 'image'}" style="font-size:32px;color:var(--success);"></i>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:13px;word-break:break-all;">${App.escapeHtml(archivo.name)}</div>
                    <div class="text-xs text-muted">${bytesLegible(res.data.tamano)}</div>
                </div>
                <button type="button" class="btn btn-danger btn-sm" onclick="POSCobro.eliminarComprobante()">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;

        Toast.success('Comprobante subido', 'Se adjuntará a la venta');
        input.value = '';
        recalcularTodo();
    }

    function eliminarComprobante() {
        const hidden = document.getElementById('comprobante-file');
        if (hidden) hidden.value = '';

        const preview = document.getElementById('comprobante-preview');
        if (preview) {
            preview.style.display = 'none';
            preview.innerHTML = '';
        }
        recalcularTodo();
    }

    function getComprobantePath() {
        return document.getElementById('comprobante-file')?.value || null;
    }

    function setConfigComprobante(valor) {
        configComprobante = valor;

        const wrap = document.getElementById('comprobante-wrap');
        const req = document.getElementById('comprobante-req');
        const hint = document.getElementById('comprobante-hint');

        if (!wrap) return;

        if (valor === 'no_permitir') {
            wrap.style.display = 'none';
        } else {
            wrap.style.display = '';
            if (req) req.style.display = valor === 'obligatorio' ? '' : 'none';
            if (hint) hint.textContent = valor === 'obligatorio'
                ? 'Obligatorio · JPG, PNG, WEBP o PDF · Máx 5 MB'
                : 'Opcional · JPG, PNG, WEBP o PDF · Máx 5 MB';
        }
    }

    // ============================================================
    // ⭐ REQUIERE FACTURA
    // ============================================================
    function toggleRequiereFactura() {
        const chk = document.getElementById('requiere-factura');
        const panel = document.getElementById('factura-panel');
        if (!chk || !panel) return;

        if (chk.checked) {
            panel.style.display = '';
            cargarClientes();
        } else {
            panel.style.display = 'none';
            const sel = document.getElementById('factura-cliente');
            if (sel) sel.value = '';
        }

        recalcularTodo();
    }

    async function cargarClientes() {
        const sel = document.getElementById('factura-cliente');
        if (!sel) return;

        sel.innerHTML = '<option value="">Cargando...</option>';

        const res = await Api.get('api/clientes.php?accion=catalogos&solo_activos=1');
        if (!res.success) {
            sel.innerHTML = '<option value="">Error al cargar</option>';
            return;
        }

        clientesCache = res.data.clientes || [];

        let html = '<option value="">Selecciona cliente</option>';
        clientesCache.forEach(c => {
            html += `<option value="${c.id}">${App.escapeHtml(c.nombre)}${c.nit ? ' · ' + App.escapeHtml(c.nit) : ''}</option>`;
        });
        sel.innerHTML = html;
    }

    function abrirNuevoCliente() {
        const modalId = 'modal-nuevo-cliente-pos';
        document.getElementById(modalId)?.remove();

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal">
                    <div class="modal-header">
                        <div class="modal-title">
                            <i class="bi bi-person-plus-fill text-primary"></i> Nuevo cliente
                        </div>
                        <button class="modal-close" onclick="document.getElementById('${modalId}').remove()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="field mb-3">
                            <label for="nc-nombre">Nombre <span class="req">*</span></label>
                            <input type="text" id="nc-nombre" maxlength="150" required autofocus>
                        </div>
                        <div class="field mb-3">
                            <label for="nc-nit">NIT / Documento</label>
                            <input type="text" id="nc-nit" maxlength="50">
                        </div>
                        <div class="field mb-3">
                            <label for="nc-telefono">Teléfono</label>
                            <input type="text" id="nc-telefono" maxlength="50">
                        </div>
                        <div class="field">
                            <label for="nc-direccion">Dirección</label>
                            <input type="text" id="nc-direccion" maxlength="255">
                        </div>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle-fill"></i>
                            <div>Los datos fiscales completos los puede ajustar el supervisor después.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.getElementById('${modalId}').remove()">Cancelar</button>
                        <button class="btn btn-primary" id="btn-guardar-cliente-pos" onclick="POSCobro.guardarNuevoCliente()">
                            <i class="bi bi-check-lg"></i> Guardar
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        setTimeout(() => document.getElementById('nc-nombre')?.focus(), 100);
    }

    async function guardarNuevoCliente() {
        const nombre = document.getElementById('nc-nombre').value.trim();
        const nit = document.getElementById('nc-nit').value.trim();
        const telefono = document.getElementById('nc-telefono').value.trim();
        const direccion = document.getElementById('nc-direccion').value.trim();

        if (nombre.length < 2) {
            Toast.warning('Falta nombre', 'El nombre es obligatorio');
            document.getElementById('nc-nombre').focus();
            return;
        }

        const btn = document.getElementById('btn-guardar-cliente-pos');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Guardando...';

        const res = await Api.post('api/clientes.php?accion=crear', {
            nombre, nit, telefono, direccion,
            tipo_persona: 'juridica',
            activo: 1,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Cliente creado', nombre);
        document.getElementById('modal-nuevo-cliente-pos')?.remove();

        await cargarClientes();
        const sel = document.getElementById('factura-cliente');
        if (sel) sel.value = res.data.id;
        recalcularTodo();
    }

    function getClienteFacturaId() {
        const chk = document.getElementById('requiere-factura');
        if (!chk || !chk.checked) return 0;

        const sel = document.getElementById('factura-cliente');
        return sel ? parseInt(sel.value) || 0 : 0;
    }

    // ============================================================
    // RESET MODAL
    // ============================================================
    function resetModal() {
        metodoActual = 'efectivo';
        monedaActual = 'CUP';
        tasaActual = 0;

        document.querySelectorAll('.cobro-metodo').forEach(b => {
            const esEfectivo = b.dataset.metodo === 'efectivo';
            b.classList.toggle('active', esEfectivo);
            b.classList.remove('btn-success', 'btn-secondary', 'btn-primary');
            b.classList.add(esEfectivo ? 'btn-success' : 'btn-secondary');
            b.disabled = false;
            b.style.opacity = 1;
        });

        document.querySelectorAll('.moneda-btn').forEach(b => {
            const esCup = b.dataset.moneda === 'CUP';
            b.classList.toggle('active', esCup);
            b.classList.remove('btn-primary', 'btn-secondary');
            b.classList.add(esCup ? 'btn-primary' : 'btn-secondary');
        });

        document.getElementById('cobro-efectivo').style.display = '';
        document.getElementById('cobro-transferencia').style.display = 'none';
        document.getElementById('cobro-mixto-wrap').style.display = 'none';
        document.getElementById('divisa-panel').style.display = 'none';

        ['monto-recibido-simple', 'monto-transferencia', 'referencia', 'ultimos-digitos', 'titular', 'monto-recibido-divisa'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });

        const selectMetodo = document.getElementById('metodo-detalle');
        if (selectMetodo) selectMetodo.value = 'Transfermóvil';

        eliminarComprobante();

        // ⭐ Reset requiere factura
        const chkFac = document.getElementById('requiere-factura');
        if (chkFac) chkFac.checked = false;
        const panelFac = document.getElementById('factura-panel');
        if (panelFac) panelFac.style.display = 'none';
        const selFac = document.getElementById('factura-cliente');
        if (selFac) selFac.value = '';

        actualizarEtiquetaUltimosDigitos();
        actualizarSelectorMoneda();
        recalcularTodo();

        // ⭐ Reset emitir comprobante
        const chkComp = document.getElementById('emitir-comprobante');
        if (chkComp) chkComp.checked = false;
        const panelComp = document.getElementById('comprobante-panel');
        if (panelComp) panelComp.style.display = 'none';

        ['comp-nombre', 'comp-documento', 'comp-telefono', 'comp-direccion'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
    }

    // ============================================================
    // UTILS
    // ============================================================
    function bytesLegible(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function renderBotonesDivisas() {
        const cont = document.getElementById('moneda-botones');
        if (!cont) return;

        let html = `
            <button type="button" class="btn btn-primary btn-sm moneda-btn active" data-moneda="CUP" onclick="POSCobro.setMoneda('CUP')">
                <i class="bi bi-currency-dollar"></i> CUP
            </button>
        `;

        divisas.forEach(d => {
            html += `
                <button type="button" class="btn btn-secondary btn-sm moneda-btn" data-moneda="${d.codigo}" onclick="POSCobro.setMoneda('${d.codigo}')">
                    <i class="bi bi-currency-exchange"></i> ${d.codigo}
                </button>
            `;
        });

        cont.innerHTML = html;
    }

    // ============================================================
    // ⭐ EMITIR COMPROBANTE
    // ============================================================
    function toggleEmitirComprobante() {
        const chk = document.getElementById('emitir-comprobante');
        const panel = document.getElementById('comprobante-panel');
        if (!chk || !panel) return;

        panel.style.display = chk.checked ? '' : 'none';
    }

    function getDatosComprobante() {
        const chk = document.getElementById('emitir-comprobante');
        if (!chk || !chk.checked) return null;

        return {
            nombre:    document.getElementById('comp-nombre')?.value.trim() || '',
            documento: document.getElementById('comp-documento')?.value.trim() || '',
            telefono:  document.getElementById('comp-telefono')?.value.trim() || '',
            direccion: document.getElementById('comp-direccion')?.value.trim() || '',
        };
    }

    // ============================================================
    // EXPORTS
    // ============================================================
    return {
        cargarDivisas,
        renderBotonesDivisas,
        recalcularTodo,
        setMetodo,
        setMoneda,
        actualizarEtiquetaUltimosDigitos,
        subirComprobante,
        eliminarComprobante,
        getComprobantePath,
        setConfigComprobante,
        calcularVueltoDivisa,
        resetModal,
        toggleRequiereFactura,
        cargarClientes,
        abrirNuevoCliente,
        guardarNuevoCliente,
        getClienteFacturaId,
        toggleEmitirComprobante,
        getDatosComprobante,
        getMetodoActual: () => metodoActual,
        getMonedaActual: () => monedaActual,
        getTasaActual: () => tasaActual,
    };
})();

window.POSCobro = POSCobro;