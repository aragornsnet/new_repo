/**
 * IPV - Sistema de notificaciones (toasts)
 *
 * Los mensajes permiten HTML controlado (<br>, <strong>, <code>).
 * El título SIEMPRE se escapa.
 *
 * Helper Toast.h() para escapar datos de usuario antes de pasarlos.
 */

const Toast = (() => {
    let container = null;

    function getContainer() {
        if (!container) {
            container = document.querySelector('.toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'toast-container';
                document.body.appendChild(container);
            }
        }
        return container;
    }

    function show(tipo, titulo, mensaje = '', duracion = 4000) {
        const iconos = {
            success: 'bi-check-circle-fill',
            error:   'bi-x-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info:    'bi-info-circle-fill',
        };

        const toast = document.createElement('div');
        toast.className = `toast toast-${tipo}`;

        toast.innerHTML = `
            <i class="bi ${iconos[tipo] || iconos.info}"></i>
            <div class="toast-content">
                <div class="toast-title">${escapeHtml(titulo)}</div>
                ${mensaje ? `<div class="toast-message">${mensaje}</div>` : ''}
            </div>
            <button class="toast-close" aria-label="Cerrar">
                <i class="bi bi-x"></i>
            </button>
        `;

        getContainer().appendChild(toast);

        const cerrar = () => {
            toast.style.animation = 'fadeOut .3s forwards';
            setTimeout(() => toast.remove(), 300);
        };

        toast.querySelector('.toast-close').addEventListener('click', cerrar);

        if (duracion > 0) {
            setTimeout(cerrar, duracion);
        }

        return toast;
    }

    function escapeHtml(t) {
        const div = document.createElement('div');
        div.textContent = t;
        return div.innerHTML;
    }

    /**
     * Helper público para escapar datos de usuario antes de pasarlos a Toast.*
     */
    function h(t) {
        return escapeHtml(String(t ?? ''));
    }

    return {
        success: (t, m) => show('success', t, m),
        error:   (t, m) => show('error', t, m),
        warning: (t, m) => show('warning', t, m),
        info:    (t, m) => show('info', t, m),
        show,
        h,
    };
})();

window.Toast = Toast;