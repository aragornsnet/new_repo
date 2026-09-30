/**
 * IPV - Lógica de la pantalla de login
 */

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-login');
    if (!form) return;

    const emailInput = document.getElementById('email');
    const passInput  = document.getElementById('password');
    const btnLogin   = document.getElementById('btn-login');

    const btnToggle = document.querySelector('.btn-toggle-pass');
    if (btnToggle) {
        btnToggle.addEventListener('click', () => Password.toggle('password', btnToggle));
    }

    const validar = () => {
        const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim());
        const passOk  = passInput.value.length > 0;
        btnLogin.disabled = !(emailOk && passOk);
    };
    emailInput.addEventListener('input', validar);
    passInput.addEventListener('input', validar);
    validar();

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = emailInput.value.trim();
        const password = passInput.value;

        btnLogin.disabled = true;
        btnLogin.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Verificando...';

        const res = await Api.post('api/auth.php?accion=login', { email, password });

        if (res.success) {
            Toast.success('Bienvenido', res.data?.nombre || '');
            setTimeout(() => {
                window.location.href = window.APP_BASE_URL || '/ipv/';
            }, 600);
        } else {
            Toast.error('Error', res.message || 'No se pudo iniciar sesión');
            btnLogin.disabled = false;
            btnLogin.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Iniciar sesión';
            passInput.value = '';
            passInput.focus();
        }
    });
});