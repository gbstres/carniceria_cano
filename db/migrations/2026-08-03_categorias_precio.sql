SET @cc_categorias_precio_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cc_categorias'
      AND COLUMN_NAME = 'precio'
);

SET @cc_categorias_precio_sql = IF(
    @cc_categorias_precio_exists = 0,
    'ALTER TABLE cc_categorias ADD COLUMN precio DECIMAL(10,2) NULL DEFAULT NULL AFTER almacen',
    'SELECT 1'
);

PREPARE cc_categorias_precio_stmt FROM @cc_categorias_precio_sql;
EXECUTE cc_categorias_precio_stmt;
DEALLOCATE PREPARE cc_categorias_precio_stmt;
