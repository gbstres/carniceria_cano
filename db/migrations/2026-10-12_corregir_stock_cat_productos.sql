-- Migración: Asegurar que los productos centralizados por categoría (CAT) no mantengan stock individual
UPDATE cc_productos 
SET almacen = 0.000, 
    fecha_act = CURRENT_DATE(), 
    hora_act = CURRENT_TIME() 
WHERE centralizar_almacen = 2 
  AND almacen <> 0;

DELETE FROM cc_cierre_stock 
WHERE tipo = 'PRODUCTO' 
  AND centraliza = 'CAT';
