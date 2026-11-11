-- Migración: Tabla de bitácora para registrar cambios de stock con motivo y usuario responsable
CREATE TABLE IF NOT EXISTS `cc_bitacora_stock` (
    `id_bitacora` INT(11) NOT NULL AUTO_INCREMENT,
    `id_sucursal` INT(3) NOT NULL,
    `tipo` VARCHAR(20) NOT NULL,
    `id_item` VARCHAR(20) NOT NULL,
    `descripcion` VARCHAR(150) NOT NULL,
    `stock_anterior` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `stock_nuevo` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `diferencia` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `motivo` TEXT NOT NULL,
    `id_usuario` INT(5) NOT NULL,
    `usuario` VARCHAR(100) NOT NULL DEFAULT '',
    `fecha` DATE NOT NULL,
    `hora` TIME NOT NULL,
    `origen` VARCHAR(100) NOT NULL DEFAULT 'MANUAL',
    PRIMARY KEY (`id_bitacora`),
    KEY `idx_bitacora_stock_sucursal` (`id_sucursal`, `fecha`),
    KEY `idx_bitacora_stock_item` (`tipo`, `id_item`),
    KEY `idx_bitacora_stock_usuario` (`id_usuario`),
    KEY `idx_bitacora_stock_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
