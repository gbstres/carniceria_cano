CREATE TABLE IF NOT EXISTS cc_ventas_pagos (
    id_sucursal INT NOT NULL,
    id_venta INT NOT NULL,
    tipo_pago INT NOT NULL,
    importe DECIMAL(12,2) NOT NULL DEFAULT 0,
    id_usuario INT NOT NULL,
    fecha_ingreso DATE NOT NULL,
    hora_ingreso TIME NOT NULL,
    id_usuario_act INT NULL,
    fecha_act DATE NULL,
    hora_act TIME NULL,
    PRIMARY KEY (id_sucursal, id_venta, tipo_pago),
    KEY idx_cc_ventas_pagos_venta (id_sucursal, id_venta),
    KEY idx_cc_ventas_pagos_tipo (id_sucursal, tipo_pago)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
