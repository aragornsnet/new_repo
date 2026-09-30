<?php
/**
 * GENERADOR DE LICENCIAS IPV - USO INTERNO
 * NO distribuir con IPV.
 */

define('PRIVATE_KEY_PATH', __DIR__ . '/keys/private.pem');
define('DIAS_LICENCIA_DEFAULT', 365);

if (!file_exists(PRIVATE_KEY_PATH)) {
    die("ERROR: No se encontró private.pem en " . PRIVATE_KEY_PATH . "\n");
}

if (php_sapi_name() === 'cli') {
    $installId = $argv[1] ?? null;
    $dias = (int)($argv[2] ?? DIAS_LICENCIA_DEFAULT);
    $cliente = $argv[3] ?? '';

    if (!$installId) {
        echo "Uso: php generar.php <install_id> [dias] [cliente]\n";
        exit(1);
    }

    echo generarLicencia($installId, $dias, $cliente) . "\n";
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $installId = trim($_POST['install_id'] ?? '');
    $dias = (int)($_POST['dias'] ?? DIAS_LICENCIA_DEFAULT);
    $cliente = trim($_POST['cliente'] ?? '');

    if ($installId) {
        $resultado = generarLicencia($installId, $dias, $cliente);
    } else {
        $error = 'Install ID requerido';
    }
}

