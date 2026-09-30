/**
 * IPV - Dashboard del Administrador
 */

const DashboardAdmin = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let chartVentas = null;

    async function cargar() {
        const loading = document.getElementById('loading');
        loading?.classList.add('active');

        try {
            const res = await Api.get('api/dashboard_admin.php');
            loading?.classList.remove('active');

            if (!res.success) {
                Toast.error('Error', res.message || 'No se pudieron cargar los datos');
                return;
            }

            const d = res.data;

            setText('stat-ventas-dia', App.formatMoney(d.hoy.total));
            setText('stat-tickets', `${d.hoy.num_ventas} ticket${d.hoy.num_ventas !== 1 ? 's' : ''} · Prom. ${App.formatMoney(d.hoy.ticket_promedio)}`);
            setText('stat-efectivo', App.formatMoney(d.hoy.efectivo));
            setText('stat-transferencia', App.formatMoney(d.hoy.transferencia));

            setText('stat-ventas-mes', App.formatMoney(d.mes.total));
            const varEl = document.getElementById('stat-variacion');
            if (varEl) {
                const v = d.mes.variacion;
                const icono = v >= 0 ? 'bi-arrow-up' : 'bi-arrow-down';
                const clase = v >= 0 ? 'up' : 'down';
                const signo = v >= 0 ? '+' : '';
                varEl.className = `stat-card-trend ${clase}`;
                varEl.innerHTML = `<i class="bi ${icono}"></i> ${signo}${v}% vs mes anterior`;
            }

            setText('alerta-stock-bajo', d.alertas.stock_bajo);
            setText('alerta-stock-negativo', d.alertas.stock_negativo);
            setText('alerta-transf', d.alertas.transfer_pendientes);
            setText('alerta-turnos', d.alertas.turnos_abiertos);

            try { dibujarGrafico(d.grafico_7dias); } catch (e) { console.error('Gráfico:', e); }
            try { renderTopProductos(d.top_productos); } catch (e) { console.error('Top:', e); }
            try { renderRanking(d.ranking_hoy); } catch (e) { console.error('Ranking:', e); }
            try { renderVentasPV(d.ventas_por_pv); } catch (e) { console.error('PV:', e); }

        } catch (err) {
            loading?.classList.remove('active');
            console.error('Dashboard error:', err);
            Toast.error('Error', 'No se pudieron cargar los datos del dashboard');
        }
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function dibujarGrafico(data) {
        const canvas = document.getElementById('chart-ventas');
        if (!canvas) return;

        if (typeof Chart === 'undefined') {
            canvas.outerHTML = `
                <div class="empty-state" style="padding: 40px 20px;">
                    <i class="bi bi-bar-chart" style="font-size:48px;color:var(--text-light);"></i>
                    <p class="text-muted mt-3">Chart.js no está disponible.</p>
                </div>
            `;
            return;
        }

        if (chartVentas) {
            chartVentas.destroy();
            chartVentas = null;
        }

        const ctx = canvas.getContext('2d');
        canvas.parentElement.style.position = 'relative';
        canvas.parentElement.style.height = '260px';

        const gradient = ctx.createLinearGradient(0, 0, 0, 260);
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
                animation: { duration: 500 },
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

    function renderTopProductos(productos) {
        const cont = document.getElementById('top-productos');
        if (!cont) return;

        if (!productos || !productos.length) {
            cont.innerHTML = `<div class="empty-state" style="padding:30px 15px;">
                <i class="bi bi-inbox" style="font-size:40px;"></i>
                <p class="text-muted mt-2">Sin ventas este mes</p>
            </div>`;
            return;
        }

        const max = Math.max(...productos.map(p => parseInt(p.unidades) || 0));

        cont.innerHTML = productos.map((p, i) => {
            const unidades = parseInt(p.unidades) || 0;
            const pct = max > 0 ? (unidades / max * 100) : 0;
            return `
                <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
                    <div style="width:28px;height:28px;border-radius:50%;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">${i+1}</div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${App.escapeHtml(p.nombre)}</div>
                        <div style="height:4px;background:var(--surface-2);border-radius:2px;margin-top:4px;overflow:hidden;">
                            <div style="height:100%;width:${pct}%;background:var(--primary);transition:width .4s;"></div>
                        </div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-weight:700;font-size:13px;">${unidades} u.</div>
                        <div style="font-size:11px;color:var(--text-muted);">${App.formatMoney(p.total)}</div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderRanking(ranking) {
        const cont = document.getElementById('ranking-hoy');
        if (!cont) return;

        if (!ranking || !ranking.length) {
            cont.innerHTML = `<div class="empty-state" style="padding:30px 15px;">
                <i class="bi bi-people" style="font-size:40px;"></i>
                <p class="text-muted mt-2">Sin vendedores registrados</p>
            </div>`;
            return;
        }

        cont.innerHTML = ranking.map((v, i) => {
            const medallas = ['🥇', '🥈', '🥉'];
            const medalla = medallas[i] || `<span style="display:inline-flex;width:26px;height:26px;border-radius:50%;background:var(--surface-2);color:var(--text-muted);align-items:center;justify-content:center;font-size:12px;font-weight:700;">${i+1}</span>`;
            const total = parseFloat(v.total) || 0;
            const numVentas = parseInt(v.num_ventas) || 0;
            return `
                <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
                    <div style="flex-shrink:0;">${medalla}</div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;">${App.escapeHtml(v.nombre)}</div>
                        <div style="font-size:11px;color:var(--text-muted);">${numVentas} venta${numVentas !== 1 ? 's' : ''}</div>
                    </div>
                    <div style="font-weight:700;font-size:14px;color:var(--primary);">${App.formatMoney(total)}</div>
                </div>
            `;
        }).join('');
    }

    function renderVentasPV(pvs) {
        const cont = document.getElementById('ventas-pv');
        if (!cont) return;

        if (!pvs || !pvs.length) {
            cont.innerHTML = `<div class="empty-state" style="padding:30px 15px;">
                <i class="bi bi-shop" style="font-size:40px;"></i>
                <p class="text-muted mt-2">Sin puntos de venta</p>
            </div>`;
            return;
        }

        cont.innerHTML = pvs.map(pv => {
            const total = parseFloat(pv.total) || 0;
            const numVentas = parseInt(pv.num_ventas) || 0;
            return `
                <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
                    <div style="width:36px;height:36px;border-radius:var(--radius-md);background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-shop"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;">${App.escapeHtml(pv.nombre)}</div>
                        <div style="font-size:11px;color:var(--text-muted);">${numVentas} venta${numVentas !== 1 ? 's' : ''}</div>
                    </div>
                    <div style="font-weight:700;font-size:14px;color:var(--primary);">${App.formatMoney(total)}</div>
                </div>
            `;
        }).join('');
    }

    document.addEventListener('DOMContentLoaded', cargar);

    return { cargar, recargar: cargar };
})();

window.DashboardAdmin = DashboardAdmin;