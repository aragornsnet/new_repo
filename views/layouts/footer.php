</main>
</div>

<?php
/**
 * Cache buster: añade ?v=<timestamp> a los archivos JS.
 * Se recalcula cuando el archivo cambia.
 */
if (!function_exists('assetVer')) {
    function assetVer(string $ruta): string {
        // La ruta viene como "public/js/archivo.js" (relativa)
        $path = __DIR__ . '/../../' . ltrim($ruta, '/');
        $v = file_exists($path) ? filemtime($path) : time();
        return BASE_URL . $ruta . '?v=' . $v;
    }
}
?>

<script>
    window.APP_BASE_URL = '<?= BASE_URL ?>';
    window.CSRF_TOKEN = '<?= h($csrfToken ?? '') ?>';
    window.CURRENT_USER = <?= json_encode($usuario ?? null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.NOTIF_POLLING = <?= (int) Config::int('notif_polling_segundos', 60) ?>;
    window.NOTIF_MAX = <?= (int) Config::int('notif_max_dropdown', 10) ?>;
</script>

<script src="<?= assetVer('public/js/toast.js') ?>"></script>
<script src="<?= assetVer('public/js/api.js') ?>"></script>
<script src="<?= assetVer('public/js/password.js') ?>"></script>
<script src="<?= assetVer('public/js/app.js') ?>"></script>
<script src="<?= assetVer('public/js/layout.js') ?>"></script>
<script src="<?= assetVer('public/js/perfil.js') ?>"></script>
<script src="<?= assetVer('public/js/notificaciones.js') ?>"></script>

<?php if (!empty($scriptsExtra) && is_array($scriptsExtra)): ?>
    <?php foreach ($scriptsExtra as $script): ?>
        <?php
        // $script viene con BASE_URL incluido, ej: "http://10.66.30.2/ipv/public/js/mis_ventas.js"
        // Extraer la ruta relativa respecto a BASE_URL
        $rutaRelativa = str_replace(BASE_URL, '', $script);
        $rutaAbsoluta = __DIR__ . '/../../' . ltrim($rutaRelativa, '/');
        $v = file_exists($rutaAbsoluta) ? filemtime($rutaAbsoluta) : time();

        // Separador: si la URL ya tiene query string, usar &; si no, usar ?
        $sep = strpos($script, '?') !== false ? '&' : '?';
        ?>
        <script src="<?= h($script) ?><?= $sep ?>v=<?= $v ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>