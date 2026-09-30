<?php
/**
 * IPV - Respuestas JSON estandarizadas para la API
 */

class Response
{
    /**
     * Envía una respuesta JSON y termina
     */
    public static function json($datos, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Respuesta exitosa
     */
    public static function ok($datos = null, string $mensaje = 'OK'): void
    {
        self::json([
            'success' => true,
            'message' => $mensaje,
            'data'    => $datos,
        ]);
    }

    /**
     * Error genérico
     */
    public static function error(string $mensaje = 'Error', int $codigo = 400, $datos = null): void
    {
        self::json([
            'success' => false,
            'message' => $mensaje,
            'data'    => $datos,
        ], $codigo);
    }

    /**
     * Error 401 (no autenticado)
     */
    public static function noAutorizado(string $mensaje = 'No autenticado'): void
    {
        self::error($mensaje, 401);
    }

    /**
     * Error 403 (sin permisos)
     */
    public static function prohibido(string $mensaje = 'Acceso denegado'): void
    {
        self::error($mensaje, 403);
    }

    /**
     * Error 404
     */
    public static function noEncontrado(string $mensaje = 'Recurso no encontrado'): void
    {
        self::error($mensaje, 404);
    }

    /**
     * Error 422 (validación)
     */
    public static function validacion(array $errores, string $mensaje = 'Errores de validación'): void
    {
        self::json([
            'success' => false,
            'message' => $mensaje,
            'errors'  => $errores,
        ], 422);
    }

    /**
     * Error 500
     */
    public static function servidor(string $mensaje = 'Error interno del servidor'): void
    {
        self::error($mensaje, 500);
    }
}