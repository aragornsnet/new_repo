<?php
/**
 * IPV - Gestión de backups
 * Genera y restaura dumps SQL usando mysqldump (si está disponible) o PHP puro
 */

require_once __DIR__ . '/Database.php';

class Backup
{
    private const BACKUP_DIR = __DIR__ . '/../storage/backups/';

    /**
     * Genera un backup completo de la base de datos.
     * Devuelve la ruta al archivo creado.
     */
    public static function generarBD(): string
    {
        // Asegurar carpeta
        if (!is_dir(self::BACKUP_DIR)) {
            mkdir(self::BACKUP_DIR, 0755, true);
        }

        $timestamp = date('Y-m-d_His');
        $nombreArchivo = "backup_{$timestamp}.sql";
        $rutaCompleta = self::BACKUP_DIR . $nombreArchivo;

        // Intentar con mysqldump primero
        $ok = self::exportarConMysqldump($rutaCompleta);

        if (!$ok) {
            // Fallback: exportar con PHP puro
            self::exportarConPHP($rutaCompleta);
        }

        return $rutaCompleta;
    }

    /**
     * Exporta usando mysqldump (más rápido y robusto)
     */
    private static function exportarConMysqldump(string $rutaArchivo): bool
    {
        // Verificar si mysqldump existe
        $check = @shell_exec('which mysqldump 2>&1');
        if (empty($check) || strpos($check, 'not found') !== false) {
            return false;
        }

        $cmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s %s --single-transaction --routines --triggers --events --default-character-set=utf8mb4 %s > %s 2>&1',
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_PORT),
            escapeshellarg(DB_USER),
            DB_PASS !== '' ? '--password=' . escapeshellarg(DB_PASS) : '',
            escapeshellarg(DB_NAME),
            escapeshellarg($rutaArchivo)
        );

        @shell_exec($cmd);

        return file_exists($rutaArchivo) && filesize($rutaArchivo) > 0;
    }

    /**
     * Exporta con PHP puro (fallback si mysqldump no está)
     * Incluye estructura y datos.
     */
    private static function exportarConPHP(string $rutaArchivo): void
    {
        $pdo = Database::get();
        $handle = fopen($rutaArchivo, 'w');

        fwrite($handle, "-- IPV Backup\n");
        fwrite($handle, "-- Generado: " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- Base de datos: " . DB_NAME . "\n\n");

        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n");
        fwrite($handle, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
        fwrite($handle, "SET time_zone = '+00:00';\n\n");

        // Listar tablas
        $tablas = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tablas as $tabla) {
            // Estructura
            $create = $pdo->query("SHOW CREATE TABLE `$tabla`")->fetch();
            fwrite($handle, "\n-- ----------------------------\n");
            fwrite($handle, "-- Tabla: $tabla\n");
            fwrite($handle, "-- ----------------------------\n");
            fwrite($handle, "DROP TABLE IF EXISTS `$tabla`;\n");
            fwrite($handle, $create['Create Table'] . ";\n\n");

            // Datos
            $filas = $pdo->query("SELECT * FROM `$tabla`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($filas)) {
                foreach ($filas as $fila) {
                    $columnas = array_map(fn($c) => "`$c`", array_keys($fila));
                    $valores = array_map(function ($v) use ($pdo) {
                        if ($v === null) return 'NULL';
                        if (is_int($v) || is_float($v)) return $v;
                        return $pdo->quote($v);
                    }, array_values($fila));

                    fwrite($handle, "INSERT INTO `$tabla` (" . implode(', ', $columnas) . ") VALUES (" . implode(', ', $valores) . ");\n");
                }
                fwrite($handle, "\n");
            }
        }

        // Vistas
        $vistas = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($vistas as $vista) {
            $create = $pdo->query("SHOW CREATE VIEW `$vista`")->fetch();
            fwrite($handle, "\n-- ----------------------------\n");
            fwrite($handle, "-- Vista: $vista\n");
            fwrite($handle, "-- ----------------------------\n");
            fwrite($handle, "DROP VIEW IF EXISTS `$vista`;\n");
            fwrite($handle, $create['Create View'] . ";\n\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");

        fclose($handle);
    }

    /**
     * Restaura un backup desde un archivo SQL
     * Devuelve true si tuvo éxito.
     */
    public static function restaurar(string $rutaArchivo): bool
    {
        if (!file_exists($rutaArchivo)) {
            throw new Exception('El archivo de backup no existe');
        }

        $pdo = Database::get();
        $sql = file_get_contents($rutaArchivo);

        if (empty($sql)) {
            throw new Exception('El archivo de backup está vacío');
        }

        // Deshabilitar chequeo de claves foráneas
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        try {
            // Ejecutar el SQL (puede tener múltiples statements)
            // Usamos exec directamente porque ya viene de un dump confiable
            $pdo->exec($sql);

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            return true;

        } catch (PDOException $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            throw new Exception('Error al restaurar: ' . $e->getMessage());
        }
    }

    /**
     * Lista los backups guardados en el servidor
     */
    public static function listar(): array
    {
        if (!is_dir(self::BACKUP_DIR)) return [];

        $archivos = glob(self::BACKUP_DIR . '*.sql');
        $lista = [];

        foreach ($archivos as $archivo) {
            $lista[] = [
                'nombre' => basename($archivo),
                'tamano' => filesize($archivo),
                'fecha'  => date('Y-m-d H:i:s', filemtime($archivo)),
            ];
        }

        // Ordenar por fecha descendente
        usort($lista, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));

        return $lista;
    }

    /**
     * Elimina un backup
     */
    public static function eliminar(string $nombre): bool
    {
        $nombre = basename($nombre); // Sanitizar
        $ruta = self::BACKUP_DIR . $nombre;

        if (!file_exists($ruta)) return false;

        return @unlink($ruta);
    }

    /**
     * Obtiene la ruta completa de un backup
     */
    public static function ruta(string $nombre): string
    {
        return self::BACKUP_DIR . basename($nombre);
    }

    /**
     * Tamaño total de todos los backups
     */
    public static function tamanoTotal(): int
    {
        if (!is_dir(self::BACKUP_DIR)) return 0;
        $total = 0;
        foreach (glob(self::BACKUP_DIR . '*.sql') as $a) {
            $total += filesize($a);
        }
        return $total;
    }
}