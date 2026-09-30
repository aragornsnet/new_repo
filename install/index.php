<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('LOCK_FILE', __DIR__ . '/.lock');

// Si ya está instalado, redirigir al login
if (file_exists(LOCK_FILE) || file_exists(CONFIG_PATH . '/database.php')) {
    header('Location: ' . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/views/login.php');
    exit;
}

// Inicializar sesión de instalación si no existe
if (!isset($_SESSION['install'])) {
    $_SESSION['install'] = [
        'paso'         => 1,
        'modo'         => 'nuevo',   // 'nuevo' | 'restore'
        'requisitos'   => [],
        'bd'           => [],
        'admin'        => [],
        'negocio'      => [],
        'datos_demo'   => false,
        'restore'      => [],        // datos del flujo de restauración
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador IPV</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="install.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <div class="brand">
            <i class="bi bi-box-seam-fill"></i>
            <span>IPV · Instalador</span>
        </div>
        <div class="muted small">Sistema de Inventario de Productos y Ventas</div>
    </div>
</header>

<main>
    <div class="container">
        <div class="card center">
            <i class="bi bi-box-seam-fill icon-success" style="font-size:72px;color:var(--primary);"></i>
            <h1 style="justify-content:center;">Bienvenido al instalador de IPV</h1>
            <p class="muted" style="max-width:520px;margin:0 auto 24px;">
                Elige cómo quieres comenzar. Puedes hacer una <strong>instalación nueva</strong>
                desde cero o <strong>restaurar</strong> una base de datos existente desde un backup.
            </p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:32px;text-align:left;">
                <a href="step1_requisitos.php"
                   class="card"
                   style="text-decoration:none;display:block;padding:24px;transition:all .2s;border:2px solid var(--border);"
                   onmouseover="this.style.borderColor='var(--primary)';this.style.transform='translateY(-2px)';"
                   onmouseout="this.style.borderColor='var(--border)';this.style.transform='translateY(0)';">
                    <i class="bi bi-plus-circle-fill" style="font-size:40px;color:var(--primary);"></i>
                    <h2 style="margin:12px 0 6px;font-size:18px;">Instalación nueva</h2>
                    <p class="muted small" style="margin:0;">
                        Crea la base de datos, el usuario dedicado y todos los archivos de configuración desde cero.
                    </p>
                </a>

                <a href="restore_step1_requisitos.php"
                   class="card"
                   style="text-decoration:none;display:block;padding:24px;transition:all .2s;border:2px solid var(--border);"
                   onmouseover="this.style.borderColor='var(--success)';this.style.transform='translateY(-2px)';"
                   onmouseout="this.style.borderColor='var(--border)';this.style.transform='translateY(0)';">
                    <i class="bi bi-arrow-counterclockwise" style="font-size:40px;color:var(--success);"></i>
                    <h2 style="margin:12px 0 6px;font-size:18px;">Restaurar backup</h2>
                    <p class="muted small" style="margin:0;">
                        Sube un archivo <code>.sql</code> previamente generado por IPV y restaura la base de datos completa.
                    </p>
                </a>
            </div>

            <div class="alert alert-info" style="margin-top:32px;text-align:left;">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    <strong>Antes de empezar:</strong>
                    <ul style="margin:6px 0 0 20px;">
                        <li>Verifica que <code>install/public.pem</code> exista.</li>
                        <li>Comprueba que <code>public/libs/tcpdf/tcpdf.php</code> esté presente.</li>
                        <li>Ten a mano las credenciales del usuario administrador de MySQL.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<footer class="footer">
    <div class="footer-inner">
        <span class="muted small">IPV &copy; <?= date('Y') ?> — Instalador v1.2.0</span>
    </div>
</footer>
</body>
</html>