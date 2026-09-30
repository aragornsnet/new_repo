/**
 * IPV - Dashboard del Supervisor
 */

const SupervisorDashboard = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let chartVentas = null;

    async function cargar() {
        const loading = document.getElementById('loading-dashboard');
        loading?.classList.add('active');

        try {
            const res = await Api.get('api/supervisor_dashboard.php');
            loading?.classList.remove('active');

            if (!res.success) {
                Toast.error('Error', res.message || 'No se pudieron cargar los datos');
                return;
            }

            const d = res.data;

            try {
                setText('stat-ventas', App.formatMoney(d.hoy.total));
                setText('stat-tickets', `${d.hoy.num_ventas} ticket${d.hoy.num_ventas !== 1 ? 's' : ''} · Prom. ${App.formatMoney(d.hoy.ticket_promedio)}`);
                setText('stat-efectivo', App.formatMoney(d.hoy.efectivo));
                setText('stat-transferencia', App.formatMoney(d.hoy.transferencia));
                setText('stat-turnos', d.alertas.turnos_abiertos);
            } catch (e) { console.error('Stats:', e); }

            try { renderAlertas(d.alertas); } catch (e) { console.error('Alertas:', e); }
            try { dibujarGrafico(d.grafico_7dias); } catch (e) { console.error('Gráfico:', e); }
            try { renderRanking(d.ranking_hoy); } catch (e) { console.error('Ranking:', e); }
            try { renderTurnos(d.turnos_abiertos); } catch (e) { console.error('Turnos:', e); }
            try { renderTransf(d.transfer_pendientes); } catch (e) { console.error('Transf:', e); }
            try { renderVentasPV(d.ventas_por_pv); } catch (e) { console.error('PV:', e); }

        } catch (e) {
            loading?.classList.remove('active');
            console.error('Error dashboard:', e);
            Toast.error('Error', 'No se pudo cargar el dashboard: ' + e.message);
        }
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function renderAlertas(a) {
        const cont = document.getElementById('alertas-container');
        if (!cont) return;

        const alertas = [];

        if (a.transfer_pendientes > 0) {
            alertas.push(`
                <a href="${BASE_URL}views/supervisor/transferencias.php" class="alert alert-warning" style="text-decoration:none;display:flex;">
                    <i class="bi bi-bank"></i>
                    <div class="alert-body">
                        <div class="alert-title">${a.transfer_pendientes} transferencia(s) pendiente(s) de verificar</div>
                        <div class="text-sm">Haz clic para verlas y verificarlas.</div>
                    </div>
                    <i class="bi bi-chevron-right"></i>
                </a>
            `);
        }

        if (a.stock_negativo > 0) {
            alertas.push(`
                <a href="${BASE_URL}views/supervisor/inventario.php?filtro=negativo" class="alert alert-danger" style="text-decoration:none;display:flex;">
                    <i class="bi bi-x-octagon-fill"></i>
                    <div class="alert-body">
                        <div class="alert-title">${a.stock_negativo} producto(s) con stock negativo</div>
                        <div class="text-sm">Revisar el inventario urgente.</div>
                    </div>
                    <i class="bi bi-chevron-right"></i>
                </a>
            `);
        }

        if (a.stock_bajo > 0) {
            alertas.push(`
                <a href="${BASE_URL}views/supervisor/inventario.php?filtro=bajo" class="alert alert-info" style="text-decoration:none;display:flex;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div class="alert-body">
                        <div class="alert-title">${a.stock_bajo} producto(s) con stock bajo</div>
                        <div class="text-sm">Considera registrar entradas.</div>
                    </div>
                    <i class="bi bi-chevron-right"></i>
                </a>
            `);
        }

        cont.innerHTML = alertas.join('');
    }

    function dibujarGrafico(data) {
        const canvas = document.getElementById('chart-ventas');
        if (!canvas || typeof Chart === 'undefined') return;

        if (chartVentas) chartVentas.destroy();

        const ctx = canvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 240);
        gradient.addColorStop(0, 'rgba(37, 99, 235, 0.35)');
        gradient.addColorStop(1, 'rgba(37, 99, 235, 0.02)');

        chartVentas = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Ventas',
                    data: data.data || [],
                    borderColor: '#2563eb',
                    backgroundColor: gradient,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (ctx) => App.formatMoney(ctx.parsed.y) } }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: (v) => App.formatMoney(v, '$', 0), maxTicksLimit: 6 },
                        grid: { color: 'rgba(0,0,0,.05)' },
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function renderRanking(ranking) {
        const cont = document.getElementById('ranking-hoy');
        if (!cont) return;

        if (!ranking.length) {
            cont.innerHTML = `<div class="empty-state" style="padding:30px 15px;">
                <i class="bi bi-people" style="font-size:40px;"></i>
                <p class="text-muted mt-2">Sin vendedores</p>
            </div>`;
            return;
        }

        cont.innerHTML = ranking.slice(0, 5).map((v, i) => {
            const medallas = ['🥇', '🥈', '🥉'];
            const medalla = medallas[i] || `<span style="display:inline-flex;width:26px;height:26px;border-radius:50%;background:var(--surface-2);color:var(--text-muted);align-items:center;justify-content:center;font-size:12px;font-weight:700;">${i+1}</span>`;
            return `
                <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
                    <div style="flex-shrink:0;">${medalla}</div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;">${App.escapeHtml(v.nombre)}</div>
                        <div style="font-size:11px;color:var(--text-muted);">
                            ${v.pv ? App.escapeHtml(v.pv) + ' · ' : ''}${v.num_ventas} venta${v.num_ventas != 1 ? 's' : ''}
                        </div>
                    </div>
                    <div style="font-weight:700;font-size:14px;color:var(--primary);">${App.formatMoney(v.total)}</div>
                </div>
            `;
        }).join('');
    }

    function renderTurnos(turnos) {
        const cont = document.getElementById('turnos-abiertos');
        if (!cont) return;

        if (!turnos.length) {
            cont.innerHTML = `<div class="empty-state" style="padding:30px 15px;">
                <i class="bi bi-clock" style="font-size:40px;"></i>
                <p class="text-muted mt-2">No hay turnos abiertos ahora</p>
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Vendedor</th>
                            <th>Punto de venta</th>
                            <th>Apertura</th>
                            <th class="text-right">Monto inicial</th>
                            <th class="text-right">Ventas</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${turnos.map(t => `
                            <tr>
                                <td><strong>${App.escapeHtml(t.vendedor)}</strong></td>
                                <td>${App.escapeHtml(t.pv)}</td>
                                <td class="text-muted text-sm">${App.timeAgo(t.fecha_apertura)}</td>
                                <td class="text-right">${App.formatMoney(t.monto_inicial)}</td>
                                <td class="text-right">${t.num_ventas}</td>
                                <td class="text-right"><strong>${App.formatMoney(t.total_vendido)}</strong></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderTransf(transf) {
        const cont = document.getElementById('transf-pendientes');
        if (!cont) return;

        if (!transf.length) {
            cont.innerHTML = `<div class="empty-state" style="padding:30px 15px;">
                <i class="bi bi-check-circle" style="font-size:40px;color:var(--success);"></i>
                <p class="text-muted mt-2">Todas las transferencias están verificadas ✅</p>
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Fecha</th>
                            <th>Vendedor</th>
                            <th>PV</th>
                            <th>Método</th>
                            <th>Referencia</th>
                            <th class="text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${transf.map(t => `
                            <tr>
                                <td><strong>${App.escapeHtml(t.folio)}</strong></td>
                                <td class="text-muted text-sm">${App.formatDate(t.fecha)}</td>
                                <td>${App.escapeHtml(t.vendedor)}</td>
                                <td class="text-muted">${App.escapeHtml(t.pv)}</td>
                                <td><span class="badge badge-info">${App.escapeHtml(t.metodo_detalle || 'Transferencia')}</span></td>
                                <td class="text-muted text-xs">${App.escapeHtml(t.referencia || '—')}</td>
                                <td class="text-right"><strong>${App.formatMoney(t.monto)}</strong></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderVentasPV(pvs) {
        const cont = document.getElementById('ventas-pv');
        if (!cont) return;

        if (!pvs.length) {
            cont.innerHTML = `<div class="empty-state" style="padding:30px 15px;">
                <i class="bi bi-shop" style="font-size:40px;"></i>
                <p class="text-muted mt-2">Sin puntos de venta</p>
            </div>`;
            return;
        }

        const max = Math.max(...pvs.map(p => parseFloat(p.total) || 0), 1);

        cont.innerHTML = pvs.map(pv => {
            const total = parseFloat(pv.total) || 0;
            const pct = (total / max) * 100;
            return `
                <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
                    <div style="width:36px;height:36px;border-radius:var(--radius-md);background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-shop"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;">${App.escapeHtml(pv.nombre)}</div>
                        <div style="height:4px;background:var(--surface-2);border-radius:2px;margin-top:6px;overflow:hidden;">
                            <div style="height:100%;width:${pct}%;background:var(--primary);transition:width .4s;"></div>
                        </div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-weight:700;font-size:14px;color:var(--primary);">${App.formatMoney(total)}</div>
                        <div style="font-size:11px;color:var(--text-muted);">${pv.num_ventas} venta${pv.num_ventas != 1 ? 's' : ''}</div>
                    </div>
                </div>
            `;
        }).join('');
    }

    document.addEventListener('DOMContentLoaded', cargar);

    return { cargar, recargar: cargar };
})();

window.SupervisorDashboard = SupervisorDashboard;