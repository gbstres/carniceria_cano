-- Ajusta instalaciones que ya crearon las tablas de activos antes de habilitar sincronización.

SET @tiene_sucursal_bitacora = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cc_activos_bitacora' AND COLUMN_NAME = 'id_sucursal'
);
SET @sql = IF(@tiene_sucursal_bitacora = 0,
    'ALTER TABLE cc_activos_bitacora ADD COLUMN id_sucursal INT NOT NULL DEFAULT 0 AFTER id_bitacora',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE cc_activos_bitacora b
INNER JOIN cc_activos a ON a.id_activo = b.id_activo
SET b.id_sucursal = a.id_sucursal
WHERE b.id_sucursal = 0;

SET @pk_activos = (
    SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cc_activos' AND INDEX_NAME = 'PRIMARY'
);
SET @sql = IF(@pk_activos <> 'id_activo,id_sucursal',
    'ALTER TABLE cc_activos DROP PRIMARY KEY, ADD PRIMARY KEY (id_activo,id_sucursal)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @pk_bitacora = (
    SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cc_activos_bitacora' AND INDEX_NAME = 'PRIMARY'
);
SET @sql = IF(@pk_bitacora <> 'id_bitacora,id_sucursal',
    'ALTER TABLE cc_activos_bitacora DROP PRIMARY KEY, ADD PRIMARY KEY (id_bitacora,id_sucursal)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_bitacora = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cc_activos_bitacora' AND INDEX_NAME = 'idx_bitacora_sucursal_activo'
);
SET @sql = IF(@idx_bitacora = 0,
    'ALTER TABLE cc_activos_bitacora ADD KEY idx_bitacora_sucursal_activo (id_sucursal,id_activo,fecha_evento,hora_evento)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
