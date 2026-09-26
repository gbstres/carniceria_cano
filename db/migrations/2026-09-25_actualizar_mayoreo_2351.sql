-- Actualización masiva a mayoreo para productos cuyos códigos inician con 2351
UPDATE cc_productos
SET mayoreo = 1,
    fecha_act = CURRENT_DATE(),
    hora_act = CURRENT_TIME()
WHERE codigo LIKE '2351%';
