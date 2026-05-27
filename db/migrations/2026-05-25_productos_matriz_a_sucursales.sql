-- Copia productos de MATRIZ (id_sucursal = 3) a sucursales Ecatepec y Olimpica.
-- Idempotente: solo inserta categorias y productos faltantes.

INSERT INTO cc_categorias (
    id_sucursal,
    id_categoria,
    desc_categoria,
    almacen,
    mayoreo,
    activo,
    id_usuario,
    fecha_ingreso,
    hora_ingreso,
    id_usuario_act,
    fecha_act,
    hora_act
)
SELECT
    destino.id_sucursal,
    origen.id_categoria,
    origen.desc_categoria,
    origen.almacen,
    origen.mayoreo,
    origen.activo,
    COALESCE(origen.id_usuario, 0),
    CASE
        WHEN origen.fecha_ingreso IS NULL OR origen.fecha_ingreso = '0000-00-00' THEN CURRENT_DATE()
        ELSE origen.fecha_ingreso
    END,
    CASE
        WHEN origen.hora_ingreso IS NULL OR origen.hora_ingreso = '00:00:00' THEN CURRENT_TIME()
        ELSE origen.hora_ingreso
    END,
    COALESCE(origen.id_usuario_act, origen.id_usuario, 0),
    CASE
        WHEN origen.fecha_act IS NULL OR origen.fecha_act = '0000-00-00' THEN CURRENT_DATE()
        ELSE origen.fecha_act
    END,
    CASE
        WHEN origen.hora_act IS NULL OR origen.hora_act = '00:00:00' THEN CURRENT_TIME()
        ELSE origen.hora_act
    END
FROM cc_categorias origen
INNER JOIN cc_sucursales destino
    ON destino.id_sucursal <> origen.id_sucursal
    AND (
        UPPER(destino.desc_sucursal) LIKE '%ECATEPEC%'
        OR UPPER(destino.desc_sucursal) LIKE '%OLIMP%'
    )
LEFT JOIN cc_categorias existente
    ON existente.id_sucursal = destino.id_sucursal
    AND existente.id_categoria = origen.id_categoria
WHERE origen.id_sucursal = 3
    AND existente.id_categoria IS NULL;

INSERT INTO cc_productos (
    codigo,
    id_sucursal,
    descripcion,
    precio_compra,
    precio_venta,
    almacen,
    id_categoria,
    centralizar_almacen,
    activo,
    id_usuario,
    fecha_ingreso,
    hora_ingreso,
    id_usuario_act,
    fecha_act,
    hora_act
)
SELECT
    origen.codigo,
    destino.id_sucursal,
    origen.descripcion,
    origen.precio_compra,
    origen.precio_venta,
    origen.almacen,
    origen.id_categoria,
    origen.centralizar_almacen,
    origen.activo,
    COALESCE(origen.id_usuario, 0),
    CASE
        WHEN origen.fecha_ingreso IS NULL OR origen.fecha_ingreso = '0000-00-00' THEN CURRENT_DATE()
        ELSE origen.fecha_ingreso
    END,
    CASE
        WHEN origen.hora_ingreso IS NULL OR origen.hora_ingreso = '00:00:00' THEN CURRENT_TIME()
        ELSE origen.hora_ingreso
    END,
    COALESCE(origen.id_usuario_act, origen.id_usuario, 0),
    CASE
        WHEN origen.fecha_act IS NULL OR origen.fecha_act = '0000-00-00' THEN CURRENT_DATE()
        ELSE origen.fecha_act
    END,
    CASE
        WHEN origen.hora_act IS NULL OR origen.hora_act = '00:00:00' THEN CURRENT_TIME()
        ELSE origen.hora_act
    END
FROM cc_productos origen
INNER JOIN cc_sucursales destino
    ON destino.id_sucursal <> origen.id_sucursal
    AND (
        UPPER(destino.desc_sucursal) LIKE '%ECATEPEC%'
        OR UPPER(destino.desc_sucursal) LIKE '%OLIMP%'
    )
INNER JOIN cc_categorias categoria_destino
    ON categoria_destino.id_sucursal = destino.id_sucursal
    AND categoria_destino.id_categoria = origen.id_categoria
LEFT JOIN cc_productos existente
    ON existente.id_sucursal = destino.id_sucursal
    AND existente.codigo = origen.codigo
WHERE origen.id_sucursal = 3
    AND existente.codigo IS NULL;
