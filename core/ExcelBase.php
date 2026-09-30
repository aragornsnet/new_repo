<?php
/**
 * IPV - Clase base para generar reportes Excel (.xlsx)
 * Usa SimpleXLSXGen (namespace Shuchkin)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Config.php';

// Cargar la librería
$libPath = __DIR__ . '/../public/libs/simplexlsxgen/SimpleXLSXGen.php';

if (!file_exists($libPath)) {
    throw new Exception('No se encontró la librería SimpleXLSXGen en: ' . $libPath);
}

require_once $libPath;

if (!class_exists('Shuchkin\SimpleXLSXGen')) {
    throw new Exception(
        'La clase Shuchkin\\SimpleXLSXGen no se encontró después de cargar ' . $libPath
    );
}

use Shuchkin\SimpleXLSXGen;

class ExcelBase
{
    protected string $nombreArchivo = 'reporte';
    protected string $nombreNegocio = '';
    protected string $tituloReporte = '';
    protected string $subtituloReporte = '';

    public function __construct(string $nombreArchivo, string $titulo = '', string $subtitulo = '')
    {
        $this->nombreArchivo = $nombreArchivo;
        $this->tituloReporte = $titulo;
        $this->subtituloReporte = $subtitulo;
        $this->nombreNegocio = Config::get('empresa_nombre', NEGOCIO_NOMBRE);
    }

    public function generar(array $columnas, array $filas, array $opciones = [])
    {
        $datos = [];

        // Título
        $titulo = $opciones['titulo'] ?? $this->tituloReporte;
        if ($titulo) {
            $datos[] = ['<style font-size="16" font-weight="bold" bgcolor="#2563eb" color="#ffffff">' . $titulo . '</style>'];
        }

        // Subtítulo
        $subtitulo = $opciones['subtitulo'] ?? $this->subtituloReporte;
        if ($subtitulo) {
            $datos[] = ['<style font-size="11" color="#64748b">' . $subtitulo . '</style>'];
        }

        // Negocio
        $datos[] = ['<style font-size="10" font-weight="bold">' . $this->nombreNegocio . '</style>'];

        // Fecha
        $datos[] = ['<style font-size="9" color="#94a3b8">Generado: ' . date('d/m/Y H:i') . '</style>'];

        // Fila vacía
        $datos[] = [''];

        // Encabezados
        $encabezados = [];
        foreach ($columnas as $col) {
            $encabezados[] = '<style font-weight="bold" bgcolor="#1e40af" color="#ffffff">' . $col . '</style>';
        }
        $datos[] = $encabezados;

        // Filas
        foreach ($filas as $fila) {
            $filaLimpia = [];
            foreach ($fila as $celda) {
                $filaLimpia[] = is_string($celda) ? $celda : (string) $celda;
            }
            $datos[] = $filaLimpia;
        }

        // Totales
        if (!empty($opciones['totales'])) {
            $datos[] = [''];
            foreach ($opciones['totales'] as $etiqueta => $valor) {
                $datos[] = [
                    '<style font-weight="bold">' . $etiqueta . '</style>',
                    '<style font-weight="bold" bgcolor="#dcfce7">' . $valor . '</style>'
                ];
            }
        }

        // Crear el XLSX
        $xlsx = SimpleXLSXGen::fromArray($datos);

        // Anchos de columna
        $anchos = $opciones['anchos'] ?? [];
        if (empty($anchos)) {
            $anchos = [];
            foreach ($columnas as $col) {
                $anchos[] = max(15, mb_strlen($col) + 5);
            }
        }

        // Aplicar anchos - SimpleXLSXGen usa índice 1-based
        $col = 1;
        foreach ($anchos as $w) {
            $xlsx->setColWidth($col, $w);
            $col++;
        }

        $nombreCompleto = $this->nombreArchivo . '_' . date('Ymd_His') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombreCompleto . '"');
        header('Cache-Control: max-age=0');

        $xlsx->downloadAs($nombreCompleto);
        exit;
    }
}