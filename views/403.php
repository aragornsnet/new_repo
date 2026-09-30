<?php
http_response_code(403);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso denegado</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/base.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/components.css">
</head>
<body>
<div class="flex-center" style="min-height:100vh;">
    <div class="card text-center" style="max-width:420px;">
        <i class="bi bi-shield-x" style="font-size:80px;color:var(--danger);"></i>
        <h1 class="mt-3">403</h1>
        <p class="text-muted">No tienes permisos para acceder a esta sección.</p>
        <a href="<?= BASE_URL ?>" class="btn btn-primary"><i class="bi bi-house"></i> Volver al inicio</a>
    </div>
</div>
</body>
</html>