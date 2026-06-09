-- Agrega clasificacion mayoreo/menudeo por producto.
-- 0 = menudeo, 1 = mayoreo.

SET @cc_productos_mayoreo_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'cc_productos'
        AND COLUMN_NAME = 'mayoreo'
);

SET @cc_productos_mayoreo_sql = IF(
    @cc_productos_mayoreo_exists = 0,
    'ALTER TABLE cc_productos ADD COLUMN mayoreo TINYINT(1) NOT NULL DEFAULT 0 AFTER id_categoria',
    'SELECT 1'
);

PREPARE cc_productos_mayoreo_stmt FROM @cc_productos_mayoreo_sql;
EXECUTE cc_productos_mayoreo_stmt;
DEALLOCATE PREPARE cc_productos_mayoreo_stmt;

UPDATE cc_productos
SET mayoreo = 0
WHERE id_sucursal IN (1, 2);
