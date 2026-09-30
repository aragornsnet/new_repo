<?php
require_once __DIR__ . '/Licencia.php';
require_once __DIR__ . '/Notificacion.php';

class Middleware
{
    public static function verificarLicencia(): void
    {
        $ruta = $_SERVER['REQUEST_URI'] ?? '';
        $exentas = ['/licencia', '/login', '/api/licencia', '/api/auth', '/logout'];

        foreach ($exentas as $exenta) {
            if (strpos($ruta, $exenta) !== false) return;
        }

        $estado = Licencia::verificar();

        // ⭐ Notificar según el estado (sin duplicados)
        self::notificarEstado($estado);

        if (!$estado['valida']) {
            $_SESSION['licencia_estado'] = $estado;
            redirigir('views/licencia.php');
        }

        if ($estado['tipo'] === 'trial' && $estado['dias_restantes'] <= 5) {
            $_SESSION['licencia_aviso'] = $estado['dias_restantes'];
        } else {
            unset($_SESSION['licencia_aviso']);
        }
    }

    private static function notificarEstado(array $estado): void
    {
        // Solo si hay sesión activa
        if (!isset($_SESSION['user']['id'])) return;

        try {
            // 1) Prueba por vencer (≤ 7 días)
            if ($estado['valida'] && $estado['tipo'] === 'trial' && $estado['dias_restantes'] <= 7) {
                Notificacion::crearParaAdminsUnaVez(
                    'licencia_prueba_por_vencer',
                    'Prueba por vencer',
                    "Quedan {$estado['dias_restantes']} días de prueba. Activa tu licencia.",
                    'views/licencia.php',
                    'hourglass-split',
                    'warning',
                    48  // no repetir en 48 horas
                );
            }

            // 2) Licencia por vencer (≤ 30 días)
            if ($estado['valida'] && $estado['tipo'] === 'licencia'
                && $estado['dias_restantes'] > 0 && $estado['dias_restantes'] <= 30) {
                Notificacion::crearParaAdminsUnaVez(
                    'licencia_por_vencer',
                    'Licencia por vencer',
                    "Tu licencia expira en {$estado['dias_restantes']} días.",
                    'views/licencia.php',
                    'calendar-x',
                    'warning',
                    72  // no repetir en 72 horas
                );
            }

            // 3) Licencia expirada
            if (!$estado['valida'] && in_array($estado['tipo'], ['expirada', 'invalida'], true)) {
                Notificacion::crearParaAdminsUnaVez(
                    'licencia_expirada',
                    'Licencia expirada',
                    'El sistema requiere una licencia activa.',
                    'views/licencia.php',
                    'x-octagon-fill',
                    'danger',
                    24
                );
            }

            // 4) Reloj retrocedido
            if ($estado['tipo'] === 'reloj_invalido') {
                Notificacion::crearParaAdminsUnaVez(
                    'licencia_reloj_invalido',
                    'Reloj del sistema modificado',
                    'Se detectó un cambio en el reloj. Revisa el sistema.',
                    'views/licencia.php',
                    'clock-history',
                    'danger',
                    24
                );
            }
        } catch (Throwable $e) {
            error_log('Middleware notificar error: ' . $e->getMessage());
        }
    }
}