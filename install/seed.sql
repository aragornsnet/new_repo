-- ============================================================
-- IPV - Datos de ejemplo
-- ============================================================

-- ============================================================
-- PUNTOS DE VENTA
-- ============================================================
INSERT IGNORE INTO puntos_venta (id, nombre, direccion, telefono) VALUES
(1, 'PV Centro', 'Calle Obispo #123, Habana Vieja', '+53 7 1234567'),
(2, 'PV Vedado', 'Calle 23 #456, Vedado',           '+53 7 2345678'),
(3, 'PV Playa',  'Ave 5ta #789, Playa',             '+53 7 3456789');

-- ============================================================
-- USUARIOS DEMO
-- Password para todos: Admin123!
-- Hash generado con password_hash('Admin123!', PASSWORD_DEFAULT)
-- ============================================================
INSERT IGNORE INTO usuarios (nombre, email, password, rol_id, punto_venta_id, avatar) VALUES
('Admin Demo',        'admin@ipv.com',       '$2y$10$8K1p/a0dL1LXMIgoEDFrwOfgqwAG1wzaXb5pAFqP0FHFBXNBt3uUu', 1, NULL, NULL),
('Supervisor Carlos', 'supervisor@ipv.com',  '$2y$10$8K1p/a0dL1LXMIgoEDFrwOfgqwAG1wzaXb5pAFqP0FHFBXNBt3uUu', 2, NULL, NULL),
('Supervisor Marta',  'supervisor2@ipv.com', '$2y$10$8K1p/a0dL1LXMIgoEDFrwOfgqwAG1wzaXb5pAFqP0FHFBXNBt3uUu', 2, NULL, NULL),
('Vendedor Juan',     'vendedor@ipv.com',    '$2y$10$8K1p/a0dL1LXMIgoEDFrwOfgqwAG1wzaXb5pAFqP0FHFBXNBt3uUu', 3, 1,    NULL),
('Vendedora Ana',     'vendedora@ipv.com',   '$2y$10$8K1p/a0dL1LXMIgoEDFrwOfgqwAG1wzaXb5pAFqP0FHFBXNBt3uUu', 3, 2,    NULL),
('Vendedor Pedro',    'vendedor2@ipv.com',   '$2y$10$8K1p/a0dL1LXMIgoEDFrwOfgqwAG1wzaXb5pAFqP0FHFBXNBt3uUu', 3, 3,    NULL);

-- ============================================================
-- CATEGOR�AS
-- ============================================================
INSERT IGNORE INTO categorias (id, nombre, descripcion) VALUES
(1, 'Bebidas',    'Refrescos, jugos y agua'),
(2, 'Snacks',     'Papas, galletas y dulces'),
(3, 'Aseo',       'Productos de limpieza'),
(4, 'Panader�a',  'Pan y boller�a'),
(5, 'Enlatados',  'Conservas y enlatados');

-- ============================================================
-- PRODUCTOS
-- ============================================================
INSERT IGNORE INTO productos (id, codigo_barras, nombre, precio, costo, stock_minimo, categoria_id) VALUES
(1,  '7501000000001', 'Coca-Cola 600ml',       25.00, 15.00, 10, 1),
(2,  '7501000000002', 'Agua Mineral 1L',       15.00,  8.00, 10, 1),
(3,  '7501000000003', 'Jugo de Naranja 1L',    30.00, 18.00,  5, 1),
(4,  '7501000000004', 'Sabritas Original',     20.00, 12.00, 10, 2),
(5,  '7501000000005', 'Galletas Mar�a',        18.00, 10.00, 10, 2),
(6,  '7501000000006', 'Chocolate Criollo',     35.00, 22.00,  5, 2),
(7,  '7501000000007', 'Cloro 1L',              40.00, 25.00,  5, 3),
(8,  '7501000000008', 'Jab�n de ba�o',         25.00, 15.00,  5, 3),
(9,  '7501000000009', 'Detergente 500g',       55.00, 35.00,  5, 3),
(10, '7501000000010', 'Pan Blanco',            40.00, 25.00, 10, 4),
(11, '7501000000011', 'Pan de Molde',          45.00, 28.00, 10, 4),
(12, '7501000000012', 'At�n en lata',          60.00, 40.00,  8, 5),
(13, '7501000000013', 'Sardinas en lata',      45.00, 28.00,  8, 5),
(14, '7501000000014', 'Frijoles en lata',      50.00, 32.00,  8, 5),
(15, '7501000000015', 'Caf� molido 250g',      90.00, 60.00,  5, 1);

