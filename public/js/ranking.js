/**
 * IPV - Ranking de vendedores
 */

const Ranking = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let chartRanking = null;
    let rankingActual = [];

    // ============================================================
    // CATÁLOGOS
    // ============================================================
    async function cargarCatalogos() {
        const res = await Api.get('api/ranking.php?accion=catalogos');
        if (!res.success) return;

        const { puntos_venta, inicio_mes } = res.data;

        document.getElementById('filtro-pv').innerHTML = '<option value="">Todos los PV</option>' +
            puntos_venta.map(p => `<option value="${p.id}">${App.escapeHtml(p.nombre)}</option>`).join('');

        // Default: este mes
        document.getElementById('filtro-desde').value = inicio_mes;
        document.getElementById('filtro-hasta').value = new Date().toISOString().split('T')[0];

        // Marcar botón "Este mes" como activo
        document.querySelectorAll('[data-atajo]').forEach(b => b.classList.remove('btn-primary'));
        document.querySelector('[data-atajo="mes"]')?.classList.add('btn-primary');
    }

    // ============================================================
    // CARGAR
    // ============================================================
    async function cargar() {
        const desde = document.getElementById('filtro-desde').value;
        const hasta = document.getElementById('filtro-hasta').value;
        const pvId = document.getElementById('filtro-pv').value;

        const params = new URLSearchParams();
        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);
        if (pvId) params.append('pv_id', pvId);

        const cont = document.getElementById('tabla-ranking');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get('api/ranking.php?accion=listar&' + params);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        const { ranking, totales, comparativa } = res.data;
        rankingActual = ranking;

        // Stats
        document.getElementById('tot-vendido').textContent = App.formatMoney(totales.total_vendido);
        document.getElementById('tot-ventas').textContent = totales.total_ventas;
        document.getElementById('tot-efectivo').textContent = App.formatMoney(totales.total_efectivo);
        document.getElementById('tot-transf').textContent = App.formatMoney(totales.total_transferencia);

        // Comparativa
        const varEl = document.getElementById('tot-comparativa');
        if (varEl) {
            const v = comparativa.variacion;
            const icono = v >= 0 ? 'bi-arrow-up' : 'bi-arrow-down';
            const clase = v >= 0 ? 'up' : 'down';
            const signo = v >= 0 ? '+' : '';
            varEl.className = `stat-card-trend ${clase}`;
            varEl.innerHTML = `<i class="bi ${icono}"></i> ${signo}${v}% vs período anterior`;
        }

        const antEl = document.getElementById('tot-ventas-ant');
        if (antEl) {
            antEl.innerHTML = `<i class="bi bi-info-circle"></i> Anterior: ${comparativa.periodo_anterior.ventas}`;
        }

        // Render
        renderPodium(ranking);
        dibujarGrafico(ranking);
        renderTabla(ranking);

        document.getElementById('total-vendedores').textContent = ranking.length;
    }

    // ============================================================
    // PODIUM
    // ============================================================
    function renderPodium(ranking) {
        const card = document.getElementById('podium-card');
        const cont = document.getElementById('podium');

        if (!ranking.length) {
            card.style.display = 'none';
            return;
        }

        card.style.display = '';

        const top3 = ranking.slice(0, 3);
        const medallas = ['🥇', '🥈', '🥉'];
        const alturas = [200, 160, 130];
        const colores = ['#fbbf24', '#cbd5e1', '#b45309'];

        // Orden visual: 2do, 1ro, 3ro
        const orden = [1, 0, 2].filter(i => top3[i]);

        cont.innerHTML = orden.map(idx => {
            const v = top3[idx];
            return `
                <div style="text-align:center;display:flex;flex-direction:column;align-items:center;">
                    <div style="font-size:48px;margin-bottom:8px;">${medallas[idx]}</div>
                    <div style="font-weight:700;font-size:15px;margin-bottom:4px;">${App.escapeHtml(v.vendedor)}</div>
                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;">${App.escapeHtml(v.pv || '')}</div>
                    <div style="
                        background:linear-gradient(180deg, ${colores[idx]} 0%, ${colores[idx]}dd 100%);
                        width:130px;
                        height:${alturas[idx]}px;
                        border-radius:12px 12px 0 0;
                        display:flex;
                        flex-direction:column;
                        align-items:center;
                        justify-content:flex-start;
                        padding-top:20px;
                        color:#fff;
                        box-shadow:0 -4px 12px rgba(0,0,0,.15);
                    ">
                        <div style="font-size:22px;font-weight:800;">${App.formatMoney(v.total_vendido)}</div>
                        <div style="font-size:13px;opacity:.9;margin-top:4px;">${v.num_ventas} venta${v.num_ventas != 1 ? 's' : ''}</div>
                        <div style="font-size:12px;opacity:.85;margin-top:2px;">Ticket prom: ${App.formatMoney(v.ticket_promedio)}</div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // ============================================================
    // GRÁFICO
    // ============================================================
    function dibujarGrafico(ranking) {
        const canvas = document.getElementById('chart-ranking');
        if (!canvas || typeof Chart === 'undefined') return;

        if (chartRanking) chartRanking.destroy();

        const top = ranking.slice(0, 10);

        if (!top.length) {
            canvas.outerHTML = `<div class="empty-state" style="padding:60px 20px;">
                <i class="bi bi-bar-chart" style="font-size:48px;color:var(--text-light);"></i>
                <p class="text-muted mt-3">Sin datos para mostrar</p>
            </div>`;
            return;
        }

        const ctx = canvas.getContext('2d');

        chartRanking = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: top.map(v => v.vendedor),
                datasets: [
                    {
                        label: 'Efectivo',
                        data: top.map(v => parseFloat(v.total_efectivo)),
                        backgroundColor: '#16a34a',
                        borderRadius: 6,
                        stack: 'pagos',
                    },
                    {
                        label: 'Transferencia',
                        data: top.map(v => parseFloat(v.total_transferencia)),
                        backgroundColor: '#0ea5e9',
                        borderRadius: 6,
                        stack: 'pagos',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { size: 12 }, usePointStyle: true, padding: 15 }
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.dataset.label}: ${App.formatMoney(ctx.parsed.y)}`,
                            footer: (items) => {
                                const total = items.reduce((sum, i) => sum + i.parsed.y, 0);
                                return `Total: ${App.formatMoney(total)}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            callback: (v) => App.formatMoney(v, '$', 0),
                            font: { size: 11 }
                        },
                        grid: { color: 'rgba(0,0,0,.05)' }
                    }
                }
            }
        });
    }

    // ============================================================
    // TABLA
    // ============================================================
    function renderTabla(ranking) {
        const cont = document.getElementById('tabla-ranking');

        if (!ranking.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>Sin datos</h3>
                <p>No hay ventas en el período seleccionado.</p>
            </div>`;
            return;
        }

        const maxTotal = Math.max(...ranking.map(r => parseFloat(r.total_vendido)));

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th>Vendedor</th>
                            <th>Punto de venta</th>
                            <th style="min-width:200px;">Progreso</th>
                            <th class="text-right">Ventas</th>
                            <th class="text-right">Ticket prom.</th>
                            <th class="text-right">Efectivo</th>
                            <th class="text-right">Transf.</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Detalles</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${ranking.map((v, i) => {
                            const medallas = ['🥇', '🥈', '🥉'];
                            const medalla = medallas[i] || `<span style="display:inline-flex;width:26px;height:26px;border-radius:50%;background:var(--surface-2);color:var(--text-muted);align-items:center;justify-content:center;font-size:12px;font-weight:700;">${i+1}</span>`;
                            const pct = maxTotal > 0 ? (parseFloat(v.total_vendido) / maxTotal * 100) : 0;
                            return `
                                <tr>
                                    <td>${medalla}</td>
                                    <td><strong>${App.escapeHtml(v.vendedor)}</strong></td>
                                    <td class="text-muted">${App.escapeHtml(v.pv)}</td>
                                    <td>
                                        <div style="height:6px;background:var(--surface-2);border-radius:3px;overflow:hidden;">
                                            <div style="height:100%;width:${pct}%;background:var(--primary);transition:width .4s;"></div>
                                        </div>
                                    </td>
                                    <td class="text-right">${v.num_ventas}</td>
                                    <td class="text-right">${App.formatMoney(v.ticket_promedio)}</td>
                                    <td class="text-right text-success">${App.formatMoney(v.total_efectivo)}</td>
                                    <td class="text-right text-info">${App.formatMoney(v.total_transferencia)}</td>
                                    <td class="text-right"><strong style="color:var(--primary);">${App.formatMoney(v.total_vendido)}</strong></td>
                                    <td class="text-right">
                                        <button class="btn btn-ghost btn-sm" onclick="Ranking.verTopProductos(${v.vendedor_id}, '${App.escapeHtml(v.vendedor).replace(/'/g, "\\'")}')" title="Ver top productos">
                                            <i class="bi bi-box-seam"></i>
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

    // ============================================================
    // TOP PRODUCTOS POR VENDEDOR
    // ============================================================
    async function verTopProductos(vendedorId, vendedorNombre) {
        const desde = document.getElementById('filtro-desde').value;
        const hasta = document.getElementById('filtro-hasta').value;

        const params = new URLSearchParams();
        params.append('vendedor_id', vendedorId);
        params.append('limit', 10);
        if (desde) params.append('desde', desde);
        if (hasta) params.append('hasta', hasta);

        const modalId = 'modal-top-productos';

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal modal-lg">
                    <div class="modal-header">
                        <div class="modal-title"><i class="bi bi-box-seam"></i> Top productos - ${App.escapeHtml(vendedorNombre)}</div>
                        <button class="modal-close" onclick="document.getElementById('${modalId}').remove()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body" id="contenido-top-productos">
                        <div class="empty-state"><div class="spinner"></div></div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.getElementById('${modalId}').remove()">Cerrar</button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const res = await Api.get('api/ranking.php?accion=top_productos&' + params);
        const cont = document.getElementById('contenido-top-productos');

        if (!res.success) {
            cont.innerHTML = `<div class="alert alert-danger">${res.message}</div>`;
            return;
        }

        const productos = res.data;

        if (!productos.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p class="text-muted">Sin productos vendidos en este período</p>
            </div>`;
            return;
        }

        const maxUnidades = Math.max(...productos.map(p => parseInt(p.unidades)));

        cont.innerHTML = productos.map((p, i) => {
            const pct = maxUnidades > 0 ? (parseInt(p.unidades) / maxUnidades * 100) : 0;
            return `
                <div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);">
                    <div style="width:32px;height:32px;border-radius:50%;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">${i+1}</div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;">${App.escapeHtml(p.producto)}</div>
                        <div class="text-xs text-muted">${App.escapeHtml(p.codigo_barras || 'Sin código')} · ${p.num_ventas} venta${p.num_ventas != 1 ? 's' : ''}</div>
                        <div style="height:4px;background:var(--surface-2);border-radius:2px;margin-top:6px;overflow:hidden;">
                            <div style="height:100%;width:${pct}%;background:var(--primary);transition:width .4s;"></div>
                        </div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-weight:700;">${p.unidades} u.</div>
                        <div class="text-xs text-muted">${App.formatMoney(p.total_vendido)}</div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // ============================================================
    // ATAJOS DE FECHA
    // ============================================================
    function atajo(tipo) {
        const hoy = new Date();
        let desde, hasta;

        switch (tipo) {
            case 'hoy':
                desde = hasta = formatoISO(hoy);
                break;
            case 'semana':
                const inicioSemana = new Date(hoy);
                inicioSemana.setDate(hoy.getDate() - hoy.getDay());
                desde = formatoISO(inicioSemana);
                hasta = formatoISO(hoy);
                break;
            case 'mes':
                desde = formatoISO(new Date(hoy.getFullYear(), hoy.getMonth(), 1));
                hasta = formatoISO(hoy);
                break;
            case 'mes_anterior':
                const mesAnt = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1);
                desde = formatoISO(mesAnt);
                hasta = formatoISO(new Date(hoy.getFullYear(), hoy.getMonth(), 0));
                break;
            case 'año':
                desde = formatoISO(new Date(hoy.getFullYear(), 0, 1));
                hasta = formatoISO(hoy);
                break;
        }

        document.getElementById('filtro-desde').value = desde;
        document.getElementById('filtro-hasta').value = hasta;

        // Marcar botón activo
        document.querySelectorAll('[data-atajo]').forEach(b => {
            b.classList.remove('btn-primary');
            b.classList.add('btn-secondary');
        });
        document.querySelector(`[data-atajo="${tipo}"]`)?.classList.remove('btn-secondary');
        document.querySelector(`[data-atajo="${tipo}"]`)?.classList.add('btn-primary');

        cargar();
    }

    function formatoISO(fecha) {
        return fecha.toISOString().split('T')[0];
    }

    // ============================================================
    // EXPORTAR CSV
    // ============================================================
    function exportar() {
        if (!rankingActual.length) {
            Toast.warning('Sin datos', 'No hay nada para exportar');
            return;
        }

        const desde = document.getElementById('filtro-desde').value;
        const hasta = document.getElementById('filtro-hasta').value;

        let csv = '\uFEFF'; // BOM UTF-8
        csv += `Ranking de vendedores\n`;
        csv += `Período: ${desde} a ${hasta}\n\n`;
        csv += `#;Vendedor;Punto de venta;Ventas;Ticket promedio;Efectivo;Transferencia;Total\n`;

        rankingActual.forEach((v, i) => {
            csv += `${i+1};${v.vendedor};${v.pv || ''};${v.num_ventas};${v.ticket_promedio};${v.total_efectivo};${v.total_transferencia};${v.total_vendido}\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `ranking_${desde}_${hasta}.csv`;
        a.click();
        URL.revokeObjectURL(url);

        Toast.success('Exportado', 'Archivo descargado');
    }

    // ============================================================
    // EVENTOS
    // ============================================================
    document.addEventListener('DOMContentLoaded', () => {
        cargarCatalogos().then(cargar);

        document.getElementById('filtro-desde')?.addEventListener('change', cargar);
        document.getElementById('filtro-hasta')?.addEventListener('change', cargar);
        document.getElementById('filtro-pv')?.addEventListener('change', cargar);
    });

    return {
        cargar, atajo, verTopProductos, exportar,
    };
})();

window.Ranking = Ranking;