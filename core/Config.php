<?php
/**
 * IPV - Lectura y escritura de la configuración (tabla configuracion)
 * Con caché en memoria para no consultar la BD en cada petición.
 */

require_once __DIR__ . '/Database.php';

class Config
{
    private static ?array $cache = null;

    /**
     * Carga toda la configuración en caché
     */
    private static function cargar(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];
        try {
            $filas = Database::fetchAll("SELECT clave, valor, tipo FROM configuracion");
            foreach ($filas as $f) {
                self::$cache[$f['clave']] = self::castValor($f['valor'], $f['tipo']);
            }
        } catch (Throwable $e) {
            error_log('Config load error: ' . $e->getMessage());
            self::$cache = [];
        }
    }

    /**
     * Devuelve el valor de una clave
     */
    public static function get(string $clave, $default = null)
    {
        self::cargar();
        return self::$cache[$clave] ?? $default;
    }

    /**
     * Devuelve un booleano
     */
    public static function bool(string $clave, bool $default = false): bool
    {
        $v = self::get($clave, $default ? '1' : '0');
        return (bool) $v;
    }

    /**
     * Devuelve un entero
     */
    public static function int(string $clave, int $default = 0): int
    {
        return (int) self::get($clave, $default);
    }

    /**
     * Establece un valor (persiste en BD)
     */
    public static function set(string $clave, $valor, ?int $usuarioId = null): bool
    {
        $valorStr = is_array($valor) ? json_encode($valor) : (string) $valor;

        $existe = Database::fetchValue(
            "SELECT COUNT(*) FROM configuracion WHERE clave = ?",
            [$clave]
        );

        if ($existe) {
            Database::update(
                'configuracion',
                ['valor' => $valorStr, 'updated_by' => $usuarioId],
                'clave = :clave',
                [':clave' => $clave]
            );
        } else {
            Database::insert('configuracion', [
                'clave'      => $clave,
                'valor'      => $valorStr,
                'tipo'       => 'texto',
                'categoria'  => 'general',
                'updated_by' => $usuarioId,
            ]);
        }

        // Invalidar caché
        self::$cache = null;
        return true;
    }

    /**
     * Devuelve toda la configuración agrupada por categoría
     */
    public static function porCategoria(string $categoria): array
    {
        return Database::fetchAll(
            "SELECT clave, valor, tipo, descripcion, opciones 
             FROM configuracion 
             WHERE categoria = ? 
             ORDER BY clave",
            [$categoria]
        );
    }

    /**
     * Convierte el valor según su tipo
     */
    private static function castValor($valor, string $tipo)
    {
        return match ($tipo) {
            'numero', 'booleano' => is_numeric($valor) ? (int) $valor : 0,
            default              => $valor,
        };
    }
}