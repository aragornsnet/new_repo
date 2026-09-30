<?php
/**
 * IPV - Conexión a base de datos (PDO singleton)
 */

require_once __DIR__ . '/../config/database.php';

class Database
{
    private static ?PDO $instance = null;

    /**
     * Devuelve la conexión PDO (singleton)
     */
    public static function get(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]);

                // ⭐ Forzar UTF-8 en la conexión
                self::$instance->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
                self::$instance->exec("SET CHARACTER SET utf8mb4");

                // Zona horaria MySQL alineada con PHP
                self::$instance->exec("SET time_zone = '" . self::offsetHorario() . "'");

            } catch (PDOException $e) {
                error_log('DB Error: ' . $e->getMessage());
                if (defined('APP_DEBUG') && APP_DEBUG) {
                    die('Error de conexión a la base de datos: ' . $e->getMessage());
                }
                die('Error de conexión a la base de datos. Contacta al administrador.');
            }
        }

        return self::$instance;
    }

    /**
     * Ejecuta una consulta preparada y devuelve el statement
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Devuelve una sola fila
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Devuelve todas las filas
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Devuelve un solo valor escalar
     */
    public static function fetchValue(string $sql, array $params = [], $default = null)
    {
        $valor = self::query($sql, $params)->fetchColumn();
        return $valor === false ? $default : $valor;
    }

    /**
     * Inserta y devuelve el ID
     */
    public static function insert(string $tabla, array $datos): int
    {
        $columnas = array_keys($datos);
        $placeholders = array_map(fn($c) => ':' . $c, $columnas);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $tabla,
            implode(', ', $columnas),
            implode(', ', $placeholders)
        );

        $params = [];
        foreach ($datos as $k => $v) {
            $params[':' . $k] = $v;
        }

        self::query($sql, $params);
        return (int) self::get()->lastInsertId();
    }

    /**
     * Actualiza filas. Devuelve el número de filas afectadas.
     */
    public static function update(string $tabla, array $datos, string $where, array $whereParams = []): int
    {
        $sets = [];
        $params = [];
        foreach ($datos as $k => $v) {
            $sets[] = "$k = :$k";
            $params[':' . $k] = $v;
        }
        foreach ($whereParams as $k => $v) {
            $params[$k] = $v;
        }

        $sql = sprintf('UPDATE %s SET %s WHERE %s', $tabla, implode(', ', $sets), $where);
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Elimina filas. Devuelve el número de filas afectadas.
     */
    public static function delete(string $tabla, string $where, array $params = []): int
    {
        $sql = sprintf('DELETE FROM %s WHERE %s', $tabla, $where);
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Inicia una transacción
     */
    public static function begin(): void
    {
        self::get()->beginTransaction();
    }

    /**
     * Confirma la transacción
     */
    public static function commit(): void
    {
        self::get()->commit();
    }

    /**
     * Revierte la transacción
     */
    public static function rollback(): void
    {
        if (self::get()->inTransaction()) {
            self::get()->rollBack();
        }
    }

    /**
     * Calcula el offset horario para MySQL a partir de la zona horaria de PHP
     */
    private static function offsetHorario(): string
    {
        $tz = date_default_timezone_get();
        $offset = (new DateTimeZone($tz))->getOffset(new DateTime('now', new DateTimeZone($tz)));
        $signo = $offset >= 0 ? '+' : '-';
        $offset = abs($offset);
        $horas = floor($offset / 3600);
        $minutos = floor(($offset % 3600) / 60);
        return sprintf('%s%02d:%02d', $signo, $horas, $minutos);
    }
}