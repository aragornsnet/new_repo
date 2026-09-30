<?php
/**
 * IPV - Restaurar backup — Paso 3: Ejecutar restauración.
 *
 * Flujo:
 *   1. Leer datos de sesión (archivo temporal + credenciales).
 *   2. Conectar con el usuario administrativo.
 *   3. Crear/actualizar el usuario dedicado + permisos.
 *   4. Crear la BD si no existe (o usarla si ya existe).
 *   5. Ejecutar el .sql en streaming.
 *   6. Verificar estructura mínima.
 *   7. Respetar o generar install_seed.
 *   8. Crear Almacén Central si no existe.
 *   9. Generar config/database.php y config/config.php.
 *  10. Copiar public.pem.
 *  11. Crear .lock.
 *  12. Redirigir al paso 4.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/install_error.log');
set_time_limit(600);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_helpers_bd.php';

// ── Verificar estado de la sesión ──
if (($_SESSION['install']['modo'] ?? '') !== 'restore') {
    header('Location: restore_step1_requisitos.php');
    exit;
}
if (empty($_SESSION['install']['bd']) || empty($_SESSION['install']['restore']['archivo_tmp'])) {
    header('Location: restore_step2_archivo.php');
    exit;
}

$bd           = $_SESSION['install']['bd'];
$restore      = $_SESSION['install']['restore'];
$archivoTmp   = $restore['archivo_tmp'];
$permitirDrop = !empty($restore['permitir_drop']);

$errores = [];
$resultado = null;

// ── Verificar que el archivo temporal siga existiendo ──
if (!file_exists($archivoTmp)) {
    $errores[] = 'El archivo temporal se perdió. Vuelve a subirlo.';
}

if (empty($errores)) {
    try {
        // ════════════════════════════════════════════════════════
        // 1. Conectar con el usuario ADMINISTRATIVO
        // ════════════════════════════════════════════════════════
        $pdoAdmin = conectarAdmin($bd['host'], $bd['puerto'], $bd['usuario'], $bd['password']);

        // ════════════════════════════════════════════════════════
        // 2. Crear/actualizar el usuario dedicado
        // ════════════════════════════════════════════════════════
        //    Necesitamos la BD primero para GRANT. Si la BD no existe
        //    y el usuario marcó "crear_bd", la creamos aquí.
        if (!empty($bd['crear_bd'])) {
            $pdoAdmin->exec("
                CREATE DATABASE IF NOT EXISTS `{$bd['basedatos']}`
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            ");
        }

        try {
            crearUsuarioDedicado(
                $pdoAdmin,
                $bd['basedatos'],
                $bd['app_usuario'],
                $bd['app_host'],
                $bd['app_password']
            );
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, '1142') || str_contains($msg, '1227') || str_contains($msg, 'Access denied')) {
                throw new Exception(
                    'El usuario administrativo no tiene privilegios para crear usuarios o conceder permisos. ' .
                    'Necesitas un usuario con permisos CREATE USER y GRANT OPTION, o contactar con tu proveedor de hosting.'
                );
            }
            throw new Exception('No se pudo crear el usuario dedicado: ' . $msg);
        }

        // ════════════════════════════════════════════════════════
        // 3. Verificar que la BD existe y conectarse a ella
        // ════════════════════════════════════════════════════════
        $existeBD = $pdoAdmin->query("
            SELECT COUNT(*) FROM information_schema.schemata
            WHERE schema_name = " . $pdoAdmin->quote($bd['basedatos'])
        )->fetchColumn();

        if (!$existeBD) {
            throw new Exception(
                'La base de datos "' . $bd['basedatos'] . '" no existe y no se pidió crearla. ' .
                'Marca la casilla "Crear la BD si no existe" o crea la BD manualmente.'
            );
        }

        $pdoAdmin->exec("USE `{$bd['basedatos']}`");

        // ════════════════════════════════════════════════════════
        // 4. Ejecutar el .sql en streaming
        // ════════════════════════════════════════════════════════
        $stats = ejecutarSQL($pdoAdmin, $archivoTmp, true);

        // ════════════════════════════════════════════════════════
        // 5. Verificar estructura mínima
        // ════════════════════════════════════════════════════════
        $verif = verificarEstructuraBD($pdoAdmin, $bd['basedatos']);

        if (!$verif['ok']) {
            throw new Exception(
                'La restauración no generó la estructura esperada. ' .
                'Faltan tablas: ' . implode(', ', $verif['faltantes'])
            );
        }

        // ════════════════════════════════════════════════════════
        // 6. install_seed: respetar el del backup o generar uno nuevo
        // ════════════════════════════════════════════════════════
        $seedExistente = $pdoAdmin->query("
            SELECT valor FROM configuracion WHERE clave = 'install_seed' LIMIT 1
        ")->fetchColumn();

        $seedNuevo = null;
        if (!$seedExistente) {
            // No venía en el backup. Generar uno nuevo.
            $seedNuevo = bin2hex(random_bytes(32));
            $existeClave = $pdoAdmin->query("
                SELECT COUNT(*) FROM configuracion WHERE clave = 'install_seed'
            ")->fetchColumn();

            if ($existeClave) {
                $pdoAdmin->prepare("
                    UPDATE configuracion SET valor = ?, updated_at = NOW()
                    WHERE clave = 'install_seed'
                ")->execute([$seedNuevo]);
            } else {
                $pdoAdmin->prepare("
                    INSERT INTO configuracion (clave, valor, tipo, categoria, descripcion)
                    VALUES ('install_seed', ?, 'texto', 'licencia', 'Semilla única de instalación')
                ")->execute([$seedNuevo]);
            }
        } else {
            $seedNuevo = $seedExistente;
        }

        // ════════════════════════════════════════════════════════
        // 7. Verificar/crear Almacén Central y actualizar almacen_id
        // ════════════════════════════════════════════════════════
        $existeAlmacen = $pdoAdmin->query("
            SELECT id FROM puntos_venta WHERE es_almacen = 1 LIMIT 1
        ")->fetchColumn();

        if (!$existeAlmacen) {
            $pdoAdmin->prepare("
                INSERT INTO puntos_venta (nombre, direccion, telefono, activo, es_almacen)
                VALUES ('Almacén Central', NULL, NULL, 1, 1)
            ")->execute();
            $almacenId = (int) $pdoAdmin->lastInsertId();
        } else {
            $almacenId = (int) $existeAlmacen;
        }

        // Actualizar configuracion.almacen_id
        $existeClaveAlm = $pdoAdmin->query("
            SELECT COUNT(*) FROM configuracion WHERE clave = 'almacen_id'
        ")->fetchColumn();

        if ($existeClaveAlm) {
            $pdoAdmin->prepare("
                UPDATE configuracion SET valor = ? WHERE clave = 'almacen_id'
            ")->execute([$almacenId]);
        } else {
            $pdoAdmin->prepare("
                INSERT INTO configuracion (clave, valor, tipo, categoria, descripcion)
                VALUES ('almacen_id', ?, 'numero', 'almacen', 'ID del PV que actúa como almacén')
            ")->execute([$almacenId]);
        }

        // ════════════════════════════════════════════════════════
        // 8. Verificar que el usuario dedicado puede conectarse
        // ════════════════════════════════════════════════════════
        try {
            $pdoTest = conectarApp(
                $bd['host'],
                $bd['puerto'],
                $bd['basedatos'],
                $bd['app_usuario'],
                $bd['app_password']
            );
            $pdoTest->query("SELECT 1 FROM usuarios LIMIT 1")->fetch();
        } catch (Throwable $e) {
            throw new Exception(
                'El usuario dedicado se creó pero no puede conectarse. ' .
                'Comprueba el host (localhost vs 127.0.0.1) y los permisos. Detalle: ' . $e->getMessage()
            );
        }

        // ════════════════════════════════════════════════════════
        // 9. Generar config/database.php
        // ════════════════════════════════════════════════════════
        if (!is_dir(CONFIG_PATH)) {
            if (!@mkdir(CONFIG_PATH, 0755, true)) {
                throw new Exception('No se pudo crear la carpeta /config.');
            }
        }
        if (!is_writable(CONFIG_PATH)) {
            throw new Exception('La carpeta /config no tiene permisos de escritura.');
        }

        $db_config = "<?php\n"
            . "/**\n"
            . " * IPV - Configuracion de base de datos\n"
            . " * Generado automaticamente por el instalador (modo restauración)\n"
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

        if (@file_put_contents(CONFIG_PATH . '/database.php', $db_config) === false) {
            throw new Exception('No se pudo escribir config/database.php');
        }
        @chmod(CONFIG_PATH . '/database.php', 0640);

        // ════════════════════════════════════════════════════════
        // 10. Detectar URL base y generar config/config.php
        // ════════════════════════════════════════════════════════
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

        // Leer datos del negocio desde la BD restaurada (si existen)
        $negocio = [
            'nombre'          => 'Mi Negocio IPV',
            'moneda_codigo'   => 'CUP',
            'moneda_simbolo'  => '$',
            'zona_horaria'    => 'America/Havana',
            'formato_fecha'   => 'd/m/Y H:i',
        ];

        try {
            $filas = $pdoAdmin->query("
                SELECT clave, valor FROM configuracion
                WHERE clave IN ('empresa_nombre', 'moneda_codigo', 'moneda_simbolo', 'zona_horaria', 'formato_fecha')
            ")->fetchAll(PDO::FETCH_KEY_PAIR);

            if (!empty($filas['empresa_nombre']))  $negocio['nombre']         = $filas['empresa_nombre'];
            if (!empty($filas['moneda_codigo']))   $negocio['moneda_codigo']  = $filas['moneda_codigo'];
            if (!empty($filas['moneda_simbolo']))  $negocio['moneda_simbolo'] = $filas['moneda_simbolo'];
            if (!empty($filas['zona_horaria']))    $negocio['zona_horaria']   = $filas['zona_horaria'];
            if (!empty($filas['formato_fecha']))   $negocio['formato_fecha']  = $filas['formato_fecha'];
        } catch (Throwable $e) {
            error_log('Restore: no se pudieron leer datos del negocio: ' . $e->getMessage());
        }

        $app_config = "<?php\n"
            . "/**\n"
            . " * IPV - Configuracion general del sistema\n"
            . " * Generado automaticamente por el instalador (modo restauración)\n"
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
            . "define('INSTALL_WITH_DEMO', false);\n"
            . "define('INSTALL_DATE', " . var_export(date('Y-m-d H:i:s'), true) . ");\n\n"
            . "// Zona horaria\n"
            . "date_default_timezone_set(ZONA_HORARIA);\n\n"
            . "// Logs\n"
            . "@ini_set('log_errors', 1);\n"
            . "@ini_set('error_log', __DIR__ . '/../storage/logs/error.log');\n";

        if (@file_put_contents(CONFIG_PATH . '/config.php', $app_config) === false) {
            throw new Exception('No se pudo escribir config/config.php');
        }
        @chmod(CONFIG_PATH . '/config.php', 0644);

        // ════════════════════════════════════════════════════════
        // 11. Copiar public.pem
        // ════════════════════════════════════════════════════════
        $origen  = __DIR__ . '/public.pem';
        $destino = CONFIG_PATH . '/public.pem';

        if (!file_exists($origen)) {
            throw new Exception('No se encontró install/public.pem.');
        }
        if (!@copy($origen, $destino)) {
            throw new Exception('No se pudo copiar public.pem a config/.');
        }
        if (!file_exists($destino) || filesize($destino) === 0) {
            throw new Exception('La copia de public.pem quedó vacía.');
        }
        @chmod($destino, 0644);

        // ════════════════════════════════════════════════════════
        // 12. Crear storage/ con protección
        // ════════════════════════════════════════════════════════
        $carpetasStorage = ['storage', 'storage/backups', 'storage/logs', 'storage/reportes'];
        foreach ($carpetasStorage as $carpeta) {
            $ruta = ROOT_PATH . '/' . $carpeta;
            if (!is_dir($ruta)) @mkdir($ruta, 0755, true);

            $htaccess = $ruta . '/.htaccess';
            if (!file_exists($htaccess)) {
                @file_put_contents($htaccess,
                    "# Denegar acceso directo\n"
                    . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
                    . "<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n"
                );
            }

            $index = $ruta . '/index.php';
            if (!file_exists($index)) {
                @file_put_contents($index, "<?php http_response_code(403); exit;\n");
            }
        }

        // ════════════════════════════════════════════════════════
        // 13. Crear .lock
        // ════════════════════════════════════════════════════════
        $lockContent = "Restaurado el " . date('Y-m-d H:i:s') . "\n"
            . "Origen: " . $restore['archivo_nombre'] . "\n"
            . "BD: " . $bd['basedatos'] . " (usuario: " . $bd['app_usuario'] . "@" . $bd['app_host'] . ")\n"
            . "URL base: " . $base_url . "\n"
            . "PHP: " . PHP_VERSION . "\n"
            . "Tablas restauradas: " . $verif['total'] . "\n"
            . "Sentencias ejecutadas: " . $stats['ejecutadas'] . "\n";

        @file_put_contents(LOCK_FILE, $lockContent);

        // ════════════════════════════════════════════════════════
        // 14. Eliminar archivo temporal
        // ════════════════════════════════════════════════════════
        @unlink($archivoTmp);

        // ════════════════════════════════════════════════════════
        // 15. Guardar resultado en sesión y pasar al paso 4
        // ════════════════════════════════════════════════════════
        $_SESSION['install']['restore_resultado'] = [
            'tablas'         => $verif['total'],
            'sentencias'     => $stats['ejecutadas'],
            'seed_generado'  => !$seedExistente,
            'archivo'        => $restore['archivo_nombre'],
            'archivo_size'   => $restore['archivo_size'],
            'almacen_id'     => $almacenId,
        ];

        $_SESSION['install_done'] = true;

        header('Location: restore_step4_finalizar.php');
        exit;

    } catch (PDOException $e) {
        $errores[] = 'Error de base de datos: ' . $e->getMessage();
    } catch (Throwable $e) {
        $errores[] = $e->getMessage();
    }
}

// ── Si hubo error, limpiar el archivo temporal ──
if (!empty($errores) && file_exists($archivoTmp)) {
    // NO lo borramos todavía para permitir reintentos en la misma sesión
    // @unlink($archivoTmp);
}

include __DIR__ . '/_header.php';
?>

<div class="container">
    <div class="progress">
        <div class="progress-step done"><i class="bi bi-check-lg"></i></div>
        <div class="progress-line done"></div>
        <div class="progress-step done"><i class="bi bi-check-lg"></i></div>
        <div class="progress-line done"></div>
        <div class="progress-step active">3</div>
        <div class="progress-line"></div>
        <div class="progress-step">4</div>
    </div>

    <div class="card">
        <h1><i class="bi bi-arrow-counterclockwise"></i> Restaurar backup — Paso 3: Ejecutando</h1>

        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    <strong>La restauración falló:</strong>
                    <ul>
                        <?php foreach ($errores as $e): ?>
                            <li><?= h($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    El archivo subido sigue en la sesión. Puedes corregir los datos y reintentar
                    sin volver a subirlo mientras no cierres el navegador.
                </div>
            </div>

            <div class="actions">
                <a href="restore_step2_archivo.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Corregir datos
                </a>
                <a href="restore_step3_restaurar.php" class="btn btn-primary">
                    <i class="bi bi-arrow-clockwise"></i> Reintentar
                </a>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-hourglass-split"></i>
                Restaurando base de datos...
            </div>
            <div class="actions center-actions">
                <a href="restore_step3_restaurar.php" class="btn btn-primary">
                    Continuar
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/_footer.php'; ?>