-- Agrega clasificacion mayoreo/menudeo por producto.
-- 0 = menudeo, 1 = mayoreo.

ALTER TABLE cc_productos
    ADD COLUMN IF NOT EXISTS mayoreo TINYINT(1) NOT NULL DEFAULT 0 AFTER id_categoria;

UPDATE cc_productos
SET mayoreo = 0
WHERE id_sucursal IN (1, 2);