-- ============================================================
-- STOCK POR PV
-- ============================================================
INSERT IGNORE INTO stock_punto_venta (producto_id, punto_venta_id, stock) VALUES
-- PV Centro (1)
(1, 1, 50), (2, 1, 40), (3, 1, 25), (4, 1, 30), (5, 1, 30),
(6, 1, 20), (7, 1, 15), (8, 1, 20), (9, 1, 10), (10, 1, 25),
(11, 1, 20), (12, 1, 15), (13, 1, 15), (14, 1, 12), (15, 1, 10),
-- PV Vedado (2)
(1, 2, 40), (2, 2, 30), (3, 2, 20), (4, 2, 25), (5, 2, 25),
(6, 2, 15), (7, 2, 10), (8, 2, 15), (9, 2, 8),  (10, 2, 20),
(11, 2, 15), (12, 2, 12), (13, 2, 10), (14, 2, 8),  (15, 2, 8),
-- PV Playa (3)
(1, 3, 30), (2, 3, 25), (3, 3, 15), (4, 3, 20), (5, 3, 20),
(6, 3, 10), (7, 3, 8),  (8, 3, 10), (9, 3, 5),  (10, 3, 15),
(11, 3, 10), (12, 3, 8),  (13, 3, 8),  (14, 3, 6),  (15, 3, 5);

-- ============================================================
-- TASAS DE CAMBIO INICIALES
-- ============================================================
INSERT IGNORE INTO tasas_cambio (divisa_id, tasa, origen, fuente) VALUES
(1, 320.0000, 'auto', 'El Toque'),
(2, 345.0000, 'auto', 'El Toque');

-- ============================================================
-- TURNO DE EJEMPLO (Juan - PV Centro)
-- ============================================================
INSERT IGNORE INTO turnos (id, punto_venta_id, usuario_id, monto_inicial, estado) VALUES
(1, 1, 4, 500.00, 'abierto');

-- ============================================================
-- VENTAS DE EJEMPLO
-- ============================================================
INSERT IGNORE INTO ventas (id, folio, turno_id, punto_venta_id, usuario_id, subtotal, total, moneda, estado) VALUES
(1, 'V-20260913-0001', 1, 1, 4, 50.00, 50.00, 'CUP', 'completada'),
(2, 'V-20260913-0002', 1, 1, 4, 40.00, 40.00, 'CUP', 'completada'),
(3, 'V-20260913-0003', 1, 1, 4, 75.00, 75.00, 'CUP', 'completada');

INSERT IGNORE INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES
(1, 1, 2, 25.00, 50.00),
(2, 4, 2, 20.00, 40.00),
(3, 1, 1, 25.00, 25.00),
(3, 5, 2, 18.00, 36.00),
(3, 2, 1, 15.00, 15.00);

INSERT IGNORE INTO pagos_venta (venta_id, metodo, monto, moneda) VALUES
(1, 'efectivo', 50.00, 'CUP'),
(2, 'efectivo', 40.00, 'CUP'),
(3, 'efectivo', 75.00, 'CUP');

-- ============================================================
-- ACTUALIZAR TOTALES DEL TURNO
-- ============================================================
UPDATE turnos SET total_ventas = 165.00 WHERE id = 1;

-- Nota: el instalador crea adem�s el administrador real en el paso 3