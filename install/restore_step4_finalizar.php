<?php
require_once __DIR__ . '/_bootstrap.php';

// Verificar que se haya completado el paso 3
if (empty($_SESSION['install']['restore_resultado'])) {
    header('Location: restore_step1_requisitos.php');
    exit;
}

$bd       = $_SESSION['install']['bd'];
$res      = $_SESSION['install']['restore_resultado'];

include __DIR__ . '/_header.php';
?>

<div class="container">
    <div class="progress">
        <div class="progress-step done"><i class="bi bi-check-lg"></i></div>
        <div class="progress-line done"></div>
        <div class="progress-step done"><i class="bi bi-check-lg"></i></div>
        <div class="progress-line done"></div>
        <div class="progress-step done"><i class="bi bi-check-lg"></i></div>
        <div class="progress-line done"></div>
        <div class="progress-step active">4</div>
    </div>

    <div class="card center">
        <i class="bi bi-check-circle-fill icon-success big"></i>
        <h1>¡Restauración completada!</h1>
        <p class="muted">El sistema IPV está listo para usarse con los datos restaurados.</p>

        <div class="resumen">
            <div class="resumen-item">
                <i class="bi bi-file-earmark-zip"></i>
                <div>
                    <strong>Archivo restaurado</strong>
                    <span><?= h($res['archivo']) ?></span>
                </div>
            </div>
            <div class="resumen-item">
                <i class="bi bi-database-fill"></i>
                <div>
                    <strong>Base de datos</strong>
                    <span><?= h($bd['basedatos']) ?> @ <?= h($bd['host']) ?></span>
                </div>
            </div>
            <div class="resumen-item">
                <i class="bi bi-person-badge-fill"></i>
                <div>
                    <strong>Usuario de la app</strong>
                    <span><?= h($bd['app_usuario']) ?>@<?= h($bd['app_host']) ?></span>
                </div>
            </div>
            <div class="resumen-item">
                <i class="bi bi-table"></i>
                <div>
                    <strong>Tablas restauradas</strong>
                    <span><?= (int)$res['tablas'] ?></span>
                </div>
            </div>
            <div class="resumen-item">
                <i class="bi bi-code-square"></i>
                <div>
                    <strong>Sentencias ejecutadas</strong>
                    <span><?= (int)$res['sentencias'] ?></span>
                </div>
            </div>
            <div class="resumen-item">
                <i class="bi bi-shield-check"></i>
                <div>
                    <strong>Install seed</strong>
                    <span>
                        <?php if ($res['seed_generado']): ?>
                            <span style="color:var(--warning);">Generado nuevo</span>
                        <?php else: ?>
                            <span style="color:var(--success);">Respetado del backup</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- ⭐ Bloque destacado con la contraseña del usuario dedicado -->
        <div class="alert alert-warning" style="text-align:left;">
            <i class="bi bi-key-fill"></i>
            <div style="flex:1;">
                <strong>Guarda esta contraseña.</strong><br>
                La aplicación usará estas credenciales para conectarse a la base de datos.
                <div style="margin-top:10px;display:flex;gap:6px;">
                    <input type="text" id="cred-app-pass"
                           value="<?= h($bd['app_password']) ?>"
                           readonly
                           style="font-family:var(--font-mono);font-size:13px;flex:1;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="copiarCredenciales()">
                        <i class="bi bi-clipboard"></i> Copiar
                    </button>
                </div>
                <div style="margin-top:8px;font-size:12px;color:var(--text-muted);">
                    <strong>Usuario:</strong> <?= h($bd['app_usuario']) ?>@<?= h($bd['app_host']) ?>
                    &nbsp;·&nbsp;
                    <strong>BD:</strong> <?= h($bd['basedatos']) ?>
                </div>
            </div>
        </div>

        <?php if ($res['seed_generado']): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    <strong>Aviso sobre licencia:</strong>
                    El backup no contenía un <code>install_seed</code>, así que se generó uno nuevo.
                    Si tenías una licencia activa en el servidor original, <strong>dejará de ser válida</strong>
                    aquí. Necesitarás reactivar la licencia con el nuevo ID de instalación.
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <i class="bi bi-shield-check"></i>
                <div>
                    El <code>install_seed</code> del backup fue respetado.
                    Si la licencia seguía vigente, debería seguir funcionando sin cambios.
                </div>
            </div>
        <?php endif; ?>

        <div class="alert alert-info">
            <i class="bi bi-shield-lock-fill"></i>
            <div>
                El usuario administrativo de MySQL <strong>no se guardó</strong> en la aplicación.
                Solo el usuario dedicado <code><?= h($bd['app_usuario']) ?></code> se usará en producción.
            </div>
        </div>

        <div class="alert alert-info">
            <i class="bi bi-info-circle-fill"></i>
            <div>
                La carpeta <code>install/</code> se eliminará automáticamente al entrar al login por primera vez.
            </div>
        </div>

        <div class="actions center-actions">
            <a href="../views/login.php" class="btn btn-primary">
                <i class="bi bi-box-arrow-in-right"></i> Ir al login
            </a>
        </div>
    </div>
</div>

<script>
function copiarCredenciales() {
    const input = document.getElementById('cred-app-pass');
    input.select();
    input.setSelectionRange(0, 999999);
    try {
        document.execCommand('copy');
        alert('Contraseña copiada al portapapeles.');
    } catch (e) {
        alert('No se pudo copiar. Selecciona y copia manualmente.');
    }
}
</script>

<?php
// Limpiar sesión de instalación una vez finalizado
// (opcional: dejar los datos por si el usuario recarga)
// $_SESSION['install'] = [];
include __DIR__ . '/_footer.php';
?>