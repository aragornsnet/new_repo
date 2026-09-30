<?php
/**
 * IPV - Sistema de licencias
 */

require_once __DIR__ . '/Fingerprint.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Database.php';

class Licencia
{
    private const PUBLIC_KEY_PATH = __DIR__ . '/../config/public.pem';
    private const LICENSE_PATH    = __DIR__ . '/../storage/licencia.lic';
    private const LAST_SEEN_PATH  = __DIR__ . '/../storage/last_seen.txt';
    private const TRIAL_DAYS      = 30;

    public static function installId(): string
    {
        $fingerprint = Fingerprint::obtener();
        $seed = self::obtenerSeed();

        if (empty($seed)) {
            return hash('sha256', 'novalid:' . $fingerprint);
        }

        return hash('sha256', $fingerprint . '|' . $seed);
    }

    public static function verificar(): array
    {
        $reloj = self::verificarReloj();
        if (!$reloj['ok']) {
            return [
                'valida'         => false,
                'tipo'           => 'reloj_invalido',
                'dias_restantes' => 0,
                'mensaje'        => $reloj['mensaje'],
            ];
        }

        $licencia = self::leerLicencia();
        if ($licencia && self::verificarFirma($licencia)) {
            $datos = self::decodificarLicencia($licencia);
            if ($datos && self::licenciaValida($datos)) {
                return [
                    'valida'         => true,
                    'tipo'           => 'licencia',
                    'dias_restantes' => self::diasRestantesLicencia($datos),
                    'mensaje'        => 'Licencia válida',
                    'datos'          => $datos,
                ];
            }
        }

        $installDate = defined('INSTALL_DATE') ? INSTALL_DATE : null;
        $trialDays = self::TRIAL_DAYS;

        if ($installDate) {
            $trialEnd = strtotime($installDate . ' +' . $trialDays . ' days');
            $diasRestantes = (int) ceil(($trialEnd - time()) / 86400);

            if ($diasRestantes > 0) {
                return [
                    'valida'         => true,
                    'tipo'           => 'trial',
                    'dias_restantes' => $diasRestantes,
                    'mensaje'        => "Prueba: {$diasRestantes} días restantes",
                ];
            }

            return [
                'valida'         => false,
                'tipo'           => 'expirada',
                'dias_restantes' => 0,
                'mensaje'        => "El período de prueba de {$trialDays} días ha expirado.",
            ];
        }

        return [
            'valida'         => false,
            'tipo'           => 'invalida',
            'dias_restantes' => 0,
            'mensaje'        => 'No se encontró fecha de instalación.',
        ];
    }

    private static function verificarReloj(): array
    {
        $ahora = time();
        $ultimaVista = 0;

        if (file_exists(self::LAST_SEEN_PATH)) {
            $contenido = @file_get_contents(self::LAST_SEEN_PATH);
            if ($contenido !== false) {
                $ultimaVista = (int) trim($contenido);
            }
        }

        if ($ultimaVista > 0 && $ahora < ($ultimaVista - 3600)) {
            error_log('Licencia: reloj retrocedido. Última vista: ' .
                date('Y-m-d H:i:s', $ultimaVista) . ', actual: ' . date('Y-m-d H:i:s', $ahora));
            return [
                'ok' => false,
                'mensaje' => 'Se detectó un cambio en el reloj del sistema. ' .
                             'Contacta al soporte técnico.',
            ];
        }

        @file_put_contents(self::LAST_SEEN_PATH, (string) $ahora);

        return ['ok' => true, 'mensaje' => 'OK'];
    }

    private static function obtenerSeed(): string
    {
        static $seed = null;
        if ($seed !== null) return $seed;

        try {
            $seed = (string) Database::fetchValue(
                "SELECT valor FROM configuracion WHERE clave = 'install_seed'",
                [],
                ''
            );
        } catch (Throwable $e) {
            if (defined('INSTALL_SEED')) {
                $seed = INSTALL_SEED;
            } else {
                $seed = '';
            }
        }

        return $seed;
    }

    public static function verificarFirma(string $licencia): bool
    {
        if (!file_exists(self::PUBLIC_KEY_PATH)) {
            error_log('Licencia: falta public.pem en ' . self::PUBLIC_KEY_PATH);
            return false;
        }

        $publicKey = openssl_pkey_get_public(file_get_contents(self::PUBLIC_KEY_PATH));
        if (!$publicKey) return false;

        $partes = explode('.', $licencia);
        if (count($partes) !== 2) return false;

        $payload = base64_decode($partes[0], true);
        $firma   = base64_decode($partes[1], true);

        if ($payload === false || $firma === false) return false;

        $resultado = openssl_verify($payload, $firma, $publicKey, OPENSSL_ALGO_SHA256);
        return $resultado === 1;
    }

    public static function decodificarLicencia(string $licencia): ?array
    {
        $partes = explode('.', $licencia);
        if (count($partes) !== 2) return null;

        $payload = base64_decode($partes[0], true);
        if ($payload === false) return null;

        $datos = json_decode($payload, true);
        return is_array($datos) ? $datos : null;
    }

    private static function licenciaValida(array $datos): bool
    {
        $installIdActual = self::installId();
        if (!hash_equals($installIdActual, $datos['install_id'] ?? '')) {
            error_log('Licencia: ID de instalación no coincide.');
            return false;
        }

        if (isset($datos['expira'])) {
            $expira = strtotime($datos['expira']);
            if ($expira < time()) {
                error_log('Licencia: expirada el ' . $datos['expira']);
                return false;
            }
        }

        return true;
    }

    private static function diasRestantesLicencia(array $datos): int
    {
        if (!isset($datos['expira'])) return -1;
        $expira = strtotime($datos['expira']);
        return max(0, (int) ceil(($expira - time()) / 86400));
    }

    public static function leerLicencia(): ?string
    {
        if (!file_exists(self::LICENSE_PATH)) return null;
        return trim(file_get_contents(self::LICENSE_PATH));
    }

    public static function guardarLicencia(string $licencia): bool
    {
        $dir = dirname(self::LICENSE_PATH);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return @file_put_contents(self::LICENSE_PATH, $licencia) !== false;
    }

    public static function idInstalacion(): string
    {
        return self::installId();
    }
}