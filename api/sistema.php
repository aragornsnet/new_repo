<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';

ApiBootstrap::iniciar(['Administrador']);

$info = [
    'app' => [
        'nombre'      => NEGOCIO_NOMBRE,
        'version'     => APP_VERSION,
        'entorno'     => APP_ENV,
        'debug'       => APP_DEBUG,
        'base_url'    => BASE_URL,
        'zona_horaria'=> ZONA_HORARIA,
        'fecha_actual'=> date('Y-m-d H:i:s'),
    ],
    'servidor' => [
        'so'              => PHP_OS,
        'servidor'        => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido',
        'php_version'     => PHP_VERSION,
        'php_sapi'        => PHP_SAPI,
        'mysql_version'   => null,
        'memoria_limite'  => ini_get('memory_limit'),
        'max_execution'   => ini_get('max_execution_time'),
        'upload_max'      => ini_get('upload_max_filesize'),
        'post_max'        => ini_get('post_max_size'),
    ],
    'extensiones' => [],
    'carpetas' => [],
    'disco' => [],
    'bd' => [],
];

try {
    $info['servidor']['mysql_version'] = Database::fetchValue("SELECT VERSION()");
} catch (Throwable $e) {
    $info['servidor']['mysql_version'] = 'Desconocida';
}

$extensiones = [
    'pdo' => true, 'pdo_mysql' => true, 'mbstring' => true, 'json' => true,
    'openssl' => true, 'fileinfo' => false, 'gd' => false, 'zip' => false,
    'curl' => false, 'mysqli' => false,
];
foreach ($extensiones as $ext => $obligatoria) {
    $info['extensiones'][] = [
        'nombre' => $ext,
        'cargada' => extension_loaded($ext),
        'obligatoria' => $obligatoria,
    ];
}

$carpetas = [
    'config'                  => __DIR__ . '/../config/',
    'public/uploads'          => __DIR__ . '/../public/uploads/',
    'public/uploads/logos'    => __DIR__ . '/../public/uploads/logos/',
    'public/uploads/fondos'   => __DIR__ . '/../public/uploads/fondos/',
    'public/uploads/favicons' => __DIR__ . '/../public/uploads/favicons/',
    'storage/backups'         => __DIR__ . '/../storage/backups/',
    'storage/logs'            => __DIR__ . '/../storage/logs/',
];
foreach ($carpetas as $nombre => $ruta) {
    $info['carpetas'][] = [
        'nombre'     => $nombre,
        'existe'     => is_dir($ruta),
        'escribible' => is_dir($ruta) && is_writable($ruta),
        'ruta'       => $ruta,
    ];
}

$total = @disk_total_space(__DIR__);
$libre = @disk_free_space(__DIR__);
$info['disco'] = [
    'total'      => $total ?: 0,
    'libre'      => $libre ?: 0,
    'usado'      => $total ? ($total - $libre) : 0,
    'porcentaje' => $total ? round((($total - $libre) / $total) * 100, 1) : 0,
];

try {
    $info['bd'] = [
        'nombre'      => DB_NAME,
        'host'        => DB_HOST,
        'puerto'      => DB_PORT,
        'tamano'      => (int) Database::fetchValue("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
            FROM information_schema.tables
            WHERE table_schema = ?
        ", [DB_NAME], 0),
        'num_tablas'  => (int) Database::fetchValue("
            SELECT COUNT(*) FROM information_schema.tables 
            WHERE table_schema = ? AND table_type = 'BASE TABLE'
        ", [DB_NAME], 0),
        'num_vistas'  => (int) Database::fetchValue("
            SELECT COUNT(*) FROM information_schema.tables 
            WHERE table_schema = ? AND table_type = 'VIEW'
        ", [DB_NAME], 0),
    ];
} catch (Throwable $e) {
    error_log('Error info BD: ' . $e->getMessage());
}

$logApache = @ini_get('error_log');
$erroresRecientes = [];
$rutas = array_filter([
    $logApache ?: null,
    '/var/log/apache2/error.log',
    '/var/log/httpd/error_log',
    __DIR__ . '/../storage/logs/error.log',
]);

foreach ($rutas as $ruta) {
    if (file_exists($ruta) && is_readable($ruta)) {
        $lineas = @file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lineas) {
            $erroresRecientes = array_slice(array_reverse($lineas), 0, 30);
            break;
        }
    }
}

Response::ok([
    'info'             => $info,
    'errores_recientes' => $erroresRecientes,
]);