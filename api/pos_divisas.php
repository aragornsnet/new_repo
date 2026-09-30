<?php
/**
 * IPV - API de divisas para el POS
 * Devuelve las divisas activas y sus tasas vigentes
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';

ApiBootstrap::iniciar(['Vendedor', 'Supervisor', 'Administrador']);

$accion = $_GET['accion'] ?? 'listar';

switch ($accion) {

    // ============================================================
    // LISTAR divisas activas con su última tasa
    // ============================================================
    case 'listar':
        // Verificar que las divisas estén habilitadas
        if (!Config::bool('divisas_habilitadas', false)) {
            Response::ok(['divisas' => []]);
            break;
        }

        // Divisas activas
        $divisas = Database::fetchAll("
            SELECT id, codigo, nombre, simbolo, orden
            FROM divisas
            WHERE activo = 1
            ORDER BY orden, codigo
        ");

        if (empty($divisas)) {
            Response::ok(['divisas' => []]);
            break;
        }

        // Para cada divisa, obtener la última tasa vigente
        $divisasConTasa = [];
        foreach ($divisas as $d) {
            $tasa = Database::fetchOne("
                SELECT tasa, origen, fuente, fecha
                FROM tasas_cambio
                WHERE divisa_id = ?
                ORDER BY fecha DESC
                LIMIT 1
            ", [$d['id']]);

            // Si no hay tasa en BD, intentar fallback desde API (El Toque)
            if (!$tasa) {
                $tasaApi = obtenerTasaDesdeAPI($d['codigo']);
                if ($tasaApi) {
                    // Guardar en BD para futuras consultas
                    Database::insert('tasas_cambio', [
                        'divisa_id' => $d['id'],
                        'tasa'      => $tasaApi,
                        'origen'    => 'auto',
                        'fuente'    => 'El Toque (fallback)',
                        'fecha'     => date('Y-m-d H:i:s'),
                    ]);

                    $tasa = [
                        'tasa'   => $tasaApi,
                        'origen' => 'auto',
                        'fuente' => 'El Toque (fallback)',
                        'fecha'  => date('Y-m-d H:i:s'),
                    ];
                }
            }

            if ($tasa) {
                $divisasConTasa[] = [
                    'id'        => (int) $d['id'],
                    'codigo'    => $d['codigo'],
                    'nombre'    => $d['nombre'],
                    'simbolo'   => $d['simbolo'],
                    'tasa'      => (float) $tasa['tasa'],
                    'origen'    => $tasa['origen'],
                    'fuente'    => $tasa['fuente'],
                    'fecha'     => $tasa['fecha'],
                ];
            }
        }

        Response::ok([
            'divisas' => $divisasConTasa,
        ]);
        break;

    // ============================================================
    // CONVERTIR un monto de CUP a una divisa específica
    // ============================================================
    case 'convertir':
        $montoCup = (float)($_GET['monto_cup'] ?? 0);
        $divisaCodigo = trim($_GET['divisa'] ?? '');

        if ($montoCup <= 0) Response::error('Monto inválido');
        if ($divisaCodigo === '') Response::error('Divisa requerida');

        $divisa = Database::fetchOne("
            SELECT id, codigo, nombre, simbolo
            FROM divisas
            WHERE codigo = ? AND activo = 1
        ", [$divisaCodigo]);

        if (!$divisa) Response::noEncontrado('Divisa no encontrada');

        // Última tasa
        $tasa = Database::fetchOne("
            SELECT tasa FROM tasas_cambio
            WHERE divisa_id = ?
            ORDER BY fecha DESC LIMIT 1
        ", [$divisa['id']]);

        if (!$tasa) Response::error('No hay tasa configurada para esta divisa');

        $tasaValor = (float) $tasa['tasa'];
        $montoDivisa = round($montoCup / $tasaValor, 2);

        Response::ok([
            'divisa'       => $divisa['codigo'],
            'simbolo'      => $divisa['simbolo'],
            'nombre'       => $divisa['nombre'],
            'tasa'         => $tasaValor,
            'monto_cup'    => $montoCup,
            'monto_divisa' => $montoDivisa,
        ]);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}

/**
 * Intenta obtener la tasa desde una API externa (El Toque).
 * Fallback si no hay tasa en BD.
 */
function obtenerTasaDesdeAPI(string $divisaCodigo): ?float
{
    // Solo intentar si la config permite auto-update
    if (!Config::bool('divisas_auto_update', false)) {
        return null;
    }

    // El Toque (ejemplo de endpoint público, ajustar según disponibilidad)
    $url = 'https://api.elt0que.com/trmi/v1/rates'; // URL de ejemplo

    try {
        // Intentar con cURL si está disponible
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (is_array($data)) {
                    // Buscar la divisa en la respuesta
                    if (isset($data[$divisaCodigo])) {
                        return (float) $data[$divisaCodigo];
                    }
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Error al obtener tasa desde API: ' . $e->getMessage());
    }

    return null;
}