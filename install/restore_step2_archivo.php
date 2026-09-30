<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_helpers_bd.php';

// Requerir que se haya pasado por los requisitos
if (($_SESSION['install']['modo'] ?? '') !== 'restore') {
    header('Location: restore_step1_requisitos.php');
    exit;
}

$errores = [];
$esPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

$valores = [
    'host'           => $_POST['host']         ?? 'localhost',
    'puerto'         => $_POST['puerto']       ?? '3306',
    'usuario'        => $_POST['usuario']      ?? 'root',
    'password'       => $_POST['password']     ?? '',
    'basedatos'      => $_POST['basedatos']    ?? '',
    'app_usuario'    => $_POST['app_usuario']  ?? 'ipv_user',
    'app_host'       => $_POST['app_host']     ?? 'localhost',
    'app_password'   => $_POST['app_password'] ?? '',
    'crear_bd'       => $esPost ? isset($_POST['crear_bd']) : true,
    'permitir_drop'  => $esPost ? isset($_POST['permitir_drop']) : false,
];

if (empty($valores['app_password'])) {
    $valores['app_password'] = generarPasswordBD(24);
}

// Regenerar contraseña
if ($esPost && ($_POST['accion'] ?? '') === 'regenerar') {
    $valores['app_password'] = generarPasswordBD(24);
    $esPost = false;
}

if ($esPost) {
    // ── Validaciones básicas ──
    if (empty($valores['host']))       $errores[] = 'El host es obligatorio.';
    if (empty($valores['usuario']))    $errores[] = 'El usuario administrativo es obligatorio.';
    if (empty($valores['basedatos']))  $errores[] = 'El nombre de la base de datos es obligatorio.';
    if (empty($valores['app_usuario'])) $errores[] = 'El usuario dedicado es obligatorio.';

    try {
        validarNombreBD($valores['basedatos']);
        validarNombreUsuarioBD($valores['app_usuario']);
        validarHostUsuario($valores['app_host']);
    } catch (Throwable $e) {
        $errores[] = $e->getMessage();
    }

    // ── Validar archivo subido ──
    if (!isset($_FILES['archivo'])) {
        $errores[] = 'No se recibió ningún archivo.';
    } else {
        $a = $_FILES['archivo'];

        if ($a['error'] !== UPLOAD_ERR_OK) {
            $errores[] = 'Error al subir el archivo (código ' . $a['error'] . ').';
        } elseif ($a['size'] > 50 * 1024 * 1024) {
            $errores[] = 'El archivo supera los 50 MB.';
        } else {
            $ext = strtolower(pathinfo($a['name'], PATHINFO_EXTENSION));
            if ($ext !== 'sql') {
                $errores[] = 'Solo se aceptan archivos .sql';
            }

            // Validar MIME real
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $a['tmp_name']);
                finfo_close($finfo);

                $mimesValidos = ['text/plain', 'application/sql', 'application/x-sql', 'text/x-sql', 'application/octet-stream'];
                if (!in_array($mime, $mimesValidos, true)) {
                    $errores[] = 'El archivo no parece un .sql válido (MIME: ' . $mime . ').';
                }
            }

            // Verificar que no tenga DROP DATABASE si no está permitido
            if (empty($errores) && !$valores['permitir_drop']) {
                $contenido = file_get_contents($a['tmp_name']);
                if (preg_match('/\bDROP\s+DATABASE\b/i', $contenido)) {
                    $errores[] = 'El archivo contiene sentencias DROP DATABASE. Marca la casilla correspondiente para permitirlo.';
                }
            }
        }
    }

    // ── Mover archivo a ubicación temporal ──
    if (empty($errores)) {
        $tmpDir = sys_get_temp_dir();
        $tmpFile = $tmpDir . '/ipv_restore_' . time() . '_' . bin2hex(random_bytes(4)) . '.sql';

        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $tmpFile)) {
            $errores[] = 'No se pudo procesar el archivo subido.';
        } else {
            $_SESSION['install']['restore'] = [
                'archivo_tmp'   => $tmpFile,
                'archivo_nombre'=> $_FILES['archivo']['name'],
                'archivo_size'  => $_FILES['archivo']['size'],
                'permitir_drop' => $valores['permitir_drop'],
            ];
        }
    }

    // ── Guardar credenciales en sesión ──
    if (empty($errores)) {
        $_SESSION['install']['bd'] = [
            'host'          => $valores['host'],
            'puerto'        => $valores['puerto'],
            'usuario'       => $valores['usuario'],
            'password'      => $valores['password'],
            'basedatos'     => $valores['basedatos'],
            'app_usuario'   => $valores['app_usuario'],
            'app_host'      => $valores['app_host'],
            'app_password'  => $valores['app_password'],
            'crear_bd'      => $valores['crear_bd'],
        ];

        $_SESSION['install']['paso'] = 3;

        header('Location: restore_step3_restaurar.php');
        exit;
    }
}

include __DIR__ . '/_header.php';
?>