function generarLicencia(string $installId, int $dias, string $cliente): string
{
    $payload = [
        'install_id' => $installId,
        'cliente'    => $cliente,
        'emitida'    => date('Y-m-d H:i:s'),
        'expira'     => date('Y-m-d H:i:s', strtotime("+{$dias} days")),
        'dias'       => $dias,
    ];

    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $payloadB64  = base64_encode($payloadJson);

    $privateKey = openssl_pkey_get_private(file_get_contents(PRIVATE_KEY_PATH));
    if (!$privateKey) {
        die("ERROR: no se pudo cargar la clave privada\n");
    }

    $firma = '';
    if (!openssl_sign($payloadJson, $firma, $privateKey, OPENSSL_ALGO_SHA256)) {
        die("ERROR: no se pudo firmar\n");
    }

    return $payloadB64 . '.' . base64_encode($firma);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Licencias IPV</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f8fafc;
            color: #0f172a;
        }
        .card {
            background: #fff;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,.07);
        }
        h1 { margin-top: 0; font-size: 24px; }
        .subtitle { color: #64748b; font-size: 13px; margin-bottom: 24px; }
        label {
            display: block;
            margin: 16px 0 6px;
            font-weight: 600;
            font-size: 13px;
        }
        input, textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            transition: border-color .15s, box-shadow .15s;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
        }
        textarea {
            min-height: 140px;
            font-family: "JetBrains Mono", Menlo, Consolas, monospace;
            font-size: 12px;
            resize: vertical;
        }
        button {
            margin-top: 20px;
            padding: 12px 24px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: background .15s;
        }
        button:hover { background: #1e40af; }
        button:active { transform: translateY(1px); }
        .btn-copiar {
            background: #16a34a;
            margin-left: 8px;
            padding: 8px 16px;
            font-size: 13px;
        }
        .btn-copiar:hover { background: #15803d; }
        .btn-copiar.copiado { background: #0f766e; }
        .resultado {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 2px solid #e2e8f0;
        }
        .resultado h3 {
            color: #16a34a;
            margin: 0 0 4px;
            font-size: 18px;
        }
        .resultado .info {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 12px;
        }
        .licencia {
            background: #f1f5f9;
            padding: 16px;
            border-radius: 8px;
            font-family: "JetBrains Mono", Menlo, Consolas, monospace;
            font-size: 11px;
            word-break: break-all;
            line-height: 1.6;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
        }
        .acciones {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        .meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 16px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 13px;
        }
        .meta-item .label {
            color: #64748b;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .meta-item .valor {
            font-weight: 600;
            margin-top: 2px;
        }
        .error {
            color: #dc2626;
            padding: 12px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔑 Generador de Licencias IPV</h1>
        <p class="subtitle">Herramienta interna. NO distribuir con el sistema.</p>

        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Install ID (recibido del cliente) *</label>
            <input type="text" name="install_id" required
                   value="<?= htmlspecialchars($_POST['install_id'] ?? '') ?>"
                   placeholder="Pega aquí el ID que te envió el cliente"
                   autocomplete="off">

            <label>Días de licencia</label>
            <input type="number" name="dias"
                   value="<?= htmlspecialchars($_POST['dias'] ?? DIAS_LICENCIA_DEFAULT) ?>"
                   min="1" max="3650">

            <label>Nombre del cliente (opcional)</label>
            <input type="text" name="cliente"
                   value="<?= htmlspecialchars($_POST['cliente'] ?? '') ?>"
                   placeholder="Ej: Juan Pérez"
                   autocomplete="off">

            <button type="submit">Generar Licencia</button>
        </form>

        <?php if (isset($resultado)): ?>
            <?php
            // Decodificar para mostrar info (sin verificar)
            $partes = explode('.', $resultado);
            $payloadJson = base64_decode($partes[0] ?? '', true);
            $datos = $payloadJson ? json_decode($payloadJson, true) : null;
            ?>
            <div class="resultado">
                <h3>✅ Licencia generada</h3>
                <p class="info">Copia el código y envíalo al cliente.</p>

                <?php if ($datos): ?>
                    <div class="meta">
                        <div class="meta-item">
                            <div class="label">Cliente</div>
                            <div class="valor"><?= htmlspecialchars($datos['cliente'] ?: '—') ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="label">Emitida</div>
                            <div class="valor"><?= htmlspecialchars($datos['emitida']) ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="label">Expira</div>
                            <div class="valor"><?= htmlspecialchars($datos['expira']) ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="label">Días</div>
                            <div class="valor"><?= (int)$datos['dias'] ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="acciones">
                    <button type="button" class="btn-copiar" id="btn-copiar" onclick="copiarLicencia()">
                        📋 Copiar al portapapeles
                    </button>
                    <button type="button" class="btn-copiar" onclick="descargarLicencia()">
                        💾 Descargar .txt
                    </button>
                </div>

                <textarea id="licencia-output" readonly style="margin-top:12px;min-height:120px;"><?= htmlspecialchars($resultado) ?></textarea>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function copiarLicencia() {
        const textarea = document.getElementById('licencia-output');
        const btn = document.getElementById('btn-copiar');

        if (!textarea) return;

        // Método moderno
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(textarea.value).then(() => {
                marcarCopiado(btn);
            }).catch(() => {
                copiarFallback(textarea, btn);
            });
        } else {
            copiarFallback(textarea, btn);
        }
    }

    function copiarFallback(textarea, btn) {
        textarea.select();
        textarea.setSelectionRange(0, 999999);
        try {
            document.execCommand('copy');
            marcarCopiado(btn);
        } catch (e) {
            alert('No se pudo copiar. Selecciona el texto manualmente.');
        }
    }

    function marcarCopiado(btn) {
        const original = btn.innerHTML;
        btn.innerHTML = '✅ Copiado';
        btn.classList.add('copiado');
        setTimeout(() => {
            btn.innerHTML = original;
            btn.classList.remove('copiado');
        }, 2000);
    }

    function descargarLicencia() {
        const textarea = document.getElementById('licencia-output');
        if (!textarea) return;

        const contenido = textarea.value;
        const blob = new Blob([contenido], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);

        // Nombre del archivo basado en el cliente y la fecha
        const fecha = new Date().toISOString().slice(0, 10);
        const nombre = 'licencia_ipv_' + fecha + '.lic';

        const a = document.createElement('a');
        a.href = url;
        a.download = nombre;
        a.click();

        URL.revokeObjectURL(url);
    }
    </script>
</body>
</html>