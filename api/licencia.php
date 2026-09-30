<?php
/**
 * IPV - API de licencias
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Licencia.php';
require_once __DIR__ . '/../core/Auditoria.php';
require_once __DIR__ . '/../core/Notificacion.php';

// Exige login y rol admin, pero NO verifica licencia
ApiBootstrap::iniciar(['Administrador'], true, false);

$accion = $_GET['accion'] ?? 'estado';

switch ($accion) {

    // ============================================================
    // ESTADO de la licencia
    // ============================================================
    case 'estado':
        $estado = Licencia::verificar();
        $estado['install_id'] = Licencia::idInstalacion();
        Response::ok($estado);
        break;

    // ============================================================
    // ID de instalación
    // ============================================================
    case 'install_id':
        Response::ok([
            'install_id' => Licencia::idInstalacion(),
        ]);
        break;

    // ============================================================
    // ACTIVAR licencia
    // ============================================================
    case 'activar':
        $body = jsonBody();
        $licencia = trim($body['licencia'] ?? '');

        if ($licencia === '') {
            Response::validacion(['licencia' => 'El código de licencia es requerido']);
        }

        if (!Licencia::verificarFirma($licencia)) {
            Response::validacion(['licencia' => 'La firma de la licencia no es válida']);
        }

        $datos = Licencia::decodificarLicencia($licencia);
        if (!$datos) {
            Response::validacion(['licencia' => 'El formato de la licencia es inválido']);
        }

        $installIdActual = Licencia::installId();
        if (!hash_equals($installIdActual, $datos['install_id'] ?? '')) {
            Response::validacion([
                'licencia' => 'Esta licencia no corresponde a esta instalación. ' .
                              'El hardware ha cambiado o la carpeta fue copiada a otro servidor.'
            ]);
        }

        if (isset($datos['expira']) && strtotime($datos['expira']) < time()) {
            Response::validacion(['licencia' => 'Esta licencia ya expiró el ' . $datos['expira']]);
        }

        // Detectar si es una activación nueva o una renovación
        $licenciaPrevia = Licencia::leerLicencia();
        $esRenovacion = $licenciaPrevia !== null;

        if (!Licencia::guardarLicencia($licencia)) {
            Response::servidor('No se pudo guardar la licencia. Verifica permisos de storage/');
        }

        // Auditoría
        Auditoria::registrar('licencia_activada', 'sistema', null, [
            'cliente' => $datos['cliente'] ?? '',
            'expira'  => $datos['expira'] ?? '',
            'renovacion' => $esRenovacion,
        ]);

        // ⭐ Notificar a todos los admins
        $titulo = $esRenovacion ? 'Licencia renovada' : 'Licencia activada';
        $mensaje = ($datos['cliente'] ? 'Cliente: ' . $datos['cliente'] . ' · ' : '')
                 . 'Expira: ' . ($datos['expira'] ?? 'N/A');

        Notificacion::crearParaAdmins(
            $esRenovacion ? 'licencia_renovada' : 'licencia_activada',
            $titulo,
            $mensaje,
            'views/licencia.php',
            'patch-check-fill',
            'success'
        );

        // Respuesta
        Response::ok([
            'cliente' => $datos['cliente'] ?? '',
            'expira'  => $datos['expira'] ?? '',
            'dias'    => Licencia::verificar()['dias_restantes'],
            'renovacion' => $esRenovacion,
        ], $esRenovacion ? 'Licencia renovada correctamente' : 'Licencia activada correctamente');
        break;

    // ============================================================
    // ELIMINAR licencia (para pruebas / reventa)
    // ============================================================
    case 'eliminar':
        $licencia = Licencia::leerLicencia();
        if (!$licencia) {
            Response::error('No hay licencia activa');
        }

        $ruta = __DIR__ . '/../storage/licencia.lic';
        if (file_exists($ruta) && !@unlink($ruta)) {
            Response::servidor('No se pudo eliminar la licencia');
        }

        Auditoria::registrar('licencia_eliminada', 'sistema', null, []);

        Notificacion::crearParaAdmins(
            'licencia_eliminada',
            'Licencia eliminada',
            'Se ha eliminado la licencia activa del sistema.',
            'views/licencia.php',
            'x-circle-fill',
            'warning'
        );

        Response::ok(null, 'Licencia eliminada');
        break;

    default:
        Response::error('Acción no reconocida', 404);
}