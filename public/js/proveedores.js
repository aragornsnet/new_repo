/**
 * IPV - Gestión de Proveedores
 */

const Proveedores = (() => {
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

        const cont = document.getElementById('tabla-proveedores');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get('api/proveedores.php?accion=listar&' + params);
        if (!res.success) {
            Toast.error('Error', res.message);
            cont.innerHTML = `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        lista = res.data;
        document.getElementById('total-prov').textContent = lista.length;
        render();
    }

    // ═══════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════
    function render() {
        const cont = document.getElementById('tabla-proveedores');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-building"></i>
                <h3>Sin proveedores</h3>
                <p>No hay proveedores registrados con los filtros actuales.</p>
                ${puedeEditar ? `<button class="btn btn-primary btn-sm mt-3" onclick="Proveedores.abrirNuevo()">
                    <i class="bi bi-plus-lg"></i> Crear primer proveedor
                </button>` : ''}
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Proveedor</th>
                            <th>NIT</th>
                            <th>Contacto</th>
                            <th class="text-right">Contratos</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(p => {
                            const activo = p.activo == 1;
                            const contratosActivos = parseInt(p.contratos_activos) || 0;
                            const contratosTotal = parseInt(p.contratos_total) || 0;

                            return `
                                <tr style="${!activo ? 'opacity:.6;' : ''}">
                                    <td>
                                        <div style="font-weight:600;">${App.escapeHtml(p.nombre)}</div>
                                        <div class="text-xs text-muted">
                                            ${p.tipo_persona === 'fisica' ? 'Persona física' : 'Persona jurídica'}
                                            ${p.email ? ' · ' + App.escapeHtml(p.email) : ''}
                                        </div>
                                    </td>
                                    <td class="text-muted text-sm">${App.escapeHtml(p.nit || '—')}</td>
                                    <td class="text-muted text-sm">
                                        ${p.contacto ? App.escapeHtml(p.contacto) : '—'}
                                        ${p.telefono ? `<div class="text-xs">${App.escapeHtml(p.telefono)}</div>` : ''}
                                    </td>
                                    <td class="text-right">
                                        ${contratosActivos > 0
                                            ? `<span class="badge badge-success">${contratosActivos} activo${contratosActivos !== 1 ? 's' : ''}</span>`
                                            : ''}
                                        ${contratosTotal > contratosActivos
                                            ? `<div class="text-xs text-muted">${contratosTotal} total</div>`
                                            : ''}
                                        ${contratosTotal === 0
                                            ? '<span class="text-muted text-xs">Sin contratos</span>'
                                            : ''}
                                    </td>
                                    <td>
                                        ${activo
                                            ? '<span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> Activo</span>'
                                            : '<span class="badge badge-neutral">Inactivo</span>'}
                                    </td>
                                    <td class="text-right">
                                        <div class="d-flex gap-1" style="justify-content:flex-end;">
                                            <button class="btn btn-ghost btn-icon"
                                                    onclick="Proveedores.verDetalle(${p.id})"
                                                    title="Ver detalle">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            ${puedeEditar ? `
                                                <button class="btn btn-ghost btn-icon"
                                                        onclick="Proveedores.editar(${p.id})"
                                                        title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-ghost btn-icon"
                                                        onclick="Proveedores.cambiarEstado(${p.id})"
                                                        title="${activo ? 'Desactivar' : 'Activar'}">
                                                    <i class="bi bi-${activo ? 'toggle-on' : 'toggle-off'}"></i>
                                                </button>
                                                <button class="btn btn-ghost btn-icon"
                                                        onclick="Proveedores.eliminar(${p.id})"
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
        document.getElementById('modal-titulo').textContent = 'Nuevo proveedor';
        document.getElementById('prov-id').value = '';
        document.getElementById('prov-nombre').value = '';
        document.getElementById('prov-nit').value = '';
        document.getElementById('prov-tipo').value = 'juridica';
        document.getElementById('prov-direccion').value = '';
        document.getElementById('prov-telefono').value = '';
        document.getElementById('prov-email').value = '';
        document.getElementById('prov-contacto').value = '';
        document.getElementById('prov-notas').value = '';
        document.getElementById('prov-activo').checked = true;

        document.getElementById('modal-proveedor').classList.add('active');
        setTimeout(() => document.getElementById('prov-nombre')?.focus(), 100);
    }

    function editar(id) {
        const p = lista.find(x => x.id == id);
        if (!p) return;

        modoEdicion = true;
        document.getElementById('modal-titulo').textContent = 'Editar proveedor';
        document.getElementById('prov-id').value = p.id;
        document.getElementById('prov-nombre').value = p.nombre;
        document.getElementById('prov-nit').value = p.nit || '';
        document.getElementById('prov-tipo').value = p.tipo_persona || 'juridica';
        document.getElementById('prov-direccion').value = p.direccion || '';
        document.getElementById('prov-telefono').value = p.telefono || '';
        document.getElementById('prov-email').value = p.email || '';
        document.getElementById('prov-contacto').value = p.contacto || '';
        document.getElementById('prov-notas').value = p.notas || '';
        document.getElementById('prov-activo').checked = p.activo == 1;

        document.getElementById('modal-proveedor').classList.add('active');
    }

    function cerrarModal() {
        document.getElementById('modal-proveedor')?.classList.remove('active');
    }

    async function guardar() {
        const id = document.getElementById('prov-id').value;
        const nombre = document.getElementById('prov-nombre').value.trim();
        const nit = document.getElementById('prov-nit').value.trim();
        const tipoPersona = document.getElementById('prov-tipo').value;
        const direccion = document.getElementById('prov-direccion').value.trim();
        const telefono = document.getElementById('prov-telefono').value.trim();
        const email = document.getElementById('prov-email').value.trim();
        const contacto = document.getElementById('prov-contacto').value.trim();
        const notas = document.getElementById('prov-notas').value.trim();
        const activo = document.getElementById('prov-activo').checked ? 1 : 0;

        if (nombre.length < 2) {
            Toast.warning('Falta nombre', 'El nombre es obligatorio (mínimo 2 caracteres)');
            document.getElementById('prov-nombre').focus();
            return;
        }

        const payload = {
            nombre, nit, tipo_persona: tipoPersona,
            direccion, telefono, email, contacto, notas, activo,
        };

        const btn = document.getElementById('btn-guardar-prov');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Guardando...';

        let res;
        if (modoEdicion) {
            payload.id = parseInt(id);
            res = await Api.post('api/proveedores.php?accion=actualizar', payload);
        } else {
            res = await Api.post('api/proveedores.php?accion=crear', payload);
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
        document.getElementById('modal-detalle').classList.add('active');
        const cont = document.getElementById('detalle-contenido');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/proveedores.php?accion=obtener&id=${id}`);
        if (!res.success) {
            cont.innerHTML = `<div class="alert alert-danger">${Toast.h(res.message)}</div>`;
            return;
        }

        const p = res.data;

        cont.innerHTML = `
            <div class="grid-2 mb-4">
                <div>
                    <div class="text-xs text-muted">Nombre</div>
                    <div style="font-weight:700;font-size:16px;">${App.escapeHtml(p.nombre)}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">NIT</div>
                    <div style="font-weight:600;">${App.escapeHtml(p.nit || '—')}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Tipo de persona</div>
                    <div style="font-weight:600;">${p.tipo_persona === 'fisica' ? 'Física' : 'Jurídica'}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Estado</div>
                    <div>${p.activo == 1
                        ? '<span class="badge badge-success">Activo</span>'
                        : '<span class="badge badge-neutral">Inactivo</span>'}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Teléfono</div>
                    <div style="font-weight:600;">${App.escapeHtml(p.telefono || '—')}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Email</div>
                    <div style="font-weight:600;">${App.escapeHtml(p.email || '—')}</div>
                </div>
                <div class="span-full">
                    <div class="text-xs text-muted">Dirección</div>
                    <div>${App.escapeHtml(p.direccion || '—')}</div>
                </div>
                <div class="span-full">
                    <div class="text-xs text-muted">Contacto</div>
                    <div>${App.escapeHtml(p.contacto || '—')}</div>
                </div>
                ${p.notas ? `<div class="span-full">
                    <div class="text-xs text-muted">Notas</div>
                    <div>${App.escapeHtml(p.notas)}</div>
                </div>` : ''}
            </div>

            <h4 style="font-size:15px;margin-bottom:10px;">
                <i class="bi bi-file-earmark-text text-primary"></i>
                Contratos (${p.contratos.length})
            </h4>

            ${p.contratos.length === 0 ? `
                <div class="empty-state" style="padding:30px;">
                    <i class="bi bi-inbox" style="font-size:32px;"></i>
                    <p class="text-muted mt-2">Sin contratos registrados</p>
                </div>
            ` : `
                <div class="tabla-wrap">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th>Nº contrato</th>
                                <th>Inicio</th>
                                <th>Caducidad</th>
                                <th class="text-right">Monto</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${p.contratos.map(c => {
                                const badge = badgeEstado(c.estado_display);
                                return `
                                    <tr>
                                        <td><strong>${App.escapeHtml(c.num_contrato)}</strong></td>
                                        <td class="text-muted text-sm">${App.formatDate(c.fecha_inicio).split(' ')[0]}</td>
                                        <td class="text-muted text-sm">${App.formatDate(c.fecha_caducidad).split(' ')[0]}</td>
                                        <td class="text-right">${c.monto ? App.formatMoney(c.monto) : '—'}</td>
                                        <td><span class="badge badge-${badge.color}">${badge.label}</span></td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            `}
        `;
    }

    function cerrarDetalle() {
        document.getElementById('modal-detalle')?.classList.remove('active');
    }

    function badgeEstado(estado) {
        const map = {
            activo:      { color: 'success', label: 'Activo' },
            por_vencer:  { color: 'warning', label: 'Por vencer' },
            por_renovar: { color: 'danger',  label: 'Por renovar' },
            renovado:    { color: 'info',    label: 'Renovado' },
            cancelado:   { color: 'neutral', label: 'Cancelado' },
            no_renovado: { color: 'neutral', label: 'No renovado' },
        };
        return map[estado] || { color: 'neutral', label: estado };
    }

    // ═══════════════════════════════════════════════════════════
    // CAMBIAR ESTADO
    // ═══════════════════════════════════════════════════════════
    async function cambiarEstado(id) {
        const p = lista.find(x => x.id == id);
        if (!p) return;

        const accion = p.activo ? 'desactivar' : 'activar';
        const ok = await App.confirmar(`¿${accion.charAt(0).toUpperCase() + accion.slice(1)} "${p.nombre}"?`);
        if (!ok) return;

        const res = await Api.post('api/proveedores.php?accion=cambiar_estado', { id });
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
        const p = lista.find(x => x.id == id);
        if (!p) return;

        const ok = await App.confirmar(
            `¿Eliminar "${p.nombre}"? Esta acción no se puede deshacer.`,
            '⚠️ Eliminar proveedor'
        );
        if (!ok) return;

        const res = await Api.post('api/proveedores.php?accion=eliminar', { id });
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
        puedeEditar = !!document.getElementById('modal-proveedor');
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

window.Proveedores = Proveedores;