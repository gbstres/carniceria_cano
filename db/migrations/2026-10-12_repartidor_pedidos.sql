-- Migración: Soporte para asignación y entrega de pedidos a repartidores en cc_pedidos_web

-- Asegurar existencia previa de la tabla cc_pedidos_web
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

ALTER TABLE `cc_pedidos_web` 
  ADD COLUMN IF NOT EXISTS `id_repartidor` INT NULL,
  ADD COLUMN IF NOT EXISTS `fecha_entrega` DATETIME NULL;
