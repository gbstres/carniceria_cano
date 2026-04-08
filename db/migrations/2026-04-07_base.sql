-- Migracion base para sucursales
-- Aplica cambios de estructura compatibles con el codigo actual sin borrar informacion.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS cc_sync_queue (
    id_sync INT NOT NULL AUTO_INCREMENT,
    id_sucursal INT NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    action_type VARCHAR(20) NOT NULL,
    record_keys LONGTEXT NOT NULL,
    payload LONGTEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    error_message TEXT NULL,
    attempts INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    PRIMARY KEY (id_sync),
    KEY idx_sync_status (status),
    KEY idx_sync_sucursal (id_sucursal),
    KEY idx_sync_entity (entity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE cc_productos
    ADD COLUMN IF NOT EXISTS id_usuario_act INT(5) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS fecha_act DATE NULL,
    ADD COLUMN IF NOT EXISTS hora_act TIME NULL;

ALTER TABLE cc_pagos_clientes
    ADD COLUMN IF NOT EXISTS estatus INT(2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS id_cierre INT(5) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS id_usuario_act INT(5) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS fecha_act DATE NULL,
    ADD COLUMN IF NOT EXISTS hora_act TIME NULL;

ALTER TABLE cc_gastos
    ADD COLUMN IF NOT EXISTS estatus INT(2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS id_cierre INT(5) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS id_usuario_act INT(5) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS fecha_act DATE NULL,
    ADD COLUMN IF NOT EXISTS hora_act TIME NULL;

ALTER TABLE cc_det_compras
    ADD COLUMN IF NOT EXISTS folio_externo VARCHAR(30) NULL;

ALTER TABLE cc_compras
    ADD COLUMN IF NOT EXISTS clave_externa VARCHAR(30) NULL;

ALTER TABLE cc_cierre
    ADD COLUMN IF NOT EXISTS id_usuario_act INT(5) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS fecha_act INT(11) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS hora_act INT(11) NOT NULL DEFAULT 0;

ALTER TABLE cc_cierre_clientes
    ADD COLUMN IF NOT EXISTS id_usuario_act INT(5) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS fecha_act DATE NULL,
    ADD COLUMN IF NOT EXISTS hora_act TIME NULL;

COMMIT;
