#!/bin/bash
# ============================================================
# Reinstalar IPV de cero + verificación completa de permisos
# ============================================================
#
# Uso:
#   sudo bash reinstalar.sh           # Modo seguro: limpia archivos, NO toca MySQL
#   sudo bash reinstalar.sh --full    # Modo completo: borra BD y usuario dedicado (pide credenciales root)
#
# Notas:
#   - El instalador web crea el usuario dedicado (por defecto: ipv_user@localhost).
#   - Si usas --full, se borran la BD y el usuario dedicado existentes.
# ============================================================

set -u

# ── Configuración ──
PROYECTO="${IPV_PATH:-/var/www/html/ipv}"
BD="${IPV_DB:-ipv_db}"
APP_USER="${IPV_APP_USER:-ipv_user}"
WEB_USER="www-data"
MODO_FULL=false

# Leer flags
for arg in "$@"; do
    case "$arg" in
        --full) MODO_FULL=true ;;
        --help|-h)
            echo "Uso: sudo bash reinstalar.sh [--full]"
            echo "  --full    Borra también la BD y el usuario dedicado (pide credenciales root de MySQL)"
            exit 0
            ;;
    esac
done

# Colores
VERDE="\033[0;32m"
ROJO="\033[0;31m"
AMARILLO="\033[1;33m"
AZUL="\033[0;34m"
RESET="\033[0m"

log_ok()   { echo -e "${VERDE}✅ $1${RESET}"; }
log_err()  { echo -e "${ROJO}❌ $1${RESET}"; }
log_warn() { echo -e "${AMARILLO}⚠️  $1${RESET}"; }
log_info() { echo -e "   $1"; }
log_sep()  { echo -e "${AZUL}────────────────────────────────────────────────────────${RESET}"; }

# ============================================================
# 0. Verificar proyecto y que sea IPV
# ============================================================
if [ ! -d "$PROYECTO" ]; then
    log_err "No existe el proyecto en $PROYECTO"
    log_info "Puedes cambiar la ruta con: IPV_PATH=/otra/ruta sudo bash reinstalar.sh"
    exit 1
fi

if [ ! -f "$PROYECTO/index.php" ] || [ ! -d "$PROYECTO/install" ] || [ ! -d "$PROYECTO/core" ]; then
    log_err "$PROYECTO no parece ser una instalación de IPV"
    log_info "Se esperaba encontrar: index.php, install/, core/"
    exit 1
fi

echo ""
echo "════════════════════════════════════════════════════════"
echo "  REINSTALACIÓN DE IPV"
echo "  Modo: $([ "$MODO_FULL" = true ] && echo 'COMPLETO (--full)' || echo 'SEGURO (sin tocar MySQL)')"
echo "  Proyecto: $PROYECTO"
echo "  BD: $BD"
echo "  Usuario dedicado: $APP_USER"
echo "════════════════════════════════════════════════════════"
echo ""

# ============================================================
# 0.b. Verificar root
# ============================================================
if [ "$(id -u)" -ne 0 ]; then
    log_err "Este script debe ejecutarse como root (sudo)"
    exit 1
fi

# ============================================================
# 0.c. Modo --full: pedir credenciales MySQL
# ============================================================
BD_USER=""
BD_PASS=""

if [ "$MODO_FULL" = true ]; then
    log_warn "MODO COMPLETO: se borrarán la BD '$BD' y el usuario '$APP_USER'"
    echo ""
    log_info "Credenciales del usuario administrativo de MySQL (root o equivalente)."
    log_info "Se usan SOLO para eliminar la BD y el usuario dedicado."
    echo ""

    read -p "Usuario MySQL [root]: " INPUT_USER
    BD_USER="${INPUT_USER:-root}"

    read -s -p "Contraseña MySQL (Enter si no tiene): " INPUT_PASS
    echo ""
    BD_PASS="$INPUT_PASS"

    echo ""
    log_info "Verificando conexión a MySQL..."

    if [ -z "$BD_PASS" ]; then
        mysql -u "$BD_USER" -e "SELECT 1;" &>/dev/null
    else
        mysql -u "$BD_USER" -p"$BD_PASS" -e "SELECT 1;" &>/dev/null
    fi

    if [ $? -ne 0 ]; then
        log_err "No se pudo conectar a MySQL con esas credenciales"
        exit 1
    fi

    log_ok "Conexión a MySQL verificada (usuario: $BD_USER)"
