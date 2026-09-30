<?php
/**
 * IPV - API de perfil de usuario
 * Permite al usuario editar su nombre, avatar y cambiar su contraseña
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';

ApiBootstrap::iniciar([]);  // cualquier logueado

$accion = $_GET['accion'] ?? 'obtener';
$usuarioId = Auth::id();

switch ($accion) {

    // ============================================================
    // OBTENER perfil del usuario actual
    // ============================================================
    case 'obtener':
        $usuario = Database::fetchOne("
            SELECT 
                u.id, u.nombre, u.email, u.avatar, u.ultimo_login, u.created_at,
                r.nombre AS rol,
                pv.nombre AS pv
            FROM usuarios u
            JOIN roles r ON r.id = u.rol_id
            LEFT JOIN puntos_venta pv ON pv.id = u.punto_venta_id
            WHERE u.id = ?
        ", [$usuarioId]);

        if (!$usuario) Response::noEncontrado('Usuario no encontrado');

        // Estadísticas del usuario
        $stats = Database::fetchOne("
            SELECT 
                COUNT(*) AS total_ventas,
                COALESCE(SUM(total), 0) AS monto_total
            FROM ventas
            WHERE usuario_id = ? AND estado = 'completada'
        ", [$usuarioId]);

        $usuario['stats'] = $stats;

        // URL completa del avatar
        $usuario['avatar_url'] = $usuario['avatar'] ? BASE_URL . $usuario['avatar'] : null;

        Response::ok($usuario);
        break;

    // ============================================================
    // ACTUALIZAR nombre
    // ============================================================
    case 'actualizar':
        $body = jsonBody();
        $nombre = trim($body['nombre'] ?? '');

        $v = new Validador(['nombre' => $nombre]);
        $v->requerido('nombre', 'nombre')->min('nombre', 3)->max('nombre', 100);

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        $usuario = Database::fetchOne("SELECT nombre FROM usuarios WHERE id = ?", [$usuarioId]);
        if (!$usuario) Response::noEncontrado('Usuario no encontrado');

        try {
            Database::update('usuarios',
                ['nombre' => $nombre],
                'id = :id',
                [':id' => $usuarioId]
            );

            // Actualizar la sesión
            $_SESSION['user']['nombre'] = $nombre;

            Auditoria::registrar('perfil_actualizado', 'usuarios', $usuarioId, [
                'antes'   => ['nombre' => $usuario['nombre']],
                'despues' => ['nombre' => $nombre],
            ]);

            Response::ok([
                'nombre' => $nombre,
            ], 'Perfil actualizado');

        } catch (Throwable $e) {
            error_log('Error actualizar perfil: ' . $e->getMessage());
            Response::servidor('No se pudo actualizar el perfil');
        }
        break;

    // ============================================================
    // SUBIR avatar
    // ============================================================
    case 'subir_avatar':
        if (!isset($_FILES['avatar'])) {
            Response::error('No se recibió ninguna imagen');
        }

        $archivo = $_FILES['avatar'];

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            Response::error('Error al subir el archivo');
        }

        // Tamaño máximo: 2 MB
        if ($archivo['size'] > 2 * 1024 * 1024) {
            Response::error('La imagen no puede superar los 2 MB');
        }

        // Validar extensión
        $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $formatosPermitidos = ['png', 'jpg', 'jpeg', 'webp'];

        if (!in_array($ext, $formatosPermitidos, true)) {
            Response::error('Formato no permitido. Se aceptan: ' . implode(', ', $formatosPermitidos));
        }

        // Validar MIME real
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeReal = finfo_file($finfo, $archivo['tmp_name']);
            finfo_close($finfo);

            $mimesPermitidos = ['image/png', 'image/jpeg', 'image/webp'];
            if (!in_array($mimeReal, $mimesPermitidos, true)) {
                Response::error('El archivo no parece ser una imagen válida');
            }
        }

        // Crear carpeta si no existe
        $carpetaDestino = __DIR__ . '/../public/uploads/avatars/';
        if (!is_dir($carpetaDestino)) {
            @mkdir($carpetaDestino, 0755, true);
        }

        if (!is_writable($carpetaDestino)) {
            Response::servidor('La carpeta de avatars no tiene permisos de escritura');
        }

        // Nombre único
        $nombreArchivo = 'avatar_' . $usuarioId . '_' . time() . '.' . $ext;
        $rutaCompleta = $carpetaDestino . $nombreArchivo;

        if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
            Response::servidor('No se pudo guardar la imagen');
        }

        // Eliminar avatar anterior si existe
        $anterior = Database::fetchValue(
            "SELECT avatar FROM usuarios WHERE id = ?",
            [$usuarioId]
        );
        if ($anterior) {
            $rutaAnterior = __DIR__ . '/../' . $anterior;
            if (file_exists($rutaAnterior)) {
                @unlink($rutaAnterior);
            }
        }

        // Ruta relativa
        $rutaRelativa = 'public/uploads/avatars/' . $nombreArchivo;

        try {
            Database::update('usuarios',
                ['avatar' => $rutaRelativa],
                'id = :id',
                [':id' => $usuarioId]
            );

            // Actualizar la sesión
            $_SESSION['user']['avatar'] = $rutaRelativa;

            Auditoria::registrar('perfil_avatar_subido', 'usuarios', $usuarioId, [
                'archivo' => $rutaRelativa,
            ]);

            Response::ok([
                'avatar' => $rutaRelativa,
                'url' => BASE_URL . $rutaRelativa,
            ], 'Foto de perfil actualizada');

        } catch (Throwable $e) {
            // Eliminar el archivo recién subido si falla la BD
            @unlink($rutaCompleta);
            error_log('Error subir avatar: ' . $e->getMessage());
            Response::servidor('No se pudo guardar el avatar');
        }
        break;

    // ============================================================
    // ELIMINAR avatar
    // ============================================================
    case 'eliminar_avatar':
        $avatar = Database::fetchValue(
            "SELECT avatar FROM usuarios WHERE id = ?",
            [$usuarioId]
        );

        if ($avatar) {
            $ruta = __DIR__ . '/../' . $avatar;
            if (file_exists($ruta)) {
                @unlink($ruta);
            }
        }

        Database::update('usuarios',
            ['avatar' => null],
            'id = :id',
            [':id' => $usuarioId]
        );

        $_SESSION['user']['avatar'] = null;

        Auditoria::registrar('perfil_avatar_eliminado', 'usuarios', $usuarioId);

        Response::ok(null, 'Foto de perfil eliminada');
        break;

    // ============================================================
    // CAMBIAR contraseña
    // ============================================================
    case 'cambiar_password':
        $body = jsonBody();
        $passwordActual = $body['password_actual'] ?? '';
        $passwordNueva = $body['password_nueva'] ?? '';
        $passwordConfirmar = $body['password_confirmar'] ?? '';

        // Validaciones básicas
        $v = new Validador([
            'password_actual' => $passwordActual,
            'password_nueva' => $passwordNueva,
            'password_confirmar' => $passwordConfirmar,
        ]);
        $v->requerido('password_actual', 'contraseña actual');
        $v->requerido('password_nueva', 'nueva contraseña')->password('password_nueva', 'nueva contraseña');
        $v->iguales('password_nueva', 'password_confirmar', 'Las contraseñas no coinciden');

        if ($v->falla()) Response::validacion($v->erroresPlanos());

        // Verificar contraseña actual
        $usuario = Database::fetchOne(
            "SELECT password FROM usuarios WHERE id = ?",
            [$usuarioId]
        );

        if (!$usuario) Response::noEncontrado('Usuario no encontrado');

        if (!password_verify($passwordActual, $usuario['password'])) {
            Response::validacion(['password_actual' => 'La contraseña actual es incorrecta']);
        }

        // Verificar que la nueva no sea igual a la actual
        if (password_verify($passwordNueva, $usuario['password'])) {
            Response::validacion(['password_nueva' => 'La nueva contraseña no puede ser igual a la actual']);
        }

        try {
            // Actualizar la contraseña
            $hash = password_hash($passwordNueva, PASSWORD_DEFAULT);
            Database::update('usuarios',
                ['password' => $hash],
                'id = :id',
                [':id' => $usuarioId]
            );

            Auditoria::registrar('perfil_password_cambiada', 'usuarios', $usuarioId);

            Response::ok(null, 'Contraseña cambiada correctamente');

        } catch (Throwable $e) {
            error_log('Error cambiar password: ' . $e->getMessage());
            Response::servidor('No se pudo cambiar la contraseña');
        }
        break;

    default:
        Response::error('Acción no reconocida', 404);
}