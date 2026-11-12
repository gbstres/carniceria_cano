-- Migración: Asegurar clave primaria compuesta en bitácora de stock para sincronización multi-sucursal
SET @pk_bitacora = (
    SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cc_bitacora_stock' AND INDEX_NAME = 'PRIMARY'
);
SET @sql = IF(@pk_bitacora <> 'id_bitacora,id_sucursal',
    'ALTER TABLE cc_bitacora_stock DROP PRIMARY KEY, ADD PRIMARY KEY (id_bitacora,id_sucursal)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
