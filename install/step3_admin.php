<?php
require_once __DIR__ . '/_bootstrap.php';

// Verificar que se haya completado paso 2
if (empty($_SESSION['install']['bd'])) {
    header('Location: step2_bd.php');
    exit;
}

$errores = [];
$valores = [
    'nombre'   => $_POST['nombre']   ?? '',
    'email'    => $_POST['email']    ?? '',
    'password' => $_POST['password'] ?? '',
    'confirmar'=> $_POST['confirmar']?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validaciones
    if (empty($valores['nombre']))  $errores[] = 'El nombre es obligatorio.';
    if (empty($valores['email']))   $errores[] = 'El email es obligatorio.';
    if (!filter_var($valores['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email no tiene un formato válido.';
    }
    if (strlen($valores['password']) < 8) {
        $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if (!preg_match('/[A-Z]/', $valores['password'])) {
        $errores[] = 'La contraseña debe contener al menos una mayúscula.';
    }
    if (!preg_match('/[0-9]/', $valores['password'])) {
        $errores[] = 'La contraseña debe contener al menos un número.';
    }
    if (!preg_match('/[!@#$%^&*()\-_=+\[\]{};:,.<>\/?~`\'"\\\\|]/', $valores['password'])) {
        $errores[] = 'La contraseña debe contener al menos un símbolo.';
    }
    if ($valores['password'] !== $valores['confirmar']) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    if (empty($errores)) {
        try {
            $bd = $_SESSION['install']['bd'];
            $dsn = "mysql:host={$bd['host']};port={$bd['puerto']};dbname={$bd['basedatos']};charset=utf8mb4";
            $pdo = new PDO($dsn, $bd['usuario'], $bd['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // ⭐ FIX: limpiar el administrador demo SOLO si se cargaron los datos de ejemplo.
            // Si el usuario NO marcó "cargar demo", no hay nada que borrar (no existe ese usuario).
            if (!empty($_SESSION['install']['bd']['cargar_demo'])) {
                $pdo->exec("DELETE FROM usuarios WHERE email LIKE 'admin@ipv.com'");
            }

            // Insertar el admin real
            $hash = password_hash($valores['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO usuarios (nombre, email, password, rol_id, punto_venta_id, activo)
                 VALUES (?, ?, ?, 1, NULL, 1)"
            );
            $stmt->execute([$valores['nombre'], $valores['email'], $hash]);
            $admin_id = $pdo->lastInsertId();

            // Registrar en auditoría
            $pdo->prepare(
                "INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, detalle, ip)
                 VALUES (?, 'instalacion_admin', 'usuarios', ?, ?, ?)"
            )->execute([
                $admin_id,
                $admin_id,
                json_encode(['email' => $valores['email'], 'origen' => 'instalador']),
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]);

            $_SESSION['install']['admin'] = [
                'id'     => $admin_id,
                'nombre' => $valores['nombre'],
                'email'  => $valores['email'],
            ];
            $_SESSION['install']['paso'] = 4;

            header('Location: step4_finalizar.php');
            exit;

        } catch (PDOException $e) {
            $errores[] = 'Error al crear administrador: ' . $e->getMessage();
        }
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
        <div class="progress-step active">3</div>
        <div class="progress-line"></div>
        <div class="progress-step">4</div>
    </div>

    <div class="card">
        <h1><i class="bi bi-person-fill-add"></i> Paso 3: Cuenta de Administrador</h1>
        <p class="muted">Crea la cuenta principal del sistema. Con esta cuenta podrás gestionar todo.</p>

        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Corrige los siguientes errores:</strong>
                <ul>
                    <?php foreach ($errores as $e): ?>
                        <li><?= h($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="form" onsubmit="return validarFormulario()">
            <div class="field">
                <label>Nombre completo</label>
                <input type="text" name="nombre" value="<?= h($valores['nombre']) ?>" required>
            </div>

            <div class="field">
                <label>Correo electrónico</label>
                <input type="email" name="email" value="<?= h($valores['email']) ?>" required>
            </div>

            <div class="field">
                <label>Contraseña</label>
                <div class="input-group">
                    <input type="password" name="password" id="password" value="<?= h($valores['password']) ?>" required oninput="validarPassword()">
                    <button type="button" class="btn-toggle-pass" onclick="togglePass('password', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="strength-bar">
                    <div class="strength-fill" id="strength_fill"></div>
                </div>
                <div class="strength-text" id="strength_text"></div>

                <ul class="req-list" id="req_list">
                    <li data-req="len"><i class="bi bi-circle"></i> Mínimo 8 caracteres</li>
                    <li data-req="mayus"><i class="bi bi-circle"></i> Al menos una mayúscula</li>
                    <li data-req="num"><i class="bi bi-circle"></i> Al menos un número</li>
                    <li data-req="simbolo"><i class="bi bi-circle"></i> Al menos un símbolo</li>
                </ul>
            </div>

            <div class="field">
                <label>Confirmar contraseña</label>
                <div class="input-group">
                    <input type="password" name="confirmar" id="confirmar" value="<?= h($valores['confirmar']) ?>" required oninput="validarPassword()">
                    <button type="button" class="btn-toggle-pass" onclick="togglePass('confirmar', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div id="match_msg" class="match-msg"></div>
            </div>

            <div class="actions">
                <a href="step2_bd.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Atrás
                </a>
                <button type="submit" class="btn btn-primary" id="btn_crear">
                    Crear administrador <i class="bi bi-arrow-right"></i>
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
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

function validarPassword() {
    const pass = document.getElementById('password').value;
    const conf = document.getElementById('confirmar').value;

    const checks = {
        len:     pass.length >= 8,
        mayus:   /[A-Z]/.test(pass),
        num:     /[0-9]/.test(pass),
        simbolo: /[!@#$%^&*()\-_=+\[\]{};:,.<>\/?~`'"\\|]/.test(pass),
    };

    let cumplidos = 0;
    for (const [k, ok] of Object.entries(checks)) {
        const li = document.querySelector(`[data-req="${k}"]`);
        const icon = li.querySelector('i');
        if (ok) {
            icon.className = 'bi bi-check-circle-fill text-success';
            li.classList.add('ok');
            cumplidos++;
        } else {
            icon.className = 'bi bi-circle';
            li.classList.remove('ok');
        }
    }

    // Fortaleza
    const fill = document.getElementById('strength_fill');
    const text = document.getElementById('strength_text');
    let nivel = 0, label = '', color = '';
    if (pass.length === 0) {
        fill.style.width = '0%';
        text.textContent = '';
    } else if (cumplidos <= 2) {
        nivel = 33; label = 'Débil'; color = '#dc2626';
    } else if (cumplidos <= 4 && pass.length < 12) {
        nivel = 66; label = 'Media'; color = '#f59e0b';
    } else {
        nivel = 100; label = 'Fuerte'; color = '#16a34a';
    }
    if (pass.length > 0) {
        fill.style.width = nivel + '%';
        fill.style.background = color;
        text.textContent = 'Fortaleza: ' + label;
        text.style.color = color;
    }

    // Coincidencia
    const msg = document.getElementById('match_msg');
    if (conf.length === 0) {
        msg.textContent = '';
        msg.className = 'match-msg';
    } else if (pass === conf) {
        msg.innerHTML = '<i class="bi bi-check-circle-fill"></i> Las contraseñas coinciden';
        msg.className = 'match-msg ok';
    } else {
        msg.innerHTML = '<i class="bi bi-x-circle-fill"></i> Las contraseñas no coinciden';
        msg.className = 'match-msg error';
    }

    // Habilitar/deshabilitar botón
    const todo = cumplidos === 4 && pass === conf && pass.length > 0;
    document.getElementById('btn_crear').disabled = !todo;
}

function validarFormulario() {
    return !document.getElementById('btn_crear').disabled;
}

// Ejecutar al cargar por si hay valores previos
document.addEventListener('DOMContentLoaded', validarPassword);
</script>

<?php include __DIR__ . '/_footer.php'; ?>