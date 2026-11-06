-- Migración para Recetas de Producción y Bandera para_venta
-- 1. Agregar columna para_venta a cc_productos (1 = A la venta en mostrador, 0 = Insumo interno / no vendible)
SET @dbname = DATABASE();
SET @tablename = "cc_productos";
SET @columnname = "para_venta";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_NAME = @tablename)
      AND (TABLE_SCHEMA = @dbname)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE cc_productos ADD COLUMN para_venta TINYINT(1) NOT NULL DEFAULT 1 AFTER activo;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 2. Crear tabla propia e independiente para Recetas de Producción (evita interferir con la venta en caja)
CREATE TABLE IF NOT EXISTS `cc_recetas_produccion` (
    `id_sucursal` INT NOT NULL,
    `codigo_p` VARCHAR(10) NOT NULL,
    `codigo_d` VARCHAR(10) NOT NULL,
    `porcentaje` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `id_usuario` INT NOT NULL,
    `fecha_ingreso` DATE NOT NULL,
    `hora_ingreso` TIME NOT NULL,
    `id_usuario_act` INT NOT NULL DEFAULT 0,
    `fecha_act` DATE NOT NULL,
    `hora_act` TIME NOT NULL,
    PRIMARY KEY (`id_sucursal`, `codigo_p`, `codigo_d`),
    KEY `idx_receta_insumo` (`id_sucursal`, `codigo_d`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

-- 3. Crear Categoría "INSUMOS / MATERIA PRIMA" (id_categoria = 10) en sucursales si no existe
INSERT INTO cc_categorias (id_sucursal, id_categoria, desc_categoria, mayoreo, almacen, precio, activo, fecha_ingreso, hora_ingreso, id_usuario, fecha_act, hora_act, id_usuario_act)
SELECT s.id_sucursal, 10, 'INSUMOS / MATERIA PRIMA', 0, 0, 0, 1, CURDATE(), CURTIME(), 1, CURDATE(), CURTIME(), 1
FROM cc_sucursales s
WHERE NOT EXISTS (
    SELECT 1 FROM cc_categorias c 
    WHERE c.id_sucursal = s.id_sucursal AND (c.id_categoria = 10 OR c.desc_categoria = 'INSUMOS / MATERIA PRIMA')
);

-- 4. Registrar producto REMOLACHA como insumo (no vendible en caja: para_venta = 0)
INSERT INTO cc_productos (id_sucursal, codigo, descripcion, precio_compra, precio_venta, almacen, limite_stock, id_categoria, mayoreo, centralizar_almacen, activo, para_venta, id_usuario, fecha_ingreso, hora_ingreso, fecha_act, hora_act, id_usuario_act)
SELECT s.id_sucursal, '9000001', 'REMOLACHA (INSUMO)', 0.00, 0.00, 0.000, 5.000, 10, 0, 1, 1, 0, 1, CURDATE(), CURTIME(), CURDATE(), CURTIME(), 1
FROM cc_sucursales s
WHERE NOT EXISTS (
    SELECT 1 FROM cc_productos p
    WHERE p.id_sucursal = s.id_sucursal AND p.codigo = '9000001'
);

-- 5. Asegurar que GRASA tenga para_venta = 0 si es solo insumo
UPDATE cc_productos SET para_venta = 0 WHERE codigo = '2351037';
