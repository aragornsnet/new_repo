<?php
/**
 * IPV - Funciones utilitarias globales
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Config.php';

/**
 * Escapa HTML (previene XSS)
 */
function h($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Formatea un número como moneda
 */
function moneda($valor, ?string $codigo = null): string
{
    $simbolo  = Config::get('moneda_simbolo', MONEDA_SIMBOLO);
    $posicion = Config::get('moneda_posicion', 'antes');
    $decimales = Config::int('moneda_decimales', 2);
    $codigo   = $codigo ?? Config::get('moneda_codigo', MONEDA_CODIGO);

    $numero = number_format((float) $valor, $decimales, '.', ',');
    return $posicion === 'antes' ? "$simbolo$numero" : "$numero $simbolo";
}

/**
 * Formatea un número con separador de miles
 */
function numero($valor, int $decimales = 0): string
{
    return number_format((float) $valor, $decimales, '.', ',');
}

/**
 * Formatea una fecha/hora
 */
function fecha($fecha, ?string $formato = null): string
{
    if (empty($fecha)) return '';
    $formato = $formato ?? Config::get('formato_fecha', FORMATO_FECHA);

    if (is_string($fecha)) {
        $ts = strtotime($fecha);
        if ($ts === false) return $fecha;
    } else {
        $ts = $fecha;
    }

    return date($formato, $ts);
}

/**
 * Formatea una fecha relativa ("hace 5 minutos")
 */
function fechaRelativa($fecha): string
{
    if (empty($fecha)) return '';
    $ts = is_string($fecha) ? strtotime($fecha) : $fecha;
    $diff = time() - $ts;

    if ($diff < 60)          return 'hace unos segundos';
    if ($diff < 3600)        return 'hace ' . floor($diff / 60) . ' min';
    if ($diff < 86400)       return 'hace ' . floor($diff / 3600) . ' h';
    if ($diff < 604800)      return 'hace ' . floor($diff / 86400) . ' días';
    return fecha($fecha, 'd/m/Y');
}

/**
 * Genera un slug a partir de un texto
 */
function slug(string $texto): string
{
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    return trim($texto, '-');
}

/**
 * Genera un folio único para ventas
 * Formato: V-YYYYMMDD-XXXX
 */
function generarFolio(string $prefijo = 'V'): string
{
    $fecha = date('Ymd');
    $aleatorio = strtoupper(substr(bin2hex(random_bytes(4)), 0, 4));
    return "$prefijo-$fecha-$aleatorio";
}

/**
 * Sanitiza un nombre de archivo
 */
function sanitizarArchivo(string $nombre): string
{
    $nombre = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombre);
    return preg_replace('/_+/', '_', $nombre);
}

/**
 * Devuelve la URL completa de un asset
 */
function asset(string $ruta): string
{
    return rtrim(BASE_URL, '/') . '/public/' . ltrim($ruta, '/');
}

/**
 * Devuelve la URL completa de una vista
 */
function url(string $ruta = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($ruta, '/');
}

/**
 * Redirige a una URL y termina
 */
function redirigir(string $ruta): void
{
    // Limpiar rutas relativas que puedan escapar del BASE_URL
    $ruta = str_replace(['../', '..\\'], '', $ruta);
    $ruta = ltrim($ruta, '/');

    header('Location: ' . (str_starts_with($ruta, 'http') ? $ruta : url($ruta)));
    exit;
}

/**
 * Guarda un mensaje flash en sesión
 */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

/**
 * Obtiene y borra el mensaje flash
 */
function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/**
 * Devuelve el nombre del mes en español
 */
function mesEspanol(int $mes): string
{
    $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
              'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return $meses[$mes] ?? '';
}

/**
 * Devuelve el nombre del día en español
 */
function diaEspanol(int $num): string
{
    $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    return $dias[$num % 7] ?? '';
}

/**
 * Convierte bytes a formato legible
 */
function bytesLegible(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Trunca un texto
 */
function truncar(string $texto, int $largo = 50, string $sufijo = '...'): string
{
    if (mb_strlen($texto) <= $largo) return $texto;
    return mb_substr($texto, 0, $largo) . $sufijo;
}

/**
 * Devuelve la inicial de un nombre (para avatar)
 */
function inicial(string $nombre): string
{
    return mb_strtoupper(mb_substr(trim($nombre), 0, 1, 'UTF-8'), 'UTF-8');
}

/**
 * Devuelve el color de avatar según el nombre (determinístico)
 */
function colorAvatar(string $texto): string
{
    $colores = ['#2563eb', '#16a34a', '#dc2626', '#f59e0b', '#7c3aed', '#0891b2', '#db2777'];
    $hash = crc32($texto);
    return $colores[$hash % count($colores)];
}

/**
 * Comprueba si la petición es AJAX/fetch
 */
function esAjax(): bool
{
    $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return strtolower($xrw) === 'xmlhttprequest'
        || str_contains($accept, 'application/json');
}

/**
 * Devuelve el método HTTP actual
 */
function metodoHttp(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/**
 * Devuelve el cuerpo JSON de la petición (para fetch)
 */
function jsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}