-- Inserta encabezados faltantes en cc_det_ventas para ventas existentes en cc_ventas de manera segura
INSERT IGNORE INTO cc_det_ventas (id_sucursal, id_venta, estatus, id_cliente, pagado, id_usuario, fecha_ingreso, hora_ingreso)
SELECT v.id_sucursal, v.id_venta, 1, 0, 0, v.id_usuario, MIN(v.fecha_ingreso), MIN(v.hora_ingreso)
FROM cc_ventas v
WHERE NOT EXISTS (
    SELECT 1 FROM cc_det_ventas dv 
    WHERE dv.id_sucursal = v.id_sucursal AND dv.id_venta = v.id_venta
)
GROUP BY v.id_sucursal, v.id_venta;
