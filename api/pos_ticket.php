<?php
/**
 * IPV - API de ticket de venta (impresión térmica)
 *
 * Permisos:
 *   - Admin, Supervisor, Vendedor: pueden descargar/imprimir tickets
 *
 * Uso:
 *   - Vista previa: api/pos_ticket.php?accion=pdf&venta_id=X&modo=inline
 *   - Descarga:     api/pos_ticket.php?accion=pdf&venta_id=X&modo=download
 *
 * El tamaño del papel se lee de `configuracion.pos_ticket_tamano`:
 *   - 58mm, 80mm o A4
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/ApiBootstrap.php';
require_once __DIR__ . '/../core/Auditoria.php';
require_once __DIR__ . '/../core/Config.php';

ApiBootstrap::iniciar(['Administrador', 'Supervisor', 'Vendedor']);

$accion = $_GET['accion'] ?? 'pdf';

switch ($accion) {

    // ============================================================
    // PDF DEL TICKET
    // ============================================================
    case 'pdf':
        $ventaId = (int)($_GET['venta_id'] ?? 0);
        if ($ventaId <= 0) Response::error('ID de venta inválido');

        // Modo: 'inline' (previsualización) o 'download' (descarga)
        $modo = $_GET['modo'] ?? 'inline';
        if (!in_array($modo, ['inline', 'download'], true)) {
            $modo = 'inline';
        }

        // Verificar que la venta existe y pertenece al usuario (si es vendedor)
        $venta = Database::fetchOne("
            SELECT id, usuario_id
            FROM ventas
            WHERE id = ?
        ", [$ventaId]);

        if (!$venta) Response::noEncontrado('Venta no encontrada');

        // Vendedor solo puede ver/imprimir sus propias ventas
        if (Auth::esVendedor() && (int)$venta['usuario_id'] !== Auth::id()) {
            Response::prohibido('Solo puedes ver los tickets de tus propias ventas');
        }

        require_once __DIR__ . '/../core/reportes/ticket_venta.php';
        generar_ticket_venta($ventaId, $modo);
        break;

    default:
        Response::error('Acción no reconocida', 404);
}