/**
 * IPV - Gestión de Divisas
 */

const Divisas = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let lista = [];
    let config = {};

    async function cargar() {
        const cont = document.getElementById('tabla-divisas');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get('api/divisas.php?accion=listar');
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        lista = res.data.divisas;
        config = res.data.config;

        renderConfig();
        render();
    }

    function recargar() { cargar(); }

    function renderConfig() {
        const cont = document.getElementById('divisas-resumen');
        const items = [
            ['Habilitadas',        config.habilitadas    ? 'Sí' : 'No'],
            ['Actualización auto', config.auto_update    ? 'Sí' : 'No'],
            ['Fuente auto',        config.auto_fuente    || '—'],
            ['Frecuencia',         config.auto_frecuencia|| '—'],
            ['Permitir manual',    config.permitir_manual? 'Sí' : 'No'],
            ['Vigencia manual',    config.manual_horas + ' h'],
        ];

        cont.innerHTML = items.map(([label, valor]) => `
            <div class="stat-card">
                <div class="stat-card-label">${App.escapeHtml(label)}</div>
                <div class="stat-card-value" style="font-size:18px;">${App.escapeHtml(String(valor))}</div>
            </div>
        `).join('');
    }

    function render() {
        const cont = document.getElementById('tabla-divisas');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state"><i class="bi bi-currency-exchange"></i><p>Sin divisas</p></div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th class="text-right">Tasa actual</th>
                            <th>Origen</th>
                            <th>Actualizada</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(d => {
                            const activa = d.activo == 1;
                            const origen = d.origen || '—';
                            const badgeOrigen = origen === 'manual'
                                ? '<span class="badge badge-warning">Manual</span>'
                                : (origen === 'auto'
                                    ? '<span class="badge badge-success">Automática</span>'
                                    : '<span class="badge badge-neutral">Sin tasa</span>');

                            return `
                                <tr style="${!activa ? 'opacity:.6;' : ''}">
                                    <td><strong>${App.escapeHtml(d.codigo)}</strong></td>
                                    <td>${App.escapeHtml(d.nombre)} <span class="text-muted">(${App.escapeHtml(d.simbolo)})</span></td>
                                    <td class="text-right">
                                        ${d.tasa_actual
                                            ? `<strong>${App.formatNumber(d.tasa_actual)}</strong>`
                                            : '<span class="text-muted">—</span>'}
                                    </td>
                                    <td>${badgeOrigen}${d.fuente ? ` <span class="text-xs text-muted">${App.escapeHtml(d.fuente)}</span>` : ''}</td>
                                    <td class="text-muted text-xs">${d.fecha_tasa ? App.formatDate(d.fecha_tasa) : '—'}</td>
                                    <td>${activa ? '<span class="badge badge-success">Activa</span>' : '<span class="badge badge-neutral">Inactiva</span>'}</td>
                                    <td class="text-right">
                                        <div class="d-flex gap-1" style="justify-content:flex-end;">
                                            <button class="btn btn-primary btn-sm" onclick="Divisas.abrirTasa(${d.id})" title="Fijar tasa" ${config.permitir_manual ? '' : 'disabled'}>
                                                <i class="bi bi-pencil-square"></i> Fijar tasa
                                            </button>
                                            <button class="btn btn-ghost btn-icon" onclick="Divisas.verHistorial(${d.id}, '${App.escapeHtml(d.codigo)}')" title="Historial">
                                                <i class="bi bi-clock-history"></i>
                                            </button>
                                            <button class="btn btn-ghost btn-icon" onclick="Divisas.cambiarEstado(${d.id})" title="${activa ? 'Desactivar' : 'Activar'}">
                                                <i class="bi bi-${activa ? 'toggle-on' : 'toggle-off'}"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function abrirTasa(id) {
        const d = lista.find(x => x.id == id);
        if (!d) return;

        const modalId = 'modal-fijar-tasa';
        document.getElementById(modalId)?.remove();

        const html = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal">
                    <div class="modal-header">
                        <div class="modal-title"><i class="bi bi-pencil-square"></i> Fijar tasa manual - ${App.escapeHtml(d.codigo)}</div>
                        <button class="modal-close" onclick="document.getElementById('${modalId}').remove()"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill"></i>
                            <div>Tasa actual: <strong>${d.tasa_actual ? App.formatNumber(d.tasa_actual) : '—'}</strong> CUP/${App.escapeHtml(d.codigo)} (${d.origen || 'sin origen'})</div>
                        </div>

                        <div class="field mb-3">
                            <label for="tasa-nueva">Nueva tasa (CUP por 1 ${App.escapeHtml(d.codigo)}) <span class="req">*</span></label>
                            <input type="number" id="tasa-nueva" min="0.0001" step="0.0001" placeholder="Ej: 320.0000" autofocus>
                            <div class="help">Vigencia: ${config.manual_horas} hora(s) antes de volver a la automática.</div>
                        </div>

                        <div class="field">
                            <label for="tasa-motivo">Motivo <span class="req">*</span></label>
                            <input type="text" id="tasa-motivo" maxlength="150" placeholder="Ej: Ajuste por mercado informal">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.getElementById('${modalId}').remove()">Cancelar</button>
                        <button class="btn btn-primary" id="btn-guardar-tasa" onclick="Divisas.guardarTasa(${d.id})">
                            <i class="bi bi-check-lg"></i> Guardar tasa
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', html);
        setTimeout(() => document.getElementById('tasa-nueva')?.focus(), 100);
    }

    async function guardarTasa(divisaId) {
        const tasa   = parseFloat(document.getElementById('tasa-nueva').value);
        const motivo = document.getElementById('tasa-motivo').value.trim();

        if (!tasa || tasa <= 0)  { Toast.warning('Tasa inválida', 'Debe ser mayor a 0'); return; }
        if (motivo.length < 3)   { Toast.warning('Falta motivo', 'Mínimo 3 caracteres'); return; }

        const btn = document.getElementById('btn-guardar-tasa');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Guardando...';

        const res = await Api.post('api/divisas.php?accion=actualizar_tasa', {
            divisa_id: divisaId, tasa, motivo,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar tasa';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Tasa actualizada', `${App.formatNumber(res.data.tasa_nueva)} (antes ${res.data.tasa_anterior ? App.formatNumber(res.data.tasa_anterior) : '—'})`);
        document.getElementById('modal-fijar-tasa')?.remove();
        cargar();
    }

    async function verHistorial(divisaId, codigo) {
        const modalId = 'modal-historial-divisa';
        document.getElementById('hist-divisa-titulo').textContent = 'Historial - ' + codigo;
        document.getElementById('hist-divisa-contenido').innerHTML = '<div class="empty-state"><div class="spinner"></div></div>';
        document.getElementById(modalId).classList.add('active');

        const res = await Api.get(`api/divisas.php?accion=historial&divisa_id=${divisaId}&limit=200`);
        const cont = document.getElementById('hist-divisa-contenido');

        if (!res.success) {
            cont.innerHTML = `<div class="alert alert-danger">${App.escapeHtml(res.message)}</div>`;
            return;
        }

        const hist = res.data;
        if (!hist.length) {
            cont.innerHTML = `<div class="empty-state"><i class="bi bi-inbox"></i><p>Sin cambios registrados</p></div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap" style="max-height:450px;overflow-y:auto;">
                <table class="tabla">
                    <thead style="position:sticky;top:0;background:var(--surface-2);z-index:1;">
                        <tr>
                            <th>Fecha</th>
                            <th class="text-right">Tasa</th>
                            <th>Origen</th>
                            <th>Fuente</th>
                            <th>Usuario</th>
                            <th>Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${hist.map(h => `
                            <tr>
                                <td class="text-muted text-xs">${App.formatDate(h.fecha)}</td>
                                <td class="text-right"><strong>${App.formatNumber(h.tasa)}</strong></td>
                                <td>${h.origen === 'manual'
                                    ? '<span class="badge badge-warning">Manual</span>'
                                    : '<span class="badge badge-success">Automática</span>'}</td>
                                <td class="text-muted text-xs">${App.escapeHtml(h.fuente || '—')}</td>
                                <td class="text-muted text-xs">${App.escapeHtml(h.usuario || 'Sistema')}</td>
                                <td class="text-muted text-xs">${App.escapeHtml(h.motivo || '—')}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    async function cambiarEstado(id) {
        const d = lista.find(x => x.id == id);
        if (!d) return;

        const accion = d.activo ? 'desactivar' : 'activar';
        const ok = await App.confirmar(`¿${accion.charAt(0).toUpperCase() + accion.slice(1)} la divisa "${d.codigo}"?`);
        if (!ok) return;

        const res = await Api.post('api/divisas.php?accion=cambiar_estado', { id });
        if (!res.success) { Toast.error('Error', res.message); return; }
        Toast.success('Listo', res.message);
        cargar();
    }

    document.addEventListener('DOMContentLoaded', cargar);

    return { cargar, recargar, abrirTasa, guardarTasa, verHistorial, cambiarEstado };
})();

window.Divisas = Divisas;