<?php
/**
 * IPV - API para subir comprobantes de transferencia
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';

ApiBootstrap::iniciar(['Vendedor', 'Supervisor', 'Administrador']);

if (metodoHttp() !== 'POST') {
    Response::error('Método no permitido', 405);
}

if (!CSRF::validarCabecera() && !CSRF::validar()) {
    Response::prohibido('Token CSRF inválido');
}

if (!isset($_FILES['comprobante'])) {
    Response::error('No se recibió ningún archivo');
}

$archivo = $_FILES['comprobante'];

// Validar errores de subida
if ($archivo['error'] !== UPLOAD_ERR_OK) {
    $errores = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo supera el límite de upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite del formulario',
        UPLOAD_ERR_PARTIAL    => 'La subida fue incompleta',
        UPLOAD_ERR_NO_FILE    => 'No se subió ningún archivo',
        UPLOAD_ERR_NO_TMP_DIR => 'No hay carpeta temporal',
        UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir en el disco',
        UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP bloqueó la subida',
    ];
    Response::error($errores[$archivo['error']] ?? 'Error desconocido');
}

// Tamaño máximo desde config
$maxMb = Config::int('transf_comprobante_max_mb', 5);
$maxBytes = $maxMb * 1024 * 1024;

if ($archivo['size'] > $maxBytes) {
    Response::error("El archivo supera el tamaño máximo de $maxMb MB");
}

// Validar extensión
$ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
$formatosPermitidos = ['png', 'jpg', 'jpeg', 'webp', 'pdf'];

if (!in_array($ext, $formatosPermitidos, true)) {
    Response::error('Formato no permitido. Se aceptan: ' . implode(', ', $formatosPermitidos));
}

// Validar MIME real
$mimesPermitidos = [
    'image/png', 'image/jpeg', 'image/webp', 'application/pdf',
];

if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeReal = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeReal, $mimesPermitidos, true)) {
        Response::error('El archivo no parece ser una imagen o PDF válido');
    }
}

// Crear carpeta destino
$carpetaDestino = __DIR__ . '/../public/uploads/comprobantes/';
if (!is_dir($carpetaDestino)) {
    if (!@mkdir($carpetaDestino, 0755, true)) {
        Response::servidor('No se pudo crear la carpeta de comprobantes');
    }
}

if (!is_writable($carpetaDestino)) {
    Response::servidor('La carpeta de comprobantes no tiene permisos de escritura');
}

// Nombre único
$nombreArchivo = 'comprobante_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$rutaCompleta = $carpetaDestino . $nombreArchivo;

if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
    Response::servidor('No se pudo guardar el archivo');
}

// Ruta relativa para guardar en BD
$rutaRelativa = 'public/uploads/comprobantes/' . $nombreArchivo;

Response::ok([
    'archivo'      => $nombreArchivo,
    'ruta'         => $rutaRelativa,
    'tamano'       => $archivo['size'],
    'ext'          => $ext,
], 'Comprobante subido correctamente');