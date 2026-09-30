<?php
/**
 * IPV - Genera PDF del manual
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(120);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/helpers.php';

Auth::iniciarSesion();
Auth::requireLogin();

// Whitelist de documentos
$documentos = [
    'usuario' => [
        'archivo' => 'manual_usuario.md',
        'titulo'  => 'Manual de Usuario',
        'roles'   => ['Administrador', 'Supervisor', 'Vendedor'],
    ],
    'tecnico' => [
        'archivo' => 'manual_tecnico.md',
        'titulo'  => 'Manual Técnico',
        'roles'   => ['Administrador'],
    ],
    'readme' => [
        'archivo' => 'README.md',
        'titulo'  => 'Acerca de IPV',
        'roles'   => ['Administrador', 'Supervisor', 'Vendedor'],
    ],
];

$docKey = $_GET['doc'] ?? 'usuario';

if (!isset($documentos[$docKey])) {
    Response::error('Documento no válido', 404);
}

$doc = $documentos[$docKey];
$rol = Auth::rol();

if (!in_array($rol, $doc['roles'], true)) {
    Response::prohibido('No tienes permiso para ver este documento');
}

$archivo = __DIR__ . '/../docs/' . $doc['archivo'];

if (!file_exists($archivo) || !is_readable($archivo)) {
    Response::noEncontrado('Documento no encontrado');
}

$contenidoMd = file_get_contents($archivo);

// Cargar TCPDF
$tcpdfPath = __DIR__ . '/../public/libs/tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) {
    Response::servidor('TCPDF no está disponible');
}

require_once $tcpdfPath;

// Configuración
$nombreNegocio = Config::get('empresa_nombre', NEGOCIO_NOMBRE);
$logo = Config::get('empresa_logo', '');

// Crear PDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator($nombreNegocio);
$pdf->SetAuthor($nombreNegocio);
$pdf->SetTitle($doc['titulo']);
$pdf->SetSubject($doc['titulo']);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->SetFont('helvetica', '', 10);

// Pie de página
$fecha = date('d/m/Y H:i');
$usuario = Auth::user()['nombre'] ?? 'Sistema';
$nombreDoc = $doc['titulo'];

class ManualPDF extends TCPDF {
    public $nombreNegocio;
    public $nombreDoc;
    public $usuario;
    public $fecha;

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 5,
            $this->nombreNegocio . ' · ' . $this->nombreDoc . ' · ' .
            'Generado por: ' . $this->usuario . ' · ' . $this->fecha,
            0, 0, 'C'
        );
    }
}

// Recrear con la clase personalizada
$pdf = new ManualPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->nombreNegocio = $nombreNegocio;
$pdf->nombreDoc = $doc['titulo'];
$pdf->usuario = $usuario;
$pdf->fecha = $fecha;

$pdf->SetCreator($nombreNegocio);
$pdf->SetAuthor($nombreNegocio);
$pdf->SetTitle($doc['titulo']);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->SetFont('helvetica', '', 10);

$pdf->AddPage();

// Portada
$pdf->SetY(40);
$pdf->SetFont('helvetica', 'B', 24);
$pdf->SetTextColor(37, 99, 235);
$pdf->Cell(0, 15, $nombreNegocio, 0, 1, 'C');

$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 20);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 12, $doc['titulo'], 0, 1, 'C');

$pdf->Ln(5);
$pdf->SetFont('helvetica', '', 11);
$pdf->SetTextColor(120, 120, 120);
$pdf->Cell(0, 8, 'Sistema IPV', 0, 1, 'C');

$pdf->Ln(30);

// Convertir Markdown a HTML
$parsedownPath = __DIR__ . '/../public/libs/parsedown/Parsedown.php';
if (file_exists($parsedownPath)) {
    require_once $parsedownPath;
    $parsedown = new Parsedown();
    $parsedown->setSafeMode(false);
    $parsedown->setBreaksEnabled(true);
    $html = $parsedown->text($contenidoMd);
} else {
    // Fallback: pre-formateado
    $html = '<pre style="font-size:9px;">' . htmlspecialchars($contenidoMd) . '</pre>';
}

// Ajustar estilos del HTML para TCPDF
$html = str_replace(
    ['<h1', '<h2', '<h3', '<h4', '<table', '<pre', '<code'],
    [
        '<h1 style="font-size:18px;font-weight:bold;color:#2563eb;margin-top:16px;"',
        '<h2 style="font-size:15px;font-weight:bold;color:#1e40af;margin-top:14px;"',
        '<h3 style="font-size:13px;font-weight:bold;color:#0f172a;margin-top:12px;"',
        '<h4 style="font-size:12px;font-weight:bold;color:#0f172a;margin-top:10px;"',
        '<table border="1" cellpadding="4" style="font-size:9px;"',
        '<pre style="font-size:8px;background-color:#f1f5f9;padding:6px;"',
        '<code style="font-size:9px;background-color:#f1f5f9;"',
    ],
    $html
);

// Escribir el HTML
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(15, 23, 42);
$pdf->writeHTML($html, true, false, true, false, '');

// Salida
$nombreArchivo = 'manual_' . $docKey . '_' . date('Ymd_His') . '.pdf';
$pdf->Output($nombreArchivo, 'I');
exit;