/**
 * IPV - Manual de uso (visor de Markdown)
 */

const Ayuda = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';
    const DOC_ACTIVO = window.AYUDA_DOC_ACTIVO || 'usuario';

    let headings = [];
    let contenidoOriginal = '';

    // ============================================================
    // INIT
    // ============================================================
    function init() {
        const contenido = document.getElementById('ayuda-contenido');
        if (!contenido) return;

        // Limpiar hash previo para evitar que el navegador salte
        if (window.location.hash) {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }

        // Guardar HTML original para restaurar tras búsquedas
        contenidoOriginal = contenido.innerHTML;

        // Asignar IDs a los headings para navegación
        asignarIdsHeadings();

        // Construir índice lateral
        construirIndice();

        // Bind del buscador
        bindBuscador();

        // Bind del botón "volver arriba"
        bindVolverArriba();

        // Scroll spy
        bindScrollSpy();

        // Restaurar scroll si venimos de un doc previo
        restaurarScrollPosicion();
    }

    // ============================================================
    // ASIGNAR IDs a los headings
    // ============================================================
    function asignarIdsHeadings() {
        const cont = document.getElementById('ayuda-contenido');
        if (!cont) return;

        headings = [];
        const usados = new Set();

        cont.querySelectorAll('h1, h2, h3, h4').forEach((h, i) => {
            const texto = h.textContent.trim();
            if (!texto) return;

            // Slug
            let slug = texto
                .toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9\s-]/g, '')
                .trim()
                .replace(/\s+/g, '-')
                .substring(0, 80);

            if (!slug) slug = 'seccion-' + i;

            // Evitar duplicados
            let baseSlug = slug;
            let contador = 2;
            while (usados.has(slug)) {
                slug = baseSlug + '-' + contador;
                contador++;
            }
            usados.add(slug);

            h.id = slug;

            headings.push({
                id: slug,
                texto: texto,
                nivel: parseInt(h.tagName.substring(1), 10),
            });
        });
    }

    // ============================================================
    // Construir índice lateral
    // ============================================================
    function construirIndice() {
        const nav = document.getElementById('ayuda-nav');
        const sinToc = document.getElementById('ayuda-sin-toc');
        if (!nav) return;

        if (!headings.length) {
            nav.innerHTML = '';
            if (sinToc) sinToc.style.display = 'block';
            return;
        }
        if (sinToc) sinToc.style.display = 'none';

        // Mostrar solo h1, h2 y h3 (h4 opcional)
        const visibles = headings.filter(h => h.nivel <= 3);

        nav.innerHTML = visibles.map(h => `
            <a href="#${h.id}" class="ayuda-nav-item h${h.nivel}" data-id="${h.id}">
                ${escapeHtml(h.texto)}
            </a>
        `).join('');

        // Click suave — scroll manual (evita que el navegador salte al top)
        nav.querySelectorAll('a').forEach(a => {
            a.addEventListener('click', (e) => {
                e.preventDefault();
                const id = a.dataset.id;
                const target = document.getElementById(id);
                if (!target) return;

                const headerHeight = parseInt(
                    getComputedStyle(document.documentElement)
                        .getPropertyValue('--header-height') || '64',
                    10
                ) || 64;

                const y = target.getBoundingClientRect().top
                        + window.scrollY
                        - headerHeight
                        - 20;

                window.scrollTo({ top: y, behavior: 'smooth' });
                history.replaceState(null, '', '#' + id);
            });
        });
    }

    // ============================================================
    // Scroll spy (resaltar en el índice el heading actual)
    // ============================================================
    function bindScrollSpy() {
        const nav = document.getElementById('ayuda-nav');
        if (!nav) return;

        const enlaces = nav.querySelectorAll('a');
        if (!enlaces.length) return;

        const onScroll = () => {
            const scrollY = window.scrollY;
            const offset = 120;

            let activo = null;
            for (const h of headings) {
                if (h.nivel > 3) continue;
                const el = document.getElementById(h.id);
                if (!el) continue;
                const top = el.getBoundingClientRect().top + scrollY - offset;
                if (scrollY >= top) {
                    activo = h.id;
                } else {
                    break;
                }
            }

            enlaces.forEach(a => {
                a.classList.toggle('active', a.dataset.id === activo);
            });

            // Scroll dentro del índice lateral (solo el nav, no la ventana)
            const activoEl = nav.querySelector('a.active');
            if (activoEl) {
                const navScrollTop = nav.scrollTop;
                const navHeight = nav.clientHeight;
                const elTop = activoEl.offsetTop - nav.offsetTop;

                if (elTop < navScrollTop) {
                    nav.scrollTo({ top: elTop, behavior: 'smooth' });
                } else if (elTop + activoEl.offsetHeight > navScrollTop + navHeight) {
                    nav.scrollTo({
                        top: elTop + activoEl.offsetHeight - navHeight,
                        behavior: 'smooth'
                    });
                }
            }
        };

        window.addEventListener('scroll', throttle(onScroll, 100));
        onScroll();
    }

    // ============================================================
    // Buscador
    // ============================================================
    function bindBuscador() {
        const input = document.getElementById('ayuda-buscar');
        const limpiar = document.getElementById('ayuda-limpiar');
        if (!input) return;

        const debounced = debounce((valor) => {
            if (limpiar) limpiar.style.display = valor.length > 0 ? 'block' : 'none';
            buscar(valor);
        }, 250);

        input.addEventListener('input', (e) => debounced(e.target.value.trim()));

        if (limpiar) {
            limpiar.addEventListener('click', () => {
                input.value = '';
                limpiar.style.display = 'none';
                buscar('');
                input.focus();
            });
        }

        // ESC limpia
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                input.value = '';
                limpiar.style.display = 'none';
                buscar('');
            }
        });
    }

    function buscar(query) {
        const cont = document.getElementById('ayuda-contenido');
        const sinResultados = document.getElementById('ayuda-busqueda-vacia');
        const sinResultadosTexto = document.getElementById('ayuda-busqueda-texto');
        if (!cont) return;

        // Restaurar contenido original
        cont.innerHTML = contenidoOriginal;

        if (!query) {
            if (sinResultados) sinResultados.style.display = 'none';
            cont.style.display = '';
            // Reconstruir índice y headings
            asignarIdsHeadings();
            construirIndice();
            return;
        }

        // Buscar en el texto
        const regex = new RegExp(escapeRegex(query), 'gi');
        let huboResultados = false;

        // Recorrer solo nodos de texto para no romper el HTML
        const walker = document.createTreeWalker(cont, NodeFilter.SHOW_TEXT, null, false);
        const nodos = [];
        while (walker.nextNode()) {
            const nodo = walker.currentNode;
            // Saltar nodos vacíos o dentro de pre/code
            if (!nodo.nodeValue.trim()) continue;
            if (nodo.parentElement.closest('pre, code, script, style')) continue;
            if (regex.test(nodo.nodeValue)) {
                nodos.push(nodo);
                huboResultados = true;
            }
            regex.lastIndex = 0;
        }

        // Resaltar
        nodos.forEach(nodo => {
            const span = document.createElement('span');
            span.innerHTML = escapeHtml(nodo.nodeValue).replace(
                regex,
                match => `<mark class="busqueda-match">${match}</mark>`
            );
            nodo.parentNode.replaceChild(span, nodo);
        });

        // Mostrar/ocultar
        if (!huboResultados) {
            cont.style.display = 'none';
            if (sinResultados) {
                if (sinResultadosTexto) sinResultadosTexto.textContent = query;
                sinResultados.style.display = '';
            }
            // Vaciar índice
            const nav = document.getElementById('ayuda-nav');
            if (nav) nav.innerHTML = '';
        } else {
            cont.style.display = '';
            if (sinResultados) sinResultados.style.display = 'none';
            // Reconstruir índice con los headings encontrados
            asignarIdsHeadings();
            construirIndice();
        }
    }

    function limpiarBusqueda() {
        const input = document.getElementById('ayuda-buscar');
        const limpiar = document.getElementById('ayuda-limpiar');
        if (input) input.value = '';
        if (limpiar) limpiar.style.display = 'none';
        buscar('');
    }

    // ============================================================
    // Volver arriba
    // ============================================================
    function bindVolverArriba() {
        const btn = document.getElementById('ayuda-top');
        if (!btn) return;

        btn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        const onScroll = () => {
            btn.classList.toggle('visible', window.scrollY > 400);
        };
        window.addEventListener('scroll', throttle(onScroll, 150));
        onScroll();
    }

    // ============================================================
    // Restaurar posición de scroll al cambiar de documento
    // ============================================================
    function restaurarScrollPosicion() {
        // Si hay un hash en la URL, hacer scroll a ese elemento
        const hash = window.location.hash;
        if (hash) {
            const target = document.getElementById(hash.substring(1));
            if (target) {
                setTimeout(() => {
                    const headerHeight = parseInt(
                        getComputedStyle(document.documentElement)
                            .getPropertyValue('--header-height') || '64',
                        10
                    ) || 64;

                    const y = target.getBoundingClientRect().top
                            + window.scrollY
                            - headerHeight
                            - 20;

                    window.scrollTo({ top: y, behavior: 'smooth' });
                }, 100);
            }
        }
    }

    // ============================================================
    // Utilidades
    // ============================================================
    function escapeHtml(t) {
        const div = document.createElement('div');
        div.textContent = t;
        return div.innerHTML;
    }

    function escapeRegex(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function debounce(fn, ms) {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), ms);
        };
    }

    function throttle(fn, ms) {
        let last = 0;
        let pending = null;
        return (...args) => {
            const now = Date.now();
            if (now - last >= ms) {
                last = now;
                fn(...args);
            } else {
                clearTimeout(pending);
                pending = setTimeout(() => {
                    last = Date.now();
                    fn(...args);
                }, ms - (now - last));
            }
        };
    }

    // ============================================================
    // INIT
    // ============================================================
    document.addEventListener('DOMContentLoaded', init);

    return { limpiarBusqueda };
})();

window.Ayuda = Ayuda;