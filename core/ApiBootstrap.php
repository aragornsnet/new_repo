<?php
/**
 * IPV - Bootstrap común para las APIs
 * Carga las dependencias base y verifica la licencia.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/CSRF.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Licencia.php';
require_once __DIR__ . '/Middleware.php';
require_once __DIR__ . '/helpers.php';

/**
 * Inicializa una API:
 *   - Inicia sesión
 *   - Verifica CSRF si es método de escritura
 *   - Verifica la licencia (excepto en rutas exentas)
 *   - Exige login
 *
 * Uso:
 *   require_once __DIR__ . '/../core/ApiBootstrap.php';
 *   ApiBootstrap::iniciar(['Vendedor', 'Administrador']);
 */
class ApiBootstrap
{
    /**
     * Inicializa la API.
     *
     * @param array $rolesPermitidos Roles permitidos. Vacío = cualquier usuario logueado.
     * @param bool  $requiereCsrf    Si valida CSRF en POST/PUT/PATCH/DELETE.
     */
    public static function iniciar(array $rolesPermitidos = [], bool $requiereCsrf = true): void
    {
        Auth::iniciarSesion();

        // 1) Verificar licencia (excepto rutas exentas)
        $ruta = $_SERVER['REQUEST_URI'] ?? '';
        $exentas = ['/api/licencia', '/api/auth'];
        $esExenta = false;
        foreach ($exentas as $ex) {
            if (strpos($ruta, $ex) !== false) {
                $esExenta = true;
                break;
            }
        }

        if (!$esExenta) {
            self::verificarLicenciaApi();
        }

        // 2) Exigir login
        if (!Auth::check()) {
            Response::noAutorizado('No autenticado');
        }

        // 3) Exigir rol si se especificó
        if (!empty($rolesPermitidos) && !Auth::tieneRol($rolesPermitidos)) {
            Response::prohibido('Acceso denegado');
        }

        // 4) Verificar CSRF en métodos de escritura
        if ($requiereCsrf) {
            $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
            if (in_array($metodo, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                if (!CSRF::validarCabecera() && !CSRF::validar()) {
                    Response::prohibido('Token CSRF inválido');
                }
            }
        }
    }

    /**
     * Inicializa una API pública (login, etc.).
     * NO verifica login, NO verifica licencia.
     * Solo valida CSRF en métodos de escritura.
     */
    public static function iniciarPublica(bool $requiereCsrf = true): void
    {
        Auth::iniciarSesion();

        if ($requiereCsrf) {
            $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
            if (in_array($metodo, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                if (!CSRF::validarCabecera() && !CSRF::validar()) {
                    Response::prohibido('Token CSRF inválido');
                }
            }
        }
    }

    /**
     * Verifica la licencia y responde con JSON si no es válida.
     */
    private static function verificarLicenciaApi(): void
    {
        $estado = Licencia::verificar();

        if (!$estado['valida']) {
            Response::json([
                'success' => false,
                'message' => $estado['mensaje'] ?? 'Licencia inválida',
                'licencia_invalida' => true,
                'licencia_tipo' => $estado['tipo'] ?? 'invalida',
            ], 403);
        }

        // Guardar aviso si quedan pocos días
        if ($estado['tipo'] === 'trial' && $estado['dias_restantes'] <= 5) {
            $_SESSION['licencia_aviso'] = $estado['dias_restantes'];
        }
    }
}