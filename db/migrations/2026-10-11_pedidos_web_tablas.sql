-- Migración: Creación de tablas base para tienda en línea y sincronización de pedidos web

CREATE TABLE IF NOT EXISTS `cc_web_clientes` (
  `id_cliente_web` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(150) NOT NULL,
  `telefono` VARCHAR(40) NOT NULL,
  `email` VARCHAR(120) NOT NULL DEFAULT '',
  `verificado` INT NOT NULL DEFAULT 0,
  `fecha_registro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_cliente_web`),
  UNIQUE KEY `idx_telefono` (`telefono`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cc_web_direcciones` (
  `id_direccion` INT NOT NULL AUTO_INCREMENT,
  `id_cliente_web` INT NOT NULL,
  `alias` VARCHAR(50) NOT NULL DEFAULT 'Principal',
  `direccion_completa` VARCHAR(255) NOT NULL,
  `referencias_ubicacion` TEXT NULL,
  `latitud` DECIMAL(10,8) NULL,
  `longitud` DECIMAL(11,8) NULL,
  `distancia_km` DECIMAL(5,2) NULL,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_direccion`),
  KEY `idx_cliente_web` (`id_cliente_web`),
  CONSTRAINT `fk_web_dir_cliente` FOREIGN KEY (`id_cliente_web`)
    REFERENCES `cc_web_clientes` (`id_cliente_web`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cc_pedidos_web` (
  `id_pedido` INT NOT NULL AUTO_INCREMENT,
  `id_cliente_web` INT NULL,
  `id_direccion` INT NULL,
  `id_sucursal` INT NOT NULL DEFAULT 1,
  `cliente_nombre` VARCHAR(150) NOT NULL,
  `cliente_telefono` VARCHAR(40) NOT NULL,
  `cliente_email` VARCHAR(120) NOT NULL DEFAULT '',
  `tipo_entrega` VARCHAR(20) NOT NULL DEFAULT 'recoger',
  `direccion_entrega` VARCHAR(255) NOT NULL DEFAULT '',
  `referencias_ubicacion` TEXT NULL,
  `latitud` DECIMAL(10,8) NULL,
  `longitud` DECIMAL(11,8) NULL,
  `distancia_km` DECIMAL(5,2) NULL,
  `metodo_pago` VARCHAR(30) NOT NULL DEFAULT 'efectivo',
  `monto_pago_efectivo` VARCHAR(50) NULL,
  `costo_envio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `notas` TEXT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `estatus` VARCHAR(30) NOT NULL DEFAULT 'nuevo',
  `id_venta` INT NULL,
  `id_repartidor` INT NULL,
  `fecha_entrega` DATETIME NULL,
  `fecha_ingreso` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_pedido`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cc_det_pedidos_web` (
  `id_detalle` INT NOT NULL AUTO_INCREMENT,
  `id_pedido` INT NOT NULL,
  `codigo` VARCHAR(40) NOT NULL,
  `descripcion` VARCHAR(180) NOT NULL,
  `precio_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `comentario` TEXT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `idx_pedido` (`id_pedido`),
  CONSTRAINT `fk_det_pedido_web` FOREIGN KEY (`id_pedido`)
    REFERENCES `cc_pedidos_web` (`id_pedido`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `cc_pedidos_web`
  ADD COLUMN IF NOT EXISTS `id_cliente_web` INT NULL,
  ADD COLUMN IF NOT EXISTS `id_direccion` INT NULL,
  ADD COLUMN IF NOT EXISTS `id_sucursal` INT NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `referencias_ubicacion` TEXT NULL,
  ADD COLUMN IF NOT EXISTS `latitud` DECIMAL(10,8) NULL,
  ADD COLUMN IF NOT EXISTS `longitud` DECIMAL(11,8) NULL,
  ADD COLUMN IF NOT EXISTS `distancia_km` DECIMAL(5,2) NULL,
  ADD COLUMN IF NOT EXISTS `monto_pago_efectivo` VARCHAR(50) NULL,
  ADD COLUMN IF NOT EXISTS `costo_envio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `id_venta` INT NULL,
  ADD COLUMN IF NOT EXISTS `id_repartidor` INT NULL,
  ADD COLUMN IF NOT EXISTS `fecha_entrega` DATETIME NULL;

ALTER TABLE `cc_det_pedidos_web`
  ADD COLUMN IF NOT EXISTS `comentario` TEXT NULL;
