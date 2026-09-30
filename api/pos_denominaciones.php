<?php
/**
 * IPV - API de denominaciones activas para el POS
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';

ApiBootstrap::iniciar(['Vendedor', 'Supervisor', 'Administrador']);

$moneda = trim($_GET['moneda'] ?? 'CUP');

// Denominaciones activas de la moneda solicitada
// Solo billetes (excluye monedas)
$denominaciones = Database::fetchAll("
    SELECT id, valor, tipo, moneda, orden
    FROM denominaciones
    WHERE moneda = ? 
      AND tipo = 'billete'
      AND activo = 1
    ORDER BY valor DESC
", [$moneda]);

// Config del POS
$config = [
    'modo_conteo' => Config::get('pos_modo_conteo_default', 'opcional'),
];

Response::ok([
    'denominaciones' => $denominaciones,
    'moneda' => $moneda,
    'config' => $config,
]);