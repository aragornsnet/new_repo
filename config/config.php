<?php
/**
 * IPV - Configuracion general del sistema
 * Generado automaticamente por el instalador (modo restauración)
 */

// Entorno
define('APP_ENV', 'production');
define('APP_DEBUG', false);
define('APP_VERSION', '1.2.0');

// URL base
define('BASE_URL', 'http://10.66.30.2/ipv/');

// Negocio
define('NEGOCIO_NOMBRE', 'Mi Negocio IPV');
define('MONEDA_CODIGO', 'CUP');
define('MONEDA_SIMBOLO', '$');
define('ZONA_HORARIA', 'America/Havana');
define('FORMATO_FECHA', 'd/m/Y H:i');

// Seguridad
define('SESSION_LIFETIME', 7200);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_BLOCK_MINUTES', 30);

// Instalacion
define('INSTALL_WITH_DEMO', false);
define('INSTALL_DATE', '2026-09-30 19:39:59');

// Zona horaria
date_default_timezone_set(ZONA_HORARIA);

// Logs
@ini_set('log_errors', 1);
@ini_set('error_log', __DIR__ . '/../storage/logs/error.log');
