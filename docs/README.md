# IPV — Sistema de Inventario de Productos y Ventas

**Versión:** 1.2.0
**Última actualización:** Septiembre 2026

Sistema completo de gestión de inventario, punto de venta (POS), control de turnos y generación de reportes para negocios con múltiples puntos de venta.

Desarrollado en **PHP 8+ con MySQL/MariaDB**, sin dependencias de frameworks pesados. Interfaz moderna, responsive y adaptada a dispositivos móviles.

---

## Tabla de contenidos

- [Novedades de la versión 1.2.0](#novedades-de-la-version-120)
- [Características principales](#caracteristicas-principales)
- [Requisitos del sistema](#requisitos-del-sistema)
- [Instalación rápida](#instalacion-rapida)
- [Uso básico](#uso-basico)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Roles y permisos](#roles-y-permisos)
- [Fases completadas](#fases-completadas)
- [Sistema de licencias](#sistema-de-licencias)
- [Notificaciones](#notificaciones)
- [Módulo de divisas](#modulo-de-divisas)
- [Visor de logs](#visor-de-logs)
- [Manual integrado](#manual-integrado)
- [Documentación completa](#documentacion-completa)
- [Seguridad](#seguridad)
- [Licencia](#licencia)
- [Créditos](#creditos)

---

## Novedades de la versión 1.2.0

Respecto a la 1.0.0, esta versión incorpora **tres fases funcionales completas**:

### Fase 1 — Proveedores y Contratos ✅

- CRUD de **proveedores** (solo Admin escribe; Almacenero y Supervisor leen).
- CRUD de **contratos con proveedores**: número de contrato manual (único), fechas, monto, forma de pago.
- Vinculación N:N entre contratos y productos (`contratos_productos`).
- Estados del contrato: `activo`, `por_vencer`, `por_renovar`, `renovado`, `cancelado`, `no_renovado`.
- Avisos automáticos a **30 / 15 / 7 días** y aviso crítico el día del vencimiento.
- Recordatorio recurrente cada **7 días** para contratos vencidos sin decisión.
- Renovación manual (crea nuevo contrato y archiva el anterior), cancelación, no-renovación y reactivación.
- Integración con **Entradas del almacén**: selector de contrato + Nº de factura de adquisición.
- Aviso automático a Admin/Supervisor si falta contrato o factura al registrar una entrada.
- Botón **"Vincular retroactivo"** para que el Admin asocie movimientos históricos a un contrato.

### Fase 2 — Facturación al por mayor ✅

- CRUD de **clientes mayoristas** (Admin, Supervisor y Vendedor escriben; Almacenero lee).
- El vendedor en el POS marca **"Requiere factura"** + selecciona o crea cliente.
- Supervisor/Admin emiten la factura desde `views/admin/facturas.php`.
- **Folio secuencial** `F-YYYY-NNNN`.
- Relación **1:1 con la venta** (la factura no tiene detalle propio; usa el de la venta).
- Estados: `emitida`, `parcial`, `pagada`, `anulada`.
- Registro de **pagos parciales** con método, referencia y notas.
- **Anulación** permitida solo si la factura no tiene pagos.
- PDF con vista previa en iframe y descarga directa.

### Fase 3 — Comprobantes de venta minorista ✅

- Emisión de **comprobantes** desde el POS (checkbox "Emitir comprobante").
- También emisibles después, desde **Mis ventas** o **Ventas del día**.
- **Folio secuencial** `C-YYYY-NNNNN`.
- Estados: `emitido`, `anulado`.
- Datos del comprador opcionales (si no se dan, sale como "Consumidor final").
- PDF con vista previa en iframe y descarga directa.
- Anulación con motivo (Vendedor solo sus propios comprobantes).

### Otras mejoras

- **Ticket térmico** configurable (58 mm / 80 mm / A4) para impresión de ventas.
- **Reimpresión de ticket** desde Mis ventas, Ventas del día y modal de detalle.
- **Botón "Imprimir ticket"** dentro del modal de éxito del POS.
- Configuración `pos_imprimir_preguntar` para preguntar automáticamente al cobrar.
- **Trazabilidad del almacén**: expediente completo por producto con todos sus movimientos.
- **Backup** ahora permite restaurar y crear backup de seguridad previo automáticamente.
- **Notificaciones** ampliadas con anti-duplicado por tipo + ID de objeto.

---

## Características principales

### Multi-punto de venta
- Soporte para 3 a 5 puntos de venta independientes.
- Inventario separado por punto de venta.
- **Almacén Central** modelado como un PV especial (`es_almacen = 1`).
- Vendedores asignados a su PV.
- Supervisores con visión global de todos los PV.
- **Almacenero** que gestiona el abastecimiento central.

### Sistema de roles
- **Administrador:** acceso total al sistema.
- **Supervisor:** gestión global de inventario, ventas, turnos y facturación.
- **Vendedor:** POS y control de su turno.
- **Almacenero:** almacén central y distribución a puntos de venta.

### Punto de venta (POS) tipo caja
- Interfaz tipo caja registradora con 2 columnas.
- Búsqueda por nombre o código de barras.
- Lector de código de barras compatible.
- Categorías con filtros rápidos.
- Dos vistas configurables: **mosaico** (tarjetas) y **lista** (filas compactas).
- Solo muestra productos con entrada registrada en el PV actual.
- Carrito persistente (no se pierde al recargar).
- Atajos de teclado (F2 búsqueda, F4 cobrar, Esc limpiar).

### Cobro avanzado
- Efectivo con cálculo automático de vuelto.
- Transferencia con referencia, últimos 4 dígitos, titular, banco y comprobante adjunto.
- Pago **mixto** (efectivo + transferencia).
- Venta en **divisas** (USD, EUR) con tasa de cambio automática.
- Sugerencia inteligente de **vuelto** (qué billetes entregar).
- **Requiere factura** (cliente mayorista) con selector de cliente.
- **Emitir comprobante** con datos opcionales del comprador.

### Control de turnos
- Apertura de turno con monto inicial.
- Snapshot del inventario por turno.
- Cierre con cálculo teórico.
- Calculadora de cierre por denominaciones (CUP y USD).
- Detección de descuadres con observaciones obligatorias.

### Inventario completo
- Catálogo de productos con código de barras.
- Historial de cambios de precio auditado.
- Entradas, bajas y ajustes con motivo obligatorio.
- Alertas de stock bajo y negativo.
- Movimientos detallados por producto.

### Almacén central y traslados
- Creación de productos **con entrada inicial** obligatoria (nunca nacen vacíos).
- Registro de entradas al almacén con **contrato + Nº factura**.
- Ajustes de stock con motivo (mermas, daños, correcciones).
- **Solicitudes de traslado** Almacén → PV con flujo completo:
  - Solicitado → Aprobado (parcial o total) → Despachado → Recibido.
  - Rechazo y cancelación con motivo obligatorio.
- **Trazabilidad** del almacén: expediente completo por producto.

### Proveedores y contratos
- Catálogo de proveedores (físicos o jurídicos).
- Contratos con estados, montos, moneda y forma de pago.
- Vinculación N:N con productos.
- Avisos automáticos de vencimiento.
- Renovación con trazabilidad (contrato anterior → nuevo).

### Facturación al por mayor
- Clientes con NIT, tipo de persona, dirección, contacto.
- Factura 1:1 con la venta.
- Folio secuencial `F-YYYY-NNNN`.
- Pagos parciales.
- Estados: emitida, parcial, pagada, anulada.
- Anulación solo sin pagos.
- PDF con vista previa.

### Comprobantes minoristas
- Folio secuencial `C-YYYY-NNNNN`.
- Emisión desde POS o después.
- Anulación con motivo.
- PDF con vista previa.

### Tickets térmicos
- Ancho configurable: **58 mm**, **80 mm** o **A4**.
- Configuración `pos_imprimir_preguntar` para auto-preguntar al cobrar.
- Reimpresión desde múltiples módulos.
- Contenido: negocio, folio, vendedor, cliente, productos, totales, pagos, comprobante si lo hay.

### Reportes profesionales
- **12 reportes en PDF y Excel**:
  1. Ventas por fecha
  2. Ventas por vendedor
  3. Ventas por PV
  4. Top productos
  5. Caja del día
  6. Movimientos de inventario
  7. Turnos
  8. Transferencias
  9. Stock bajo
  10. Auditoría
  11. **Facturas** (por fecha, cliente, vendedor)
  12. **Comprobantes** (por fecha, vendedor, PV)
- Encabezado con logo y datos del negocio.
- Filtros por rango de fechas.

### Sistema de licencias
- Prueba de 30 días desde la instalación.
- Activación con código firmado (RSA-SHA256).
- Vinculado al hardware del servidor (fingerprint).
- Renovación sin reinstalar.
- Detección de manipulación del reloj del sistema.

### Notificaciones
- Campana en el header con contador.
- Dropdown con las últimas notificaciones.
- Más de **20 eventos** notificados (contratos, transferencias, stock, backups, licencia, etc.).
- Polling automático configurable.
- Anti-duplicado por tipo + ID de objeto.

### Módulo de divisas
- Gestión de tasas de cambio.
- Modo automático (El Toque) o manual.
- Historial completo de tasas.
- Uso en el POS para ventas en USD/EUR.

### Visor de logs
- Listado de archivos `.log` del sistema.
- Filtro por nivel (error, warning, info, debug).
- Búsqueda en el contenido.
- Descargar, vaciar y eliminar archivos.

### Manual integrado
- Manual de usuario y técnico accesibles desde el sistema.
- Visor Markdown con índice lateral.
- Buscador interno.
- Descarga en PDF.

### Seguridad y auditoría
- Contraseñas con hash bcrypt.
- Sesiones seguras con tokens CSRF.
- Bloqueo por intentos fallidos de login.
- Auditoría completa de todas las acciones.
- Registro de IP y user-agent en cada operación.
- Protección de carpetas sensibles vía `.htaccess`.

### Personalización
- Logo, favicon, colores corporativos.
- Nombre y datos del negocio.
- Modo claro / oscuro.
- Tipografía configurable.
- Textos de PDFs personalizables.

### Otros
- Diseño responsive (móvil, tablet, escritorio).
- Modo oscuro automático o manual.
- Backup y restauración desde el panel admin.
- Información del sistema para diagnóstico.
- Interfaz completamente en español.

---

## Requisitos del sistema

### Servidor
- PHP 8.0 o superior (recomendado PHP 8.2+)
- MySQL 5.7+ o MariaDB 10.4+
- Apache 2.4+ con `mod_rewrite` habilitado

### Extensiones PHP requeridas
- `pdo` y `pdo_mysql`
- `mbstring`
- `json`
- `openssl` (obligatoria para el sistema de licencias)
- `fileinfo`

### Extensiones PHP opcionales
- `gd` (para procesamiento de imágenes)
- `zip` (para exportaciones)
- `curl` (para actualización automática de tasas desde El Toque)

### Librerías incluidas
- **TCPDF** — generación de PDFs → `public/libs/tcpdf/`
- **SimpleXLSXGen** — generación de Excel → `public/libs/simplexlsxgen/`
- **Parsedown** — renderizado de Markdown → `public/libs/parsedown/`
- **Chart.js** — gráficos → `public/libs/chartjs/`

> ⚠️ **TCPDF es obligatorio.** Sin él, los módulos de ticket, factura, comprobante y reportes PDF fallarán.

### Cliente
- Navegador moderno (Chrome, Firefox, Edge, Safari)
- Resolución mínima: 1024×768 (óptimo: 1366×768 o superior)
- Para uso móvil: iOS 12+ / Android 8+

### Hardware recomendado
- Servidor: 2 GB RAM, 20 GB disco
- Cliente POS: PC con lector de código de barras USB (opcional)
- Impresora térmica de 58 mm u 80 mm (opcional)

---

## Instalación rápida

### Paso 1: Copiar el proyecto

```bash
# En Linux (Apache)
sudo cp -r ipv/ /var/www/html/

# En XAMPP (Windows)
# Copiar a C:\xampp\htdocs\ipv\

# En Laragon (Windows)
# Copiar a C:\laragon\www\ipv\
```

### Paso 2: Verificar la clave pública

Antes de instalar, asegúrate de que exista `install/public.pem`. Es la clave pública para verificar las licencias.

```bash
ls -la /var/www/html/ipv/install/public.pem
```

### Paso 3: Verificar librerías

```bash
ls /var/www/html/ipv/public/libs/tcpdf/tcpdf.php        # debe existir
ls /var/www/html/ipv/public/libs/simplexlsxgen/SimpleXLSXGen.php
ls /var/www/html/ipv/public/libs/parsedown/Parsedown.php
ls /var/www/html/ipv/public/libs/chartjs/chart.min.js
```

### Paso 4: Permisos (Linux/Mac)

```bash
sudo chown -R www-data:www-data /var/www/html/ipv
sudo chmod -R 755 /var/www/html/ipv
sudo chmod -R 775 /var/www/html/ipv/storage
sudo chmod -R 775 /var/www/html/ipv/public/uploads
sudo chmod -R 775 /var/www/html/ipv/config
```

### Paso 5: Abrir el instalador

```
http://tu-servidor/ipv/install/
```

### Paso 6: Seguir el asistente

1. **Paso 1 — Requisitos:** Verifica que todo esté en verde.
2. **Paso 2 — Base de datos:** Ingresa los datos de conexión y elige si cargar datos de ejemplo.
3. **Paso 3 — Administrador:** Crea tu cuenta de administrador.
4. **Paso 4 — Finalizar:** Se generan los archivos de configuración.

Durante la instalación se genera:
- `config/database.php` con las credenciales.
- `config/config.php` con la configuración general.
- Un `INSTALL_SEED` aleatorio en la BD.
- Se copia `install/public.pem` a `config/public.pem`.
- Se crea `install/.lock` para bloquear reinstalaciones.
- Se crea automáticamente un **Almacén Central**.

### Paso 7: Iniciar sesión

```
http://tu-servidor/ipv/
```

### Paso 8: Eliminación automática del instalador

Al entrar por primera vez al login, el sistema elimina automáticamente `install/`. Si no, bórrala manualmente:

```bash
sudo rm -rf /var/www/html/ipv/install/
```

### Reinstalación

`reinstalar.sh` borra la configuración, limpia uploads y storage, aplica permisos y verifica `public.pem`.

```bash
sudo bash reinstalar.sh
```

---

## Uso básico

### Como Administrador

1. Configurar el negocio: **Personalización** → Nombre, logo, colores, moneda, zona horaria.
2. Crear puntos de venta: **Puntos de venta** → Nuevo PV.
3. Crear categorías: **Categorías** → Nueva categoría.
4. Crear **proveedores**: **Proveedores** → Nuevo proveedor.
5. Crear **contratos**: **Contratos** → Nuevo contrato (vincular productos).
6. Asignar precio y costo a productos (se crean desde el almacén).
7. Crear usuarios: **Usuarios** → Nuevo usuario (asignar rol).
8. Crear **clientes mayoristas**: **Clientes** → Nuevo cliente.
9. Configurar el sistema: **Configuración** → POS, Seguridad, Divisas, etc.
10. Gestionar licencia: **Licencia** → Ver estado, activar, renovar.
11. Ver logs: **Logs** → Diagnóstico de errores.

### Como Supervisor

1. Crear **solicitudes de traslado** al almacén.
2. Confirmar la **recepción** de mercancía en su PV.
3. Registrar **bajas** y **ajustes** de inventario.
4. Verificar **transferencias** de pago.
5. Emitir **facturas** de ventas pendientes.
6. Supervisar ventas del día y caja consolidada.
7. Consultar **ranking** de vendedores.
8. Generar reportes.

### Como Almacenero

1. Crear productos con **entrada inicial** al almacén.
2. Registrar **entradas** al almacén (con contrato + factura).
3. Editar datos básicos de productos (sin precio/costo).
4. **Ajustar stock** del almacén con motivo.
5. **Aprobar**, **rechazar** y **despachar** solicitudes de traslado.
6. Consultar la **trazabilidad** completa de cada producto.
7. Ver el stock del almacén central.

### Como Vendedor

1. Abrir turno: **Inicio** → Abrir turno → Indicar monto inicial.
2. Vender: **Punto de venta** → Agregar productos → Cobrar.
3. Marcar "Requiere factura" si es cliente mayorista.
4. Marcar "Emitir comprobante" si el cliente lo pide.
5. Imprimir el ticket desde el modal de éxito (si está configurado).
6. Consultar sus ventas: **Mis ventas** (con opción de reimprimir ticket).
7. Consultar su caja: **Mi caja**.
8. Cerrar turno: **Cerrar turno** → Contar efectivo → Confirmar.

---

## Estructura del proyecto

```
ipv/
├── api/                          Endpoints AJAX (JSON)
│   ├── ajustes.php
│   ├── almacen.php
│   ├── auditoria.php
│   ├── auth.php
│   ├── backup.php
│   ├── bajas.php
│   ├── caja_dia.php
│   ├── categorias.php
│   ├── clientes.php              Fase 2
│   ├── comprobantes.php          Fase 3
│   ├── configuracion.php
│   ├── contratos.php             Fase 1
│   ├── dashboard_admin.php
│   ├── divisas.php
│   ├── entradas.php
│   ├── facturas.php              Fase 2
│   ├── inventario.php
│   ├── licencia.php
│   ├── logs.php
│   ├── manual_pdf.php
│   ├── mi_caja.php
│   ├── mis_ventas.php
│   ├── movimientos.php
│   ├── notificaciones.php
│   ├── perfil.php
│   ├── personalizacion.php
│   ├── pos.php
│   ├── pos_cobro.php
│   ├── pos_comprobante.php
│   ├── pos_denominaciones.php
│   ├── pos_divisas.php
│   ├── pos_ticket.php            Fase 3 (ticket térmico)
│   ├── productos.php
│   ├── proveedores.php           Fase 1
│   ├── puntos_venta.php
│   ├── ranking.php
│   ├── reportes.php
│   ├── reportes_datos.php
│   ├── sistema.php
│   ├── solicitudes.php           Fase 1 (traslados)
│   ├── supervisor_dashboard.php
│   ├── transferencias.php
│   ├── turnos.php
│   ├── turnos_vendedor.php
│   ├── usuarios.php
│   ├── vendedor_dashboard.php
│   └── ventas_supervisor.php
│
├── config/                       Configuración (generada por el instalador)
│   ├── config.php
│   ├── database.php
│   └── public.pem
│
├── core/                         Núcleo del sistema
│   ├── reportes/                 Generadores de reportes
│   │   ├── auditoria.php
│   │   ├── caja_dia.php
│   │   ├── comprobante.php       Fase 3
│   │   ├── comprobantes_*.php    Fase 3
│   │   ├── factura.php           Fase 2
│   │   ├── facturas_*.php        Fase 2
│   │   ├── mi_caja.php
│   │   ├── mis_ventas.php
│   │   ├── movimientos.php
│   │   ├── stock_bajo.php
│   │   ├── ticket_venta.php      Fase 3 (ticket térmico)
│   │   ├── top_productos.php
│   │   ├── transferencias.php
│   │   ├── turnos.php
│   │   ├── ventas_fechas.php
│   │   ├── ventas_pv.php
│   │   ├── ventas_vendedor.php
│   │   └── _plantilla.php
│   ├── ApiBootstrap.php
│   ├── Auth.php
│   ├── Auditoria.php
│   ├── Backup.php
│   ├── Config.php
│   ├── ContratoAvisos.php        Fase 1 (avisos de contratos)
│   ├── CSRF.php
│   ├── Database.php
│   ├── ExcelBase.php
│   ├── Fingerprint.php
│   ├── helpers.php
│   ├── Licencia.php
│   ├── Middleware.php
│   ├── Notificacion.php
│   ├── ReporteBase.php
│   ├── Response.php
│   └── Validador.php
│
├── docs/                         Documentación
│   ├── README.md
│   ├── manual_usuario.md
│   └── manual_tecnico.md
│
├── generador/                    Generador de licencias (NO distribuir)
│   ├── generar.php
│   └── keys/
│       ├── private.pem
│       └── public.pem
│
├── install/                      Instalador web (se elimina tras instalar)
│   ├── index.php
│   ├── step1_requisitos.php
│   ├── step2_bd.php
│   ├── step3_admin.php
│   ├── step4_finalizar.php
│   ├── schema.sql
│   ├── seed.sql
│   ├── migration_001_contratos.sql     Fase 1
│   ├── migration_002_facturacion.sql   Fase 2
│   ├── public.pem
│   ├── install.css
│   └── install.js
│
├── public/                       Archivos públicos
│   ├── css/                      base, components, forms, layout, dark-mode,
│   │                             pos, personalizacion, ayuda
│   ├── js/                       api, app, toast, layout, auth, perfil,
│   │                             pos, pos_cobro_avanzado, almacen_*, etc.
│   ├── libs/
│   │   ├── chartjs/              chart.min.js
│   │   ├── tcpdf/                TCPDF (obligatoria)
│   │   ├── simplexlsxgen/        SimpleXLSXGen.php
│   │   └── parsedown/            Parsedown.php
│   └── uploads/
│       ├── logos/
│       ├── fondos/
│       ├── favicons/
│       ├── comprobantes/
│       └── avatars/
│
├── storage/                      Datos internos (denegado vía .htaccess)
│   ├── backups/
│   ├── reportes/
│   ├── logs/
│   ├── licencia.lic
│   └── last_seen.txt
│
├── views/                        Vistas HTML
│   ├── layouts/                  header.php, sidebar.php, footer.php
│   ├── admin/                    usuarios, PV, productos, categorías,
│   │                             proveedores, contratos, clientes,
│   │                             ventas, turnos, transferencias,
│   │                             facturas, comprobantes, reportes,
│   │                             auditoría, backup, configuración,
│   │                             personalización, divisas, logs, sistema
│   ├── almacen/                  dashboard, inventario, entradas,
│   │                             solicitudes, trazabilidad, categorías
│   ├── inventario/               index, entradas, bajas, ajustes, movimientos
│   ├── supervisor/               dashboard, ventas, turnos, caja,
│   │                             transferencias, ranking, reportes,
│   │                             solicitudes
│   ├── vendedor/                 dashboard, pos, mis_ventas, mi_caja,
│   │                             cerrar_turno, mis_reportes,
│   │                             mis_comprobantes
│   ├── dashboards/               admin, vendedor, supervisor (redirect)
│   ├── login.php
│   ├── 403.php
│   ├── 404.php
│   ├── ayuda.php
│   └── licencia.php
│
├── index.php                     Punto de entrada (redirige por rol)
├── logout.php                    Cerrar sesión
├── reinstalar.sh                 Script de reinstalación
├── .htaccess                     Reglas de seguridad
└── README.md                     Este archivo
```

---

## Roles y permisos

| Acción | Admin | Supervisor | Almacenero | Vendedor |
|--------|:-----:|:----------:|:----------:|:--------:|
| Ver dashboard global | ✅ | ✅ | ❌ | ❌ |
| Ver su dashboard | ✅ | ✅ | ✅ | ✅ |
| Gestionar usuarios | ✅ | ❌ | ❌ | ❌ |
| Gestionar PV | ✅ | ❌ | ❌ | ❌ |
| Gestionar categorías | ✅ | ❌ | ✅ | ❌ |
| Gestionar productos (precio/costo) | ✅ | ❌ | ❌ | ❌ |
| Editar datos básicos de producto | ✅ | ❌ | ✅ | ❌ |
| Crear producto con entrada inicial | ✅ | ❌ | ✅ | ❌ |
| Registrar entradas al almacén | ✅ | ❌ | ✅ | ❌ |
| Ajustar stock del almacén | ✅ | ❌ | ✅ | ❌ |
| Registrar entradas a PV | ✅ | ✅ | ❌ | ❌ |
| Registrar bajas | ✅ | ✅ | ❌ | ❌ |
| Ajustar inventario de PV | ✅ | ✅ | ❌ | ❌ |
| Ver movimientos | ✅ | ✅ | ❌ | ❌ |
| Trazabilidad del almacén | ✅ | ❌ | ✅ | ❌ |
| Gestionar proveedores | ✅ | ❌ | ❌ | ❌ |
| Ver proveedores | ✅ | ✅ | ✅ | ❌ |
| Gestionar contratos | ✅ | ❌ | ❌ | ❌ |
| Ver contratos | ✅ | ✅ | ✅ | ❌ |
| Crear solicitudes de traslado | ✅ | ✅ | ❌ | ❌ |
| Aprobar/Rechazar/Despachar solicitudes | ✅ | ❌ | ✅ | ❌ |
| Confirmar recepción de traslado | ✅ | ✅ | ❌ | ❌ |
| Gestionar clientes mayoristas | ✅ | ✅ | ❌ | ✅ |
| Ver clientes (solo lectura) | ✅ | ✅ | ✅ | ✅ |
| Emitir facturas | ✅ | ✅ | ❌ | ❌ |
| Registrar pagos de facturas | ✅ | ✅ | ❌ | ❌ |
| Emitir comprobantes minoristas | ✅ | ✅ | ❌ | ✅ (solo propios) |
| Anular comprobantes | ✅ | ✅ | ❌ | ✅ (solo propios) |
| Abrir turno | ❌ | ❌ | ❌ | ✅ |
| Vender (POS) | ❌ | ❌ | ❌ | ✅ |
| Ver todas las ventas | ✅ | ✅ | ❌ | ❌ |
| Ver sus ventas | ❌ | ❌ | ❌ | ✅ |
| Verificar transferencias | ✅ | ✅ | ❌ | ❌ |
| Ver caja del día | ✅ | ✅ | ❌ | Solo la propia |
| Ranking vendedores | ✅ | ✅ | ❌ | ❌ |
| Generar reportes | ✅ | ✅ | ❌ | Solo propios |
| Personalización | ✅ | ❌ | ❌ | ❌ |
| Configuración | ✅ | ❌ | ❌ | ❌ |
| Gestión de divisas | ✅ | ❌ | ❌ | ❌ |
| Backup | ✅ | ❌ | ❌ | ❌ |
| Auditoría | ✅ | ❌ | ❌ | ❌ |
| Visor de logs | ✅ | ❌ | ❌ | ❌ |
| Gestión de licencia | ✅ | ❌ | ❌ | ❌ |
| Ver manual de usuario | ✅ | ✅ | ✅ | ✅ |
| Ver manual técnico | ✅ | ❌ | ❌ | ❌ |

---

## Fases completadas

| Fase | Módulos | Estado |
|------|---------|--------|
| **1.0** | Roles, PV, categorías, productos, POS, turnos, inventario, reportes, backup, licencia, personalización | ✅ |
| **1.1** | Divisas, notificaciones, visor de logs, manual integrado, sistema de información | ✅ |
| **1.2 — Fase 1** | Proveedores + Contratos + avisos automáticos + integración con entradas | ✅ |
| **1.2 — Fase 2** | Clientes mayoristas + Facturación al por mayor + pagos parciales | ✅ |
| **1.2 — Fase 3** | Comprobantes minoristas + tickets térmicos (58/80/A4) + reimpresión | ✅ |

### Flujo end-to-end del producto

```
Alta (Almacenero)
  └─ api/almacen.php?accion=crear_producto_con_entrada
      ├─ INSERT productos (precio=0, costo=0)
      ├─ INSERT stock_punto_venta (almacén, cantidad_inicial)
      └─ INSERT movimientos (tipo='entrada', almacén)

Precio (Admin)
  └─ api/productos.php?accion=actualizar
      ├─ UPDATE productos (precio, costo)
      └─ INSERT precios_historial

Entrada al almacén (Almacenero)
  └─ api/almacen.php?accion=entrada
      ├─ UPDATE stock_punto_venta (almacén, +cantidad)
      └─ INSERT movimientos (tipo='entrada', contrato_id, numero_factura)

Solicitud de traslado (Supervisor)
  └─ api/solicitudes.php?accion=crear

Aprobación (Almacenero)
  └─ api/solicitudes.php?accion=aprobar

Despacho (Almacenero)
  └─ api/solicitudes.php?accion=despachar
      ├─ UPDATE stock_punto_venta (almacén, -cantidad)
      └─ INSERT movimientos (tipo='transferencia')

Recepción (Supervisor)
  └─ api/solicitudes.php?accion=recibir
      ├─ UPDATE stock_punto_venta (PV, +cantidad)
      └─ INSERT movimientos (tipo='entrada')

Venta (Vendedor)
  └─ api/pos_cobro.php?accion=registrar
      ├─ INSERT ventas + detalle_ventas + pagos_venta
      └─ UPDATE stock_punto_venta (PV, -cantidad)

Factura (Supervisor/Admin)
  └─ api/facturas.php?accion=emitir

Comprobante (Vendedor/Admin/Supervisor)
  └─ api/comprobantes.php?accion=emitir
```

---

## Sistema de licencias

### Cómo funciona

- Al instalar IPV, comienza una **prueba gratuita de 30 días**.
- Al terminar, el sistema requiere una licencia activa.
- La licencia se vincula al **hardware del servidor** (fingerprint).
- Se firma con **RSA-SHA256** y se verifica con `config/public.pem`.

### Activación

1. Cliente: **Sistema → Licencia**.
2. Copia el **ID de instalación**.
3. Lo envía al vendedor.
4. Vendedor genera el código con `generador/generar.php`.
5. Cliente pega el código y pulsa **Activar licencia**.

### Renovación

1. Cliente: **Sistema → Licencia → Renovar**.
2. Copia ID (no cambia si no cambia el hardware).
3. Recibe nuevo código y lo pega.

### Duración

- Por defecto: **365 días**.
- El vendedor puede generar cualquier duración.

### Cambio de hardware

- El `INSTALL_ID` cambia.
- La licencia anterior deja de ser válida.
- El cliente necesita una nueva licencia.

### Detección de manipulación

- Guarda la última fecha vista en `storage/last_seen.txt`.
- Si el reloj retrocede más de 1 hora, bloquea el acceso.

---

## Notificaciones

### Eventos notificados

| Evento | Destinatarios |
|--------|---------------|
| Transferencia pendiente de verificar | Admins + Supervisores |
| Stock negativo (venta, baja, ajuste) | Admins + Supervisores |
| Stock bajo (baja, ajuste) | Admins + Supervisores |
| Ajuste grande (≥10 unidades) | Admins |
| Stock sigue negativo tras entrada | Admins + Supervisores |
| Venta cancelada | Admins |
| Nuevo usuario creado | Admins |
| Backup generado / restaurado | Admins |
| Turno cerrado con descuadre | Admins + Supervisores |
| Cuenta bloqueada | Admins |
| Licencia por vencer / expirada | Admins |
| **Contrato por vencer (30/15/7 días)** | Admins + Supervisores |
| **Contrato vence hoy** | Admins + Supervisores |
| **Contrato por renovar (recurrente cada 7 días)** | Admins + Supervisores |
| **Entrada sin contrato / sin factura** | Admins + Supervisores |
| **Factura emitida** | Admins |
| **Venta pendiente de facturar** | Admins + Supervisores |
| **Comprobante emitido / anulado** | Admins + Supervisores |
| **Producto pendiente de precio** | Admins |

### Configuración

Desde `Configuración → Notificaciones`:
- `notif_polling_segundos`
- `notif_max_dropdown`
- `notif_email_criticas`
- `notif_intentos_login`

---

## Módulo de divisas

- Venta en **USD / EUR** desde el POS.
- Tasa automática (El Toque) o manual.
- Vigencia configurable de la tasa manual.
- Vuelto en CUP calculado automáticamente.

---

## Visor de logs

`Sistema → Logs` (solo Admin).

Funcionalidades: listado, filtro por nivel, búsqueda, paginación, descarga, vaciar, eliminar, estadísticas.

---

## Manual integrado

- **Manual de Usuario:** todos los roles.
- **Acerca de IPV (README):** todos los roles.
- **Manual Técnico:** solo Administrador.

Funcionalidades: visor Markdown, índice lateral automático, scroll spy, buscador con resaltado, descarga en PDF.

---

## Documentación completa

- [Manual de Usuario](manual_usuario.md)
- [Manual Técnico](manual_tecnico.md)

---

## Seguridad

### Medidas implementadas

- Contraseñas con **hash bcrypt**.
- Protección **CSRF** en todos los formularios.
- **Prepared statements** (PDO) contra SQL Injection.
- **Escape HTML** en todas las salidas (anti-XSS).
- Sesiones seguras con cookies `HttpOnly` y `SameSite=Lax`.
- Bloqueo de cuenta tras intentos fallidos.
- Protección de carpetas sensibles vía `.htaccess`.
- Validación de **MIME real** en subidas.
- **Auditoría** completa.
- Verificación centralizada de **licencia**.
- Detección de manipulación del **reloj**.
- **Cabeceras de seguridad**: `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`.

### Recomendaciones

1. Usar **HTTPS** en producción.
2. Eliminar `install/` tras instalar (automático).
3. Cambiar la contraseña del admin periódicamente.
4. Hacer **backups** regularmente.
5. Restringir permisos del servidor.
6. Actualizar PHP con parches de seguridad.
7. **Custodiar `private.pem`** del generador.
8. No compartir el **generador** con clientes.

---

## Licencia

Este sistema está licenciado bajo los términos especificados por el titular del proyecto.

El uso está sujeto a la licencia activa. Durante la prueba (30 días) el sistema es completamente funcional.

---

## Créditos

### Desarrollo
- **Sistema:** IPV — Inventario y Ventas
- **Versión:** 1.2.0
- **Año:** 2026

### Tecnologías utilizadas
- **Backend:** PHP 8+, MySQL/MariaDB
- **Frontend:** HTML5, CSS3, JavaScript (vanilla)
- **Librerías:**
  - Bootstrap Icons — Iconos
  - Chart.js — Gráficos
  - TCPDF — Generación de PDFs
  - SimpleXLSXGen — Generación de Excels
  - Parsedown — Renderizado de Markdown
  - Inter — Tipografía

### Agradecimientos
- A todos los usuarios que contribuyeron con reportes de bugs.
- A la comunidad de código abierto por las herramientas utilizadas.

---

**¿Preguntas o problemas?** Consulta el [Manual de Usuario](manual_usuario.md) o contacta al administrador del sistema.