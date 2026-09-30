# Manual de Usuario — Sistema IPV

**Versión:** 1.2.0
**Última actualización:** Septiembre 2026

---

## Índice general

- [Parte 1 — Introducción](#parte-1-introduccion)
- [Parte 2 — Administrador](#parte-2-administrador)
- [Parte 3 — Supervisor](#parte-3-supervisor)
- [Parte 4 — Almacenero](#parte-4-almacenero)
- [Parte 5 — Vendedor](#parte-5-vendedor)
- [Parte 6 — Casos de uso](#parte-6-casos-de-uso)
- [Parte 7 — Preguntas frecuentes](#parte-7-preguntas-frecuentes)
- [Parte 8 — Glosario](#parte-8-glosario)

---

# Parte 1 — Introducción

## 1.1 ¿Qué es el sistema IPV?

**IPV** significa **Inventario de Productos y Ventas**. Es un sistema web que te permite:

- Llevar el control de inventario de tus productos.
- Registrar ventas desde el punto de venta (POS).
- Controlar turnos de cada vendedor.
- Gestionar el **almacén central** y la distribución a los PV.
- Administrar **proveedores** y **contratos** de compra.
- Emitir **facturas** al por mayor y **comprobantes** minoristas.
- Imprimir **tickets térmicos** (58 mm / 80 mm).
- Generar reportes de ventas, inventario y caja.
- Administrar usuarios con diferentes permisos.
- Auditar todas las acciones del sistema.
- Gestionar la licencia del sistema.
- Recibir notificaciones de eventos importantes.

### ¿Para quién está diseñado?

- Negocios con múltiples puntos de venta (3 a 5 sucursales).
- Tiendas que necesitan control de inventario y almacén central.
- Franquicias con varios vendedores.
- Comercios que venden en efectivo, transferencia y divisas.
- Negocios que facturan al por mayor y al detalle.

### ¿Qué NO hace?

- No factura electrónicamente ante organismos fiscales (solo genera documentos internos).
- No se conecta con bancos directamente.
- No controla nómina.

---

## 1.2 Requisitos

### Para usar el sistema necesitas:

- Un navegador moderno: Chrome, Firefox, Edge o Safari actualizado.
- Conexión a la red local donde esté instalado el sistema.
- Tu usuario y contraseña (te los dará el administrador).

### Opcional (según tu rol):

- **Lector de código de barras USB** (para vendedores).
- **Impresora térmica** de 58 mm u 80 mm (para tickets).
- Tablet o móvil con navegador actualizado.

---

## 1.3 Cómo iniciar sesión

### Paso 1: Abre el navegador

Escribe la dirección del sistema. Por ejemplo:

```
http://192.168.1.100/ipv/
```

O el dominio que te haya indicado el administrador.

### Paso 2: Introduce tus credenciales

Verás una pantalla con dos campos:

- **Correo:** tu email de trabajo.
- **Contraseña:** la que te asignó el administrador.

Puedes pulsar el ícono del ojo para mostrar la contraseña.

### Paso 3: Serás redirigido según tu rol

- Admin → Dashboard del administrador.
- Supervisor → Dashboard del supervisor.
- Almacenero → Dashboard del almacén central.
- Vendedor → Dashboard del vendedor (o al POS si tienes turno abierto).

### Errores comunes

| Mensaje | Causa | Solución |
|---------|-------|----------|
| "Credenciales incorrectas" | Email o contraseña mal | Verifica mayúsculas y espacios |
| "La cuenta está desactivada" | Tu usuario fue desactivado | Contacta al administrador |
| "Cuenta bloqueada. Intenta en X min" | Demasiados intentos fallidos | Espera X minutos |
| "Token CSRF inválido" | La sesión expiró | Recarga la página e intenta de nuevo |

---

## 1.4 Roles y permisos

El sistema tiene **4 roles**:

### Administrador
- Acceso total al sistema.
- Gestiona usuarios, productos, precios, personalización.
- Ve todos los puntos de venta.
- Genera cualquier reporte.
- Accede a auditoría, logs, backup y licencia.
- Asigna precio y costo a los productos.
- Gestiona **proveedores** y **contratos** (escritura).
- Emite **facturas** al por mayor.
- Activa/desactiva productos.

### Supervisor
- Crea **solicitudes de traslado** al almacén.
- Confirma la **recepción** de mercancía en su PV.
- Registra **bajas** y **ajustes** de inventario.
- Verifica **transferencias de pago**.
- Emite **facturas** de ventas pendientes.
- Ve ventas de todos los vendedores.
- Ve caja del día consolidada.
- Genera reportes de ventas y caja.
- Ve **ranking** de vendedores.
- NO puede crear usuarios ni modificar productos.

### Almacenero
- **Crea productos con entrada inicial** al almacén.
- Registra **entradas** al almacén central.
- **Ajusta stock** del almacén (mermas, daños, correcciones).
- Edita datos básicos del producto (nombre, código, categoría, unidad, stock mínimo).
- **Aprueba**, **rechaza** y **despacha** solicitudes de traslado.
- Ve el stock del almacén central.
- Consulta la **trazabilidad** completa de cada producto.
- Gestiona **categorías**.
- NO ve precio ni costo.
- NO puede activar/desactivar productos.

### Vendedor
- Abre y cierra su turno.
- Vende desde el POS.
- Marca "Requiere factura" si el cliente es mayorista.
- Emite **comprobantes** minoristas.
- Ve sus propias ventas (con opción de reimprimir ticket).
- Ve su propia caja.
- NO puede ver datos de otros vendedores.

---

## 1.5 Cómo cerrar sesión

### Paso 1: Clic en tu nombre (arriba a la derecha)

En el header verás tu nombre. Al pulsarlo se abre un menú.

### Paso 2: Clic en "Cerrar sesión"

Se cierra la sesión y vuelves a la pantalla de login.

### Recomendaciones

- Siempre cierra sesión al terminar tu jornada.
- No compartas tu usuario y contraseña.
- Si trabajas en una computadora compartida, cierra sesión al levantarte.

---

## 1.6 Interfaz general

### Elementos comunes

- **Sidebar (menú lateral):** cambia según tu rol.
- **Header (barra superior):** campana (notificaciones), luna/sol (tema), tu nombre (menú de usuario).

### Sidebar

- Clic en el ícono de hamburguesa para colapsar/expandir.
- Cada ítem te lleva a un módulo diferente.
- El ítem activo se resalta en azul.

### Header

- **Campana:** muestra el número de notificaciones no leídas.
- **Luna/Sol:** cambia entre modo claro y oscuro.
- **Tu nombre:** abre el menú de usuario.

---

## 1.7 Notificaciones

### Cómo verlas

1. Pulsa el ícono de campana en el header.
2. Se abre un dropdown con las últimas notificaciones.
3. Las no leídas aparecen resaltadas.
4. Pulsa una notificación para ir al evento relacionado (se marca como leída).

### Qué te notifica

- Transferencias pendientes de verificar (supervisor/admin).
- Productos con stock negativo o bajo (supervisor/admin).
- Ajustes de inventario grandes (admin).
- Ventas canceladas (admin).
- Nuevos usuarios creados (admin).
- Backups generados o restaurados (admin).
- Turnos cerrados con descuadre (supervisor/admin).
- Cuenta bloqueada por intentos fallidos (admin).
- Licencia por vencer o expirada (admin).
- **Contratos por vencer (30/15/7 días)** (admin/supervisor).
- **Contratos vencidos sin decisión** (admin/supervisor).
- **Entradas al almacén sin contrato o sin factura** (admin/supervisor).
- **Facturas emitidas** (admin).
- **Ventas pendientes de facturar** (admin/supervisor).
- **Comprobantes emitidos/anulados** (admin/supervisor).
- **Productos pendientes de precio** (admin).

---

## 1.8 Mi perfil

Desde el menú de usuario (arriba a la derecha) → **Mi perfil**.

### Qué puedes hacer

- Cambiar tu nombre.
- Subir o eliminar tu foto de perfil.
- Cambiar tu contraseña.

---

## 1.9 Manual de uso

Desde el menú de usuario o el sidebar → **Ayuda → Manual**.

### Documentos disponibles

- **Manual de Usuario:** todos los roles.
- **Acerca de IPV (README):** todos los roles.
- **Manual Técnico:** solo Administrador.

### Funcionalidades

- Buscador dentro del manual.
- Índice lateral generado automáticamente.
- Descarga en PDF.
- Navegación entre documentos.

---

# Parte 2 — Administrador

## 2.1 Dashboard

El dashboard muestra un resumen completo de la operación.

### ¿Qué muestra?

**Fila 1 — Métricas del día:**
- Ventas del día (monto total).
- Efectivo hoy.
- Transferencias hoy.
- Ventas del mes (con comparación vs mes anterior).

**Fila 2 — Alertas:**
- Productos con stock bajo.
- Productos con stock negativo.
- Transferencias pendientes de verificar.
- Turnos abiertos actualmente.

**Fila 3 — Gráficos:**
- Ventas de los últimos 7 días (gráfico de línea).
- Top 5 productos del mes.

**Fila 4 — Listas:**
- Ranking de vendedores (hoy).
- Ventas por punto de venta (mes).

---

## 2.2 Gestión de usuarios

### ¿Qué puedes hacer?

- Crear usuarios (Administrador, Supervisor, Vendedor, Almacenero).
- Editar datos de usuarios.
- Resetear contraseñas.
- Activar/desactivar usuarios.
- Asignar vendedores a un punto de venta.

### Cómo crear un usuario

1. Sidebar → **Usuarios**.
2. Clic en **"Nuevo usuario"**.
3. Rellena:
   - Nombre completo.
   - Correo.
   - Rol (Admin / Supervisor / Vendedor / Almacenero).
   - Punto de venta (solo si es Vendedor).
   - Usuario activo (checkbox).
   - Contraseña (con requisitos de seguridad).
   - Confirmar contraseña.
4. Clic en **Guardar**.

### Reglas importantes

- **Vendedores** deben tener un PV asignado.
- **Admin, Supervisor y Almacenero** NO tienen PV.
- El correo debe ser único.
- Debe existir siempre al menos un administrador activo.

---

## 2.3 Puntos de venta

### ¿Qué puedes hacer?

- Crear nuevos puntos de venta.
- Editar nombre, dirección, teléfono.
- Activar/desactivar PV.

### Reglas

- No se puede eliminar un PV con ventas, vendedores, turnos o movimientos.
- Se puede desactivar (no aparecerá en el POS).
- El **Almacén Central** es un PV especial (`es_almacen = 1`) que se crea durante la instalación.

---

## 2.4 Categorías

### ¿Qué puedes hacer?

- Crear categorías (Bebidas, Snacks, etc.).
- Editar nombre y descripción.
- Activar/desactivar.

### Cómo crear una categoría

1. Sidebar → **Categorías**.
2. Clic en **"Nueva categoría"**.
3. Rellena: nombre y descripción.
4. Clic en **Guardar**.

---

## 2.5 Productos

### ¿Qué puedes hacer?

- Ver todos los productos.
- Editar **precio, costo, descripción, stock mínimo, categoría, unidad**.
- Ver **historial de cambios de precio**.
- **Activar/desactivar** productos.
- Ver **stock por PV**.
- Ver **movimientos recientes**.

### Importante

⚠️ **Los productos se crean desde el módulo de Almacén**, no desde aquí. Esto garantiza que todos nazcan con una **entrada inicial al almacén**.

### Cómo editar un producto

1. Sidebar → **Productos**.
2. Clic en el ícono de lápiz del producto.
3. Modifica lo que necesites.
4. Si cambias el precio, el sistema te pedirá un **motivo** (opcional pero recomendado).
5. Clic en **Guardar**.

### Historial de precios

Cada cambio de precio queda registrado con fecha, usuario y motivo. Visible en el detalle del producto.

---

## 2.6 Proveedores

### ¿Qué puedes hacer?

- Crear, editar, activar/desactivar proveedores.
- Ver contratos asociados a cada proveedor.

### Cómo crear un proveedor

1. Sidebar → **Proveedores**.
2. Clic en **"Nuevo proveedor"**.
3. Rellena:
   - Nombre / Razón social.
   - NIT / Documento.
   - Tipo de persona (Física / Jurídica).
   - Dirección.
   - Teléfono.
   - Email.
   - Persona de contacto.
   - Notas.
   - Activo (checkbox).
4. Clic en **Guardar**.

---

## 2.7 Contratos de proveedor

### ¿Qué puedes hacer?

- Crear contratos con número manual (único).
- Vincular productos al contrato.
- Renovar, cancelar o marcar como "no renovado".
- Reactivar contratos archivados.

### Estados del contrato

- `activo` → vigente.
- `por_vencer` → vence en ≤30 días.
- `por_renovar` → vencido, decisión pendiente.
- `renovado` → ya se renovó (existe contrato nuevo).
- `cancelado` → cancelado antes de vencer.
- `no_renovado` → vencido y se decidió no renovar.

### Cómo crear un contrato

1. Sidebar → **Contratos**.
2. Clic en **"Nuevo contrato"**.
3. Rellena:
   - Nº de contrato (único, cualquier carácter).
   - Proveedor.
   - Fecha inicio y caducidad.
   - Monto y moneda.
   - Forma de pago (contado / crédito / mixto).
   - Plazo en días (si es crédito).
   - Descripción y observaciones.
4. Busca y agrega **productos** al contrato.
5. Clic en **Guardar**.

### Avisos automáticos

- A **30 días** de vencer.
- A **15 días** de vencer.
- A **7 días** de vencer.
- **El día del vencimiento** (aviso crítico).
- **Cada 7 días** si ya venció y no se ha decidido.

### Renovación

1. Abre el contrato activo o por vencer.
2. Clic en **"Renovar"**.
3. Ingresa nuevo número, fecha inicio y caducidad.
4. El sistema crea un **nuevo contrato** copiando datos y productos.
5. El contrato anterior se marca como `renovado`.

---

## 2.8 Clientes mayoristas

### ¿Qué puedes hacer?

- Crear, editar, activar/desactivar clientes.
- Ver el historial de facturas y saldo pendiente de cada cliente.

### Cómo crear un cliente

1. Sidebar → **Clientes**.
2. Clic en **"Nuevo cliente"**.
3. Rellena:
   - Nombre / Razón social.
   - NIT / Documento.
   - Tipo de persona.
   - Dirección, teléfono, email.
   - Persona de contacto.
   - Notas.
   - Activo.
4. Clic en **Guardar**.

---

## 2.9 Ventas globales

### ¿Qué puedes ver?

Todas las ventas del sistema (de todos los PV, todos los vendedores).

### Filtros

- Buscar por folio o vendedor.
- Filtrar por PV, vendedor, método de pago y estado.
- Rango de fechas.

### Acciones

- Ver detalle completo de cada venta.
- **Cancelar** una venta (con motivo obligatorio).
- **Exportar CSV**.
- **Emitir comprobante** de ventas sin comprobante.

---

## 2.10 Facturas al por mayor

### ¿Qué puedes hacer?

- Ver todas las facturas.
- Emitir nuevas facturas desde ventas pendientes.
- Registrar pagos parciales o totales.
- Anular facturas (solo si no tienen pagos).
- Descargar PDF.

### Cómo emitir una factura

1. Sidebar → **Facturas**.
2. Clic en **"Emitir nueva factura"**.
3. Se abre un modal con las **ventas pendientes de facturar**.
4. Clic en **"Emitir"** en la venta que quieras.
5. Revisa los datos y ajusta:
   - Fecha de vencimiento.
   - Descuento (opcional).
   - Impuesto (opcional).
   - Observaciones.
6. Clic en **"Emitir factura"**.
7. Se genera un folio `F-YYYY-NNNN` y se abre la vista previa del PDF.

### Registrar un pago

1. Abre el detalle de la factura.
2. Clic en **"Registrar pago"**.
3. Ingresa monto (máximo: saldo pendiente), método, referencia y notas.
4. Clic en **"Registrar pago"**.

### Anular una factura

Solo si **no tiene pagos registrados**.

1. Abre el detalle.
2. Clic en **"Anular"**.
3. Ingresa motivo (mínimo 5 caracteres).
4. Clic en **"Anular factura"**.

---

## 2.11 Comprobantes minoristas

### ¿Qué puedes hacer?

- Ver todos los comprobantes emitidos.
- Anular comprobantes con motivo.
- Descargar PDF.

### Estados

- `emitido` → válido.
- `anulado` → sin efecto.

---

## 2.12 Turnos globales

### ¿Qué puedes ver?

Todos los turnos abiertos y cerrados, con filtros por PV, vendedor, estado y fechas.

### Detalle del turno

- Vendedor y PV.
- Apertura y cierre.
- Duración.
- Monto inicial.
- Total vendido.
- Efectivo y transferencias.
- Ventas del turno.
- Inventario del turno (si se cerró con conteo).
- Descuadres.

---

## 2.13 Transferencias

### ¿Qué puedes hacer?

- Verificar o rechazar transferencias de pago de todos los PV.
- Verificación masiva.

### Cómo verificar

1. Sidebar → **Transferencias**.
2. Clic en el ojo de la transferencia.
3. Revisa: referencia, últimos 4 dígitos, titular, comprobante adjunto.
4. Clic en **"Verificar"** o **"Rechazar"** (con motivo).

---

## 2.14 Auditoría

### ¿Qué es?

Registro completo de todas las acciones en el sistema.

### Cómo consultarla

1. Sidebar → **Auditoría**.
2. Verás una tabla con: fecha, usuario, acción, tabla afectada, IP.
3. Filtra por usuario, acción, tabla o rango de fechas.

### Exportar

Clic en **"Exportar CSV"**.

---

## 2.15 Backup

### Cómo hacer un backup

1. Sidebar → **Backup**.
2. Clic en **"Generar y descargar backup"**.
3. Se descarga un archivo `backup_YYYYMMDD_HHMMSS.sql`.

### Cómo restaurar

⚠️ Restaurar reemplaza TODOS los datos actuales.

1. Clic en **"Restaurar desde archivo"**.
2. Selecciona el archivo `.sql`.
3. Escribe **`RESTAURAR`** en mayúsculas.
4. Clic en **"Restaurar backup"**.
5. El sistema crea un **backup de seguridad automático** antes de restaurar.

### Recomendaciones

- Haz backup diario al cerrar.
- Guárdalos **fuera del servidor** (USB, nube).
- Prueba la restauración periódicamente.

---

## 2.16 Visor de logs

Sidebar → **Logs**.

### Funcionalidades

- Lista de archivos `.log`.
- Filtro por nivel (error, warning, info, debug).
- Búsqueda por texto.
- Paginación.
- Descargar, vaciar, eliminar.
- Estadísticas.

---

## 2.17 Licencia

Sidebar → **Licencia**.

### Ver estado

Muestra si estás en prueba (30 días) o con licencia activa, con días restantes.

### Activar

1. Copia tu **ID de instalación**.
2. Envíalo al vendedor.
3. Pega el código de licencia.
4. Clic en **"Activar licencia"**.

### Renovar

1. Clic en **"Renovar licencia"**.
2. Copia tu ID (sigue siendo el mismo si no cambió el hardware).
3. Pega el nuevo código.

---

## 2.18 Personalización

Sidebar → **Personalización**.

### Pestañas

- **Identidad:** nombre, eslogan, logo, favicon.
- **Contacto:** dirección, teléfono, email, web.
- **Colores:** primario, secundario, acento, éxito, peligro.
- **Moneda y formato:** código, símbolo, posición, decimales, formato de fecha, zona horaria.
- **Tema:** modo (claro/oscuro/auto), tipografía.
- **Login:** mensaje, imagen de fondo.
- **Documentos:** encabezado, pie, texto legal.

---

## 2.19 Configuración

Sidebar → **Configuración**.

### Pestañas

- **Seguridad:** política de contraseñas.
- **POS:** permitir stock negativo, mostrar sin stock, tamaño de ticket, preguntar antes de imprimir.
- **Turnos:** conteo físico, bloqueo por negativos, forzar cierre.
- **Inventario:** supervisor ajusta stock, ajustes inmediatos.
- **Divisas:** habilitadas, auto-update, frecuencia, fuente, manual.
- **Transferencias:** comprobante adjunto, tamaño máximo, verificación, retención.
- **Notificaciones:** email en críticas, polling, intentos de login, máximo dropdown.

### Configuración de ticket

- `pos_ticket_tamano` → **58 mm**, **80 mm** o **A4**.
- `pos_imprimir_preguntar` → 1 para preguntar al cobrar.

---

## 2.20 Divisas

Sidebar → **Divisas**.

- Ver divisas activas y su última tasa.
- **Fijar tasa manual** con motivo y duración.
- Ver historial.
- Activar/desactivar divisa.

---

## 2.21 Sistema

Sidebar → **Sistema**.

Información técnica: versión de PHP, MySQL, extensiones cargadas, permisos de carpetas, espacio en disco, tamaño de la BD, errores recientes.

---

## 2.22 Reportes

Sidebar → **Reportes**.

### Reportes disponibles

1. Ventas por fecha.
2. Ventas por vendedor.
3. Ventas por PV.
4. Top productos.
5. Caja del día.
6. Movimientos de inventario.
7. Turnos.
8. Transferencias.
9. Stock bajo.
10. Auditoría.
11. **Facturas por fecha**.
12. **Facturas por cliente**.
13. **Facturas por vendedor**.
14. **Comprobantes por fecha**.
15. **Comprobantes por vendedor**.
16. **Comprobantes por PV**.

### Cómo generar

1. Clic en el tipo.
2. Configura filtros.
3. **Vista previa** (HTML en modal), **PDF** o **Excel**.

---

# Parte 3 — Supervisor

## 3.1 Dashboard

El dashboard del supervisor muestra el estado global de todos los PV.

### ¿Qué muestra?

- Métricas del día (ventas, efectivo, transferencias, turnos abiertos).
- Gráfico de ventas de los últimos 7 días.
- Ranking de vendedores (top 5).
- Turnos abiertos con detalle.
- Transferencias pendientes de verificar.
- Ventas por punto de venta.

---

## 3.2 Solicitudes de traslado

### ¿Qué son?

Pedidos que haces al almacén central para abastecer tu PV.

### Flujo

1. **Tú creas** la solicitud → estado `solicitado`.
2. **Almacenero aprueba** (total o parcial) → estado `aprobado`.
3. **Almacenero despacha** → estado `despachado` o `despachado_parcial`.
4. **Tú confirmas recepción** → estado `recibido`.

### Cómo crear una solicitud

1. Sidebar → **Solicitudes**.
2. Clic en **"Nueva solicitud"**.
3. Selecciona PV destino, motivo.
4. Busca productos del almacén y agrégalos con cantidad.
5. Clic en **"Crear solicitud"**.

### Cómo confirmar recepción

1. Abre la solicitud en estado `despachado` o `despachado_parcial`.
2. Ajusta las cantidades recibidas (si difieren).
3. Clic en **"Confirmar recepción"**.
4. Se suma el stock a tu PV.

### Cómo cancelar

Solo si está en estado `solicitado`.

1. Abre la solicitud.
2. Clic en **"Cancelar"**.
3. Ingresa motivo.

---

## 3.3 Bajas de inventario

### ¿Qué son?

Dar de baja productos (vencidos, dañados, robados, etc.).

### Cómo registrar una baja

1. Sidebar → **Bajas**.
2. Clic en **"Nueva baja"**.
3. Rellena: PV, producto, cantidad, motivo, descripción.
4. Clic en **"Registrar baja"**.

### Carga masiva

1. Clic en **"Carga masiva"**.
2. Selecciona PV, motivo y descripción general.
3. Rellena cantidades por producto.
4. Clic en **"Aplicar bajas"**.

---

## 3.4 Ajustes de inventario

### ¿Qué es?

Corregir el stock a un **valor exacto** (no suma ni resta).

### Cómo hacer un ajuste

1. Sidebar → **Ajustes**.
2. Clic en **"Nuevo ajuste"**.
3. Rellena: PV, producto, **nuevo stock**, motivo, descripción.
4. Si la diferencia es grande (≥10), el sistema pide confirmación.
5. Clic en **"Aplicar ajuste"**.

---

## 3.5 Movimientos

Registro de todas las entradas, bajas, ajustes y transferencias.

### Filtros

- Tipo, PV, usuario, rango de fechas.
- Búsqueda por producto, motivo, descripción.

### Exportar

Clic en **"Exportar CSV"**.

---

## 3.6 Ventas del día

### ¿Qué muestra?

Todas las ventas registradas hoy por todos los vendedores.

### Filtros

- PV, vendedor, método de pago, estado.
- Búsqueda por folio o vendedor.
- Rango de fechas.

### Ver detalle

Clic en el ojo para ver: productos, pagos, conteo de efectivo.

### Cancelar venta

1. Abre el detalle.
2. Clic en **"Cancelar venta"**.
3. Ingresa motivo.
4. El stock se devuelve automáticamente.

### Reimprimir ticket

En la tabla y en el detalle, botón 🖨️ para reimprimir el ticket.

### Emitir comprobante

Clic en **"Emitir comprobante"** en el header del módulo.

---

## 3.7 Turnos

### ¿Qué muestra?

Turnos abiertos y cerrados de todos los vendedores.

### Filtros

- PV, vendedor, estado, rango de fechas.

### Detalle del turno

- Info general.
- Resumen de pagos.
- Cierre (si aplica).
- Ventas del turno.
- Inventario del turno (si se cerró con conteo).

---

## 3.8 Caja del día

### ¿Qué muestra?

Consolidado de caja del día, separado por vendedor y por PV.

### Atajos

- Hoy, Ayer, Últimos 7 días, Este mes.

### Exportar

Clic en **"Exportar"**.

---

## 3.9 Transferencias

Verificar pagos por transferencia de todos los PV.

### Estados

- `pendiente`, `verificada`, `rechazada`.

### Verificación individual

1. Clic en el ojo.
2. Revisa datos y comprobante.
3. Clic en **"Verificar"** o **"Rechazar"**.

### Verificación masiva

1. Clic en **"Verificación masiva"**.
2. Selecciona varias.
3. Clic en **"Verificar seleccionadas"**.

---

## 3.10 Ranking

### ¿Qué es?

Comparativa de vendedores por ventas en un período.

### Atajos

- Hoy, Esta semana, Este mes, Mes anterior, Este año.

### Qué muestra

- Podium con los 3 mejores.
- Gráfico de barras (efectivo + transferencias).
- Tabla detallada por vendedor.
- Top productos por vendedor (clic en 📦).

### Exportar

Clic en **"Exportar CSV"**.

---

## 3.11 Facturas

Igual que Admin: ver todas, emitir desde pendientes, registrar pagos, anular, descargar PDF.

---

## 3.12 Comprobantes

Ver todos, anular, descargar PDF.

---

## 3.13 Reportes

Igual que Admin, pero solo los reportes de ventas, caja, movimientos, turnos, transferencias, stock bajo, facturas y comprobantes.

---

# Parte 4 — Almacenero

## 4.1 Dashboard

Estado del almacén central.

### ¿Qué muestra?

- Productos activos.
- Unidades totales en stock.
- Productos con stock bajo.
- Productos con stock negativo.
- Solicitudes pendientes (solicitado + aprobado).
- Entradas registradas hoy.
- Últimas 5 entradas.
- Últimas 5 solicitudes.

---

## 4.2 Entradas al almacén

### ¿Qué son?

Registrar la llegada de mercancía al almacén central. Es el **único punto de alta de productos**.

### Cómo registrar una entrada

1. Sidebar → **Entradas**.
2. Clic en **"Nueva entrada"**.
3. Busca el producto:
   - **Si existe** → selecciónalo.
   - **Si no existe** → botón **"Crear producto con este texto"**.

### Crear producto nuevo

1. Clic en **"Crear producto con [nombre]"**.
2. Rellena:
   - Código de barras (opcional).
   - Categoría (obligatoria).
   - Unidad de medida (obligatoria).
   - Stock mínimo.
   - Descripción (opcional).
   - **Cantidad inicial (≥1).**
   - Motivo de la entrada.
   - **Contrato de proveedor** (opcional).
   - **Nº factura de adquisición** (opcional).
3. Clic en **"Crear producto y registrar entrada"**.

**Importante:** al crear un producto se le asigna **precio = 0** y **costo = 0**. El admin los asigna después. Recibirá notificación automática.

### Registrar entrada a producto existente

1. Selecciona el producto.
2. Rellena cantidad, motivo, referencia, descripción.
3. **Contrato** (opcional, solo si el producto está vinculado).
4. **Nº factura** (opcional).
5. Clic en **"Registrar entrada"**.

**Aviso:** si no seleccionas contrato o no pones Nº factura, el sistema notificará al Admin/Supervisor.

---

## 4.3 Inventario del almacén

### ¿Qué muestra?

Stock actual del almacén central.

### Acciones por producto

- **Ver detalle** (ojo).
- **Editar producto** (lápiz): nombre, código, categoría, unidad, stock mínimo, descripción.
- **Ajustar stock** (llave inglesa): corrige el stock a un valor exacto.
- **Registrar entrada** (más): redirige a la pantalla de entradas con el producto preseleccionado.

### Ajustar stock

1. Clic en la llave inglesa.
2. Ingresa el **nuevo stock** (0 o mayor).
3. Selecciona **motivo**.
4. Clic en **"Aplicar ajuste"**.

**Ejemplo:** si tienes 20 unidades y se dañan 2, el nuevo stock es 18. El sistema resta 2 y registra el movimiento.

---

## 4.4 Solicitudes de traslado (aprobar y despachar)

### ¿Qué son?

Solicitudes que los supervisores hacen para pedir mercancía del almacén a su PV.

### Flujo

1. Supervisor crea solicitud → `solicitado`.
2. Tú apruebas (total o parcial) → `aprobado`.
3. Tú despachas → `despachado` / `despachado_parcial`.
4. Supervisor confirma recepción → `recibido`.

### Aprobar

1. Sidebar → **Solicitudes**.
2. Filtra por estado `solicitado`.
3. Clic en el ojo.
4. Ajusta la **cantidad a aprobar** por producto (puede ser menor si no hay stock).
5. Clic en **"Aprobar solicitud"**.
6. El modal se reabre con el botón **"Despachar mercancía"**.

### Despachar

1. Con el modal abierto, clic en **"Despachar mercancía"**.
2. Confirma.
3. El stock del almacén baja y se registra un movimiento de `transferencia`.

### Rechazar

1. Abre la solicitud.
2. Clic en **"Rechazar"**.
3. Ingresa motivo (mínimo 5 caracteres).
4. El supervisor recibe notificación.

---

## 4.5 Trazabilidad del almacén

### ¿Qué es?

Expediente completo de cada producto: todas sus entradas, ajustes y despachos.

### Cómo consultarlo

1. Sidebar → **Trazabilidad**.
2. Filtra por producto, categoría o "solo con movimientos".
3. Clic en el ojo de un producto.

### Qué muestra

- Info general del producto.
- **Stock actual en almacén.**
- Resumen: total entradas, total salidas, ajustes netos.
- Primer y último movimiento.
- Timeline completo de movimientos con filtros (fecha, tipo).

### Exportar

Clic en **"Exportar CSV"** dentro del expediente.

---

## 4.6 Categorías

### ¿Qué puedes hacer?

- Crear, editar, activar/desactivar categorías.

---

# Parte 5 — Vendedor

## 5.1 Dashboard

### Sin turno abierto

Banner grande con botón **"Abrir turno ahora"**.

Si hay alertas de stock, se muestran abajo.

### Con turno abierto

- Banner verde con: turno, PV, tiempo, monto inicial, botones **Ir al POS** y **Cerrar turno**.
- Stats del turno: ventas, efectivo, transferencias, productos disponibles.
- Últimas 5 ventas del turno.
- Top 3 productos del turno.

---

## 5.2 Abrir turno

1. En el dashboard, clic en **"Abrir turno ahora"**.
2. Ingresa el **monto inicial** (efectivo contado al empezar).
3. Observaciones (opcional).
4. Clic en **"Abrir turno"**.

El sistema guarda una **foto del inventario actual**.

### Reglas

- Un vendedor solo puede tener 1 turno abierto.
- Un PV solo puede tener 1 turno abierto a la vez.

---

## 5.3 POS — Punto de Venta

### Interfaz

2 columnas: productos a la izquierda, carrito a la derecha.

### Vistas del catálogo

- **Mosaico:** tarjetas con nombre, código, precio y stock.
- **Lista:** filas horizontales compactas.

Cambia entre vistas con los botones del header. Se recuerda.

### Cómo vender

1. Busca el producto (nombre o código).
2. Clic en la tarjeta → se agrega al carrito.
3. Ajusta cantidad si es necesario.
4. Clic en **"COBRAR"**.

### Atajos de teclado

- **F2** → enfocar buscador.
- **F4** → cobrar.
- **Esc** → limpiar búsqueda.
- **Enter** en el buscador con 6+ dígitos → buscar por código.

### Nota sobre productos visibles

Solo se muestran productos que hayan tenido al menos **una entrada** en tu PV.

### Carrito persistente

Si recargas la página, el carrito NO se pierde.

---

## 5.4 Cobro

### Modal de cobro

Muestra: total, selector de método, selector de moneda (si hay divisas), campo de monto recibido, cálculo de vuelto.

### Cobro en efectivo

1. Método: Efectivo.
2. Ingresa el monto recibido.
3. El sistema calcula el vuelto.
4. Clic en **"Confirmar cobro"**.

### Cobro por transferencia

1. Método: Transferencia.
2. Selecciona el método (Transfermóvil, EnZona, etc.).
3. Ingresa referencia, últimos 4 dígitos, titular.
4. Sube el comprobante (si es obligatorio).
5. Clic en **"Confirmar cobro"**.

La transferencia queda **pendiente** hasta que el supervisor la verifique.

### Pago mixto

1. Método: Mixto.
2. Ingresa monto en efectivo y monto por transferencia.
3. Ingresa referencia.
4. Clic en **"Confirmar cobro"**.

### Cobro en divisa

1. Selecciona moneda (CUP, USD, EUR).
2. Se muestra el total en divisa.
3. Ingresa el monto recibido en divisa.
4. El vuelto se entrega en CUP.
5. Clic en **"Confirmar cobro"**.

### Requiere factura

Marca el checkbox **"Requiere factura"** y selecciona/crea cliente mayorista.

### Emitir comprobante

Marca el checkbox **"Emitir comprobante"** y opcionalmente añade datos del comprador.

---

## 5.5 Imprimir ticket

Al confirmar el cobro, si `pos_imprimir_preguntar = 1`, el sistema pregunta si quieres imprimir el ticket.

También hay botón **"Imprimir ticket"** en el modal de éxito.

El ticket respeta el tamaño configurado (58 mm / 80 mm / A4).

---

## 5.6 Mis ventas

### ¿Qué muestra?

Solo tus ventas del turno actual.

### Filtros

- Folio.
- Método de pago.
- Estado.

### Ver detalle

Clic en el ojo para ver productos, pagos, vuelto, comprobante.

### Reimprimir ticket

Clic en 🖨️ en la tabla o en el modal de detalle.

### Exportar

Clic en **"Exportar"**.

### Emitir comprobante

Clic en **"Emitir comprobante"** en el header del módulo.

---

## 5.7 Mi caja

### ¿Qué muestra?

Estado de tu caja en el turno actual.

- Efectivo teórico (monto inicial + ventas efectivo).
- Ventas en efectivo.
- Transferencias (no suman a caja).
- Total vendido.
- Ventas en divisa.
- Movimientos de efectivo con acumulado.

---

## 5.8 Cerrar turno

1. Clic en **"Cerrar turno"**.
2. Revisa el resumen del turno.
3. Cuenta el efectivo físico.
4. En la calculadora de cierre, ingresa las cantidades de cada billete.
5. Si hay descuadre, escribe observaciones obligatorias.
6. Clic en **"CERRAR TURNO"**.
7. Confirma.

### Conteo de inventario (opcional)

Puedes hacer un conteo físico del inventario al cerrar.

---

## 5.9 Mis comprobantes

Ver comprobantes de tu turno actual. Anular los propios.

---

## 5.10 Reportes

### Reportes disponibles

- **Mis ventas** (del turno o rango de fechas).
- **Mi caja** (turno actual).

---

# Parte 6 — Casos de uso

## 6.1 Vender un producto nuevo (flujo completo end-to-end)

### Paso 1 — Crear producto (Almacenero)

1. Sidebar → Almacén → Entradas.
2. Clic en **"Nueva entrada"**.
3. Buscar "Agua Test 500ml" (no existe).
4. Clic en **"Crear producto con 'Agua Test 500ml'"**.
5. Rellenar: categoría, unidad, cantidad inicial 50.
6. Clic en **"Crear producto y registrar entrada"**.

### Paso 2 — Asignar precio (Admin)

1. Login como Admin.
2. Recibes notificación **"Producto pendiente de precio"**.
3. Sidebar → Productos.
4. Editar: precio 10, costo 5.
5. Guardar.

### Paso 3 — Solicitar traslado (Supervisor)

1. Sidebar → Solicitudes.
2. Clic en **"Nueva solicitud"**.
3. PV destino, motivo, agregar 20 unidades.
4. Crear solicitud.

### Paso 4 — Aprobar y despachar (Almacenero)

1. Sidebar → Solicitudes.
2. Abrir la solicitud.
3. Aprobar con cantidad 20.
4. Despachar.

### Paso 5 — Confirmar recepción (Supervisor)

1. Sidebar → Solicitudes.
2. Abrir la solicitud despachada.
3. Confirmar recepción.

### Paso 6 — Vender en el POS (Vendedor)

1. Abrir turno.
2. Ir al POS.
3. El producto aparece en el catálogo.
4. Agregar al carrito, cobrar.
5. Imprimir ticket (si aplica).

---

## 6.2 Vender con efectivo

Escenario: 2 Coca-Colas, el cliente paga con $100.

1. En el POS, busca "coca".
2. Clic en la tarjeta.
3. Cantidad 2.
4. Total: $50.00.
5. Clic en **"COBRAR"** (o F4).
6. Método: Efectivo.
7. Monto recibido: 100.
8. Vuelto: $50.00.
9. Clic en **"Confirmar cobro"**.
10. Modal de éxito con folio, total, vuelto.
11. Opción de **imprimir ticket**.
12. Clic en **"Nueva venta"**.

---

## 6.3 Vender con pago mixto

Escenario: $250 total, $150 efectivo + $100 transferencia.

1. Agregar productos.
2. Clic en **"COBRAR"**.
3. Método: **Mixto**.
4. Monto en efectivo: 150.
5. Monto por transferencia: 100.
6. Método de transferencia: Transfermóvil.
7. Referencia: 1234567890.
8. Últimos 4 dígitos: 4567.
9. Clic en **"Confirmar cobro"**.

---

## 6.4 Vender con comprobante

1. Agregar productos.
2. Clic en **"COBRAR"**.
3. Marca **"Emitir comprobante"**.
4. Ingresa datos del comprador (opcional).
5. Clic en **"Confirmar cobro"**.
6. El comprobante se emite automáticamente con folio `C-YYYY-NNNNN`.
7. Se muestra en el modal de éxito.

---

## 6.5 Vender con factura (cliente mayorista)

1. Agregar productos.
2. Clic en **"COBRAR"**.
3. Marca **"Requiere factura"**.
4. Selecciona cliente (o crea uno nuevo).
5. Clic en **"Confirmar cobro"**.
6. La venta queda **pendiente de facturar**.
7. El supervisor emite la factura después.

---

## 6.6 Cobrar en divisa

Escenario: total $1,600 CUP, tasa 320 CUP/USD.

1. Agregar productos.
2. Clic en **"COBRAR"**.
3. Selecciona moneda **USD**.
4. El sistema muestra:
   - Total en CUP: $1,600.00
   - Tasa: 320.00 CUP/USD
   - Total en divisa: $5.00
5. Ingresa monto recibido en USD: 10.
6. Vuelto en CUP: $1,600.00.
7. Clic en **"Confirmar cobro"**.

---

## 6.7 Reimprimir ticket

1. Sidebar → **Mis ventas** (o **Ventas del día** si eres supervisor).
2. Localiza la venta.
3. Clic en 🖨️.
4. Se abre modal con el PDF del ticket.
5. Opciones: Cerrar, Descargar PDF, Imprimir.

---

## 6.8 Registrar una entrada con contrato

Escenario: llega mercancía con factura FAC-2026-1234 amparada por el contrato CT-2026-0001.

1. Login como Almacenero.
2. Sidebar → Almacén → Entradas.
3. Clic en **"Nueva entrada"**.
4. Buscar producto.
5. Seleccionar el producto.
6. Rellenar: cantidad, motivo, referencia.
7. Seleccionar **contrato** CT-2026-0001.
8. Ingresar **Nº factura**: FAC-2026-1234.
9. Clic en **"Registrar entrada"**.

---

## 6.9 Gestionar contrato por vencer

Escenario: contrato CT-2026-0001 vence en 7 días.

1. Login como Admin.
2. Recibes notificación **"Contrato vence en 7 días"**.
3. Sidebar → Contratos.
4. Filtro por estado `por_vencer`.
5. Abrir el contrato.
6. Decidir:
   - **Renovar** → nuevo contrato, el viejo queda `renovado`.
   - **No renovar** → marcar motivo, queda `no_renovado`.

---

## 6.10 Emitir factura a cliente mayorista

1. Login como Supervisor.
2. Recibes notificación **"Venta pendiente de facturar"**.
3. Sidebar → Facturas.
4. Clic en **"Emitir nueva factura"**.
5. Selecciona la venta.
6. Ajusta: fecha de vencimiento, descuento, impuesto.
7. Clic en **"Emitir factura"**.
8. Se genera folio `F-YYYY-NNNN`.
9. Vista previa del PDF.

---

## 6.11 Registrar pago de factura

1. Abre el detalle de la factura.
2. Clic en **"Registrar pago"**.
3. Ingresa monto (≤ saldo pendiente), método, referencia.
4. Clic en **"Registrar pago"**.
5. El estado pasa a `parcial` o `pagada`.

---

## 6.12 Hacer un backup

1. Login como Admin.
2. Sidebar → Backup.
3. Clic en **"Generar y descargar backup"**.
4. Se descarga `backup_YYYYMMDD_HHMMSS.sql`.
5. Guárdalo en lugar seguro.

---

## 6.13 Activar la licencia

1. Login como Admin.
2. Sidebar → Licencia.
3. Copia tu **ID de instalación**.
4. Envíalo al vendedor.
5. Pega el código recibido.
6. Clic en **"Activar licencia"**.

---

## 6.14 Fijar tasa de cambio manual

1. Login como Admin.
2. Sidebar → Divisas.
3. Localiza USD.
4. Clic en **"Fijar tasa"**.
5. Nueva tasa (ej: 350.00).
6. Motivo: "Actualización por mercado informal".
7. Clic en **"Guardar tasa"**.

---

## 6.15 Cerrar turno con descuadre

Escenario: al cerrar, el efectivo contado es menor al esperado.

1. Clic en **"Cerrar turno"**.
2. Revisa resumen.
3. Cuenta los billetes.
4. Ingresa cantidades.
5. Aparece en rojo: "Faltan $500".
6. Escribe observaciones obligatorias.
7. Clic en **"CERRAR TURNO"**.
8. Confirma.
9. El supervisor verá el descuadre en su dashboard.

---

# Parte 7 — Preguntas frecuentes

## Cuenta y acceso

**1. Olvidé mi contraseña. ¿Qué hago?**
Contacta al administrador. Solo el admin puede resetear contraseñas.

**2. ¿Puedo cambiar mi contraseña?**
Sí, desde **Mi perfil** en el menú de usuario.

**3. Mi cuenta está bloqueada. ¿Qué hago?**
Espera 30 minutos. Después de varios intentos fallidos, el sistema bloquea temporalmente.

**4. ¿Cuánto tiempo dura mi sesión?**
2 horas de inactividad.

**5. ¿Cómo cambio mi foto de perfil?**
Menú de usuario → Mi perfil → Subir foto.

---

## POS y ventas

**6. ¿Puedo vender sin abrir turno?**
No. Primero debes abrir turno.

**7. ¿Puedo vender más de lo que hay en stock?**
Depende de la configuración (`pos_permitir_stock_negativo`).

**8. ¿Se pierde el carrito si recargo la página?**
No. El carrito se guarda en el navegador.

**9. ¿Puedo cancelar una venta después de cobrarla?**
Sí, pero solo el supervisor o admin. Se debe indicar el motivo.

**10. ¿Cómo imprimo un ticket?**
Al confirmar el cobro, si está configurado, se pregunta. También hay botón en el modal de éxito.

**11. ¿Puedo reimprimir un ticket?**
Sí, desde **Mis ventas** o **Ventas del día**.

**12. ¿Puedo cobrar en dólares?**
Sí, si el admin habilitó las divisas.

**13. ¿Por qué no veo un producto que sé que existe?**
Porque solo se muestran productos con al menos una **entrada** en tu PV. Contacta al supervisor.

---

## Inventario

**14. ¿Cómo sé si un producto tiene stock bajo?**
Los productos con stock bajo se marcan en amarillo. Los negativos, en rojo.

**15. ¿Puedo modificar el stock directamente?**
Solo con ajustes (supervisor o admin) con motivo obligatorio.

**16. ¿Qué es una "entrada"?**
Registrar que llegó mercancía.

**17. ¿Qué es una "baja"?**
Dar de baja un producto (vencido, dañado, robado).

**18. ¿Cómo veo el historial de un producto?**
En el almacén: **Trazabilidad**. En el PV: ver detalle del producto.

---

## Almacén y traslados

**19. ¿Cómo pido mercancía al almacén?**
Como Supervisor: **Solicitudes** → Nueva solicitud.

**20. ¿Cómo apruebo una solicitud?**
Como Almacenero: **Solicitudes** → abrir → ajustar cantidad → Aprobar.

**21. ¿Puedo rechazar una solicitud?**
Sí, con motivo obligatorio.

**22. ¿Qué pasa si no tengo stock suficiente?**
El sistema no te deja despachar. Puedes aprobar una cantidad menor o rechazar.

---

## Contratos y proveedores

**23. ¿Quién puede crear contratos?**
Solo el Administrador.

**24. ¿Qué pasa cuando un contrato vence?**
Pasa a `por_renovar` y genera avisos recurrentes hasta que decidas.

**25. ¿Cómo renuevo un contrato?**
Abre el contrato → **Renovar** → nuevo número y fechas. El anterior queda `renovado`.

---

## Facturación y comprobantes

**26. ¿Puedo emitir una factura sin venta?**
No. La factura siempre va 1:1 con una venta.

**27. ¿Puedo editar una factura emitida?**
No. Solo anular (si no tiene pagos).

**28. ¿Qué pasa si anulo un comprobante?**
Queda sin efecto. El PDF lo muestra como anulado.

**29. ¿Puedo emitir factura y comprobante en la misma venta?**
No. Son excluyentes.

---

## Turnos

**30. ¿Cuándo debo abrir turno?**
Al iniciar la jornada.

**31. ¿Cuándo debo cerrar turno?**
Al terminar la jornada.

**32. ¿Puedo dejar el turno abierto y cerrarlo mañana?**
No se recomienda. El turno acumula las ventas del día.

**33. ¿Cuántos turnos puedo tener abiertos?**
Uno solo en tu PV.

---

## Notificaciones

**34. ¿Cómo veo mis notificaciones?**
Pulsa la campana en el header.

**35. ¿Puedo eliminar notificaciones?**
Sí, individualmente o todas las leídas.

---

## Licencia

**36. ¿Qué pasa cuando termina la prueba?**
El sistema se bloquea hasta activar licencia.

**37. ¿La licencia se puede mover a otro servidor?**
No. Está vinculada al hardware.

**38. ¿Qué pasa si cambio el reloj del servidor?**
El sistema lo detecta y bloquea el acceso hasta corregirlo.

---

## Reportes

**39. ¿Puedo descargar un reporte en Excel?**
Sí. Cada reporte tiene botón PDF y Excel.

**40. ¿Los reportes llevan el logo del negocio?**
Sí, se configura en Personalización.

---

## Problemas técnicos

**41. No me carga el sistema.**
- Recarga (Ctrl+F5).
- Limpia la caché del navegador.
- Verifica tu conexión.
- Contacta al admin.

**42. No puedo iniciar sesión.**
- Verifica mayúsculas.
- Revisa Bloq Mayús.
- Prueba con otro navegador.
- Contacta al admin.

**43. El lector de código de barras no funciona.**
- Verifica que esté conectado por USB.
- Prueba en otro puerto.
- Reinicia el navegador.

**44. El ticket sale en blanco o con error.**
Verifica que `TCPDF` esté instalado en `public/libs/tcpdf/`. Contacta al admin.

---

# Parte 8 — Glosario

| Término | Significado |
|---------|-------------|
| **Admin** | Administrador del sistema. Acceso total. |
| **Ajuste** | Corregir el stock a un valor exacto. |
| **Almacén Central** | PV especial donde llega toda la mercancía antes de distribuirse. |
| **Almacenero** | Rol que gestiona el almacén central y las solicitudes. |
| **Auditoría** | Registro de todas las acciones del sistema. |
| **Backup** | Copia de seguridad de la base de datos. |
| **Caja** | Efectivo disponible en el PV. |
| **Categoría** | Agrupación de productos. |
| **Cierre de turno** | Fin del turno con conteo de caja. |
| **Cliente mayorista** | Cliente al que se le emite factura. |
| **Código de barras** | Identificador único de un producto. |
| **Comprobante** | Documento de venta minorista. |
| **Contrato** | Acuerdo con proveedor, con vigencia y productos. |
| **CSV** | Formato de archivo de datos separados por comas. |
| **Descuadre** | Diferencia entre efectivo esperado y contado. |
| **Despacho** | Envío de mercancía del almacén a un PV. |
| **Divisa** | Moneda extranjera (USD, EUR). |
| **Entrada** | Registro de mercancía que llega. |
| **Factura** | Documento de venta al por mayor. |
| **Fingerprint** | Huella digital del hardware del servidor. |
| **Folio** | Número único de cada venta, factura, comprobante o solicitud. |
| **INSTALL_ID** | Identificador único de la instalación. |
| **IPV** | Inventario de Productos y Ventas (nombre del sistema). |
| **Licencia** | Código firmado que autoriza el uso del sistema. |
| **Logs** | Registros técnicos del sistema. |
| **Notificación** | Aviso automático de eventos importantes. |
| **PDF** | Formato de documento portátil. |
| **POS** | Punto de Venta (interfaz). |
| **PV** | Punto de Venta (sucursal física). |
| **Recepción** | Confirmación del PV de que recibió la mercancía. |
| **Reimprimir ticket** | Volver a emitir el ticket de una venta. |
| **Solicitud de traslado** | Pedido al almacén. |
| **Stock** | Cantidad de unidades disponibles. |
| **Stock bajo** | Menor o igual al stock mínimo. |
| **Stock negativo** | Por debajo de 0 (sobreventa). |
| **Supervisor** | Rol que supervisa vendedores y verifica transferencias. |
| **Tasa de cambio** | Valor de una divisa en CUP. |
| **TCPDF** | Librería PHP que genera los PDFs. |
| **Ticket** | Comprobante impreso de una venta. |
| **Trazabilidad** | Expediente completo de un producto del almacén. |
| **Turno** | Período de trabajo de un vendedor. |
| **Usuario** | Cuenta para acceder al sistema. |
| **Vendedor** | Rol que opera el POS. |
| **Vuelto** | Dinero que se le devuelve al cliente. |

---

Para el manual técnico, consulta `manual_tecnico.md`.