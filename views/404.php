<?php
http_response_code(404);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Página no encontrada</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/base.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/components.css">
</head>
<body>
<div class="flex-center" style="min-height:100vh;">
    <div class="card text-center" style="max-width:420px;">
        <i class="bi bi-question-circle" style="font-size:80px;color:var(--warning);"></i>
        <h1 class="mt-3">404</h1>
        <p class="text-muted">La página que buscas no existe.</p>
        <a href="<?= BASE_URL ?>" class="btn btn-primary"><i class="bi bi-house"></i> Volver al inicio</a>
    </div>
</div>
</body>
</html>