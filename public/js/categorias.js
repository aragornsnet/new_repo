/**
 * IPV - Gestión de Categorías
 */

const Categorias = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let lista = [];
    let modoEdicion = false;

    async function cargar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const estado = document.getElementById('filtro-estado')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (estado) params.append('estado', estado);

        const cont = document.getElementById('tabla-cat');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/categorias.php?accion=listar&${params}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        lista = res.data;
        document.getElementById('total-cat').textContent = lista.length;
        render();
    }

    function render() {
        const cont = document.getElementById('tabla-cat');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-tag"></i>
                <h3>Sin categorías</h3>
                <p>Crea la primera con el botón "Nueva categoría".</p>
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Productos</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lista.map(c => `
                            <tr>
                                <td class="text-muted">${c.id}</td>
                                <td><strong>${App.escapeHtml(c.nombre)}</strong></td>
                                <td class="text-muted">${App.escapeHtml(c.descripcion || '—')}</td>
                                <td><span class="badge badge-info">${c.num_productos} producto${c.num_productos != 1 ? 's' : ''}</span></td>
                                <td>${c.activo == 1 
                                    ? '<span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> Activa</span>' 
                                    : '<span class="badge badge-neutral">Inactiva</span>'}</td>
                                <td class="text-right">
                                    <div class="d-flex gap-1" style="justify-content:flex-end;">
                                        <button class="btn btn-ghost btn-icon" onclick="Categorias.editar(${c.id})" title="Editar"><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-ghost btn-icon" onclick="Categorias.cambiarEstado(${c.id})" title="${c.activo ? 'Desactivar' : 'Activar'}"><i class="bi bi-${c.activo ? 'toggle-on' : 'toggle-off'}"></i></button>
                                        <button class="btn btn-ghost btn-icon" onclick="Categorias.eliminar(${c.id})" title="Eliminar" style="color:var(--danger);"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function abrirNuevo() {
        modoEdicion = false;
        document.getElementById('modal-titulo').textContent = 'Nueva categoría';
        document.getElementById('cat-id').value = '';
        document.getElementById('cat-nombre').value = '';
        document.getElementById('cat-descripcion').value = '';
        document.getElementById('cat-activo').checked = true;
        document.getElementById('modal-cat').classList.add('active');
    }

    function editar(id) {
        const c = lista.find(x => x.id == id);
        if (!c) return;

        modoEdicion = true;
        document.getElementById('modal-titulo').textContent = 'Editar categoría';
        document.getElementById('cat-id').value = c.id;
        document.getElementById('cat-nombre').value = c.nombre;
        document.getElementById('cat-descripcion').value = c.descripcion || '';
        document.getElementById('cat-activo').checked = c.activo == 1;
        document.getElementById('modal-cat').classList.add('active');
    }

    function cerrarModal() {
        document.getElementById('modal-cat').classList.remove('active');
    }

    async function guardar() {
        const id = document.getElementById('cat-id').value;
        const nombre = document.getElementById('cat-nombre').value.trim();
        const descripcion = document.getElementById('cat-descripcion').value.trim();
        const activo = document.getElementById('cat-activo').checked ? 1 : 0;

        if (!nombre) {
            Toast.warning('Campo requerido', 'El nombre es obligatorio');
            return;
        }

        const payload = { nombre, descripcion, activo };
        const btn = document.getElementById('btn-guardar-cat');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div>';

        let res;
        if (modoEdicion) {
            payload.id = parseInt(id);
            res = await Api.post('api/categorias.php?accion=actualizar', payload);
        } else {
            res = await Api.post('api/categorias.php?accion=crear', payload);
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

    async function cambiarEstado(id) {
        const c = lista.find(x => x.id == id);
        if (!c) return;

        const accion = c.activo ? 'desactivar' : 'activar';
        const ok = await App.confirmar(`¿${accion.charAt(0).toUpperCase() + accion.slice(1)} "${c.nombre}"?`);
        if (!ok) return;

        const res = await Api.post('api/categorias.php?accion=cambiar_estado', { id });
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }
        Toast.success('Listo', res.message);
        cargar();
    }

    async function eliminar(id) {
        const c = lista.find(x => x.id == id);
        if (!c) return;

        const ok = await App.confirmar(`¿Eliminar "${c.nombre}"? Esta acción no se puede deshacer.`, '⚠️ Eliminar categoría');
        if (!ok) return;

        const res = await Api.post('api/categorias.php?accion=eliminar', { id });
        if (!res.success) {
            Toast.error('No se puede eliminar', res.message);
            return;
        }
        Toast.success('Eliminada', res.message);
        cargar();
    }

    document.addEventListener('DOMContentLoaded', () => {
        cargar();

        const debounced = App.debounce(cargar, 400);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-estado')?.addEventListener('change', cargar);
    });

    return { cargar, abrirNuevo, editar, cerrarModal, guardar, cambiarEstado, eliminar };
})();

window.Categorias = Categorias;