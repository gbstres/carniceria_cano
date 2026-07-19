SET @schema_name = DATABASE();

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE cc_productos ADD COLUMN limite_stock DECIMAL(10,3) NOT NULL DEFAULT 5 AFTER almacen',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'cc_productos'
      AND COLUMN_NAME = 'limite_stock'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