fi

echo ""
log_warn "ADVERTENCIA: Este script BORRA:"
echo "   • config/database.php, config.php, public.pem"
echo "   • Todos los uploads (logos, avatares, comprobantes)"
echo "   • Todos los backups, logs y reportes"
if [ "$MODO_FULL" = true ]; then
    echo "   • La base de datos '$BD'"
    echo "   • El usuario MySQL '$APP_USER'@'localhost'"
fi
echo ""
log_info "NO se borran: código fuente (api/, core/, views/, public/js, public/css)"
echo ""
read -p "¿Continuar con la reinstalación? (s/N): " CONFIRMAR
if [[ ! "$CONFIRMAR" =~ ^[sS]$ ]]; then
    log_info "Cancelado por el usuario."
    exit 0
fi

# ============================================================
# 1. Config del instalador
# ============================================================
echo ""
echo "🗑️  Eliminando config del instalador..."
rm -f "$PROYECTO/install/.lock"
rm -f "$PROYECTO/config/database.php"
rm -f "$PROYECTO/config/config.php"
rm -f "$PROYECTO/config/public.pem"
mkdir -p "$PROYECTO/config"
log_ok "Config eliminada"

# ============================================================
# 2. Base de datos (solo en --full)
# ============================================================
if [ "$MODO_FULL" = true ]; then
    echo ""
    echo "🗑️  Eliminando base de datos '$BD'..."
    if [ -z "$BD_PASS" ]; then
        mysql -u "$BD_USER" -e "DROP DATABASE IF EXISTS \`$BD\`;" 2>/dev/null
    else
        mysql -u "$BD_USER" -p"$BD_PASS" -e "DROP DATABASE IF EXISTS \`$BD\`;" 2>/dev/null
    fi
    if [ $? -eq 0 ]; then
        log_ok "Base de datos eliminada"
    else
        log_warn "No se pudo eliminar la BD (¿ya no existía?)"
    fi

    echo ""
    echo "🗑️  Eliminando usuario dedicado '$APP_USER'..."
    if [ -z "$BD_PASS" ]; then
        mysql -u "$BD_USER" -e "DROP USER IF EXISTS '$APP_USER'@'localhost';" 2>/dev/null
        mysql -u "$BD_USER" -e "DROP USER IF EXISTS '$APP_USER'@'127.0.0.1';" 2>/dev/null
        mysql -u "$BD_USER" -e "DROP USER IF EXISTS '$APP_USER'@'%';" 2>/dev/null
        mysql -u "$BD_USER" -e "FLUSH PRIVILEGES;" 2>/dev/null
    else
        mysql -u "$BD_USER" -p"$BD_PASS" -e "DROP USER IF EXISTS '$APP_USER'@'localhost';" 2>/dev/null
        mysql -u "$BD_USER" -p"$BD_PASS" -e "DROP USER IF EXISTS '$APP_USER'@'127.0.0.1';" 2>/dev/null
        mysql -u "$BD_USER" -p"$BD_PASS" -e "DROP USER IF EXISTS '$APP_USER'@'%';" 2>/dev/null
        mysql -u "$BD_USER" -p"$BD_PASS" -e "FLUSH PRIVILEGES;" 2>/dev/null
    fi
    log_ok "Usuario dedicado eliminado (si existía)"
else
    echo ""
    log_info "Modo seguro: NO se toca MySQL."
    log_info "Si quieres borrar la BD y el usuario dedicado, ejecuta con --full"
fi

# ============================================================
# 3. Uploads
# ============================================================
echo ""
echo "🗑️  Limpiando uploads..."
rm -rf "$PROYECTO/public/uploads"
log_ok "Uploads eliminados"

echo "🔧 Creando estructura de carpeta uploads"
mkdir -p "$PROYECTO/public/uploads/"{logos,fondos,favicons,comprobantes,avatars}

# .htaccess raíz de uploads
cat > "$PROYECTO/public/uploads/.htaccess" << 'EOF'
<FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$">
    Require all denied
</FilesMatch>
EOF

# .htaccess recursivos en cada subcarpeta
for sub in logos fondos favicons comprobantes avatars; do
    cat > "$PROYECTO/public/uploads/$sub/.htaccess" << 'EOF'
<FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$">
    Require all denied
</FilesMatch>
EOF
done

log_ok "Uploads recreados con .htaccess recursivos"

