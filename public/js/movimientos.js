/**
 * IPV - Historial de movimientos
 */

const Movimientos = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let paginaActual = 1;
    let totalPags = 1;

    async function cargarCatalogos() {
        const res = await Api.get('api/movimientos.php?accion=catalogos');
        if (!res.success) return;

        const { puntos_venta, usuarios } = res.data;

        document.getElementById('filtro-pv').innerHTML = '<option value="">Todos los PV</option>' +
            puntos_venta.map(p => `<option value="${p.id}">${App.escapeHtml(p.nombre)}</option>`).join('');

        document.getElementById('filtro-usuario').innerHTML = '<option value="">Todos</option>' +
            usuarios.map(u => `<option value="${u.id}">${App.escapeHtml(u.nombre)} (${u.total})</option>`).join('');
    }

    async function cargar(pagina = 1) {
        paginaActual = pagina;

        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const tipo = document.getElementById('filtro-tipo')?.value || '';
        const pvId = document.getElementById('filtro-pv')?.value || '';
        const usuarioId = document.getElementById('filtro-usuario')?.value || '';
        const desde = document.getElementById('filtro-desde')?.value || '';
        const hasta = document.getElementById('filtro-hasta')?.value || '';

        const params = new URLSearchParams();
        params.append('pagina', paginaActual);
        if (q) params.append('q', q);
        if (tipo) params.append('tipo', tipo);
        if (pvId) params.append('pv_id', pvId);
        if (usuarioId) params.append('usuario_id', usuarioId);
        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);

        const cont = document.getElementById('tabla-movimientos');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/movimientos.php?accion=listar&${params}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        totalPags = res.data.total_pags || 1;
        document.getElementById('total-movimientos').textContent = res.data.total;
        render(res.data.datos);
        renderPaginacion();
    }

    function render(lista) {
        const cont = document.getElementById('tabla-movimientos');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>Sin movimientos</h3>
                <p>No hay movimientos que coincidan con los filtros.</p>
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Producto</th>
                            <th>Unidad</th>
                            <th>Punto de venta</th>
                            <th class="text-right">Cambio</th>
                            <th class="text-right">Stock</th>
                            <th>Motivo</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(m => {
                            const tipoBadge = getTipoBadge(m.tipo);
                            const diff = parseInt(m.cantidad);
                            const signo = diff > 0 ? '+' : '';
                            const color = m.tipo === 'entrada' ? 'var(--success)' : 
                                         (m.tipo === 'baja' ? 'var(--danger)' : 
                                         (diff > 0 ? 'var(--success)' : 'var(--danger)'));
                            const unidad = m.unidad_medida || 'Unidad';
                            return `
                                <tr>
                                    <td class="text-muted text-xs" style="white-space:nowrap;">${App.formatDate(m.fecha)}</td>
                                    <td>${tipoBadge}</td>
                                    <td>
                                        <div style="font-weight:600;">${App.escapeHtml(m.producto)}</div>
                                        <div class="text-xs text-muted">${App.escapeHtml(m.codigo_barras || '—')}</div>
                                    </td>
                                    <td class="text-muted text-sm">${App.escapeHtml(unidad)}</td>
                                    <td>${App.escapeHtml(m.pv)}</td>
                                    <td class="text-right"><strong style="color:${color};">${signo}${diff} ${App.escapeHtml(unidad)}</strong></td>
                                    <td class="text-right text-sm">
                                        <span class="text-muted">${m.valor_anterior}</span>
                                        <i class="bi bi-arrow-right text-xs"></i>
                                        <strong>${m.valor_nuevo}</strong>
                                    </td>
                                    <td class="text-sm">
                                        <div style="font-weight:500;">${App.escapeHtml(m.motivo || '—')}</div>
                                        ${m.descripcion ? `<div class="text-xs text-muted">${App.escapeHtml(m.descripcion.substring(0, 60))}${m.descripcion.length > 60 ? '...' : ''}</div>` : ''}
                                    </td>
                                    <td class="text-muted text-sm">${App.escapeHtml(m.usuario)}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function getTipoBadge(tipo) {
        const map = {
            entrada:       ['badge-success', 'Entrada',       'box-arrow-in-down'],
            salida:        ['badge-warning', 'Salida',        'box-arrow-up'],
            baja:          ['badge-danger',  'Baja',          'trash'],
            ajuste:        ['badge-info',    'Ajuste',        'wrench-adjustable'],
            transferencia: ['badge-neutral', 'Transferencia', 'arrow-left-right'],
        };
        const [clase, label, icon] = map[tipo] || ['badge-neutral', tipo, 'circle'];
        return `<span class="badge ${clase}"><i class="bi bi-${icon}"></i> ${label}</span>`;
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

        botones.push(`<button class="btn btn-secondary btn-sm" onclick="Movimientos.cargar(${Math.max(1, paginaActual - 1)})" ${paginaActual === 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button>`);

        for (let i = inicio; i <= fin; i++) {
            botones.push(`<button class="btn ${i === paginaActual ? 'btn-primary' : 'btn-ghost'} btn-sm" onclick="Movimientos.cargar(${i})">${i}</button>`);
        }

        botones.push(`<button class="btn btn-secondary btn-sm" onclick="Movimientos.cargar(${Math.min(totalPags, paginaActual + 1)})" ${paginaActual === totalPags ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button>`);

        cont.innerHTML = `
            <div style="display:flex;justify-content:center;align-items:center;gap:4px;flex-wrap:wrap;">
                ${botones.join('')}
            </div>
            <div class="text-center text-muted text-xs mt-2">
                Página ${paginaActual} de ${totalPags}
            </div>
        `;
    }

    function exportar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const tipo = document.getElementById('filtro-tipo')?.value || '';
        const pvId = document.getElementById('filtro-pv')?.value || '';
        const desde = document.getElementById('filtro-desde')?.value || '';
        const hasta = document.getElementById('filtro-hasta')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (tipo) params.append('tipo', tipo);
        if (pvId) params.append('pv_id', pvId);
        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);

        window.location.href = BASE_URL + 'api/movimientos.php?accion=exportar&' + params;
    }

    function limpiar() {
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-tipo').value = '';
        document.getElementById('filtro-pv').value = '';
        document.getElementById('filtro-usuario').value = '';
        document.getElementById('filtro-desde').value = '';
        document.getElementById('filtro-hasta').value = '';
        cargar(1);
    }

    document.addEventListener('DOMContentLoaded', () => {
        cargarCatalogos().then(() => cargar(1));

        const debounced = App.debounce(() => cargar(1), 500);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-tipo')?.addEventListener('change', () => cargar(1));
        document.getElementById('filtro-pv')?.addEventListener('change', () => cargar(1));
        document.getElementById('filtro-usuario')?.addEventListener('change', () => cargar(1));
        document.getElementById('filtro-desde')?.addEventListener('change', () => cargar(1));
        document.getElementById('filtro-hasta')?.addEventListener('change', () => cargar(1));
    });

    return { cargar, exportar, limpiar };
})();

window.Movimientos = Movimientos;