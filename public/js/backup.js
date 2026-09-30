/**
 * IPV - Backup y restauración
 */

const Backup = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    async function cargar() {
        const cont = document.getElementById('lista-backups');
        if (!cont) return;

        cont.innerHTML = '<div class="empty-state"><div class="spinner"></div></div>';

        try {
            const res = await Api.get('api/backup.php?accion=listar');

            if (!res || !res.success) {
                cont.innerHTML = `<div class="alert alert-danger" style="margin:16px;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div><strong>Error</strong><p>${(res && res.message) || 'Error al cargar'}</p></div>
                </div>`;
                return;
            }

            const { backups, total_tamano } = res.data;

            const totalEl = document.getElementById('total-tamano');
            if (totalEl) {
                totalEl.textContent = `${backups.length} archivo(s) · ${bytesLegible(total_tamano)}`;
            }

            render(backups);

        } catch (e) {
            console.error('Error cargar backups:', e);
            cont.innerHTML = `<div class="alert alert-danger" style="margin:16px;">Error: ${App.escapeHtml(e.message)}</div>`;
        }
    }

    function render(backups) {
        const cont = document.getElementById('lista-backups');
        if (!cont) return;

        if (!backups.length) {
            cont.innerHTML = `<div class="empty-state">
                <i class="bi bi-archive"></i>
                <h3>Sin backups</h3>
                <p>Genera tu primer backup con el botón superior.</p>
            </div>`;
            return;
        }

        cont.innerHTML = `
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Archivo</th>
                            <th>Fecha</th>
                            <th class="text-right">Tamaño</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${backups.map(b => `
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <i class="bi bi-file-earmark-code" style="color:var(--primary);font-size:18px;"></i>
                                        <span style="font-family:var(--font-mono);font-size:12px;">${App.escapeHtml(b.nombre)}</span>
                                    </div>
                                </td>
                                <td class="text-muted text-sm">${App.formatDate(b.fecha)}</td>
                                <td class="text-right text-sm">${bytesLegible(b.tamano)}</td>
                                <td class="text-right">
                                    <div class="d-flex gap-1" style="justify-content:flex-end;">
                                        <button class="btn btn-ghost btn-icon" onclick="Backup.descargar('${App.escapeHtml(b.nombre).replace(/'/g, "\\'")}')" title="Descargar">
                                            <i class="bi bi-download"></i>
                                        </button>
                                        <button class="btn btn-ghost btn-icon" onclick="Backup.eliminar('${App.escapeHtml(b.nombre).replace(/'/g, "\\'")}')" title="Eliminar" style="color:var(--danger);">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    async function generarYDescargar() {
        const btn = document.getElementById('btn-generar');
        const originalHtml = btn ? btn.innerHTML : '';

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Generando...';
        }

        Toast.info('Generando backup...', 'Esto puede tardar unos segundos');

        try {
            const res = await Api.post('api/backup.php?accion=generar_guardar', {});

            if (!res || !res.success) {
                Toast.error('Error al generar', (res && res.message) || 'Respuesta inválida');
                return;
            }

            Toast.success('Backup generado', `${res.data.archivo} · ${bytesLegible(res.data.tamano)}`);

            setTimeout(() => {
                window.location.href = BASE_URL + 'api/backup.php?accion=descargar&archivo=' + encodeURIComponent(res.data.archivo);
            }, 300);

            setTimeout(cargar, 2000);

        } catch (e) {
            console.error('Error generar backup:', e);
            Toast.error('Error', e.message);
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    }

    function descargar(nombre) {
        window.location.href = BASE_URL + 'api/backup.php?accion=descargar&archivo=' + encodeURIComponent(nombre);
    }

    async function eliminar(nombre) {
        const ok = await App.confirmar(`¿Eliminar el backup "${nombre}"?`, '⚠️ Eliminar backup');
        if (!ok) return;

        const res = await Api.post('api/backup.php?accion=eliminar', { archivo: nombre });
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }
        Toast.success('Eliminado', 'Backup eliminado');
        cargar();
    }

    function validarConfirmacion() {
        const input = document.getElementById('confirm-restore');
        const btn = document.getElementById('btn-restaurar');
        if (!input || !btn) return;
        btn.disabled = input.value !== 'RESTAURAR';
    }

    async function restaurar() {
        const fileInput = document.getElementById('file-restore');
        const confirmInput = document.getElementById('confirm-restore');

        if (!fileInput || !fileInput.files || !fileInput.files[0]) {
            Toast.warning('Archivo requerido', 'Selecciona un archivo .sql');
            return;
        }

        if (!confirmInput || confirmInput.value !== 'RESTAURAR') {
            Toast.warning('Confirmación requerida', 'Escribe RESTAURAR');
            return;
        }

        const archivo = fileInput.files[0];

        const ok = await App.confirmar(
            `⚠️ ESTA ACCIÓN REEMPLAZARÁ TODOS LOS DATOS ACTUALES ⚠️\n\nArchivo: ${archivo.name}\n\n¿Continuar?`,
            '⚠️ Confirmar restauración'
        );
        if (!ok) return;

        const formData = new FormData();
        formData.append('archivo', archivo);
        formData.append('confirmacion', confirmInput.value);

        const btn = document.getElementById('btn-restaurar');
        const originalHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Restaurando...';
        }

        Toast.info('Restaurando...', 'No cierres esta ventana');

        const res = await Api.upload('api/backup.php?accion=restaurar', formData);

        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }

        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Restaurado', 'Backup previo: ' + (res.data.backup_previo || '—'));

        fileInput.value = '';
        confirmInput.value = '';

        setTimeout(() => {
            if (confirm('Restauración completada. ¿Cerrar sesión para aplicar los cambios?')) {
                window.location.href = BASE_URL + 'logout.php';
            } else {
                cargar();
            }
        }, 2000);
    }

    function bytesLegible(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', cargar);
    } else {
        cargar();
    }

    return { cargar, generarYDescargar, descargar, eliminar, validarConfirmacion, restaurar };
})();

window.Backup = Backup;