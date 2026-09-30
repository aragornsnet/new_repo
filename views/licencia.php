<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Fingerprint.php';
require_once __DIR__ . '/../core/Licencia.php';
require_once __DIR__ . '/../core/helpers.php';

Auth::iniciarSesion();

if (!Auth::check()) {
    redirigir('views/login.php');
}

$estado = Licencia::verificar();
$expirada = !$estado['valida'];
$tieneLicenciaActiva = ($estado['tipo'] === 'licencia' && $estado['valida']);

$tituloPagina = 'Licencia';
$subtituloPagina = $expirada ? 'Activación requerida' : 'Estado de tu licencia';
$paginaActiva = 'licencia';
$scriptsExtra = [
    BASE_URL . 'public/js/licencia.js',
];

require __DIR__ . '/layouts/header.php';
?>

<div class="container" style="max-width: 800px; margin: 0 auto;">

    <?php if ($expirada): ?>
        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- CASO 1: EXPIRADA -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="card" style="text-align:center;padding:40px 32px;">
            <i class="bi bi-shield-lock-fill" style="font-size:72px;color:var(--warning);"></i>
            <h1 style="margin:16px 0 8px;">Período de prueba finalizado</h1>
            <p class="text-muted" style="font-size:15px;max-width:500px;margin:0 auto;">
                El período de prueba ha terminado. Para continuar usando IPV, necesitas activar una licencia.
            </p>
        </div>
    <?php else: ?>
        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- CASO 2: VÁLIDA -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="page-header">
            <div>
                <h2 class="page-title">🛡️ Licencia</h2>
                <p class="page-subtitle">Estado actual de tu licencia de uso</p>
            </div>
        </div>

        <?php if ($estado['tipo'] === 'trial'): ?>
            <?php
            $trialDias = 30; // FIJO
            $fechaVencimiento = date('Y-m-d H:i:s', strtotime(INSTALL_DATE . ' +' . $trialDias . ' days'));
            $diasRestantes = (int)$estado['dias_restantes'];
            ?>
            <div class="card" style="background:linear-gradient(135deg, var(--info) 0%, #0369a1 100%);color:#fff;border:none;margin-bottom:24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                    <div>
                        <div style="font-size:13px;opacity:.9;margin-bottom:4px;">
                            <i class="bi bi-hourglass-split"></i> Período de prueba activo
                        </div>
                        <div style="font-size:32px;font-weight:700;">
                            <?= $diasRestantes ?> día<?= $diasRestantes !== 1 ? 's' : '' ?> restante<?= $diasRestantes !== 1 ? 's' : '' ?>
                        </div>
                        <div style="font-size:15px;opacity:.95;margin-top:8px;">
                            <i class="bi bi-calendar-x"></i>
                            Caduca el <strong><?= fecha($fechaVencimiento, 'd/m/Y') ?></strong>
                            a las <strong><?= date('H:i', strtotime($fechaVencimiento)) ?></strong>
                        </div>
                    </div>
                    <i class="bi bi-hourglass-split" style="font-size:80px;opacity:.3;"></i>
                </div>
            </div>
        <?php else: ?>
            <?php
            $diasRestantes = $estado['dias_restantes'] ?? -1;
            $perpetua = ($diasRestantes === -1);
            ?>
            <div class="card" style="background:linear-gradient(135deg, var(--success) 0%, #15803d 100%);color:#fff;border:none;margin-bottom:24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                    <div>
                        <div style="font-size:13px;opacity:.9;margin-bottom:4px;">
                            <i class="bi bi-patch-check-fill"></i> Licencia activa
                        </div>
                        <div style="font-size:26px;font-weight:700;">
                            <?= !empty($estado['datos']['cliente']) ? h($estado['datos']['cliente']) : 'Cliente registrado' ?>
                        </div>
                        <div style="font-size:15px;opacity:.95;margin-top:8px;">
                            <?php if ($perpetua): ?>
                                <i class="bi bi-infinity"></i>
                                <strong>Licencia perpetua</strong> (sin fecha de expiración)
                            <?php else: ?>
                                <i class="bi bi-calendar-check"></i>
                                Expira el <strong><?= fecha($estado['datos']['expira'], 'd/m/Y') ?></strong>
                                <span style="opacity:.85;">· <?= (int)$diasRestantes ?> día<?= $diasRestantes !== 1 ? 's' : '' ?> restante<?= $diasRestantes !== 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <i class="bi bi-patch-check-fill" style="font-size:80px;opacity:.3;"></i>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-info-circle-fill"></i> Información de la licencia</div>
            </div>
            <div class="grid-2">
                <div>
                    <div class="text-xs text-muted">Tipo</div>
                    <div style="font-weight:600;">
                        <?= $estado['tipo'] === 'trial' ? 'Prueba' : 'Licencia completa' ?>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted">Estado</div>
                    <div>
                        <span class="badge badge-<?= $estado['tipo'] === 'trial' ? 'info' : 'success' ?>">
                            <?= $estado['valida'] ? 'Válida' : 'Inválida' ?>
                        </span>
                    </div>
                </div>
                <?php if (!empty($estado['datos']['emitida'])): ?>
                    <div>
                        <div class="text-xs text-muted">Emitida</div>
                        <div style="font-weight:600;"><?= fecha($estado['datos']['emitida'], 'd/m/Y H:i') ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($estado['datos']['expira'])): ?>
                    <div>
                        <div class="text-xs text-muted">Expira</div>
                        <div style="font-weight:600;"><?= fecha($estado['datos']['expira'], 'd/m/Y H:i') ?></div>
                    </div>
                <?php endif; ?>
                <div>
                    <div class="text-xs text-muted">Fecha de instalación</div>
                    <div style="font-weight:600;"><?= fecha(INSTALL_DATE, 'd/m/Y H:i') ?></div>
                </div>
                <?php if (defined('TRIAL_DAYS')): ?>
                    <div>
                        <div class="text-xs text-muted">Días de prueba</div>
                        <div style="font-weight:600;"><?= (int)TRIAL_DAYS ?> días</div>
                    </div>
                <?php endif; ?>
                <div style="grid-column: 1 / -1;">
                    <div class="text-xs text-muted">ID de instalación</div>
                    <div style="font-family:var(--font-mono);font-size:11px;word-break:break-all;background:var(--surface-2);padding:10px;border-radius:var(--radius-md);margin-top:4px;">
                        <?= h(Licencia::idInstalacion()) ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- SECCIÓN DE ACTIVACIÓN -->
    <!-- Se muestra si: expirada O en prueba -->
    <!-- Se OCULTA si: licencia activa (se muestra botón Renovar) -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <?php if ($expirada || $estado['tipo'] === 'trial'): ?>
        <div class="card" id="card-activacion">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-key-fill"></i>
                    <?= $expirada ? 'Activar licencia' : 'Activar licencia ahora' ?>
                </div>
            </div>

            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div class="alert-body">
                    <strong>Paso 1:</strong> Copia tu ID de instalación y envíalo al vendedor.<br>
                    <strong>Paso 2:</strong> Recibirás un código de licencia.<br>
                    <strong>Paso 3:</strong> Pega el código aquí y activa.
                </div>
            </div>

            <div class="field mb-4">
                <label>Tu ID de instalación</label>
                <div class="input-group">
                    <input type="text" id="install-id" readonly
                           value="<?= h(Licencia::idInstalacion()) ?>"
                           style="font-family:var(--font-mono);font-size:12px;padding-right:44px;">
                    <button type="button" class="btn-toggle-pass" onclick="Licencia.copiarInstallId()" title="Copiar al portapapeles">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
                <div class="help">Envía este ID al vendedor para recibir tu licencia.</div>
            </div>

            <div class="field mb-4">
                <label for="licencia-input">Código de licencia <span class="req">*</span></label>
                <textarea id="licencia-input" placeholder="Pega aquí el código de licencia que recibiste..."
                          style="min-height:120px;font-family:var(--font-mono);font-size:12px;"></textarea>
                <div class="help">El código es una cadena larga que empieza por "eyJ..."</div>
            </div>

            <button class="btn btn-primary btn-block btn-lg" onclick="Licencia.activar()" id="btn-activar">
                <i class="bi bi-check-circle-fill"></i>
                Activar licencia
            </button>
        </div>
    <?php else: ?>
        <!-- Licencia activa: aviso con botón "Renovar" -->
        <div class="card" id="card-licencia-activa" style="background:var(--surface-2);border:1px dashed var(--border);">
            <div style="text-align:center;padding:16px;">
                <i class="bi bi-shield-check" style="font-size:32px;color:var(--success);"></i>
                <div style="margin-top:8px;font-weight:600;color:var(--text);">
                    Tu licencia está activa y funcionando correctamente.
                </div>
                <div class="text-muted text-sm" style="margin-top:4px;">
                    <?php if (($estado['dias_restantes'] ?? -1) === -1): ?>
                        Licencia perpetua. No requiere renovación.
                    <?php else: ?>
                        Si necesitas renovarla antes de que expire, pulsa el botón de abajo.
                    <?php endif; ?>
                </div>

                <?php if (($estado['dias_restantes'] ?? -1) !== -1): ?>
                    <button class="btn btn-secondary mt-3" onclick="Licencia.mostrarFormRenovar()">
                        <i class="bi bi-arrow-clockwise"></i> Renovar licencia
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulario de renovación (oculto por defecto) -->
        <div class="card" id="card-activacion" style="display:none;">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-key-fill"></i> Renovar licencia
                </div>
            </div>

            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div class="alert-body">
                    Pega aquí el nuevo código de licencia que te enviamos para extender tu licencia.
                </div>
            </div>

            <div class="field mb-4">
                <label>Tu ID de instalación</label>
                <div class="input-group">
                    <input type="text" id="install-id" readonly
                           value="<?= h(Licencia::idInstalacion()) ?>"
                           style="font-family:var(--font-mono);font-size:12px;padding-right:44px;">
                    <button type="button" class="btn-toggle-pass" onclick="Licencia.copiarInstallId()" title="Copiar al portapapeles">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
                <div class="help">Envía este ID al vendedor si necesitas renovar.</div>
            </div>

            <div class="field mb-4">
                <label for="licencia-input">Código de licencia <span class="req">*</span></label>
                <textarea id="licencia-input" placeholder="Pega aquí el código de licencia que recibiste..."
                          style="min-height:120px;font-family:var(--font-mono);font-size:12px;"></textarea>
            </div>

            <div style="display:flex;gap:8px;">
                <button class="btn btn-secondary" onclick="Licencia.ocultarFormRenovar()">
                    Cancelar
                </button>
                <button class="btn btn-primary" style="flex:1;" onclick="Licencia.activar()" id="btn-activar">
                    <i class="bi bi-check-circle-fill"></i>
                    Renovar licencia
                </button>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>