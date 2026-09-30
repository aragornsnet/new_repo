/**
 * IPV - Cierre de turno (calculadora + cuadre + cierre)
 */

const CerrarTurno = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let datos = null;
    let conteoCup = {};      // { denom_id: cantidad }
    let conteoUsd = {};
    let denomsCup = [];
    let denomsUsd = [];

    // ============================================================
    // INICIALIZAR
    // ============================================================
    async function init() {
        const loading = document.getElementById('loading');
        loading?.classList.add('active');

        try {
            const res = await Api.get('api/turnos_vendedor.php?accion=resumen_cierre');
            loading?.classList.remove('active');

            if (!res.success) {
                Toast.error('Error', res.message);
                setTimeout(() => window.location.href = BASE_URL + 'views/vendedor/dashboard.php', 2000);
                return;
            }

            datos = res.data;
            denomsCup = res.data.denominaciones_cup;
            denomsUsd = res.data.denominaciones_usd;

            render();

        } catch (e) {
            loading?.classList.remove('active');
            console.error('Error:', e);
            Toast.error('Error', 'No se pudo cargar el resumen de cierre');
        }
    }

    // ============================================================
    // RENDER PRINCIPAL
    // ============================================================
    function render() {
        const cont = document.getElementById('contenido-cierre');
        const t = datos.turno;
        const r = datos.resumen;
        const hayUsd = r.usd_esperado > 0;

        cont.innerHTML = `
            <!-- Resumen del turno -->
            <div class="card mb-4">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-info-circle-fill"></i> Resumen del turno</div>
                </div>
                <div class="grid-2">
                    <div><div class="text-xs text-muted">Punto de venta</div><div style="font-weight:600;">${App.escapeHtml(t.pv)}</div></div>
                    <div><div class="text-xs text-muted">Duración</div><div style="font-weight:600;">${formatearDuracion(t.duracion_seg)}</div></div>
                    <div><div class="text-xs text-muted">Apertura</div><div style="font-weight:600;">${App.formatDate(t.fecha_apertura)}</div></div>
                    <div><div class="text-xs text-muted">Monto inicial</div><div style="font-weight:600;">${App.formatMoney(t.monto_inicial)}</div></div>
                    <div><div class="text-xs text-muted">Número de ventas</div><div style="font-weight:600;">${r.num_ventas}</div></div>
                    <div><div class="text-xs text-muted">Total vendido</div><div style="font-weight:700;color:var(--primary);font-size:18px;">${App.formatMoney(r.total_vendido)}</div></div>
                    <div><div class="text-xs text-muted">Efectivo CUP</div><div style="font-weight:600;color:var(--success);">${App.formatMoney(r.total_efectivo)}</div></div>
                    <div><div class="text-xs text-muted">Transferencias</div><div style="font-weight:600;color:var(--info);">${App.formatMoney(r.total_transferencia)}</div></div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- CALCULADORA CUP -->
            <!-- ============================================ -->
            <div class="card mb-4">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-cash-stack text-success"></i> Cierre de efectivo CUP</div>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill"></i>
                    <div>
                        Cuenta los billetes que tienes en caja y anota la cantidad de cada uno.
                        El sistema calculará el total contado y lo comparará con el esperado.
                    </div>
                </div>

                <div class="grid-2 mb-3">
                    <div class="card" style="background:var(--surface-2);">
                        <div class="text-xs text-muted">Efectivo teórico esperado</div>
                        <div style="font-weight:700;font-size:22px;color:var(--primary);" id="cup-esperado">
                            ${App.formatMoney(r.efectivo_teorico)}
                        </div>
                        <div class="text-xs text-muted mt-1">
                            (inicial ${App.formatMoney(t.monto_inicial)} + ventas efectivo ${App.formatMoney(r.total_efectivo)})
                        </div>
                    </div>

                    <div class="card" style="background:var(--success-light);" id="cup-contado-card">
                        <div class="text-xs text-muted">Total contado en caja</div>
                        <div style="font-weight:700;font-size:22px;color:var(--success);" id="cup-contado">$0.00</div>
                        <div class="text-xs mt-1" id="cup-diferencia" style="font-weight:600;">—</div>
                    </div>
                </div>

                <div class="field mb-3">
                    <label>Conteo de billetes CUP</label>
                    <div class="denom-calc-grid" id="calc-cup"></div>
                </div>

                <div class="text-center">
                    <button class="btn btn-ghost btn-sm" onclick="CerrarTurno.limpiarConteoCup()">
                        <i class="bi bi-x-circle"></i> Limpiar conteo CUP
                    </button>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- CALCULADORA USD (solo si hubo ventas en USD) -->
            <!-- ============================================ -->
            ${hayUsd ? `
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="card-title"><i class="bi bi-currency-exchange text-info"></i> Cierre de efectivo USD</div>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill"></i>
                        <div>Cuenta los billetes en USD que tienes en caja.</div>
                    </div>

                    <div class="grid-2 mb-3">
                        <div class="card" style="background:var(--surface-2);">
                            <div class="text-xs text-muted">USD esperado</div>
                            <div style="font-weight:700;font-size:22px;color:var(--primary);" id="usd-esperado">
                                $${App.formatNumber(r.usd_esperado, 2)} USD
                            </div>
                        </div>

                        <div class="card" style="background:var(--info-light);" id="usd-contado-card">
                            <div class="text-xs text-muted">Total contado en USD</div>
                            <div style="font-weight:700;font-size:22px;color:var(--info);" id="usd-contado">$0.00 USD</div>
                            <div class="text-xs mt-1" id="usd-diferencia" style="font-weight:600;">—</div>
                        </div>
                    </div>

                    <div class="field mb-3">
                        <label>Conteo de billetes USD</label>
                        <div class="denom-calc-grid" id="calc-usd"></div>
                    </div>

                    <div class="text-center">
                        <button class="btn btn-ghost btn-sm" onclick="CerrarTurno.limpiarConteoUsd()">
                            <i class="bi bi-x-circle"></i> Limpiar conteo USD
                        </button>
                    </div>
                </div>
            ` : ''}

            <!-- ============================================ -->
            <!-- OBSERVACIONES -->
            <!-- ============================================ -->
            <div class="card mb-4">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-chat-left-text"></i> Observaciones</div>
                </div>
                <div class="field">
                    <label for="observaciones">
                        Observaciones del cierre
                        <span class="req" id="obs-required" style="display:none;">*</span>
                    </label>
                    <textarea id="observaciones" maxlength="500" placeholder="Escribe cualquier observación sobre el cierre del turno (obligatorio si hay descuadre)" style="min-height:80px;"></textarea>
                    <div class="help" id="obs-help">Si hay descuadre, es obligatorio explicar qué pasó.</div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- CONTEO DE INVENTARIO (OPCIONAL) -->
            <!-- ============================================ -->
            <div class="card mb-4">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-box-seam"></i> Conteo de inventario (opcional)</div>
                </div>
                <p class="text-muted mb-3">
                    El conteo de inventario es <strong>opcional</strong>. Hazlo solo si quieres verificar el stock físico del punto de venta.
                    Si no lo haces, el sistema cerrará con el cálculo teórico.
                </p>
                <button class="btn btn-secondary" id="btn-toggle-inventario" onclick="CerrarTurno.toggleInventario()">
                    <i class="bi bi-clipboard-check"></i> Hacer conteo de inventario
                </button>
                <div id="inventario-panel" style="display:none;margin-top:16px;">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <strong>Atención:</strong> el conteo de inventario puede llevar varios minutos.
                            Si decides hacerlo, cuenta físicamente cada producto del PV.
                        </div>
                    </div>
                    <div id="inventario-tabla">
                        <div class="empty-state"><div class="spinner"></div></div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- ACCIONES -->
            <!-- ============================================ -->
            <div class="card" style="background:var(--surface-2);">
                <div class="d-flex gap-3" style="flex-wrap:wrap;">
                    <a href="<?= BASE_URL ?>views/vendedor/pos.php" class="btn btn-secondary" style="flex:1;">
                        <i class="bi bi-cart-check"></i> Volver al POS
                    </a>
                    <button class="btn btn-danger" id="btn-cerrar" onclick="CerrarTurno.confirmarCierre()" style="flex:2;font-size:16px;font-weight:700;padding:14px;">
                        <i class="bi bi-lock-fill"></i> CERRAR TURNO
                    </button>
                </div>
                <div class="text-center mt-3">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        Al cerrar el turno no podrás registrar más ventas hasta abrir uno nuevo.
                    </small>
                </div>
            </div>
        `;

        // Renderizar calculadoras
        renderCalculadora('calc-cup', denomsCup, conteoCup, 'CUP');
        if (hayUsd) {
            renderCalculadora('calc-usd', denomsUsd, conteoUsd, 'USD');
        }

        recalcular();
    }

    // ============================================================
    // CALCULADORA
    // ============================================================
    function renderCalculadora(contenedorId, denominaciones, conteo, moneda) {
        const cont = document.getElementById(contenedorId);
        if (!cont) return;

        if (!denominaciones.length) {
            cont.innerHTML = `<div class="alert alert-warning">No hay denominaciones configuradas para ${moneda}</div>`;
            return;
        }

        const total = calcularTotal(moneda);

        cont.innerHTML = `
            <div class="calc-tabla">
                <div class="calc-tabla-header">
                    <div>Billete</div>
                    <div style="text-align:center;">Cantidad</div>
                    <div style="text-align:right;">Subtotal</div>
                </div>
                <div class="calc-tabla-body">
                    ${denominaciones.map(d => {
                        const cantidad = conteo[d.id] || 0;
                        const subtotal = cantidad * parseFloat(d.valor);
                        const activo = cantidad > 0;

                        return `
                            <div class="calc-tabla-row ${activo ? 'con-valor' : ''}" data-denom-id="${d.id}" data-moneda="${moneda}">
                                <div class="calc-tabla-billete">
                                    💵 $${App.formatNumber(d.valor)}
                                </div>
                                <div class="calc-tabla-cantidad">
                                    <input type="number" class="calc-input" min="0" value="${cantidad}"
                                           onchange="CerrarTurno.setCantidad('${moneda}', ${d.id}, this.value)"
                                           onfocus="this.select()"
                                           placeholder="0">
                                </div>
                                <div class="calc-tabla-subtotal" data-subtotal="${d.id}">
                                    ${App.formatMoney(subtotal)}
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
                <div class="calc-tabla-footer">
                    <div class="calc-total-label">TOTAL CONTADO</div>
                    <div class="calc-total-value" id="calc-total-${moneda}">
                        ${App.formatMoney(total)}
                    </div>
                </div>
            </div>
        `;
    }

    function cambiarCantidad(moneda, denomId, delta) {
        const conteo = moneda === 'CUP' ? conteoCup : conteoUsd;
        const cantidad = (conteo[denomId] || 0) + delta;
        if (cantidad < 0) return;
        conteo[denomId] = cantidad;
        actualizarFila(moneda, denomId);
        recalcular();
    }

    function setCantidad(moneda, denomId, valor) {
        const conteo = moneda === 'CUP' ? conteoCup : conteoUsd;
        const cantidad = parseInt(valor) || 0;
        conteo[denomId] = cantidad < 0 ? 0 : cantidad;
        actualizarFila(moneda, denomId);
        recalcular();
    }

    function actualizarFila(moneda, denomId) {
        const conteo = moneda === 'CUP' ? conteoCup : conteoUsd;
        const cantidad = conteo[denomId] || 0;
        const denominaciones = moneda === 'CUP' ? denomsCup : denomsUsd;
        const denom = denominaciones.find(d => d.id == denomId);
        if (!denom) return;

        // Actualizar input
        const input = document.querySelector(`.calc-tabla-row[data-denom-id="${denomId}"][data-moneda="${moneda}"] .calc-input`);
        if (input && document.activeElement !== input) input.value = cantidad;

        // Actualizar subtotal
        const subtotalEl = document.querySelector(`.calc-tabla-row[data-denom-id="${denomId}"][data-moneda="${moneda}"] .calc-tabla-subtotal`);
        if (subtotalEl) subtotalEl.textContent = App.formatMoney(cantidad * parseFloat(denom.valor));

        // Marcar fila activa
        const row = document.querySelector(`.calc-tabla-row[data-denom-id="${denomId}"][data-moneda="${moneda}"]`);
        if (row) row.classList.toggle('con-valor', cantidad > 0);

        // Actualizar total
        const totalEl = document.getElementById(`calc-total-${moneda}`);
        if (totalEl) totalEl.textContent = App.formatMoney(calcularTotal(moneda));
    }

    function calcularTotal(moneda) {
        const conteo = moneda === 'CUP' ? conteoCup : conteoUsd;
        const denominaciones = moneda === 'CUP' ? denomsCup : denomsUsd;
        let total = 0;
        for (const denomId in conteo) {
            const denom = denominaciones.find(d => d.id == denomId);
            if (denom) total += conteo[denomId] * parseFloat(denom.valor);
        }
        return Math.round(total * 100) / 100;
    }

    function limpiarConteoCup() {
        conteoCup = {};
        document.querySelectorAll('.calc-tabla-row[data-moneda="CUP"] .calc-input').forEach(i => i.value = 0);
        document.querySelectorAll('.calc-tabla-row[data-moneda="CUP"] .calc-tabla-subtotal').forEach(e => e.textContent = App.formatMoney(0));
        document.querySelectorAll('.calc-tabla-row[data-moneda="CUP"]').forEach(r => r.classList.remove('con-valor'));

        const totalEl = document.getElementById('calc-total-CUP');
        if (totalEl) totalEl.textContent = App.formatMoney(0);

        recalcular();
    }

    function limpiarConteoUsd() {
        conteoUsd = {};
        document.querySelectorAll('.calc-tabla-row[data-moneda="USD"] .calc-input').forEach(i => i.value = 0);
        document.querySelectorAll('.calc-tabla-row[data-moneda="USD"] .calc-tabla-subtotal').forEach(e => e.textContent = App.formatMoney(0));
        document.querySelectorAll('.calc-tabla-row[data-moneda="USD"]').forEach(r => r.classList.remove('con-valor'));

        const totalEl = document.getElementById('calc-total-USD');
        if (totalEl) totalEl.textContent = App.formatMoney(0);

        recalcular();
    }

    // ============================================================
    // RECALCULAR DIFERENCIAS
    // ============================================================
    function recalcular() {
        const r = datos.resumen;
        const hayUsd = r.usd_esperado > 0;

        // CUP
        const cupContado = calcularTotal('CUP');
        const cupEsperado = r.efectivo_teorico;
        const cupDif = Math.round((cupContado - cupEsperado) * 100) / 100;

        document.getElementById('cup-contado').textContent = App.formatMoney(cupContado);

        const cupDifEl = document.getElementById('cup-diferencia');
        const cupCard = document.getElementById('cup-contado-card');

        if (cupDif === 0) {
            cupDifEl.innerHTML = '<span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> Exacto</span>';
            cupCard.style.background = 'var(--success-light)';
        } else if (cupDif > 0) {
            cupDifEl.innerHTML = `<span class="badge badge-warning"><i class="bi bi-arrow-up-circle-fill"></i> Sobran ${App.formatMoney(cupDif)}</span>`;
            cupCard.style.background = 'var(--warning-light)';
        } else {
            cupDifEl.innerHTML = `<span class="badge badge-danger"><i class="bi bi-arrow-down-circle-fill"></i> Faltan ${App.formatMoney(Math.abs(cupDif))}</span>`;
            cupCard.style.background = 'var(--danger-light)';
        }

        // USD
        let usdDif = 0;
        if (hayUsd) {
            const usdContado = calcularTotal('USD');
            const usdEsperado = r.usd_esperado;
            usdDif = Math.round((usdContado - usdEsperado) * 100) / 100;

            document.getElementById('usd-contado').textContent = `$${App.formatNumber(usdContado, 2)} USD`;

            const usdDifEl = document.getElementById('usd-diferencia');
            const usdCard = document.getElementById('usd-contado-card');

            if (usdDif === 0) {
                usdDifEl.innerHTML = '<span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> Exacto</span>';
                usdCard.style.background = 'var(--success-light)';
            } else if (usdDif > 0) {
                usdDifEl.innerHTML = `<span class="badge badge-warning"><i class="bi bi-arrow-up-circle-fill"></i> Sobran $${App.formatNumber(usdDif, 2)}</span>`;
                usdCard.style.background = 'var(--warning-light)';
            } else {
                usdDifEl.innerHTML = `<span class="badge badge-danger"><i class="bi bi-arrow-down-circle-fill"></i> Faltan $${App.formatNumber(Math.abs(usdDif), 2)}</span>`;
                usdCard.style.background = 'var(--danger-light)';
            }
        }

        // Marcar observaciones como obligatorias si hay descuadre
        const hayDescuadre = (Math.abs(cupDif) > 0.01) || (Math.abs(usdDif) > 0.01);
        const obsReq = document.getElementById('obs-required');
        const obsHelp = document.getElementById('obs-help');

        if (hayDescuadre) {
            obsReq.style.display = '';
            obsHelp.innerHTML = '<strong style="color:var(--danger);">Obligatorio: hay descuadre, explica qué pasó.</strong>';
        } else {
            obsReq.style.display = 'none';
            obsHelp.textContent = 'Si hay descuadre, es obligatorio explicar qué pasó.';
        }
    }

    // ============================================================
    // INVENTARIO (opcional)
    // ============================================================
    function toggleInventario() {
        const panel = document.getElementById('inventario-panel');
        const btn = document.getElementById('btn-toggle-inventario');

        if (panel.style.display === 'none') {
            panel.style.display = '';
            btn.innerHTML = '<i class="bi bi-x-circle"></i> Cancelar conteo de inventario';
            cargarInventario();
        } else {
            panel.style.display = 'none';
            btn.innerHTML = '<i class="bi bi-clipboard-check"></i> Hacer conteo de inventario';
        }
    }

    async function cargarInventario() {
        const cont = document.getElementById('inventario-tabla');

        const res = await Api.get('api/turnos_vendedor.php?accion=resumen_cierre');
        if (!res.success) {
            cont.innerHTML = `<div class="alert alert-danger">${res.message}</div>`;
            return;
        }

        // Obtener el inventario del turno
        const res2 = await Api.get('api/turnos_vendedor.php?accion=resumen_cierre');
        // Necesitamos un endpoint que devuelva el inventario. Por ahora,
        // usamos el resumen de cierre y pedimos aparte. Si no existe, mostramos aviso.
        // TODO: crear endpoint dedicado si se necesita.

        cont.innerHTML = `
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    El conteo de inventario está en desarrollo. Por ahora puedes cerrar el turno sin contar inventario.
                    Esta funcionalidad estará disponible en una próxima versión.
                </div>
            </div>
        `;
    }

    // ============================================================
    // CONFIRMAR CIERRE
    // ============================================================
    async function confirmarCierre() {
        const r = datos.resumen;
        const hayUsd = r.usd_esperado > 0;

        const cupContado = calcularTotal('CUP');
        const usdContado = hayUsd ? calcularTotal('USD') : 0;

        const observaciones = document.getElementById('observaciones').value.trim();

        const cupDif = Math.round((cupContado - r.efectivo_teorico) * 100) / 100;
        const usdDif = Math.round((usdContado - r.usd_esperado) * 100) / 100;
        const hayDescuadre = (Math.abs(cupDif) > 0.01) || (Math.abs(usdDif) > 0.01);

        if (hayDescuadre && observaciones.length < 5) {
            Toast.warning('Falta observación', 'Debes indicar observaciones cuando hay descuadre');
            document.getElementById('observaciones').focus();
            return;
        }

        // Confirmación
        let mensaje = `¿Cerrar el turno?\n\n`;
        mensaje += `Efectivo CUP contado: ${App.formatMoney(cupContado)}\n`;
        mensaje += `Efectivo CUP teórico: ${App.formatMoney(r.efectivo_teorico)}\n`;
        if (cupDif !== 0) mensaje += `Diferencia CUP: ${cupDif > 0 ? '+' : ''}${App.formatMoney(cupDif)}\n`;

        if (hayUsd) {
            mensaje += `\nUSD contado: $${App.formatNumber(usdContado, 2)}\n`;
            mensaje += `USD esperado: $${App.formatNumber(r.usd_esperado, 2)}\n`;
            if (usdDif !== 0) mensaje += `Diferencia USD: ${usdDif > 0 ? '+' : ''}$${App.formatNumber(usdDif, 2)}\n`;
        }

        mensaje += `\nDespués de cerrar, no podrás vender hasta abrir un nuevo turno.`;

        const ok = await App.confirmar(mensaje, '🔒 Confirmar cierre de turno');
        if (!ok) return;

        const btn = document.getElementById('btn-cerrar');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Cerrando turno...';

        // Preparar payload
        const payload = {
            monto_final_cup: cupContado,
            monto_final_usd: usdContado,
            observaciones: observaciones,
            conteo_cup: getConteoDetalle(conteoCup, denomsCup),
            conteo_usd: getConteoDetalle(conteoUsd, denomsUsd),
        };

        const res = await Api.post('api/turnos_vendedor.php?accion=cerrar', payload);

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-lock-fill"></i> CERRAR TURNO';

        if (!res.success) {
            Toast.error('Error al cerrar', res.message);
            return;
        }

        // Mostrar éxito
        mostrarExito(res.data);
    }

    function getConteoDetalle(conteo, denominaciones) {
        const detalle = [];
        for (const denomId in conteo) {
            const cantidad = conteo[denomId];
            if (cantidad > 0) {
                const denom = denominaciones.find(d => d.id == denomId);
                if (denom) {
                    detalle.push({
                        valor: parseFloat(denom.valor),
                        cantidad: cantidad,
                        subtotal: cantidad * parseFloat(denom.valor),
                    });
                }
            }
        }
        return detalle;
    }

    function mostrarExito(data) {
        const modalId = 'modal-cierre-exito';

        const html = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal" style="max-width:520px;">
                    <div class="modal-body" style="text-align:center;padding:40px 24px;">
                        <div style="width:96px;height:96px;border-radius:50%;background:var(--success-light);color:var(--success);display:flex;align-items:center;justify-content:center;font-size:52px;margin:0 auto 20px;">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <h2 style="font-size:24px;margin-bottom:8px;">¡Turno cerrado!</h2>
                        <p class="text-muted">Turno #${data.turno_id}</p>

                        <div style="text-align:left;margin-top:24px;padding:16px;background:var(--surface-2);border-radius:var(--radius-md);">
                            <div style="display:flex;justify-content:space-between;padding:6px 0;">
                                <span>Ventas del turno:</span><strong>${data.num_ventas}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:6px 0;">
                                <span>Total vendido:</span><strong>${App.formatMoney(data.total_ventas)}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid var(--border);margin-top:6px;padding-top:10px;">
                                <span>Efectivo esperado:</span><strong>${App.formatMoney(data.efectivo_teorico)}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:6px 0;">
                                <span>Efectivo contado:</span><strong>${App.formatMoney(data.efectivo_contado)}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:6px 0;">
                                <span>Diferencia CUP:</span>
                                <strong style="color:${data.descuadre_cup === 0 ? 'var(--success)' : (data.descuadre_cup > 0 ? 'var(--warning)' : 'var(--danger)')};">
                                    ${data.descuadre_cup > 0 ? '+' : ''}${App.formatMoney(data.descuadre_cup)}
                                </strong>
                            </div>
                            ${data.usd_esperado > 0 ? `
                                <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid var(--border);margin-top:6px;padding-top:10px;">
                                    <span>USD esperado:</span><strong>$${App.formatNumber(data.usd_esperado, 2)}</strong>
                                </div>
                                <div style="display:flex;justify-content:space-between;padding:6px 0;">
                                    <span>USD contado:</span><strong>$${App.formatNumber(data.usd_contado, 2)}</strong>
                                </div>
                                <div style="display:flex;justify-content:space-between;padding:6px 0;">
                                    <span>Diferencia USD:</span>
                                    <strong style="color:${data.descuadre_usd === 0 ? 'var(--success)' : (data.descuadre_usd > 0 ? 'var(--warning)' : 'var(--danger)')};">
                                        ${data.descuadre_usd > 0 ? '+' : ''}$${App.formatNumber(data.descuadre_usd, 2)}
                                    </strong>
                                </div>
                            ` : ''}
                        </div>

                        <div class="alert alert-info mt-3" style="text-align:left;">
                            <i class="bi bi-info-circle-fill"></i>
                            <div>Para volver a vender, debes <strong>abrir un nuevo turno</strong> desde el dashboard.</div>
                        </div>

                        <a href="${BASE_URL}views/vendedor/dashboard.php" class="btn btn-primary btn-block btn-lg mt-3">
                            <i class="bi bi-house"></i> Ir al dashboard
                        </a>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', html);
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
    // INIT
    // ============================================================
    document.addEventListener('DOMContentLoaded', init);

    return {
        cambiarCantidad,
        setCantidad,
        limpiarConteoCup,
        limpiarConteoUsd,
        toggleInventario,
        confirmarCierre,
    };
})();

window.CerrarTurno = CerrarTurno;