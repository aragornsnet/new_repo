/**
 * IPV - Comportamiento del layout: sidebar, tema, menú de usuario
 * y scroll automático hacia el item activo del sidebar.
 */

document.addEventListener('DOMContentLoaded', () => {
    // ============================================================
    // Referencias base
    // ============================================================
    const app        = document.querySelector('.app');
    const toggleBtn  = document.querySelector('.sidebar-toggle');
    const sidebar    = document.querySelector('.sidebar');
    const overlay    = document.querySelector('.sidebar-overlay');
    const sidebarNav = document.getElementById('sidebar-nav');

    // ============================================================
    // 1) SIDEBAR TOGGLE (desktop: colapsa, móvil: overlay)
    // ============================================================
    if (toggleBtn && app) {
        toggleBtn.addEventListener('click', () => {
            if (window.innerWidth <= 1024) {
                sidebar?.classList.toggle('open');
                overlay?.classList.toggle('active');
            } else {
                app.classList.toggle('sidebar-collapsed');
                localStorage.setItem(
                    'ipv_sidebar_collapsed',
                    app.classList.contains('sidebar-collapsed') ? '1' : '0'
                );
            }
        });
    }

    // Restaurar estado del sidebar en desktop
    if (app && window.innerWidth > 1024) {
        if (localStorage.getItem('ipv_sidebar_collapsed') === '1') {
            app.classList.add('sidebar-collapsed');
        }
    }

    overlay?.addEventListener('click', () => {
        sidebar?.classList.remove('open');
        overlay.classList.remove('active');
    });

    // ============================================================
    // 2) TEMA CLARO/OSCURO
    // ============================================================
    const themeToggle = document.querySelector('[data-toggle-theme]');
    const html = document.documentElement;

    const temaGuardado = localStorage.getItem('ipv_theme') || 'claro';
    aplicarTema(temaGuardado);

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const actual = html.dataset.theme || 'claro';
            const nuevo = actual === 'dark' ? 'claro' : 'dark';
            aplicarTema(nuevo);
            localStorage.setItem('ipv_theme', nuevo);
        });
    }

    function aplicarTema(tema) {
        if (tema === 'dark') {
            html.dataset.theme = 'dark';
            themeToggle?.querySelector('i')?.classList.replace('bi-moon-stars', 'bi-sun');
        } else {
            html.removeAttribute('data-theme');
            themeToggle?.querySelector('i')?.classList.replace('bi-sun', 'bi-moon-stars');
        }
    }

    // ============================================================
    // 3) MENÚ DE USUARIO
    // ============================================================
    const userTrigger = document.querySelector('.user-menu-trigger');
    const userMenu    = document.querySelector('.user-menu');
    const userDropdown = userMenu ? userMenu.querySelector('.user-dropdown') : null;

    if (userTrigger && userDropdown) {
        userTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            userDropdown.classList.toggle('active');
        });
        document.addEventListener('click', (e) => {
            if (!userDropdown.contains(e.target) && !userTrigger.contains(e.target)) {
                userDropdown.classList.remove('active');
            }
        });
    }

    // ============================================================
    // 4) SCROLL DEL SIDEBAR
    // ------------------------------------------------------------
    // Objetivo: al cargar cualquier página, el sidebar debe mostrar
    // el item activo (la opción del menú actual), haciendo scroll
    // automático hacia él si es necesario.
    //
    // Estrategia:
    //   1) Buscar el item activo (.nav-item.active).
    //   2) Hacer scroll para que quede visible (centrado si posible).
    //   3) Fallback: si no hay item activo, restaurar el scroll guardado.
    //   4) Guardar el scroll al navegar (para el fallback).
    // ============================================================
    if (sidebarNav) {
        const SCROLL_KEY = 'ipv_sidebar_scroll';

        // ------------------------------------------------------------
        // Guardar el scroll actual
        // ------------------------------------------------------------
        function guardarScroll() {
            try {
                sessionStorage.setItem(SCROLL_KEY, String(sidebarNav.scrollTop));
            } catch (e) {
                // sessionStorage puede fallar en modo incógnito estricto
            }
        }

        // ------------------------------------------------------------
        // Hacer scroll para que el item activo sea visible
        // ------------------------------------------------------------
        function centrarItemActivo(intentosRestantes = 10) {
            const activo = sidebarNav.querySelector('a.nav-item.active');

            if (!activo) {
                // No hay item activo (ej: página 403, 404, login).
                // Fallback: restaurar el scroll guardado.
                restaurarScrollGuardado(intentosRestantes);
                return;
            }

            // Verificar que el nav tenga dimensiones (el contenido ya cargó)
            if (sidebarNav.scrollHeight <= sidebarNav.clientHeight) {
                // El sidebar aún no tiene contenido scrolleable, reintentar
                if (intentosRestantes > 0) {
                    requestAnimationFrame(() => centrarItemActivo(intentosRestantes - 1));
                }
                return;
            }

            // Calcular la posición ideal del item activo:
            // centrarlo verticalmente en el viewport del sidebar.
            const navRect     = sidebarNav.getBoundingClientRect();
            const activoRect  = activo.getBoundingClientRect();

            // Distancia del item activo respecto al top del nav (en el contenido)
            const offsetEnNav = activoRect.top - navRect.top + sidebarNav.scrollTop;

            // Altura visible del nav
            const alturaVisible = sidebarNav.clientHeight;

            // Altura del item activo
            const alturaItem = activoRect.height;

            // Posición ideal: centrar el item activo
            // (offsetEnNav - alturaVisible/2 + alturaItem/2)
            const scrollIdeal = Math.max(
                0,
                offsetEnNav - (alturaVisible / 2) + (alturaItem / 2)
            );

            // Si ya está visible y cómodamente posicionado, no mover
            const scrollMin = offsetEnNav - alturaVisible + alturaItem + 20;
            const scrollMax = offsetEnNav - 20;

            if (sidebarNav.scrollTop >= scrollMin && sidebarNav.scrollTop <= scrollMax) {
                // Ya está visible, no hace falta mover
                return;
            }

            // Aplicar scroll suave (sin animación, para evitar el parpadeo)
            sidebarNav.scrollTop = scrollIdeal;

            // Verificar que se aplicó; si no, reintentar
            if (Math.abs(sidebarNav.scrollTop - scrollIdeal) > 2 && intentosRestantes > 0) {
                requestAnimationFrame(() => centrarItemActivo(intentosRestantes - 1));
            }
        }

        // ------------------------------------------------------------
        // Fallback: restaurar el scroll guardado
        // ------------------------------------------------------------
        function restaurarScrollGuardado(intentosRestantes = 10) {
            let guardado;
            try {
                guardado = sessionStorage.getItem(SCROLL_KEY);
            } catch (e) {
                return;
            }

            if (guardado === null) return;

            const target = parseInt(guardado, 10) || 0;
            if (target === 0) return;

            if (sidebarNav.scrollHeight <= sidebarNav.clientHeight) {
                if (intentosRestantes > 0) {
                    requestAnimationFrame(() => restaurarScrollGuardado(intentosRestantes - 1));
                }
                return;
            }

            sidebarNav.scrollTop = target;

            if (Math.abs(sidebarNav.scrollTop - target) > 2 && intentosRestantes > 0) {
                requestAnimationFrame(() => restaurarScrollGuardado(intentosRestantes - 1));
            }
        }

        // ------------------------------------------------------------
        // Ejecutar al cargar la página
        // ------------------------------------------------------------
        // Intento 1: cuando el DOM está listo (ya estamos en DOMContentLoaded)
        centrarItemActivo();

        // Intento 2: cuando la página termina de cargar (imágenes, fuentes)
        window.addEventListener('load', () => centrarItemActivo());

        // Intento 3: cuando la página viene del bfcache (botón "atrás")
        window.addEventListener('pageshow', (e) => {
            if (e.persisted) {
                centrarItemActivo();
            }
        });

        // ------------------------------------------------------------
        // Guardar el scroll al hacer scroll (con debounce)
        // ------------------------------------------------------------
        let scrollTimer;
        sidebarNav.addEventListener('scroll', () => {
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(guardarScroll, 80);
        }, { passive: true });

        // ------------------------------------------------------------
        // Guardar el scroll al hacer clic en un enlace del sidebar
        // (fase de captura, antes de la navegación)
        // ------------------------------------------------------------
        sidebarNav.addEventListener('click', (e) => {
            const link = e.target.closest('a.nav-item');
            if (link) {
                guardarScroll();
            }
        }, true);

        // ------------------------------------------------------------
        // Guardar el scroll al salir de la página (respaldo)
        // ------------------------------------------------------------
        window.addEventListener('beforeunload', guardarScroll);

        // ------------------------------------------------------------
        // Guardar el scroll cuando la pestaña pasa a segundo plano
        // ------------------------------------------------------------
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                guardarScroll();
            }
        });
    }
});