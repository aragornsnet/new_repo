<?php
/**
 * IPV - Helpers para el instalador relacionados con la BD.
 * Se usa desde step2_bd.php, step4_finalizar.php y restore_step3_restaurar.php.
 */

/**
 * Genera una contraseña segura para el usuario dedicado.
 */
function generarPasswordBD(int $longitud = 24): string
{
    $mayus = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $minus = 'abcdefghijkmnopqrstuvwxyz';
    $nums  = '23456789';
    $syms  = '-_';
    $todos = $mayus . $minus . $nums . $syms;

    $pass  = $mayus[random_int(0, strlen($mayus) - 1)];
    $pass .= $minus[random_int(0, strlen($minus) - 1)];
    $pass .= $nums[random_int(0, strlen($nums) - 1)];
    $pass .= $syms[random_int(0, strlen($syms) - 1)];

    for ($i = 4; $i < $longitud; $i++) {
        $pass .= $todos[random_int(0, strlen($todos) - 1)];
    }

    return str_shuffle($pass);
}

/**
 * Conecta con el usuario administrativo de MySQL.
 */
function conectarAdmin(string $host, string $puerto, string $usuario, string $password): PDO
{
    $dsn = "mysql:host={$host};port={$puerto};charset=utf8mb4";

    return new PDO($dsn, $usuario, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/**
 * Conecta con el usuario dedicado a una BD concreta.
 */
function conectarApp(string $host, string $puerto, string $bd, string $usuario, string $password): PDO
{
    $dsn = "mysql:host={$host};port={$puerto};dbname={$bd};charset=utf8mb4";

    return new PDO($dsn, $usuario, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/**
 * Valida nombre de BD.
 */
function validarNombreBD(string $nombre): void
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $nombre)) {
        throw new Exception("Nombre de BD no válido: solo letras, números y guión bajo.");
    }
}

/**
 * Valida nombre de usuario de BD.
 */
function validarNombreUsuarioBD(string $usuario): void
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $usuario)) {
        throw new Exception("Nombre de usuario no válido: solo letras, números y guión bajo.");
    }
    if (strlen($usuario) > 32) {
        throw new Exception("El nombre de usuario no puede superar 32 caracteres.");
    }
}

/**
 * Valida host de usuario.
 */
function validarHostUsuario(string $host): void
{
    if (!in_array($host, ['localhost', '127.0.0.1', '%'], true)) {
        throw new Exception("Host de usuario no válido.");
    }
}

/**
 * Crea la base de datos si no existe.
 */
function crearBaseDatos(PDO $pdo, string $nombreBD): void
{
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$nombreBD}`
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$nombreBD}`");
}

/**
 * Crea o actualiza el usuario dedicado y le otorga permisos solo sobre la BD indicada.
 */
function crearUsuarioDedicado(
    PDO $pdoAdmin,
    string $nombreBD,
    string $usuario,
    string $host,
    string $password
): void {
    $u = $pdoAdmin->quote($usuario);
    $h = $pdoAdmin->quote($host);
    $p = $pdoAdmin->quote($password);

    $pdoAdmin->exec("CREATE USER IF NOT EXISTS {$u}@{$h} IDENTIFIED BY {$p}");
    $pdoAdmin->exec("ALTER USER {$u}@{$h} IDENTIFIED BY {$p}");
    $pdoAdmin->exec("GRANT ALL PRIVILEGES ON `{$nombreBD}`.* TO {$u}@{$h}");
    $pdoAdmin->exec("FLUSH PRIVILEGES");
}

/**
 * Ejecuta un archivo .sql completo usando mysqli::multi_query().
 *
 * Maneja correctamente:
 *   - Comentarios ejecutables /*!50001 ... *\/
 *   - Vistas, triggers, procedures, DELIMITER
 *   - Strings con escapes
 *
 * Credenciales:
 *   - Si config/database.php existe → usa DB_HOST, DB_USER, DB_PASS, DB_NAME.
 *   - Si NO existe (modo restore durante instalación) → usa $_SESSION['install']['bd'].
 *   - Como último recurso, extrae las credenciales del propio $pdo.
 *
 * @return array{ejecutadas:int, errores:string[]}
 */
