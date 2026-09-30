-- ============================================================
-- IPV - Esquema completo del sistema
-- Motor: MySQL 5.7+ / MariaDB 10.4+
-- Codificación: UTF-8 (utf8mb4)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 1. ROLES
-- ============================================================
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(150),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (id, nombre, descripcion) VALUES
(1, 'Administrador', 'Acceso total al sistema, todos los puntos de venta'),
(2, 'Supervisor',    'Inventario, entradas, bajas, ajustes y supervisión de PV'),
(3, 'Vendedor',      'Registro de ventas y control de su turno en su PV'),
(4, 'Almacenero',    'Gestión del almacén central y distribución a puntos de venta');

-- ============================================================
-- 2. PUNTOS DE VENTA
-- El almacén se modela como un PV con es_almacen = 1
-- ============================================================
CREATE TABLE IF NOT EXISTS puntos_venta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    direccion VARCHAR(200),
    telefono VARCHAR(30),
    activo TINYINT(1) DEFAULT 1,
    es_almacen TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pv_almacen (es_almacen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. USUARIOS
-- ============================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    avatar VARCHAR(255) NULL,
    password VARCHAR(255) NOT NULL,
    rol_id INT NOT NULL,
    punto_venta_id INT NULL,
    activo TINYINT(1) DEFAULT 1,
    intentos_fallidos INT DEFAULT 0,
    bloqueado_hasta DATETIME NULL,
    ultimo_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id),
    FOREIGN KEY (punto_venta_id) REFERENCES puntos_venta(id) ON DELETE SET NULL,
    INDEX idx_usuarios_rol (rol_id),
    INDEX idx_usuarios_pv (punto_venta_id),
    INDEX idx_usuarios_email (email),
    INDEX idx_usuarios_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. CATEGORÍAS
