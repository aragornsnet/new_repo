/**
 * IPV - Gestión de Clientes Mayoristas
 */

const Clientes = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let lista = [];
    let modoEdicion = false;
    let puedeEditar = false;

    // ═══════════════════════════════════════════════════════════
    // LISTAR
    // ═══════════════════════════════════════════════════════════
    async function cargar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const estado = document.getElementById('filtro-estado')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (estado) params.append('estado', estado);

        const cont = document.getElementById('tabla-clientes');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get('api/clientes.php?accion=listar&' + params);
        if (!res.success) {
            Toast.error('Error', res.message);
            cont.innerHTML = `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        lista = res.data;
        document.getElementById('total-clientes').textContent = lista.length;
        document.getElementById('total-clientes-2').textContent = lista.length;
        render();
    }

    // ═══════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════
    function render() {
        const cont = document.getElementById('tabla-clientes');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-people"></i>
                <h3>Sin clientes</h3>
                <p>No hay clientes registrados con los filtros actuales.</p>
                ${puedeEditar ? `<button class="btn btn-primary btn-sm mt-3" onclick="Clientes.abrirNuevo()">
                    <i class="bi bi-plus-lg"></i> Crear primer cliente
                </button>` : ''}
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>NIT</th>
                            <th>Contacto</th>
                            <th class="text-right">Facturas pend.</th>
                            <th class="text-right">Saldo pend.</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(c => {
                            const activo = c.activo == 1;
                            const pendientes = parseInt(c.facturas_pendientes) || 0;
                            const saldo = parseFloat(c.saldo_pendiente) || 0;

                            return `
                                <tr style="${!activo ? 'opacity:.6;' : ''}">
                                    <td>
                                        <div style="font-weight:600;">${App.escapeHtml(c.nombre)}</div>
                                        <div class="text-xs text-muted">
                                            ${c.tipo_persona === 'fisica' ? 'Persona física' : 'Persona jurídica'}
                                            ${c.email ? ' · ' + App.escapeHtml(c.email) : ''}
                                        </div>
                                    </td>
                                    <td class="text-muted text-sm">${App.escapeHtml(c.nit || '—')}</td>
                                    <td class="text-muted text-sm">
                                        ${c.contacto ? App.escapeHtml(c.contacto) : '—'}
                                        ${c.telefono ? `<div class="text-xs">${App.escapeHtml(c.telefono)}</div>` : ''}
                                    </td>
                                    <td class="text-right">
                                        ${pendientes > 0
                                            ? `<span class="badge badge-warning">${pendientes}</span>`
                                            : '<span class="text-muted text-xs">—</span>'}
                                    </td>
                                    <td class="text-right">
                                        ${saldo > 0
                                            ? `<strong style="color:var(--danger);">${App.formatMoney(saldo)}</strong>`
                                            : '<span class="text-muted text-xs">—</span>'}
                                    </td>
                                    <td>
                                        ${activo
                                            ? '<span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> Activo</span>'
                                            : '<span class="badge badge-neutral">Inactivo</span>'}
                                    </td>
                                    <td class="text-right">
                                        <div class="d-flex gap-1" style="justify-content:flex-end;">
                                            <button class="btn btn-ghost btn-icon"
                                                    onclick="Clientes.verDetalle(${c.id})"
                                                    title="Ver detalle">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            ${puedeEditar ? `
                                                <button class="btn btn-ghost btn-icon"
                                                        onclick="Clientes.editar(${c.id})"
                                                        title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-ghost btn-icon"
                                                        onclick="Clientes.cambiarEstado(${c.id})"
                                                        title="${activo ? 'Desactivar' : 'Activar'}">
                                                    <i class="bi bi-${activo ? 'toggle-on' : 'toggle-off'}"></i>
                                                </button>
                                                <button class="btn btn-ghost btn-icon"
                                                        onclick="Clientes.eliminar(${c.id})"
                                                        title="Eliminar"
                                                        style="color:var(--danger);">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            ` : ''}
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

    // ═══════════════════════════════════════════════════════════
    // CREAR / EDITAR
    // ═══════════════════════════════════════════════════════════
    function abrirNuevo() {
        modoEdicion = false;
        document.getElementById('modal-titulo').textContent = 'Nuevo cliente';
        document.getElementById('cli-id').value = '';
        document.getElementById('cli-nombre').value = '';
        document.getElementById('cli-nit').value = '';
        document.getElementById('cli-tipo').value = 'juridica';
        document.getElementById('cli-direccion').value = '';
        document.getElementById('cli-telefono').value = '';
        document.getElementById('cli-email').value = '';
        document.getElementById('cli-contacto').value = '';
        document.getElementById('cli-notas').value = '';
        document.getElementById('cli-activo').checked = true;

        document.getElementById('modal-cliente').classList.add('active');
        setTimeout(() => document.getElementById('cli-nombre')?.focus(), 100);
    }

    function editar(id) {
        const c = lista.find(x => x.id == id);
        if (!c) return;

        modoEdicion = true;
        document.getElementById('modal-titulo').textContent = 'Editar cliente';
        document.getElementById('cli-id').value = c.id;
        document.getElementById('cli-nombre').value = c.nombre;
        document.getElementById('cli-nit').value = c.nit || '';
        document.getElementById('cli-tipo').value = c.tipo_persona || 'juridica';
        document.getElementById('cli-direccion').value = c.direccion || '';
        document.getElementById('cli-telefono').value = c.telefono || '';
        document.getElementById('cli-email').value = c.email || '';
        document.getElementById('cli-contacto').value = c.contacto || '';
        document.getElementById('cli-notas').value = c.notas || '';
        document.getElementById('cli-activo').checked = c.activo == 1;

        document.getElementById('modal-cliente').classList.add('active');
    }

    function cerrarModal() {
        document.getElementById('modal-cliente')?.classList.remove('active');
    }

    async function guardar() {
        const id = document.getElementById('cli-id').value;
        const nombre = document.getElementById('cli-nombre').value.trim();
        const nit = document.getElementById('cli-nit').value.trim();
        const tipoPersona = document.getElementById('cli-tipo').value;
        const direccion = document.getElementById('cli-direccion').value.trim();
        const telefono = document.getElementById('cli-telefono').value.trim();
        const email = document.getElementById('cli-email').value.trim();
        const contacto = document.getElementById('cli-contacto').value.trim();
        const notas = document.getElementById('cli-notas').value.trim();
        const activo = document.getElementById('cli-activo').checked ? 1 : 0;

        if (nombre.length < 2) {
            Toast.warning('Falta nombre', 'El nombre es obligatorio (mínimo 2 caracteres)');
            document.getElementById('cli-nombre').focus();
            return;
        }

        const payload = {
            nombre, nit, tipo_persona: tipoPersona,
            direccion, telefono, email, contacto, notas, activo,
        };

        const btn = document.getElementById('btn-guardar-cli');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Guardando...';

        let res;
        if (modoEdicion) {
            payload.id = parseInt(id);
            res = await Api.post('api/clientes.php?accion=actualizar', payload);
        } else {
            res = await Api.post('api/clientes.php?accion=crear', payload);
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';

        if (!res.success) {
            if (res.errors) Toast.error('Validación', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Listo', res.message);
        cerrarModal();
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // VER DETALLE
    // ═══════════════════════════════════════════════════════════
    async function verDetalle(id) {
        document.getElementById('modal-detalle-cliente').classList.add('active');
        const cont = document.getElementById('detalle-contenido');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/clientes.php?accion=obtener&id=${id}`);
        if (!res.success) {
            cont.innerHTML = `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        const c = res.data;

        let facturasHtml = '';
        if (c.facturas.length === 0) {
            facturasHtml = `
                <div class="empty-state" style="padding:30px;">
                    <i class="bi bi-inbox" style="font-size:32px;"></i>
                    <p class="text-muted mt-2">Sin facturas registradas</p>
                </div>
            `;
        } else {
            const badges = {
                emitida: { color: 'warning', label: 'Emitida' },
                parcial: { color: 'info', label: 'Parcial' },
                pagada:  { color: 'success', label: 'Pagada' },
                anulada: { color: 'danger', label: 'Anulada' },
            };

            facturasHtml = `
                <div class="tabla-wrap">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Emisión</th>
                                <th>Vencimiento</th>
                                <th class="text-right">Total</th>
                                <th class="text-right">Pagado</th>
                                <th class="text-right">Saldo</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${c.facturas.map(f => {
                                const badge = badges[f.estado] || { color: 'neutral', label: f.estado };
                                const saldo = (parseFloat(f.total) - parseFloat(f.total_pagado)).toFixed(2);
                                return `
                                    <tr>
                                        <td><strong>${App.escapeHtml(f.folio)}</strong></td>
                                        <td class="text-muted text-sm">${App.formatDate(f.fecha_emision).split(' ')[0]}</td>
                                        <td class="text-muted text-sm">${f.fecha_vencimiento ? App.formatDate(f.fecha_vencimiento).split(' ')[0] : '—'}</td>
                                        <td class="text-right">${App.formatMoney(f.total)}</td>
                                        <td class="text-right text-success">${App.formatMoney(f.total_pagado)}</td>
                                        <td class="text-right" style="${parseFloat(saldo) > 0 ? 'color:var(--danger);font-weight:600;' : ''}">${App.formatMoney(saldo)}</td>
                                        <td><span class="badge badge-${badge.color}">${badge.label}</span></td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        cont.innerHTML = `
            <div class="grid-2 mb-4">
                <div>
                    <div class="text-xs text-muted">Nombre</div>
                    <div style="font-weight:700;font-size:16px;">${App.escapeHtml(c.nombre)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">NIT</div>
                    <div style="font-weight:600;">${App.escapeHtml(c.nit || '—')}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Tipo</div>
                    <div style="font-weight:600;">${c.tipo_persona === 'fisica' ? 'Física' : 'Jurídica'}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Estado</div>
                    <div>${c.activo == 1 ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-neutral">Inactivo</span>'}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Teléfono</div>
                    <div style="font-weight:600;">${App.escapeHtml(c.telefono || '—')}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Email</div>
                    <div style="font-weight:600;">${App.escapeHtml(c.email || '—')}</div>
                </div>
                <div class="span-full">
                    <div class="text-xs text-muted">Dirección</div>
                    <div>${App.escapeHtml(c.direccion || '—')}</div>
                </div>
                <div class="span-full">
                    <div class="text-xs text-muted">Contacto</div>
                    <div>${App.escapeHtml(c.contacto || '—')}</div>
                </div>
                ${c.notas ? `<div class="span-full">
                    <div class="text-xs text-muted">Notas</div>
                    <div>${App.escapeHtml(c.notas)}</div>
                </div>` : ''}
            </div>

            <h4 style="font-size:15px;margin-bottom:10px;">
                <i class="bi bi-receipt text-primary"></i>
                Facturas (${c.facturas.length})
            </h4>
            ${facturasHtml}
        `;
    }

    function cerrarDetalle() {
        document.getElementById('modal-detalle-cliente')?.classList.remove('active');
    }

    // ═══════════════════════════════════════════════════════════
    // CAMBIAR ESTADO
    // ═══════════════════════════════════════════════════════════
    async function cambiarEstado(id) {
        const c = lista.find(x => x.id == id);
        if (!c) return;

        const accion = c.activo ? 'desactivar' : 'activar';
        const ok = await App.confirmar(`¿${accion.charAt(0).toUpperCase() + accion.slice(1)} "${c.nombre}"?`);
        if (!ok) return;

        const res = await Api.post('api/clientes.php?accion=cambiar_estado', { id });
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }
        Toast.success('Listo', res.message);
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // ELIMINAR
    // ═══════════════════════════════════════════════════════════
    async function eliminar(id) {
        const c = lista.find(x => x.id == id);
        if (!c) return;

        const ok = await App.confirmar(
            `¿Eliminar "${c.nombre}"? Esta acción no se puede deshacer.`,
            '⚠️ Eliminar cliente'
        );
        if (!ok) return;

        const res = await Api.post('api/clientes.php?accion=eliminar', { id });
        if (!res.success) {
            Toast.error('No se puede eliminar', res.message);
            return;
        }
        Toast.success('Eliminado', res.message);
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // FILTROS
    // ═══════════════════════════════════════════════════════════
    function limpiar() {
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-estado').value = '';
        cargar();
    }

    // ═══════════════════════════════════════════════════════════
    // EVENTOS
    // ═══════════════════════════════════════════════════════════
    document.addEventListener('DOMContentLoaded', () => {
        puedeEditar = !!document.getElementById('modal-cliente');
        cargar();

        const debounced = App.debounce(cargar, 400);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-estado')?.addEventListener('change', cargar);
    });

    return {
        cargar,
        limpiar,
        abrirNuevo,
        editar,
        cerrarModal,
        guardar,
        verDetalle,
        cerrarDetalle,
        cambiarEstado,
        eliminar,
    };
})();

window.Clientes = Clientes;