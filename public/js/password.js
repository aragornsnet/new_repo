/**
 * IPV - Utilidades de contraseña: toggle ver/ocultar, fortaleza, coincidencia
 */

const Password = (() => {
    /**
     * Alterna mostrar/ocultar una contraseña
     */
    function toggle(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }

    /**
     * Calcula el nivel de fortaleza según los requisitos configurados
     */
    function strength(pass, requisitos) {
        const checks = {
            len:     pass.length >= (requisitos.minLength || 8),
            mayus:   /[A-Z]/.test(pass),
            minus:   /[a-z]/.test(pass),
            num:     /[0-9]/.test(pass),
            simbolo: /[!@#$%^&*()\-_=+\[\]{};:,.<>\/?~`'"\\|]/.test(pass),
        };

        let cumplidos = 0;
        for (const [k, ok] of Object.entries(checks)) {
            if (requisitos[k] && ok) cumplidos++;
        }

        const total = Object.values(requisitos).filter(Boolean).length;
        let nivel = 0, label = '', color = '';

        if (pass.length === 0) {
            return { nivel: 0, label: '', color: '', checks };
        }

        if (cumplidos <= total / 2) {
            nivel = 33; label = 'Débil'; color = '#dc2626';
        } else if (cumplidos < total || pass.length < 12) {
            nivel = 66; label = 'Media'; color = '#f59e0b';
        } else {
            nivel = 100; label = 'Fuerte'; color = '#16a34a';
        }

        return { nivel, label, color, checks };
    }

    return { toggle, strength };
})();

window.Password = Password;