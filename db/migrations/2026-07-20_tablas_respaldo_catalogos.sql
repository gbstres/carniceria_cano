SET @fecha = CURDATE();
SET @hora = CURTIME();

SET @next_id = (SELECT COALESCE(MAX(id), 0) + 1 FROM cc_tablas_respaldo);
INSERT INTO cc_tablas_respaldo
    (id, nombre_comun, nombre_tabla, secuencia, id_usuario, fecha_ingreso, hora_ingreso, id_usuario_act, fecha_act, hora_act)
SELECT
    @next_id, 'DERIVADOS', 'cc_derivados', 3, 1, @fecha, @hora, 0, @fecha, @hora
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM cc_tablas_respaldo WHERE nombre_tabla = 'cc_derivados'
);

SET @next_id = (SELECT COALESCE(MAX(id), 0) + 1 FROM cc_tablas_respaldo);
INSERT INTO cc_tablas_respaldo
    (id, nombre_comun, nombre_tabla, secuencia, id_usuario, fecha_ingreso, hora_ingreso, id_usuario_act, fecha_act, hora_act)
SELECT
    @next_id, 'EQUIVALENCIAS PRODUCTOS', 'cc_equivalencias_productos', 4, 1, @fecha, @hora, 0, @fecha, @hora
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM cc_tablas_respaldo WHERE nombre_tabla = 'cc_equivalencias_productos'
);
