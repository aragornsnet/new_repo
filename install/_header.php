<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador IPV - Paso <?= $_SESSION['install']['paso'] ?? 1 ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="install.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <div class="brand">
            <i class="bi bi-box-seam-fill"></i>
            <span>IPV · Instalador</span>
        </div>
        <div class="muted small">Sistema de Inventario de Productos y Ventas</div>
    </div>
</header>
<main>