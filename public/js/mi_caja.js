/**
 * IPV - Mi Caja (vendedor)
 */

const MiCaja = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let datos = null;

    async function cargar() {
        const loading = document.getElementById('loading');
        loading?.classList.add('active');

        const res = await Api.get('api/mi_caja.php');
        loading?.classList.remove('active');

        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        datos = res.data;
        render();
    }

    function render() {
        const cont = document.getElementById('contenido-caja');
        const t = datos.turno;
        const r = datos.resumen;

        cont.innerHTML = `
            <!-- Banner del turno -->
            <div class="card" style="background:linear-gradient(135deg, var(--success) 0%, #15803d 100%);color:#fff;margin-bottom:24px;border:none;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                    <div>
                        <div style="font-size:13px;opacity:.9;margin-bottom:4px;">
                            <i class="bi bi-clock-history"></i> Turno abierto desde ${App.formatDate(t.fecha_apertura)}
                        </div>
                        <div style="font-size:26px;font-weight:700;">${App.escapeHtml(t.pv)}</div>
                        <div style="font-size:14px;opacity:.9;">Duración: ${formatearDuracion(t.duracion_seg)}</div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:13px;opacity:.9;">Monto inicial</div>
                        <div style="font-size:24px;font-weight:800;">${App.formatMoney(t.monto_inicial)}</div>
                    </div>
                </div>
            </div>

            <!-- Resumen de efectivo -->
            <div class="grid-stats mb-4">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-icon success"><i class="bi bi-cash-stack"></i></div>
                    </div>
                    <div class="stat-card-label">Efectivo teórico en caja</div>
                    <div class="stat-card-value" style="color:var(--success);">${App.formatMoney(r.efectivo_teorico)}</div>
                    <div class="stat-card-trend"><i class="bi bi-info-circle"></i> Inicial + ventas efectivo</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-icon primary"><i class="bi bi-cash"></i></div>
                    </div>
                    <div class="stat-card-label">Ventas en efectivo</div>
                    <div class="stat-card-value">${App.formatMoney(r.total_efectivo)}</div>
                    <div class="stat-card-trend"><i class="bi bi-receipt"></i> ${r.num_ventas_efectivo} venta${r.num_ventas_efectivo !== 1 ? 's' : ''}</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-icon info"><i class="bi bi-bank"></i></div>
                    </div>
                    <div class="stat-card-label">Transferencias</div>
                    <div class="stat-card-value">${App.formatMoney(r.total_transferencia)}</div>
                    <div class="stat-card-trend"><i class="bi bi-info-circle"></i> No suma a la caja</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-icon warning"><i class="bi bi-receipt-cutoff"></i></div>
                    </div>
                    <div class="stat-card-label">Total vendido</div>
                    <div class="stat-card-value">${App.formatMoney(r.total_general)}</div>
                    <div class="stat-card-trend"><i class="bi bi-receipt"></i> ${r.num_ventas} venta${r.num_ventas !== 1 ? 's' : ''}</div>
                </div>
            </div>

            ${datos.ventas_divisa && datos.ventas_divisa.length > 0 ? `
                <div class="card mb-4" style="background:var(--info-light);border:1px solid var(--info);">
                    <div class="card-header" style="border-color:var(--info);">
                        <div class="card-title"><i class="bi bi-currency-exchange text-info"></i> Ventas en divisa</div>
                    </div>
                    ${datos.ventas_divisa.map(vd => `
                        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
                            <div>
                                <strong>${vd.moneda}</strong>
                                <span class="text-muted text-sm">· ${vd.num_ventas} venta${vd.num_ventas !== 1 ? 's' : ''}</span>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-weight:700;">${vd.moneda === 'USD' ? '$' : '€'}${App.formatNumber(vd.total_divisa, 2)}</div>
                                <div class="text-xs text-muted">Tasa: ${App.formatNumber(vd.tasa, 2)}</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            ` : ''}

            <!-- Movimientos de efectivo -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-list-ul"></i> Movimientos de efectivo del turno
                        <span class="badge badge-neutral ml-2">${datos.movimientos.length}</span>
                    </div>
                </div>

                ${datos.movimientos.length === 0 ? `
                    <div class="empty-state" style="padding:40px 20px;">
                        <i class="bi bi-cash-stack" style="font-size:48px;"></i>
                        <p class="text-muted mt-2">No ha habido ventas en efectivo todavía</p>
                    </div>
                ` : `
                    <div class="tabla-wrap">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th>Hora</th>
                                    <th>Folio</th>
                                    <th class="text-right">Efectivo recibido</th>
                                    <th class="text-right">Total venta</th>
                                    <th class="text-right">Acumulado</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${(() => {
                                    let acumulado = parseFloat(t.monto_inicial);
                                    return datos.movimientos.map(m => {
                                        acumulado += parseFloat(m.monto_efectivo);
                                        return `
                                            <tr>
                                                <td class="text-muted text-xs">${App.formatDate(m.fecha)}</td>
                                                <td><strong>${App.escapeHtml(m.folio)}</strong></td>
                                                <td class="text-right text-success"><strong>+${App.formatMoney(m.monto_efectivo)}</strong></td>
                                                <td class="text-right">${App.formatMoney(m.total)}</td>
                                                <td class="text-right text-muted">${App.formatMoney(acumulado)}</td>
                                            </tr>
                                        `;
                                    }).join('');
                                })()}
                            </tbody>
                            <tfoot style="background:var(--success-light);">
                                <tr>
                                    <td colspan="2"><strong>INICIAL + VENTAS EN EFECTIVO</strong></td>
                                    <td class="text-right"><strong style="color:var(--success);font-size:16px;">+${App.formatMoney(r.total_efectivo)}</strong></td>
                                    <td colspan="2" class="text-right">
                                        <div class="text-xs text-muted">Total actual en caja</div>
                                        <strong style="font-size:18px;color:var(--primary);">${App.formatMoney(r.efectivo_teorico)}</strong>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                `}
            </div>

            <!-- Acciones -->
            <div class="card mt-4" style="background:var(--surface-2);">
                <div class="d-flex gap-3" style="flex-wrap:wrap;">
                    <a href="${BASE_URL}views/vendedor/pos.php" class="btn btn-primary" style="flex:1;">
                        <i class="bi bi-cart-plus"></i> Ir al POS
                    </a>
                    <a href="${BASE_URL}views/vendedor/cerrar_turno.php" class="btn btn-danger" style="flex:1;">
                        <i class="bi bi-lock-fill"></i> Cerrar turno y cuadrar caja
                    </a>
                </div>
            </div>
        `;
    }

    function formatearDuracion(seg) {
        if (!seg || seg < 0) return '0 min';
        const h = Math.floor(seg / 3600);
        const m = Math.floor((seg % 3600) / 60);
        if (h > 0) return `${h}h ${m}min`;
        return `${m} min`;
    }

    document.addEventListener('DOMContentLoaded', cargar);

    return { cargar };
})();

window.MiCaja = MiCaja;