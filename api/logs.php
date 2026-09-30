<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Auditoria.php';

ApiBootstrap::iniciar(['Administrador']);

$accion = $_GET['accion'] ?? 'listar';

$LOG_DIR = realpath(__DIR__ . '/../storage/logs');
if (!$LOG_DIR) {
    @mkdir(__DIR__ . '/../storage/logs', 0755, true);
    $LOG_DIR = realpath(__DIR__ . '/../storage/logs');
}

switch ($accion) {

    case 'listar':
        $archivos = [];
        if (is_dir($LOG_DIR)) {
            foreach (scandir($LOG_DIR) as $item) {
                if ($item === '.' || $item === '..') continue;
                $ruta = $LOG_DIR . '/' . $item;
                if (!is_file($ruta)) continue;

                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (!in_array($ext, ['log', 'txt'], true)) continue;

                $archivos[] = [
                    'nombre' => $item,
                    'tamano' => filesize($ruta),
                    'fecha'  => date('Y-m-d H:i:s', filemtime($ruta)),
                    'lineas' => contarLineas($ruta),
                ];
            }
        }

        usort($archivos, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));

        $phpLog = ini_get('error_log');
        if ($phpLog && file_exists($phpLog) && is_readable($phpLog)) {
            $realPhpLog = realpath($phpLog);
            if (strpos($realPhpLog, $LOG_DIR) !== 0) {
                $nombre = basename($realPhpLog);
                $existe = false;
                foreach ($archivos as $a) {
                    if ($a['nombre'] === $nombre) { $existe = true; break; }
                }
                if (!$existe) {
                    $archivos[] = [
                        'nombre' => $nombre,
                        'tamano' => filesize($realPhpLog),
                        'fecha'  => date('Y-m-d H:i:s', filemtime($realPhpLog)),
                        'lineas' => contarLineas($realPhpLog),
                        'externo' => true,
                        'ruta'   => $realPhpLog,
                    ];
                }
            }
        }

        Response::ok(['archivos' => $archivos, 'directorio' => $LOG_DIR]);
        break;

    case 'leer':
        $nombre = trim($_GET['archivo'] ?? '');
        if ($nombre === '') Response::error('Nombre de archivo requerido');

        $ruta = resolverRuta($nombre, $LOG_DIR);
        if (!$ruta) Response::noEncontrado('Archivo no encontrado');
        if (!is_readable($ruta)) Response::prohibido('No se puede leer el archivo');

        $nivel = trim($_GET['nivel'] ?? '');
        $busqueda = trim($_GET['q'] ?? '');
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $porPagina = min(max((int)($_GET['por_pagina'] ?? 100), 10), 1000);
        $ordenInverso = ($_GET['inverso'] ?? '1') === '1';

        $lineas = leerLineas($ruta);

        if ($nivel !== '') {
            $nivelUpper = strtoupper($nivel);
            $lineas = array_values(array_filter($lineas, function($l) use ($nivelUpper) {
                $lUpper = strtoupper($l);
                switch ($nivelUpper) {
                    case 'ERROR':   return strpos($lUpper, 'ERROR') !== false || strpos($lUpper, 'FATAL') !== false || strpos($lUpper, 'EXCEPTION') !== false;
                    case 'WARNING': return strpos($lUpper, 'WARNING') !== false || strpos($lUpper, 'WARN') !== false;
                    case 'INFO':    return strpos($lUpper, 'INFO') !== false;
                    case 'DEBUG':   return strpos($lUpper, 'DEBUG') !== false;
                }
                return true;
            }));
        }

        if ($busqueda !== '') {
            $busquedaLower = mb_strtolower($busqueda);
            $lineas = array_values(array_filter($lineas, function($l) use ($busquedaLower) {
                return mb_strpos(mb_strtolower($l), $busquedaLower) !== false;
            }));
        }

        $total = count($lineas);
        $totalPags = max(1, (int)ceil($total / $porPagina));

        if ($ordenInverso) $lineas = array_reverse($lineas);

        $offset = ($pagina - 1) * $porPagina;
        $paginadas = array_slice($lineas, $offset, $porPagina);

        $resultado = [];
        foreach ($paginadas as $i => $linea) {
            $numOriginal = $ordenInverso ? ($total - $offset - $i) : ($offset + $i + 1);
            $resultado[] = [
                'num'   => $numOriginal,
                'texto' => $linea,
                'nivel' => detectarNivel($linea),
            ];
        }

        Response::ok([
            'archivo'    => $nombre,
            'lineas'     => $resultado,
            'total'      => $total,
            'pagina'     => $pagina,
            'por_pagina' => $porPagina,
            'total_pags' => $totalPags,
            'tamano'     => filesize($ruta),
            'modificado' => date('Y-m-d H:i:s', filemtime($ruta)),
        ]);
        break;

    case 'descargar':
        $nombre = trim($_GET['archivo'] ?? '');
        if ($nombre === '') Response::error('Nombre de archivo requerido');

        $ruta = resolverRuta($nombre, $LOG_DIR);
        if (!$ruta) Response::noEncontrado('Archivo no encontrado');
        if (!is_readable($ruta)) Response::prohibido('No se puede leer el archivo');

        $nombreDescarga = 'log_' . date('Ymd_His') . '_' . basename($ruta);

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreDescarga . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;

    case 'vaciar':
        $body = jsonBody();
        $nombre = trim($body['archivo'] ?? '');
        if ($nombre === '') Response::error('Nombre de archivo requerido');

        $ruta = resolverRuta($nombre, $LOG_DIR);
        if (!$ruta) Response::noEncontrado('Archivo no encontrado');
        if (!is_writable($ruta)) Response::prohibido('No se puede escribir');

        $tamanoAntes = filesize($ruta);
        $fh = @fopen($ruta, 'w');
        if (!$fh) Response::servidor('No se pudo vaciar');
        fclose($fh);

        Auditoria::registrar('log_vaciado', 'sistema', null, [
            'archivo' => basename($ruta), 'tamano_antes' => $tamanoAntes,
        ]);

        Response::ok(null, 'Archivo vaciado');
        break;

    case 'eliminar':
        $body = jsonBody();
        $nombre = trim($body['archivo'] ?? '');
        if ($nombre === '') Response::error('Nombre de archivo requerido');

        $ruta = resolverRuta($nombre, $LOG_DIR);
        if (!$ruta) Response::noEncontrado('Archivo no encontrado');
        if (strpos($ruta, $LOG_DIR) !== 0) Response::prohibido('Solo se pueden eliminar logs de storage/logs');

        $tamano = filesize($ruta);
        if (!@unlink($ruta)) Response::servidor('No se pudo eliminar');

        Auditoria::registrar('log_eliminado', 'sistema', null, [
            'archivo' => basename($ruta), 'tamano' => $tamano,
        ]);

        Response::ok(null, 'Archivo eliminado');
        break;

    case 'estadisticas':
        $totalTamano = 0;
        $totalArchivos = 0;
        $porNivel = ['error' => 0, 'warning' => 0, 'info' => 0, 'debug' => 0];

        if (is_dir($LOG_DIR)) {
            foreach (scandir($LOG_DIR) as $item) {
                if ($item === '.' || $item === '..') continue;
                $ruta = $LOG_DIR . '/' . $item;
                if (!is_file($ruta)) continue;
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (!in_array($ext, ['log', 'txt'], true)) continue;

                $totalTamano += filesize($ruta);
                $totalArchivos++;

                if (filesize($ruta) < 5 * 1024 * 1024) {
                    foreach (leerLineas($ruta) as $l) {
                        $n = detectarNivel($l);
                        if (isset($porNivel[$n])) $porNivel[$n]++;
                    }
                }
            }
        }

        Response::ok([
            'total_archivos' => $totalArchivos,
            'total_tamano'   => $totalTamano,
            'por_nivel'      => $porNivel,
        ]);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}

