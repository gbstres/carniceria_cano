-- Inventario de activos físicos y bitácora de fallas/mantenimientos.

CREATE TABLE IF NOT EXISTS cc_activos (
    id_activo INT NOT NULL AUTO_INCREMENT,
    id_sucursal INT NOT NULL,
    tipo VARCHAR(80) NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    identificador VARCHAR(100) NOT NULL DEFAULT '',
    estado ENUM('OPERATIVO','REQUIERE ATENCION','FUERA DE SERVICIO','BAJA') NOT NULL DEFAULT 'OPERATIVO',
    observaciones VARCHAR(500) NOT NULL DEFAULT '',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    id_usuario INT NOT NULL,
    fecha_ingreso DATE NOT NULL,
    hora_ingreso TIME NOT NULL,
    id_usuario_act INT DEFAULT NULL,
    fecha_act DATE DEFAULT NULL,
    hora_act TIME DEFAULT NULL,
    PRIMARY KEY (id_activo, id_sucursal),
    KEY idx_activos_sucursal (id_sucursal, activo),
    KEY idx_activos_tipo (tipo),
    KEY idx_activos_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

CREATE TABLE IF NOT EXISTS cc_activos_bitacora (
    id_bitacora INT NOT NULL AUTO_INCREMENT,
    id_sucursal INT NOT NULL,
    id_activo INT NOT NULL,
    tipo_evento ENUM('FALLA','MANTENIMIENTO','INSPECCION','REPARACION','OBSERVACION') NOT NULL,
    fecha_evento DATE NOT NULL,
    hora_evento TIME NOT NULL,
    detalle VARCHAR(1000) NOT NULL,
    estatus ENUM('PENDIENTE','EN PROCESO','RESUELTO') NOT NULL DEFAULT 'PENDIENTE',
    fecha_resolucion DATE DEFAULT NULL,
    hora_resolucion TIME DEFAULT NULL,
    solucion VARCHAR(1000) NOT NULL DEFAULT '',
    id_usuario INT NOT NULL,
    fecha_ingreso DATE NOT NULL,
    hora_ingreso TIME NOT NULL,
    id_usuario_act INT DEFAULT NULL,
    fecha_act DATE DEFAULT NULL,
    hora_act TIME DEFAULT NULL,
    PRIMARY KEY (id_bitacora, id_sucursal),
    KEY idx_bitacora_activo_fecha (id_sucursal, id_activo, fecha_evento, hora_evento),
    KEY idx_bitacora_estatus (estatus),
    KEY idx_bitacora_tipo (tipo_evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;
