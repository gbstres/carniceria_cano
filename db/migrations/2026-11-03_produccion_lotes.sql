-- Migración para el control de Producción de Derivados por Lotes
-- Permite transformar insumos (ej. Carne de res) en productos elaborados (ej. Longaniza)
-- sin recurrir a compras manuales falsas.

CREATE TABLE IF NOT EXISTS `cc_produccion` (
    `id_sucursal` INT NOT NULL,
    `id_produccion` INT NOT NULL,
    `codigo` VARCHAR(10) NOT NULL,
    `descripcion` VARCHAR(255) NOT NULL,
    `cantidad` DECIMAL(10,3) NOT NULL,
    `comentario` TEXT NULL,
    `estatus` TINYINT NOT NULL DEFAULT 1, -- 1: Procesado/Activo, 2: Cancelado, 3: En cierre
    `id_cierre` INT NOT NULL DEFAULT 0,
    `id_usuario` INT NOT NULL,
    `fecha_ingreso` DATE NOT NULL,
    `hora_ingreso` TIME NOT NULL,
    `id_usuario_act` INT NULL DEFAULT 0,
    `fecha_act` DATE NULL,
    `hora_act` TIME NULL,
    PRIMARY KEY (`id_sucursal`, `id_produccion`),
    KEY `idx_produccion_codigo` (`id_sucursal`, `codigo`),
    KEY `idx_produccion_fecha` (`id_sucursal`, `fecha_ingreso`),
    KEY `idx_produccion_cierre` (`id_sucursal`, `id_cierre`, `estatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

CREATE TABLE IF NOT EXISTS `cc_produccion_detalle` (
    `id_sucursal` INT NOT NULL,
    `id_produccion` INT NOT NULL,
    `id_renglon` INT NOT NULL,
    `codigo_insumo` VARCHAR(10) NOT NULL,
    `desc_insumo` VARCHAR(255) NOT NULL,
    `porcentaje_teorico` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `cantidad_utilizada` DECIMAL(10,3) NOT NULL,
    PRIMARY KEY (`id_sucursal`, `id_produccion`, `id_renglon`),
    KEY `idx_prod_det_insumo` (`id_sucursal`, `codigo_insumo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;
