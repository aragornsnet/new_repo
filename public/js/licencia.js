/**
 * IPV - Licencia (frontend)
 */

const Licencia = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    function copiarInstallId() {
        const input = document.getElementById('install-id');
        if (!input) return;
        input.select();
        input.setSelectionRange(0, 999999);
        try {
            document.execCommand('copy');
            if (typeof Toast !== 'undefined') {
                Toast.success('Copiado', 'ID de instalación copiado');
            }
        } catch (e) {
            if (typeof Toast !== 'undefined') {
                Toast.error('Error', 'No se pudo copiar');
            }
        }
    }

    function mostrarFormRenovar() {
        const activa = document.getElementById('card-licencia-activa');
        const form = document.getElementById('card-activacion');
        if (activa) activa.style.display = 'none';
        if (form) {
            form.style.display = '';
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const textarea = document.getElementById('licencia-input');
            if (textarea) setTimeout(() => textarea.focus(), 300);
        }
    }

    function ocultarFormRenovar() {
        const activa = document.getElementById('card-licencia-activa');
        const form = document.getElementById('card-activacion');
        if (form) form.style.display = 'none';
        if (activa) {
            activa.style.display = '';
            activa.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        // Limpiar el textarea
        const textarea = document.getElementById('licencia-input');
        if (textarea) textarea.value = '';
    }

    async function activar() {
        const textarea = document.getElementById('licencia-input');
        if (!textarea) return;

        const licencia = textarea.value.trim();

        if (!licencia) {
            if (typeof Toast !== 'undefined') {
                Toast.warning('Falta licencia', 'Pega el código de licencia');
            } else {
                alert('Pega el código de licencia');
            }
            return;
        }

        const btn = document.getElementById('btn-activar');
        const originalHtml = btn ? btn.innerHTML : '';

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Activando...';
        }

        try {
            const res = await Api.post('api/licencia.php?accion=activar', { licencia });

            if (!res.success) {
                if (res.errors) {
                    Toast.error('Error', Object.values(res.errors).join('<br>'));
                } else {
                    Toast.error('Error', res.message);
                }
                return;
            }

            Toast.success('Licencia activada', 'Recargando...');
            setTimeout(() => window.location.reload(), 1500);

        } catch (e) {
            console.error(e);
            Toast.error('Error', 'No se pudo activar la licencia');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    }

    return {
        copiarInstallId,
        activar,
        mostrarFormRenovar,
        ocultarFormRenovar,
    };
})();

window.Licencia = Licencia;