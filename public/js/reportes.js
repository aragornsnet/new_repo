/**
 * IPV - Generador de reportes (con vista previa)
 */

const Reportes = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let tipoActual = null;

    const NOMBRES = {
        ventas_fechas: 'Ventas por fecha',
        ventas_vendedor: 'Ventas por vendedor',
        ventas_pv: 'Ventas por punto de venta',
        top_productos: 'Top productos',
        caja_dia: 'Caja del día',
        movimientos: 'Movimientos de inventario',
        turnos: 'Turnos',
        transferencias: 'Transferencias',
        stock_bajo: 'Productos con stock bajo',
        auditoria: 'Auditoría',
        mis_ventas: 'Mis ventas',
        mi_caja: 'Mi caja',
        facturas_fechas: 'Facturas por fecha',
        facturas_cliente: 'Facturas por cliente',
        facturas_vendedor: 'Facturas por vendedor',
        comprobantes_fechas: 'Comprobantes por fecha',
        comprobantes_vendedor: 'Comprobantes por vendedor',
        comprobantes_pv: 'Comprobantes por punto de venta',
    };

    // ============================================================
    // SELECCIONAR TIPO
    // ============================================================
    function seleccionar(tipo) {
        tipoActual = tipo;

        document.querySelectorAll('.reporte-btn').forEach(b => {
            const activo = b.dataset.tipo === tipo;
            b.classList.toggle('btn-primary', activo);
            b.classList.toggle('btn-secondary', !activo);
        });

        const nombreEl = document.getElementById('reporte-nombre');
        if (nombreEl) nombreEl.textContent = NOMBRES[tipo] || tipo;

        const wrap = document.getElementById('filtros-wrap');
        if (wrap) wrap.style.display = '';

        renderFiltros(tipo);
    }

    // ============================================================
    // RENDER FILTROS
    // ============================================================
    function renderFiltros(tipo) {
        const cont = document.getElementById('filtros-contenido');
        if (!cont) return;

        const data = window.REPORTES_DATA || {};

        const hoy = new Date().toISOString().split('T')[0];
        const inicioMes = new Date();
        inicioMes.setDate(1);
        const inicioMesStr = inicioMes.toISOString().split('T')[0];

        const filtroFechas = `
            <div class="field">
                <label>Desde</label>
                <input type="date" id="f-desde" value="${inicioMesStr}">
            </div>
            <div class="field">
                <label>Hasta</label>
                <input type="date" id="f-hasta" value="${hoy}">
            </div>
        `;

        const filtroPV = data.puntos ? `
            <div class="field">
                <label>Punto de venta</label>
                <select id="f-pv">
                    <option value="">Todos</option>
                    ${data.puntos.map(p => `<option value="${p.id}">${App.escapeHtml(p.nombre)}</option>`).join('')}
                </select>
            </div>
        ` : '';

        const filtroVendedor = data.vendedores ? `
            <div class="field">
                <label>Vendedor</label>
                <select id="f-vendedor">
                    <option value="">Todos</option>
                    ${data.vendedores.map(v => `<option value="${v.id}">${App.escapeHtml(v.nombre)}</option>`).join('')}
                </select>
            </div>
        ` : '';

        let html = '';

        switch (tipo) {
            case 'ventas_fechas':
                html = `<div class="form-grid">${filtroFechas}${filtroPV}${filtroVendedor}</div>`;
                break;

            case 'ventas_vendedor':
            case 'ventas_pv':
                html = `<div class="form-grid">${filtroFechas}${filtroPV}</div>`;
                break;

            case 'top_productos':
                html = `<div class="form-grid">${filtroFechas}<div class="field"><label>Límite</label><input type="number" id="f-limit" value="20" min="5" max="100"></div></div>`;
                break;

            case 'caja_dia':
                html = `<div class="form-grid">
                    <div class="field"><label>Desde</label><input type="date" id="f-desde" value="${hoy}"></div>
                    <div class="field"><label>Hasta</label><input type="date" id="f-hasta" value="${hoy}"></div>
                    ${filtroPV}
                </div>`;
                break;

            case 'movimientos':
                html = `<div class="form-grid">
                    ${filtroFechas}${filtroPV}
                    <div class="field"><label>Tipo</label>
                        <select id="f-tipo">
                            <option value="">Todos</option>
                            <option value="entrada">Entrada</option>
                            <option value="baja">Baja</option>
                            <option value="ajuste">Ajuste</option>
                        </select>
                    </div>
                </div>`;
                break;

            case 'turnos':
                html = `<div class="form-grid">
                    ${filtroFechas}
                    <div class="field"><label>Estado</label>
                        <select id="f-estado">
                            <option value="cerrado">Cerrados</option>
                            <option value="abierto">Abiertos</option>
                            <option value="">Todos</option>
                        </select>
                    </div>
                </div>`;
                break;

            case 'transferencias':
                html = `<div class="form-grid">
                    ${filtroFechas}
                    <div class="field"><label>Estado</label>
                        <select id="f-estado">
                            <option value="">Todas</option>
                            <option value="pendiente">Pendientes</option>
                            <option value="verificada">Verificadas</option>
                            <option value="rechazada">Rechazadas</option>
                        </select>
                    </div>
                </div>`;
                break;

            case 'stock_bajo':
                html = `<div class="form-grid">
                    <div class="field"><label>Filtro</label>
                        <select id="f-filtro">
                            <option value="bajo">Stock bajo</option>
                            <option value="negativo">Stock negativo</option>
                            <option value="todos">Todos</option>
                        </select>
                    </div>
                    ${filtroPV}
                </div>`;
                break;

            case 'auditoria':
                const usuariosOpts = data.usuarios ? data.usuarios.map(u => `<option value="${u.id}">${App.escapeHtml(u.nombre)}</option>`).join('') : '';
                html = `<div class="form-grid">
                    <div class="field"><label>Desde</label><input type="date" id="f-desde" value="${hoy}"></div>
                    <div class="field"><label>Hasta</label><input type="date" id="f-hasta" value="${hoy}"></div>
                    <div class="field"><label>Usuario</label>
                        <select id="f-usuario"><option value="">Todos</option>${usuariosOpts}</select>
                    </div>
                    <div class="field"><label>Acción</label><input type="text" id="f-accion" placeholder="Ej: login"></div>
                </div>`;
                break;

            case 'facturas_fechas':
            case 'facturas_cliente':
            case 'facturas_vendedor':
                const clientesOpts = data.clientes ? data.clientes.map(c =>
                    `<option value="${c.id}">${App.escapeHtml(c.nombre)}</option>`
                ).join('') : '';

                html = `<div class="form-grid">
                    ${filtroFechas}
                    <div class="field">
                        <label>Cliente</label>
                        <select id="f-cliente">
                            <option value="">Todos</option>
                            ${clientesOpts}
                        </select>
                    </div>
                    ${filtroVendedor}
                    <div class="field">
                        <label>Estado</label>
                        <select id="f-estado">
                            <option value="">Todos</option>
                            <option value="emitida">Emitidas</option>
                            <option value="parcial">Parciales</option>
                            <option value="pagada">Pagadas</option>
                            ${tipo === 'facturas_fechas' ? '<option value="anulada">Anuladas</option>' : ''}
                        </select>
                    </div>
                </div>`;
                break;

            case 'mis_ventas':
                html = `<div class="form-grid">${filtroFechas}</div>`;
                break;

            case 'mi_caja':
                html = `<div class="alert alert-info"><i class="bi bi-info-circle"></i> Este reporte muestra el estado actual de tu caja (turno abierto).</div>`;
                break;

            case 'comprobantes_fechas':
                html = `<div class="form-grid">
                    ${filtroFechas}
                    ${filtroPV}
                    ${filtroVendedor}
                    <div class="field">
                        <label>Estado</label>
                        <select id="f-estado">
                            <option value="">Todos</option>
                            <option value="emitido">Emitidos</option>
                            <option value="anulado">Anulados</option>
                        </select>
                    </div>
                </div>`;
                break;

            case 'comprobantes_vendedor':
            case 'comprobantes_pv':
                html = `<div class="form-grid">
                    ${filtroFechas}
                    ${filtroPV}
                </div>`;
                break;

            default:
                html = '<div class="alert alert-warning">Sin filtros</div>';
        }

        cont.innerHTML = html;
    }

    // ============================================================
    // RECOGER PARÁMETROS DE FILTROS
    // ============================================================
    function recogerParametros() {
        const params = new URLSearchParams();
        params.append('tipo', tipoActual);

        const desde = document.getElementById('f-desde')?.value;
        const hasta = document.getElementById('f-hasta')?.value;
        const pv = document.getElementById('f-pv')?.value;
        const vendedor = document.getElementById('f-vendedor')?.value;
        const limit = document.getElementById('f-limit')?.value;
        const tipoMov = document.getElementById('f-tipo')?.value;
        const estado = document.getElementById('f-estado')?.value;
        const filtro = document.getElementById('f-filtro')?.value;
        const usuario = document.getElementById('f-usuario')?.value;
        const accion = document.getElementById('f-accion')?.value;
        const cliente = document.getElementById('f-cliente')?.value;

        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);
        if (pv) params.append('pv_id', pv);
        if (vendedor) params.append('vendedor_id', vendedor);
        if (limit) params.append('limit', limit);
        if (tipoMov) params.append('tipo_mov', tipoMov);
        if (estado) params.append('estado_turno', estado);
        if (filtro) params.append('filtro_stock', filtro);
        if (usuario) params.append('usuario_id', usuario);
        if (accion) params.append('accion_filtro', accion);
        if (cliente) params.append('cliente_id', cliente);
        if (estado) params.append('estado', estado);

        return params;
    }

    // ============================================================
    // VER (vista previa en modal)
    // ============================================================
    async function ver() {
        if (!tipoActual) {
            Toast.warning('Selecciona un reporte', 'Primero elige el tipo de reporte');
            return;
        }

        const params = recogerParametros();

        const modalId = 'modal-reporte-preview';
        document.getElementById(modalId)?.remove();

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal modal-xl">
                    <div class="modal-header">
                        <div class="modal-title">
                            <i class="bi bi-eye"></i>
                            <span id="preview-titulo">Vista previa...</span>
                        </div>
                        <button class="modal-close" onclick="document.getElementById('${modalId}').remove()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body" id="preview-contenido">
                        <div class="empty-state">
                            <div class="spinner spinner-lg"></div>
                            <p class="text-muted mt-3">Cargando datos...</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.getElementById('${modalId}').remove()">
                            Cerrar
                        </button>
                        <button class="btn btn-danger" onclick="Reportes.generar('pdf')">
                            <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
                        </button>
                        <button class="btn btn-success" onclick="Reportes.generar('excel')">
                            <i class="bi bi-file-earmark-excel"></i> Descargar Excel
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const res = await Api.get('api/reportes_datos.php?' + params.toString());

        const cont = document.getElementById('preview-contenido');

        if (!res.success) {
            cont.innerHTML = `<div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                ${App.escapeHtml(res.message)}
            </div>`;
            return;
        }

        const d = res.data;
        document.getElementById('preview-titulo').textContent = d.titulo || 'Vista previa';

        const tablaHtml = `
            <div style="margin-bottom:16px;">
                <h3 style="font-size:18px;font-weight:700;">${App.escapeHtml(d.titulo || '')}</h3>
                ${d.subtitulo ? `<div class="text-muted text-sm">${App.escapeHtml(d.subtitulo)}</div>` : ''}
            </div>

            ${d.resumen && d.resumen.length ? `
                <div style="display:grid;grid-template-columns:repeat(${Math.min(d.resumen.length, 4)}, 1fr);gap:10px;margin-bottom:16px;">
                    ${d.resumen.map(r => `
                        <div style="padding:12px;background:var(--surface-2);border-radius:var(--radius-md);">
                            <div class="text-xs text-muted">${App.escapeHtml(r.label)}</div>
                            <div style="font-weight:700;font-size:16px;">${App.escapeHtml(r.valor)}</div>
                        </div>
                    `).join('')}
                </div>
            ` : ''}

            ${d.nota ? `
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> ${App.escapeHtml(d.nota)}
                </div>
            ` : ''}

            ${d.filas.length === 0 ? `
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p class="text-muted mt-3">No hay datos para mostrar con estos filtros</p>
                </div>
            ` : `
                <div class="tabla-wrap" style="max-height:500px;overflow-y:auto;">
                    <table class="tabla">
                        <thead style="position:sticky;top:0;background:var(--surface-2);z-index:1;">
                            <tr>
                                ${d.columnas.map((c, i) => `<th class="${i > 0 ? 'text-right' : ''}">${App.escapeHtml(c)}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${d.filas.map(fila => `
                                <tr>
                                    ${fila.map((celda, i) => `
                                        <td class="${i > 0 ? 'text-right' : ''}">${App.escapeHtml(String(celda))}</td>
                                    `).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>

                ${d.totales ? `
                    <div style="margin-top:20px;padding:16px;background:var(--surface-2);border-radius:var(--radius-md);">
                        ${Object.entries(d.totales).map(([etiqueta, valor]) => `
                            <div style="display:flex;justify-content:space-between;padding:4px 0;">
                                <span>${App.escapeHtml(etiqueta)}:</span>
                                <strong>${App.escapeHtml(String(valor))}</strong>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}

                <div class="text-muted text-xs text-center mt-3">
                    Mostrando ${d.filas.length} fila${d.filas.length !== 1 ? 's' : ''}
                </div>
            `}
        `;

        cont.innerHTML = tablaHtml;
    }

    // ============================================================
    // GENERAR (descarga)
    // ============================================================
    function generar(formato) {
        if (!tipoActual) {
            Toast.warning('Selecciona un reporte', 'Primero elige el tipo de reporte');
            return;
        }

        const params = recogerParametros();
        params.append('formato', formato);

        Toast.info('Generando reporte...', 'Espera un momento');
        window.open(BASE_URL + 'api/reportes.php?' + params.toString(), '_blank');
    }

    return { seleccionar, generar, ver };
})();

window.Reportes = Reportes;