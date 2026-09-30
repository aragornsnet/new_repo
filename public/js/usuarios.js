/**
 * IPV - Gestión de usuarios
 */

const Usuarios = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let lista = [];
    let modoEdicion = false;

    async function cargar() {
        const q = document.getElementById('filtro-q')?.value.trim() || '';
        const rol = document.getElementById('filtro-rol')?.value || '';
        const pv = document.getElementById('filtro-pv')?.value || '';
        const estado = document.getElementById('filtro-estado')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (rol) params.append('rol_id', rol);
        if (pv) params.append('pv_id', pv);
        if (estado) params.append('estado', estado);

        const cont = document.getElementById('tabla-usuarios');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get(`api/usuarios.php?accion=listar&${params}`);
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        lista = res.data;
        document.getElementById('total-usuarios').textContent = lista.length;
        render();
    }

    function render() {
        const cont = document.getElementById('tabla-usuarios');

        if (!lista.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-people"></i>
                <h3>Sin usuarios</h3>
                <p>No hay usuarios que coincidan con los filtros.</p>
            </div>`;
            return;
        }

        const filas = lista.map(u => {
            const avatar = `<div class="avatar avatar-sm" style="background:${colorFromString(u.nombre)};">${inicial(u.nombre)}</div>`;
            const badgeRol = {
                1: '<span class="badge badge-danger">Administrador</span>',
                2: '<span class="badge badge-warning">Supervisor</span>',
                3: '<span class="badge badge-info">Vendedor</span>',
                4: '<span class="badge badge-primary">Almacenero</span>',
            }[u.rol_id] || '<span class="badge badge-neutral">Sin rol</span>';
            const badgeEstado = u.activo
                ? '<span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> Activo</span>'
                : '<span class="badge badge-neutral">Inactivo</span>';
            const pv = u.pv ? `<span class="text-muted">${App.escapeHtml(u.pv)}</span>` : '<span class="text-light">—</span>';
            const ultimoLogin = u.ultimo_login ? App.timeAgo(u.ultimo_login) : '<span class="text-light">Nunca</span>';

            return `
                <tr>
                    <td>
                        <div class="d-flex gap-2" style="align-items:center;">
                            ${avatar}
                            <div style="min-width:0;">
                                <div style="font-weight:600;">${App.escapeHtml(u.nombre)}</div>
                                <div class="text-xs text-muted">${App.escapeHtml(u.email)}</div>
                            </div>
                        </div>
                    </td>
                    <td>${badgeRol}</td>
                    <td>${pv}</td>
                    <td>${badgeEstado}</td>
                    <td class="text-muted text-xs">${ultimoLogin}</td>
                    <td class="text-right">
                        <div class="d-flex gap-1" style="justify-content:flex-end;">
                            <button class="btn btn-ghost btn-icon" onclick="Usuarios.editar(${u.id})" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-ghost btn-icon" onclick="Usuarios.abrirReset(${u.id})" title="Resetear contraseña">
                                <i class="bi bi-key"></i>
                            </button>
                            <button class="btn btn-ghost btn-icon" onclick="Usuarios.cambiarEstado(${u.id})" title="${u.activo ? 'Desactivar' : 'Activar'}">
                                <i class="bi bi-${u.activo ? 'toggle-on' : 'toggle-off'}"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Punto de venta</th>
                            <th>Estado</th>
                            <th>Último login</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>${filas}</tbody>
                </table>
            </div>
        `;
    }

    function inicial(nombre) {
        return (nombre || '?').trim().charAt(0).toUpperCase();
    }
    function colorFromString(str) {
        const colores = ['#2563eb', '#16a34a', '#dc2626', '#f59e0b', '#7c3aed', '#0891b2', '#db2777'];
        let hash = 0;
        for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
        return colores[Math.abs(hash) % colores.length];
    }

    function abrirFormNuevo() {
        modoEdicion = false;
        document.getElementById('modal-titulo').textContent = 'Nuevo usuario';
        document.getElementById('usuario-id').value = '';
        document.getElementById('u-nombre').value = '';
        document.getElementById('u-email').value = '';
        document.getElementById('u-rol').value = '';
        document.getElementById('u-pv').value = '';
        document.getElementById('u-activo').checked = true;
        document.getElementById('u-password').value = '';
        document.getElementById('u-password2').value = '';
        document.getElementById('seccion-password').style.display = '';
        document.getElementById('field-pv').style.display = 'none';

        validarPassword();
        document.getElementById('modal-usuario').classList.add('active');
    }

    async function editar(id) {
        const u = lista.find(x => x.id == id);
        if (!u) return;

        modoEdicion = true;
        document.getElementById('modal-titulo').textContent = 'Editar usuario';
        document.getElementById('usuario-id').value = u.id;
        document.getElementById('u-nombre').value = u.nombre;
        document.getElementById('u-email').value = u.email;
        document.getElementById('u-rol').value = u.rol_id;
        document.getElementById('u-pv').value = u.pv_id || '';
        document.getElementById('u-activo').checked = u.activo == 1;
        document.getElementById('seccion-password').style.display = 'none';
        document.getElementById('field-pv').style.display = u.rol_id == 3 ? '' : 'none';

        document.getElementById('modal-usuario').classList.add('active');
    }

    function cerrarModal() {
        document.getElementById('modal-usuario').classList.remove('active');
    }

    function validarPassword() {
        const pass = document.getElementById('u-password')?.value || '';
        const pass2 = document.getElementById('u-password2')?.value || '';

        const checks = {
            len:     pass.length >= 8,
            mayus:   /[A-Z]/.test(pass),
            minus:   /[a-z]/.test(pass),
            num:     /[0-9]/.test(pass),
            simbolo: /[!@#$%^&*()\-_=+\[\]{};:,.<>\/?~`'"\\|]/.test(pass),
        };

        let cumplidos = 0;
        for (const [k, ok] of Object.entries(checks)) {
            const li = document.querySelector(`#req-list [data-req="${k}"]`);
            if (!li) continue;
            const icon = li.querySelector('i');
            if (ok) { icon.className = 'bi bi-check-circle-fill'; li.classList.add('ok'); cumplidos++; }
            else    { icon.className = 'bi bi-circle'; li.classList.remove('ok'); }
        }

        const fill = document.getElementById('strength-fill');
        const text = document.getElementById('strength-text');
        if (fill && text) {
            if (pass.length === 0) { fill.style.width = '0%'; text.textContent = ''; }
            else {
                let nivel, label, color;
                if (cumplidos <= 2)        { nivel = 33;  label = 'Débil';  color = '#dc2626'; }
                else if (cumplidos < 5 || pass.length < 12) { nivel = 66; label = 'Media'; color = '#f59e0b'; }
                else                       { nivel = 100; label = 'Fuerte'; color = '#16a34a'; }
                fill.style.width = nivel + '%';
                fill.style.background = color;
                text.textContent = 'Fortaleza: ' + label;
                text.style.color = color;
            }
        }

        const msg = document.getElementById('match-msg');
        if (msg) {
            if (pass2.length === 0) { msg.textContent = ''; msg.className = 'match-msg'; }
            else if (pass === pass2) { msg.innerHTML = '<i class="bi bi-check-circle-fill"></i> Las contraseñas coinciden'; msg.className = 'match-msg ok'; }
            else { msg.innerHTML = '<i class="bi bi-x-circle-fill"></i> Las contraseñas no coinciden'; msg.className = 'match-msg error'; }
        }
    }

    async function guardar() {
        const id = document.getElementById('usuario-id').value;
        const nombre = document.getElementById('u-nombre').value.trim();
        const email = document.getElementById('u-email').value.trim();
        const rolId = parseInt(document.getElementById('u-rol').value);
        const pvId = document.getElementById('u-pv').value;
        const activo = document.getElementById('u-activo').checked ? 1 : 0;

        if (!nombre || !email || !rolId) {
            Toast.warning('Campos incompletos', 'Completa los campos obligatorios');
            return;
        }

        const payload = { nombre, email, rol_id: rolId, punto_venta_id: pvId || null, activo };

        const btn = document.getElementById('btn-guardar');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Guardando...';

        let res;
        if (modoEdicion) {
            payload.id = parseInt(id);
            res = await Api.post('api/usuarios.php?accion=actualizar', payload);
        } else {
            const pass = document.getElementById('u-password').value;
            const pass2 = document.getElementById('u-password2').value;
            if (!pass || pass !== pass2) {
                Toast.warning('Contraseña inválida', 'Verifica las contraseñas');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                return;
            }
            payload.password = pass;
            payload.password2 = pass2;
            res = await Api.post('api/usuarios.php?accion=crear', payload);
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';

        if (!res.success) {
            if (res.errors) Toast.error('Errores', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Listo', res.message);
        cerrarModal();
        cargar();
    }

    function abrirReset(id) {
        const u = lista.find(x => x.id == id);
        if (!u) return;

        document.getElementById('reset-id').value = u.id;
        document.getElementById('reset-nombre').textContent = u.nombre;
        document.getElementById('reset-password').value = '';
        document.getElementById('reset-password2').value = '';
        document.getElementById('reset-strength-fill').style.width = '0%';
        document.getElementById('reset-strength-text').textContent = '';
        document.getElementById('reset-match-msg').textContent = '';

        document.getElementById('modal-reset').classList.add('active');
    }

    function cerrarReset() {
        document.getElementById('modal-reset').classList.remove('active');
    }

    async function confirmarReset() {
        const id = parseInt(document.getElementById('reset-id').value);
        const pass = document.getElementById('reset-password').value;
        const pass2 = document.getElementById('reset-password2').value;

        if (!pass || pass !== pass2) {
            Toast.warning('Contraseñas no coinciden', 'Verifica los campos');
            return;
        }

        const btn = document.getElementById('btn-reset');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div>';

        const res = await Api.post('api/usuarios.php?accion=reset_password', { id, password: pass, password2: pass2 });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-key"></i> Resetear';

        if (!res.success) {
            if (res.errors) Toast.error('Error', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Contraseña actualizada', res.message);
        cerrarReset();
    }

    async function cambiarEstado(id) {
        const u = lista.find(x => x.id == id);
        if (!u) return;

        const accion = u.activo ? 'desactivar' : 'activar';
        const ok = await App.confirmar(`¿Seguro que quieres ${accion} a ${u.nombre}?`);
        if (!ok) return;

        const res = await Api.post('api/usuarios.php?accion=cambiar_estado', { id });
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }
        Toast.success('Listo', res.message);
        cargar();
    }

    function limpiarFiltros() {
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-rol').value = '';
        document.getElementById('filtro-pv').value = '';
        document.getElementById('filtro-estado').value = '';
        cargar();
    }

    document.addEventListener('DOMContentLoaded', () => {
        cargar();

        const debounced = App.debounce(cargar, 400);
        document.getElementById('filtro-q')?.addEventListener('input', debounced);
        document.getElementById('filtro-rol')?.addEventListener('change', cargar);
        document.getElementById('filtro-pv')?.addEventListener('change', cargar);
        document.getElementById('filtro-estado')?.addEventListener('change', cargar);

        document.getElementById('u-password')?.addEventListener('input', validarPassword);
        document.getElementById('u-password2')?.addEventListener('input', validarPassword);

        document.getElementById('u-rol')?.addEventListener('change', (e) => {
            const esVendedor = parseInt(e.target.value) === 3;
            document.getElementById('field-pv').style.display = esVendedor ? '' : 'none';
            if (!esVendedor) document.getElementById('u-pv').value = '';
        });

        const validarReset = () => {
            const pass = document.getElementById('reset-password').value;
            const pass2 = document.getElementById('reset-password2').value;
            const fill = document.getElementById('reset-strength-fill');
            const text = document.getElementById('reset-strength-text');
            const msg = document.getElementById('reset-match-msg');

            const checks = {
                len: pass.length >= 8,
                mayus: /[A-Z]/.test(pass),
                minus: /[a-z]/.test(pass),
                num: /[0-9]/.test(pass),
                simbolo: /[!@#$%^&*()\-_=+\[\]{};:,.<>\/?~`'"\\|]/.test(pass),
            };
            const cumplidos = Object.values(checks).filter(Boolean).length;

            if (pass.length === 0) { fill.style.width = '0%'; text.textContent = ''; }
            else {
                let nivel, label, color;
                if (cumplidos <= 2) { nivel = 33; label = 'Débil'; color = '#dc2626'; }
                else if (cumplidos < 5) { nivel = 66; label = 'Media'; color = '#f59e0b'; }
                else { nivel = 100; label = 'Fuerte'; color = '#16a34a'; }
                fill.style.width = nivel + '%'; fill.style.background = color;
                text.textContent = 'Fortaleza: ' + label; text.style.color = color;
            }

            if (pass2.length === 0) { msg.textContent = ''; msg.className = 'match-msg'; }
            else if (pass === pass2) { msg.innerHTML = '<i class="bi bi-check-circle-fill"></i> Las contraseñas coinciden'; msg.className = 'match-msg ok'; }
            else { msg.innerHTML = '<i class="bi bi-x-circle-fill"></i> Las contraseñas no coinciden'; msg.className = 'match-msg error'; }
        };

        document.getElementById('reset-password')?.addEventListener('input', validarReset);
        document.getElementById('reset-password2')?.addEventListener('input', validarReset);
    });

    return { cargar, abrirFormNuevo, editar, cerrarModal, guardar, abrirReset, cerrarReset, confirmarReset, cambiarEstado, limpiarFiltros };
})();

window.Usuarios = Usuarios;