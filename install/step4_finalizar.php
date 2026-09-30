<?php
/**
 * IPV - Paso 4: Finalizar instalación
 * Genera config/database.php, config/config.php, crea storage/,
 * copia public.pem y guarda el INSTALL_SEED en la BD.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/install_error.log');

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_helpers_bd.php';

// Verificar que se haya completado paso 3
if (empty($_SESSION['install']['admin'])) {
    header('Location: step3_admin.php');
    exit;
}

$bd      = $_SESSION['install']['bd'];
$admin   = $_SESSION['install']['admin'];
$negocio = $_SESSION['install']['negocio'];

$errores = [];
$done    = isset($_GET['done']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ============================================================
        // 0) Crear carpetas de storage con protección
        // ============================================================
        $carpetasStorage = ['storage', 'storage/backups', 'storage/logs', 'storage/reportes'];

        foreach ($carpetasStorage as $carpeta) {
            $ruta = ROOT_PATH . '/' . $carpeta;
            if (!is_dir($ruta)) @mkdir($ruta, 0755, true);

            $htaccess = $ruta . '/.htaccess';
            if (!file_exists($htaccess)) {
                $contenidoHtaccess = "# Denegar acceso directo\n"
                    . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
                    . "<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n";
                @file_put_contents($htaccess, $contenidoHtaccess);
            }

            $index = $ruta . '/index.php';
            if (!file_exists($index)) {
                @file_put_contents($index, "<?php http_response_code(403); exit;\n");
            }
        }

        // ============================================================
        // 1) Asegurar carpeta /config
        // ============================================================
        if (!is_dir(CONFIG_PATH)) {
            if (!@mkdir(CONFIG_PATH, 0755, true)) {
                throw new Exception('No se pudo crear la carpeta /config. Verifica permisos.');
            }
        }
        if (!is_writable(CONFIG_PATH)) {
            throw new Exception('La carpeta /config no tiene permisos de escritura.');
        }

        // ============================================================
        // 2) Generar config/database.php (SOLO credenciales del usuario dedicado)
        // ============================================================
        $db_config = "<?php\n"
            . "/**\n"
            . " * IPV - Configuracion de base de datos\n"
            . " * Generado automaticamente por el instalador\n"
            . " * Fecha: " . date('Y-m-d H:i:s') . "\n"
            . " *\n"
            . " * NOTA: La aplicación usa un usuario dedicado con permisos\n"
            . " *       solo sobre esta base de datos. NO usa root.\n"
            . " */\n\n"
            . "define('DB_HOST', " . var_export((string)$bd['host'], true) . ");\n"
            . "define('DB_PORT', " . var_export((string)$bd['puerto'], true) . ");\n"
            . "define('DB_USER', " . var_export((string)$bd['app_usuario'], true) . ");\n"
            . "define('DB_PASS', " . var_export((string)$bd['app_password'], true) . ");\n"
            . "define('DB_NAME', " . var_export((string)$bd['basedatos'], true) . ");\n"
            . "define('DB_CHARSET', 'utf8mb4');\n";

        $r1 = @file_put_contents(CONFIG_PATH . '/database.php', $db_config);
        if ($r1 === false) throw new Exception('No se pudo escribir config/database.php');

        @chmod(CONFIG_PATH . '/database.php', 0640);

        // ============================================================
        // 3) Conectar con el USUARIO DEDICADO y guardar INSTALL_SEED
        // ============================================================
        $installSeed = bin2hex(random_bytes(32));

        $pdo = conectarApp(
            $bd['host'],
            $bd['puerto'],
            $bd['basedatos'],
            $bd['app_usuario'],
            $bd['app_password']
        );

        $existeTabla = $pdo->query("
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = " . $pdo->quote($bd['basedatos']) . "
              AND table_name = 'configuracion'
        ")->fetchColumn();

        if (!$existeTabla) {
            throw new Exception('La tabla configuracion no existe. Verifica que schema.sql se ejecutó.');
        }

        $existeSeed = $pdo->prepare("SELECT COUNT(*) FROM configuracion WHERE clave = 'install_seed'");
        $existeSeed->execute();

        if ($existeSeed->fetchColumn() > 0) {
            $pdo->prepare("UPDATE configuracion SET valor = ?, updated_at = NOW() WHERE clave = 'install_seed'")
                ->execute([$installSeed]);
        } else {
            $pdo->prepare("
                INSERT INTO configuracion (clave, valor, tipo, categoria, descripcion)
                VALUES ('install_seed', ?, 'texto', 'licencia', 'Semilla única de instalación')
            ")->execute([$installSeed]);
        }

        // ============================================================
        // 4) Detectar URL base
        // ============================================================
        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
        );
        $protocol = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/ipv/install');
        $basePath = preg_replace('#/install/?$#', '', $scriptDir);
        $basePath = rtrim($basePath, '/') . '/';

        if ($basePath === '/' || $basePath === '') {
            $basePath = '/';
        } elseif ($basePath[0] !== '/') {
            $basePath = '/' . $basePath;
        }

        $base_url = $protocol . '://' . $host . $basePath;

        // ============================================================
        // 5) Generar config/config.php
        // ============================================================
        $app_config = "<?php\n"
            . "/**\n"
            . " * IPV - Configuracion general del sistema\n"
            . " * Generado automaticamente por el instalador\n"
            . " */\n\n"
            . "// Entorno\n"
            . "define('APP_ENV', 'production');\n"
            . "define('APP_DEBUG', false);\n"
            . "define('APP_VERSION', '1.2.0');\n\n"
            . "// URL base\n"
            . "define('BASE_URL', " . var_export($base_url, true) . ");\n\n"
            . "// Negocio\n"
            . "define('NEGOCIO_NOMBRE', " . var_export((string)$negocio['nombre'], true) . ");\n"
            . "define('MONEDA_CODIGO', " . var_export((string)$negocio['moneda_codigo'], true) . ");\n"
            . "define('MONEDA_SIMBOLO', " . var_export((string)$negocio['moneda_simbolo'], true) . ");\n"
            . "define('ZONA_HORARIA', " . var_export((string)$negocio['zona_horaria'], true) . ");\n"
            . "define('FORMATO_FECHA', " . var_export((string)$negocio['formato_fecha'], true) . ");\n\n"
            . "// Seguridad\n"
            . "define('SESSION_LIFETIME', 7200);\n"
            . "define('MAX_LOGIN_ATTEMPTS', 5);\n"
            . "define('LOGIN_BLOCK_MINUTES', 30);\n\n"
            . "// Instalacion\n"
            . "define('INSTALL_WITH_DEMO', " . var_export((bool)($bd['cargar_demo'] ?? false), true) . ");\n"
            . "define('INSTALL_DATE', " . var_export(date('Y-m-d H:i:s'), true) . ");\n\n"
            . "// Zona horaria\n"
            . "date_default_timezone_set(ZONA_HORARIA);\n\n"
            . "// Logs\n"
            . "@ini_set('log_errors', 1);\n"
            . "@ini_set('error_log', __DIR__ . '/../storage/logs/error.log');\n";

        $r2 = @file_put_contents(CONFIG_PATH . '/config.php', $app_config);
        if ($r2 === false) throw new Exception('No se pudo escribir config/config.php');

        @chmod(CONFIG_PATH . '/config.php', 0644);

        // ============================================================
        // 6) Copiar public.pem al config
        // ============================================================
        $publicKeyOrigen  = __DIR__ . '/public.pem';
        $publicKeyDestino = CONFIG_PATH . '/public.pem';

        if (!file_exists($publicKeyOrigen)) {
            throw new Exception('No se encontró install/public.pem.');
        }

        if (!@copy($publicKeyOrigen, $publicKeyDestino)) {
            throw new Exception('No se pudo copiar public.pem a config/.');
        }

        if (!file_exists($publicKeyDestino) || filesize($publicKeyDestino) === 0) {
            throw new Exception('La copia de public.pem quedó vacía.');
        }

        @chmod($publicKeyDestino, 0644);

        // ============================================================
        // 7) Crear archivo .lock
        // ============================================================
        $lockContent = "Instalado el " . date('Y-m-d H:i:s') . "\n"
            . "Con datos demo: " . ($bd['cargar_demo'] ? 'Sí' : 'No') . "\n"
            . "Admin: " . $admin['email'] . "\n"
            . "BD: " . $bd['basedatos'] . " (usuario: " . $bd['app_usuario'] . "@" . $bd['app_host'] . ")\n"
            . "URL base: " . $base_url . "\n"
            . "PHP: " . PHP_VERSION . "\n";

        @file_put_contents(LOCK_FILE, $lockContent);

        $_SESSION['install_done'] = true;

        header('Location: step4_finalizar.php?done=1');
        exit;

    } catch (Throwable $e) {
        $errores[] = $e->getMessage();
        error_log('IPV Install Error: ' . $e->getMessage());
    }
}

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

    <?php if ($done): ?>
        <div class="card center">
            <i class="bi bi-check-circle-fill icon-success big"></i>
            <h1>¡Instalación completada!</h1>
            <p class="muted">El sistema IPV está listo para usarse.</p>

            <div class="resumen">
                <div class="resumen-item">
                    <i class="bi bi-person-circle"></i>
                    <div>
                        <strong>Administrador</strong>
                        <span><?= h($admin['email']) ?></span>
                    </div>
                </div>
                <div class="resumen-item">
                    <i class="bi bi-shop"></i>
                    <div>
                        <strong>Negocio</strong>
                        <span><?= h($negocio['nombre']) ?></span>
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
            </div>

            <!-- ⭐ Bloque destacado con la contraseña del usuario dedicado -->
            <div class="alert alert-warning" style="text-align:left;">
                <i class="bi bi-key-fill"></i>
                <div style="flex:1;">
                    <strong>Guarda esta contraseña.</strong><br>
                    La aplicación usará estas credenciales para conectarse a la base de datos.
                    Si necesitas conectarte manualmente a MySQL, las necesitarás.
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

            <?php if ($bd['cargar_demo'] ?? false): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>Recuerda:</strong>
                    Se cargaron usuarios demo. <strong>Elimínalos o desactívalos</strong> antes de usar el sistema en producción.
                </div>
            <?php endif; ?>

            <div class="alert alert-info">
                <i class="bi bi-shield-lock-fill"></i>
                <div>
                    El usuario administrativo de MySQL <strong>no se guardó</strong> en la aplicación.
                    Solo el usuario dedicado <code><?= h($bd['app_usuario']) ?></code> se usará en producción.
                </div>
            </div>

            <div class="actions center-actions">
                <a href="../views/login.php" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right"></i> Ir al login
                </a>
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

    <?php else: ?>
        <div class="card">
            <h1><i class="bi bi-flag-fill"></i> Paso 4: Finalizar instalación</h1>
            <p class="muted">Todo está listo. Al pulsar el botón se generarán los archivos de configuración.</p>

            <?php if (!empty($errores)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        <strong>Errores detectados:</strong>
                        <ul>
                            <?php foreach ($errores as $e): ?>
                                <li><?= h($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <div class="resumen">
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
                    <i class="bi bi-person-circle"></i>
                    <div>
                        <strong>Administrador</strong>
                        <span><?= h($admin['email']) ?></span>
                    </div>
                </div>
                <div class="resumen-item">
                    <i class="bi bi-shop"></i>
                    <div>
                        <strong>Negocio</strong>
                        <span><?= h($negocio['nombre']) ?></span>
                    </div>
                </div>
            </div>

            <form method="POST">
                <div class="actions">
                    <a href="step3_admin.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Atrás
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg"></i> Finalizar instalación
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/_footer.php'; ?>