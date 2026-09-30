/**
 * IPV - Personalización
 */

const Personalizacion = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let config = {};
    let cambios = {};
    let tabActiva = 'identidad';
    let original = {};

    async function cargar() {
        const cont = document.getElementById('contenido-personalizacion');
        if (!cont) return;

        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        try {
            const res = await Api.get('api/personalizacion.php?accion=listar');

            if (!res || !res.success) {
                cont.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> ${res?.message || 'Error al cargar'}</div>`;
                return;
            }

            config = res.data;
            original = JSON.parse(JSON.stringify(config));

            try {
                renderTab();
            } catch (e) {
                cont.innerHTML = `<div class="alert alert-danger">Error al renderizar: ${App.escapeHtml(e.message)}</div>`;
            }

        } catch (e) {
            cont.innerHTML = `<div class="alert alert-danger">Error: ${App.escapeHtml(e.message)}</div>`;
        }
    }

    function renderTab() {
        const cont = document.getElementById('contenido-personalizacion');
        if (!cont) return;

        switch (tabActiva) {
            case 'identidad':  cont.innerHTML = renderIdentidad();  break;
            case 'contacto':   cont.innerHTML = renderContacto();   break;
            case 'colores':    cont.innerHTML = renderColores();    break;
            case 'moneda':     cont.innerHTML = renderMoneda();     break;
            case 'tema':       cont.innerHTML = renderTema();       break;
            case 'login':      cont.innerHTML = renderLogin();      break;
            case 'documentos': cont.innerHTML = renderDocumentos(); break;
        }

        try {
            if (tabActiva === 'colores')   aplicarVistaPreviaColores();
            if (tabActiva === 'tema')      aplicarVistaPreviaTema();
            if (tabActiva === 'identidad') aplicarVistaPreviaIdentidad();
            if (tabActiva === 'moneda')    rePreviewMoneda();
        } catch (e) { console.error('Post-render:', e); }

        restaurarValoresCambiados();
    }

    function renderIdentidad() {
        const logo = getValorActual('empresa_logo');
        const favicon = getValorActual('empresa_favicon');

        return `
            <div class="config-section">
                <h3><i class="bi bi-building"></i> Información del negocio</h3>
                <div class="form-grid">
                    <div class="field span-2">
                        <label for="empresa_nombre">Nombre del negocio</label>
                        <input type="text" id="empresa_nombre" maxlength="100" value="${escapeAttr(getValorActual('empresa_nombre'))}" oninput="Personalizacion.cambiar('empresa_nombre', this.value)">
                    </div>
                    <div class="field span-2">
                        <label for="empresa_eslogan">Eslogan</label>
                        <input type="text" id="empresa_eslogan" maxlength="150" value="${escapeAttr(getValorActual('empresa_eslogan'))}" oninput="Personalizacion.cambiar('empresa_eslogan', this.value)">
                    </div>
                    <div class="field span-full">
                        <label for="empresa_descripcion">Descripción corta</label>
                        <textarea id="empresa_descripcion" maxlength="200" oninput="Personalizacion.cambiar('empresa_descripcion', this.value)">${escapeHtml(getValorActual('empresa_descripcion'))}</textarea>
                    </div>
                </div>

                <div class="mt-4">
                    <h4 style="font-size:14px;margin-bottom:12px;"><i class="bi bi-card-image text-primary"></i> Logotipo</h4>
                    ${logo ? `
                        <div class="upload-preview">
                            <div class="upload-preview-img"><img src="${BASE_URL}${logo}" alt="Logo" onerror="this.style.display='none'"></div>
                            <div class="upload-preview-info">
                                <div class="name">${logo.split('/').pop()}</div>
                                <div class="hint">PNG, JPG, SVG o WEBP · Máx. 3 MB</div>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-secondary btn-sm" onclick="document.getElementById('file-logo').click()"><i class="bi bi-arrow-repeat"></i> Cambiar</button>
                                <button class="btn btn-danger btn-sm" onclick="Personalizacion.eliminarImagen('empresa_logo')"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                    ` : `
                        <div class="upload-zone" onclick="document.getElementById('file-logo').click()">
                            <i class="bi bi-cloud-arrow-up"></i>
                            <div class="upload-zone-text">Subir logotipo</div>
                            <div class="upload-zone-hint">PNG, JPG, SVG o WEBP · Máx. 3 MB</div>
                        </div>
                    `}
                    <input type="file" id="file-logo" accept=".png,.jpg,.jpeg,.svg,.webp" style="display:none;" onchange="Personalizacion.subirImagen('logo', this)">
                </div>

                <div class="mt-4">
                    <h4 style="font-size:14px;margin-bottom:12px;"><i class="bi bi-star-fill text-primary"></i> Favicon</h4>
                    ${favicon ? `
                        <div class="upload-preview">
                            <div class="upload-preview-img" style="width:60px;height:60px;"><img src="${BASE_URL}${favicon}" alt="Favicon" onerror="this.style.display='none'"></div>
                            <div class="upload-preview-info">
                                <div class="name">${favicon.split('/').pop()}</div>
                                <div class="hint">PNG, ICO o SVG · Máx. 1 MB</div>
                            </div>
                            <button class="btn btn-danger btn-sm" onclick="Personalizacion.eliminarImagen('empresa_favicon')"><i class="bi bi-trash"></i></button>
                        </div>
                    ` : `
                        <div class="upload-zone" onclick="document.getElementById('file-favicon').click()" style="padding:24px;">
                            <i class="bi bi-star" style="font-size:32px;"></i>
                            <div class="upload-zone-text">Subir favicon</div>
                            <div class="upload-zone-hint">PNG, ICO o SVG · Máx. 1 MB</div>
                        </div>
                    `}
                    <input type="file" id="file-favicon" accept=".png,.ico,.svg" style="display:none;" onchange="Personalizacion.subirImagen('favicon', this)">
                </div>

                <div class="field-preview mt-4" id="preview-identidad"></div>
            </div>
        `;
    }

    function aplicarVistaPreviaIdentidad() {
        const cont = document.getElementById('preview-identidad');
        if (!cont) return;

        const nombre = getValorActual('empresa_nombre');
        const eslogan = getValorActual('empresa_eslogan');
        const descripcion = getValorActual('empresa_descripcion');
        const logo = getValorActual('empresa_logo');

        cont.innerHTML = `
            <div class="text-xs" style="margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em;">Vista previa</div>
            <div style="background:var(--surface);border-radius:var(--radius-md);padding:16px;display:flex;align-items:center;gap:12px;">
                ${logo 
                    ? `<img src="${BASE_URL}${logo}" style="width:48px;height:48px;object-fit:contain;border-radius:8px;background:#fff;" onerror="this.style.display='none'">`
                    : `<div style="width:48px;height:48px;border-radius:8px;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;"><i class="bi bi-box-seam-fill"></i></div>`
                }
                <div>
                    <div style="font-weight:700;font-size:15px;">${escapeHtml(nombre || 'Mi Negocio')}</div>
                    <div class="text-xs text-muted">${escapeHtml(eslogan || '')}</div>
                    ${descripcion ? `<div class="text-xs text-muted mt-1">${escapeHtml(descripcion)}</div>` : ''}
                </div>
            </div>
        `;
    }

    function renderContacto() {
        return `
            <div class="config-section">
                <h3><i class="bi bi-telephone"></i> Datos de contacto</h3>
                <div class="form-grid">
                    <div class="field span-full">
                        <label for="empresa_direccion">Dirección</label>
                        <input type="text" id="empresa_direccion" maxlength="200" value="${escapeAttr(getValorActual('empresa_direccion'))}" oninput="Personalizacion.cambiar('empresa_direccion', this.value)">
                    </div>
                    <div class="field">
                        <label for="empresa_telefono">Teléfono</label>
                        <input type="text" id="empresa_telefono" maxlength="30" value="${escapeAttr(getValorActual('empresa_telefono'))}" oninput="Personalizacion.cambiar('empresa_telefono', this.value)">
                    </div>
                    <div class="field">
                        <label for="empresa_email">Correo</label>
                        <input type="email" id="empresa_email" maxlength="120" value="${escapeAttr(getValorActual('empresa_email'))}" oninput="Personalizacion.cambiar('empresa_email', this.value)">
                    </div>
                    <div class="field span-full">
                        <label for="empresa_web">Sitio web</label>
                        <input type="text" id="empresa_web" maxlength="120" value="${escapeAttr(getValorActual('empresa_web'))}" oninput="Personalizacion.cambiar('empresa_web', this.value)">
                    </div>
                </div>
            </div>
        `;
    }

    function renderColores() {
        const colores = [
            ['color_primario',   'Color primario',    'Botones principales, enlaces, acentos'],
            ['color_secundario', 'Color secundario',  'Hover, gradientes, cabeceras'],
            ['color_acento',     'Color de acento',   'Alertas informativas, destacados'],
            ['color_exito',      'Color de éxito',    'Confirmaciones, estados positivos'],
            ['color_peligro',    'Color de peligro',  'Errores, eliminaciones, alertas críticas'],
        ];

        return `
            <div class="config-section">
                <h3><i class="bi bi-palette"></i> Paleta de colores</h3>
                <p class="text-muted mb-4">Los cambios se aplican en tiempo real.</p>
                <div class="form-grid">
                    ${colores.map(([clave, label, desc]) => {
                        const valor = getValorActual(clave) || '#2563eb';
                        return `
                            <div class="field">
                                <label for="${clave}">${label}</label>
                                <div class="color-picker-wrapper">
                                    <input type="color" id="${clave}" value="${valor}" oninput="Personalizacion.cambiarColor('${clave}', this.value)">
                                    <input type="text" id="${clave}_text" value="${valor}" maxlength="7" oninput="Personalizacion.cambiarColorTexto('${clave}', this.value)">
                                </div>
                                <div class="help">${desc}</div>
                            </div>
                        `;
                    }).join('')}
                </div>
                <div class="field-preview mt-4" id="preview-colores"></div>
            </div>
        `;
    }

    function aplicarVistaPreviaColores() {
        const cont = document.getElementById('preview-colores');
        if (!cont) return;

        const primario = getValorActual('color_primario') || '#2563eb';
        const secundario = getValorActual('color_secundario') || '#1e40af';
        const acento = getValorActual('color_acento') || '#f59e0b';
        const exito = getValorActual('color_exito') || '#16a34a';
        const peligro = getValorActual('color_peligro') || '#dc2626';

        cont.innerHTML = `
            <div class="text-xs" style="margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em;">Vista previa</div>
            <div style="padding:20px;background:linear-gradient(135deg, ${primario} 0%, ${secundario} 100%);border-radius:12px;color:#fff;margin-bottom:12px;">
                <div style="font-weight:700;font-size:16px;">Encabezado de ejemplo</div>
                <div style="opacity:.9;font-size:13px;">Con gradiente del primario y secundario</div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button style="padding:8px 16px;border-radius:8px;background:${primario};color:#fff;border:none;font-weight:600;font-size:13px;cursor:pointer;">Primario</button>
                <button style="padding:8px 16px;border-radius:8px;background:${secundario};color:#fff;border:none;font-weight:600;font-size:13px;cursor:pointer;">Secundario</button>
                <button style="padding:8px 16px;border-radius:8px;background:${acento};color:#fff;border:none;font-weight:600;font-size:13px;cursor:pointer;">Acento</button>
                <button style="padding:8px 16px;border-radius:8px;background:${exito};color:#fff;border:none;font-weight:600;font-size:13px;cursor:pointer;">Éxito</button>
                <button style="padding:8px 16px;border-radius:8px;background:${peligro};color:#fff;border:none;font-weight:600;font-size:13px;cursor:pointer;">Peligro</button>
            </div>
        `;
    }

    function renderMoneda() {
        return `
            <div class="config-section">
                <h3><i class="bi bi-cash-coin"></i> Moneda y formato</h3>
                <div class="form-grid">
                    <div class="field">
                        <label for="moneda_codigo">Código de moneda</label>
                        <input type="text" id="moneda_codigo" maxlength="5" value="${escapeAttr(getValorActual('moneda_codigo'))}" oninput="Personalizacion.cambiar('moneda_codigo', this.value); Personalizacion.rePreviewMoneda();">
                    </div>
                    <div class="field">
                        <label for="moneda_simbolo">Símbolo</label>
                        <input type="text" id="moneda_simbolo" maxlength="5" value="${escapeAttr(getValorActual('moneda_simbolo'))}" oninput="Personalizacion.cambiar('moneda_simbolo', this.value); Personalizacion.rePreviewMoneda();">
                    </div>
                    <div class="field">
                        <label for="moneda_posicion">Posición del símbolo</label>
                        <select id="moneda_posicion" onchange="Personalizacion.cambiar('moneda_posicion', this.value); Personalizacion.rePreviewMoneda();">
                            <option value="antes" ${getValorActual('moneda_posicion') === 'antes' ? 'selected' : ''}>Antes del número ($100.00)</option>
                            <option value="despues" ${getValorActual('moneda_posicion') === 'despues' ? 'selected' : ''}>Después del número (100.00 $)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="moneda_decimales">Decimales</label>
                        <select id="moneda_decimales" onchange="Personalizacion.cambiar('moneda_decimales', this.value); Personalizacion.rePreviewMoneda();">
                            ${[0,1,2,3,4].map(n => `<option value="${n}" ${getValorActual('moneda_decimales') == n ? 'selected' : ''}>${n} decimal${n !== 1 ? 'es' : ''}</option>`).join('')}
                        </select>
                    </div>
                    <div class="field">
                        <label for="formato_fecha">Formato de fecha y hora</label>
                        <input type="text" id="formato_fecha" maxlength="30" value="${escapeAttr(getValorActual('formato_fecha'))}" oninput="Personalizacion.cambiar('formato_fecha', this.value)">
                    </div>
                    <div class="field">
                        <label for="zona_horaria">Zona horaria</label>
                        <select id="zona_horaria" onchange="Personalizacion.cambiar('zona_horaria', this.value)">
                            ${['America/Havana','America/Mexico_City','America/Bogota','America/Lima','America/Santiago','America/Argentina/Buenos_Aires','America/New_York','Europe/Madrid','Europe/London','UTC'].map(z => `<option value="${z}" ${getValorActual('zona_horaria') === z ? 'selected' : ''}>${z}</option>`).join('')}
                        </select>
                    </div>
                </div>
                <div class="field-preview mt-4" id="preview-moneda"></div>
            </div>
        `;
    }

    function rePreviewMoneda() {
        setTimeout(() => {
            const cont = document.getElementById('preview-moneda');
            if (!cont) return;
            const simbolo = getValorActual('moneda_simbolo') || '$';
            const posicion = getValorActual('moneda_posicion') || 'antes';
            const decimales = parseInt(getValorActual('moneda_decimales')) || 0;
            const ejemplo = 1234.5;
            const conMiles = ejemplo.toLocaleString('es-ES', { minimumFractionDigits: decimales, maximumFractionDigits: decimales });
            const valor = posicion === 'antes' ? `${simbolo}${conMiles}` : `${conMiles} ${simbolo}`;

            cont.innerHTML = `
                <div class="text-xs" style="margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em;">Vista previa</div>
                Ejemplo: <strong style="font-size:18px;color:var(--primary);">${valor}</strong>
            `;
        }, 0);
    }

    function renderTema() {
        const modo = getValorActual('tema_modo');
        const tipografia = getValorActual('tipografia');
        const permitir = getValorActual('permitir_cambio_tema');

        return `
            <div class="config-section">
                <h3><i class="bi bi-paint-bucket"></i> Tema visual</h3>
                <div class="form-grid">
                    <div class="field">
                        <label for="tema_modo">Modo por defecto</label>
                        <select id="tema_modo" onchange="Personalizacion.cambiar('tema_modo', this.value); Personalizacion.aplicarVistaPreviaTema();">
                            <option value="claro" ${modo === 'claro' ? 'selected' : ''}>☀️ Claro</option>
                            <option value="oscuro" ${modo === 'oscuro' ? 'selected' : ''}>🌙 Oscuro</option>
                            <option value="auto" ${modo === 'auto' ? 'selected' : ''}>⚙️ Auto</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="tipografia">Tipografía</label>
                        <select id="tipografia" onchange="Personalizacion.cambiar('tipografia', this.value); Personalizacion.aplicarVistaPreviaTema();">
                            ${['Inter','Poppins','Roboto','system-ui'].map(f => `<option value="${f}" ${tipografia === f ? 'selected' : ''}>${f}</option>`).join('')}
                        </select>
                    </div>
                    <div class="field span-full">
                        <label class="check">
                            <input type="checkbox" id="permitir_cambio_tema" ${permitir == 1 ? 'checked' : ''} onchange="Personalizacion.cambiar('permitir_cambio_tema', this.checked ? 1 : 0)">
                            <span>Permitir que los usuarios cambien su propio tema</span>
                        </label>
                    </div>
                </div>
                <div class="field-preview mt-4" id="preview-tema"></div>
            </div>
        `;
    }

    function aplicarVistaPreviaTema() {
        const cont = document.getElementById('preview-tema');
        if (!cont) return;

        const modo = getValorActual('tema_modo');
        const tipografia = getValorActual('tipografia') || 'Inter';
        const esOscuro = modo === 'oscuro';

        const fuentesCSS = {
            'Inter':     "'Inter', system-ui, sans-serif",
            'Poppins':   "'Poppins', system-ui, sans-serif",
            'Roboto':    "'Roboto', system-ui, sans-serif",
            'system-ui': "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
        };
        const fuenteCSS = fuentesCSS[tipografia] || fuentesCSS['Inter'];

        const fuentesGoogle = {
            'Inter':   'Inter:wght@400;600;700',
            'Poppins': 'Poppins:wght@400;600;700',
            'Roboto':  'Roboto:wght@400;700',
        };
        if (fuentesGoogle[tipografia]) {
            const linkId = 'font-preview-' + tipografia;
            if (!document.getElementById(linkId)) {
                const link = document.createElement('link');
                link.id = linkId;
                link.rel = 'stylesheet';
                link.href = `https://fonts.googleapis.com/css2?family=${fuentesGoogle[tipografia]}&display=swap`;
                document.head.appendChild(link);
            }
        }

        cont.innerHTML = `
            <div class="text-xs" style="margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em;">
                Vista previa · Fuente: <strong>${escapeHtml(tipografia)}</strong>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div class="theme-preview" style="background:${esOscuro ? '#1e293b' : '#f8fafc'};color:${esOscuro ? '#f1f5f9' : '#0f172a'};font-family:${fuenteCSS};">
                    <div style="font-weight:700;font-size:15px;margin-bottom:6px;">${modo === 'auto' ? '⚙️ Auto' : (esOscuro ? '🌙 Oscuro' : '☀️ Claro')}</div>
                    <div style="font-size:13px;opacity:.8;">Modo actual</div>
                </div>
                <div class="theme-preview" style="background:${esOscuro ? '#f8fafc' : '#1e293b'};color:${esOscuro ? '#0f172a' : '#f1f5f9'};font-family:${fuenteCSS};">
                    <div style="font-weight:700;font-size:15px;margin-bottom:6px;">${esOscuro ? '☀️ Claro' : '🌙 Oscuro'}</div>
                    <div style="font-size:13px;opacity:.8;">Alternativa</div>
                </div>
            </div>
            <div style="padding:16px;background:var(--surface);border:1px solid var(--border);border-radius:12px;font-family:${fuenteCSS};">
                <div style="font-weight:700;font-size:16px;margin-bottom:6px;">Texto de ejemplo</div>
                <div style="font-size:14px;color:var(--text-muted);margin-bottom:10px;">El veloz murciélago hindú comía feliz cardillo y kiwi.</div>
                <div style="font-size:13px;line-height:1.6;"><strong>1234567890</strong> · Precio: ${App.formatMoney(1234.56)}</div>
            </div>
        `;
    }

    function renderLogin() {
        const fondo = getValorActual('login_fondo');
        return `
            <div class="config-section">
                <h3><i class="bi bi-box-arrow-in-right"></i> Pantalla de login</h3>
                <div class="field">
                    <label for="login_mensaje">Mensaje de bienvenida</label>
                    <input type="text" id="login_mensaje" maxlength="120" value="${escapeAttr(getValorActual('login_mensaje'))}" oninput="Personalizacion.cambiar('login_mensaje', this.value)">
                </div>
                <div class="mt-4">
                    <h4 style="font-size:14px;margin-bottom:12px;"><i class="bi bi-image text-primary"></i> Imagen de fondo</h4>
                    ${fondo ? `
                        <div class="upload-preview">
                            <div class="upload-preview-img" style="width:150px;height:80px;"><img src="${BASE_URL}${fondo}" alt="Fondo" onerror="this.style.display='none'"></div>
                            <div class="upload-preview-info">
                                <div class="name">${fondo.split('/').pop()}</div>
                                <div class="hint">PNG, JPG o WEBP · Máx. 5 MB</div>
                            </div>
                            <button class="btn btn-danger btn-sm" onclick="Personalizacion.eliminarImagen('login_fondo')"><i class="bi bi-trash"></i></button>
                        </div>
                    ` : `
                        <div class="upload-zone" onclick="document.getElementById('file-fondo').click()">
                            <i class="bi bi-cloud-arrow-up"></i>
                            <div class="upload-zone-text">Subir imagen de fondo</div>
                            <div class="upload-zone-hint">PNG, JPG o WEBP · Máx. 5 MB</div>
                        </div>
                    `}
                    <input type="file" id="file-fondo" accept=".png,.jpg,.jpeg,.webp" style="display:none;" onchange="Personalizacion.subirImagen('fondo', this)">
                </div>
            </div>
        `;
    }

    function renderDocumentos() {
        return `
            <div class="config-section">
                <h3><i class="bi bi-file-pdf"></i> Documentos (PDF)</h3>
                <div class="field mb-3">
                    <label for="pdf_encabezado">Encabezado</label>
                    <textarea id="pdf_encabezado" maxlength="500" style="min-height:80px;" oninput="Personalizacion.cambiar('pdf_encabezado', this.value)">${escapeHtml(getValorActual('pdf_encabezado'))}</textarea>
                </div>
                <div class="field mb-3">
                    <label for="pdf_pie">Pie de página</label>
                    <textarea id="pdf_pie" maxlength="500" style="min-height:80px;" oninput="Personalizacion.cambiar('pdf_pie', this.value)">${escapeHtml(getValorActual('pdf_pie'))}</textarea>
                </div>
                <div class="field">
                    <label for="pdf_texto_legal">Texto legal</label>
                    <textarea id="pdf_texto_legal" maxlength="1000" style="min-height:100px;" oninput="Personalizacion.cambiar('pdf_texto_legal', this.value)">${escapeHtml(getValorActual('pdf_texto_legal'))}</textarea>
                </div>
            </div>
        `;
    }

    function cambiar(clave, valor) {
        const orig = getValorOriginal(clave);
        if (String(valor) === String(orig)) {
            delete cambios[clave];
        } else {
            cambios[clave] = valor;
        }
        actualizarSaveBar();

        // Preview en vivo de tipografía
        if (clave === 'tipografia') {
            aplicarTipografiaEnVivo(valor);
        }
    }

    function aplicarTipografiaEnVivo(tipografia) {
        const fuentesCSS = {
            'Inter':     "'Inter', system-ui, sans-serif",
            'Poppins':   "'Poppins', system-ui, sans-serif",
            'Roboto':    "'Roboto', system-ui, sans-serif",
            'system-ui': "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
        };
        const fuente = fuentesCSS[tipografia] || fuentesCSS['Inter'];

        const fuentesGoogle = {
            'Inter':   'Inter:wght@400;500;600;700;800',
            'Poppins': 'Poppins:wght@400;500;600;700;800',
            'Roboto':  'Roboto:wght@400;500;700;900',
        };
        if (fuentesGoogle[tipografia]) {
            const linkId = 'font-live-' + tipografia;
            if (!document.getElementById(linkId)) {
                const link = document.createElement('link');
                link.id = linkId;
                link.rel = 'stylesheet';
                link.href = `https://fonts.googleapis.com/css2?family=${fuentesGoogle[tipografia]}&display=swap`;
                document.head.appendChild(link);
            }
        }

        const styleId = 'tipografia-preview-live';
        let styleEl = document.getElementById(styleId);
        if (!styleEl) {
            styleEl = document.createElement('style');
            styleEl.id = styleId;
            document.head.appendChild(styleEl);
        }
        styleEl.textContent = `body, input, select, textarea, button, h1, h2, h3, h4, h5, h6 { font-family: ${fuente} !important; }`;
    }

    function cambiarColor(clave, valor) {
        const textInput = document.getElementById(clave + '_text');
        if (textInput) textInput.value = valor;
        cambiar(clave, valor);
        aplicarVistaPreviaColores();
    }

    function cambiarColorTexto(clave, valor) {
        if (!/^#[0-9A-Fa-f]{6}$/.test(valor)) return;
        const colorInput = document.getElementById(clave);
        if (colorInput) colorInput.value = valor;
        cambiar(clave, valor);
        aplicarVistaPreviaColores();
    }

    function getValorActual(clave) {
        if (clave in cambios) return cambios[clave];
        return getValorOriginal(clave);
    }

    function getValorOriginal(clave) {
        for (const cat in original) {
            for (const item of original[cat]) {
                if (item.clave === clave) return item.valor || '';
            }
        }
        return '';
    }

    function actualizarSaveBar() {
        const bar = document.getElementById('save-bar');
        if (!bar) return;
        bar.style.display = Object.keys(cambios).length > 0 ? '' : 'none';
    }

    function restaurarValoresCambiados() {
        for (const clave in cambios) {
            const input = document.getElementById(clave);
            if (input) {
                if (input.type === 'checkbox') input.checked = cambios[clave] == 1;
                else input.value = cambios[clave];
            }
        }
    }

    function descartarCambios() {
        if (!confirm('¿Descartar todos los cambios sin guardar?')) return;
        cambios = {};
        actualizarSaveBar();
        renderTab();
    }

    async function guardar() {
        if (Object.keys(cambios).length === 0) {
            Toast.info('Sin cambios', 'No hay nada que guardar');
            return;
        }

        const res = await Api.post('api/personalizacion.php?accion=guardar', { cambios });

        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Guardado', `${res.data.actualizadas || res.data.actualizados} cambio(s) aplicado(s)`);

        for (const clave in cambios) {
            for (const cat in original) {
                for (const item of original[cat]) {
                    if (item.clave === clave) item.valor = String(cambios[clave]);
                }
            }
        }
        cambios = {};
        actualizarSaveBar();
    }

    async function subirImagen(tipo, input) {
        if (!input.files || !input.files[0]) return;
        const archivo = input.files[0];
        const formData = new FormData();
        formData.append('archivo', archivo);

        Toast.info('Subiendo...', archivo.name);

        const res = await Api.upload(`api/personalizacion.php?accion=subir_imagen&tipo=${tipo}`, formData);

        if (!res.success) {
            Toast.error('Error al subir', res.message);
            input.value = '';
            return;
        }

        Toast.success('Imagen actualizada', 'Se reflejará al recargar');

        const res2 = await Api.get('api/personalizacion.php?accion=listar');
        if (res2.success) {
            original = res2.data;
            config = res2.data;
            cambios = {};
            actualizarSaveBar();
            renderTab();
        }
        input.value = '';
    }

    async function eliminarImagen(clave) {
        const ok = await App.confirmar('¿Eliminar esta imagen?', 'Confirmar');
        if (!ok) return;

        const res = await Api.post('api/personalizacion.php?accion=eliminar_imagen', { clave });
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Eliminada', 'La imagen fue eliminada');

        const res2 = await Api.get('api/personalizacion.php?accion=listar');
        if (res2.success) {
            original = res2.data;
            config = res2.data;
            cambios = {};
            actualizarSaveBar();
            renderTab();
        }
    }

    function escapeHtml(t) {
        const div = document.createElement('div');
        div.textContent = t || '';
        return div.innerHTML;
    }
    function escapeAttr(t) {
        return String(t || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (!document.querySelector('link[href*="personalizacion.css"]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = BASE_URL + 'public/css/personalizacion.css';
            document.head.appendChild(link);
        }

        cargar();

        document.querySelectorAll('#tabs-personalizacion .tab').forEach(btn => {
            btn.addEventListener('click', () => {
                if (Object.keys(cambios).length > 0) {
                    if (!confirm('Tienes cambios sin guardar. ¿Descartarlos?')) return;
                    cambios = {};
                    actualizarSaveBar();
                }
                document.querySelectorAll('#tabs-personalizacion .tab').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                tabActiva = btn.dataset.tab;
                renderTab();
            });
        });

        window.addEventListener('beforeunload', (e) => {
            if (Object.keys(cambios).length > 0) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    });

    return {
        cargar, cambiar, cambiarColor, cambiarColorTexto,
        guardar, descartarCambios, subirImagen, eliminarImagen,
        aplicarVistaPreviaTema, rePreviewMoneda,
    };
})();

window.Personalizacion = Personalizacion;