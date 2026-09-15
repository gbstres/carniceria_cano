-- Migración: Historial de stock por cierre de día
CREATE TABLE IF NOT EXISTS `cc_cierre_stock` (
    `id_sucursal` INT(3) NOT NULL,
    `id_cierre` INT(5) NOT NULL,
    `tipo` VARCHAR(20) NOT NULL,
    `codigo` VARCHAR(20) NOT NULL,
    `descripcion` VARCHAR(150) NOT NULL,
    `id_categoria` INT(10) DEFAULT 0,
    `desc_categoria` VARCHAR(150) DEFAULT '',
    `centraliza` VARCHAR(50) DEFAULT '',
    `stock` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `precio_compra` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `fecha_ingreso` DATE NOT NULL,
    `hora_ingreso` TIME NOT NULL,
    `id_usuario` INT(5) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_sucursal`, `id_cierre`, `tipo`, `codigo`),
    KEY `idx_cierre_stock_fecha` (`id_sucursal`, `fecha_ingreso`),
    KEY `idx_cierre_stock_cierre` (`id_sucursal`, `id_cierre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
