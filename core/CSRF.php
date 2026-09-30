<?php
/**
 * IPV - Protección CSRF
 * Genera y valida tokens para formularios y peticiones AJAX
 */

class CSRF
{
    private const NOMBRE_TOKEN = '_csrf_token';

    /**
     * Devuelve el token actual (lo genera si no existe)
     */
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION[self::NOMBRE_TOKEN])) {
            $_SESSION[self::NOMBRE_TOKEN] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::NOMBRE_TOKEN];
    }

    /**
     * Devuelve el campo HTML oculto con el token
     */
    public static function campo(): string
    {
        return '<input type="hidden" name="' . self::NOMBRE_TOKEN . '" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Valida el token recibido por POST
     */
    public static function validar(?string $token = null): bool
    {
        if ($token === null) {
            $token = $_POST[self::NOMBRE_TOKEN] ?? '';
        }

        if (empty($token) || empty($_SESSION[self::NOMBRE_TOKEN])) {
            return false;
        }

        return hash_equals($_SESSION[self::NOMBRE_TOKEN], $token);
    }

    /**
     * Valida o aborta con error 403
     */
    public static function exigir(): void
    {
        if (!self::validar()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido o expirado']);
            exit;
        }
    }

    /**
     * Devuelve el nombre del campo (para AJAX)
     */
    public static function nombreCampo(): string
    {
        return self::NOMBRE_TOKEN;
    }

    /**
     * Devuelve el nombre de la cabecera HTTP (para AJAX)
     */
    public static function nombreCabecera(): string
    {
        return 'X-CSRF-Token';
    }

    /**
     * Valida por cabecera (para AJAX/fetch)
     */
    public static function validarCabecera(): bool
    {
        $headers = getallheaders();
        $token = $headers[self::nombreCabecera()] ?? '';
        return self::validar($token);
    }
}