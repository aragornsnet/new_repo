<?php
/**
 * IPV - API de autenticación
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Validador.php';
require_once __DIR__ . '/../core/Auditoria.php';
require_once __DIR__ . '/../core/Notificacion.php';

// Pública: sin login, sin licencia
ApiBootstrap::iniciarPublica();

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    case 'login':
        if (metodoHttp() !== 'POST') {
            Response::error('Método no permitido', 405);
        }

        $body = jsonBody();
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';

        $v = new Validador(['email' => $email, 'password' => $password]);
        $v->requerido('email', 'correo')->email('email');
        $v->requerido('password', 'contraseña');

        if ($v->falla()) {
            Response::validacion($v->erroresPlanos());
        }

        // ⭐ Verificar si el login va a fallar por bloqueo o por credenciales
        //    antes de intentar, para poder notificar adecuadamente
        $usuarioPrevio = Database::fetchOne(
            "SELECT id, nombre, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE email = ?",
            [strtolower(trim($email))]
        );

        $res = Auth::login($email, $password);

        if (!$res['ok']) {
            // ⭐ Si el usuario existe y acaba de ser bloqueado, notificar a admins
            if ($usuarioPrevio) {
                $usuarioActual = Database::fetchOne(
                    "SELECT intentos_fallidos, bloqueado_hasta FROM usuarios WHERE id = ?",
                    [$usuarioPrevio['id']]
                );

                // Si acaba de ser bloqueado (bloqueado_hasta > ahora)
                if (!empty($usuarioActual['bloqueado_hasta']) 
                    && strtotime($usuarioActual['bloqueado_hasta']) > time()
                    && strtotime($usuarioActual['bloqueado_hasta']) > strtotime($usuarioPrevio['bloqueado_hasta'] ?? '1970-01-01')
                ) {
                    Notificacion::crearParaAdmins(
                        'cuenta_bloqueada',
                        'Cuenta bloqueada por intentos fallidos',
                        $usuarioPrevio['nombre'] . ' (' . $email . ') - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'),
                        'views/admin/usuarios.php',
                        'shield-lock-fill',
                        'danger'
                    );
                }
            }

            Response::error($res['mensaje'], 401);
        }

        Response::ok([
            'id'     => $res['usuario']['id'],
            'nombre' => $res['usuario']['nombre'],
            'rol'    => $res['usuario']['rol'],
        ], 'Inicio de sesión exitoso');
        break;

    case 'logout':
        Auth::cerrarSesion();
        Response::ok(null, 'Sesión cerrada');
        break;

    case 'check':
        if (Auth::check()) {
            Response::ok(Auth::user(), 'Sesión activa');
        }
        Response::error('No autenticado', 401);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}