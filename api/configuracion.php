<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';

ApiBootstrap::iniciar(['Administrador']);

$accion = $_GET['accion'] ?? 'listar';

const CATEGORIAS_CONFIG = ['seguridad', 'pos', 'turno', 'inventario', 'divisas', 'transferencias', 'notificaciones'];

switch ($accion) {

    case 'listar':
        $categorias = CATEGORIAS_CONFIG;
        $placeholders = implode(',', array_fill(0, count($categorias), '?'));

        $config = Database::fetchAll("
            SELECT clave, valor, tipo, categoria, descripcion, opciones
            FROM configuracion
            WHERE categoria IN ($placeholders)
            ORDER BY categoria, clave
        ", $categorias);

        $agrupado = [];
        foreach ($categorias as $cat) {
            $agrupado[$cat] = [];
        }
        foreach ($config as $c) {
            $agrupado[$c['categoria']][] = $c;
        }

        Response::ok($agrupado);
        break;

    case 'guardar':
        $body = jsonBody();
        $cambios = $body['cambios'] ?? [];

        if (empty($cambios) || !is_array($cambios)) {
            Response::error('No hay cambios para guardar');
        }

        $clavesPermitidas = Database::fetchAll("
            SELECT clave, tipo, categoria
            FROM configuracion
            WHERE categoria IN ('" . implode("','", CATEGORIAS_CONFIG) . "')
        ");
        $mapaClaves = [];
        foreach ($clavesPermitidas as $c) {
            $mapaClaves[$c['clave']] = $c;
        }

        $actualizados = [];
        $antes = [];
        $errores = [];

        try {
            Database::begin();

            foreach ($cambios as $clave => $valor) {
                if (!isset($mapaClaves[$clave])) continue;

                $tipo = $mapaClaves[$clave]['tipo'];

                $valorLimpio = match ($tipo) {
                    'booleano' => $valor ? '1' : '0',
                    'numero'   => (string) ((int) $valor),
                    default    => is_string($valor) ? trim($valor) : (string) $valor,
                };

                $err = validarReglaNegocio($clave, $valorLimpio);
                if ($err) {
                    $errores[$clave] = $err;
                    continue;
                }

                $antes[$clave] = Database::fetchValue(
                    "SELECT valor FROM configuracion WHERE clave = ?", [$clave]
                );

                Database::update('configuracion',
                    ['valor' => $valorLimpio, 'updated_by' => Auth::id()],
                    'clave = :clave',
                    [':clave' => $clave]
                );

                $actualizados[] = $clave;
            }

            if (!empty($errores)) {
                Database::rollback();
                Response::validacion($errores);
            }

            Auditoria::registrar('config_actualizada', 'configuracion', null, [
                'claves_cambiadas' => $actualizados,
                'antes'            => $antes,
                'despues'          => $cambios,
            ]);

            Database::commit();

            Response::ok([
                'actualizadas' => count($actualizados),
                'claves'       => $actualizados,
            ], 'Configuración guardada');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error guardar configuración: ' . $e->getMessage());
            Response::servidor('No se pudo guardar la configuración');
        }
        break;

    case 'restaurar':
        $body = jsonBody();
        $categoria = $body['categoria'] ?? '';

        if (!in_array($categoria, CATEGORIAS_CONFIG, true)) {
            Response::error('Categoría no válida');
        }

        $defaults = valoresPorDefecto($categoria);

        if (empty($defaults)) {
            Response::error('No hay valores por defecto para esta categoría');
        }

        try {
            Database::begin();

            foreach ($defaults as $clave => $valor) {
                Database::update('configuracion',
                    ['valor' => $valor, 'updated_by' => Auth::id()],
                    'clave = :clave',
                    [':clave' => $clave]
                );
            }

            Auditoria::registrar('config_restaurada', 'configuracion', null, [
                'categoria' => $categoria,
                'valores'   => $defaults,
            ]);

            Database::commit();
            Response::ok(null, 'Valores restaurados');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error restaurar config: ' . $e->getMessage());
            Response::servidor('No se pudo restaurar');
        }
        break;

    default:
        Response::error('Acción no reconocida', 404);
}

function validarReglaNegocio(string $clave, $valor): ?string
{
    switch ($clave) {
        case 'pass_longitud_min':
            $n = (int) $valor;
            if ($n < 6 || $n > 32) return 'La longitud debe estar entre 6 y 32';
            break;
        case 'pass_historial':
            $n = (int) $valor;
            if ($n < 0 || $n > 20) return 'Debe estar entre 0 y 20';
            break;
        case 'pass_caducidad_dias':
            $n = (int) $valor;
            if ($n < 0 || $n > 365) return 'Debe estar entre 0 y 365 días';
            break;
        case 'notif_polling_segundos':
            $n = (int) $valor;
            if ($n < 10 || $n > 600) return 'Debe estar entre 10 y 600 segundos';
            break;
        case 'notif_intentos_login':
            $n = (int) $valor;
            if ($n < 1 || $n > 20) return 'Debe estar entre 1 y 20 intentos';
            break;
        case 'cierre_justificacion_min':
            $n = (int) $valor;
            if ($n < 0 || $n > 500) return 'Debe estar entre 0 y 500 caracteres';
            break;
        case 'transf_comprobante_max_mb':
            $n = (int) $valor;
            if ($n < 1 || $n > 20) return 'Debe estar entre 1 y 20 MB';
            break;
        case 'transf_comprobante_retencion':
            $n = (int) $valor;
            if ($n < 0 || $n > 3650) return 'Debe estar entre 0 y 3650 días';
            break;
        case 'divisas_manual_duracion_horas':
            $n = (int) $valor;
            if ($n < 0 || $n > 720) return 'Debe estar entre 0 y 720 horas';
            break;
    }
    return null;
}

function valoresPorDefecto(string $categoria): array
{
    $defaults = [
        'seguridad' => [
            'pass_longitud_min'   => '8',
            'pass_req_mayuscula'  => '1',
            'pass_req_minuscula'  => '1',
            'pass_req_numero'     => '1',
            'pass_req_simbolo'    => '1',
            'pass_historial'      => '3',
            'pass_caducidad_dias' => '0',
        ],
        'pos' => [
            'pos_permitir_stock_negativo' => '1',
            'pos_mostrar_sin_stock'       => '1',
            'pos_modo_conteo_default'     => 'opcional',
            'pos_ticket_tamano'           => '80mm',
            'pos_imprimir_preguntar'      => '1',
        ],
        'turno' => [
            'turno_cierre_con_conteo_default' => '0',
            'turno_bloquear_si_negativos'     => '1',
            'cierre_forzar_supervisor'        => '1',
            'cierre_forzar_admin'             => '1',
            'cierre_justificacion_min'        => '20',
        ],
        'inventario' => [
            'supervisor_puede_ajustar' => '1',
            'ajustes_inmediatos'       => '1',
        ],
        'divisas' => [
            'divisas_habilitadas'          => '1',
            'divisas_auto_update'          => '1',
            'divisas_auto_frecuencia'      => 'hora',
            'divisas_auto_fuente'          => 'eltoque',
            'divisas_permitir_manual'      => '1',
            'divisas_manual_duracion_horas'=> '6',
        ],
        'transferencias' => [
            'transf_comprobante_adjunto'   => 'opcional',
            'transf_comprobante_max_mb'    => '5',
            'transf_verificacion'          => 'obligatoria',
            'transf_comprobante_retencion' => '90',
        ],
        'notificaciones' => [
            'notif_email_criticas'    => '0',
            'notif_polling_segundos'  => '60',
            'notif_intentos_login'    => '5',
            'notif_max_dropdown'      => '10',
        ],
    ];

    return $defaults[$categoria] ?? [];
}