<div class="container">
    <div class="progress">
        <div class="progress-step done"><i class="bi bi-check-lg"></i></div>
        <div class="progress-line done"></div>
        <div class="progress-step active">2</div>
        <div class="progress-line"></div>
        <div class="progress-step">3</div>
        <div class="progress-line"></div>
        <div class="progress-step">4</div>
    </div>

    <div class="card">
        <h1><i class="bi bi-upload"></i> Restaurar backup — Paso 2: Archivo y credenciales</h1>
        <p class="muted">
            Sube el archivo <code>.sql</code> generado por IPV y las credenciales de MySQL.
        </p>

        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    <strong>Corrige los siguientes errores:</strong>
                    <ul>
                        <?php foreach ($errores as $e): ?>
                            <li><?= h($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="form" autocomplete="off">

            <!-- Archivo SQL -->
            <h2><i class="bi bi-file-earmark-zip"></i> Archivo de backup</h2>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    <strong>⚠️ Esta operación reemplaza la base de datos actual.</strong>
                    Si ya existe una BD con el mismo nombre, sus datos serán sobrescritos.
                    Tamaño máximo: <strong>50 MB</strong>.
                </div>
            </div>

            <div class="field">
                <label>Archivo <code>.sql</code></label>
                <input type="file" name="archivo" accept=".sql" required>
                <small class="muted">Debe ser un backup generado por IPV.</small>
            </div>

            <div class="field">
                <label class="checkbox">
                    <input type="checkbox" name="permitir_drop" value="1" <?= $valores['permitir_drop'] ? 'checked' : '' ?>>
                    <span>Permitir sentencias <code>DROP DATABASE</code> en el archivo</span>
                </label>
                <small class="muted">
                    Solo marca esto si estás seguro. Por defecto se bloquean para evitar borrados accidentales.
                </small>
            </div>

            <!-- Usuario administrativo -->
            <h2><i class="bi bi-shield-lock-fill"></i> Usuario administrativo de MySQL</h2>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    Se usa <strong>solo para restaurar</strong>. Debe tener permisos de
                    <code>CREATE DATABASE</code>, <code>CREATE USER</code> y <code>GRANT OPTION</code>.
                    <strong>No se guardará en la aplicación.</strong>
                </div>
            </div>

            <div class="grid grid-2">
                <div class="field">
                    <label>Host</label>
                    <input type="text" name="host" value="<?= h($valores['host']) ?>" required>
                </div>
                <div class="field">
                    <label>Puerto</label>
                    <input type="text" name="puerto" value="<?= h($valores['puerto']) ?>" required>
                </div>
                <div class="field">
                    <label>Usuario</label>
                    <input type="text" name="usuario" value="<?= h($valores['usuario']) ?>" required>
                </div>
                <div class="field">
                    <label>Contraseña</label>
                    <div class="input-group">
                        <input type="password" name="password" id="bd_password" value="<?= h($valores['password']) ?>">
                        <button type="button" class="btn-toggle-pass" onclick="togglePass('bd_password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Base de datos destino -->
            <h2><i class="bi bi-database-fill"></i> Base de datos destino</h2>
            <div class="grid grid-2">
                <div class="field">
                    <label>Nombre de la base de datos</label>
                    <input type="text" name="basedatos" value="<?= h($valores['basedatos']) ?>" required>
                    <small class="muted">Debe coincidir con el nombre que tenía la BD original.</small>
                </div>
                <div class="field">
                    <label class="checkbox" style="margin-top:24px;">
                        <input type="checkbox" name="crear_bd" value="1" <?= $valores['crear_bd'] ? 'checked' : '' ?>>
                        <span>Crear la BD si no existe</span>
                    </label>
                </div>
            </div>

            <!-- Usuario dedicado -->
            <h2><i class="bi bi-person-badge-fill"></i> Usuario dedicado para IPV</h2>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    La aplicación usará <strong>únicamente este usuario</strong> en producción.
                </div>
            </div>

            <div class="grid grid-2">
                <div class="field">
                    <label>Nombre de usuario</label>
                    <input type="text" name="app_usuario" value="<?= h($valores['app_usuario']) ?>" required
                           pattern="[A-Za-z0-9_]+" maxlength="32">
                </div>
                <div class="field">
                    <label>Host del usuario</label>
                    <select name="app_host">
                        <option value="localhost" <?= $valores['app_host'] === 'localhost' ? 'selected' : '' ?>>localhost</option>
                        <option value="127.0.0.1" <?= $valores['app_host'] === '127.0.0.1' ? 'selected' : '' ?>>127.0.0.1</option>
                        <option value="%" <?= $valores['app_host'] === '%' ? 'selected' : '' ?>>% (cualquier host)</option>
                    </select>
                </div>
                <div class="field grid-full">
                    <label>Contraseña generada automáticamente</label>
                    <div class="input-group">
                        <input type="text" name="app_password" id="app_password" value="<?= h($valores['app_password']) ?>" readonly>
                        <button type="button" class="btn-toggle-pass" onclick="copiarPass()" title="Copiar">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                    <small class="muted">
                        <button type="submit" name="accion" value="regenerar" class="btn-link"
                                style="background:none;border:none;color:var(--primary);cursor:pointer;padding:0;font:inherit;text-decoration:underline;">
                            Regenerar contraseña
                        </button>
                    </small>
                </div>
            </div>

            <div class="actions">
                <a href="restore_step1_requisitos.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Atrás
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-arrow-right"></i> Restaurar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePass(id, btn) {
    const input = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

function copiarPass() {
    const input = document.getElementById('app_password');
    input.select();
    input.setSelectionRange(0, 999999);
    try {
        document.execCommand('copy');
        const btn = event.currentTarget;
        const icon = btn.querySelector('i');
        const old = icon.className;
        icon.className = 'bi bi-check-lg';
        setTimeout(() => icon.className = old, 1500);
    } catch (e) {
        alert('No se pudo copiar. Selecciona y copia manualmente.');
    }
}
</script>

<?php include __DIR__ . '/_footer.php'; ?>