<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/helpers.php';

Auth::iniciarSesion();
Auth::requireLogin();

// ============================================================
// Documentos disponibles (whitelist)
// ============================================================
$documentos = [
    'usuario' => [
        'archivo'   => 'manual_usuario.md',
        'titulo'    => 'Manual de Usuario',
        'icono'     => 'bi-book',
        'roles'     => ['Administrador', 'Supervisor', 'Vendedor', 'Almacenero'],
    ],
    'tecnico' => [
        'archivo'   => 'manual_tecnico.md',
        'titulo'    => 'Manual Técnico',
        'icono'     => 'bi-tools',
        'roles'     => ['Administrador'],
    ],
    'readme' => [
        'archivo'   => 'README.md',
        'titulo'    => 'Acerca de IPV',
        'icono'     => 'bi-info-circle',
        'roles'     => ['Administrador', 'Supervisor', 'Vendedor', 'Almacenero'],
    ],
];

// ============================================================
// Determinar documento activo
// ============================================================
$rol = Auth::rol();
$docActivo = $_GET['doc'] ?? 'usuario';

// Validar que el doc exista y el usuario tenga permiso
if (!isset($documentos[$docActivo])) {
    $docActivo = 'usuario';
}
if (!in_array($rol, $documentos[$docActivo]['roles'], true)) {
    foreach ($documentos as $key => $doc) {
        if (in_array($rol, $doc['roles'], true)) {
            $docActivo = $key;
            break;
        }
    }
}

$documentosPermitidos = array_filter($documentos, function ($doc) use ($rol) {
    return in_array($rol, $doc['roles'], true);
});

// ============================================================
// Cargar y convertir el Markdown
// ============================================================
$docsPath = __DIR__ . '/../docs/';
$archivo = $docsPath . $documentos[$docActivo]['archivo'];

$contenidoHtml = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Documento no encontrado.</div>';
$contenidoMd = '';

if (file_exists($archivo) && is_readable($archivo)) {
    $contenidoMd = file_get_contents($archivo);

    $parsedownPath = __DIR__ . '/../public/libs/parsedown/Parsedown.php';
    if (file_exists($parsedownPath)) {
        require_once $parsedownPath;

        if (class_exists('Parsedown')) {
            $parsedown = new Parsedown();
            $parsedown->setSafeMode(false);
            $parsedown->setBreaksEnabled(true);
            $contenidoHtml = $parsedown->text($contenidoMd);
        } else {
            $contenidoHtml = '<div class="alert alert-danger">Parsedown no se cargó correctamente.</div>';
        }
    } else {
        $contenidoHtml = '<pre style="white-space:pre-wrap;">' . h($contenidoMd) . '</pre>';
    }
}

// ============================================================
// Render
// ============================================================
$tituloPagina = 'Manual de uso';
$subtituloPagina = $documentos[$docActivo]['titulo'];
$paginaActiva = 'ayuda-' . $docActivo;
$cssExtra = [
    BASE_URL . 'public/css/ayuda.css',
];
$scriptsExtra = [
    BASE_URL . 'public/js/ayuda.js',
];
require __DIR__ . '/layouts/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title">📚 <?= h($documentos[$docActivo]['titulo']) ?></h2>
        <p class="page-subtitle">Guía completa del sistema IPV</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>api/manual_pdf.php?doc=<?= h($docActivo) ?>" target="_blank" class="btn btn-danger">
            <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
        </a>
    </div>
</div>

<div class="ayuda-layout">

    <!-- Índice lateral -->
    <aside class="ayuda-indice">
        <div class="ayuda-buscador">
            <i class="bi bi-search"></i>
            <input type="text" id="ayuda-buscar" placeholder="Buscar en el manual..." autocomplete="off">
            <button id="ayuda-limpiar" class="ayuda-limpiar" style="display:none;" type="button">
                <i class="bi bi-x-circle-fill"></i>
            </button>
        </div>

        <div class="ayuda-docs-lista">
            <div class="ayuda-docs-title">Documentos</div>
            <?php foreach ($documentosPermitidos as $key => $doc): ?>
                <a href="<?= BASE_URL ?>views/ayuda.php?doc=<?= h($key) ?>"
                   class="ayuda-doc-item <?= $key === $docActivo ? 'active' : '' ?>">
                    <i class="bi <?= h($doc['icono']) ?>"></i>
                    <span><?= h($doc['titulo']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="ayuda-toc-wrap">
            <div class="ayuda-toc-title">Índice del documento</div>
            <nav id="ayuda-nav" class="ayuda-nav"></nav>
            <div id="ayuda-sin-toc" class="text-muted text-xs" style="display:none;padding:8px 12px;">
                Sin encabezados
            </div>
        </div>

        <div id="ayuda-sin-resultados" class="ayuda-sin-resultados" style="display:none;">
            <i class="bi bi-search"></i>
            <p>Sin resultados</p>
        </div>
    </aside>

    <!-- Contenido -->
    <main class="ayuda-contenido-wrap">
        <article class="ayuda-contenido" id="ayuda-contenido">
            <?= $contenidoHtml ?>
        </article>

        <div id="ayuda-busqueda-vacia" class="ayuda-busqueda-vacia" style="display:none;">
            <i class="bi bi-search"></i>
            <h3>Sin resultados</h3>
            <p>No se encontró "<strong id="ayuda-busqueda-texto"></strong>" en este documento.</p>
            <button class="btn btn-secondary btn-sm" onclick="Ayuda.limpiarBusqueda()">
                <i class="bi bi-x-circle"></i> Limpiar búsqueda
            </button>
        </div>
    </main>

    <!-- Botón volver arriba -->
    <button id="ayuda-top" class="ayuda-top" title="Volver arriba" type="button">
        <i class="bi bi-arrow-up"></i>
    </button>

</div>

<script>
    window.AYUDA_DOC_ACTIVO = <?= json_encode($docActivo) ?>;
</script>

<?php require __DIR__ . '/layouts/footer.php'; ?>