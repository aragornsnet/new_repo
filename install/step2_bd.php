<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_helpers_bd.php';

$errores = [];
$esPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

// Valores por defecto / recuperados
$valores = [
    'host'            => $_POST['host']      ?? 'localhost',
    'puerto'          => $_POST['puerto']    ?? '3306',
    'usuario'         => $_POST['usuario']   ?? 'root',
    'password'        => $_POST['password']  ?? '',
    'basedatos'       => $_POST['basedatos'] ?? 'ipv_db',
    'app_usuario'     => $_POST['app_usuario'] ?? 'ipv_user',
    'app_host'        => $_POST['app_host']   ?? 'localhost',
    'app_password'    => $_POST['app_password'] ?? '',
    'cargar_demo'     => $esPost ? isset($_POST['cargar_demo']) : false,
    'negocio_nombre'  => $_POST['negocio_nombre'] ?? 'Mi Negocio IPV',
    'moneda_codigo'   => $_POST['moneda_codigo']  ?? 'CUP',
    'moneda_simbolo'  => $_POST['moneda_simbolo'] ?? '$',
    'zona_horaria'    => $_POST['zona_horaria']   ?? 'America/Havana',
    'formato_fecha'   => $_POST['formato_fecha']  ?? 'd/m/Y H:i',
];

// Generar contraseña si no viene del POST
if (empty($valores['app_password'])) {
    $valores['app_password'] = generarPasswordBD(24);
}

// Si el usuario pulsa "Regenerar contraseña" (submit aparte)
if ($esPost && ($_POST['accion'] ?? '') === 'regenerar') {
    $valores['app_password'] = generarPasswordBD(24);
    $esPost = false; // No procesar instalación, solo redibujar
}

