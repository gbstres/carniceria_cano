-- =============================================================================
-- Migración: Actualización y alta de Tortillinas en sucursales 1, 2, 3 y 9
-- Basado en catálogo configurado en Sucursal 5
-- =============================================================================

-- SUCURSAL 1
INSERT INTO cc_productos (id_sucursal, codigo, descripcion, precio_compra, precio_venta, almacen, limite_stock, id_categoria, mayoreo, centralizar_almacen, activo, fecha_ingreso, hora_ingreso, id_usuario, fecha_act, hora_act, id_usuario_act)
VALUES 
(1, '2350115', 'TORTILLINA NATURAL', 32.00, 40.00, 0.000, 5.000, 8, 0, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(1, '2350118', 'TORTILLINA MANTEQUILLA', 42.00, 39.00, 0.000, 5.000, 8, 0, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(1, '2350119', 'TORTILLINA INTEGRAL', 32.00, 40.00, 0.000, 3.000, 8, 0, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(1, '2350120', 'TORTILLINA NOPAL', 32.00, 40.00, 0.000, 3.000, 8, 0, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(1, '2351060', 'TORTILLINA NATURAL M', 28.00, 32.00, 0.000, 5.000, 8, 1, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(1, '2351101', 'TORTILLINA MANTEQUILLA M', 28.00, 32.00, 0.000, 5.000, 8, 1, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(1, '2351102', 'TORTILLINA INTEGRAL M', 28.00, 32.00, 0.000, 5.000, 8, 1, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(1, '2351103', 'TORTILLINA NOPAL M', 28.00, 32.00, 0.000, 5.000, 8, 1, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    id_categoria = VALUES(id_categoria),
    fecha_act = CURRENT_DATE(),
    hora_act = CURRENT_TIME(),
    id_usuario_act = 1;

-- SUCURSAL 2
INSERT INTO cc_productos (id_sucursal, codigo, descripcion, precio_compra, precio_venta, almacen, limite_stock, id_categoria, mayoreo, centralizar_almacen, activo, fecha_ingreso, hora_ingreso, id_usuario, fecha_act, hora_act, id_usuario_act)
VALUES 
(2, '2350115', 'TORTILLINA NATURAL', 32.00, 40.00, 0.000, 5.000, 8, 0, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(2, '2350118', 'TORTILLINA MANTEQUILLA', 42.00, 39.00, 0.000, 5.000, 8, 0, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(2, '2350119', 'TORTILLINA INTEGRAL', 32.00, 40.00, 0.000, 3.000, 8, 0, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(2, '2350120', 'TORTILLINA NOPAL', 32.00, 40.00, 0.000, 3.000, 8, 0, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(2, '2351060', 'TORTILLINA NATURAL M', 28.00, 32.00, 0.000, 5.000, 8, 1, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(2, '2351101', 'TORTILLINA MANTEQUILLA M', 28.00, 32.00, 0.000, 5.000, 8, 1, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(2, '2351102', 'TORTILLINA INTEGRAL M', 28.00, 32.00, 0.000, 5.000, 8, 1, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(2, '2351103', 'TORTILLINA NOPAL M', 28.00, 32.00, 0.000, 5.000, 8, 1, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    id_categoria = VALUES(id_categoria),
    fecha_act = CURRENT_DATE(),
    hora_act = CURRENT_TIME(),
    id_usuario_act = 1;

-- SUCURSAL 3 (Categoría 9 = VARIOS)
INSERT INTO cc_productos (id_sucursal, codigo, descripcion, precio_compra, precio_venta, almacen, limite_stock, id_categoria, mayoreo, centralizar_almacen, activo, fecha_ingreso, hora_ingreso, id_usuario, fecha_act, hora_act, id_usuario_act)
VALUES 
(3, '2350115', 'TORTILLINA NATURAL', 32.00, 40.00, 0.000, 5.000, 9, 0, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(3, '2350118', 'TORTILLINA MANTEQUILLA', 42.00, 39.00, 0.000, 5.000, 9, 0, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(3, '2350119', 'TORTILLINA INTEGRAL', 32.00, 40.00, 0.000, 3.000, 9, 0, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(3, '2350120', 'TORTILLINA NOPAL', 32.00, 40.00, 0.000, 3.000, 9, 0, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(3, '2351060', 'TORTILLINA NATURAL M', 28.00, 32.00, 0.000, 5.000, 9, 1, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(3, '2351101', 'TORTILLINA MANTEQUILLA M', 28.00, 32.00, 0.000, 5.000, 9, 1, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(3, '2351102', 'TORTILLINA INTEGRAL M', 28.00, 32.00, 0.000, 5.000, 9, 1, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(3, '2351103', 'TORTILLINA NOPAL M', 28.00, 32.00, 0.000, 5.000, 9, 1, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    id_categoria = VALUES(id_categoria),
    fecha_act = CURRENT_DATE(),
    hora_act = CURRENT_TIME(),
    id_usuario_act = 1;

-- SUCURSAL 9
INSERT INTO cc_productos (id_sucursal, codigo, descripcion, precio_compra, precio_venta, almacen, limite_stock, id_categoria, mayoreo, centralizar_almacen, activo, fecha_ingreso, hora_ingreso, id_usuario, fecha_act, hora_act, id_usuario_act)
VALUES 
(9, '2350115', 'TORTILLINA NATURAL', 32.00, 40.00, 0.000, 5.000, 8, 0, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(9, '2350118', 'TORTILLINA MANTEQUILLA', 42.00, 39.00, 0.000, 5.000, 8, 0, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(9, '2350119', 'TORTILLINA INTEGRAL', 32.00, 40.00, 0.000, 3.000, 8, 0, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(9, '2350120', 'TORTILLINA NOPAL', 32.00, 40.00, 0.000, 3.000, 8, 0, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(9, '2351060', 'TORTILLINA NATURAL M', 28.00, 32.00, 0.000, 5.000, 8, 1, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(9, '2351101', 'TORTILLINA MANTEQUILLA M', 28.00, 32.00, 0.000, 5.000, 8, 1, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(9, '2351102', 'TORTILLINA INTEGRAL M', 28.00, 32.00, 0.000, 5.000, 8, 1, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1),
(9, '2351103', 'TORTILLINA NOPAL M', 28.00, 32.00, 0.000, 5.000, 8, 1, 1, 1, CURRENT_DATE(), CURRENT_TIME(), 1, CURRENT_DATE(), CURRENT_TIME(), 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    id_categoria = VALUES(id_categoria),
    fecha_act = CURRENT_DATE(),
    hora_act = CURRENT_TIME(),
    id_usuario_act = 1;
