<?php
$tituloPagina = 'Dashboard Supervisor';
$subtituloPagina = 'Supervisión de todos los puntos de venta';
$paginaActiva = 'dashboard';
require __DIR__ . '/../layouts/header.php';

// Redirigir al nuevo dashboard del supervisor
redirigir('views/supervisor/dashboard.php');
?>