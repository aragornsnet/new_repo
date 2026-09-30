<?php
/**
 * IPV - Fingerprint del hardware
 * Calcula un hash basado en el hardware del servidor.
 * NO se guarda en disco: se recalcula en cada request.
 */

class Fingerprint
{
    /**
     * Devuelve el fingerprint del hardware actual.
     * Si el hardware cambia, el hash cambia.
     */
    public static function obtener(): string
    {
        $componentes = [];

        // 1. Machine ID (Linux) / Machine GUID (Windows)
        $machineId = self::machineId();
        if ($machineId) $componentes[] = 'machine:' . $machineId;

        // 2. MAC address de la primera interfaz
        $mac = self::macAddress();
        if ($mac) $componentes[] = 'mac:' . $mac;

        // 3. Hostname
        $hostname = gethostname();
        if ($hostname) $componentes[] = 'host:' . $hostname;

        // 4. Sistema operativo
        $componentes[] = 'os:' . PHP_OS;

        // 5. CPU (Linux)
        $cpu = self::cpuInfo();
        if ($cpu) $componentes[] = 'cpu:' . $cpu;

        // Si no hay NINGÚN componente de hardware real, no podemos generar fingerprint
        if (count($componentes) <= 2) {
            $componentes[] = 'path:' . md5(__DIR__);
        }

        return hash('sha256', implode('|', $componentes));
    }

    private static function machineId(): ?string
    {
        $archivos = ['/etc/machine-id', '/var/lib/dbus/machine-id'];
        foreach ($archivos as $archivo) {
            if (is_readable($archivo)) {
                $id = trim(file_get_contents($archivo));
                if (!empty($id)) return $id;
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $output = @shell_exec('wmic csproduct get uuid 2>nul');
            if ($output) {
                $lines = array_values(array_filter(array_map('trim', explode("\n", $output))));
                if (count($lines) > 1 && $lines[1] !== 'UUID') {
                    return $lines[1];
                }
            }
        }

        return null;
    }

    private static function macAddress(): ?string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $salida = @shell_exec("cat /sys/class/net/*/address 2>/dev/null | grep -v '^00:00:00:00:00:00$' | head -1");
            if ($salida) return trim($salida);
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $output = @shell_exec('getmac /fo csv /nh 2>nul');
            if ($output && preg_match('/([0-9A-F]{2}[:-]){5}([0-9A-F]{2})/i', $output, $m)) {
                return $m[0];
            }
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            $output = @shell_exec("ifconfig en0 2>/dev/null | grep ether | awk '{print $2}'");
            if ($output) return trim($output);
        }

        return null;
    }

    private static function cpuInfo(): ?string
    {
        if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/cpuinfo')) {
            $info = file_get_contents('/proc/cpuinfo');
            if (preg_match('/model name\s*:\s*(.+)/', $info, $m)) {
                return trim($m[1]);
            }
        }
        return null;
    }
}