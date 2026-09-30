/**
 * IPV - Información del sistema
 */

const Sistema = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    async function cargar() {
        const cont = document.getElementById('contenido-sistema');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get('api/sistema.php');
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        const { info, errores_recientes } = res.data;
        render(info, errores_recientes);
    }

    function render(info, errores) {
        const cont = document.getElementById('contenido-sistema');

        cont.innerHTML = `
            <div class="card mb-4">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-info-circle-fill"></i> Información general</div>
                </div>
                <div class="grid-3">
                    ${cardInfo('Negocio', info.app.nombre, 'bi-shop')}
                    ${cardInfo('Versión', info.app.version, 'bi-tag-fill')}
                    ${cardInfo('Entorno', info.app.entorno, 'bi-gear-fill')}
                    ${cardInfo('Zona horaria', info.app.zona_horaria, 'bi-clock')}
                    ${cardInfo('Fecha del servidor', info.app.fecha_actual, 'bi-calendar-check')}
                    ${cardInfo('URL base', info.app.base_url, 'bi-link-45deg')}
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-cpu-fill"></i> Servidor</div>
                </div>
                <div class="grid-3">
                    ${cardInfo('Sistema operativo', info.servidor.so, 'bi-windows')}
                    ${cardInfo('Servidor web', info.servidor.servidor, 'bi-server')}
                    ${cardInfo('PHP', info.servidor.php_version, 'bi-filetype-php')}
                    ${cardInfo('MySQL', info.servidor.mysql_version, 'bi-database-fill')}
                    ${cardInfo('Memoria', info.servidor.memoria_limite, 'bi-memory')}
                    ${cardInfo('Max ejecución', info.servidor.max_execution + 's', 'bi-hourglass-split')}
                    ${cardInfo('Upload máximo', info.servidor.upload_max, 'bi-cloud-arrow-up')}
                    ${cardInfo('POST máximo', info.servidor.post_max, 'bi-file-earmark-arrow-up')}
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-device-hdd-fill"></i> Almacenamiento</div>
                </div>
                <div style="margin-bottom:8px;display:flex;justify-content:space-between;">
                    <span class="text-muted">${bytesLegible(info.disco.usado)} usados de ${bytesLegible(info.disco.total)}</span>
                    <strong>${info.disco.porcentaje}%</strong>
                </div>
                <div style="height:12px;background:var(--surface-2);border-radius:999px;overflow:hidden;">
                    <div style="height:100%;width:${info.disco.porcentaje}%;background:${info.disco.porcentaje > 85 ? 'var(--danger)' : (info.disco.porcentaje > 70 ? 'var(--warning)' : 'var(--success)')};transition:width .4s;"></div>
                </div>
                <div class="text-muted text-xs mt-2">${bytesLegible(info.disco.libre)} disponibles</div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-database-fill"></i> Base de datos</div>
                </div>
                <div class="grid-3">
                    ${cardInfo('Nombre', info.bd.nombre, 'bi-database')}
                    ${cardInfo('Host', info.bd.host + ':' + info.bd.puerto, 'bi-hdd-network')}
                    ${cardInfo('Tamaño', info.bd.tamano + ' MB', 'bi-hdd-stack')}
                    ${cardInfo('Tablas', info.bd.num_tablas, 'bi-table')}
                    ${cardInfo('Vistas', info.bd.num_vistas, 'bi-eye')}
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-plugin"></i> Extensiones PHP</div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    ${info.extensiones.map(e => `
                        <span class="badge ${e.cargada ? 'badge-success' : (e.obligatoria ? 'badge-danger' : 'badge-neutral')}">
                            <i class="bi bi-${e.cargada ? 'check-circle-fill' : 'x-circle-fill'}"></i>
                            ${App.escapeHtml(e.nombre)}${e.obligatoria ? '' : ' (opcional)'}
                        </span>
                    `).join('')}
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-folder-fill"></i> Permisos de carpetas</div>
                </div>
                <div class="tabla-wrap">
                    <table class="tabla">
                        <thead>
                            <tr><th>Carpeta</th><th>Existe</th><th>Escribible</th></tr>
                        </thead>
                        <tbody>
                            ${info.carpetas.map(c => `
                                <tr>
                                    <td><code>${App.escapeHtml(c.nombre)}</code></td>
                                    <td>${c.existe ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-danger">No</span>'}</td>
                                    <td>${c.escribible ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-warning">No</span>'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>

            ${errores && errores.length ? `
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="card-title"><i class="bi bi-exclamation-triangle-fill text-warning"></i> Errores recientes</div>
                    </div>
                    <pre style="font-size:11px;max-height:400px;overflow:auto;">${App.escapeHtml(errores.join('\n'))}</pre>
                </div>
            ` : ''}

            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div class="alert-body">
                    Si algún indicador aparece en rojo o amarillo, revisa la configuración del servidor.
                </div>
            </div>
        `;
    }

    function cardInfo(label, valor, icono) {
        return `
            <div style="padding:12px;background:var(--surface-2);border-radius:var(--radius-md);">
                <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-muted);margin-bottom:4px;">
                    <i class="bi ${icono}"></i> ${App.escapeHtml(label)}
                </div>
                <div style="font-weight:600;font-size:14px;word-break:break-word;">${App.escapeHtml(String(valor))}</div>
            </div>
        `;
    }

    function bytesLegible(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    document.addEventListener('DOMContentLoaded', cargar);

    return { cargar, recargar: cargar };
})();

window.Sistema = Sistema;