if ($esPost) {
    // Validaciones de campos
    if (empty($valores['host']))      $errores[] = 'El host es obligatorio.';
    if (empty($valores['usuario']))   $errores[] = 'El usuario administrativo es obligatorio.';
    if (empty($valores['basedatos'])) $errores[] = 'El nombre de la base de datos es obligatorio.';
    if (empty($valores['app_usuario'])) $errores[] = 'El usuario dedicado es obligatorio.';

    try {
        validarNombreBD($valores['basedatos']);
        validarNombreUsuarioBD($valores['app_usuario']);
        validarHostUsuario($valores['app_host']);
    } catch (Throwable $e) {
        $errores[] = $e->getMessage();
    }

    // Evitar que app_usuario sea igual al admin
    if (strtolower($valores['app_usuario']) === strtolower($valores['usuario'])
        && $valores['app_host'] === $valores['host']) {
        $errores[] = 'El usuario dedicado no puede ser igual al usuario administrativo.';
    }

    if (empty($errores)) {
        try {
            // ────────────────────────────────────────────────
            // 1. Conectar con el usuario ADMINISTRATIVO
            // ────────────────────────────────────────────────
            $pdoAdmin = conectarAdmin(
                $valores['host'],
                $valores['puerto'],
                $valores['usuario'],
                $valores['password']
            );

            // ────────────────────────────────────────────────
            // 2. Crear la BD
            // ────────────────────────────────────────────────
            crearBaseDatos($pdoAdmin, $valores['basedatos']);

            // ────────────────────────────────────────────────
            // 3. Crear/actualizar el usuario dedicado + permisos
            // ────────────────────────────────────────────────
            try {
                crearUsuarioDedicado(
                    $pdoAdmin,
                    $valores['basedatos'],
                    $valores['app_usuario'],
                    $valores['app_host'],
                    $valores['app_password']
                );
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (str_contains($msg, '1142') || str_contains($msg, '1227') || str_contains($msg, 'Access denied')) {
                    throw new Exception(
                        'El usuario administrativo no tiene privilegios para crear usuarios ' .
                        'o conceder permisos. Necesitas un usuario con permisos CREATE USER y GRANT OPTION ' .
                        'o contactar con tu proveedor de hosting. Detalle: ' . $msg
                    );
                }
                throw new Exception('No se pudo crear el usuario dedicado: ' . $msg);
            }

            // ────────────────────────────────────────────────
            // 4. Ejecutar schema.sql y seed.sql con el usuario ADMIN
            //    (o con el dedicado, es indiferente; usamos admin para evitar problemas de permisos iniciales)
            // ────────────────────────────────────────────────
            $pdoAdmin->exec("USE `{$valores['basedatos']}`");

            $schema = file_get_contents(__DIR__ . '/schema.sql');
            if ($schema === false) throw new Exception('No se pudo leer schema.sql');
            $pdoAdmin->exec($schema);

            if ($valores['cargar_demo']) {
                $seed = file_get_contents(__DIR__ . '/seed.sql');
                if ($seed !== false) {
                    $pdoAdmin->exec($seed);
                }
            }

            // ────────────────────────────────────────────────
            // 5. Crear Almacén Central
            // ────────────────────────────────────────────────
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

            $pdoAdmin->prepare("
                UPDATE configuracion SET valor = ? WHERE clave = 'almacen_id'
            ")->execute([$almacenId]);

            // ────────────────────────────────────────────────
            // 6. VERIFICAR que el usuario dedicado puede conectarse
            // ────────────────────────────────────────────────
            try {
                $pdoTest = conectarApp(
                    $valores['host'],
                    $valores['puerto'],
                    $valores['basedatos'],
                    $valores['app_usuario'],
                    $valores['app_password']
                );
                // Probar una consulta simple
                $pdoTest->query("SELECT 1 FROM usuarios LIMIT 1")->fetch();
            } catch (Throwable $e) {
                throw new Exception(
                    'El usuario dedicado se creó pero no puede conectarse. ' .
                    'Comprueba el host (`localhost` vs `127.0.0.1`) y los permisos. Detalle: ' . $e->getMessage()
                );
            }

            // ────────────────────────────────────────────────
            // 7. Guardar en sesión
            // ────────────────────────────────────────────────
            $_SESSION['install']['bd'] = [
                'host'          => $valores['host'],
                'puerto'        => $valores['puerto'],
                'usuario'       => $valores['usuario'],      // admin (solo referencia)
                'password'      => $valores['password'],     // admin (solo referencia)
                'basedatos'     => $valores['basedatos'],
                'app_usuario'   => $valores['app_usuario'],
                'app_host'      => $valores['app_host'],
                'app_password'  => $valores['app_password'],
                'cargar_demo'   => $valores['cargar_demo'],
                'almacen_id'    => $almacenId,
            ];

            $_SESSION['install']['negocio'] = [
                'nombre'         => $valores['negocio_nombre'],
                'moneda_codigo'  => $valores['moneda_codigo'],
                'moneda_simbolo' => $valores['moneda_simbolo'],
                'zona_horaria'   => $valores['zona_horaria'],
                'formato_fecha'  => $valores['formato_fecha'],
            ];

            $_SESSION['install']['paso'] = 3;

            header('Location: step3_admin.php');
            exit;

        } catch (PDOException $e) {
            $errores[] = 'Error de conexión: ' . $e->getMessage();
        } catch (Throwable $e) {
            $errores[] = $e->getMessage();
        }
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
        <h1><i class="bi bi-database-fill"></i> Paso 2: Base de datos y configuración</h1>
        <p class="muted">
            El instalador creará la base de datos y un <strong>usuario dedicado</strong> para que la aplicación
            trabaje con él. El usuario administrativo se usa solo durante la instalación.
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

        <form method="POST" class="form" autocomplete="off">

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- USUARIO ADMINISTRATIVO (solo para instalar)            -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <h2><i class="bi bi-shield-lock-fill"></i> Usuario administrativo de MySQL</h2>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    Este usuario se usa <strong>solo durante la instalación</strong> para crear la base de datos
                    y el usuario dedicado. Debe tener permisos de <code>CREATE DATABASE</code>,
                    <code>CREATE USER</code> y <code>GRANT OPTION</code>.
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
                    <label>Usuario administrativo</label>
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

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- BASE DE DATOS                                          -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <h2><i class="bi bi-database-fill"></i> Base de datos</h2>
            <div class="grid grid-2">
                <div class="field grid-full">
                    <label>Nombre de la base de datos</label>
                    <input type="text" name="basedatos" value="<?= h($valores['basedatos']) ?>" required>
                    <small class="muted">Si no existe, se creará automáticamente. Solo letras, números y guión bajo.</small>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- USUARIO DEDICADO (el que usará la app)                 -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <h2><i class="bi bi-person-badge-fill"></i> Usuario dedicado para IPV</h2>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    La aplicación usará <strong>únicamente este usuario</strong> en producción.
                    Solo tendrá permisos sobre <code><?= h($valores['basedatos']) ?></code>.
                </div>
            </div>

            <div class="grid grid-2">
                <div class="field">
                    <label>Nombre de usuario</label>
                    <input type="text" name="app_usuario" value="<?= h($valores['app_usuario']) ?>" required
                           pattern="[A-Za-z0-9_]+" maxlength="32">
                    <small class="muted">Solo letras, números y guión bajo (máx. 32).</small>
                </div>
                <div class="field">
                    <label>Host del usuario</label>
                    <select name="app_host">
                        <option value="localhost" <?= $valores['app_host'] === 'localhost' ? 'selected' : '' ?>>localhost</option>
                        <option value="127.0.0.1" <?= $valores['app_host'] === '127.0.0.1' ? 'selected' : '' ?>>127.0.0.1</option>
                        <option value="%" <?= $valores['app_host'] === '%' ? 'selected' : '' ?>>% (cualquier host)</option>
                    </select>
                    <small class="muted">Usa <code>localhost</code> si MySQL y Apache están en el mismo servidor.</small>
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
                        Anótala. Se guardará en <code>config/database.php</code>.
                        <button type="submit" name="accion" value="regenerar" class="btn-link" style="background:none;border:none;color:var(--primary);cursor:pointer;padding:0;font:inherit;text-decoration:underline;">
                            Regenerar contraseña
                        </button>
                    </small>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- DATOS DEL NEGOCIO                                      -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <h2><i class="bi bi-shop"></i> Datos del negocio</h2>
            <div class="grid grid-2">
                <div class="field grid-full">
                    <label>Nombre del negocio</label>
                    <input type="text" name="negocio_nombre" value="<?= h($valores['negocio_nombre']) ?>" required>
                </div>
                <div class="field">
                    <label>Código de moneda</label>
                    <input type="text" name="moneda_codigo" value="<?= h($valores['moneda_codigo']) ?>" maxlength="5" required>
                </div>
                <div class="field">
                    <label>Símbolo de moneda</label>
                    <input type="text" name="moneda_simbolo" value="<?= h($valores['moneda_simbolo']) ?>" maxlength="5" required>
                </div>
                <div class="field">
                    <label>Zona horaria</label>
                    <select name="zona_horaria">
                        <?php
                        $zonas = ['America/Havana', 'America/Mexico_City', 'America/Bogota', 'America/Lima',
                                  'America/Santiago', 'America/Argentina/Buenos_Aires', 'America/New_York',
                                  'Europe/Madrid', 'Europe/London', 'UTC'];
                        foreach ($zonas as $z):
                            $sel = ($valores['zona_horaria'] === $z) ? 'selected' : '';
                        ?>
                            <option value="<?= h($z) ?>" <?= $sel ?>><?= h($z) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Formato de fecha y hora</label>
                    <input type="text" name="formato_fecha" value="<?= h($valores['formato_fecha']) ?>" required>
                    <small class="muted">Ej: d/m/Y H:i</small>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- DATOS DE EJEMPLO                                       -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <h2><i class="bi bi-database-check"></i> Datos de ejemplo</h2>
            <div class="field">
                <label class="checkbox">
                    <input type="checkbox" name="cargar_demo" value="1" <?= $valores['cargar_demo'] ? 'checked' : '' ?>>
                    <span>Cargar datos de ejemplo (PV, usuarios demo, productos, ventas de prueba)</span>
                </label>
            </div>

            <div class="actions">
                <a href="step1_requisitos.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Atrás
                </a>
                <button type="submit" class="btn btn-primary">
                    Instalar y continuar <i class="bi bi-arrow-right"></i>
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