function resolverRuta(string $nombre, string $LOG_DIR): ?string
{
    $nombre = basename($nombre);
    $ruta = $LOG_DIR . '/' . $nombre;
    if (file_exists($ruta)) {
        $real = realpath($ruta);
        if ($real && strpos($real, $LOG_DIR) === 0) return $real;
    }

    $phpLog = ini_get('error_log');
    if ($phpLog && file_exists($phpLog) && basename($phpLog) === $nombre) {
        $real = realpath($phpLog);
        if ($real && is_readable($real)) return $real;
    }

    return null;
}

function leerLineas(string $ruta, int $maxLineas = 50000): array
{
    $lineas = [];
    $fh = @fopen($ruta, 'r');
    if (!$fh) return [];

    $i = 0;
    while (($linea = fgets($fh)) !== false) {
        $lineas[] = rtrim($linea, "\r\n");
        $i++;
        if ($i >= $maxLineas) break;
    }
    fclose($fh);
    return $lineas;
}

function contarLineas(string $ruta): int
{
    $fh = @fopen($ruta, 'r');
    if (!$fh) return 0;
    $count = 0;
    while (!feof($fh)) {
        if (fgets($fh) !== false) $count++;
    }
    fclose($fh);
    return $count;
}

function detectarNivel(string $linea): string
{
    $upper = strtoupper($linea);
    if (strpos($upper, 'FATAL') !== false || strpos($upper, 'ERROR') !== false || strpos($upper, 'EXCEPTION') !== false || strpos($upper, 'CRITICAL') !== false) return 'error';
    if (strpos($upper, 'WARNING') !== false || strpos($upper, 'WARN') !== false || strpos($upper, 'DEPRECATED') !== false) return 'warning';
    if (strpos($upper, 'DEBUG') !== false) return 'debug';
    if (strpos($upper, 'INFO') !== false || strpos($upper, 'NOTICE') !== false) return 'info';
    return 'info';
}