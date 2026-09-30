/**
 * IPV - Gestión de Puntos de Venta
 */

const PuntosVenta = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let lista = [];
    let modoEdicion = false;

    async function cargar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const estado = document.getElementById('filtro-estado')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (estado) params.append('estado', estado);

        const cont = document.getElementById('lista-pv');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/puntos_venta.php?accion=listar&${params}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        lista = res.data;
        document.getElementById('total-pv').textContent = lista.length;
        render();
    }

    function render() {
        const cont = document.getElementById('lista-pv');

        if (!lista.length) {
            cont.innerHTML = `<div class="card">
                <div class="empty-state">
                    <i class="bi bi-shop"></i>
                    <h3>Sin puntos de venta</h3>
                    <p>Crea el primero con el botón "Nuevo punto de venta".</p>
                </div>
            </div>`;
            return;
        }

        cont.innerHTML = `<div class="grid-3">${lista.map(pv => {
            const activo = pv.activo == 1;
            return `
                <div class="card" style="padding:0;overflow:hidden;${activo ? '' : 'opacity:.65;'}">
                    <div style="padding:16px 18px;background:${activo ? 'linear-gradient(135deg,var(--primary) 0%,var(--primary-dark) 100%)' : 'var(--surface-2)'};color:${activo ? '#fff' : 'var(--text-muted)'};">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                            <div style="font-weight:700;font-size:17px;">${App.escapeHtml(pv.nombre)}</div>
                            <span class="badge" style="background:rgba(255,255,255,.2);color:${activo ? '#fff' : 'var(--text-muted)'};">
                                ${activo ? 'Activo' : 'Inactivo'}
                            </span>
                        </div>
                        <div style="font-size:12px;opacity:.9;margin-top:4px;display:flex;align-items:center;gap:6px;">
                            <i class="bi bi-geo-alt-fill"></i>
                            ${App.escapeHtml(pv.direccion || 'Sin dirección')}
                        </div>
                    </div>

                    <div style="padding:16px 18px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <div class="text-xs text-muted">Vendedores</div>
                            <div style="font-size:20px;font-weight:700;">${pv.num_vendedores}</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted">Productos con stock</div>
                            <div style="font-size:20px;font-weight:700;">${pv.productos_con_stock}</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted">Ventas hoy</div>
                            <div style="font-size:16px;font-weight:600;">${App.formatMoney(pv.ventas_hoy)}</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted">Ventas mes</div>
                            <div style="font-size:16px;font-weight:600;color:var(--primary);">${App.formatMoney(pv.ventas_mes)}</div>
                        </div>
                    </div>

                    ${pv.telefono ? `
                        <div style="padding:0 18px 12px;">
                            <div class="text-xs text-muted"><i class="bi bi-telephone-fill"></i> ${App.escapeHtml(pv.telefono)}</div>
                        </div>
                    ` : ''}

                    <div style="padding:12px 18px;border-top:1px solid var(--border);display:flex;gap:6px;justify-content:flex-end;">
                        <button class="btn btn-ghost btn-sm" onclick="PuntosVenta.verDetalle(${pv.id})" title="Ver detalle"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-ghost btn-sm" onclick="PuntosVenta.editar(${pv.id})" title="Editar"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-ghost btn-sm" onclick="PuntosVenta.cambiarEstado(${pv.id})" title="${activo ? 'Desactivar' : 'Activar'}"><i class="bi bi-${activo ? 'toggle-on' : 'toggle-off'}"></i></button>
                        <button class="btn btn-ghost btn-sm" onclick="PuntosVenta.eliminar(${pv.id})" title="Eliminar" style="color:var(--danger);"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            `;
        }).join('')}</div>`;
    }

    function abrirNuevo() {
        modoEdicion = false;
        document.getElementById('modal-titulo').textContent = 'Nuevo punto de venta';
        document.getElementById('pv-id').value = '';
        document.getElementById('pv-nombre').value = '';
        document.getElementById('pv-direccion').value = '';
        document.getElementById('pv-telefono').value = '';
        document.getElementById('pv-activo').checked = true;
        document.getElementById('modal-pv').classList.add('active');
    }

    function editar(id) {
        const pv = lista.find(x => x.id == id);
        if (!pv) return;

        modoEdicion = true;
        document.getElementById('modal-titulo').textContent = 'Editar punto de venta';
        document.getElementById('pv-id').value = pv.id;
        document.getElementById('pv-nombre').value = pv.nombre;
        document.getElementById('pv-direccion').value = pv.direccion || '';
        document.getElementById('pv-telefono').value = pv.telefono || '';
        document.getElementById('pv-activo').checked = pv.activo == 1;
        document.getElementById('modal-pv').classList.add('active');
    }

    function cerrarModal() {
        document.getElementById('modal-pv').classList.remove('active');
    }

    async function guardar() {
        const id = document.getElementById('pv-id').value;
        const nombre = document.getElementById('pv-nombre').value.trim();
        const direccion = document.getElementById('pv-direccion').value.trim();
        const telefono = document.getElementById('pv-telefono').value.trim();
        const activo = document.getElementById('pv-activo').checked ? 1 : 0;

        if (!nombre) {
            Toast.warning('Campo requerido', 'El nombre es obligatorio');
            return;
        }

        const payload = { nombre, direccion, telefono, activo };
        const btn = document.getElementById('btn-guardar-pv');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div>';

        let res;
        if (modoEdicion) {
            payload.id = parseInt(id);
            res = await Api.post('api/puntos_venta.php?accion=actualizar', payload);
        } else {
            res = await Api.post('api/puntos_venta.php?accion=crear', payload);
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

    async function verDetalle(id) {
        document.getElementById('modal-detalle').classList.add('active');
        const cont = document.getElementById('detalle-contenido');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/puntos_venta.php?accion=obtener&id=${id}`);
        if (!res.success) {
            cont.innerHTML = `<div class="alert alert-danger">${res.message}</div>`;
            return;
        }

        const pv = res.data;
        cont.innerHTML = `
            <h3>${App.escapeHtml(pv.nombre)}</h3>
            <p class="text-muted mb-4">
                ${pv.direccion ? `<i class="bi bi-geo-alt-fill"></i> ${App.escapeHtml(pv.direccion)}` : 'Sin dirección'}
                ${pv.telefono ? ` · <i class="bi bi-telephone-fill"></i> ${App.escapeHtml(pv.telefono)}` : ''}
            </p>

            <h4 style="font-size:15px;margin-top:20px;margin-bottom:10px;"><i class="bi bi-people-fill text-primary"></i> Vendedores (${pv.vendedores.length})</h4>
            ${pv.vendedores.length === 0 ? `
                <p class="text-muted text-sm">Sin vendedores asignados.</p>
            ` : `
                <div class="tabla-wrap mb-4">
                    <table class="tabla">
                        <thead><tr><th>Nombre</th><th>Email</th><th>Estado</th></tr></thead>
                        <tbody>
                            ${pv.vendedores.map(v => `
                                <tr>
                                    <td><strong>${App.escapeHtml(v.nombre)}</strong></td>
                                    <td class="text-muted">${App.escapeHtml(v.email)}</td>
                                    <td>${v.activo == 1 ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-neutral">Inactivo</span>'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `}

            <h4 style="font-size:15px;margin-top:20px;margin-bottom:10px;"><i class="bi bi-clock-history text-warning"></i> Turnos abiertos ahora (${pv.turnos_abiertos.length})</h4>
            ${pv.turnos_abiertos.length === 0 ? `
                <p class="text-muted text-sm">Sin turnos abiertos en este momento.</p>
            ` : `
                <div class="tabla-wrap">
                    <table class="tabla">
                        <thead><tr><th>Vendedor</th><th>Apertura</th><th>Monto inicial</th></tr></thead>
                        <tbody>
                            ${pv.turnos_abiertos.map(t => `
                                <tr>
                                    <td><strong>${App.escapeHtml(t.vendedor)}</strong></td>
                                    <td class="text-muted">${App.formatDate(t.fecha_apertura)}</td>
                                    <td>${App.formatMoney(t.monto_inicial)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `}
        `;
    }

    function cerrarDetalle() {
        document.getElementById('modal-detalle').classList.remove('active');
    }

    async function cambiarEstado(id) {
        const pv = lista.find(x => x.id == id);
        if (!pv) return;

        const accion = pv.activo ? 'desactivar' : 'activar';
        const ok = await App.confirmar(`¿Seguro que quieres ${accion} "${pv.nombre}"?`);
        if (!ok) return;

        const res = await Api.post('api/puntos_venta.php?accion=cambiar_estado', { id });
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }
        Toast.success('Listo', res.message);
        cargar();
    }

    async function eliminar(id) {
        const pv = lista.find(x => x.id == id);
        if (!pv) return;

        const ok = await App.confirmar(`¿Eliminar "${pv.nombre}"? Esta acción no se puede deshacer.`, '⚠️ Eliminar punto de venta');
        if (!ok) return;

        const res = await Api.post('api/puntos_venta.php?accion=eliminar', { id });
        if (!res.success) {
            Toast.error('No se puede eliminar', res.message);
            return;
        }
        Toast.success('Eliminado', res.message);
        cargar();
    }

    document.addEventListener('DOMContentLoaded', () => {
        cargar();

        const debounced = App.debounce(cargar, 400);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-estado')?.addEventListener('change', cargar);
    });

    return { cargar, abrirNuevo, editar, cerrarModal, guardar, verDetalle, cerrarDetalle, cambiarEstado, eliminar };
})();

window.PuntosVenta = PuntosVenta;