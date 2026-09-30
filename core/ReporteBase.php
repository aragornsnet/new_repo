<?php
/**
 * IPV - Clase base para reportes PDF con TCPDF
 * Proporciona encabezado, pie, logo y datos del negocio
 */

require_once __DIR__ . '/../public/libs/tcpdf/tcpdf.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/../config/config.php';

class ReporteBase extends TCPDF
{
    protected string $tituloReporte = '';
    protected string $subtituloReporte = '';
    protected string $nombreNegocio = '';
    protected string $eslogan = '';
    protected string $logoPath = '';
    protected string $direccion = '';
    protected string $telefono = '';
    protected string $email = '';
    protected string $textoLegal = '';
    protected string $usuarioGenerador = '';
    protected string $fechaGeneracion = '';

    public function __construct(string $titulo, string $subtitulo = '')
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);

        $this->tituloReporte = $titulo;
        $this->subtituloReporte = $subtitulo;

        // Datos del negocio
        $this->nombreNegocio = Config::get('empresa_nombre', NEGOCIO_NOMBRE);
        $this->eslogan = Config::get('empresa_eslogan', '');
        $this->direccion = Config::get('empresa_direccion', '');
        $this->telefono = Config::get('empresa_telefono', '');
        $this->email = Config::get('empresa_email', '');
        $this->textoLegal = Config::get('pdf_texto_legal', '');

        // Logo
        $logo = Config::get('empresa_logo', '');
        if ($logo) {
            $rutaLogo = __DIR__ . '/../' . ltrim($logo, '/');
            if (file_exists($rutaLogo)) {
                $this->logoPath = $rutaLogo;
            }
        }

        // Usuario generador (si hay sesión)
        if (isset($_SESSION['user'])) {
            $this->usuarioGenerador = $_SESSION['user']['nombre'] ?? '';
        }

        $this->fechaGeneracion = date('d/m/Y H:i');

        // Configurar documento
        $this->SetCreator($this->nombreNegocio);
        $this->SetAuthor($this->nombreNegocio);
        $this->SetTitle($titulo);
        $this->SetSubject($subtitulo ?: $titulo);

        // Márgenes
        $this->SetMargins(12, 45, 12);
        $this->SetHeaderMargin(8);
        $this->SetFooterMargin(10);
        $this->SetAutoPageBreak(true, 20);

        // Fuente
        $this->SetFont('helvetica', '', 10);

        // Quitar la línea por defecto del header/footer
        $this->setPrintHeader(true);
        $this->setPrintFooter(true);
    }

    /**
     * Encabezado del PDF
     */
    public function Header()
    {
        // Logo (arriba a la izquierda)
        if ($this->logoPath && file_exists($this->logoPath)) {
            $this->Image($this->logoPath, 12, 8, 22, 22, '', '', '', false, 300);
        }

        // Nombre del negocio (arriba a la derecha)
        $this->SetY(10);
        $this->SetFont('helvetica', 'B', 14);
        $this->SetTextColor(37, 99, 235);
        $this->Cell(0, 6, $this->nombreNegocio, 0, 1, 'R');

        // Eslogan
        if ($this->eslogan) {
            $this->SetFont('helvetica', 'I', 9);
            $this->SetTextColor(100, 116, 139);
            $this->Cell(0, 4, $this->eslogan, 0, 1, 'R');
        }

        // Contacto
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);

        $contacto = [];
        if ($this->direccion) $contacto[] = $this->direccion;
        if ($this->telefono)  $contacto[] = 'Tel: ' . $this->telefono;
        if ($this->email)     $contacto[] = $this->email;

        foreach ($contacto as $linea) {
            $this->Cell(0, 3.5, $linea, 0, 1, 'R');
        }

        // Línea separadora
        $this->SetY(32);
        $this->SetDrawColor(37, 99, 235);
        $this->SetLineWidth(0.5);
        $this->Line(12, $this->GetY(), 198, $this->GetY());

        // Título del reporte
        $this->SetY(35);
        $this->SetFont('helvetica', 'B', 15);
        $this->SetTextColor(15, 23, 42);
        $this->Cell(0, 6, $this->tituloReporte, 0, 1, 'C');

        // Subtítulo
        if ($this->subtituloReporte) {
            $this->SetFont('helvetica', '', 10);
            $this->SetTextColor(100, 116, 139);
            $this->Cell(0, 4, $this->subtituloReporte, 0, 1, 'C');
        }

        $this->SetY(50);
    }

    /**
     * Pie de página
     */
    public function Footer()
    {
        $this->SetY(-18);

        // Línea
        $this->SetDrawColor(226, 232, 240);
        $this->SetLineWidth(0.3);
        $this->Line(12, $this->GetY(), 198, $this->GetY());

        $this->SetY(-15);

        // Texto legal (si hay)
        if ($this->textoLegal) {
            $this->SetFont('helvetica', 'I', 7);
            $this->SetTextColor(148, 163, 184);
            $this->Cell(0, 3, mb_substr($this->textoLegal, 0, 150), 0, 1, 'C');
        }

        // Usuario + fecha + página
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);

        $izquierda = 'Generado por: ' . $this->usuarioGenerador;
        $centro = 'Fecha: ' . $this->fechaGeneracion;

        $this->Cell(90, 4, $izquierda, 0, 0, 'L');
        $this->Cell(0, 4, $centro . ' · Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'R');
    }

    /**
     * Genera la tabla del reporte
     */
    public function generarTabla(array $columnas, array $filas, array $opciones = [])
    {
        $anchos = $opciones['anchos'] ?? array_fill(0, count($columnas), 1);
        $alineaciones = $opciones['alineaciones'] ?? array_fill(0, count($columnas), 'L');

        $totalAncho = array_sum($anchos);
        $anchoDisponible = 186; // 210 - 12 - 12

        // Encabezado de tabla
        $this->SetFont('helvetica', 'B', 9);
        $this->SetFillColor(37, 99, 235);
        $this->SetTextColor(255, 255, 255);

        foreach ($columnas as $i => $col) {
            $anchoCol = ($anchos[$i] / $totalAncho) * $anchoDisponible;
            $this->Cell($anchoCol, 7, $col, 0, 0, 'C', true);
        }
        $this->Ln();

        // Filas
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(15, 23, 42);

        $relleno = false;
        foreach ($filas as $fila) {
            $this->SetFillColor($relleno ? 241 : 255, $relleno ? 245 : 255, $relleno ? 249 : 255);

            foreach ($fila as $i => $celda) {
                $anchoCol = ($anchos[$i] / $totalAncho) * $anchoDisponible;
                $this->Cell($anchoCol, 6, (string) $celda, 0, 0, $alineaciones[$i] ?? 'L', true);
            }
            $this->Ln();
            $relleno = !$relleno;
        }
    }

    /**
     * Bloque de totales
     */
    public function bloqueTotales(array $totales)
    {
        $this->Ln(3);
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(15, 23, 42);
        $this->SetFillColor(241, 245, 249);

        foreach ($totales as $etiqueta => $valor) {
            $this->Cell(140, 6, $etiqueta . ':', 0, 0, 'R');
            $this->Cell(46, 6, $valor, 0, 1, 'R', true);
        }
    }

    /**
     * Bloque de resumen (cajas)
     */
    public function bloqueResumen(array $items)
    {
        $this->Ln(2);

        // Posiciones absolutas
        $xInicial = 12;
        $anchoTotal = 186;
        $numItems = count($items);
        $espacio = 3;
        $anchoItem = ($anchoTotal - ($espacio * ($numItems - 1))) / $numItems;
        $altoItem = 16;
        $y = $this->GetY();

        // Guardar color de fondo y borde actuales
        $this->SetFillColor(241, 245, 249);
        $this->SetDrawColor(226, 232, 240);
        $this->SetLineWidth(0.2);

        // Dibujar cajas de fondo PRIMERO
        foreach ($items as $i => $item) {
            $x = $xInicial + ($anchoItem + $espacio) * $i;
            $this->Rect($x, $y, $anchoItem, $altoItem, 'DF'); // D=draw, F=fill
        }

        // Ahora escribir los textos ENCIMA de las cajas
        foreach ($items as $i => $item) {
            $x = $xInicial + ($anchoItem + $espacio) * $i;

            // Etiqueta
            $this->SetXY($x + 3, $y + 3);
            $this->SetFont('helvetica', '', 8);
            $this->SetTextColor(100, 116, 139);
            $this->Cell($anchoItem - 6, 4, $item['label'], 0, 0, 'L');

            // Valor
            $this->SetXY($x + 3, $y + 8);
            $this->SetFont('helvetica', 'B', 11);
            $this->SetTextColor(15, 23, 42);
            $this->Cell($anchoItem - 6, 6, $item['valor'], 0, 0, 'L');
        }

        // Dejar el cursor al final del bloque
        $this->SetY($y + $altoItem + 6);
    }
}