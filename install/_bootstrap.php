<?php
/**
 * Bootstrap común del instalador
 */

// Diagnóstico visible
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/install_error.log');

// Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Rutas
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('LOCK_FILE', __DIR__ . '/.lock');

// Si ya está instalado, bloquear
if (file_exists(LOCK_FILE) || file_exists(CONFIG_PATH . '/database.php')) {
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['install'])) {
    header('Location: index.php');
    exit;
}

// Helpers
if (!function_exists('h')) {
    function h($text) {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('flash')) {
    function flash($tipo, $mensaje) {
        $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    }
}

if (!function_exists('get_flash')) {
    function get_flash() {
        if (isset($_SESSION['flash'])) {
            $f = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $f;
        }
        return null;
    }
}