function ejecutarSQL(PDO $pdo, string $ruta, bool $detenerEnError = true): array
{
    if (!file_exists($ruta)) {
        throw new Exception("Archivo SQL no encontrado: {$ruta}");
    }

    if (!extension_loaded('mysqli')) {
        throw new Exception('La extensión mysqli es necesaria para restaurar el backup.');
    }

    [$host, $puerto, $usuario, $pass, $bd] = obtenerCredencialesBD($pdo);

    $mysqli = @new mysqli($host, $usuario, $pass, $bd, $puerto);
    if ($mysqli->connect_errno) {
        throw new Exception('No se pudo conectar con mysqli: ' . $mysqli->connect_error);
    }

    $mysqli->set_charset('utf8mb4');
    $mysqli->query("SET FOREIGN_KEY_CHECKS = 0");
    $mysqli->query("SET UNIQUE_CHECKS = 0");
    $mysqli->query("SET AUTOCOMMIT = 1");

    $sql        = file_get_contents($ruta);
    $ejecutadas = 0;
    $errores    = [];

    try {
        if (!$mysqli->multi_query($sql)) {
            throw new Exception('Error en multi_query: ' . $mysqli->error);
        }

        do {
            $ejecutadas++;
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
            if (!$mysqli->more_results()) {
                break;
            }
            if (!$mysqli->next_result()) {
                if ($mysqli->errno) {
                    $errores[] = $mysqli->error;
                    if ($detenerEnError) {
                        throw new Exception('Error ejecutando SQL: ' . $mysqli->error);
                    }
                }
                break;
            }
        } while (true);

        if ($mysqli->errno && $detenerEnError) {
            throw new Exception('Error ejecutando SQL: ' . $mysqli->error);
        }

    } finally {
        $mysqli->query("SET FOREIGN_KEY_CHECKS = 1");
        $mysqli->query("SET UNIQUE_CHECKS = 1");
        $mysqli->close();
    }

    return [
        'ejecutadas' => $ejecutadas,
        'errores'    => $errores,
    ];
}

/**
 * Obtiene [host, puerto, usuario, password, bd] para mysqli.
 *
 * Prioridad:
 *   1. Constantes DB_* (producción / cuando config/database.php ya existe)
 *   2. $_SESSION['install']['bd'] (instalación / restauración)
 *   3. Credenciales del propio $pdo (si tiene atributos accesibles)
 */
function obtenerCredencialesBD(PDO $pdo): array
{
    // 1. Constantes definidas por config/database.php
    if (defined('DB_HOST') && defined('DB_USER') && defined('DB_NAME')) {
        return [
            (string) DB_HOST,
            (int) (defined('DB_PORT') ? DB_PORT : 3306),
            (string) DB_USER,
            (string) (defined('DB_PASS') ? DB_PASS : ''),
            (string) DB_NAME,
        ];
    }

    // 2. Sesión de instalación / restauración
    if (!empty($_SESSION['install']['bd'])) {
        $b = $_SESSION['install']['bd'];
        return [
            (string) ($b['host']       ?? 'localhost'),
            (int)    ($b['puerto']     ?? 3306),
            (string) ($b['usuario']    ?? 'root'),
            (string) ($b['password']   ?? ''),
            (string) ($b['basedatos']  ?? ''),
        ];
    }

    // 3. Fallback: no tenemos credenciales
    throw new Exception('No se pudieron obtener las credenciales de la base de datos.');
}

/**
 * Verifica que la BD contenga las tablas mínimas esperadas.
 */
function verificarEstructuraBD(PDO $pdo, string $nombreBD): array
{
    $tablasRequeridas = ['usuarios', 'productos', 'ventas', 'configuracion', 'turnos', 'puntos_venta'];

    $tablasActuales = $pdo->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = " . $pdo->quote($nombreBD) . "
          AND table_type = 'BASE TABLE'
    ")->fetchAll(PDO::FETCH_COLUMN);

    $faltantes = array_diff($tablasRequeridas, $tablasActuales);

    return [
        'ok'        => empty($faltantes),
        'total'     => count($tablasActuales),
        'faltantes' => array_values($faltantes),
    ];
}