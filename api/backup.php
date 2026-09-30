<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(300);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Auditoria.php';
require_once __DIR__ . '/../core/Notificacion.php';
require_once __DIR__ . '/../core/Backup.php';

ApiBootstrap::iniciar(['Administrador']);

$accion = $_GET['accion'] ?? 'listar';

switch ($accion) {

    case 'listar':
        Response::ok([
            'backups'      => Backup::listar(),
            'total_tamano' => Backup::tamanoTotal(),
        ]);
        break;

    case 'generar':
        try {
            $ruta = Backup::generarBD();
            if (!file_exists($ruta)) Response::servidor('No se pudo generar el backup');

            $tamano = filesize($ruta);
            $nombre = basename($ruta);

            Auditoria::registrar('backup_creado', 'sistema', null, [
                'archivo' => $nombre, 'tamano' => $tamano,
            ]);

            Notificacion::crearParaAdmins(
                'backup_creado', 'Backup generado',
                $nombre . ' (' . bytesLegible($tamano) . ')',
                'views/admin/backup.php', 'hdd-fill', 'info'
            );

            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $nombre . '"');
            header('Content-Length: ' . $tamano);
            readfile($ruta);
            exit;

        } catch (Throwable $e) {
            error_log('Error generar backup: ' . $e->getMessage());
            Response::servidor('Error al generar el backup: ' . $e->getMessage());
        }
        break;

    case 'generar_guardar':
        try {
            $ruta = Backup::generarBD();
            if (!file_exists($ruta)) Response::servidor('No se pudo generar el backup');

            $tamano = filesize($ruta);
            $nombre = basename($ruta);

            Auditoria::registrar('backup_creado', 'sistema', null, [
                'archivo' => $nombre, 'tamano' => $tamano,
            ]);

            Notificacion::crearParaAdmins(
                'backup_creado', 'Backup generado',
                $nombre . ' (' . bytesLegible($tamano) . ')',
                'views/admin/backup.php', 'hdd-fill', 'info'
            );

            Response::ok(['archivo' => $nombre, 'tamano' => $tamano], 'Backup generado correctamente');

        } catch (Throwable $e) {
            error_log('Error generar backup: ' . $e->getMessage());
            Response::servidor('Error al generar el backup: ' . $e->getMessage());
        }
        break;

    case 'restaurar':
        if (!isset($_FILES['archivo'])) Response::error('No se recibió ningún archivo');

        $archivo = $_FILES['archivo'];
        if ($archivo['error'] !== UPLOAD_ERR_OK) Response::error('Error al subir el archivo');

        $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if ($ext !== 'sql') Response::error('Solo se aceptan archivos .sql');
        if ($archivo['size'] > 50 * 1024 * 1024) Response::error('El archivo supera los 50 MB');

        $confirmacion = $_POST['confirmacion'] ?? '';
        if ($confirmacion !== 'RESTAURAR') Response::error('Debes escribir RESTAURAR para confirmar');

        $tmp = sys_get_temp_dir() . '/ipv_restore_' . time() . '.sql';
        if (!move_uploaded_file($archivo['tmp_name'], $tmp)) Response::servidor('No se pudo procesar el archivo');

        try {
            $backupPrevio = Backup::generarBD();
            Backup::restaurar($tmp);
            @unlink($tmp);

            $nombrePrevio = basename($backupPrevio);

            Auditoria::registrar('backup_restaurado', 'sistema', null, [
                'archivo_original' => $archivo['name'],
                'backup_previo'    => $nombrePrevio,
                'tamano'           => $archivo['size'],
            ]);

            Notificacion::crearParaAdmins(
                'backup_restaurado', 'Backup restaurado',
                $archivo['name'] . ' (backup previo: ' . $nombrePrevio . ')',
                'views/admin/backup.php', 'arrow-counterclockwise', 'warning'
            );

            Response::ok(['backup_previo' => $nombrePrevio], 'Backup restaurado correctamente.');

        } catch (Throwable $e) {
            @unlink($tmp);
            error_log('Error restaurar backup: ' . $e->getMessage());
            Response::servidor('Error al restaurar: ' . $e->getMessage());
        }
        break;

    case 'descargar':
        $nombre = $_GET['archivo'] ?? '';
        if ($nombre === '') Response::error('Nombre de archivo requerido');

        $ruta = Backup::ruta($nombre);
        if (!file_exists($ruta)) Response::noEncontrado('Backup no encontrado');

        $real = realpath($ruta);
        $permitida = realpath(__DIR__ . '/../storage/backups/');
        if (strpos($real, $permitida) !== 0) Response::prohibido('Acceso denegado');

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;

    case 'eliminar':
        $body = jsonBody();
        $nombre = $body['archivo'] ?? '';
        if ($nombre === '') Response::error('Nombre de archivo requerido');

        if (!Backup::eliminar($nombre)) Response::error('No se pudo eliminar el backup');

        Auditoria::registrar('backup_eliminado', 'sistema', null, [
            'archivo' => basename($nombre),
        ]);

        Response::ok(null, 'Backup eliminado');
        break;

    default:
        Response::error('Acción no reconocida', 404);
}