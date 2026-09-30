/**
 * IPV - Sistema de notificaciones (polling + dropdown)
 */

const Notificaciones = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let intervalo = null;
    let pollingSegundos = 60;
    let maxDropdown = 10;
    let abierto = false;

    function init() {
        const btn = document.querySelector('[data-notif-trigger]');
        if (!btn) return;

        bindTrigger(btn);
        bindFueraClick();
        cargarConfigYPolling();

        refrescar();
    }

    function cargarConfigYPolling() {
        pollingSegundos = parseInt(window.NOTIF_POLLING || 60, 10);
        maxDropdown     = parseInt(window.NOTIF_MAX    || 10, 10);

        if (intervalo) clearInterval(intervalo);
        intervalo = setInterval(() => {
            if (document.visibilityState === 'visible') {
                refrescarContador();
            }
        }, pollingSegundos * 1000);

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                refrescarContador();
            }
        });
    }

    function bindTrigger(btn) {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            abierto = !abierto;
            const dropdown = document.getElementById('notif-dropdown');
            if (abierto) {
                await refrescar();
                dropdown?.classList.add('active');
            } else {
                dropdown?.classList.remove('active');
            }
        });
    }

    function bindFueraClick() {
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('notif-dropdown');
            const btn = document.querySelector('[data-notif-trigger]');
            if (!dropdown || !btn) return;
            if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
                dropdown.classList.remove('active');
                abierto = false;
            }
        });
    }

    async function refrescarContador() {
        const res = await Api.get('api/notificaciones.php?accion=contar');
        if (!res.success) return;
        actualizarBadge(res.data.no_leidas);
    }

    async function refrescar() {
        const res = await Api.get('api/notificaciones.php?accion=listar&limit=' + maxDropdown);
        if (!res.success) return;

        actualizarBadge(res.data.no_leidas);
        renderLista(res.data.notificaciones);
    }

    function actualizarBadge(n) {
        const badge = document.querySelector('[data-notif-badge]');
        if (!badge) return;
        if (n > 0) {
            badge.textContent = n > 99 ? '99+' : n;
            badge.classList.remove('d-none');
        } else {
            badge.classList.add('d-none');
        }
    }

    function renderLista(lista) {
        const cont = document.getElementById('notif-lista');
        if (!cont) return;

        if (!lista.length) {
            cont.innerHTML = `
                <div style="padding:30px 20px;text-align:center;color:var(--text-muted);">
                    <i class="bi bi-bell-slash" style="font-size:32px;opacity:.5;"></i>
                    <div class="mt-2 text-sm">Sin notificaciones</div>
                </div>
            `;
            return;
        }

        cont.innerHTML = lista.map(n => {
            const noLeida = n.leida == 0;
            return `
                <div class="notif-item ${noLeida ? 'no-leida' : ''}" onclick="Notificaciones.abrir(${n.id}, '${encodeURIComponent(n.url || '')}')">
                    <div class="notif-icon bg-${n.color || 'primary'}">
                        <i class="bi bi-${n.icono || 'bell'}"></i>
                    </div>
                    <div class="notif-body">
                        <div class="notif-titulo">${escapeHtml(n.titulo)}</div>
                        ${n.mensaje ? `<div class="notif-mensaje">${escapeHtml(n.mensaje)}</div>` : ''}
                        <div class="notif-fecha">${App.timeAgo(n.fecha_creacion)}</div>
                    </div>
                    <button class="notif-close" onclick="event.stopPropagation();Notificaciones.eliminar(${n.id})" title="Eliminar">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            `;
        }).join('');
    }

    async function abrir(id, urlEncoded) {
        await Api.post('api/notificaciones.php?accion=marcar_leida', { id });
        const url = decodeURIComponent(urlEncoded || '');
        if (url) {
            window.location.href = url.startsWith('http') ? url : BASE_URL + url.replace(/^\//, '');
        } else {
            refrescar();
        }
    }

    async function eliminar(id) {
        const res = await Api.post('api/notificaciones.php?accion=eliminar', { id });
        if (res.success) {
            actualizarBadge(res.data.no_leidas);
            refrescar();
        }
    }

    async function marcarTodasLeidas() {
        const res = await Api.post('api/notificaciones.php?accion=marcar_todas_leidas');
        if (res.success) {
            actualizarBadge(0);
            refrescar();
            Toast.success('Listo', 'Todas marcadas como leídas');
        }
    }

    async function eliminarLeidas() {
        const ok = await App.confirmar('¿Eliminar todas las notificaciones leídas?');
        if (!ok) return;
        const res = await Api.post('api/notificaciones.php?accion=eliminar_leidas');
        if (res.success) {
            refrescar();
            Toast.success('Listo', 'Notificaciones eliminadas');
        }
    }

    function escapeHtml(t) {
        const div = document.createElement('div');
        div.textContent = t || '';
        return div.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', init);

    return { refrescar, refrescarContador, marcarTodasLeidas, eliminarLeidas, abrir, eliminar };
})();

window.Notificaciones = Notificaciones;