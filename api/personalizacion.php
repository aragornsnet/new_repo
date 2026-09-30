<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';

ApiBootstrap::iniciar(['Administrador']);

$accion = $_GET['accion'] ?? 'listar';

switch ($accion) {

    case 'listar':
        $config = Database::fetchAll("
            SELECT clave, valor, tipo, categoria, descripcion, opciones
            FROM configuracion
            ORDER BY categoria, clave
        ");

        $agrupado = [];
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

        $clavesValidas = Database::fetchAll("SELECT clave, tipo FROM configuracion");
        $mapaValidas = [];
        foreach ($clavesValidas as $c) {
            $mapaValidas[$c['clave']] = $c['tipo'];
        }

        $actualizados = [];
        $antes = [];

        try {
            Database::begin();

            foreach ($cambios as $clave => $valor) {
                if (!isset($mapaValidas[$clave])) continue;

                $valorAnterior = Database::fetchValue(
                    "SELECT valor FROM configuracion WHERE clave = ?", [$clave]
                );
                $antes[$clave] = $valorAnterior;

                $tipo = $mapaValidas[$clave];
                $valorLimpio = match ($tipo) {
                    'booleano' => $valor ? '1' : '0',
                    'numero'   => (string) ((int) $valor),
                    default    => is_string($valor) ? trim($valor) : (string) $valor,
                };

                Database::update('configuracion',
                    ['valor' => $valorLimpio, 'updated_by' => Auth::id()],
                    'clave = :clave',
                    [':clave' => $clave]
                );

                $actualizados[] = $clave;
            }

            Auditoria::registrar('personalizacion_actualizada', 'configuracion', null, [
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
            error_log('Error guardar personalización: ' . $e->getMessage());
            Response::servidor('No se pudo guardar la configuración');
        }
        break;

    case 'subir_imagen':
        if (!isset($_FILES['archivo'])) {
            Response::error('No se recibió ninguna imagen');
        }

        $tipo = $_GET['tipo'] ?? '';
        $tiposValidos = [
            'logo'    => ['clave' => 'empresa_logo',      'carpeta' => 'logos',    'max_mb' => 3,  'formatos' => ['png', 'jpg', 'jpeg', 'svg', 'webp']],
            'favicon' => ['clave' => 'empresa_favicon',   'carpeta' => 'favicons', 'max_mb' => 1,  'formatos' => ['png', 'ico', 'svg']],
            'fondo'   => ['clave' => 'login_fondo',       'carpeta' => 'fondos',   'max_mb' => 5,  'formatos' => ['png', 'jpg', 'jpeg', 'webp']],
        ];

        if (!isset($tiposValidos[$tipo])) {
            Response::error('Tipo de imagen no válido');
        }

        $cfg = $tiposValidos[$tipo];
        $archivo = $_FILES['archivo'];

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            Response::error('Error al subir el archivo (código ' . $archivo['error'] . ')');
        }

        $maxBytes = $cfg['max_mb'] * 1024 * 1024;
        if ($archivo['size'] > $maxBytes) {
            Response::error("El archivo supera el tamaño máximo de {$cfg['max_mb']} MB");
        }

        $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $cfg['formatos'], true)) {
            Response::error('Formato no permitido');
        }

        $mimesPermitidos = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp', 'image/x-icon'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeReal = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeReal, $mimesPermitidos, true)) {
            Response::error('El archivo no parece ser una imagen válida');
        }

        $carpetaDestino = __DIR__ . '/../public/uploads/' . $cfg['carpeta'] . '/';
        if (!is_dir($carpetaDestino)) @mkdir($carpetaDestino, 0755, true);

        $nombreArchivo = $tipo . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $rutaCompleta = $carpetaDestino . $nombreArchivo;

        if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
            Response::servidor('No se pudo guardar el archivo');
        }

        $rutaRelativa = 'public/uploads/' . $cfg['carpeta'] . '/' . $nombreArchivo;

        $anterior = Database::fetchValue(
            "SELECT valor FROM configuracion WHERE clave = ?", [$cfg['clave']]
        );
        if ($anterior) {
            $rutaAnterior = __DIR__ . '/../' . $anterior;
            if (file_exists($rutaAnterior)) @unlink($rutaAnterior);
        }

        Config::set($cfg['clave'], $rutaRelativa, Auth::id());

        Auditoria::registrar('personalizacion_imagen_subida', 'configuracion', null, [
            'tipo' => $tipo, 'archivo' => $rutaRelativa,
        ]);

        Response::ok(['ruta' => $rutaRelativa], 'Imagen actualizada');
        break;

    case 'eliminar_imagen':
        $body = jsonBody();
        $clave = $body['clave'] ?? '';

        $clavesValidas = ['empresa_logo', 'empresa_favicon', 'login_fondo'];
        if (!in_array($clave, $clavesValidas, true)) {
            Response::error('Clave no válida');
        }

        $ruta = Database::fetchValue("SELECT valor FROM configuracion WHERE clave = ?", [$clave]);

        if ($ruta) {
            $rutaCompleta = __DIR__ . '/../' . $ruta;
            if (file_exists($rutaCompleta)) @unlink($rutaCompleta);
        }

        Config::set($clave, '', Auth::id());

        Auditoria::registrar('personalizacion_imagen_eliminada', 'configuracion', null, [
            'clave' => $clave,
        ]);

        Response::ok(null, 'Imagen eliminada');
        break;

    case 'restaurar':
        $body = jsonBody();
        $categoria = $body['categoria'] ?? '';

        if ($categoria === '') Response::error('Categoría requerida');

        $defaults = [
            'identidad' => [
                'empresa_nombre'      => NEGOCIO_NOMBRE,
                'empresa_eslogan'     => 'Calidad y servicio',
                'empresa_descripcion' => '',
            ],
            'colores' => [
                'color_primario'   => '#2563eb',
                'color_secundario' => '#1e40af',
                'color_acento'     => '#f59e0b',
                'color_exito'      => '#16a34a',
                'color_peligro'    => '#dc2626',
            ],
            'moneda' => [
                'moneda_simbolo'   => MONEDA_SIMBOLO,
                'moneda_codigo'    => MONEDA_CODIGO,
                'moneda_posicion'  => 'antes',
                'moneda_decimales' => '2',
                'formato_fecha'    => FORMATO_FECHA,
                'zona_horaria'     => ZONA_HORARIA,
            ],
            'tema' => [
                'tema_modo'             => 'claro',
                'tipografia'            => 'Inter',
                'permitir_cambio_tema'  => '1',
                'login_mensaje'         => 'Bienvenido al sistema',
            ],
        ];

        if (!isset($defaults[$categoria])) {
            Response::error('Categoría sin valores por defecto');
        }

        try {
            Database::begin();

            foreach ($defaults[$categoria] as $clave => $valor) {
                Database::update('configuracion',
                    ['valor' => $valor, 'updated_by' => Auth::id()],
                    'clave = :clave',
                    [':clave' => $clave]
                );
            }

            Auditoria::registrar('personalizacion_restaurada', 'configuracion', null, [
                'categoria' => $categoria,
                'valores'   => $defaults[$categoria],
            ]);

            Database::commit();
            Response::ok(null, 'Valores restaurados');

        } catch (Throwable $e) {
            Database::rollback();
            error_log('Error restaurar: ' . $e->getMessage());
            Response::servidor('No se pudo restaurar');
        }
        break;

    default:
        Response::error('Acción no reconocida', 404);
}