-- ============================================================
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    descripcion VARCHAR(150),
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. UNIDADES DE MEDIDA
-- ============================================================
CREATE TABLE IF NOT EXISTS unidades_medida (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE,
    abreviatura VARCHAR(10) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    orden INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO unidades_medida (nombre, abreviatura, orden) VALUES
('Unidad',     'ud',  1),
('Caja',       'cja', 2),
('Paquete',    'pqt', 3),
('Kilogramo',  'kg',  4),
('Gramo',      'g',   5),
('Litro',      'L',   6),
('Mililitro',  'ml',  7),
('Metro',      'm',   8),
('Docena',     'doc', 9),
('Bolsa',      'bol', 10);

-- ============================================================
-- 6. PROVEEDORES
-- ============================================================
CREATE TABLE IF NOT EXISTS proveedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    nit VARCHAR(50) NULL,
    tipo_persona ENUM('fisica','juridica') DEFAULT 'juridica',
    direccion VARCHAR(255) NULL,
    telefono VARCHAR(50) NULL,
    email VARCHAR(120) NULL,
    contacto VARCHAR(120) NULL,
    notas TEXT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_prov_activo (activo),
    INDEX idx_prov_nombre (nombre),
    INDEX idx_prov_nit (nit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. PRODUCTOS
-- ============================================================
CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_barras VARCHAR(50) UNIQUE,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255),
    precio DECIMAL(10,2) NOT NULL DEFAULT 0,
    costo DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock_minimo INT NOT NULL DEFAULT 5,
    unidad_medida VARCHAR(20) NOT NULL DEFAULT 'Unidad',
    categoria_id INT,
    activo TINYINT(1) DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_prod_cat (categoria_id),
    INDEX idx_prod_activo (activo),
    INDEX idx_prod_codigo (codigo_barras),
    INDEX idx_prod_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. PRECIOS_HISTORIAL
-- ============================================================
CREATE TABLE IF NOT EXISTS precios_historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    precio_anterior DECIMAL(10,2) NOT NULL,
    precio_nuevo DECIMAL(10,2) NOT NULL,
    usuario_id INT NOT NULL,
    motivo VARCHAR(150),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_precio_prod (producto_id),
    INDEX idx_precio_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. CONTRATOS DE PROVEEDOR
-- ============================================================
CREATE TABLE IF NOT EXISTS contratos_proveedor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    num_contrato VARCHAR(50) NOT NULL UNIQUE,
    proveedor_id INT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_caducidad DATE NOT NULL,
    monto DECIMAL(12,2) NULL,
    moneda VARCHAR(5) DEFAULT 'CUP',
    forma_pago ENUM('contado','credito','mixto') DEFAULT 'contado',
    plazo_dias INT NULL,
    descripcion TEXT NULL,
    documento VARCHAR(255) NULL,
    observaciones TEXT NULL,
    estado ENUM('activo','por_renovar','renovado','cancelado','no_renovado') DEFAULT 'activo',
    contrato_anterior_id INT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
    FOREIGN KEY (contrato_anterior_id) REFERENCES contratos_proveedor(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_cont_prov (proveedor_id),
    INDEX idx_cont_estado (estado),
    INDEX idx_cont_caducidad (fecha_caducidad),
    INDEX idx_cont_num (num_contrato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. CONTRATOS_PRODUCTOS (relación N:N)
-- ============================================================
CREATE TABLE IF NOT EXISTS contratos_productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contrato_id INT NOT NULL,
    producto_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_contrato_producto (contrato_id, producto_id),
    FOREIGN KEY (contrato_id) REFERENCES contratos_proveedor(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    INDEX idx_cp_contrato (contrato_id),
    INDEX idx_cp_producto (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. STOCK_PUNTO_VENTA
-- ============================================================
CREATE TABLE IF NOT EXISTS stock_punto_venta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    punto_venta_id INT NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_stock (producto_id, punto_venta_id),
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (punto_venta_id) REFERENCES puntos_venta(id) ON DELETE CASCADE,
    INDEX idx_stock_pv (punto_venta_id),
    INDEX idx_stock_prod (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. TURNOS
-- ============================================================
CREATE TABLE IF NOT EXISTS turnos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    punto_venta_id INT NOT NULL,
    usuario_id INT NOT NULL,
    fecha_apertura DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre DATETIME NULL,
    monto_inicial DECIMAL(10,2) DEFAULT 0,
    monto_final_efectivo DECIMAL(10,2) DEFAULT 0,
    monto_final_transferencia DECIMAL(10,2) DEFAULT 0,
    total_ventas DECIMAL(10,2) DEFAULT 0,
    estado ENUM('abierto','cerrado') DEFAULT 'abierto',
    cierre_con_conteo TINYINT(1) DEFAULT 0,
    total_descuadres INT DEFAULT 0,
    observaciones VARCHAR(255),
    observaciones_cierre VARCHAR(255),
    forzado TINYINT(1) DEFAULT 0,
    forzado_por INT NULL,
    fecha_forzado DATETIME NULL,
    justificacion_forzado TEXT NULL,
    FOREIGN KEY (punto_venta_id) REFERENCES puntos_venta(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (forzado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_turno_pv (punto_venta_id),
    INDEX idx_turno_user (usuario_id),
    INDEX idx_turno_estado (estado),
    INDEX idx_turno_fecha (fecha_apertura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. TURNO_INVENTARIO
-- ============================================================
CREATE TABLE IF NOT EXISTS turno_inventario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turno_id INT NOT NULL,
    producto_id INT NOT NULL,
    existencia_inicial INT NOT NULL DEFAULT 0,
    entradas INT NOT NULL DEFAULT 0,
    ventas INT NOT NULL DEFAULT 0,
    bajas INT NOT NULL DEFAULT 0,
    ajustes INT NOT NULL DEFAULT 0,
    existencia_final INT NULL,
    descuadre INT DEFAULT 0,
    observaciones VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_turno_prod (turno_id, producto_id),
    FOREIGN KEY (turno_id) REFERENCES turnos(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    INDEX idx_ti_turno (turno_id),
    INDEX idx_ti_prod (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. MOVIMIENTOS
-- ============================================================
CREATE TABLE IF NOT EXISTS movimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    punto_venta_id INT NOT NULL,
    turno_id INT NULL,
    tipo ENUM('entrada','salida','baja','transferencia','ajuste') NOT NULL,
    cantidad INT NOT NULL,
    motivo VARCHAR(200),
    descripcion VARCHAR(255),
    valor_anterior INT NULL,
    valor_nuevo INT NULL,
    usuario_id INT NOT NULL,
    contrato_id INT NULL,
    num_contrato VARCHAR(50) NULL,
    numero_factura VARCHAR(100) NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (punto_venta_id) REFERENCES puntos_venta(id),
    FOREIGN KEY (turno_id) REFERENCES turnos(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (contrato_id) REFERENCES contratos_proveedor(id) ON DELETE SET NULL,
    INDEX idx_mov_prod (producto_id),
    INDEX idx_mov_pv (punto_venta_id),
    INDEX idx_mov_turno (turno_id),
    INDEX idx_mov_fecha (fecha),
    INDEX idx_mov_tipo (tipo),
    INDEX idx_mov_user (usuario_id),
    INDEX idx_mov_contrato (contrato_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. DIVISAS
-- ============================================================
CREATE TABLE IF NOT EXISTS divisas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(5) NOT NULL UNIQUE,
    nombre VARCHAR(50) NOT NULL,
    simbolo VARCHAR(5) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    orden INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO divisas (codigo, nombre, simbolo, activo, orden) VALUES
('USD', 'Dólar estadounidense', '$', 1, 1),
('EUR', 'Euro',                 '€', 1, 2);

-- ============================================================
-- 16. TASAS_CAMBIO
-- ============================================================
CREATE TABLE IF NOT EXISTS tasas_cambio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    divisa_id INT NOT NULL,
    tasa DECIMAL(12,4) NOT NULL,
    origen ENUM('auto','manual') DEFAULT 'auto',
    fuente VARCHAR(50) NULL,
    usuario_id INT NULL,
    motivo VARCHAR(150) NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (divisa_id) REFERENCES divisas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_tasa_divisa (divisa_id),
    INDEX idx_tasa_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. CLIENTES MAYORISTAS
-- ============================================================
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    nit VARCHAR(50) NULL,
    tipo_persona ENUM('fisica','juridica') DEFAULT 'juridica',
    direccion VARCHAR(255) NULL,
    telefono VARCHAR(50) NULL,
    email VARCHAR(120) NULL,
    contacto VARCHAR(120) NULL,
    notas TEXT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_cli_activo (activo),
    INDEX idx_cli_nombre (nombre),
    INDEX idx_cli_nit (nit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 18. VENTAS
-- ============================================================
CREATE TABLE IF NOT EXISTS ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folio VARCHAR(20) NOT NULL UNIQUE,
    turno_id INT NOT NULL,
    punto_venta_id INT NOT NULL,
    usuario_id INT NOT NULL,
    cliente_id INT NULL,
    requiere_factura TINYINT(1) DEFAULT 0,
    requiere_comprobante TINYINT(1) DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    moneda VARCHAR(5) DEFAULT 'CUP',
    tasa_aplicada DECIMAL(12,4) NULL,
    total_divisa DECIMAL(10,2) NULL,
    equivalente_cup DECIMAL(10,2) NULL,
    estado ENUM('completada','cancelada') DEFAULT 'completada',
    motivo_cancelacion VARCHAR(255) NULL,
    cancelada_por INT NULL,
    fecha_cancelacion DATETIME NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (turno_id) REFERENCES turnos(id),
    FOREIGN KEY (punto_venta_id) REFERENCES puntos_venta(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_venta_turno (turno_id),
    INDEX idx_venta_pv (punto_venta_id),
    INDEX idx_venta_user (usuario_id),
    INDEX idx_venta_fecha (fecha),
    INDEX idx_venta_estado (estado),
    INDEX idx_venta_folio (folio),
    INDEX idx_venta_cliente (cliente_id),
    INDEX idx_venta_requiere_factura (requiere_factura),
    INDEX idx_venta_requiere_comprobante (requiere_comprobante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 19. DETALLE_VENTAS
-- ============================================================
CREATE TABLE IF NOT EXISTS detalle_ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    INDEX idx_det_venta (venta_id),
    INDEX idx_det_prod (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 20. PAGOS_VENTA
-- ============================================================
CREATE TABLE IF NOT EXISTS pagos_venta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    metodo ENUM('efectivo','transferencia') NOT NULL,
    metodo_detalle VARCHAR(50) NULL,
    monto DECIMAL(10,2) NOT NULL,
    moneda VARCHAR(5) DEFAULT 'CUP',
    monto_divisa DECIMAL(10,2) NULL,
    referencia VARCHAR(100) NULL,
    ultimos_digitos VARCHAR(4) NULL,
    titular VARCHAR(100) NULL,
    banco VARCHAR(50) NULL,
    comprobante VARCHAR(255) NULL,
    verificado TINYINT(1) DEFAULT 0,
    verificado_por INT NULL,
    fecha_verificacion DATETIME NULL,
    motivo_rechazo VARCHAR(255) NULL,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (verificado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_pago_venta (venta_id),
    INDEX idx_pago_metodo (metodo),
    INDEX idx_pago_verificado (verificado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 21. DENOMINACIONES
-- ============================================================
CREATE TABLE IF NOT EXISTS denominaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    moneda VARCHAR(10) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    tipo ENUM('billete','moneda') NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    orden INT DEFAULT 0,
    UNIQUE KEY uk_den (moneda, valor, tipo),
    INDEX idx_den_moneda (moneda),
    INDEX idx_den_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO denominaciones (moneda, valor, tipo, orden) VALUES
('CUP', 5000.00, 'billete', 1),
('CUP', 2000.00, 'billete', 2),
('CUP', 1000.00, 'billete', 3),
('CUP',  500.00, 'billete', 4),
('CUP',  200.00, 'billete', 5),
('CUP',  100.00, 'billete', 6),
('CUP',   50.00, 'billete', 7),
('CUP',   20.00, 'billete', 8),
('CUP',   10.00, 'billete', 9),
('CUP',    5.00, 'billete', 10),
('CUP',    5.00, 'moneda', 11),
('CUP',    2.00, 'moneda', 12),
('CUP',    1.00, 'moneda', 13),
('USD',  100.00, 'billete', 1),
('USD',   50.00, 'billete', 2),
('USD',   20.00, 'billete', 3),
('USD',   10.00, 'billete', 4),
('USD',    5.00, 'billete', 5),
('USD',    1.00, 'billete', 6),
('EUR',  500.00, 'billete', 1),
('EUR',  200.00, 'billete', 2),
('EUR',  100.00, 'billete', 3),
('EUR',   50.00, 'billete', 4),
('EUR',   20.00, 'billete', 5),
('EUR',   10.00, 'billete', 6),
('EUR',    5.00, 'billete', 7);

-- ============================================================
-- 22. VENTA_DENOMINACIONES
-- ============================================================
CREATE TABLE IF NOT EXISTS venta_denominaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    pago_id INT NULL,
    denominacion_id INT NOT NULL,
    cantidad INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    tipo_movimiento ENUM('recibido','vuelto') DEFAULT 'recibido',
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (pago_id) REFERENCES pagos_venta(id) ON DELETE CASCADE,
    FOREIGN KEY (denominacion_id) REFERENCES denominaciones(id),
    INDEX idx_vd_venta (venta_id),
    INDEX idx_vd_tipo (tipo_movimiento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 23. METODOS_TRANSFERENCIA
-- ============================================================
CREATE TABLE IF NOT EXISTS metodos_transferencia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    requiere_referencia TINYINT(1) DEFAULT 1,
    requiere_titular TINYINT(1) DEFAULT 0,
    requiere_banco TINYINT(1) DEFAULT 0,
    requiere_comprobante TINYINT(1) DEFAULT 0,
    formato_referencia VARCHAR(100) NULL,
    activo TINYINT(1) DEFAULT 1,
    orden INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO metodos_transferencia (nombre, requiere_referencia, requiere_titular, requiere_banco, requiere_comprobante, activo, orden) VALUES
('Transfermóvil', 1, 0, 0, 0, 1, 1),
('EnZona',        1, 0, 0, 0, 1, 2),
('Tarjeta débito',1, 0, 1, 1, 1, 3),
('Tarjeta crédito',1, 0, 1, 1, 1, 4),
('Otro',          1, 1, 0, 0, 1, 5);

-- ============================================================
-- 24. AUDITORIA
-- ============================================================
CREATE TABLE IF NOT EXISTS auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    accion VARCHAR(100) NOT NULL,
    tabla_afectada VARCHAR(50),
    registro_id INT NULL,
    detalle TEXT,
    ip VARCHAR(45),
    user_agent VARCHAR(255),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_aud_user (usuario_id),
    INDEX idx_aud_fecha (fecha),
    INDEX idx_aud_accion (accion),
    INDEX idx_aud_tabla (tabla_afectada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 25. CONFIGURACION
-- ============================================================
CREATE TABLE IF NOT EXISTS configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(80) NOT NULL UNIQUE,
    valor TEXT,
    tipo ENUM('texto','textarea','imagen','color','numero','booleano','select') DEFAULT 'texto',
    categoria VARCHAR(50) DEFAULT 'general',
    descripcion VARCHAR(200),
    opciones TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_conf_cat (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO configuracion (clave, valor, tipo, categoria, descripcion) VALUES
('empresa_nombre',      'Mi Negocio IPV',        'texto',    'identidad', 'Nombre de la empresa'),
('empresa_eslogan',     'Calidad y servicio',    'texto',    'identidad', 'Eslogan o lema'),
('empresa_descripcion', '',                      'textarea', 'identidad', 'Descripción corta'),
('empresa_logo',        '',                      'imagen',   'identidad', 'Logotipo principal'),
('empresa_favicon',     '',                      'imagen',   'identidad', 'Icono del navegador'),
('empresa_direccion',   '',                      'texto',    'contacto',  'Dirección física'),
('empresa_telefono',    '',                      'texto',    'contacto',  'Teléfono principal'),
('empresa_email',       '',                      'texto',    'contacto',  'Email de contacto'),
('empresa_web',         '',                      'texto',    'contacto',  'Sitio web'),
('color_primario',      '#2563eb',               'color',    'colores',   'Color principal'),
('color_secundario',    '#1e40af',               'color',    'colores',   'Color secundario'),
('color_acento',        '#f59e0b',               'color',    'colores',   'Color de acento'),
('color_exito',         '#16a34a',               'color',    'colores',   'Color de éxito'),
('color_peligro',       '#dc2626',               'color',    'colores',   'Color de peligro'),
('moneda_simbolo',      '$',                     'texto',    'moneda',    'Símbolo de moneda'),
('moneda_codigo',       'CUP',                   'texto',    'moneda',    'Código ISO'),
('moneda_posicion',     'antes',                 'select',   'moneda',    'Posición del símbolo'),
('moneda_decimales',    '2',                     'numero',   'moneda',    'Decimales'),
('formato_fecha',       'd/m/Y H:i',             'texto',    'moneda',    'Formato de fecha'),
('zona_horaria',        'America/Havana',        'select',   'moneda',    'Zona horaria'),
('tema_modo',           'claro',                 'select',   'tema',      'Modo visual'),
('tipografia',          'Inter',                 'select',   'tema',      'Fuente tipográfica'),
('permitir_cambio_tema','1',                     'booleano', 'tema',      'Permitir cambio de tema'),
('login_fondo',         '',                      'imagen',   'tema',      'Fondo del login'),
('login_mensaje',       'Bienvenido al sistema', 'texto',    'tema',      'Mensaje del login'),
('pdf_encabezado',      '',                      'textarea', 'documentos','Encabezado de PDFs'),
('pdf_pie',             '',                      'textarea', 'documentos','Pie de página'),
('pdf_texto_legal',     '',                      'textarea', 'documentos','Texto legal'),
('pass_longitud_min',     '8',  'numero',   'seguridad', 'Longitud mínima de contraseña'),
('pass_req_mayuscula',    '1',  'booleano', 'seguridad', 'Requerir mayúscula'),
('pass_req_minuscula',    '1',  'booleano', 'seguridad', 'Requerir minúscula'),
('pass_req_numero',       '1',  'booleano', 'seguridad', 'Requerir número'),
('pass_req_simbolo',      '1',  'booleano', 'seguridad', 'Requerir símbolo'),
('pass_historial',        '3',  'numero',   'seguridad', 'No repetir últimas N contraseñas'),
('pass_caducidad_dias',   '0',  'numero',   'seguridad', 'Días de caducidad (0 = nunca)'),
('pos_permitir_stock_negativo',     '1', 'booleano', 'pos', 'Permitir vender con stock negativo'),
('pos_mostrar_sin_stock',           '1', 'booleano', 'pos', 'Mostrar productos sin stock'),
('pos_modo_conteo_default',         'opcional', 'select', 'pos', 'Conteo por denominación'),
('pos_ticket_tamano',               '80mm', 'select', 'pos', 'Tamaño del ticket'),
('pos_imprimir_preguntar',          '1', 'booleano', 'pos', 'Preguntar antes de imprimir'),
('turno_cierre_con_conteo_default', '0', 'booleano', 'turno', 'Conteo físico por defecto'),
('turno_bloquear_si_negativos',     '1', 'booleano', 'turno', 'Bloquear cierre si hay negativos'),
('cierre_forzar_supervisor',        '1', 'booleano', 'turno', 'Supervisor puede forzar cierre'),
('cierre_forzar_admin',             '1', 'booleano', 'turno', 'Admin puede forzar cierre'),
('cierre_justificacion_min',        '20','numero',   'turno', 'Mínimo caracteres justificación'),
('supervisor_puede_ajustar',        '1', 'booleano', 'inventario', 'Permitir ajustes al supervisor'),
('ajustes_inmediatos',              '1', 'booleano', 'inventario', 'Ajustes sin aprobación'),
('divisas_habilitadas',             '1', 'booleano', 'divisas', 'Habilitar venta en divisas'),
('divisas_auto_update',             '1', 'booleano', 'divisas', 'Actualización automática de tasas'),
('divisas_auto_frecuencia',         'hora', 'select', 'divisas', 'Frecuencia de actualización'),
('divisas_auto_fuente',             'eltoque', 'select', 'divisas', 'Fuente de tasas'),
('divisas_permitir_manual',         '1', 'booleano', 'divisas', 'Permitir sobrescritura manual'),
('divisas_manual_duracion_horas',   '6', 'numero',   'divisas', 'Horas de vigencia de tasa manual'),
('transf_comprobante_adjunto',      'opcional', 'select', 'transferencias', 'Política de comprobante'),
('transf_comprobante_max_mb',       '5', 'numero', 'transferencias', 'Tamaño máximo MB'),
('transf_verificacion',             'obligatoria', 'select', 'transferencias', 'Verificación'),
('transf_comprobante_retencion',    '90', 'numero', 'transferencias', 'Días de retención'),
('notif_email_criticas',            '0', 'booleano', 'notificaciones', 'Email en eventos críticos'),
('notif_polling_segundos',          '60','numero',   'notificaciones', 'Intervalo de polling'),
('notif_intentos_login',            '5', 'numero',   'notificaciones', 'Intentos antes de bloquear'),
('notif_max_dropdown',              '10','numero',   'notificaciones', 'Notificaciones en el dropdown'),
('install_seed',                    '',  'texto',    'licencia', 'Semilla única de instalación'),
('almacen_id',                      '0', 'numero',   'almacen', 'ID del PV que actúa como almacén'),
('almacen_permitir_negativo',       '0', 'booleano', 'almacen', 'Permitir stock negativo en el almacén'),
('almacen_requiere_recepcion',      '1', 'booleano', 'almacen', 'Requiere confirmación de recepción'),
('factura_plazo_dias_default',      '30','numero',   'facturacion', 'Plazo de pago por defecto (días)'),
('factura_dias_aviso_vencimiento',  '7', 'numero',   'facturacion', 'Días antes del vencimiento para avisar');

-- ============================================================
-- 26. NOTIFICACIONES
-- ============================================================
CREATE TABLE IF NOT EXISTS notificaciones (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 27. SOLICITUDES_TRASLADO
-- ============================================================
CREATE TABLE IF NOT EXISTS solicitudes_traslado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folio VARCHAR(20) NOT NULL UNIQUE,
    almacen_id INT NOT NULL,
    punto_venta_id INT NOT NULL,
    solicitado_por INT NOT NULL,
    aprobado_por INT NULL,
    despachado_por INT NULL,
    recibido_por INT NULL,
    rechazado_por INT NULL,
    cancelado_por INT NULL,
    estado ENUM(
        'solicitado',
        'aprobado',
        'despachado',
        'despachado_parcial',
        'recibido',
        'rechazado',
        'cancelado'
    ) DEFAULT 'solicitado',
    motivo VARCHAR(200),
    descripcion TEXT,
    observaciones_almacen VARCHAR(255),
    observaciones_recepcion VARCHAR(255),
    motivo_rechazo VARCHAR(255),
    motivo_cancelacion VARCHAR(255),
    total_items INT DEFAULT 0,
    total_unidades_solicitadas INT DEFAULT 0,
    total_unidades_despachadas INT DEFAULT 0,
    total_unidades_recibidas INT DEFAULT 0,
    fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_aprobacion DATETIME NULL,
    fecha_despacho DATETIME NULL,
    fecha_recepcion DATETIME NULL,
    fecha_rechazo DATETIME NULL,
    fecha_cancelacion DATETIME NULL,
    FOREIGN KEY (almacen_id) REFERENCES puntos_venta(id),
    FOREIGN KEY (punto_venta_id) REFERENCES puntos_venta(id),
    FOREIGN KEY (solicitado_por) REFERENCES usuarios(id),
    FOREIGN KEY (aprobado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (despachado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (recibido_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (rechazado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_sol_almacen (almacen_id),
    INDEX idx_sol_pv (punto_venta_id),
    INDEX idx_sol_estado (estado),
    INDEX idx_sol_fecha (fecha_solicitud)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 28. SOLICITUDES_DETALLE
-- ============================================================
CREATE TABLE IF NOT EXISTS solicitudes_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad_solicitada INT NOT NULL DEFAULT 0,
    cantidad_aprobada INT NOT NULL DEFAULT 0,
    cantidad_despachada INT NOT NULL DEFAULT 0,
    cantidad_recibida INT NOT NULL DEFAULT 0,
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_traslado(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    INDEX idx_soldet_sol (solicitud_id),
    INDEX idx_soldet_prod (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 29. FACTURAS (1:1 con ventas)
-- ============================================================
CREATE TABLE IF NOT EXISTS facturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folio VARCHAR(20) NOT NULL UNIQUE,
    venta_id INT NOT NULL UNIQUE,
    cliente_id INT NOT NULL,
    fecha_emision DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_vencimiento DATE NULL,
    moneda VARCHAR(5) DEFAULT 'CUP',
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    descuento DECIMAL(10,2) NOT NULL DEFAULT 0,
    impuesto DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    estado ENUM('emitida','parcial','pagada','anulada') DEFAULT 'emitida',
    observaciones TEXT NULL,
    emitida_por INT NULL,
    anulada_por INT NULL,
    fecha_anulacion DATETIME NULL,
    motivo_anulacion VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (emitida_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (anulada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_fact_cliente (cliente_id),
    INDEX idx_fact_estado (estado),
    INDEX idx_fact_fecha (fecha_emision),
    INDEX idx_fact_vencimiento (fecha_vencimiento),
    INDEX idx_fact_folio (folio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 30. FACTURAS_PAGOS
-- ============================================================
CREATE TABLE IF NOT EXISTS facturas_pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    factura_id INT NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    monto DECIMAL(10,2) NOT NULL,
    metodo ENUM('efectivo','transferencia','otro') DEFAULT 'efectivo',
    referencia VARCHAR(100) NULL,
    notas VARCHAR(255) NULL,
    usuario_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_fp_factura (factura_id),
    INDEX idx_fp_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 31. COMPROBANTES DE VENTA (minorista)
-- ============================================================
CREATE TABLE IF NOT EXISTS comprobantes_venta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folio VARCHAR(20) NOT NULL UNIQUE,
    venta_id INT NOT NULL,
    nombre_comprador VARCHAR(150) NULL,
    documento_comprador VARCHAR(50) NULL,
    telefono_comprador VARCHAR(50) NULL,
    direccion_comprador VARCHAR(255) NULL,
    observaciones VARCHAR(255) NULL,
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    moneda VARCHAR(5) DEFAULT 'CUP',
    estado ENUM('emitido','anulado') DEFAULT 'emitido',
    emitido_por INT NULL,
    anulado_por INT NULL,
    fecha_anulacion DATETIME NULL,
    motivo_anulacion VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (emitido_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (anulado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_comp_venta (venta_id),
    INDEX idx_comp_estado (estado),
    INDEX idx_comp_fecha (created_at),
    INDEX idx_comp_folio (folio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VISTAS
-- ============================================================

CREATE OR REPLACE VIEW v_ventas_dia AS
SELECT 
    v.id, v.folio, v.fecha,
    pv.nombre AS punto_venta,
    u.nombre AS vendedor,
    v.total, v.estado,
    t.id AS turno_id
FROM ventas v
JOIN puntos_venta pv ON pv.id = v.punto_venta_id
JOIN usuarios u ON u.id = v.usuario_id
JOIN turnos t ON t.id = v.turno_id
WHERE DATE(v.fecha) = CURDATE();

CREATE OR REPLACE VIEW v_caja_dia_vendedor AS
SELECT 
    u.id AS usuario_id,
    u.nombre AS vendedor,
    pv.nombre AS punto_venta,
    DATE(v.fecha) AS dia,
    SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END) AS total_efectivo,
    SUM(CASE WHEN p.metodo = 'transferencia' THEN p.monto ELSE 0 END) AS total_transferencia,
    SUM(p.monto) AS total_general,
    COUNT(DISTINCT v.id) AS num_ventas
FROM ventas v
JOIN usuarios u ON u.id = v.usuario_id
JOIN puntos_venta pv ON pv.id = v.punto_venta_id
JOIN pagos_venta p ON p.venta_id = v.id
WHERE v.estado = 'completada'
GROUP BY u.id, u.nombre, pv.nombre, DATE(v.fecha);

CREATE OR REPLACE VIEW v_top_productos_mes AS
SELECT 
    pr.id AS producto_id,
    pr.nombre AS producto,
    pr.unidad_medida,
    SUM(dv.cantidad) AS unidades_vendidas,
    SUM(dv.subtotal) AS total_vendido
FROM detalle_ventas dv
JOIN productos pr ON pr.id = dv.producto_id
JOIN ventas v ON v.id = dv.venta_id
WHERE v.estado = 'completada'
  AND MONTH(v.fecha) = MONTH(CURDATE())
  AND YEAR(v.fecha) = YEAR(CURDATE())
GROUP BY pr.id, pr.nombre, pr.unidad_medida
ORDER BY unidades_vendidas DESC;

CREATE OR REPLACE VIEW v_stock_actual AS
SELECT 
    spv.id,
    pr.id AS producto_id,
    pr.nombre AS producto,
    pr.codigo_barras,
    pr.unidad_medida,
    pv.id AS punto_venta_id,
    pv.nombre AS punto_venta,
    pv.es_almacen,
    spv.stock,
    pr.stock_minimo,
    CASE WHEN spv.stock <= pr.stock_minimo THEN 1 ELSE 0 END AS alerta_stock,
    CASE WHEN spv.stock < 0 THEN 1 ELSE 0 END AS stock_negativo
FROM stock_punto_venta spv
JOIN productos pr ON pr.id = spv.producto_id
JOIN puntos_venta pv ON pv.id = spv.punto_venta_id;

CREATE OR REPLACE VIEW v_ventas_por_vendedor AS
SELECT 
    u.id AS usuario_id,
    u.nombre AS vendedor,
    pv.nombre AS punto_venta,
    DATE(v.fecha) AS dia,
    COUNT(v.id) AS num_ventas,
    SUM(v.total) AS total_vendido
FROM ventas v
JOIN usuarios u ON u.id = v.usuario_id
JOIN puntos_venta pv ON pv.id = v.punto_venta_id
WHERE v.estado = 'completada'
GROUP BY u.id, u.nombre, pv.nombre, DATE(v.fecha);

CREATE OR REPLACE VIEW v_transferencias_pendientes AS
SELECT 
    p.id AS pago_id,
    v.id AS venta_id,
    v.folio,
    v.fecha,
    u.nombre AS vendedor,
    pv.nombre AS punto_venta,
    p.metodo_detalle,
    p.monto,
    p.referencia,
    p.comprobante
FROM pagos_venta p
JOIN ventas v ON v.id = p.venta_id
JOIN usuarios u ON u.id = v.usuario_id
JOIN puntos_venta pv ON pv.id = v.punto_venta_id
WHERE p.metodo = 'transferencia'
  AND p.verificado = 0
  AND v.estado = 'completada'
ORDER BY v.fecha DESC;

CREATE OR REPLACE VIEW v_stock_almacen AS
SELECT 
    p.id AS producto_id,
    p.codigo_barras,
    p.nombre AS producto,
    p.precio,
    p.costo,
    p.stock_minimo,
    p.unidad_medida,
    p.activo,
    c.nombre AS categoria,
    c.id AS categoria_id,
    COALESCE(spv.stock, 0) AS stock,
    COALESCE(spv.id, 0) AS stock_id
FROM productos p
LEFT JOIN categorias c ON c.id = p.categoria_id
LEFT JOIN stock_punto_venta spv ON spv.producto_id = p.id
    AND spv.punto_venta_id = (
        SELECT id FROM puntos_venta WHERE es_almacen = 1 AND activo = 1 LIMIT 1
    )
WHERE p.activo = 1;

-- ============================================================
-- FACTURAS — VISTA AUXILIAR
-- ============================================================
CREATE OR REPLACE VIEW v_facturas_pendientes AS
SELECT 
    f.id AS factura_id,
    f.folio,
    f.venta_id,
    f.cliente_id,
    c.nombre AS cliente,
    c.nit AS cliente_nit,
    f.fecha_emision,
    f.fecha_vencimiento,
    f.total,
    f.estado,
    COALESCE((SELECT SUM(p.monto) FROM facturas_pagos p WHERE p.factura_id = f.id), 0) AS total_pagado,
    f.total - COALESCE((SELECT SUM(p.monto) FROM facturas_pagos p WHERE p.factura_id = f.id), 0) AS saldo_pendiente
FROM facturas f
JOIN clientes c ON c.id = f.cliente_id
WHERE f.estado IN ('emitida', 'parcial')
ORDER BY f.fecha_vencimiento ASC, f.fecha_emision ASC;