# ============================================================
# 4. Storage
# ============================================================
echo ""
echo "🗑️  Limpiando storage..."
rm -rf "$PROYECTO/storage/backups"
rm -rf "$PROYECTO/storage/reportes"
rm -rf "$PROYECTO/storage/logs"
rm -f  "$PROYECTO/storage/licencia.lic"
rm -f  "$PROYECTO/storage/last_seen.txt"
log_ok "Storage eliminados"

echo "🔧 Creando estructura de carpeta storage"
mkdir -p "$PROYECTO/storage/backups"
mkdir -p "$PROYECTO/storage/reportes"
mkdir -p "$PROYECTO/storage/logs"

# .htaccess raíz de storage
cat > "$PROYECTO/storage/.htaccess" << 'EOF'
# IPV - Denegar acceso directo a todo storage/
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>

<FilesMatch "\.(sql|log|lic|txt|pdf|xlsx|csv)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>
EOF

# .htaccess + index.php en subcarpetas
for sub in backups reportes logs; do
    cat > "$PROYECTO/storage/$sub/.htaccess" << 'EOF'
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>
EOF
    echo '<?php http_response_code(403); exit;' > "$PROYECTO/storage/$sub/index.php"
done

# index.php raíz de storage
echo '<?php http_response_code(403); exit;' > "$PROYECTO/storage/index.php"

# .gitignore de storage
cat > "$PROYECTO/storage/.gitignore" << 'EOF'
# Ignorar todo el contenido
*
!.gitignore
!index.php
!.htaccess

# Excepto los index.php y .htaccess de subcarpetas
!backups/.gitignore
!backups/index.php
!backups/.htaccess
!logs/.gitignore
!logs/index.php
!logs/.htaccess
!reportes/.gitignore
!reportes/index.php
!reportes/.htaccess

# Ignorar específicos
*.sql
*.log
*.lic
last_seen.txt
EOF

log_ok "Storage recreado con .htaccess, index.php y .gitignore"

# ============================================================
# 4.b. Proteger install/ (parcialmente)
# ============================================================
if [ -d "$PROYECTO/install" ]; then
    cat > "$PROYECTO/install/.htaccess" << 'EOF'
# IPV - Bloquear acceso directo a archivos sensibles del instalador
<FilesMatch "\.(pem|sql|log|lock)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>
EOF
    log_ok "install/.htaccess creado"
fi

# ============================================================
# 5. Verificar public.pem
# ============================================================
echo ""
echo "🔍 Verificando public.pem..."
if [ ! -f "$PROYECTO/install/public.pem" ]; then
    log_err "Falta $PROYECTO/install/public.pem"
    log_info "Cópialo desde tu máquina:"
    log_info "  cp /ruta/a/generador/keys/public.pem $PROYECTO/install/public.pem"
    exit 1
fi

if [ ! -s "$PROYECTO/install/public.pem" ]; then
    log_err "public.pem existe pero está vacío"
    exit 1
fi

log_ok "public.pem encontrado y no vacío"

mkdir -p "$PROYECTO/config"
cp "$PROYECTO/install/public.pem" "$PROYECTO/config/public.pem" 2>/dev/null || true
log_ok "public.pem copiado a config/"

# ============================================================
# 6. Verificar usuario del servidor web
# ============================================================
echo ""
echo "🔍 Verificando usuario del servidor web..."
if ! id "$WEB_USER" &>/dev/null; then
    log_warn "El usuario '$WEB_USER' no existe."
    log_info "Usuarios disponibles:"
    ps aux | grep -E 'apache|nginx|httpd|php-fpm' | grep -v grep | awk '{print "   - " $1}' | sort -u
    echo ""
    read -p "Introduce el usuario del servidor web [www-data]: " NUEVO_USUARIO
    if [ -n "$NUEVO_USUARIO" ]; then
        WEB_USER="$NUEVO_USUARIO"
    fi
    if ! id "$WEB_USER" &>/dev/null; then
        log_err "El usuario '$WEB_USER' no existe. Abortando."
        exit 1
    fi
fi
log_ok "Usuario del servidor web: $WEB_USER"

# ============================================================
# 7. APLICAR PERMISOS
# ============================================================
echo ""
log_sep
echo "  🔧 APLICANDO PERMISOS"
log_sep
echo ""

# 7.1. Carpetas con ESCRITURA (775)
log_info "Aplicando 775 a carpetas de escritura..."

