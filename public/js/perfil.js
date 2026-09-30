/**
 * IPV - Editor de perfil
 */

const Perfil = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let perfilActual = null;

    // ============================================================
    // ABRIR modal de perfil
    // ============================================================
    async function abrir() {
        const modalId = 'modal-perfil';

        document.getElementById(modalId)?.remove();

        const modalHtml = `
            <div class="modal-backdrop active" id="${modalId}">
                <div class="modal modal-lg">
                    <div class="modal-header">
                        <div class="modal-title"><i class="bi bi-person-circle"></i> Mi perfil</div>
                        <button class="modal-close" onclick="Perfil.cerrar()"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="tabs" id="tabs-perfil">
                            <button class="tab active" data-tab="datos">
                                <i class="bi bi-person-fill"></i> Datos personales
                            </button>
                            <button class="tab" data-tab="password">
                                <i class="bi bi-key-fill"></i> Cambiar contraseña
                            </button>
                        </div>

                        <div id="perfil-contenido">
                            <div class="empty-state"><div class="spinner spinner-lg"></div></div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Bind tabs
        document.querySelectorAll('#tabs-perfil .tab').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#tabs-perfil .tab').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                renderTab(btn.dataset.tab);
            });
        });

        // Cargar datos
        await cargar();
    }

    // ============================================================
    // CARGAR datos
    // ============================================================
    async function cargar() {
        const res = await Api.get('api/perfil.php?accion=obtener');

        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        perfilActual = res.data;
        renderTab('datos');
    }

    // ============================================================
    // RENDER por tab
    // ============================================================
    function renderTab(tab) {
        const cont = document.getElementById('perfil-contenido');

        if (tab === 'datos') {
            cont.innerHTML = renderDatos();
            bindDatos();
        } else {
            cont.innerHTML = renderPassword();
            bindPassword();
        }
    }

    // ============================================================
    // TAB: DATOS PERSONALES
    // ============================================================
    function renderDatos() {
        const u = perfilActual;
        const avatarUrl = u.avatar_url;

        return `
            <div class="form-grid">
                <!-- Avatar -->
                <div class="field span-full" style="align-items:center;">
                    <label>Foto de perfil</label>
                    <div style="display:flex;align-items:center;gap:20px;margin-top:8px;">
                        <div id="avatar-preview" style="width:100px;height:100px;border-radius:50%;overflow:hidden;background:${colorAvatar(u.nombre)};display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:700;color:#fff;">
                            ${avatarUrl 
                                ? `<img src="${avatarUrl}" alt="Avatar" style="width:100%;height:100%;object-fit:cover;">`
                                : escapeHtml(inicial(u.nombre))
                            }
                        </div>
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('avatar-input').click()">
                                <i class="bi bi-camera"></i> ${avatarUrl ? 'Cambiar foto' : 'Subir foto'}
                            </button>
                            ${avatarUrl ? `
                                <button type="button" class="btn btn-danger btn-sm" onclick="Perfil.eliminarAvatar()">
                                    <i class="bi bi-trash"></i> Eliminar foto
                                </button>
                            ` : ''}
                        </div>
                    </div>
                    <input type="file" id="avatar-input" accept=".png,.jpg,.jpeg,.webp" style="display:none;">
                    <div class="help">PNG, JPG o WEBP · Máx 2 MB</div>
                </div>

                <!-- Nombre -->
                <div class="field span-full">
                    <label for="perfil-nombre">Nombre completo <span class="req">*</span></label>
                    <input type="text" id="perfil-nombre" maxlength="100" value="${escapeAttr(u.nombre)}" required>
                </div>

                <!-- Email (solo lectura) -->
                <div class="field span-full">
                    <label>Correo electrónico</label>
                    <input type="email" value="${escapeAttr(u.email)}" readonly style="background:var(--surface-2);">
                    <div class="help">Solo el administrador puede cambiar tu correo</div>
                </div>

                <!-- Rol y PV (solo lectura) -->
                <div class="field">
                    <label>Rol</label>
                    <input type="text" value="${escapeAttr(u.rol)}" readonly style="background:var(--surface-2);">
                </div>
                <div class="field">
                    <label>Punto de venta</label>
                    <input type="text" value="${escapeAttr(u.pv || '—')}" readonly style="background:var(--surface-2);">
                </div>

                <!-- Info adicional -->
                <div class="field span-full">
                    <div style="padding:12px;background:var(--surface-2);border-radius:var(--radius-md);display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <div class="text-xs text-muted">Último login</div>
                            <div style="font-weight:600;">${u.ultimo_login ? App.formatDate(u.ultimo_login) : '—'}</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted">Miembro desde</div>
                            <div style="font-weight:600;">${App.formatDate(u.created_at)}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
                <button class="btn btn-secondary" onclick="Perfil.cerrar()">Cancelar</button>
                <button class="btn btn-primary" id="btn-guardar-perfil" onclick="Perfil.guardar()">
                    <i class="bi bi-check-lg"></i> Guardar cambios
                </button>
            </div>
        `;
    }

    function bindDatos() {
        const input = document.getElementById('avatar-input');
        input?.addEventListener('change', (e) => {
            if (e.target.files && e.target.files[0]) {
                subirAvatar(e.target.files[0]);
            }
        });
    }

    // ============================================================
    // SUBIR AVATAR
    // ============================================================
    async function subirAvatar(archivo) {
        const formData = new FormData();
        formData.append('avatar', archivo);

        Toast.info('Subiendo...', archivo.name);

        const res = await Api.upload('api/perfil.php?accion=subir_avatar', formData);

        if (!res.success) {
            Toast.error('Error al subir', res.message);
            return;
        }

        Toast.success('Foto actualizada', 'Actualizando...');

        // Actualizar el avatar en el header y sidebar
        actualizarAvatarUI(res.data.url);

        // Recargar el perfil
        await cargar();
    }

    async function eliminarAvatar() {
        const ok = await App.confirmar('¿Eliminar tu foto de perfil?', 'Confirmar');
        if (!ok) return;

        const res = await Api.post('api/perfil.php?accion=eliminar_avatar');

        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Foto eliminada', '');

        actualizarAvatarUI(null);

        await cargar();
    }

    // ============================================================
    // ACTUALIZAR AVATAR EN UI
    // ============================================================
    function actualizarAvatarUI(url) {
        // Avatar del header
        const headerAvatar = document.querySelector('.user-menu-trigger .avatar');
        if (headerAvatar) {
            if (url) {
                headerAvatar.innerHTML = `<img src="${url}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
                headerAvatar.style.background = 'transparent';
            } else {
                const nombre = perfilActual.nombre;
                headerAvatar.innerHTML = escapeHtml(inicial(nombre));
                headerAvatar.style.background = colorAvatar(nombre);
            }
        }

        // Avatar del sidebar
        const sidebarAvatar = document.querySelector('.sidebar-user .avatar');
        if (sidebarAvatar) {
            if (url) {
                sidebarAvatar.innerHTML = `<img src="${url}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
                sidebarAvatar.style.background = 'transparent';
            } else {
                const nombre = perfilActual.nombre;
                sidebarAvatar.innerHTML = escapeHtml(inicial(nombre));
                sidebarAvatar.style.background = colorAvatar(nombre);
            }
        }
    }

    // ============================================================
    // GUARDAR NOMBRE
    // ============================================================
    async function guardar() {
        const nombre = document.getElementById('perfil-nombre').value.trim();

        if (!nombre || nombre.length < 3) {
            Toast.warning('Nombre inválido', 'Debe tener al menos 3 caracteres');
            return;
        }

        const btn = document.getElementById('btn-guardar-perfil');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Guardando...';

        const res = await Api.post('api/perfil.php?accion=actualizar', { nombre });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar cambios';

        if (!res.success) {
            if (res.errors) Toast.error('Error', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Perfil actualizado', 'El nombre se actualizó');

        actualizarNombreUI(nombre);
        perfilActual.nombre = nombre;
        actualizarAvatarUI(perfilActual.avatar_url);
    }

    function actualizarNombreUI(nombre) {
        const headerName = document.querySelector('.user-menu-trigger .user-name');
        if (headerName) headerName.textContent = nombre;

        const sidebarName = document.querySelector('.sidebar-user-name');
        if (sidebarName) sidebarName.textContent = nombre;

        const dropdownName = document.querySelector('.user-dropdown-header .name');
        if (dropdownName) dropdownName.textContent = nombre;
    }

    // ============================================================
    // TAB: CAMBIAR CONTRASEÑA
    // ============================================================
    function renderPassword() {
        return `
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div>Para cambiar tu contraseña, primero verifica tu contraseña actual.</div>
            </div>

            <div class="form-grid">
                <div class="field span-full">
                    <label for="pass-actual">Contraseña actual <span class="req">*</span></label>
                    <div class="input-group">
                        <input type="password" id="pass-actual" autocomplete="current-password" required>
                        <button type="button" class="btn-toggle-pass" onclick="Password.toggle('pass-actual', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="field span-full">
                    <label for="pass-nueva">Nueva contraseña <span class="req">*</span></label>
                    <div class="input-group">
                        <input type="password" id="pass-nueva" autocomplete="new-password" required>
                        <button type="button" class="btn-toggle-pass" onclick="Password.toggle('pass-nueva', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
                    <div class="strength-text" id="strength-text"></div>
                    <ul class="req-list" id="req-list-pass">
                        <li data-req="len"><i class="bi bi-circle"></i> Mínimo 8 caracteres</li>
                        <li data-req="mayus"><i class="bi bi-circle"></i> Al menos una mayúscula</li>
                        <li data-req="minus"><i class="bi bi-circle"></i> Al menos una minúscula</li>
                        <li data-req="num"><i class="bi bi-circle"></i> Al menos un número</li>
                        <li data-req="simbolo"><i class="bi bi-circle"></i> Al menos un símbolo</li>
                    </ul>
                </div>

                <div class="field span-full">
                    <label for="pass-confirmar">Confirmar nueva contraseña <span class="req">*</span></label>
                    <div class="input-group">
                        <input type="password" id="pass-confirmar" autocomplete="new-password" required>
                        <button type="button" class="btn-toggle-pass" onclick="Password.toggle('pass-confirmar', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="match-msg" id="match-msg"></div>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
                <button class="btn btn-secondary" onclick="Perfil.cerrar()">Cancelar</button>
                <button class="btn btn-danger" id="btn-cambiar-pass" onclick="Perfil.cambiarPassword()">
                    <i class="bi bi-key-fill"></i> Cambiar contraseña
                </button>
            </div>
        `;
    }

    function bindPassword() {
        const nueva = document.getElementById('pass-nueva');
        const confirmar = document.getElementById('pass-confirmar');

        const validar = () => {
            const pass = nueva.value;
            const conf = confirmar.value;

            const checks = {
                len: pass.length >= 8,
                mayus: /[A-Z]/.test(pass),
                minus: /[a-z]/.test(pass),
                num: /[0-9]/.test(pass),
                simbolo: /[!@#$%^&*()\-_=+\[\]{};:,.<>\/?~`'"\\|]/.test(pass),
            };

            let cumplidos = 0;
            for (const [k, ok] of Object.entries(checks)) {
                const li = document.querySelector(`#req-list-pass [data-req="${k}"]`);
                if (!li) continue;
                const icon = li.querySelector('i');
                if (ok) { icon.className = 'bi bi-check-circle-fill'; li.classList.add('ok'); cumplidos++; }
                else { icon.className = 'bi bi-circle'; li.classList.remove('ok'); }
            }

            const fill = document.getElementById('strength-fill');
            const text = document.getElementById('strength-text');
            if (fill && text) {
                if (pass.length === 0) { fill.style.width = '0%'; text.textContent = ''; }
                else {
                    let nivel, label, color;
                    if (cumplidos <= 2) { nivel = 33; label = 'Débil'; color = '#dc2626'; }
                    else if (cumplidos < 5 || pass.length < 12) { nivel = 66; label = 'Media'; color = '#f59e0b'; }
                    else { nivel = 100; label = 'Fuerte'; color = '#16a34a'; }
                    fill.style.width = nivel + '%';
                    fill.style.background = color;
                    text.textContent = 'Fortaleza: ' + label;
                    text.style.color = color;
                }
            }

            const msg = document.getElementById('match-msg');
            if (msg) {
                if (conf.length === 0) { msg.textContent = ''; msg.className = 'match-msg'; }
                else if (pass === conf) { msg.innerHTML = '<i class="bi bi-check-circle-fill"></i> Las contraseñas coinciden'; msg.className = 'match-msg ok'; }
                else { msg.innerHTML = '<i class="bi bi-x-circle-fill"></i> Las contraseñas no coinciden'; msg.className = 'match-msg error'; }
            }
        };

        nueva?.addEventListener('input', validar);
        confirmar?.addEventListener('input', validar);
    }

    async function cambiarPassword() {
        const actual = document.getElementById('pass-actual').value;
        const nueva = document.getElementById('pass-nueva').value;
        const confirmar = document.getElementById('pass-confirmar').value;

        if (!actual) { Toast.warning('Falta', 'Ingresa tu contraseña actual'); return; }
        if (!nueva) { Toast.warning('Falta', 'Ingresa la nueva contraseña'); return; }
        if (nueva !== confirmar) { Toast.warning('Error', 'Las contraseñas no coinciden'); return; }

        const btn = document.getElementById('btn-cambiar-pass');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Cambiando...';

        const res = await Api.post('api/perfil.php?accion=cambiar_password', {
            password_actual: actual,
            password_nueva: nueva,
            password_confirmar: confirmar,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-key-fill"></i> Cambiar contraseña';

        if (!res.success) {
            if (res.errors) Toast.error('Error', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Contraseña cambiada', 'En tu próximo inicio de sesión usa la nueva');

        document.getElementById('pass-actual').value = '';
        document.getElementById('pass-nueva').value = '';
        document.getElementById('pass-confirmar').value = '';

        // Volver al tab de datos
        document.querySelectorAll('#tabs-perfil .tab').forEach(b => b.classList.remove('active'));
        document.querySelector('#tabs-perfil .tab[data-tab="datos"]').classList.add('active');
        renderTab('datos');
    }

    // ============================================================
    // CERRAR
    // ============================================================
    function cerrar() {
        document.getElementById('modal-perfil')?.remove();
    }

    // ============================================================
    // HELPERS
    // ============================================================
    function inicial(nombre) {
        return (nombre || '?').trim().charAt(0).toUpperCase();
    }
    function colorAvatar(str) {
        const colores = ['#2563eb', '#16a34a', '#dc2626', '#f59e0b', '#7c3aed', '#0891b2', '#db2777'];
        let hash = 0;
        for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
        return colores[Math.abs(hash) % colores.length];
    }
    function escapeHtml(t) {
        const div = document.createElement('div');
        div.textContent = t || '';
        return div.innerHTML;
    }
    function escapeAttr(t) {
        return String(t || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // ============================================================
    // INIT
    // ============================================================
    document.addEventListener('DOMContentLoaded', () => {
        // Hook al botón del menú de usuario
        const trigger = document.querySelector('.user-menu-trigger');
        // No hacemos nada automático, se llama desde el botón "Mi perfil"
    });

    return {
        abrir,
        cerrar,
        guardar,
        eliminarAvatar,
        cambiarPassword,
    };
})();

window.Perfil = Perfil;