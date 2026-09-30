/**
 * IPV - Cliente API con manejo de CSRF y errores
 */

const Api = (() => {
    const BASE = window.APP_BASE_URL || '/ipv/';
    const CSRF_TOKEN = window.CSRF_TOKEN || '';

    /**
     * Petición genérica
     */
    async function request(url, options = {}) {
        const finalUrl = url.startsWith('http') ? url : BASE + url;
        const headers = Object.assign({
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': CSRF_TOKEN,
            'Accept': 'application/json',
        }, options.headers || {});

        if (options.body && !(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        try {
            const res = await fetch(finalUrl, Object.assign({}, options, { headers }));

            let data = null;
            const ct = res.headers.get('content-type') || '';
            if (ct.includes('application/json')) {
                data = await res.json();
            } else {
                data = { success: res.ok, message: await res.text() };
            }

            if (!res.ok) {
                // Sesión expirada
                if (res.status === 401) {
                    Toast.warning('Sesión expirada', 'Inicia sesión de nuevo');
                    setTimeout(() => { window.location.href = BASE + 'views/login.php'; }, 1500);
                }
                const msg = data?.message || `Error ${res.status}`;
                return { success: false, message: msg, status: res.status, data: data?.data, errors: data?.errors };
            }

            return data;

        } catch (err) {
            console.error('API Error:', err);
            return { success: false, message: 'Error de conexión con el servidor' };
        }
    }

    return {
        get:    (url) => request(url, { method: 'GET' }),
        post:   (url, body) => request(url, { method: 'POST', body }),
        put:    (url, body) => request(url, { method: 'PUT', body }),
        patch:  (url, body) => request(url, { method: 'PATCH', body }),
        delete: (url) => request(url, { method: 'DELETE' }),

        upload: (url, formData) => request(url, { method: 'POST', body: formData }),

        request,
    };
})();

window.Api = Api;