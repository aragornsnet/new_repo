/**
 * IPV - Auditoría
 */

const Auditoria = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let paginaActual = 1;
    let totalPags = 1;
    let chartActividad = null;

    async function cargar(pagina = 1) {
        paginaActual = pagina;

        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const usuarioId = document.getElementById('filtro-usuario')?.value || '';
        const accionFiltro = document.getElementById('filtro-accion')?.value || '';
        const tabla = document.getElementById('filtro-tabla')?.value || '';
        const desde = document.getElementById('filtro-desde')?.value || '';
        const hasta = document.getElementById('filtro-hasta')?.value || '';

        const params = new URLSearchParams();
        params.append('pagina', paginaActual);
        params.append('por_pagina', 50);
        if (q) params.append('q', q);
        if (usuarioId) params.append('usuario_id', usuarioId);
        if (accionFiltro) params.append('accion_filtro', accionFiltro);
        if (tabla) params.append('tabla', tabla);
        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);

        const cont = document.getElementById('tabla-auditoria');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/auditoria.php?accion=listar&${params}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        totalPags = res.data.total_pags || 1;
        renderTabla(res.data.datos, res.data.total);
        renderPaginacion();
    }

    function renderTabla(datos, total) {
        const cont = document.getElementById('tabla-auditoria');

        if (!datos.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>Sin registros</h3>
                <p>No hay acciones que coincidan con los filtros.</p>
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="card-header" style="margin-top:0;padding-top:0;">
                <div class="card-title"><i class="bi bi-list-ul"></i> ${total} registro${total !== 1 ? 's' : ''}</div>
            </div>
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Tabla</th>
                            <th>IP</th>
                            <th class="text-right">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${datos.map(a => `
                            <tr>
                                <td class="text-muted text-xs" style="white-space:nowrap;">${App.formatDate(a.fecha)}</td>
                                <td>
                                    ${a.usuario 
                                        ? `<div style="display:flex;align-items:center;gap:8px;">
                                            <div class="avatar avatar-sm" style="background:${colorAvatar(a.usuario)};">${inicial(a.usuario)}</div>
                                            <div>
                                                <div style="font-weight:600;font-size:13px;">${App.escapeHtml(a.usuario)}</div>
                                                <div class="text-xs text-muted">${App.escapeHtml(a.usuario_email || '')}</div>
                                            </div>
                                           </div>`
                                        : '<span class="badge badge-neutral">Sistema</span>'}
                                </td>
                                <td>${renderAccionBadge(a.accion)}</td>
                                <td class="text-muted text-xs">${App.escapeHtml(a.tabla_afectada || '—')}${a.registro_id ? ' #' + a.registro_id : ''}</td>
                                <td class="text-muted text-xs" style="font-family:var(--font-mono);">${App.escapeHtml(a.ip || '—')}</td>
                                <td class="text-right">
                                    <button class="btn btn-ghost btn-icon" onclick="Auditoria.verDetalle(${a.id})" title="Ver detalle">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderAccionBadge(accion) {
        const map = {
            'login':                        ['badge-success', 'Inicio de sesión'],
            'logout':                       ['badge-neutral', 'Cierre de sesión'],
            'login_fallido':                ['badge-danger',  'Login fallido'],
            'usuario_creado':               ['badge-primary', 'Usuario creado'],
            'usuario_actualizado':          ['badge-primary', 'Usuario actualizado'],
            'usuario_activado':             ['badge-success', 'Usuario activado'],
            'usuario_desactivado':          ['badge-warning', 'Usuario desactivado'],
            'password_reseteada':           ['badge-warning', 'Password reseteada'],
            'pv_creado':                    ['badge-primary', 'PV creado'],
            'pv_actualizado':               ['badge-primary', 'PV actualizado'],
            'pv_activado':                  ['badge-success', 'PV activado'],
            'pv_desactivado':               ['badge-warning', 'PV desactivado'],
            'pv_eliminado':                 ['badge-danger',  'PV eliminado'],
            'categoria_creada':             ['badge-primary', 'Categoría creada'],
            'categoria_actualizada':        ['badge-primary', 'Categoría actualizada'],
            'categoria_activada':           ['badge-success', 'Categoría activada'],
            'categoria_desactivada':        ['badge-warning', 'Categoría desactivada'],
            'categoria_eliminada':          ['badge-danger',  'Categoría eliminada'],
            'producto_creado':              ['badge-primary', 'Producto creado'],
            'producto_actualizado':         ['badge-primary', 'Producto actualizado'],
            'producto_activado':            ['badge-success', 'Producto activado'],
            'producto_desactivado':         ['badge-warning', 'Producto desactivado'],
            'producto_eliminado':           ['badge-danger',  'Producto eliminado'],
            'personalizacion_actualizada':  ['badge-info',    'Personalización'],
            'personalizacion_imagen_subida':['badge-info',    'Imagen subida'],
            'personalizacion_imagen_eliminada': ['badge-warning', 'Imagen eliminada'],
            'personalizacion_restaurada':   ['badge-warning', 'Personalización restaurada'],
            'venta_creada':                 ['badge-success', 'Venta'],
            'venta_cancelada':              ['badge-danger',  'Venta cancelada'],
            'turno_abierto':                ['badge-success', 'Turno abierto'],
            'turno_cerrado':                ['badge-info',    'Turno cerrado'],
            'turno_forzado':                ['badge-danger',  'Turno forzado'],
            'transferencia_verificada':     ['badge-success', 'Transferencia OK'],
            'transferencia_rechazada':      ['badge-danger',  'Transferencia rechazada'],
            'ajuste_inventario':            ['badge-warning', 'Ajuste inventario'],
            'backup_creado':                ['badge-info',    'Backup creado'],
            'backup_restaurado':            ['badge-danger',  'Backup restaurado'],
            'backup_eliminado':             ['badge-warning', 'Backup eliminado'],
            'config_actualizada':           ['badge-info',    'Config actualizada'],
            'config_restaurada':            ['badge-warning', 'Config restaurada'],
        };

        const [clase, etiqueta] = map[accion] || ['badge-neutral', accion];
        return `<span class="badge ${clase}">${App.escapeHtml(etiqueta)}</span>`;
    }

    function renderPaginacion() {
        const cont = document.getElementById('paginacion');
        if (!cont) return;

        if (totalPags <= 1) {
            cont.innerHTML = '';
            return;
        }

        const botones = [];
        const maxVisible = 7;
        let inicio = Math.max(1, paginaActual - 3);
        let fin = Math.min(totalPags, inicio + maxVisible - 1);
        if (fin - inicio < maxVisible - 1) inicio = Math.max(1, fin - maxVisible + 1);

        botones.push(`<button class="btn btn-secondary btn-sm" onclick="Auditoria.cargar(${Math.max(1, paginaActual - 1)})" ${paginaActual === 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button>`);

        if (inicio > 1) {
            botones.push(`<button class="btn btn-ghost btn-sm" onclick="Auditoria.cargar(1)">1</button>`);
            if (inicio > 2) botones.push(`<span class="text-muted" style="padding:0 6px;">…</span>`);
        }

        for (let i = inicio; i <= fin; i++) {
            botones.push(`<button class="btn ${i === paginaActual ? 'btn-primary' : 'btn-ghost'} btn-sm" onclick="Auditoria.cargar(${i})">${i}</button>`);
        }

        if (fin < totalPags) {
            if (fin < totalPags - 1) botones.push(`<span class="text-muted" style="padding:0 6px;">…</span>`);
            botones.push(`<button class="btn btn-ghost btn-sm" onclick="Auditoria.cargar(${totalPags})">${totalPags}</button>`);
        }

        botones.push(`<button class="btn btn-secondary btn-sm" onclick="Auditoria.cargar(${Math.min(totalPags, paginaActual + 1)})" ${paginaActual === totalPags ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button>`);

        cont.innerHTML = `
            <div style="display:flex;justify-content:center;align-items:center;gap:4px;flex-wrap:wrap;">
                ${botones.join('')}
            </div>
            <div class="text-center text-muted text-xs mt-2">
                Página ${paginaActual} de ${totalPags}
            </div>
        `;
    }

    async function verDetalle(id) {
        const res = await Api.get(`api/auditoria.php?accion=obtener&id=${id}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        const a = res.data;
        const modalId = 'modal-auditoria-detalle';

        let detalleHtml = '<span class="text-muted">Sin detalle</span>';
        if (a.detalle) {
            try {
                const json = JSON.parse(a.detalle);
                detalleHtml = `<pre style="font-size:12px;max-height:300px;overflow:auto;">${App.escapeHtml(JSON.stringify(json, null, 2))}</pre>`;
            } catch (e) {
                detalleHtml = `<pre style="font-size:12px;max-height:300px;overflow:auto;">${App.escapeHtml(a.detalle)}</pre>`;
            }
        }

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal modal-lg">
                    <div class="modal-header">
                        <div class="modal-title"><i class="bi bi-shield-check"></i> Detalle de auditoría #${a.id}</div>
                        <button class="modal-close" onclick="document.getElementById('${modalId}').remove()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-grid">
                            <div class="field">
                                <label class="text-xs text-muted">Fecha</label>
                                <div style="font-weight:600;">${App.formatDate(a.fecha)}</div>
                            </div>
                            <div class="field">
                                <label class="text-xs text-muted">Acción</label>
                                <div>${renderAccionBadge(a.accion)}</div>
                            </div>
                            <div class="field">
                                <label class="text-xs text-muted">Usuario</label>
                                <div style="font-weight:600;">${App.escapeHtml(a.usuario || 'Sistema')}</div>
                                ${a.usuario_email ? `<div class="text-xs text-muted">${App.escapeHtml(a.usuario_email)}</div>` : ''}
                            </div>
                            <div class="field">
                                <label class="text-xs text-muted">IP</label>
                                <div style="font-family:var(--font-mono);">${App.escapeHtml(a.ip || '—')}</div>
                            </div>
                            <div class="field">
                                <label class="text-xs text-muted">Tabla afectada</label>
                                <div>${App.escapeHtml(a.tabla_afectada || '—')}</div>
                            </div>
                            <div class="field">
                                <label class="text-xs text-muted">ID del registro</label>
                                <div>${a.registro_id || '—'}</div>
                            </div>
                        </div>
                        ${a.user_agent ? `
                            <div class="field mt-3">
                                <label class="text-xs text-muted">User Agent</label>
                                <div class="text-xs text-muted" style="word-break:break-all;">${App.escapeHtml(a.user_agent)}</div>
                            </div>
                        ` : ''}
                        <div class="field mt-3">
                            <label class="text-xs text-muted">Detalle</label>
                            ${detalleHtml}
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

    async function cargarCatalogos() {
        const res = await Api.get('api/auditoria.php?accion=catalogos');
        if (!res.success) return;

        const { acciones, tablas, usuarios } = res.data;

        const selUsuario = document.getElementById('filtro-usuario');
        selUsuario.innerHTML = '<option value="">Todos</option>' +
            usuarios.map(u => `<option value="${u.id}">${App.escapeHtml(u.nombre)} (${u.total})</option>`).join('');

        const selAccion = document.getElementById('filtro-accion');
        selAccion.innerHTML = '<option value="">Todas</option>' +
            acciones.map(a => `<option value="${a.accion}">${App.escapeHtml(a.accion)} (${a.total})</option>`).join('');

        const selTabla = document.getElementById('filtro-tabla');
        selTabla.innerHTML = '<option value="">Todas</option>' +
            tablas.map(t => `<option value="${t.tabla_afectada}">${App.escapeHtml(t.tabla_afectada)} (${t.total})</option>`).join('');
    }

    async function cargarEstadisticas() {
        const res = await Api.get('api/auditoria.php?accion=estadisticas');
        if (!res.success) return;

        const d = res.data;

        document.getElementById('stat-total').textContent = App.formatNumber(d.total_acciones);
        document.getElementById('stat-hoy').textContent = App.formatNumber(d.hoy);
        document.getElementById('stat-semana').textContent = App.formatNumber(d.semana);

        dibujarActividad(d.actividad_7dias);
    }

    function dibujarActividad(datos) {
        const canvas = document.getElementById('chart-actividad');
        if (!canvas || typeof Chart === 'undefined') return;

        const dias = [];
        const totales = [];
        for (let i = 6; i >= 0; i--) {
            const fecha = new Date();
            fecha.setDate(fecha.getDate() - i);
            const fechaStr = fecha.toISOString().split('T')[0];
            dias.push(fecha.getDate());
            const encontrado = datos.find(d => d.dia === fechaStr);
            totales.push(encontrado ? parseInt(encontrado.total) : 0);
        }

        if (chartActividad) chartActividad.destroy();

        const ctx = canvas.getContext('2d');
        chartActividad = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: dias,
                datasets: [{
                    data: totales,
                    backgroundColor: '#2563eb',
                    borderRadius: 4,
                    barThickness: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { display: false }, y: { display: false, beginAtZero: true } }
            }
        });
    }

    function exportar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const usuarioId = document.getElementById('filtro-usuario')?.value || '';
        const accionFiltro = document.getElementById('filtro-accion')?.value || '';
        const tabla = document.getElementById('filtro-tabla')?.value || '';
        const desde = document.getElementById('filtro-desde')?.value || '';
        const hasta = document.getElementById('filtro-hasta')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (usuarioId) params.append('usuario_id', usuarioId);
        if (accionFiltro) params.append('accion_filtro', accionFiltro);
        if (tabla) params.append('tabla', tabla);
        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);

        window.location.href = BASE_URL + 'api/auditoria.php?accion=exportar&' + params;
    }

    function limpiar() {
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-usuario').value = '';
        document.getElementById('filtro-accion').value = '';
        document.getElementById('filtro-tabla').value = '';
        document.getElementById('filtro-desde').value = '';
        document.getElementById('filtro-hasta').value = '';
        cargar(1);
    }

    function inicial(nombre) {
        return (nombre || '?').trim().charAt(0).toUpperCase();
    }
    function colorAvatar(str) {
        const colores = ['#2563eb', '#16a34a', '#dc2626', '#f59e0b', '#7c3aed', '#0891b2', '#db2777'];
        let hash = 0;
        for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
        return colores[Math.abs(hash) % colores.length];
    }

    document.addEventListener('DOMContentLoaded', () => {
        cargarCatalogos();
        cargarEstadisticas();
        cargar(1);

        const debounced = App.debounce(() => cargar(1), 500);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-usuario')?.addEventListener('change', () => cargar(1));
        document.getElementById('filtro-accion')?.addEventListener('change', () => cargar(1));
        document.getElementById('filtro-tabla')?.addEventListener('change', () => cargar(1));
        document.getElementById('filtro-desde')?.addEventListener('change', () => cargar(1));
        document.getElementById('filtro-hasta')?.addEventListener('change', () => cargar(1));
    });

    return { cargar, verDetalle, exportar, limpiar };
})();

window.Auditoria = Auditoria;