CARPETAS_ESCRITURA=(
    "$PROYECTO/storage"
    "$PROYECTO/storage/backups"
    "$PROYECTO/storage/reportes"
    "$PROYECTO/storage/logs"
    "$PROYECTO/config"
    "$PROYECTO/public/uploads"
    "$PROYECTO/public/uploads/logos"
    "$PROYECTO/public/uploads/fondos"
    "$PROYECTO/public/uploads/favicons"
    "$PROYECTO/public/uploads/comprobantes"
    "$PROYECTO/public/uploads/avatars"
)

for ruta in "${CARPETAS_ESCRITURA[@]}"; do
    if [ -d "$ruta" ]; then
        chmod 775 "$ruta"
        log_ok "$(basename "$ruta")"
    else
        log_warn "$(basename "$ruta") (no existe, omitido)"
    fi
done

# 7.2. Carpetas de SOLO LECTURA (755)
echo ""
log_info "Aplicando 755 a carpetas de solo lectura..."

CARPETAS_LECTURA=(
    "$PROYECTO"
    "$PROYECTO/api"
    "$PROYECTO/core"
    "$PROYECTO/core/reportes"
    "$PROYECTO/views"
    "$PROYECTO/views/layouts"
    "$PROYECTO/views/admin"
    "$PROYECTO/views/supervisor"
    "$PROYECTO/views/vendedor"
    "$PROYECTO/views/dashboards"
    "$PROYECTO/views/almacen"
    "$PROYECTO/views/inventario"
    "$PROYECTO/public"
    "$PROYECTO/public/css"
    "$PROYECTO/public/js"
    "$PROYECTO/public/libs"
    "$PROYECTO/public/libs/chartjs"
    "$PROYECTO/public/libs/tcpdf"
    "$PROYECTO/public/libs/simplexlsxgen"
    "$PROYECTO/public/libs/parsedown"
    "$PROYECTO/install"
)

for ruta in "${CARPETAS_LECTURA[@]}"; do
    if [ -d "$ruta" ]; then
        chmod 755 "$ruta"
    fi
done
log_ok "Permisos de lectura aplicados"

# 7.3. Archivos críticos
echo ""
log_info "Aplicando permisos a archivos críticos..."

find "$PROYECTO" -name "*.php" -type f -exec chmod 644 {} +
log_ok "Archivos .php → 644"

find "$PROYECTO" -name ".htaccess" -type f -exec chmod 644 {} +
log_ok "Archivos .htaccess → 644"

find "$PROYECTO" -name "*.sql" -type f -exec chmod 644 {} +
log_ok "Archivos .sql → 644"

find "$PROYECTO/public" \( -name "*.css" -o -name "*.js" \) -type f -exec chmod 644 {} +
log_ok "Archivos .css/.js → 644"

if [ -f "$PROYECTO/install/public.pem" ]; then
    chmod 644 "$PROYECTO/install/public.pem"
fi
if [ -f "$PROYECTO/config/public.pem" ]; then
    chmod 644 "$PROYECTO/config/public.pem"
fi
log_ok "Claves públicas → 644"

find "$PROYECTO/storage" -type f -exec chmod 664 {} + 2>/dev/null
log_ok "Archivos de storage → 664"

# 7.4. Propietario
echo ""
log_info "Cambiando propietario del proyecto a $WEB_USER..."
chown -R "$WEB_USER:$WEB_USER" "$PROYECTO" 2>/dev/null
if [ $? -eq 0 ]; then
    log_ok "Propietario cambiado a $WEB_USER"
else
    log_warn "No se pudo cambiar el propietario"
fi

# 7.5. Permisos del instalador
echo ""
log_info "Verificando permisos del instalador..."
chmod 775 "$PROYECTO/config"
if [ -d "$PROYECTO/install" ]; then
    chmod 755 "$PROYECTO/install"
fi
log_ok "Permisos del instalador aplicados"

# ============================================================
# 8. VERIFICACIÓN FINAL
# ============================================================
echo ""
log_sep
echo "  🔍 VERIFICACIÓN"
log_sep
echo ""

PUEDE_SUDO=false
if [ "$(id -u)" -eq 0 ]; then
    PUEDE_SUDO=true
fi

TODOS_OK=true

log_info "Verificando permisos de escritura..."

CARPETAS_A_VERIFICAR=(
    "$PROYECTO/storage"
    "$PROYECTO/storage/backups"
    "$PROYECTO/storage/logs"
    "$PROYECTO/storage/reportes"
    "$PROYECTO/config"
    "$PROYECTO/public/uploads"
)

