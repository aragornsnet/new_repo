<?php
/**
 * IPV - Cerrar sesión
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Auditoria.php';
require_once __DIR__ . '/core/helpers.php';

Auth::iniciarSesion();
Auth::cerrarSesion();

header('Location: ' . BASE_URL . 'views/login.php');
exit;