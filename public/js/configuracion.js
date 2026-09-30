/**
 * IPV - Configuración del sistema
 */

const Configuracion = (() => {
    const BASE_URL = window.APP_BASE_URL || '/ipv/';

    let config = {};
    let cambios = {};
    let original = {};
    let tabActiva = 'seguridad';

    const ETIQUETAS = {
        pass_longitud_min:              ['Longitud mínima',              'Número mínimo de caracteres'],
        pass_req_mayuscula:             ['Requerir mayúscula',           'Al menos una letra mayúscula'],
        pass_req_minuscula:             ['Requerir minúscula',           'Al menos una letra minúscula'],
        pass_req_numero:                ['Requerir número',              'Al menos un dígito'],
        pass_req_simbolo:               ['Requerir símbolo',             'Al menos un carácter especial'],
        pass_historial:                 ['Historial de contraseñas',     'No repetir las últimas N'],
        pass_caducidad_dias:            ['Caducidad (días)',             '0 = nunca caduca'],
        pos_permitir_stock_negativo:    ['Permitir stock negativo',      'Vender aunque quede negativo'],
        pos_mostrar_sin_stock:          ['Mostrar productos sin stock',  'Aparecen bloqueados'],
        pos_modo_conteo_default:        ['Modo de conteo por defecto',   'Cómo inicia el cobro'],
        pos_ticket_tamano:              ['Tamaño del ticket',            'Ancho del papel'],
        pos_imprimir_preguntar:         ['Preguntar antes de imprimir',  'El vendedor decide'],
        turno_cierre_con_conteo_default:['Cierre con conteo físico',     'Pide conteo al cerrar'],
        turno_bloquear_si_negativos:    ['Bloquear cierre con negativos','No cerrar si hay negativos'],
        cierre_forzar_supervisor:       ['Supervisor puede forzar',      'Forzar con justificación'],
        cierre_forzar_admin:            ['Admin puede forzar',           'Forzar con justificación'],
        cierre_justificacion_min:       ['Mínimo justificación',         'Caracteres mínimos'],
        supervisor_puede_ajustar:       ['Supervisor ajusta stock',      'Puede hacer ajustes'],
        ajustes_inmediatos:             ['Ajustes inmediatos',           'Sin aprobación'],
        divisas_habilitadas:            ['Venta en divisas',             'Habilitar cobro en USD/EUR'],
        divisas_auto_update:            ['Actualización automática',     'Obtener tasa desde internet'],
        divisas_auto_frecuencia:        ['Frecuencia de actualización',  'Cada cuánto'],
        divisas_auto_fuente:            ['Fuente de tasas',              'De dónde se obtienen'],
        divisas_permitir_manual:        ['Permitir sobrescritura',       'Admin fija tasa manual'],
        divisas_manual_duracion_horas:  ['Duración manual (horas)',      'Horas antes de auto'],
        transf_comprobante_adjunto:     ['Comprobante adjunto',          'Política de subida'],
        transf_comprobante_max_mb:      ['Tamaño máximo (MB)',           'Tamaño máximo'],
        transf_verificacion:            ['Verificación del supervisor',  'Obligatoria u opcional'],
        transf_comprobante_retencion:   ['Retención (días)',             '0 = indefinido'],
        notif_email_criticas:           ['Email en eventos críticos',    'Enviar correo al admin'],
        notif_polling_segundos:         ['Intervalo de polling',         'Segundos entre consultas'],
        notif_intentos_login:           ['Intentos antes de bloqueo',    'Número de intentos'],
    };

    const OPCIONES_SELECT = {
        pos_modo_conteo_default: [['obligatorio','Obligatorio'],['opcional','Opcional'],['deshabilitado','Deshabilitado']],
        pos_ticket_tamano: [['58mm','58 mm'],['80mm','80 mm'],['A4','A4']],
        divisas_auto_frecuencia: [['hora','Cada hora'],['6horas','Cada 6 horas'],['diaria','Diaria (08:00)'],['off','Desactivada']],
        divisas_auto_fuente: [['eltoque','El Toque (TRMI)'],['bcc','Banco Central de Cuba'],['manual','Solo manual']],
        transf_comprobante_adjunto: [['no_permitir','No permitir'],['opcional','Opcional'],['obligatorio','Obligatorio']],
        transf_verificacion: [['obligatoria','Obligatoria'],['opcional','Opcional']],
    };

    async function cargar() {
        const cont = document.getElementById('contenido-config');
        cont.innerHTML = '<div class="empty-state"><div class="spinner spinner-lg"></div></div>';

        const res = await Api.get('api/configuracion.php?accion=listar');
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        config = res.data;
        original = JSON.parse(JSON.stringify(config));
        renderTab();
    }

    function renderTab() {
        const cont = document.getElementById('contenido-config');
        const items = config[tabActiva] || [];

        if (!items.length) {
            cont.innerHTML = `<div class="alert alert-warning">No hay parámetros en esta categoría.</div>`;
            return;
        }

        const html = items.map(item => renderItem(item)).join('');

        cont.innerHTML = `
            <div class="config-section">
                ${html}
            </div>
            <div class="text-center mt-4">
                <button class="btn btn-secondary btn-sm" onclick="Configuracion.restaurar('${tabActiva}')">
                    <i class="bi bi-arrow-counterclockwise"></i> Restaurar valores por defecto
                </button>
            </div>
        `;

        restaurarValoresCambiados();
    }

    function renderItem(item) {
        const clave = item.clave;
        const valor = getValorActual(clave);
        const tipo = item.tipo;
        const [label, help] = ETIQUETAS[clave] || [clave, ''];

        const esCambiado = clave in cambios;

        let inputHtml = '';
        switch (tipo) {
            case 'booleano':
                inputHtml = `
                    <label class="check">
                        <input type="checkbox" id="${clave}" ${valor == 1 ? 'checked' : ''} onchange="Configuracion.cambiar('${clave}', this.checked ? 1 : 0)">
                        <span>${escapeHtml(label)}</span>
                    </label>
                `;
                break;
            case 'numero':
                inputHtml = `
                    <label for="${clave}">${escapeHtml(label)}</label>
                    <input type="number" id="${clave}" value="${escapeAttr(valor)}" min="0" step="1" oninput="Configuracion.cambiar('${clave}', this.value)">
                `;
                break;
            case 'select':
                const opciones = OPCIONES_SELECT[clave] || [];
                inputHtml = `
                    <label for="${clave}">${escapeHtml(label)}</label>
                    <select id="${clave}" onchange="Configuracion.cambiar('${clave}', this.value)">
                        ${opciones.map(([v, l]) => `<option value="${v}" ${valor === v ? 'selected' : ''}>${escapeHtml(l)}</option>`).join('')}
                    </select>
                `;
                break;
            default:
                inputHtml = `
                    <label for="${clave}">${escapeHtml(label)}</label>
                    <input type="text" id="${clave}" value="${escapeAttr(valor)}" maxlength="200" oninput="Configuracion.cambiar('${clave}', this.value)">
                `;
        }

        return `
            <div class="field" data-clave="${clave}" style="padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 8px; ${esCambiado ? 'background: var(--warning-light); border-left: 3px solid var(--warning);' : ''}">
                ${inputHtml}
                ${help ? `<div class="help">${escapeHtml(help)}</div>` : ''}
            </div>
        `;
    }

    function restaurarValoresCambiados() {
        for (const clave in cambios) {
            const field = document.querySelector(`[data-clave="${clave}"]`);
            if (field) {
                field.style.background = 'var(--warning-light)';
                field.style.borderLeft = '3px solid var(--warning)';
            }
        }
    }

    function cambiar(clave, valor) {
        const orig = getValorOriginal(clave);
        if (String(valor) === String(orig)) {
            delete cambios[clave];
        } else {
            cambios[clave] = valor;
        }
        actualizarSaveBar();
        renderTab();
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
        const hayCambios = Object.keys(cambios).length > 0;
        bar.style.display = hayCambios ? '' : 'none';
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

        const res = await Api.post('api/configuracion.php?accion=guardar', { cambios });

        if (!res.success) {
            if (res.errors) Toast.error('Errores', Object.values(res.errors).join('<br>'));
            else Toast.error('Error', res.message);
            return;
        }

        Toast.success('Guardado', `${res.data.actualizadas} cambio(s) aplicado(s)`);

        for (const clave in cambios) {
            for (const cat in original) {
                for (const item of original[cat]) {
                    if (item.clave === clave) item.valor = String(cambios[clave]);
                }
            }
        }
        cambios = {};
        actualizarSaveBar();
        renderTab();
    }

    async function restaurar(categoria) {
        const ok = await App.confirmar(`¿Restaurar los valores por defecto de "${categoria}"?`, '⚠️ Restaurar');
        if (!ok) return;

        const res = await Api.post('api/configuracion.php?accion=restaurar', { categoria });
        if (!res.success) {
            Toast.error('Error', res.message);
            return;
        }

        Toast.success('Restaurado', 'Valores por defecto aplicados');
        cambios = {};
        actualizarSaveBar();
        cargar();
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
        cargar();

        document.querySelectorAll('#tabs-config .tab').forEach(btn => {
            btn.addEventListener('click', () => {
                if (Object.keys(cambios).length > 0) {
                    if (!confirm('Tienes cambios sin guardar. ¿Descartarlos?')) return;
                    cambios = {};
                    actualizarSaveBar();
                }
                document.querySelectorAll('#tabs-config .tab').forEach(b => b.classList.remove('active'));
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

    return { cargar, cambiar, guardar, descartarCambios, restaurar };
})();

window.Configuracion = Configuracion;