-- Migración: Soporte para asignación y entrega de pedidos a repartidores en cc_pedidos_web
ALTER TABLE `cc_pedidos_web` 
  ADD COLUMN IF NOT EXISTS `id_repartidor` INT NULL,
  ADD COLUMN IF NOT EXISTS `fecha_entrega` DATETIME NULL;
