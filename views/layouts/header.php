<?php

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/CSRF.php';
require_once __DIR__ . '/../../core/Config.php';
require_once __DIR__ . '/../../core/helpers.php';

Auth::iniciarSesion();
Auth::requireLogin();

// ⭐ Verificar licencia
require_once __DIR__ . '/../../core/Middleware.php';
Middleware::verificarLicencia();

$usuario = Auth::user();

// ⭐ Avisos de contratos (solo Admin y Supervisor)
if (in_array($usuario['rol'] ?? '', ['Administrador', 'Supervisor'], true)) {
    require_once __DIR__ . '/../../core/ContratoAvisos.php';
    ContratoAvisos::revisar();
}

$nombreNegocio = Config::get('empresa_nombre', NEGOCIO_NOMBRE);
$logo = Config::get('empresa_logo', '');
$csrfToken = CSRF::token();
$paginaActiva = $paginaActiva ?? '';
$tituloPagina = $tituloPagina ?? 'Panel';
$subtituloPagina = $subtituloPagina ?? '';

/**
 * Cache buster para archivos estáticos.
 * Añade ?v=<filemtime> para forzar la recarga al cambiar.
 */
if (!function_exists('assetVer')) {
    function assetVer(string $ruta): string {
        $path = __DIR__ . '/../../' . ltrim($ruta, '/');
        $v = file_exists($path) ? filemtime($path) : time();
        return BASE_URL . $ruta . '?v=' . $v;
    }
}
?>
<!DOCTYPE html>
<html lang="es" <?php if (Config::get('tema_modo') === 'oscuro'): ?>data-theme="dark"<?php endif; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($tituloPagina) ?> · <?= h($nombreNegocio) ?></title>

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

    $colorPrimario   = Config::get('color_primario', '#2563eb');
    $colorSecundario = Config::get('color_secundario', '#1e40af');
    $colorAcento     = Config::get('color_acento', '#f59e0b');
    $colorExito      = Config::get('color_exito', '#16a34a');
    $colorPeligro    = Config::get('color_peligro', '#dc2626');

    $favicon = Config::get('empresa_favicon', '');
    ?>

    <?php if ($favicon): ?>
        <link rel="icon" href="<?= h(BASE_URL . ltrim($favicon, '/')) ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <?php if ($fontQuery): ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=<?= h($fontQuery) ?>&display=swap" rel="stylesheet">
    <?php endif; ?>

    <link rel="stylesheet" href="<?= assetVer('public/css/base.css') ?>">
    <link rel="stylesheet" href="<?= assetVer('public/css/components.css') ?>">
    <link rel="stylesheet" href="<?= assetVer('public/css/forms.css') ?>">
    <link rel="stylesheet" href="<?= assetVer('public/css/layout.css') ?>">
    <link rel="stylesheet" href="<?= assetVer('public/css/dark-mode.css') ?>">
    <link rel="stylesheet" href="<?= assetVer('public/css/personalizacion.css') ?>">

    <?php if (!empty($cssExtra) && is_array($cssExtra)): ?>
        <?php foreach ($cssExtra as $css): ?>
            <?php
            $rutaRelativa = str_replace(BASE_URL, '', $css);
            $rutaAbsoluta = __DIR__ . '/../../' . ltrim($rutaRelativa, '/');
            $v = file_exists($rutaAbsoluta) ? filemtime($rutaAbsoluta) : time();
            $sep = strpos($css, '?') !== false ? '&' : '?';
            ?>
            <link rel="stylesheet" href="<?= h($css) ?><?= $sep ?>v=<?= $v ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <style>
    :root {
        --primary:        <?= $colorPrimario ?>;
        --primary-dark:   <?= $colorSecundario ?>;
        --secondary:      <?= $colorSecundario ?>;
        --accent:         <?= $colorAcento ?>;
        --success:        <?= $colorExito ?>;
        --danger:         <?= $colorPeligro ?>;
        --primary-light:  color-mix(in srgb, <?= $colorPrimario ?> 15%, white);
        --font-sans: <?= $fontCSS ?>;
    }
    body, input, select, textarea, button, h1, h2, h3, h4, h5, h6 {
        font-family: <?= $fontCSS ?> !important;
    }
    </style>
