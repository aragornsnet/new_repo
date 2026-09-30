<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/CSRF.php';
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/helpers.php';

// ============================================================
// Auto-eliminar la carpeta install/ tras la instalación
// ============================================================
// $carpetaInstall = __DIR__ . '/../install';

if (is_dir($carpetaInstall)) {
    // Verificar que el sistema esté instalado antes de borrar
    $configExiste  = file_exists(__DIR__ . '/../config/config.php');
    $databaseExiste = file_exists(__DIR__ . '/../config/database.php');
    $lockExiste     = file_exists($carpetaInstall . '/.lock');

    if ($configExiste && $databaseExiste && $lockExiste) {
        // Eliminar recursivamente la carpeta install/
        $eliminarRecursivo = function ($dir) use (&$eliminarRecursivo) {
            if (!is_dir($dir)) return @unlink($dir);

            $items = @scandir($dir);
            if (!$items) return false;

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $ruta = $dir . '/' . $item;
                if (is_dir($ruta)) {
                    $eliminarRecursivo($ruta);
                } else {
                    @unlink($ruta);
                }
            }
            return @rmdir($dir);
        };

        $eliminarRecursivo($carpetaInstall);
    }
}

Auth::iniciarSesion();

// Si ya hay sesión, redirigir
if (Auth::check()) {
    redirigir('index.php');
}

$nombreNegocio = Config::get('empresa_nombre', NEGOCIO_NOMBRE);
$eslogan = Config::get('empresa_eslogan', '');
$logo = Config::get('empresa_logo', '');
$mensajeLogin = Config::get('login_mensaje', 'Bienvenido al sistema');
$fondoLogin = Config::get('login_fondo', '');

$csrfToken = CSRF::token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión · <?= h($nombreNegocio) ?></title>
    <?php if ($favicon = Config::get('empresa_favicon', '')): ?>
        <link rel="icon" type="image/png" href="<?= h(BASE_URL . ltrim($favicon, '/')) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php
    $tipografia = Config::get('tipografia', 'Inter');
    $fuentesPermitidas = [
        'Inter'     => 'Inter:wght@400;500;600;700;800',
        'Poppins'   => 'Poppins:wght@400;500;600;700;800',
        'Roboto'    => 'Roboto:wght@400;500;700;900',
        'system-ui' => null,
    ];
    if (!array_key_exists($tipografia, $fuentesPermitidas)) {
        $tipografia = 'Inter';
    }
    $fontQuery = $fuentesPermitidas[$tipografia];
    $fontCSS = $tipografia === 'system-ui'
        ? "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
        : "'{$tipografia}', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";
    ?>
    <?php if ($fontQuery): ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=<?= h($fontQuery) ?>&display=swap" rel="stylesheet">
    <?php endif; ?>
    <style>
    :root {
        --primary:        <?= Config::get('color_primario', '#2563eb') ?>;
        --primary-dark:   <?= Config::get('color_secundario', '#1e40af') ?>;
        --secondary:      <?= Config::get('color_secundario', '#1e40af') ?>;
        --accent:         <?= Config::get('color_acento', '#f59e0b') ?>;
        --success:        <?= Config::get('color_exito', '#16a34a') ?>;
        --danger:         <?= Config::get('color_peligro', '#dc2626') ?>;
        --font-sans: <?= $fontCSS ?>;
    }
    body, input, select, textarea, button, h1, h2, h3, h4, h5, h6 {
        font-family: <?= $fontCSS ?> !important;
    }
    </style>
    <?php if ($favicon = Config::get('empresa_favicon', '')): ?>
        <link rel="icon" href="<?= h(BASE_URL . ltrim($favicon, '/')) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/base.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/components.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/forms.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/dark-mode.css">
    <style>
        .login-page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--bg);
        }
        .login-left {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-5);
        }
        .login-card {
            width: 100%;
            max-width: 420px;
        }
        .login-brand {
            text-align: center;
            margin-bottom: var(--space-6);
        }
        .login-logo {
            width: 72px;
            height: 72px;
            margin: 0 auto var(--space-3);
            border-radius: var(--radius-lg);
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            box-shadow: var(--shadow-lg);
        }
        .login-logo img { width: 100%; height: 100%; object-fit: contain; border-radius: inherit; }
        .login-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text);
        }
        .login-subtitle {
            color: var(--text-muted);
            font-size: 14px;
        }
        .login-right {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-6);
            position: relative;
            overflow: hidden;
        }
        .login-right::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 40%, rgba(255,255,255,.1) 0%, transparent 50%),
                        radial-gradient(circle at 70% 80%, rgba(255,255,255,.08) 0%, transparent 50%);
        }
        .login-right-content {
            color: #fff;
            text-align: center;
            max-width: 400px;
            position: relative;
            z-index: 1;
        }
        .login-right-content i {
            font-size: 120px;
            opacity: .9;
            margin-bottom: var(--space-4);
            display: block;
        }
        .login-right-content h2 {
            color: #fff;
            font-size: 32px;
            margin-bottom: var(--space-3);
        }
        .login-right-content p {
            opacity: .9;
            font-size: 16px;
            line-height: 1.6;
        }
        @media (max-width: 900px) {
            .login-page { grid-template-columns: 1fr; }
            .login-right { display: none; }
        }
    </style>
