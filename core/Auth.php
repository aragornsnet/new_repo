<?php
/**
 * IPV - Autenticación y control de sesión
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';

class Auth
{
    public static function iniciarSesion(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        if (isset($_SESSION['user']) && isset($_SESSION['ultimo_acceso'])) {
            $inactividad = time() - $_SESSION['ultimo_acceso'];

            if ($inactividad > SESSION_LIFETIME) {
                $_SESSION = [];

                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(
                        session_name(),
                        '',
                        time() - 42000,
                        $params["path"],
                        $params["domain"],
                        $params["secure"],
                        $params["httponly"]
                    );
                }
                session_destroy();
                session_start();

                $_SESSION['flash'] = [
                    'tipo'    => 'warning',
                    'mensaje' => 'Tu sesión expiró por inactividad. Inicia sesión de nuevo.',
                ];
                return;
            }

            $_SESSION['ultimo_acceso'] = time();
        }
    }

    public static function login(string $email, string $password): array
    {
        $email = trim(strtolower($email));

        $usuario = Database::fetchOne(
            "SELECT u.*, r.nombre AS rol_nombre, pv.nombre AS pv_nombre
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             LEFT JOIN puntos_venta pv ON pv.id = u.punto_venta_id
             WHERE u.email = ?",
            [$email]
        );

        if (!$usuario) {
            self::registrarIntentoFallido(null, $email);
            return ['ok' => false, 'mensaje' => 'Credenciales incorrectas'];
        }

        if (!$usuario['activo']) {
            return ['ok' => false, 'mensaje' => 'La cuenta está desactivada'];
        }

        if ($usuario['bloqueado_hasta'] && strtotime($usuario['bloqueado_hasta']) > time()) {
            $min = ceil((strtotime($usuario['bloqueado_hasta']) - time()) / 60);
            return ['ok' => false, 'mensaje' => "Cuenta bloqueada. Intenta en {$min} minuto(s)."];
        }

        if (!password_verify($password, $usuario['password'])) {
            self::registrarIntentoFallido($usuario['id'], $email);
            return ['ok' => false, 'mensaje' => 'Credenciales incorrectas'];
        }

        session_regenerate_id(true);

        $_SESSION = [];

        $_SESSION['user'] = [
            'id'             => (int) $usuario['id'],
            'nombre'         => $usuario['nombre'],
            'email'          => $usuario['email'],
            'rol_id'         => (int) $usuario['rol_id'],
            'rol'            => $usuario['rol_nombre'],
            'punto_venta_id' => $usuario['punto_venta_id'] ? (int) $usuario['punto_venta_id'] : null,
            'pv_nombre'      => $usuario['pv_nombre'],
        ];
        $_SESSION['ultimo_acceso'] = time();
        $_SESSION['login_time'] = time();

        Database::update(
            'usuarios',
            [
                'intentos_fallidos' => 0,
                'bloqueado_hasta'   => null,
                'ultimo_login'      => date('Y-m-d H:i:s'),
            ],
            'id = :id',
            [':id' => $usuario['id']]
        );

        Auditoria::registrar('login', 'usuarios', (int) $usuario['id'], [
            'email' => $email,
        ]);

        return ['ok' => true, 'usuario' => $_SESSION['user']];
    }

    public static function cerrarSesion(): void
    {
        if (self::check()) {
            Auditoria::registrar('logout', 'usuarios', self::id());
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    public static function rol(): ?string
    {
        return $_SESSION['user']['rol'] ?? null;
    }

    public static function rolId(): ?int
    {
        return $_SESSION['user']['rol_id'] ?? null;
    }

    public static function puntoVentaId(): ?int
    {
        return $_SESSION['user']['punto_venta_id'] ?? null;
    }

    public static function esAdmin(): bool
    {
        return self::rolId() === 1;
    }

    public static function esSupervisor(): bool
    {
        return self::rolId() === 2;
    }

    public static function esVendedor(): bool
    {
        return self::rolId() === 3;
    }

    /**
     * ⭐ NUEVO: rol Almacenero (id 4)
     */
    public static function esAlmacenero(): bool
    {
        return self::rolId() === 4;
    }

    public static function tieneRol(array $roles): bool
    {
        $rol = self::rol();
        return $rol !== null && in_array($rol, $roles, true);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . BASE_URL . 'views/login.php');
            exit;
        }
    }

    public static function requireRol(array $roles): void
    {
        self::requireLogin();
        if (!self::tieneRol($roles)) {
            http_response_code(403);
            die('Acceso denegado');
        }
    }

    private static function registrarIntentoFallido(?int $usuarioId, string $email): void
    {
        if (!$usuarioId) {
            Auditoria::registrar('login_fallido', 'usuarios', null, ['email' => $email]);
            return;
        }

        $usuario = Database::fetchOne("SELECT intentos_fallidos FROM usuarios WHERE id = ?", [$usuarioId]);
        $intentos = ((int) ($usuario['intentos_fallidos'] ?? 0)) + 1;

        $datos = ['intentos_fallidos' => $intentos];

        if ($intentos >= MAX_LOGIN_ATTEMPTS) {
            $datos['bloqueado_hasta'] = date('Y-m-d H:i:s', time() + (LOGIN_BLOCK_MINUTES * 60));
            $datos['intentos_fallidos'] = 0;
        }

        Database::update('usuarios', $datos, 'id = :id', [':id' => $usuarioId]);

        Auditoria::registrar('login_fallido', 'usuarios', $usuarioId, [
            'email'    => $email,
            'intentos' => $intentos,
        ]);
    }
}