</head>
<body>

<div class="sidebar-overlay"></div>

<div class="app">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <header class="header">
        <div class="header-left">
            <button class="sidebar-toggle" aria-label="Alternar menú">
                <i class="bi bi-list"></i>
            </button>
            <div>
                <h1 class="header-title"><?= h($tituloPagina) ?></h1>
                <?php if ($subtituloPagina): ?>
                    <div class="header-subtitle"><?= h($subtituloPagina) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="header-right">
            <button class="header-icon-btn" data-toggle-theme title="Cambiar tema">
                <i class="bi bi-moon-stars"></i>
            </button>

            <div class="notif-wrapper">
                <button class="header-icon-btn" data-notif-trigger title="Notificaciones">
                    <i class="bi bi-bell"></i>
                    <span class="notif-dot d-none" data-notif-badge>0</span>
                </button>
                <div class="user-dropdown notif-dropdown" id="notif-dropdown">
                    <div class="user-dropdown-header" style="display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <div class="name">Notificaciones</div>
                            <div class="email" style="font-size:11px;">Recientes</div>
                        </div>
                        <div class="d-flex gap-1">
                            <button class="btn btn-ghost btn-icon" onclick="Notificaciones.marcarTodasLeidas()" title="Marcar todas leídas">
                                <i class="bi bi-check2-all"></i>
                            </button>
                            <button class="btn btn-ghost btn-icon" onclick="Notificaciones.eliminarLeidas()" title="Eliminar leídas">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div id="notif-lista" style="max-height:400px;overflow-y:auto;">
                        <div style="padding:30px 20px;text-align:center;color:var(--text-muted);">
                            <div class="spinner"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="user-menu">
                <button class="user-menu-trigger">
                    <div class="avatar"
                         style="background: <?= colorAvatar($usuario['nombre']) ?>">
                        <?= h(inicial($usuario['nombre'])) ?>
                    </div>
                    <span class="user-name"><?= h($usuario['nombre']) ?></span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="user-dropdown">
                    <div class="user-dropdown-header">
                        <div class="name"><?= h($usuario['nombre']) ?></div>
                        <div class="email"><?= h($usuario['email']) ?></div>
                        <div class="text-xs text-muted mt-1">
                            <span class="badge badge-primary"><?= h($usuario['rol']) ?></span>
                            <?php if ($usuario['pv_nombre']): ?>
                                <span class="badge badge-neutral"><?= h($usuario['pv_nombre']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <hr>
                    <a href="<?= BASE_URL ?>views/ayuda.php?doc=usuario">
                        <i class="bi bi-book"></i> Manual de uso
                    </a>
                    <button type="button" onclick="Perfil.abrir()">
                        <i class="bi bi-person-circle"></i> Mi perfil
                    </button>
                    <hr>
                    <form method="POST" action="<?= BASE_URL ?>logout.php" style="margin:0;">
                        <?= CSRF::campo() ?>
                        <button type="submit" class="danger" style="background:transparent;border:none;width:100%;text-align:left;padding:10px 12px;border-radius:var(--radius-sm);cursor:pointer;display:flex;align-items:center;gap:var(--space-3);font-size:14px;color:var(--danger);font-family:inherit;">
                            <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="main">
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= h($flash['tipo']) ?>">
                <i class="bi bi-<?= $flash['tipo'] === 'success' ? 'check-circle-fill' :
                                  ($flash['tipo'] === 'danger' ? 'x-circle-fill' :
                                  ($flash['tipo'] === 'warning' ? 'exclamation-triangle-fill' : 'info-circle-fill')) ?>"></i>
                <div class="alert-body"><?= h($flash['mensaje']) ?></div>
            </div>
        <?php endif; ?>