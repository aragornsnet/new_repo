/**
 * IPV - Utilidades globales
 */

const App = (() => {
    /**
     * Formatea un número con separador de miles
     */
    function formatNumber(n, decimales = 0) {
        return new Intl.NumberFormat('es-ES', {
            minimumFractionDigits: decimales,
            maximumFractionDigits: decimales,
        }).format(n);
    }

    /**
     * Formatea un valor como moneda
     */
    function formatMoney(n, simbolo = '$', decimales = 2) {
        return simbolo + formatNumber(n, decimales);
    }

    /**
     * Formatea una fecha
     */
    function formatDate(fecha) {
        if (!fecha) return '';
        const d = new Date(fecha.replace(' ', 'T'));
        if (isNaN(d)) return fecha;
        const pad = (x) => String(x).padStart(2, '0');
        return `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    /**
     * Formatea fecha relativa
     */
    function timeAgo(fecha) {
        if (!fecha) return '';
        const d = new Date(fecha.replace(' ', 'T'));
        const diff = (Date.now() - d.getTime()) / 1000;
        if (diff < 60) return 'hace unos segundos';
        if (diff < 3600) return `hace ${Math.floor(diff/60)} min`;
        if (diff < 86400) return `hace ${Math.floor(diff/3600)} h`;
        if (diff < 604800) return `hace ${Math.floor(diff/86400)} días`;
        return formatDate(fecha).split(' ')[0];
    }

    /**
     * Escapa HTML
     */
    function escapeHtml(t) {
        const div = document.createElement('div');
        div.textContent = t;
        return div.innerHTML;
    }

    /**
     * Debounce
     */
    function debounce(fn, delay = 300) {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };
    }

    /**
     * Confirmación con modal nativo estilizado
     */
    function confirmar(mensaje, titulo = '¿Estás seguro?') {
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'modal-backdrop active';
            overlay.innerHTML = `
                <div class="modal" style="max-width:420px;">
                    <div class="modal-header">
                        <div class="modal-title"><i class="bi bi-question-circle"></i> ${escapeHtml(titulo)}</div>
                    </div>
                    <div class="modal-body">
                        <p>${escapeHtml(mensaje)}</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-action="no">Cancelar</button>
                        <button class="btn btn-danger" data-action="si">Aceptar</button>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);

            overlay.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                if (action === 'si') { overlay.remove(); resolve(true); }
                else if (action === 'no' || e.target === overlay) { overlay.remove(); resolve(false); }
            });
        });
    }

    return {
        formatNumber, formatMoney, formatDate, timeAgo,
        escapeHtml, debounce, confirmar,
    };
})();

window.App = App;