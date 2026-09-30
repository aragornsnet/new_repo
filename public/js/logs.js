/**
 * IPV - Visor de logs
 */

const Logs = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let archivoActual = '';
    let paginaActual = 1;
    let totalPags = 1;
    let archivosDisponibles = [];

    // ============================================================
    // INICIALIZACIÓN
    // ============================================================
    async function init() {
        await cargarArchivos();
        await cargarEstadisticas();

        if (archivosDisponibles.length > 0) {
            // Seleccionar el primero automáticamente
            const sel = document.getElementById('archivo-select');
            sel.value = archivosDisponibles[0].nombre;
            archivoActual = archivosDisponibles[0].nombre;
            await cargar(1);
        }
    }

    function recargar() {
        init();
    }

    // ============================================================
    // CARGAR LISTA DE ARCHIVOS
    // ============================================================
    async function cargarArchivos() {
        const sel = document.getElementById('archivo-select');
        sel.innerHTML = '<option value="">Cargando...</option>';

        const res = await Api.get('api/logs.php?accion=listar');
        if (!res.success) {
            Toast.error('Error', res.message);
            sel.innerHTML = '<option value="">Error al cargar</option>';
            return;
        }

        archivosDisponibles = res.data.archivos || [];

        if (!archivosDisponibles.length) {
            sel.innerHTML = '<option value="">Sin archivos de log</option>';
            return;
        }

        sel.innerHTML = archivosDisponibles.map(a => {
            const tamaño = bytesLegible(a.tamano);
            const externo = a.externo ? ' (externo)' : '';
            return `<option value="${escapeAttr(a.nombre)}">${escapeHtml(a.nombre)} — ${tamaño}${externo}</option>`;
        }).join('');
    }

    // ============================================================
    // CARGAR ESTADÍSTICAS
    // ============================================================
    async function cargarEstadisticas() {
        const res = await Api.get('api/logs.php?accion=estadisticas');
        if (!res.success) return;

        const d = res.data;
        setText('stat-archivos', d.total_archivos);
        setText('stat-tamano', bytesLegible(d.total_tamano));
        setText('stat-errores', d.por_nivel.error || 0);
        setText('stat-warnings', d.por_nivel.warning || 0);
    }

    // ============================================================
    // CAMBIAR ARCHIVO
    // ============================================================
    function cambiarArchivo() {
        const sel = document.getElementById('archivo-select');
        archivoActual = sel.value;

        if (!archivoActual) {
            document.getElementById('visor-logs').innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-terminal"></i>
                    <p class="text-muted">Selecciona un archivo para ver su contenido</p>
                </div>
            `;
            document.getElementById('btn-descargar').disabled = true;
            document.getElementById('btn-vaciar').disabled = true;
            document.getElementById('btn-eliminar').disabled = true;
            return;
        }

        cargar(1);
    }

    // ============================================================
    // CARGAR CONTENIDO
    // ============================================================
    async function cargar(pagina = 1) {
        if (!archivoActual) return;

        paginaActual = pagina;

        const nivel = document.getElementById('filtro-nivel').value;
        const q = document.getElementById('filtro-q').value.trim();
        const orden = document.getElementById('filtro-orden').value;

        const params = new URLSearchParams();
        params.append('archivo', archivoActual);
        params.append('pagina', paginaActual);
        params.append('por_pagina', 100);
        params.append('inverso', orden);
        if (nivel) params.append('nivel', nivel);
        if (q) params.append('q', q);

        const cont = document.getElementById('visor-logs');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get('api/logs.php?accion=leer&' + params);
        if (!res.success) {
            cont.innerHTML = `<div class="alert alert-danger">${escapeHtml(res.message)}</div>`;
            return;
        }

        const d = res.data;
        totalPags = d.total_pags;

        setText('total-lineas', d.total);
        setText('archivo-actual', d.archivo);

        // Info
        const info = document.getElementById('info-archivo');
        info.innerHTML = `
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div class="alert-body">
                    <strong>${escapeHtml(d.archivo)}</strong> ·
                    Tamaño: ${bytesLegible(d.tamano)} ·
                    Última modificación: ${App.formatDate(d.modificado)} ·
                    Total líneas: ${App.formatNumber(d.total)}
                    ${nivel || q ? ' · <strong>Filtros activos</strong>' : ''}
                </div>
            </div>
        `;

        renderLineas(d.lineas);
        renderPaginacion();

        document.getElementById('btn-descargar').disabled = false;
        document.getElementById('btn-vaciar').disabled = false;
        // El botón eliminar solo se activa si es un log interno
        const esExterno = archivosDisponibles.find(a => a.nombre === archivoActual)?.externo;
        document.getElementById('btn-eliminar').disabled = !!esExterno;
    }

    // ============================================================
    // RENDER LÍNEAS
    // ============================================================
    function renderLineas(lineas) {
        const cont = document.getElementById('visor-logs');

        if (!lineas.length) {
            cont.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p class="text-muted">Sin líneas para mostrar</p>
                </div>
            `;
            return;
        }

        const colorNivel = {
            error:   'var(--danger)',
            warning: 'var(--warning)',
            info:    'var(--text)',
            debug:   'var(--text-muted)',
        };

        cont.innerHTML = `
            <div style="background:#1e293b;color:#f1f5f9;font-family:var(--font-mono);font-size:12px;line-height:1.5;max-height:600px;overflow-y:auto;padding:0;">
                ${lineas.map(l => `
                    <div style="display:grid;grid-template-columns:60px 1fr;gap:8px;padding:4px 12px;border-bottom:1px solid rgba(255,255,255,.05);">
                        <div style="color:#64748b;text-align:right;user-select:none;">${l.num}</div>
                        <div style="color:${colorNivel[l.nivel] || '#f1f5f9'};word-break:break-all;white-space:pre-wrap;">${escapeHtml(l.texto)}</div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    // ============================================================
    // PAGINACIÓN
    // ============================================================
    function renderPaginacion() {
        const cont = document.getElementById('paginacion');
        if (!cont) return;

        if (totalPags <= 1) {
            cont.innerHTML = '';
            return;
        }

        const botones = [];
        const maxVisible = 7;
        let inicio = Math.max(1, paginaActual - 3);
        let fin = Math.min(totalPags, inicio + maxVisible - 1);
        if (fin - inicio < maxVisible - 1) inicio = Math.max(1, fin - maxVisible + 1);

        botones.push(`<button class="btn btn-secondary btn-sm" onclick="Logs.cargar(${Math.max(1, paginaActual - 1)})" ${paginaActual === 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button>`);

        if (inicio > 1) {
            botones.push(`<button class="btn btn-ghost btn-sm" onclick="Logs.cargar(1)">1</button>`);
            if (inicio > 2) botones.push(`<span class="text-muted" style="padding:0 6px;">…</span>`);
        }

        for (let i = inicio; i <= fin; i++) {
            botones.push(`<button class="btn ${i === paginaActual ? 'btn-primary' : 'btn-ghost'} btn-sm" onclick="Logs.cargar(${i})">${i}</button>`);
        }

        if (fin < totalPags) {
            if (fin < totalPags - 1) botones.push(`<span class="text-muted" style="padding:0 6px;">…</span>`);
            botones.push(`<button class="btn btn-ghost btn-sm" onclick="Logs.cargar(${totalPags})">${totalPags}</button>`);
        }

        botones.push(`<button class="btn btn-secondary btn-sm" onclick="Logs.cargar(${Math.min(totalPags, paginaActual + 1)})" ${paginaActual === totalPags ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button>`);

        cont.innerHTML = `
            <div style="display:flex;justify-content:center;align-items:center;gap:4px;flex-wrap:wrap;">
                ${botones.join('')}
            </div>
            <div class="text-center text-muted text-xs mt-2">
                Página ${paginaActual} de ${totalPags}
            </div>
        `;
    }

    // ============================================================
    // BÚSQUEDA CON DEBOUNCE
    // ============================================================
    const buscarDebounced = App.debounce(() => cargar(1), 400);

    // ============================================================
    // ACCIONES
    // ============================================================
    function descargar() {
        if (!archivoActual) return;
        window.location.href = BASE_URL + 'api/logs.php?accion=descargar&archivo=' + encodeURIComponent(archivoActual);
    }

    async function vaciar() {
        if (!archivoActual) return;

        const ok = await App.confirmar(
            `¿Vaciar el archivo "${archivoActual}"?\n\nEl archivo quedará vacío pero no se eliminará.`,
            '⚠️ Vaciar log'
        );
        if (!ok) return;

        const res = await Api.post('api/logs.php?accion=vaciar', { archivo: archivoActual });
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }
        Toast.success('Vaciar', 'Archivo vaciado');
        cargar(1);
        cargarEstadisticas();
    }

    async function eliminar() {
        if (!archivoActual) return;

        const ok = await App.confirmar(
            `¿Eliminar el archivo "${archivoActual}"?\n\nEsta acción no se puede deshacer.`,
            '⚠️ Eliminar log'
        );
        if (!ok) return;

        const res = await Api.post('api/logs.php?accion=eliminar', { archivo: archivoActual });
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }
        Toast.success('Eliminar', 'Archivo eliminado');

        archivoActual = '';
        await cargarArchivos();
        await cargarEstadisticas();

        document.getElementById('visor-logs').innerHTML = `
            <div class="empty-state">
                <i class="bi bi-terminal"></i>
                <p class="text-muted">Selecciona un archivo para ver su contenido</p>
            </div>
        `;
        document.getElementById('info-archivo').innerHTML = '';
        document.getElementById('paginacion').innerHTML = '';
        document.getElementById('btn-descargar').disabled = true;
        document.getElementById('btn-vaciar').disabled = true;
        document.getElementById('btn-eliminar').disabled = true;
    }

    function limpiarFiltros() {
        document.getElementById('filtro-nivel').value = '';
        document.getElementById('filtro-q').value = '';
        document.getElementById('filtro-orden').value = '1';
        cargar(1);
    }

    // ============================================================
    // HELPERS
    // ============================================================
    function setText(id, valor) {
        const el = document.getElementById(id);
        if (el) el.textContent = valor;
    }

    function bytesLegible(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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
    document.addEventListener('DOMContentLoaded', init);

    return {
        recargar,
        cargar,
        cambiarArchivo,
        buscarDebounced,
        descargar,
        vaciar,
        eliminar,
        limpiarFiltros,
    };
})();

window.Logs = Logs;