-- Catálogo común para homologar categorías entre sucursales.
-- Los id_categoria locales no se modifican.

CREATE TABLE IF NOT EXISTS cc_categorias_globales (
    id_categoria_global INT NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    id_usuario INT NOT NULL DEFAULT 0,
    fecha_ingreso DATE NOT NULL,
    hora_ingreso TIME NOT NULL,
    id_usuario_act INT NOT NULL DEFAULT 0,
    fecha_act DATE NULL,
    hora_act TIME NULL,
    PRIMARY KEY (id_categoria_global),
    UNIQUE KEY uq_categorias_globales_nombre (nombre),
    KEY idx_categorias_globales_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

CREATE TABLE IF NOT EXISTS cc_categorias_homologacion (
    id_sucursal INT NOT NULL,
    id_categoria INT NOT NULL,
    id_categoria_global INT NOT NULL,
    id_usuario INT NOT NULL DEFAULT 0,
    fecha_ingreso DATE NOT NULL,
    hora_ingreso TIME NOT NULL,
    id_usuario_act INT NOT NULL DEFAULT 0,
    fecha_act DATE NULL,
    hora_act TIME NULL,
    PRIMARY KEY (id_sucursal, id_categoria),
    UNIQUE KEY uq_homologacion_global_sucursal (id_categoria_global, id_sucursal),
    KEY idx_categorias_homologacion_global (id_categoria_global)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

SET @cc_homologacion_unique_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cc_categorias_homologacion'
      AND INDEX_NAME = 'uq_homologacion_global_sucursal'
);
SET @cc_homologacion_unique_sql = IF(
    @cc_homologacion_unique_exists = 0,
    'ALTER TABLE cc_categorias_homologacion ADD UNIQUE KEY uq_homologacion_global_sucursal (id_categoria_global, id_sucursal)',
    'SELECT 1'
);
PREPARE cc_homologacion_unique_stmt FROM @cc_homologacion_unique_sql;
EXECUTE cc_homologacion_unique_stmt;
DEALLOCATE PREPARE cc_homologacion_unique_stmt;
