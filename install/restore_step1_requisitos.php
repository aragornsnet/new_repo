<?php
require_once __DIR__ . '/_bootstrap.php';

// Marcar el modo restore en la sesión
$_SESSION['install']['modo'] = 'restore';
$_SESSION['install']['paso'] = 1;

// Verificación de requisitos (idéntica a step1_requisitos, más chequeos extra)
$requisitos = [
    'php_version' => [
        'nombre'   => 'PHP 8.0 o superior',
        'actual'   => PHP_VERSION,
        'ok'       => version_compare(PHP_VERSION, '8.0.0', '>='),
        'critico'  => true,
    ],
    'pdo' => [
        'nombre'   => 'Extensión PDO',
        'actual'   => extension_loaded('pdo') ? 'Instalada' : 'No instalada',
        'ok'       => extension_loaded('pdo'),
        'critico'  => true,
    ],
    'pdo_mysql' => [
        'nombre'   => 'Driver PDO MySQL',
        'actual'   => extension_loaded('pdo_mysql') ? 'Instalado' : 'No instalado',
        'ok'       => extension_loaded('pdo_mysql'),
        'critico'  => true,
    ],
    'mbstring' => [
        'nombre'   => 'Extensión mbstring',
        'actual'   => extension_loaded('mbstring') ? 'Instalada' : 'No instalada',
        'ok'       => extension_loaded('mbstring'),
        'critico'  => true,
    ],
    'json' => [
        'nombre'   => 'Extensión JSON',
        'actual'   => extension_loaded('json') ? 'Instalada' : 'No instalada',
        'ok'       => extension_loaded('json'),
        'critico'  => true,
    ],
    'openssl' => [
        'nombre'   => 'Extensión OpenSSL',
        'actual'   => extension_loaded('openssl') ? 'Instalada' : 'No instalada',
        'ok'       => extension_loaded('openssl'),
        'critico'  => false,
    ],
    'upload_max' => [
        'nombre'   => 'Tamaño máximo de subida (≥ 50 MB)',
        'actual'   => ini_get('upload_max_filesize'),
        'ok'       => convertirBytes(ini_get('upload_max_filesize')) >= 50 * 1024 * 1024,
        'critico'  => true,
    ],
    'post_max' => [
        'nombre'   => 'Tamaño máximo POST (≥ 50 MB)',
        'actual'   => ini_get('post_max_size'),
        'ok'       => convertirBytes(ini_get('post_max_size')) >= 50 * 1024 * 1024,
        'critico'  => true,
    ],
    'max_execution' => [
        'nombre'   => 'Tiempo máximo de ejecución (≥ 300 s)',
        'actual'   => ini_get('max_execution_time') . ' s',
        'ok'       => (int)ini_get('max_execution_time') >= 300 || ini_get('max_execution_time') == 0,
        'critico'  => false,
    ],
    'config_writable' => [
        'nombre'   => 'Carpeta /config escribible',
        'actual'   => is_writable(CONFIG_PATH) || @mkdir(CONFIG_PATH, 0755, true) ? 'Sí' : 'No',
        'ok'       => is_writable(CONFIG_PATH) || @mkdir(CONFIG_PATH, 0755, true),
        'critico'  => true,
    ],
    'install_writable' => [
        'nombre'   => 'Carpeta /install escribible (para .lock)',
        'actual'   => is_writable(__DIR__) ? 'Sí' : 'No',
        'ok'       => is_writable(__DIR__),
        'critico'  => true,
    ],
    'uploads_writable' => [
        'nombre'   => 'Carpeta public/uploads escribible',
        'actual'   => is_writable(ROOT_PATH . '/public/uploads') ? 'Sí' : 'No',
        'ok'       => is_writable(ROOT_PATH . '/public/uploads'),
        'critico'  => true,
    ],
];

/**
 * Convierte "50M", "1G", "512K" a bytes.
 */
function convertirBytes(string $valor): int
{
    $valor = trim($valor);
    if ($valor === '') return 0;
    if ($valor === '-1') return PHP_INT_MAX;

    $unidad = strtolower(substr($valor, -1));
    $num = (int) $valor;

    return match ($unidad) {
        'g' => $num * 1024 * 1024 * 1024,
        'm' => $num * 1024 * 1024,
        'k' => $num * 1024,
        default => (int) $valor,
    };
}

$todo_ok = true;
foreach ($requisitos as $r) {
    if ($r['critico'] && !$r['ok']) {
        $todo_ok = false;
        break;
    }
}

$_SESSION['install']['requisitos'] = $requisitos;

include __DIR__ . '/_header.php';
?>

<div class="container">
    <div class="progress">
        <div class="progress-step active">1</div>
        <div class="progress-line"></div>
        <div class="progress-step">2</div>
        <div class="progress-line"></div>
        <div class="progress-step">3</div>
        <div class="progress-line"></div>
        <div class="progress-step">4</div>
    </div>

    <div class="card">
        <h1><i class="bi bi-arrow-counterclockwise"></i> Restaurar backup — Paso 1: Requisitos</h1>
        <p class="muted">Comprobando que tu servidor cumpla con los requisitos mínimos para restaurar.</p>

        <table class="tabla-requisitos">
            <thead>
                <tr>
                    <th>Requisito</th>
                    <th>Estado</th>
                    <th>Resultado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requisitos as $r): ?>
                <tr>
                    <td><?= h($r['nombre']) ?></td>
                    <td class="muted"><?= h($r['actual']) ?></td>
                    <td>
                        <?php if ($r['ok']): ?>
                            <span class="badge badge-success"><i class="bi bi-check-lg"></i> OK</span>
                        <?php else: ?>
                            <span class="badge badge-danger"><i class="bi bi-x-lg"></i> Falla</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!$todo_ok): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Hay requisitos críticos sin cumplir.</strong>
                Corrige los errores marcados y recarga esta página.
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                Todos los requisitos están correctos. ¡Podemos continuar!
            </div>
        <?php endif; ?>

        <div class="actions">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver al inicio
            </a>
            <a href="restore_step2_archivo.php" class="btn btn-primary <?= !$todo_ok ? 'disabled' : '' ?>"
               <?= !$todo_ok ? 'onclick="return false;"' : '' ?>>
                Continuar <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/_footer.php'; ?>