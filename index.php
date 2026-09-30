<?php
/**
 * IPV - Punto de entrada
 * Redirige según el rol del usuario
 */

$archivoConfig = __DIR__ . '/config/config.php';

if (!file_exists($archivoConfig)) {
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    header('Location: ' . $basePath . '/install/index.php');
    exit;
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/helpers.php';

Auth::iniciarSesion();

if (!Auth::check()) {
    redirigir('views/login.php');
}

switch (Auth::rolId()) {
    case 1: // Administrador
        redirigir('views/dashboards/admin.php');
        break;
    case 2: // Supervisor
        redirigir('views/supervisor/dashboard.php');
        break;
    case 3: // Vendedor
        redirigir('views/vendedor/dashboard.php');
        break;
    case 4: // ⭐ Almacenero
        redirigir('views/almacen/dashboard.php');
        break;
    default:
        Auth::cerrarSesion();
        redirigir('views/login.php');
}