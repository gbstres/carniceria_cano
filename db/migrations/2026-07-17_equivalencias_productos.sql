-- Crea equivalencias de producto origen -> producto destino de inventario.
-- Se usa para que movimientos de mayoreo puedan afectar stock de menudeo.

CREATE TABLE IF NOT EXISTS cc_equivalencias_productos (
    id_sucursal INT NOT NULL,
    codigo_origen VARCHAR(10) NOT NULL,
    codigo_destino VARCHAR(10) NOT NULL,
    factor DECIMAL(10,4) NOT NULL DEFAULT 1,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    id_usuario INT NOT NULL DEFAULT 0,
    fecha_ingreso DATE NOT NULL,
    hora_ingreso TIME NOT NULL,
    id_usuario_act INT NOT NULL DEFAULT 0,
    fecha_act DATE NOT NULL,
    hora_act TIME NOT NULL,
    PRIMARY KEY (id_sucursal, codigo_origen),
    KEY idx_equivalencias_destino (id_sucursal, codigo_destino),
    KEY idx_equivalencias_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;