for carpeta in "${CARPETAS_A_VERIFICAR[@]}"; do
    if [ -d "$carpeta" ]; then
        if sudo -u "$WEB_USER" test -w "$carpeta" 2>/dev/null; then
            log_ok "$(basename "$carpeta") → escribible"
        else
            log_err "$(basename "$carpeta") → NO escribible"
            TODOS_OK=false
        fi
    fi
done

echo ""
log_info "Verificando permisos de lectura..."

CARPETAS_LECTURA_VERIF=(
    "$PROYECTO/api"
    "$PROYECTO/core"
    "$PROYECTO/views"
    "$PROYECTO/public"
)

for carpeta in "${CARPETAS_LECTURA_VERIF[@]}"; do
    if [ -d "$carpeta" ]; then
        if sudo -u "$WEB_USER" test -r "$carpeta" 2>/dev/null; then
            log_ok "$(basename "$carpeta") → legible"
        else
            log_err "$(basename "$carpeta") → NO legible"
            TODOS_OK=false
        fi
    fi
done

# ── Librerías PHP ──
echo ""
log_info "Verificando librerías PHP incluidas..."

LIBS_CRITICAS=(
    "$PROYECTO/public/libs/tcpdf/tcpdf.php"
    "$PROYECTO/public/libs/simplexlsxgen/SimpleXLSXGen.php"
    "$PROYECTO/public/libs/parsedown/Parsedown.php"
    "$PROYECTO/public/libs/chartjs/chart.min.js"
)

for lib in "${LIBS_CRITICAS[@]}"; do
    if [ -f "$lib" ]; then
        log_ok "$(basename "$lib")"
    else
        log_err "$(basename "$lib") → NO ENCONTRADO"
        TODOS_OK=false
    fi
done

# ── Archivos críticos ──
echo ""
log_info "Verificando archivos críticos..."

ARCHIVOS_CRITICOS=(
    "$PROYECTO/index.php"
    "$PROYECTO/logout.php"
    "$PROYECTO/install/index.php"
    "$PROYECTO/install/step1_requisitos.php"
    "$PROYECTO/install/step2_bd.php"
    "$PROYECTO/install/step3_admin.php"
    "$PROYECTO/install/step4_finalizar.php"
    "$PROYECTO/install/_helpers_bd.php"
    "$PROYECTO/install/restore_step1_requisitos.php"
    "$PROYECTO/install/restore_step2_archivo.php"
    "$PROYECTO/install/restore_step3_restaurar.php"
    "$PROYECTO/install/restore_step4_finalizar.php"
    "$PROYECTO/install/schema.sql"
    "$PROYECTO/install/seed.sql"
    "$PROYECTO/install/public.pem"
    "$PROYECTO/config/public.pem"
    "$PROYECTO/core/ApiBootstrap.php"
    "$PROYECTO/core/Licencia.php"
    "$PROYECTO/core/Fingerprint.php"
    "$PROYECTO/core/Middleware.php"
    "$PROYECTO/core/ContratoAvisos.php"
    "$PROYECTO/core/reportes/ticket_venta.php"
    "$PROYECTO/core/reportes/factura.php"
    "$PROYECTO/core/reportes/comprobante.php"
    "$PROYECTO/public/js/licencia.js"
    "$PROYECTO/public/js/notificaciones.js"
    "$PROYECTO/public/js/ayuda.js"
    "$PROYECTO/api/licencia.php"
    "$PROYECTO/api/notificaciones.php"
    "$PROYECTO/api/pos_ticket.php"
    "$PROYECTO/api/almacen.php"
    "$PROYECTO/api/solicitudes.php"
    "$PROYECTO/api/facturas.php"
    "$PROYECTO/api/comprobantes.php"
    "$PROYECTO/api/clientes.php"
    "$PROYECTO/api/proveedores.php"
    "$PROYECTO/api/contratos.php"
)

for archivo in "${ARCHIVOS_CRITICOS[@]}"; do
    if [ -f "$archivo" ]; then
        log_ok "$(basename "$archivo")"
    else
        log_err "$(basename "$archivo") → NO ENCONTRADO"
        TODOS_OK=false
    fi
done

# ── Extensiones PHP ──
echo ""
log_info "Verificando extensiones PHP..."

if php -m 2>/dev/null | grep -q "^openssl$"; then
    log_ok "openssl cargada"
