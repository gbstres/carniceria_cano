-- Tabla para fotos de productos
-- Compatible con mantenimientos/producto_fotos.php y storefront_logic.php

CREATE TABLE IF NOT EXISTS cc_productos_fotos (
    id_foto INT NOT NULL AUTO_INCREMENT,
    id_sucursal INT(3) NOT NULL,
    codigo VARCHAR(30) NOT NULL,
    ruta_small VARCHAR(255) NOT NULL,
    ruta_large VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255) DEFAULT NULL,
    orden INT NOT NULL DEFAULT 1,
    es_principal TINYINT(1) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    id_usuario INT(5) NOT NULL DEFAULT 0,
    fecha_ingreso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_foto),
    KEY idx_productos_fotos_codigo (id_sucursal, codigo),
    KEY idx_productos_fotos_principal (id_sucursal, codigo, es_principal),
    KEY idx_productos_fotos_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
