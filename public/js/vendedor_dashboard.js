/**
 * IPV - Dashboard del Vendedor
 */

const VendedorDashboard = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let datosActuales = null;

    // ============================================================
    // CARGAR
    // ============================================================
    async function cargar() {
        const loading = document.getElementById('loading');
        loading?.classList.add('active');

        try {
            const res = await Api.get('api/vendedor_dashboard.php');
            loading?.classList.remove('active');

            if (!res.success) {
                Toast.error('Error', res.message || 'No se pudieron cargar los datos');
                return;
            }

            datosActuales = res.data;

            if (res.data.turno) {
                renderConTurno(res.data);
            } else {
                renderSinTurno(res.data);
            }
        } catch (e) {
            loading?.classList.remove('active');
            console.error('Error dashboard vendedor:', e);
            Toast.error('Error', 'No se pudo cargar el dashboard');
        }
    }

    // ============================================================
    // SIN TURNO
    // ============================================================
    function renderSinTurno(d) {
        const cont = document.getElementById('contenido-dashboard');

        cont.innerHTML = `
            <div class="card" style="background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);color:#fff;text-align:center;padding:48px 24px;margin-bottom:24px;border:none;">
                <i class="bi bi-play-circle-fill" style="font-size:96px;margin-bottom:24px;opacity:.9;"></i>
                <h2 style="font-size:32px;margin-bottom:12px;color:#fff;">No tienes un turno abierto</h2>
                <p style="font-size:16px;opacity:.9;margin-bottom:32px;max-width:500px;margin-left:auto;margin-right:auto;">
                    Para empezar a vender, primero debes <strong>abrir un turno</strong> indicando el monto inicial en efectivo de tu caja.
                </p>
                <button class="btn" style="background:#fff;color:var(--primary);font-size:18px;padding:16px 40px;font-weight:700;" onclick="VendedorDashboard.abrirTurnoModal()">
                    <i class="bi bi-play-circle-fill" style="font-size:22px;"></i> Abrir turno ahora
                </button>
            </div>

            ${d.alertas.stock_negativo > 0 || d.alertas.stock_bajo > 0 ? `
                <div class="grid-stats mb-4">
                    ${d.alertas.stock_negativo > 0 ? `
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-icon danger"><i class="bi bi-x-octagon-fill"></i></div>
                            </div>
                            <div class="stat-card-label">Productos en negativo</div>
                            <div class="stat-card-value">${d.alertas.stock_negativo}</div>
                            <div class="stat-card-trend down"><i class="bi bi-exclamation-triangle-fill"></i> Revisar urgente</div>
                        </div>
                    ` : ''}
                    ${d.alertas.stock_bajo > 0 ? `
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-icon warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            </div>
                            <div class="stat-card-label">Productos con stock bajo</div>
                            <div class="stat-card-value">${d.alertas.stock_bajo}</div>
                            <div class="stat-card-trend"><i class="bi bi-info-circle"></i> Considera reportar entradas</div>
                        </div>
                    ` : ''}
                </div>
            ` : ''}

            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-info-circle-fill"></i> ¿Qué hacer antes de abrir turno?</div>
                </div>
                <ol style="padding-left:24px;font-size:14px;line-height:2;">
                    <li>Verifica que el cajón de efectivo esté abierto y accesible</li>
                    <li>Cuenta el efectivo inicial que hay en la caja</li>
                    <li>Pulsa <strong>"Abrir turno ahora"</strong> e ingresa el monto contado</li>
                    <li>El sistema tomará una foto del inventario actual como referencia</li>
                    <li>Ya puedes empezar a vender en el POS</li>
                </ol>
            </div>
        `;
    }

    // ============================================================
    // CON TURNO ABIERTO
    // ============================================================
    function renderConTurno(d) {
        const cont = document.getElementById('contenido-dashboard');
        const t = d.turno;
        const s = d.stats;

        cont.innerHTML = `
            <div class="card" style="background:linear-gradient(135deg, var(--success) 0%, #15803d 100%);color:#fff;margin-bottom:24px;border:none;padding:24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                    <div>
                        <div style="font-size:14px;opacity:.9;margin-bottom:4px;">
                            <i class="bi bi-circle-fill" style="color:#fff;font-size:10px;"></i> Turno #${t.id} ABIERTO
                        </div>
                        <div style="font-size:28px;font-weight:700;margin-bottom:8px;">
                            ${App.escapeHtml(t.pv)}
                        </div>
                        <div style="font-size:15px;opacity:.9;">
                            <i class="bi bi-clock-history"></i> Abierto hace ${formatearDuracion(t.duracion_seg)}
                            · Monto inicial: <strong>${App.formatMoney(t.monto_inicial)}</strong>
                        </div>
                    </div>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;">
                        <a href="${BASE_URL}views/vendedor/pos.php" class="btn" style="background:#fff;color:var(--success);font-size:18px;padding:16px 32px;font-weight:700;">
                            <i class="bi bi-cart-fill" style="font-size:22px;"></i> Ir al POS
                        </a>
                        <button class="btn" style="background:rgba(255,255,255,.2);color:#fff;padding:16px 24px;font-weight:600;border:1px solid rgba(255,255,255,.4);" onclick="VendedorDashboard.cerrarTurnoModal()">
                            <i class="bi bi-stop-circle"></i> Cerrar turno
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats del turno -->
            <div class="grid-stats mb-4">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-icon primary"><i class="bi bi-cart-check-fill"></i></div>
                    </div>
                    <div class="stat-card-label">Ventas del turno</div>
                    <div class="stat-card-value">${App.formatMoney(s.total_vendido)}</div>
                    <div class="stat-card-trend"><i class="bi bi-receipt"></i> ${s.num_ventas} venta${s.num_ventas !== 1 ? 's' : ''}</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-icon success"><i class="bi bi-cash-coin"></i></div>
                    </div>
                    <div class="stat-card-label">Efectivo acumulado</div>
                    <div class="stat-card-value">${App.formatMoney(s.total_efectivo)}</div>
                    <div class="stat-card-trend"><i class="bi bi-info-circle"></i> A entregar al cerrar</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-icon info"><i class="bi bi-bank"></i></div>
                    </div>
                    <div class="stat-card-label">Transferencias</div>
                    <div class="stat-card-value">${App.formatMoney(s.total_transferencia)}</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-icon warning"><i class="bi bi-box-seam"></i></div>
                    </div>
                    <div class="stat-card-label">Productos disponibles</div>
                    <div class="stat-card-value">${d.alertas.productos_disponibles}</div>
                    ${d.alertas.stock_bajo > 0 ? `<div class="stat-card-trend down"><i class="bi bi-exclamation-triangle-fill"></i> ${d.alertas.stock_bajo} con stock bajo</div>` : ''}
                </div>
            </div>

            <!-- Grid: últimas ventas + top productos -->
            <div class="grid-2">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="bi bi-receipt"></i> Últimas ventas del turno</div>
                        <a href="${BASE_URL}views/vendedor/mis_ventas.php" class="btn btn-ghost btn-sm">
                            Ver todas <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    ${d.ultimas_ventas.length === 0 ? `
                        <div class="empty-state" style="padding:30px 15px;">
                            <i class="bi bi-inbox" style="font-size:40px;"></i>
                            <p class="text-muted mt-2">Aún no has vendido nada</p>
                            <a href="${BASE_URL}views/vendedor/pos.php" class="btn btn-primary btn-sm mt-3">
                                <i class="bi bi-cart-fill"></i> Empezar a vender
                            </a>
                        </div>
                    ` : `
                        ${d.ultimas_ventas.map(v => `
                            <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
                                <div style="width:36px;height:36px;border-radius:50%;background:${v.estado === 'cancelada' ? 'var(--danger-light)' : 'var(--success-light)'};color:${v.estado === 'cancelada' ? 'var(--danger)' : 'var(--success)'};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="bi bi-${v.estado === 'cancelada' ? 'x-circle' : 'check-circle'}"></i>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:600;font-size:13px;">${App.escapeHtml(v.folio)}</div>
                                    <div class="text-xs text-muted">${App.timeAgo(v.fecha)} · ${v.num_productos} producto${v.num_productos !== 1 ? 's' : ''}</div>
                                </div>
                                <div style="text-align:right;flex-shrink:0;">
                                    <div style="font-weight:700;font-size:14px;color:${v.estado === 'cancelada' ? 'var(--danger)' : 'var(--primary)'};">
                                        ${App.formatMoney(v.total)}
                                    </div>
                                    ${v.estado === 'cancelada' ? '<div class="text-xs" style="color:var(--danger);">Cancelada</div>' : ''}
                                </div>
                            </div>
                        `).join('')}
                    `}
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="bi bi-trophy-fill"></i> Top productos del turno</div>
                    </div>
                    ${d.top_productos.length === 0 ? `
                        <div class="empty-state" style="padding:30px 15px;">
                            <i class="bi bi-box-seam" style="font-size:40px;"></i>
                            <p class="text-muted mt-2">Sin ventas todavía</p>
                        </div>
                    ` : `
                        ${d.top_productos.map((p, i) => {
                            const medallas = ['🥇', '🥈', '🥉'];
                            return `
                                <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
                                    <div style="font-size:24px;flex-shrink:0;">${medallas[i]}</div>
                                    <div style="flex:1;min-width:0;">
                                        <div style="font-weight:600;font-size:13px;">${App.escapeHtml(p.nombre)}</div>
                                        <div class="text-xs text-muted">${p.unidades} unidades</div>
                                    </div>
                                    <div style="font-weight:700;font-size:14px;color:var(--primary);">${App.formatMoney(p.total)}</div>
                                </div>
                            `;
                        }).join('')}
                    `}
                </div>
            </div>
        `;
    }

    // ============================================================
    // ABRIR TURNO
    // ============================================================
    function abrirTurnoModal() {
        const modalId = 'modal-abrir-turno';

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal">
                    <div class="modal-header">
                        <div class="modal-title"><i class="bi bi-play-circle-fill text-success"></i> Abrir turno</div>
                        <button class="modal-close" onclick="document.getElementById('${modalId}').remove()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill"></i>
                            <div>
                                <strong>Antes de abrir el turno:</strong>
                                <ul style="margin-top:6px;padding-left:20px;">
                                    <li>Cuenta el efectivo que hay en la caja</li>
                                    <li>Ingresa el monto exacto abajo</li>
                                    <li>El sistema guardará una foto del inventario actual</li>
                                </ul>
                            </div>
                        </div>

                        <div class="field mb-3">
                            <label for="monto_inicial">Monto inicial en efectivo <span class="req">*</span></label>
                            <input type="number" id="monto_inicial" min="0" step="0.01" value="0" placeholder="0.00" required autofocus>
                            <div class="help">Efectivo contado al momento de abrir la caja</div>
                        </div>

                        <div class="field">
                            <label for="observaciones">Observaciones</label>
                            <textarea id="observaciones" maxlength="255" placeholder="Notas opcionales (ej: caja con billetes pequeños)" style="min-height:60px;"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.getElementById('${modalId}').remove()">Cancelar</button>
                        <button class="btn btn-success" id="btn-abrir-turno" onclick="VendedorDashboard.confirmarAbrirTurno()">
                            <i class="bi bi-play-circle"></i> Abrir turno
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        setTimeout(() => document.getElementById('monto_inicial')?.focus(), 100);
    }

    async function confirmarAbrirTurno() {
        const montoInput = document.getElementById('monto_inicial');
        const obsInput = document.getElementById('observaciones');
        const btn = document.getElementById('btn-abrir-turno');

        const monto = parseFloat(montoInput.value) || 0;
        const observaciones = obsInput.value.trim();

        if (monto < 0) {
            Toast.warning('Monto inválido', 'El monto no puede ser negativo');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Abriendo turno...';

        const res = await Api.post('api/turnos_vendedor.php?accion=abrir', {
            monto_inicial: monto,
            observaciones: observaciones,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-circle"></i> Abrir turno';

        if (!res.success) {
            Toast.error('Error al abrir turno', res.message);
            return;
        }

        Toast.success('Turno abierto', `${res.data.productos_cargados} productos cargados`);

        document.getElementById('modal-abrir-turno')?.remove();

        // Recargar el dashboard
        cargar();
    }

    // ============================================================
    // CERRAR TURNO
    // ============================================================
    function cerrarTurnoModal() {
        // Redirigir a la vista dedicada de cierre
        window.location.href = BASE_URL + 'views/vendedor/cerrar_turno.php';
    }

    // ============================================================
    // HELPERS
    // ============================================================
    function formatearDuracion(seg) {
        if (!seg || seg < 0) return '0 min';
        const h = Math.floor(seg / 3600);
        const m = Math.floor((seg % 3600) / 60);
        if (h > 0) return `${h}h ${m}min`;
        return `${m} min`;
    }

    // ============================================================
    // INICIALIZACIÓN
    // ============================================================
    document.addEventListener('DOMContentLoaded', cargar);

    return {
        cargar,
        abrirTurnoModal,
        confirmarAbrirTurno,
        cerrarTurnoModal,
    };
})();

window.VendedorDashboard = VendedorDashboard;