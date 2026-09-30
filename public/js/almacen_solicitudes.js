/**
 * IPV - Solicitudes de Traslado (Almacenero)
 */

const AlmacenSolicitudes = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let lista = [];
    let solicitudActual = null;

    // ═══════════════════════════════════════════════════════════
    // CARGAR LISTA
    // ═══════════════════════════════════════════════════════════
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

    // ═══════════════════════════════════════════════════════════
    // RENDER TABLA
    // ═══════════════════════════════════════════════════════════
    function render() {
        const cont = document.getElementById('tabla-solicitudes');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>Sin solicitudes</h3>
                <p>No hay solicitudes que coincidan con los filtros.</p>
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
                            <th>PV</th>
                            <th>Solicitante</th>
                            <th>Fecha</th>
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
                                    <td>${App.escapeHtml(s.pv)}</td>
                                    <td class="text-muted text-sm">${App.escapeHtml(s.solicitado_por_nombre)}</td>
                                    <td class="text-muted text-xs">${App.formatDate(s.fecha_solicitud)}</td>
                                    <td class="text-right">${s.total_items}</td>
                                    <td class="text-right">
                                        <strong>${s.total_unidades_solicitadas}</strong>
                                        ${s.total_unidades_despachadas > 0
                                            ? `<div class="text-xs text-muted">desp: ${s.total_unidades_despachadas}</div>`
                                            : ''}
                                    </td>
                                    <td><span class="badge badge-${color}">${label}</span></td>
                                    <td class="text-right">
                                        <button class="btn btn-ghost btn-icon"
                                                onclick="AlmacenSolicitudes.verDetalle(${s.id})"
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

    // ═══════════════════════════════════════════════════════════
    // VER DETALLE
    // ═══════════════════════════════════════════════════════════
    async function verDetalle(id) {
        const res = await Api.get(`api/solicitudes.php?accion=obtener&id=${id}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        solicitudActual = res.data;
        const s = solicitudActual;

        document.getElementById('sol-titulo').textContent = `Solicitud ${s.folio}`;

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

        // Avisos según estado
        if (s.estado === 'rechazado' && s.motivo_rechazo) {
            html += `
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle-fill"></i>
                    <div>
                        <strong>Rechazada</strong>
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
                        <strong>Observaciones del almacén:</strong>
                        <div>${App.escapeHtml(s.observaciones_almacen)}</div>
                    </div>
                </div>
            `;
        }

        // Tabla de items
        const puedeAprobar = s.estado === 'solicitado';
        const puedeDespachar = s.estado === 'aprobado';

        html += `
            <h4 style="font-size:15px;margin-bottom:10px;">
                <i class="bi bi-box-seam text-primary"></i> Productos solicitados
            </h4>
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Unidad</th>
                            <th class="text-right">Stock almacén</th>
                            <th class="text-right">Solicitado</th>
                            <th class="text-right">Aprobado</th>
                            <th class="text-right">Despachado</th>
                            <th class="text-right">Recibido</th>
                            ${puedeAprobar ? '<th class="text-right" style="width:140px;">Cant. a aprobar</th>' : ''}
                        </tr>
                    </thead>
                    <tbody>
                        ${s.detalle.map(d => {
                            const stockAlm = parseInt(d.stock_almacen_actual) || 0;
                            const sol = parseInt(d.cantidad_solicitada) || 0;
                            const sinStock = stockAlm < sol;
                            const unidad = d.unidad_medida || 'Unidad';

                            return `
                                <tr data-detalle-id="${d.id}">
                                    <td>
                                        <div style="font-weight:600;">${App.escapeHtml(d.producto)}</div>
                                        <div class="text-xs text-muted">${App.escapeHtml(d.codigo_barras || '—')}</div>
                                    </td>
                                    <td class="text-muted text-sm">${App.escapeHtml(unidad)}</td>
                                    <td class="text-right" style="${sinStock ? 'color:var(--danger);font-weight:700;' : ''}">
                                        ${stockAlm} ${App.escapeHtml(unidad)}
                                    </td>
                                    <td class="text-right"><strong>${sol} ${App.escapeHtml(unidad)}</strong></td>
                                    <td class="text-right">${d.cantidad_aprobada ? d.cantidad_aprobada + ' ' + App.escapeHtml(unidad) : '—'}</td>
                                    <td class="text-right">${d.cantidad_despachada ? d.cantidad_despachada + ' ' + App.escapeHtml(unidad) : '—'}</td>
                                    <td class="text-right">${d.cantidad_recibida ? d.cantidad_recibida + ' ' + App.escapeHtml(unidad) : '—'}</td>
                                    ${puedeAprobar ? `
                                        <td class="text-right">
                                            <input type="number"
                                                   class="calc-input aprobar-input"
                                                   data-detalle-id="${d.id}"
                                                   data-max="${sol}"
                                                   value="${sol}"
                                                   min="0"
                                                   max="${sol}"
                                                   style="width:100px;text-align:center;padding:6px;"
                                                   oninput="AlmacenSolicitudes.validarCantidadAprobada(this)">
                                        </td>
                                    ` : ''}
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;

        document.getElementById('sol-contenido').innerHTML = html;

        // Footer con acciones
        let footer = '';

        if (s.estado === 'solicitado') {
            footer = `
                <button class="btn btn-danger" onclick="AlmacenSolicitudes.abrirRechazar(${s.id})">
                    <i class="bi bi-x-lg"></i> Rechazar
                </button>
                <button class="btn btn-primary" onclick="AlmacenSolicitudes.aprobar(${s.id})">
                    <i class="bi bi-check-lg"></i> Aprobar solicitud
                </button>
            `;
        } else if (s.estado === 'aprobado') {
            footer = `
                <button class="btn btn-success" onclick="AlmacenSolicitudes.despachar(${s.id})">
                    <i class="bi bi-truck"></i> Despachar mercancía
                </button>
            `;
        } else if (s.estado === 'despachado' || s.estado === 'despachado_parcial') {
            footer = `
                <div class="text-muted text-sm" style="padding:8px;">
                    <i class="bi bi-hourglass-split"></i>
                    Esperando confirmación de recepción por el supervisor del PV
                </div>
            `;
        }

        footer += `
            <button class="btn btn-secondary" onclick="AlmacenSolicitudes.cerrarModal()">
                Cerrar
            </button>
        `;

        document.getElementById('sol-footer').innerHTML = footer;

        document.getElementById('modal-solicitud').classList.add('active');
    }

    function cerrarModal() {
        document.getElementById('modal-solicitud').classList.remove('active');
        solicitudActual = null;
    }

    // ═══════════════════════════════════════════════════════════
    // VALIDAR CANTIDAD A APROBAR
    // ═══════════════════════════════════════════════════════════
    function validarCantidadAprobada(input) {
        const max = parseInt(input.dataset.max) || 0;
        let val = parseInt(input.value) || 0;

        if (val < 0) val = 0;
        if (val > max) val = max;

        input.value = val;
    }

    // ═══════════════════════════════════════════════════════════
    // APROBAR
    // ═══════════════════════════════════════════════════════════
    async function aprobar(id) {
        const inputs = document.querySelectorAll('.aprobar-input');
        const items = [];

        inputs.forEach(inp => {
            items.push({
                id: parseInt(inp.dataset.detalleId),
                cantidad_aprobada: parseInt(inp.value) || 0,
            });
        });

        const totalAprobado = items.reduce((sum, i) => sum + i.cantidad_aprobada, 0);

        if (totalAprobado === 0) {
            Toast.warning('Nada aprobado', 'Debes aprobar al menos 1 unidad');
            return;
        }

        const esParcial = items.some(i => {
            const fila = document.querySelector(`tr[data-detalle-id="${i.id}"]`);
            const solicitado = parseInt(fila?.querySelector('td:nth-child(4) strong')?.textContent) || 0;
            return i.cantidad_aprobada < solicitado;
        });

        let mensaje = `¿Aprobar esta solicitud?\n\nTotal a aprobar: ${totalAprobado} unidades\n`;
        if (esParcial) {
            mensaje += '\n⚠️ Es una aprobación PARCIAL (algunos productos no se aprueban completos).';
        }

        const ok = await App.confirmar(mensaje, '✅ Confirmar aprobación');
        if (!ok) return;

        const btn = document.querySelector('#sol-footer .btn-primary');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Aprobando...';
        }

        const res = await Api.post('api/solicitudes.php?accion=aprobar', {
            id,
            items,
            observaciones: '',
        });

        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Aprobar solicitud';
        }

        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Aprobada', `${res.data.total_aprobado} unidades aprobadas`);
        cargar();
        verDetalle(id);   // ⭐ reabrir el detalle automáticamente
    }

    // ═══════════════════════════════════════════════════════════
    // DESPACHAR
    // ═══════════════════════════════════════════════════════════
    async function despachar(id) {
        const ok = await App.confirmar(
            '¿Confirmar el despacho de la mercancía?\n\nSe descontará el stock del almacén y se notificará al PV.',
            '🚚 Confirmar despacho'
        );
        if (!ok) return;

        const btn = document.querySelector('#sol-footer .btn-success');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Despachando...';
        }

        const res = await Api.post('api/solicitudes.php?accion=despachar', {
            id,
            observaciones: '',
        });

        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-truck"></i> Despachar mercancía';
        }

        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Despachada', `${res.data.total_despachado} unidades en camino`);
        cargar();
        verDetalle(id);   // ⭐ reabrir el detalle
    }

    // ═══════════════════════════════════════════════════════════
    // RECHAZAR
    // ═══════════════════════════════════════════════════════════
    function abrirRechazar(id) {
        document.getElementById('rech-id').value = id;
        document.getElementById('rech-motivo').value = '';
        document.getElementById('modal-rechazar').classList.add('active');
        setTimeout(() => document.getElementById('rech-motivo')?.focus(), 100);
    }

    async function confirmarRechazo() {
        const id = parseInt(document.getElementById('rech-id').value);
        const motivo = document.getElementById('rech-motivo').value.trim();

        if (motivo.length < 5) {
            Toast.warning('Motivo muy corto', 'Mínimo 5 caracteres');
            return;
        }

        const btn = document.getElementById('btn-confirmar-rechazo');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Rechazando...';

        const res = await Api.post('api/solicitudes.php?accion=rechazar', {
            id,
            motivo,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-x-lg"></i> Rechazar';

        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Rechazada', 'El solicitante fue notificado');
        document.getElementById('modal-rechazar').classList.remove('active');
        cargar();
        verDetalle(id);   // ⭐ reabrir el detalle con el nuevo estado
    }

    // ═══════════════════════════════════════════════════════════
    // FILTROS
    // ═══════════════════════════════════════════════════════════
    function limpiar() {
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-estado').value = '';
        document.getElementById('filtro-desde').value = '';
        document.getElementById('filtro-hasta').value = '';
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // EVENTOS
    // ═══════════════════════════════════════════════════════════
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
        cerrarModal,
        validarCantidadAprobada,
        aprobar,
        despachar,
        abrirRechazar,
        confirmarRechazo,
    };
})();

window.AlmacenSolicitudes = AlmacenSolicitudes;