else
    log_err "openssl NO cargada (crítico para licencias)"
    TODOS_OK=false
fi

if php -m 2>/dev/null | grep -q "^pdo_mysql$"; then
    log_ok "pdo_mysql cargada"
else
    log_err "pdo_mysql NO cargada (crítico para BD)"
    TODOS_OK=false
fi

if php -m 2>/dev/null | grep -q "^mbstring$"; then
    log_ok "mbstring cargada"
else
    log_warn "mbstring NO cargada"
fi

if php -m 2>/dev/null | grep -q "^fileinfo$"; then
    log_ok "fileinfo cargada"
else
    log_warn "fileinfo NO cargada (validación de uploads degradada)"
fi

if php -m 2>/dev/null | grep -q "^gd$"; then
    log_ok "gd cargada"
else
    log_warn "gd NO cargada (procesamiento de imágenes limitado)"
fi

if php -m 2>/dev/null | grep -q "^curl$"; then
    log_ok "curl cargada (actualización de divisas disponible)"
else
    log_warn "curl NO cargada (actualización automática de divisas deshabilitada)"
fi

# ── Escritura por usuario web ──
echo ""
log_info "Verificando que el instalador pueda escribir..."
if sudo -u "$WEB_USER" test -w "$PROYECTO/config" 2>/dev/null; then
    log_ok "config/ escribible por $WEB_USER"
else
    log_err "config/ NO escribible por $WEB_USER (el instalador fallará)"
    TODOS_OK=false
fi

if sudo -u "$WEB_USER" test -w "$PROYECTO/install" 2>/dev/null; then
    log_ok "install/ escribible por $WEB_USER"
else
    log_warn "install/ NO escribible por $WEB_USER (no se podrá crear .lock)"
fi

# ── Chequeo informativo del usuario dedicado ──
echo ""
log_info "Chequeo informativo del usuario dedicado '$APP_USER'..."
if command -v mysql &>/dev/null; then
    log_info "  El instalador web creará '$APP_USER'@'localhost' con permisos"
    log_info "  solo sobre la BD '$BD'. Si ya existe un usuario huérfano,"
    log_info "  puedes borrarlo con:"
    log_info "    DROP USER IF EXISTS '$APP_USER'@'localhost';"
    log_info "  (usa --full en este script para hacerlo automáticamente)"
else
    log_warn "  Cliente mysql no disponible en el sistema"
fi

# ============================================================
# 9. RESUMEN
# ============================================================
echo ""
echo "════════════════════════════════════════════════════════"
if [ "$TODOS_OK" = true ]; then
    echo -e "  ${VERDE}✅ REINSTALACIÓN COMPLETADA Y PERMISOS OK${RESET}"
else
    echo -e "  ${AMARILLO}⚠️  REINSTALACIÓN COMPLETADA CON ADVERTENCIAS${RESET}"
fi
echo "════════════════════════════════════════════════════════"
echo ""
echo -e "🌐 Abre el instalador en:  ${AZUL}http://tu-servidor/ipv/${RESET}"
echo "   • Será redirigido al instalador (instalación nueva o restaurar backup)."
echo ""
echo "📋 Resumen de cambios:"
echo "   • config/ limpiada"
echo "   • uploads/ y storage/ recreados con .htaccess, index.php y .gitignore"
echo "   • install/.htaccess creado para proteger .pem, .sql y .lock"
if [ "$MODO_FULL" = true ]; then
    echo "   • BD '$BD' eliminada"
    echo "   • Usuario '$APP_USER' eliminado (si existía)"
else
    echo "   • MySQL NO tocado (usa --full si quieres borrar BD y usuario)"
fi
echo ""
echo "📋 Permisos aplicados:"
echo "   • Carpetas de escritura: 775"
echo "   • Carpetas de lectura:   755"
echo "   • Archivos .php/.css/.js: 644"
echo "   • Archivos en storage/:  664"
echo "   • Propietario: $WEB_USER"
echo ""
echo "📋 Durante la instalación:"
echo "   • Se creará el usuario dedicado '$APP_USER'@'localhost'"
echo "   • La app usará SOLO ese usuario (no root)"
echo "   • Se generará un nuevo install_seed"
echo ""
log_warn "IMPORTANTE: Al reinstalar se genera un nuevo install_seed."
log_warn "La licencia anterior quedará inválida y necesitarás reactivar."
echo ""