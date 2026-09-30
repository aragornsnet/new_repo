# Manual Técnico — Sistema IPV

**Versión:** 1.2.0
**Última actualización:** Septiembre 2026

---

## Índice

1. [Introducción](#1-introduccion)
2. [Stack tecnológico](#2-stack-tecnologico)
3. [Arquitectura del sistema](#3-arquitectura-del-sistema)
4. [Estructura de carpetas](#4-estructura-de-carpetas)
5. [Modelo de datos](#5-modelo-de-datos)
6. [Autenticación y roles](#6-autenticacion-y-roles)
7. [API y endpoints](#7-api-y-endpoints)
8. [Flujos principales](#8-flujos-principales)
9. [Sistema de licencias](#9-sistema-de-licencias)
10. [Sistema de notificaciones](#10-sistema-de-notificaciones)
11. [Módulo de divisas](#11-modulo-de-divisas)
12. [Visor de logs](#12-visor-de-logs)
13. [Manual integrado](#13-manual-integrado)
14. [Generación de PDFs y Excels](#14-generacion-de-pdfs-y-excels)
15. [Tickets térmicos](#15-tickets-termicos)
16. [Seguridad centralizada](#16-seguridad-centralizada)
17. [Instalación y despliegue](#17-instalacion-y-despliegue)
18. [Solución de problemas](#18-solucion-de-problemas)

---

## 1. Introducción

### ¿Qué es este documento?

El Manual Técnico describe la arquitectura interna del Sistema IPV. Está dirigido a:

- Desarrolladores que mantengan o extiendan el sistema.
- Administradores de sistemas que lo desplieguen.
- Soporte técnico que diagnostique problemas.
- Auditores que revisen la seguridad del código.

### ¿Qué NO cubre?

- Uso diario → `manual_usuario.md`.
- Instalación básica desde cero → `README.md`.

### Convenciones

- `código` → fragmentos o nombres de archivos.
- **Negrita** → conceptos importantes.
- *Cursiva* → rutas.

---

## 2. Stack tecnológico

### Backend

| Componente | Tecnología | Versión |
|-----------|-----------|---------|
| Lenguaje | PHP | 8.0+ (recomendado 8.2+) |
| Base de datos | MySQL / MariaDB | 5.7+ / 10.4+ |
| Servidor web | Apache | 2.4+ |
| Conexión BD | PDO | nativo |
| Sesiones | PHP Sessions | nativas |
| Cifrado | OpenSSL | nativo |

### Frontend

| Componente | Tecnología | Versión |
|-----------|-----------|---------|
| HTML | HTML5 | — |
| CSS | CSS3 (vanilla) | — |
| JavaScript | Vanilla JS (ES6+) | — |
| Iconos | Bootstrap Icons | 1.11+ |
| Tipografía | Inter | Google Fonts |

### Librerías PHP

| Librería | Uso | Ubicación | Obligatoria |
|---------|-----|-----------|:-----------:|
| TCPDF | Generación de PDFs | `public/libs/tcpdf/` | ✅ Sí |
| SimpleXLSXGen | Generación de Excels | `public/libs/simplexlsxgen/` | ✅ Sí |
| Parsedown | Renderizado de Markdown | `public/libs/parsedown/` | ✅ Sí |

### Librerías JS

| Librería | Uso | Ubicación |
|---------|-----|-----------|
| Chart.js | Gráficos | `public/libs/chartjs/` |

### Herramientas de desarrollo (opcionales)

- Composer — solo para desarrollo.
- Git — control de versiones.
- VSCode — editor recomendado.
- Xdebug — depuración.

> ⚠️ **TCPDF es obligatorio.** Sin él, los módulos de ticket, factura, comprobante y reportes PDF fallan con fatal error.

---

## 3. Arquitectura del sistema

### Vista general

```
CLIENTE (Navegador)
    HTML + CSS + JS (vanilla) + Fetch API
        |
        | HTTPS/HTTP (JSON)
        v
SERVIDOR WEB (Apache)
    APLICACIÓN PHP
        /api/*.php     (endpoints JSON)
        /views/*.php   (HTML renderizado)
        /core/*.php    (lógica compartida)
            |
            | PDO
            v
    BASE DE DATOS
    MySQL / MariaDB
```

### Patrón de arquitectura

El sistema sigue un **MVC simplificado sin framework**:

- **Modelo:** tablas de MySQL.
- **Vista:** archivos `/views/*.php`.
- **Controlador:** archivos `/api/*.php`.

Frontend y backend están separados: el frontend (JS) se comunica con el backend (PHP) mediante `fetch` a endpoints JSON.

### Flujo típico de una petición

1. Usuario hace clic en un botón (JS).
2. JS llama a `fetch('/api/usuarios.php?accion=crear', {...})`.
3. `ApiBootstrap` valida licencia, sesión, rol y CSRF.
4. PHP valida datos con `Validador`.
5. PHP interactúa con la BD (PDO).
6. PHP devuelve JSON.
7. JS procesa la respuesta y actualiza la UI.

### Flujo de un PDF (tickets, facturas, comprobantes, reportes)

1. JS abre el endpoint en un iframe (`?modo=inline`) o hace `window.location.href` (`?modo=download`).
2. El endpoint valida permisos.
3. El endpoint `require_once` el generador de PDF (`core/reportes/*.php`).
4. El generador usa TCPDF (o `ReporteBase`).
5. Genera el PDF como string (`Output($nombre, 'S')`).
6. Emite `header()` manualmente (`Content-Type: application/pdf`) y `echo` el contenido.
7. `exit`.

**Importante:** **NO** se usa `Output($nombre, 'I')` porque TCPDF puede forzar descarga en algunos navegadores. Se controlan las cabeceras manualmente.

---

## 4. Estructura de carpetas

```
ipv/
├── api/                        Endpoints JSON
├── config/                     Configuración (generada por instalador)
│   ├── config.php
│   ├── database.php
│   └── public.pem
├── core/                       Núcleo
│   ├── reportes/               Generadores de reportes y PDFs
│   ├── ApiBootstrap.php
│   ├── Auth.php
│   ├── Auditoria.php
│   ├── Backup.php
│   ├── Config.php
│   ├── ContratoAvisos.php      Fase 1
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
├── docs/                       Documentación (README, manuales)
├── generador/                  Generador de licencias (NO distribuir)
│   ├── generar.php
│   └── keys/
├── install/                    Instalador web (se elimina tras instalar)
│   ├── schema.sql
│   ├── seed.sql
│   ├── migration_001_contratos.sql
│   ├── migration_002_facturacion.sql
│   └── public.pem
├── public/                     Archivos públicos
│   ├── css/
│   ├── js/
│   ├── libs/
│   │   ├── chartjs/
│   │   ├── tcpdf/              ⚠️ Obligatoria
│   │   ├── simplexlsxgen/
│   │   └── parsedown/
│   └── uploads/
├── storage/                    Datos internos (denegado vía .htaccess)
│   ├── backups/
│   ├── reportes/
│   ├── logs/
│   ├── licencia.lic
│   └── last_seen.txt
├── views/                      Vistas HTML
│   ├── layouts/
│   ├── admin/
│   ├── almacen/
│   ├── inventario/
│   ├── supervisor/
│   ├── vendedor/
│   ├── dashboards/
│   ├── login.php
│   ├── 403.php
│   ├── 404.php
│   ├── ayuda.php
│   └── licencia.php
├── index.php
├── logout.php
├── reinstalar.sh
├── .htaccess
└── README.md
```

---

## 5. Modelo de datos

### Diagrama de relaciones

```
roles (1) -----< usuarios (N)
                   |
puntos_venta (1) --+
    |              |
    +--< stock_punto_venta >-- productos --< categorias
    |           |                  |
    |           |                  +--< precios_historial
    |           |
    |           +--< turnos >-- usuarios
    |           |       |
    |           |       +--< turno_inventario >-- productos
    |           |       |
    |           |       +--< ventas >-- usuarios
    |           |           |
    |           |           +--< detalle_ventas >-- productos
    |           |           +--< pagos_venta >-- usuarios
    |           |           +--< venta_denominaciones >-- denominaciones
    |           |
    |           +--< movimientos >-- productos
    |                  |-- usuarios
    |                  |-- contratos_proveedor
    |
    +--< solicitudes_traslado >-- usuarios
    |       |
    |       +--< solicitudes_detalle >-- productos
    |
    +--< contratos_proveedor >-- proveedores
    |       |
    |       +--< contratos_productos >-- productos
    |
    +--< facturas >-- clientes
    |       |
    |       +--< facturas_pagos >-- usuarios
    |
    +--< comprobantes_venta >-- ventas

divisas --< tasas_cambio
notificaciones -- usuarios
auditoria
configuracion
```

### Tablas principales

#### Base

| Tabla | Propósito |
|-------|-----------|
| `roles` | Roles del sistema (1=Admin, 2=Supervisor, 3=Vendedor, 4=Almacenero) |
| `usuarios` | Cuentas de usuario |
| `puntos_venta` | Sucursales físicas (incluye el almacén con `es_almacen=1`) |
| `categorias` | Categorías de productos |
| `unidades_medida` | Unidades (Unidad, Kg, Litro, etc.) |
| `productos` | Catálogo de productos |
| `precios_historial` | Historial de cambios de precio |
| `stock_punto_venta` | Stock por producto y PV |
| `turnos` | Turnos de trabajo |
| `turno_inventario` | Snapshot del inventario por turno |
| `movimientos` | Movimientos de inventario (entrada, baja, ajuste, transferencia) |

#### Ventas

| Tabla | Propósito |
|-------|-----------|
| `ventas` | Ventas realizadas |
| `detalle_ventas` | Detalle de cada venta |
| `pagos_venta` | Pagos (efectivo/transferencia) |
| `denominaciones` | Billetes y monedas |
| `venta_denominaciones` | Detalle de billetes por venta |
| `metodos_transferencia` | Métodos de transferencia |

#### Divisas

| Tabla | Propósito |
|-------|-----------|
| `divisas` | Divisas habilitadas |
| `tasas_cambio` | Historial de tasas |

#### Sistema

| Tabla | Propósito |
|-------|-----------|
| `notificaciones` | Notificaciones del sistema |
| `auditoria` | Bitácora |
| `configuracion` | Parámetros configurables |

#### Traslados (Almacén → PV)

| Tabla | Propósito |
|-------|-----------|
| `solicitudes_traslado` | Cabecera de solicitud |
| `solicitudes_detalle` | Detalle con 4 cantidades (solicitada/aprobada/despachada/recibida) |

#### Fase 1 — Proveedores y Contratos

| Tabla | Propósito |
|-------|-----------|
| `proveedores` | Catálogo de proveedores |
| `contratos_proveedor` | Cabecera de contrato |
| `contratos_productos` | Relación N:N |

**Columnas añadidas a `movimientos`:** `contrato_id`, `num_contrato`, `numero_factura`.

#### Fase 2 — Facturación

| Tabla | Propósito |
|-------|-----------|
| `clientes` | Clientes mayoristas |
| `facturas` | Facturas (1:1 con ventas) |
| `facturas_pagos` | Pagos parciales |

**Columnas añadidas a `ventas`:** `cliente_id`, `requiere_factura`.

#### Fase 3 — Comprobantes

| Tabla | Propósito |
|-------|-----------|
| `comprobantes_venta` | Comprobantes minoristas (folio `C-YYYY-NNNNN`) |

**Columna añadida a `ventas`:** `requiere_comprobante`.

### Vistas SQL

| Vista | Propósito |
|-------|-----------|
| `v_ventas_dia` | Ventas del día actual |
| `v_caja_dia_vendedor` | Caja consolidada por vendedor |
| `v_top_productos_mes` | Productos más vendidos del mes |
| `v_stock_actual` | Stock actual con alertas |
| `v_ventas_por_vendedor` | Ventas agrupadas por vendedor |
| `v_transferencias_pendientes` | Transferencias sin verificar |
| `v_stock_almacen` | Stock actual del almacén |
| `v_facturas_pendientes` | Facturas con saldo pendiente |

### Ecuación fundamental del inventario

```
Existencia inicial + Entradas − Ventas − Bajas ± Ajustes = Existencia final
```

Continuidad entre turnos:

```
Existencia final del turno N = Existencia inicial del turno N+1
```

### Reglas de integridad

1. **`stock_punto_venta`**
   - Se crea al: crear producto (solo almacén), entrada al almacén, recepción de traslado, venta con `INSERT ... ON DUPLICATE KEY UPDATE`.
   - **NO** se crea al crear un PV.

2. **`movimientos`**
   - Guarda `valor_anterior` y `valor_nuevo`.
   - Tipos: `entrada`, `salida`, `baja`, `ajuste`, `transferencia`.
   - Los ajustes requieren motivo obligatorio.
   - Son **inmutables** (no se editan ni borran).

3. **`solicitudes_detalle`**
   - 4 cantidades que cumplen: `solicitada >= aprobada >= despachada >= recibida`.

4. **Estados de solicitud**
   - `solicitado` → `aprobado` → `despachado`/`despachado_parcial` → `recibido`.
   - `rechazado` → por almacenero con motivo.
   - `cancelado` → por supervisor antes de aprobar.

---

## 6. Autenticación y roles

### Flujo de login

1. Usuario envía email + contraseña (POST `/api/auth.php?accion=login`).
2. Backend valida credenciales contra `usuarios`.
3. Si es correcto: regenera ID de sesión, guarda datos en `$_SESSION`.
4. Si es incorrecto: incrementa `intentos_fallidos`. Si supera `MAX_LOGIN_ATTEMPTS` (5), bloquea por `LOGIN_BLOCK_MINUTES` (30) y notifica a admins.

### Roles y permisos

| Rol | ID | Acceso |
|-----|-----|--------|
| Administrador | 1 | Total |
| Supervisor | 2 | Todos los PV (supervisión global) |
| Vendedor | 3 | Solo su PV |
| Almacenero | 4 | Almacén central y distribución |

### Reglas de asignación de PV

| Rol | PV asignado |
|-----|-------------|
| Administrador | ❌ No tiene PV (ve todos) |
| Supervisor | ❌ No tiene PV (ve todos) |
| Vendedor | ✅ Obligatorio |
| Almacenero | ❌ No tiene PV (gestiona el almacén) |

### Control de acceso

- Backend: `ApiBootstrap::iniciar([...])` verifica rol.
- Frontend: sidebar se genera según el rol.
- Router: `index.php` redirige según el rol.

### Sesiones

- Duración: `SESSION_LIFETIME` (2 horas).
- Cookies `HttpOnly` + `SameSite=Lax`.
- Regeneración de ID al hacer login.
- Expiración por inactividad.

### Bloqueo de cuenta

- Después de `MAX_LOGIN_ATTEMPTS` (5) intentos fallidos.
- Bloqueo por `LOGIN_BLOCK_MINUTES` (30).
- Registrado en auditoría.
- Se notifica a los admins.

---

## 7. API y endpoints

### Convención de rutas

```
/api/{modulo}.php?accion={accion}
```

### Respuestas JSON estándar

Éxito:
```json
{
  "success": true,
  "message": "OK",
  "data": { ... }
}
```

Error:
```json
{
  "success": false,
  "message": "Descripción del error",
  "errors": { "campo": "mensaje" }
}
```

### Listado de endpoints

#### Autenticación

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/api/auth.php?accion=login` | Iniciar sesión |
| POST | `/api/auth.php?accion=logout` | Cerrar sesión |
| GET | `/api/auth.php?accion=check` | Verificar sesión |

#### Almacén (Admin y Almacenero)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/almacen.php?accion=listar` | Stock del almacén |
| GET | `/api/almacen.php?accion=obtener&producto_id=X` | Detalle de un producto |
| GET | `/api/almacen.php?accion=productos` | Catálogo (sin precio/costo) |
| POST | `/api/almacen.php?accion=crear_producto_con_entrada` | Crear producto + entrada inicial |
| POST | `/api/almacen.php?accion=actualizar_producto` | Editar datos básicos |
| POST | `/api/almacen.php?accion=ajustar_stock` | Ajustar stock (mermas) |
| POST | `/api/almacen.php?accion=entrada` | Entrada a producto existente |
| GET | `/api/almacen.php?accion=historial_entradas` | Historial de entradas |
| GET | `/api/almacen.php?accion=trazabilidad_listar` | Productos con agregados |
| GET | `/api/almacen.php?accion=trazabilidad_producto&producto_id=X` | Expediente |
| GET | `/api/almacen.php?accion=contratos_vigentes` | Contratos activos |
| GET | `/api/almacen.php?accion=contratos_para_producto&producto_id=X` | Contratos de un producto |
| POST | `/api/almacen.php?accion=vincular_movimiento_contrato` | Vincular movimiento a contrato (solo Admin) |
| GET | `/api/almacen.php?accion=catalogos` | Categorías, unidades, motivos |

#### Solicitudes de traslado (Admin, Supervisor, Almacenero)

| Método | Endpoint | Roles |
|--------|----------|-------|
| GET | `/api/solicitudes.php?accion=listar` | Todos |
| GET | `/api/solicitudes.php?accion=obtener&id=X` | Todos |
| POST | `/api/solicitudes.php?accion=crear` | Supervisor, Admin |
| POST | `/api/solicitudes.php?accion=aprobar` | Almacenero, Admin |
| POST | `/api/solicitudes.php?accion=rechazar` | Almacenero, Admin |
| POST | `/api/solicitudes.php?accion=despachar` | Almacenero, Admin |
| POST | `/api/solicitudes.php?accion=recibir` | Supervisor, Admin |
| POST | `/api/solicitudes.php?accion=cancelar` | Supervisor, Admin |
| GET | `/api/solicitudes.php?accion=productos_almacen` | Supervisor, Admin |
| GET | `/api/solicitudes.php?accion=catalogos` | Todos |

#### Proveedores y Contratos (Fase 1)

| Método | Endpoint | Roles |
|--------|----------|-------|
| GET | `/api/proveedores.php?accion=listar` | Todos |
| GET | `/api/proveedores.php?accion=obtener&id=X` | Todos |
| POST | `/api/proveedores.php?accion=crear` | Admin |
| POST | `/api/proveedores.php?accion=actualizar` | Admin |
| POST | `/api/proveedores.php?accion=cambiar_estado` | Admin |
| POST | `/api/proveedores.php?accion=eliminar` | Admin |
| GET | `/api/proveedores.php?accion=catalogos` | Todos |
| GET | `/api/contratos.php?accion=listar` | Todos |
| GET | `/api/contratos.php?accion=obtener&id=X` | Todos |
| POST | `/api/contratos.php?accion=crear` | Admin |
| POST | `/api/contratos.php?accion=actualizar` | Admin |
| POST | `/api/contratos.php?accion=cancelar` | Admin |
| POST | `/api/contratos.php?accion=no_renovar` | Admin |
| POST | `/api/contratos.php?accion=renovar` | Admin |
| POST | `/api/contratos.php?accion=reactivar` | Admin |
| GET | `/api/contratos.php?accion=productos_disponibles` | Admin |
| GET | `/api/contratos.php?accion=por_vencer&dias=30` | Todos |
| GET | `/api/contratos.php?accion=alertas_resumen` | Todos |
| GET | `/api/contratos.php?accion=catalogos` | Todos |

#### Clientes (Fase 2)

| Método | Endpoint | Roles |
|--------|----------|-------|
| GET | `/api/clientes.php?accion=listar` | Todos |
| GET | `/api/clientes.php?accion=obtener&id=X` | Todos |
| POST | `/api/clientes.php?accion=crear` | Admin, Supervisor, Vendedor |
| POST | `/api/clientes.php?accion=actualizar` | Admin, Supervisor, Vendedor |
| POST | `/api/clientes.php?accion=cambiar_estado` | Admin, Supervisor, Vendedor |
| POST | `/api/clientes.php?accion=eliminar` | Admin, Supervisor, Vendedor |
| GET | `/api/clientes.php?accion=catalogos` | Todos |

#### Facturas (Fase 2)

| Método | Endpoint | Roles |
|--------|----------|-------|
| GET | `/api/facturas.php?accion=listar` | Todos |
| GET | `/api/facturas.php?accion=obtener&id=X` | Todos |
| POST | `/api/facturas.php?accion=emitir` | Admin, Supervisor |
| POST | `/api/facturas.php?accion=registrar_pago` | Admin, Supervisor |
| POST | `/api/facturas.php?accion=anular` | Admin, Supervisor |
| GET | `/api/facturas.php?accion=pendientes_facturar` | Admin, Supervisor |
| GET | `/api/facturas.php?accion=pdf&id=X&modo=inline\|download` | Todos |
| GET | `/api/facturas.php?accion=catalogos` | Todos |

#### Comprobantes (Fase 3)

| Método | Endpoint | Roles |
|--------|----------|-------|
| GET | `/api/comprobantes.php?accion=listar` | Todos |
| GET | `/api/comprobantes.php?accion=obtener&id=X` | Todos |
| POST | `/api/comprobantes.php?accion=emitir` | Admin, Supervisor, Vendedor |
| POST | `/api/comprobantes.php?accion=anular` | Admin, Supervisor, Vendedor |
| GET | `/api/comprobantes.php?accion=pdf&id=X&modo=inline\|download` | Todos |
| GET | `/api/comprobantes.php?accion=ventas_sin_comprobante` | Todos |
| GET | `/api/comprobantes.php?accion=catalogos` | Todos |

#### Ticket térmico (Fase 3)

| Método | Endpoint | Roles |
|--------|----------|-------|
| GET | `/api/pos_ticket.php?accion=pdf&venta_id=X&modo=inline\|download` | Admin, Supervisor, Vendedor |

**Notas:**
- El tamaño del papel se lee de `configuracion.pos_ticket_tamano` (**58mm**, **80mm**, **A4**).
- El vendedor solo puede ver/imprimir sus propias ventas (validado en el endpoint).

#### Productos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/productos.php?accion=listar` | Listar |
| POST | `/api/productos.php?accion=crear` | ❌ Bloqueado (redirige a Almacén) |
| POST | `/api/productos.php?accion=actualizar` | Actualizar |
| POST | `/api/productos.php?accion=cambiar_estado` | Activar/desactivar |
| POST | `/api/productos.php?accion=eliminar` | Eliminar (si no tiene dependencias) |
| GET | `/api/productos.php?accion=historial_precios` | Historial global |
| GET | `/api/productos.php?accion=obtener&id=X` | Detalle con stock por PV |
| GET | `/api/productos.php?accion=categorias` | Catálogo |
| GET | `/api/productos.php?accion=unidades_medida` | Catálogo |

#### POS (Vendedor)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/pos.php?accion=inicializar` | Cargar turno, categorías, config |
| GET | `/api/pos.php?accion=productos` | Buscar productos |
| GET | `/api/pos.php?accion=buscar_codigo` | Buscar por código |
| POST | `/api/pos_cobro.php?accion=registrar` | Registrar venta |
| POST | `/api/pos_comprobante.php` | Subir comprobante de transferencia |
| GET | `/api/pos_denominaciones.php?moneda=CUP` | Denominaciones activas |
| GET | `/api/pos_divisas.php?accion=listar` | Divisas con tasa |
| GET | `/api/pos_divisas.php?accion=convertir` | Conversión |

#### Turnos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/turnos_vendedor.php?accion=estado` | Estado del turno |
| POST | `/api/turnos_vendedor.php?accion=abrir` | Abrir turno |
| POST | `/api/turnos_vendedor.php?accion=cerrar` | Cerrar turno |
| GET | `/api/turnos_vendedor.php?accion=resumen_cierre` | Resumen |
| GET | `/api/turnos.php?accion=listar` | Listar (Admin/Supervisor) |
| GET | `/api/turnos.php?accion=obtener&id=X` | Detalle |
| GET | `/api/turnos.php?accion=resumen_dia` | Resumen del día |

#### Inventario

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/inventario.php?accion=listar` | Stock |
| GET | `/api/inventario.php?accion=resumen_pv` | Resumen por PV |
| GET | `/api/inventario.php?accion=detalle_producto` | Detalle |
| POST | `/api/entradas.php?accion=registrar` | Entrada |
| POST | `/api/entradas.php?accion=registrar_masivo` | Entrada masiva |
| POST | `/api/bajas.php?accion=registrar` | Baja |
| POST | `/api/bajas.php?accion=registrar_masivo` | Baja masiva |
| POST | `/api/ajustes.php?accion=registrar` | Ajuste |
| GET | `/api/movimientos.php?accion=listar` | Movimientos |
| GET | `/api/movimientos.php?accion=exportar` | Exportar CSV |

#### Notificaciones

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/notificaciones.php?accion=listar` | Listar |
| GET | `/api/notificaciones.php?accion=contar` | Contar no leídas |
| POST | `/api/notificaciones.php?accion=marcar_leida` | Marcar una |
| POST | `/api/notificaciones.php?accion=marcar_todas_leidas` | Marcar todas |
| POST | `/api/notificaciones.php?accion=eliminar` | Eliminar una |
| POST | `/api/notificaciones.php?accion=eliminar_leidas` | Eliminar leídas |

#### Licencia

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/licencia.php?accion=estado` | Estado |
| GET | `/api/licencia.php?accion=install_id` | ID de instalación |
| POST | `/api/licencia.php?accion=activar` | Activar/renovar |
| POST | `/api/licencia.php?accion=eliminar` | Eliminar (para pruebas) |

#### Divisas (Admin)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/divisas.php?accion=listar` | Listar divisas |
| POST | `/api/divisas.php?accion=actualizar_tasa` | Fijar tasa |
| GET | `/api/divisas.php?accion=historial&divisa_id=X` | Historial |
| POST | `/api/divisas.php?accion=cambiar_estado` | Activar/desactivar |
| GET | `/api/divisas.php?accion=catalogos` | Catálogo |

#### Logs (Admin)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/logs.php?accion=listar` | Listar archivos |
| GET | `/api/logs.php?accion=leer&archivo=X` | Leer |
| GET | `/api/logs.php?accion=descargar&archivo=X` | Descargar |
| POST | `/api/logs.php?accion=vaciar` | Vaciar |
| POST | `/api/logs.php?accion=eliminar` | Eliminar |
| GET | `/api/logs.php?accion=estadisticas` | Estadísticas |

#### Manual

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/manual_pdf.php?doc={usuario\|tecnico\|readme}` | PDF del manual |

#### Reportes

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/reportes.php?tipo={tipo}&formato={pdf\|excel}` | Generar reporte |
| GET | `/api/reportes_datos.php?tipo={tipo}` | Datos para vista previa |

**Tipos de reporte disponibles:**
- Ventas: `ventas_fechas`, `ventas_vendedor`, `ventas_pv`, `top_productos`
- Caja: `caja_dia`
- Inventario: `movimientos`, `stock_bajo`
- Turnos: `turnos`
- Transferencias: `transferencias`
- Auditoría: `auditoria`
- Vendedor: `mis_ventas`, `mi_caja`
- **Facturas:** `facturas_fechas`, `facturas_cliente`, `facturas_vendedor`
- **Comprobantes:** `comprobantes_fechas`, `comprobantes_vendedor`, `comprobantes_pv`

#### Dashboard / Sistema

| Método | Endpoint | Roles |
|--------|----------|-------|
| GET | `/api/dashboard_admin.php` | Admin |
| GET | `/api/supervisor_dashboard.php` | Supervisor, Admin |
| GET | `/api/vendedor_dashboard.php` | Vendedor, Supervisor, Admin |
| GET | `/api/caja_dia.php` | Supervisor, Admin |
| GET | `/api/ranking.php?accion=listar` | Supervisor, Admin |
| GET | `/api/ranking.php?accion=top_productos` | Supervisor, Admin |
| GET | `/api/ranking.php?accion=evolucion` | Supervisor, Admin |
| GET | `/api/ranking.php?accion=catalogos` | Supervisor, Admin |
| GET | `/api/ventas_supervisor.php?accion=listar` | Supervisor, Admin |
| GET | `/api/mis_ventas.php?accion=listar` | Vendedor |
| GET | `/api/mi_caja.php` | Vendedor |
| GET | `/api/transferencias.php?accion=listar` | Supervisor, Admin |
| POST | `/api/transferencias.php?accion=verificar` | Supervisor, Admin |
| POST | `/api/transferencias.php?accion=rechazar` | Supervisor, Admin |
| POST | `/api/transferencias.php?accion=verificar_masivo` | Supervisor, Admin |

### Seguridad en endpoints

Todos los endpoints usan `ApiBootstrap::iniciar([roles])`, que:

1. Verifica la licencia (excepto `auth.php` y `licencia.php`).
2. Exige login.
3. Verifica el rol.
4. Valida CSRF en POST/PUT/PATCH/DELETE.

Además, cada endpoint valida datos con `Validador`, usa PDO preparado y devuelve JSON con `Response::ok()` o `Response::error()`.

---

## 8. Flujos principales

### Flujo del producto (end-to-end)

1. **ALTA (Almacenero)**
   ```
   api/almacen.php?accion=crear_producto_con_entrada
   ├── INSERT productos (precio=0, costo=0)
   ├── INSERT stock_punto_venta (almacén, cantidad_inicial)
   ├── INSERT movimientos (tipo='entrada', almacén)
   └── Notifica al admin: producto_sin_precio
   ```

2. **PRECIO (Admin)**
   ```
   api/productos.php?accion=actualizar
   ├── UPDATE productos (precio, costo)
   ├── INSERT precios_historial (si cambió)
   └── Notifica al almacenero
   ```

3. **ENTRADAS AL ALMACÉN (Almacenero)**
   ```
   api/almacen.php?accion=entrada
   ├── UPDATE stock_punto_venta (almacén, +cantidad)
   ├── INSERT movimientos (tipo='entrada', contrato_id, numero_factura)
   └── Notifica a Admin/Supervisor si falta contrato o factura
   ```

4. **AJUSTES DE STOCK (Almacenero)**
   ```
   api/almacen.php?accion=ajustar_stock
   ├── UPDATE stock_punto_venta (almacén, nuevo_stock)
   └── INSERT movimientos (tipo='ajuste', motivo obligatorio)
   ```

5. **SOLICITUD DE TRASLADO (Supervisor)**
   ```
   api/solicitudes.php?accion=crear
   ├── INSERT solicitudes_traslado (estado='solicitado')
   ├── INSERT solicitudes_detalle (cantidad_solicitada)
   └── Notifica al almacenero
   ```

6. **APROBACIÓN (Almacenero)**
   ```
   api/solicitudes.php?accion=aprobar
   ├── UPDATE solicitudes_detalle (cantidad_aprobada)
   ├── UPDATE solicitudes_traslado (estado='aprobado')
   └── Notifica al solicitante
   ```

7. **DESPACHO (Almacenero)**
   ```
   api/solicitudes.php?accion=despachar
   ├── UPDATE stock_punto_venta (almacén, -cantidad_aprobada)
   ├── INSERT movimientos (tipo='transferencia', almacén)
   ├── UPDATE solicitudes_detalle (cantidad_despachada)
   ├── UPDATE solicitudes_traslado (estado='despachado'/'despachado_parcial')
   └── Notifica al solicitante
   ```

8. **RECEPCIÓN (Supervisor)**
   ```
   api/solicitudes.php?accion=recibir
   ├── UPDATE stock_punto_venta (PV, +cantidad_recibida)
   ├── INSERT movimientos (tipo='entrada', PV)
   ├── UPDATE solicitudes_detalle (cantidad_recibida)
   ├── UPDATE solicitudes_traslado (estado='recibido')
   └── Notifica al almacenero
   ```

9. **VENTA (Vendedor)**
   ```
   api/pos_cobro.php?accion=registrar
   ├── UPDATE stock_punto_venta (PV, -cantidad)
   ├── INSERT ventas + detalle_ventas + pagos_venta
   ├── UPDATE turno_inventario (ventas +cantidad)
   ├── INSERT comprobantes_venta (si se pidió)
   └── Notifica si stock negativo / si requiere factura
   ```

10. **FACTURA (Supervisor/Admin)**
    ```
    api/facturas.php?accion=emitir
    ├── INSERT facturas (1:1 con venta)
    └── Notifica al admin
    ```

11. **COMPROBANTE (Vendedor/Admin/Supervisor)**
    ```
    api/comprobantes.php?accion=emitir
    └── INSERT comprobantes_venta (folio C-YYYY-NNNNN)
    ```

### Reglas del flujo

1. **Todo producto nace en el almacén central**, nunca directamente en un PV.
2. **El PV arranca vacío** al crearse.
3. **El stock del PV llega solo por recepción de traslado** confirmada.
4. **El POS solo muestra productos** que hayan tenido al menos una entrada en ese PV.
5. **El almacenero no ve precio ni costo** en ningún endpoint.
6. **El ajuste de stock del almacén** solo permite valores `>= 0`.
7. **Los ajustes por merma** requieren motivo obligatorio.

### Flujo de un turno

**Apertura:**
1. Vendedor clic en "Abrir turno".
2. Ingresa monto inicial.
3. Sistema crea `turnos` (estado `abierto`).
4. Sistema crea snapshot en `turno_inventario`.

**Durante:**
5. Cada venta actualiza `turno_inventario.ventas`.
6. Cada entrada actualiza `turno_inventario.entradas`.
7. Cada baja actualiza `turno_inventario.bajas`.

**Cierre:**
8. Vendedor cuenta efectivo.
9. Ingresa cantidades en calculadora.
10. Sistema calcula diferencia vs teórico.
11. Si hay descuadre, requiere observaciones.
12. UPDATE `turnos` (estado `cerrado`).
13. UPDATE `turno_inventario` con existencia final y descuadre.

### Flujo de un pago por transferencia

1. Vendedor cobra → INSERT en `pagos_venta` con `metodo='transferencia'`, `verificado=0`.
2. Sistema crea notificación para supervisores/admins.
3. Supervisor ve la transferencia pendiente.
4. Supervisor verifica o rechaza.

### Flujo de un contrato con avisos

1. Admin crea contrato → estado `activo`.
2. Al cargar cualquier página, `header.php` llama a `ContratoAvisos::revisar()` para Admin/Supervisor.
3. `ContratoAvisos`:
   - Actualiza contratos vencidos: `activo` → `por_renovar`.
   - Avisa a 30 días (16-30 días restantes).
   - Avisa a 15 días (8-15 días restantes).
   - Avisa a 7 días (1-7 días restantes).
   - Avisa el día del vencimiento.
   - Avisa cada 7 días a contratos `por_renovar`.
4. Anti-duplicado: tipo + ID del contrato + ventana de horas.
5. Admin puede: renovar (crea nuevo, marca el viejo como `renovado`), cancelar, no renovar, reactivar.

### Flujo de facturación

1. Vendedor cobra en POS marcando "Requiere factura" + cliente.
2. Sistema marca `ventas.requiere_factura = 1`.
3. Notifica a Supervisor/Admin.
4. Supervisor emite factura → folio `F-YYYY-NNNN`.
5. Supervisor registra pagos (parciales o totales).
6. Supervisor puede anular (solo si no tiene pagos).

### Flujo de comprobantes

1. Vendedor cobra en POS marcando "Emitir comprobante".
2. Sistema emite automáticamente al confirmar el cobro → folio `C-YYYY-NNNNN`.
3. También se puede emitir después desde "Mis ventas" o "Ventas del día".
4. Se puede anular con motivo (Vendedor solo los propios).

### Flujo de ticket térmico

1. Al confirmar el cobro, si `pos_imprimir_preguntar = 1`, se pregunta al vendedor.
2. También hay botón manual en el modal de éxito.
3. El frontend hace `window.open()` o abre modal con iframe a `api/pos_ticket.php`.
4. El endpoint valida permisos.
5. `ticket_venta.php` genera el PDF con TCPDF según `pos_ticket_tamano`.
6. Salida controlada con `header()` manual + `Output($nombre, 'S')` + `echo`.

### Flujo de auditoría

Cada acción importante llama a:
```php
Auditoria::registrar('accion_nombre', 'tabla', $registroId, [
    'detalle' => [...],
]);
```

Guarda: usuario actual, fecha/hora, IP, user-agent y detalle en JSON.

---

## 9. Sistema de licencias

### Arquitectura

Tres componentes:

- `core/Fingerprint.php` — hash del hardware.
- `core/Licencia.php` — verificación y validación.
- `core/Middleware.php` — verificación global en vistas.

### Componentes del fingerprint

`Fingerprint::obtener()` combina:

- Machine ID (Linux `/etc/machine-id` o Windows `wmic csproduct get uuid`).
- MAC address de la primera interfaz.
- Hostname.
- Sistema operativo.
- Modelo de CPU (Linux).

Devuelve hash SHA-256.

### INSTALL_ID

Se calcula dinámicamente:

```php
INSTALL_ID = hash('sha256', fingerprint + '|' + INSTALL_SEED)
```

`INSTALL_SEED` es un valor aleatorio de 64 caracteres generado durante la instalación y guardado en `configuracion` con la clave `install_seed`.

**Importante:** el `INSTALL_ID` NO se guarda en `config.php`. Se recalcula en cada verificación.

### Claves RSA

- `generador/keys/private.pem` — privada. **Solo el vendedor.**
- `generador/keys/public.pem` — pública. Se distribuye con IPV.
- `install/public.pem` — copia en el instalador.
- `config/public.pem` — copia en la instalación.

### Formato de la licencia

```
BASE64_PAYLOAD.BASE64_FIRMA
```

Payload JSON:
```json
{
  "install_id": "a1b2c3d4...",
  "cliente": "Nombre del cliente",
  "emitida": "2026-09-19 14:30:00",
  "expira": "2027-09-19 14:30:00",
  "dias": 365
}
```

Firma: RSA-SHA256.

### Flujo de verificación

`Licencia::verificar()`:

1. Verifica el reloj (`verificarReloj()`).
2. Lee `storage/licencia.lic` si existe.
3. Verifica firma RSA con `config/public.pem`.
4. Decodifica el payload.
5. Verifica `install_id`.
6. Verifica expiración.
7. Devuelve `tipo=licencia` o `tipo=trial` o `tipo=expirada`.

### Detección de reloj

`Licencia::verificarReloj()`:

1. Lee `storage/last_seen.txt`.
2. Compara con `time()`.
3. Si el reloj retrocedió más de 1 hora → bloquea.
4. Si no, actualiza `last_seen.txt`.

### Generador externo

`generador/generar.php`:

- Lee `private.pem`.
- Construye payload JSON.
- Firma con RSA-SHA256.
- Devuelve `BASE64_PAYLOAD.BASE64_FIRMA`.

Uso CLI:
```bash
php generar.php <install_id> [dias] [cliente]
```

### Cambio de hardware

Si el hardware cambia:
- El fingerprint cambia.
- El INSTALL_ID cambia.
- La licencia anterior deja de ser válida.
- El cliente necesita nueva licencia.

### Prueba de 30 días

`TRIAL_DAYS` está fijo en `core/Licencia.php` como constante privada.

---

## 10. Sistema de notificaciones

### Tabla `notificaciones`

```sql
CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    tipo VARCHAR(50) NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    mensaje VARCHAR(255),
    url VARCHAR(255) NULL,
    icono VARCHAR(50) DEFAULT 'bell',
    color VARCHAR(20) DEFAULT 'primary',
    leida TINYINT(1) DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_lectura DATETIME NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_notif_usuario (usuario_id, leida),
    INDEX idx_notif_fecha (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Helper `Notificacion`

- `crear()` — para un usuario.
- `crearParaRol()` — para todos los de un rol.
- `crearParaAdmins()` — admins.
- `crearParaSupervisores()` — admins + supervisores.
- `crearUnaVez()` — sin duplicados en las últimas X horas.
- `crearParaAdminsUnaVez()` — idem.
- `contarNoLeidas()`.

### Eventos que generan notificaciones

| Evento | Tipo | Destinatarios | Archivo |
|--------|------|---------------|---------|
| Transferencia pendiente | `transferencia_pendiente` | Admins + Supervisores | `api/pos_cobro.php` |
| Stock negativo (venta) | `stock_negativo` | Admins + Supervisores | `api/pos_cobro.php` |
| Stock negativo (baja) | `stock_negativo` | Admins + Supervisores | `api/bajas.php` |
| Stock negativo (baja masiva) | `stock_negativo` | Admins + Supervisores | `api/bajas.php` |
| Stock bajo (baja) | `stock_bajo` | Admins + Supervisores | `api/bajas.php` |
| Stock negativo (ajuste) | `stock_negativo` | Admins + Supervisores | `api/ajustes.php` |
| Stock bajo (ajuste) | `stock_bajo` | Admins + Supervisores | `api/ajustes.php` |
| Ajuste grande (≥10 uds) | `ajuste_grande` | Admins | `api/ajustes.php` |
| Stock sigue negativo tras entrada | `stock_negativo` | Admins + Supervisores | `api/entradas.php` |
| Venta cancelada | `venta_cancelada` | Admins | `api/ventas_supervisor.php` |
| Nuevo usuario | `usuario_creado` | Admins | `api/usuarios.php` |
| Backup generado | `backup_creado` | Admins | `api/backup.php` |
| Backup restaurado | `backup_restaurado` | Admins | `api/backup.php` |
| Turno cerrado con descuadre | `turno_descuadre` | Admins + Supervisores | `api/turnos_vendedor.php` |
| Cuenta bloqueada | `cuenta_bloqueada` | Admins | `api/auth.php` |
| Licencia por vencer (prueba) | `licencia_prueba_por_vencer` | Admins | `core/Middleware.php` |
| Licencia por vencer (anual) | `licencia_por_vencer` | Admins | `core/Middleware.php` |
| Licencia expirada | `licencia_expirada` | Admins | `core/Middleware.php` |
| Reloj inválido | `licencia_reloj_invalido` | Admins | `core/Middleware.php` |
| Licencia activada/renovada | `licencia_activada`/`licencia_renovada` | Admins | `api/licencia.php` |
| **Contrato vence en 30/15/7 días** | `contrato_vence_Nd_ID` | Admins + Supervisores | `core/ContratoAvisos.php` |
| **Contrato vence hoy** | `contrato_vence_hoy_ID` | Admins + Supervisores | `core/ContratoAvisos.php` |
| **Contrato por renovar** | `contrato_por_renovar_ID` | Admins + Supervisores | `core/ContratoAvisos.php` |
| **Entrada sin contrato/factura** | `entrada_sin_documentos` | Admins + Supervisores | `api/almacen.php` |
| **Factura emitida** | `factura_emitida` | Admins | `api/facturas.php` |
| **Venta pendiente de facturar** | `venta_requiere_factura` | Admins + Supervisores | `api/pos_cobro.php` |
| **Producto pendiente de precio** | `producto_sin_precio` | Admins | `api/almacen.php` |
| **Producto con precio actualizado** | `producto_precio_actualizado` | Almaceneros | `api/productos.php` |
| **Solicitud creada** | `solicitud_nueva` | Almaceneros | `api/solicitudes.php` |
| **Solicitud aprobada** | `solicitud_aprobada` | Solicitante | `api/solicitudes.php` |
| **Solicitud rechazada** | `solicitud_rechazada` | Solicitante | `api/solicitudes.php` |
| **Solicitud despachada** | `solicitud_despachada` | Solicitante | `api/solicitudes.php` |
| **Solicitud recibida** | `solicitud_recibida` | Almaceneros | `api/solicitudes.php` |
| **Solicitud cancelada** | `solicitud_cancelada` | Almaceneros | `api/solicitudes.php` |

### Anti-duplicados

`crearUnaVez()` verifica si ya existe una notificación del mismo tipo para el mismo usuario en las últimas X horas.

### Polling del frontend

`public/js/notificaciones.js` hace polling cada `notif_polling_segundos` (60 por defecto).

---

## 11. Módulo de divisas

### Tablas

- `divisas` — divisas habilitadas (USD, EUR).
- `tasas_cambio` — historial de tasas.

### API

`api/divisas.php`:
- `listar` — divisas con última tasa.
- `actualizar_tasa` — fijar tasa manual.
- `historial` — historial de una divisa.
- `cambiar_estado` — activar/desactivar.

`api/pos_divisas.php` (POS):
- `listar` — divisas activas con tasa vigente.
- `convertir` — conversión.

### Obtención automática

`api/pos_divisas.php` incluye `obtenerTasaDesdeAPI()` que consulta la API de El Toque:

- Requiere token (`ELTOQUE_API_TOKEN`).
- Endpoint: `https://api.eltoque.com/trmi/v1/rates`.
- Header `Authorization: Bearer <token>`.
- Respuesta JSON con `tasas: { USD: ..., EUR: ... }`.
- Frecuencia configurable desde `divisas_auto_frecuencia`.
- Se guarda en `tasas_cambio` con `origen='auto'`.

### Tasa manual

Cuando el admin fija una tasa manual:

- Se inserta en `tasas_cambio` con `origen='manual'`, `usuario_id` y `motivo`.
- Dura `divisas_manual_duracion_horas` (por defecto 6).
- Después, vuelve a la tasa automática.
- Registro en auditoría.

### Uso en el POS

- Vendedor elige moneda (CUP, USD, EUR).
- Sistema calcula el total en divisa con la tasa vigente.
- Vendedor ingresa el monto recibido en divisa.
- Vuelto se calcula en CUP.

### Integración con El Toque

1. Solicitar token.
2. Guardar en `config/config.php`:
   ```php
   define('ELTOQUE_API_TOKEN', 'tu_token_aqui');
   ```
3. Activar `divisas_auto_update`.

**Límite:** 1 petición por segundo.

**Recomendación:** usar caché (la tasa en BD).

---

## 12. Visor de logs

### API

`api/logs.php` (solo Admin):

- `listar` — archivos `.log` y `.txt` en `storage/logs/` + el `error_log` de PHP.
- `leer` — lectura paginada con filtros (nivel, búsqueda, orden).
- `descargar` — descarga completa.
- `vaciar` — trunca a 0.
- `eliminar` — borra (solo si está en `storage/logs/`).
- `estadisticas` — nº de archivos, tamaño total, conteo por nivel.

### Configuración de logs

En `config/config.php`:
```php
@ini_set('log_errors', 1);
@ini_set('error_log', __DIR__ . '/../storage/logs/error.log');
```

### Detección de nivel

- `error`: ERROR, FATAL, EXCEPTION, CRITICAL.
- `warning`: WARNING, WARN, DEPRECATED.
- `info`: INFO, NOTICE.
- `debug`: DEBUG.

### Seguridad

- Solo Admin.
- Solo archivos en `storage/logs/` o el `error_log` de PHP.
- Protección contra path traversal con `basename()` y `realpath()`.

---

## 13. Manual integrado

### Componentes

- `views/ayuda.php` — vista principal.
- `public/css/ayuda.css` — estilos.
- `public/js/ayuda.js` — lógica (índice, buscador, scroll spy).
- `api/manual_pdf.php` — genera PDF del manual.
- `public/libs/parsedown/Parsedown.php` — renderiza Markdown.

### Documentos

En `docs/`:
- `README.md` — Acerca de IPV.
- `manual_usuario.md` — Manual de usuario.
- `manual_tecnico.md` — Manual técnico (solo Admin).

### Whitelist

```php
$documentos = [
    'usuario' => [
        'archivo' => 'manual_usuario.md',
        'titulo'  => 'Manual de Usuario',
        'roles'   => ['Administrador', 'Supervisor', 'Vendedor', 'Almacenero'],
    ],
    'tecnico' => [
        'archivo' => 'manual_tecnico.md',
        'titulo'  => 'Manual Técnico',
        'roles'   => ['Administrador'],
    ],
    'readme' => [
        'archivo' => 'README.md',
        'titulo'  => 'Acerca de IPV',
        'roles'   => ['Administrador', 'Supervisor', 'Vendedor', 'Almacenero'],
    ],
];
```

### PDF

`api/manual_pdf.php`:
1. Verifica sesión, rol y whitelist.
2. Lee el `.md`.
3. Convierte a HTML con Parsedown.
4. Ajusta estilos para TCPDF.
5. Genera el PDF con `TCPDF::writeHTML()`.
6. Devuelve el PDF inline.

---

## 14. Generación de PDFs y Excels

### Base de reportes PDF

`core/ReporteBase.php` extiende TCPDF:

- Encabezado con logo, nombre, eslogan, contacto.
- Pie con usuario, fecha y paginación.
- Métodos `generarTabla()`, `bloqueTotales()`, `bloqueResumen()`.

### Base de reportes Excel

`core/ExcelBase.php` usa SimpleXLSXGen:

- Encabezado con título, subtítulo, negocio y fecha.
- Fila de encabezados.
- Filas de datos.
- Bloque de totales.

### PDFs específicos

- `core/reportes/factura.php` → `generar_factura_pdf($id, $modo)`.
- `core/reportes/comprobante.php` → `generar_comprobante_pdf($id, $modo)`.
- `core/reportes/ticket_venta.php` → `generar_ticket_venta($id, $modo)`.

### Patrón obligatorio de salida PDF

**NO** usar `$pdf->Output($nombre, 'I')` porque TCPDF puede forzar descarga en algunos navegadores.

**Sí** usar:

```php
while (ob_get_level()) {
    ob_end_clean();
}

$contenido = $pdf->Output($nombreArchivo, 'S');

if ($modo === 'inline') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
    header('Content-Length: ' . strlen($contenido));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
} else {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Content-Length: ' . strlen($contenido));
}

echo $contenido;
exit;
```

### Nota sobre IDM y similares

Los gestores de descargas (IDM, etc.) interceptan PDFs con `Content-Type: application/pdf` y fuerzan descarga. Esto es un comportamiento del cliente, no del sistema. Documentado para el usuario final.

---

## 15. Tickets térmicos

### Generador

`core/reportes/ticket_venta.php` → `generar_ticket_venta($ventaId, $modo)`.

### Tamaños soportados

Leídos de `configuracion.pos_ticket_tamano`:

| Valor | Ancho | Margen |
|-------|-------|--------|
| `58mm` | 58 mm | 3 mm |
| `80mm` | 80 mm | 4 mm |
| `A4` | 210 mm | 12 mm |

### Estructura del PDF

- **Encabezado:** nombre, eslogan, dirección, teléfono.
- **Info:** folio, fecha, vendedor, cliente (si aplica), comprobante (si aplica).
- **Detalle:** producto, cantidad, precio, subtotal.
- **Totales:** subtotal y TOTAL.
- **Divisa:** si aplica, muestra moneda, total en divisa y tasa.
- **Pagos:** método y monto.
- **Estado cancelada:** aviso en rojo.
- **Pie:** agradecimiento.

### Fuentes

- 58mm → 7-10pt.
- 80mm → 8-11pt.

### Ajuste de columnas

En 58mm las columnas son más estrechas que en 80mm. El generador adapta los anchos.

### Endpoint

`api/pos_ticket.php?accion=pdf&venta_id=X&modo=inline|download`:

1. Valida `venta_id`.
2. Valida permisos: vendedor solo sus propias ventas.
3. Lee `pos_ticket_tamano`.
4. `require_once` el generador.
5. Llama a `generar_ticket_venta($ventaId, $modo)`.

### Integración con el POS

- En el modal de éxito (`#modal-exito`), botón **"Imprimir ticket"**.
- El botón llama a `POS.imprimirDesdeExito()`.
- `POS.imprimirTicket(ventaId)` abre un modal con iframe apuntando al endpoint.
- Si `pos_imprimir_preguntar = 1`, se auto-abre al cobrar.

### Reimpresión

- Desde **Mis ventas** y **Ventas del día**, botón 🖨️ en la tabla.
- En el modal de detalle, botón "Reimprimir ticket".

---

## 16. Seguridad centralizada

### ApiBootstrap

`core/ApiBootstrap.php` centraliza la seguridad.

#### Métodos

- `iniciar($rolesPermitidos, $requiereCsrf, $verificarLicencia)` — protegidas.
- `iniciarPublica($requiereCsrf)` — login.

#### Flujo de `iniciar()`

1. Inicia sesión.
2. Verifica la licencia.
3. Exige login.
4. Verifica el rol.
5. Valida CSRF en POST/PUT/PATCH/DELETE.

### Middleware

`core/Middleware.php` verifica la licencia en cada request de las vistas.

#### Flujo

1. Rutas exentas: `/licencia`, `/login`, `/api/licencia`, `/api/auth`, `/logout`.
2. Si la licencia no es válida → redirige a `views/licencia.php`.
3. Si está en prueba con ≤ 5 días → guarda aviso en sesión.
4. Crea notificaciones de licencia (con anti-duplicados).

### Defensa en profundidad

- **Capa 1:** `Auth::iniciarSesion()` + `Auth::requireLogin()`.
- **Capa 2:** `ApiBootstrap::iniciar()` (licencia + rol + CSRF).
- **Capa 3:** `Validador` en cada endpoint.
- **Capa 4:** PDO preparado en todas las consultas.
- **Capa 5:** Escape HTML en todas las salidas.
- **Capa 6:** Auditoría de todas las acciones.
- **Capa 7:** Cabeceras de seguridad HTTP (`.htaccess`).

### `.htaccess` raíz

```apache
# Bloquear acceso a carpetas sensibles
RedirectMatch 403 ^/ipv/storage/.*$
RedirectMatch 403 ^/ipv/config/.*$
RedirectMatch 403 ^/ipv/core/.*$

# Bloquear archivos sensibles
<FilesMatch "\.(sql|log|md|json|lock|ini)$">
    Require all denied
</FilesMatch>

# Bloquear archivos ocultos
<FilesMatch "^\.">
    Require all denied
</FilesMatch>

# Deshabilitar listado de directorios
Options -Indexes

# Cabeceras de seguridad
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>
```

### `.htaccess` en `storage/`

Deniega todo acceso directo y bloquea `.sql`, `.log`, `.lic`, `.txt`, `.pdf`, `.xlsx`, `.csv`.

### PDO sin emulación

`Database.php` configura:
```php
PDO::ATTR_EMULATE_PREPARES => false
```

**Consecuencia importante:** los placeholders con nombre no se pueden repetir en la misma consulta. Usar `:param` y `:param2` si se repiten.

---

## 17. Instalación y despliegue

### Requisitos

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.4+
- Apache 2.4+
- Extensiones: `pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo`

### Instalación paso a paso

**Paso 1:** Copiar el proyecto.
```bash
sudo cp -r ipv/ /var/www/html/
```

**Paso 2:** Verificar `install/public.pem` y las librerías.
```bash
ls -la /var/www/html/ipv/install/public.pem
ls /var/www/html/ipv/public/libs/tcpdf/tcpdf.php
ls /var/www/html/ipv/public/libs/simplexlsxgen/SimpleXLSXGen.php
ls /var/www/html/ipv/public/libs/parsedown/Parsedown.php
ls /var/www/html/ipv/public/libs/chartjs/chart.min.js
```

**Paso 3:** Permisos.
```bash
sudo chown -R www-data:www-data /var/www/html/ipv
sudo find /var/www/html/ipv -type d -exec chmod 755 {} \;
sudo find /var/www/html/ipv -type f -exec chmod 644 {} \;
sudo chmod -R 775 /var/www/html/ipv/storage
sudo chmod -R 775 /var/www/html/ipv/public/uploads
sudo chmod -R 775 /var/www/html/ipv/config
```

**Paso 4:** Configurar Apache.
```apache
<Directory /var/www/html/ipv>
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

**Paso 5:** Abrir el instalador.
```
http://tu-servidor/ipv/install/
```

**Paso 6:** Seguir los 4 pasos.

**Paso 7:** Iniciar sesión. La carpeta `install/` se elimina automáticamente.

### Instalación de TCPDF

Si no viene incluida:

```bash
cd /var/www/html/ipv/public/libs/
wget https://github.com/tecnickcom/TCPDF/archive/refs/tags/6.6.5.tar.gz
tar -xzf 6.6.5.tar.gz
mv TCPDF-6.6.5 tcpdf
rm 6.6.5.tar.gz
ls tcpdf/tcpdf.php   # debe existir
```

### Configuración de PHP recomendada

En `/etc/php/8.x/apache2/php.ini`:
```ini
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 300
memory_limit = 256M
```

### Configuración de MySQL/MariaDB

```sql
ALTER DATABASE ipv_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Backup manual

```bash
# Backup
mysqldump -u root -p ipv_db > backup.sql

# Restaurar
mysql -u root -p ipv_db < backup.sql
```

### Reinstalación

`reinstalar.sh`:
- Borra `config/database.php`, `config/config.php`, `config/public.pem`.
- Borra `install/.lock`.
- Elimina la base de datos.
- Limpia `public/uploads/` y `storage/`.
- Recrea carpetas y `.htaccess`.
- Verifica `public.pem` y usuario web.
- Aplica permisos.

```bash
sudo bash reinstalar.sh
```

### Seguridad en despliegue

⚠️ **Antes de distribuir a clientes:**

1. **Rotar claves RSA del generador** si se filtraron:
   ```bash
   cd generador/keys
   rm -f private.pem public.pem
   openssl genrsa -out private.pem 4096
   openssl rsa -in private.pem -pubout -out public.pem
   cp public.pem ../../install/public.pem
   ```
2. **Cambiar la contraseña de MySQL.**
3. **No compartir el generador con clientes.**
4. **Usar HTTPS en producción.**

---

## 18. Solución de problemas

### El login no funciona

1. Contraseña mal hasheada → regenerar.
2. Sesión no se guarda → revisar `session.save_path`.
3. Usuario inactivo → verificar en BD.
4. Bloqueado por intentos → esperar 30 min.

### Error 500 al generar reportes

**Causa:** error de PHP en algún archivo de reportes.

**Diagnóstico:**
```bash
tail -50 /var/www/html/ipv/storage/logs/error.log | grep -i reportes
```

**Solución:**
- Verificar que TCPDF y SimpleXLSXGen estén en `public/libs/`.
- Verificar sintaxis de `core/reportes/`.

### Fatal error: TCPDF no encontrado

```
require_once(.../public/libs/tcpdf/tcpdf.php): 
Failed to open stream: No such file or directory
```

**Causa:** falta la librería.

**Solución:** instalar TCPDF (ver sección 17).

### Acentos mal guardados (Ã© en vez de é)

**Causa:** charset.

**Solución:**
1. Verificar `Database.php` con `SET NAMES 'utf8mb4'`.
2. Verificar BD con `utf8mb4`.
3. Corregir datos:
   ```sql
   UPDATE productos 
   SET nombre = CONVERT(BINARY CONVERT(nombre USING latin1) USING utf8mb4)
   WHERE nombre LIKE '%Ã%';
   ```

### La sesión expira muy rápido

**Causa:** `SESSION_LIFETIME` bajo o cookies mal.

**Solución:**
1. Ajustar en `config/config.php`:
   ```php
   define('SESSION_LIFETIME', 7200);
   ```
2. Verificar cookies en `Auth.php`.

### El carrito se pierde al recargar

**Causa:** `localStorage` deshabilitado.

**Solución:**
- Verificar navegador.
- Limpiar caché.
- Revisar consola (F12).

### Error "Class SimpleXLSXGen not found"

**Causa:** librería no cargada.

**Solución:**
1. Verificar `public/libs/simplexlsxgen/SimpleXLSXGen.php`.
2. Verificar `namespace Shuchkin;`.
3. Ejecutar:
   ```bash
   php -r "require 'public/libs/simplexlsxgen/SimpleXLSXGen.php'; var_dump(class_exists('Shuchkin\SimpleXLSXGen'));"
   ```

### PDF no descarga el logo

**Causa:** ruta del logo mal.

**Solución:**
1. Verificar que el logo esté en `public/uploads/logos/`.
2. Verificar ruta relativa en BD.
3. Verificar que `ReporteBase.php` construya bien la ruta absoluta.

### La licencia no se activa

**Causas posibles:**
1. Firma inválida → `config/public.pem` no coincide.
2. `install_id` no coincide → hardware cambió.
3. Licencia expiró → verificar fecha.
4. Permisos en `storage/`.

**Diagnóstico:**
```bash
openssl rsa -pubin -in /var/www/html/ipv/config/public.pem -noout -text
ls -la /var/www/html/ipv/storage/
```

### El sistema pide licencia aunque está activa

**Causas posibles:**
1. `last_seen.txt` no escribible → `chmod 775`.
2. Reloj retrocedió → `date`.
3. Fingerprint cambió.

**Diagnóstico:**
```bash
ls -la /var/www/html/ipv/storage/last_seen.txt
cat /var/www/html/ipv/storage/last_seen.txt
date
```

### Las notificaciones no aparecen

1. Tabla `notificaciones` no existe.
2. Polling no activo.
3. Badge no actualiza.

**Diagnóstico:**
```bash
mysql -u root -p -e "SHOW TABLES LIKE 'notificaciones'; ipv_db"
```

### No se puede acceder al visor de logs

1. `storage/logs/` no existe.
2. Sin archivos `.log`.
3. `error_log` apunta a otra ruta.

### Error SQLSTATE[HY093] Invalid parameter number

**Causa:** placeholders repetidos con `PDO::ATTR_EMULATE_PREPARES = false`.

**Solución:** usar `:param1`, `:param2` cuando se repitan.

### Error "Column not found"

**Causa:** esquema desactualizado.

**Ejemplo típico:**
```
Unknown column 'p.unidad_medida' in 'SELECT'
```

**Solución:**
```sql
ALTER TABLE productos 
ADD COLUMN unidad_medida VARCHAR(20) NOT NULL DEFAULT 'Unidad' AFTER stock_minimo;
```

### Otros errores comunes

| Error | Solución |
|-------|----------|
| Token CSRF inválido | Recargar la página |
| No autenticado | Volver a iniciar sesión |
| Acceso denegado | Verificar rol |
| Cannot modify header information | Verificar salida antes de `header()` |
| Call to undefined method | Verificar que el archivo esté actualizado |
| Ticket en blanco | Verificar TCPDF instalado |
| IDM intercepta descargas | Documentado, usar botón "Abrir en nueva pestaña" |

---

## Recursos adicionales

- Manual de Usuario: `manual_usuario.md`
- README: `README.md`
- Logs de Apache: `/var/log/apache2/error.log`
- Logs internos: `storage/logs/error.log`
- Logs de MySQL: `/var/log/mysql/error.log`
- Documentación de El Toque: `https://bit.ly/3UztEM1`
- TCPDF: `https://github.com/tecnickcom/TCPDF`