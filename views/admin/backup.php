<?php
require_once __DIR__ . '/../../config/config.php';

$tituloPagina = 'Backup';
$subtituloPagina = 'Respaldos y restauración de la base de datos';
$paginaActiva = 'backup';
$scriptsExtra = [
    BASE_URL . 'public/js/backup.js',
];
require __DIR__ . '/../layouts/header.php';

if (!Auth::esAdmin()) {
    redirigir('views/403.php');
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">💾 Backup y restauración</h2>
        <p class="page-subtitle">Respalda la base de datos regularmente para proteger tu información</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" id="btn-generar" onclick="Backup.generarYDescargar()">
            <i class="bi bi-download"></i> Generar y descargar backup
        </button>
    </div>
</div>

<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div class="alert-body">
        <div class="alert-title">Recomendaciones importantes</div>
        <ul>
            <li>Haz backups regularmente (diario o semanal según el volumen de ventas).</li>
            <li>Guarda los backups <strong>fuera del servidor</strong> (USB, nube, otro equipo).</li>
            <li>Antes de restaurar un backup, el sistema creará automáticamente un backup previo de seguridad.</li>
            <li>La restauración <strong>reemplaza toda la información actual</strong>. Úsala con precaución.</li>
        </ul>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-arrow-counterclockwise text-danger"></i> Restaurar desde archivo</div>
    </div>
    <p class="text-muted mb-3">
        Sube un archivo <code>.sql</code> previamente generado por el sistema para restaurar la base de datos.
        Esta acción <strong>reemplazará todos los datos actuales</strong>.
    </p>

    <div class="form-grid">
        <div class="field span-full">
            <label>Archivo de backup (.sql)</label>
            <input type="file" id="file-restore" accept=".sql">
            <div class="help">Máximo 50 MB</div>
        </div>

        <div class="field span-full">
            <label>Confirmación</label>
            <input type="text" id="confirm-restore" placeholder="Escribe RESTAURAR para confirmar"
                   oninput="Backup.validarConfirmacion()">
            <div class="help">Escribe la palabra RESTAURAR en mayúsculas para habilitar el botón</div>
        </div>
    </div>

    <div class="mt-3">
        <button class="btn btn-danger" id="btn-restaurar" onclick="Backup.restaurar()" disabled>
            <i class="bi bi-exclamation-triangle-fill"></i> Restaurar backup
        </button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-archive-fill"></i> Backups guardados en el servidor</div>
        <div class="text-muted text-sm" id="total-tamano">—</div>
    </div>
    <div id="lista-backups">
        <div class="empty-state"><div class="spinner"></div></div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>