</head>
<body>
<div class="login-page">
    <div class="login-left">
        <div class="login-card">
            <div class="login-brand">
                <div class="login-logo">
                    <?php if ($logo): ?>
                        <img src="<?= h(BASE_URL . ltrim($logo, '/')) ?>" alt="Logo">
                    <?php else: ?>
                        <i class="bi bi-box-seam-fill"></i>
                    <?php endif; ?>
                </div>
                <h1 class="login-title"><?= h($nombreNegocio) ?></h1>
                <?php if ($eslogan): ?>
                    <p class="login-subtitle"><?= h($eslogan) ?></p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3 class="mb-3"><i class="bi bi-box-arrow-in-right text-primary"></i> <?= h($mensajeLogin) ?></h3>

                <form id="form-login" class="form" novalidate>
                    <div class="field">
                        <label for="email">Correo electrónico <span class="req">*</span></label>
                        <div class="input-group has-icon">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" id="email" name="email"
                                   placeholder="tu@correo.com" autocomplete="email" required autofocus>
                        </div>
                    </div>

                    <div class="field">
                        <label for="password">Contraseña <span class="req">*</span></label>
                        <div class="input-group">
                            <input type="password" id="password" name="password"
                                   placeholder="••••••••" autocomplete="current-password" required>
                            <button type="button" class="btn-toggle-pass" aria-label="Mostrar contraseña">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg" id="btn-login" disabled>
                        <i class="bi bi-box-arrow-in-right"></i> Iniciar sesión
                    </button>
                </form>
            </div>

            <p class="text-center text-muted text-xs mt-4">
                &copy; <?= date('Y') ?> <?= h($nombreNegocio) ?> · Todos los derechos reservados
            </p>
        </div>
    </div>

    <div class="login-right">
        <div class="login-right-content">
            <i class="bi bi-shop-window"></i>
            <h2>Sistema IPV</h2>
            <p>Inventario, ventas y control de caja en un solo lugar. Gestiona tus puntos de venta de forma rápida y segura.</p>
        </div>
    </div>
</div>

<script>
    window.APP_BASE_URL = '<?= BASE_URL ?>';
    window.CSRF_TOKEN = '<?= h($csrfToken) ?>';
</script>
<script src="<?= BASE_URL ?>public/js/toast.js"></script>
<script src="<?= BASE_URL ?>public/js/api.js"></script>
<script src="<?= BASE_URL ?>public/js/password.js"></script>
<script src="<?= BASE_URL ?>public/js/app.js"></script>
<script src="<?= BASE_URL ?>public/js/auth.js"></script>
</body>
</html>