<?php
/**
 * IPV - Generación del ID único de instalación
 */

class InstalacionId
{
    /**
     * Genera un ID único basado en hardware y entorno.
     * Se llama UNA VEZ durante la instalación y se guarda en config.
     */
    public static function generar(): string
    {
        $criterios = [];

        // 1. Machine ID (Linux) / Machine GUID (Windows)
        $machineId = self::obtenerMachineId();
        if ($machineId) $criterios[] = 'machine:' . $machineId;

        // 2. MAC address
        $mac = self::obtenerMac();
        if ($mac) $criterios[] = 'mac:' . $mac;

        // 3. Hostname
        $hostname = gethostname();
        if ($hostname) $criterios[] = 'host:' . $hostname;

        // 4. Salt único generado al instalar
        $salt = bin2hex(random_bytes(16));
        $criterios[] = 'salt:' . $salt;

        // 5. Ruta de instalación (normalizada)
        $ruta = str_replace('\\', '/', realpath(__DIR__ . '/..'));
        $criterios[] = 'path:' . md5($ruta);

        // Combinar y hashear
        $base = implode('|', $criterios);
        return hash('sha256', $base);
    }

    /**
     * Obtiene el Machine ID del sistema operativo.
     */
    private static function obtenerMachineId(): ?string
    {
        // Linux
        $archivos = ['/etc/machine-id', '/var/lib/dbus/machine-id'];
        foreach ($archivos as $archivo) {
            if (is_readable($archivo)) {
                $id = trim(file_get_contents($archivo));
                if (!empty($id)) return $id;
            }
        }

        // Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = @shell_exec('wmic csproduct get uuid 2>nul');
            if ($output) {
                $lines = array_filter(explode("\n", $output));
                if (count($lines) > 1) {
                    $uuid = trim($lines[1]);
                    if (!empty($uuid) && $uuid !== 'UUID') return $uuid;
                }
            }
        }

        return null;
    }

    /**
     * Obtiene la MAC address principal.
     */
    private static function obtenerMac(): ?string
    {
        // Linux
        $salida = @shell_exec("ip link 2>/dev/null | grep -oP '(?<=link/ether )([0-9a-f:]{17})' | head -1");
        if ($salida) return trim($salida);

        // Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = @shell_exec('getmac /fo csv /nh 2>nul');
            if ($output) {
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    if (preg_match('/([0-9A-F]{2}[:-]){5}([0-9A-F]{2})/i', $line, $m)) {
                        return $m[0];
                    }
                }
            }
        }

        return null;
    }
}