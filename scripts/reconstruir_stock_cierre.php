<?php
/**
 * Script para Reconstruir y Regenerar Stock a partir de un Cierre Específico
 * 
 * Uso desde línea de comandos (CLI):
 *   C:\xampp\php\php.exe scripts/reconstruir_stock_cierre.php [ID_SUCURSAL] [ID_CIERRE]
 * 
 * Uso desde el navegador:
 *   http://localhost/carniceriacano/scripts/reconstruir_stock_cierre.php?id_sucursal=3&id_cierre=627
 */

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../functions/config.php";

$id_sucursal = 3;
$id_cierre = 0;

if (PHP_SAPI === 'cli') {
    global $argv;
    if (isset($argv[1]) && is_numeric($argv[1])) {
        $id_sucursal = (int) $argv[1];
    }
    if (isset($argv[2]) && is_numeric($argv[2])) {
        $id_cierre = (int) $argv[2];
    }
} else {
    if (isset($_GET['id_sucursal']) && is_numeric($_GET['id_sucursal'])) {
        $id_sucursal = (int) $_GET['id_sucursal'];
    }
    if (isset($_GET['id_cierre']) && is_numeric($_GET['id_cierre'])) {
        $id_cierre = (int) $_GET['id_cierre'];
    }
}

// Si no se proporcionó id_cierre, buscar el último cierre registrado para la sucursal
if ($id_cierre <= 0) {
    $resCierre = mysqli_query($link, "SELECT id_cierre, fecha_ingreso, hora_ingreso FROM cc_cierre WHERE id_sucursal = $id_sucursal ORDER BY id_cierre DESC LIMIT 1");
    $rowCierre = mysqli_fetch_assoc($resCierre);
    if ($rowCierre) {
        $id_cierre = (int) $rowCierre['id_cierre'];
        $fecha_cierre = $rowCierre['fecha_ingreso'];
        $hora_cierre = $rowCierre['hora_ingreso'] ?? '00:00:00';
    } else {
        die("Error: No se encontraron cierres para la sucursal $id_sucursal\n");
    }
} else {
    $resCierre = mysqli_query($link, "SELECT fecha_ingreso, hora_ingreso FROM cc_cierre WHERE id_sucursal = $id_sucursal AND id_cierre = $id_cierre LIMIT 1");
    $rowCierre = mysqli_fetch_assoc($resCierre);
    $fecha_cierre = $rowCierre['fecha_ingreso'] ?? date('Y-m-d');
    $hora_cierre = $rowCierre['hora_ingreso'] ?? '00:00:00';
}

echo "=========================================================\n";
echo " RECONSTRUYENDO INVENTARIO DESDE CIERRE ID $id_cierre (SUCURSAL $id_sucursal)\n";
echo " Fecha y Hora de Cierre: $fecha_cierre $hora_cierre\n";
echo "=========================================================\n\n";

// 1. Recalcular Stock de Productos (centralizar_almacen = 1)
$sqlProd = "
UPDATE cc_productos p
INNER JOIN cc_cierre_stock cs 
    ON p.id_sucursal = cs.id_sucursal 
   AND p.codigo = cs.codigo COLLATE utf8_spanish_ci 
   AND cs.id_cierre = $id_cierre 
   AND cs.tipo = 'PRODUCTO'
LEFT JOIN (
    SELECT codigo, SUM(cantidad) AS cant_vendida
    FROM cc_ventas
    WHERE id_sucursal = $id_sucursal 
      AND TIMESTAMP(fecha_ingreso, hora_ingreso) > TIMESTAMP('$fecha_cierre', '$hora_cierre')
      AND estatus <> 2
    GROUP BY codigo
) v ON p.codigo = v.codigo COLLATE utf8_spanish_ci
LEFT JOIN (
    SELECT codigo, SUM(cantidad) AS cant_comprada
    FROM cc_compras
    WHERE id_sucursal = $id_sucursal 
      AND TIMESTAMP(fecha_ingreso, hora_ingreso) > TIMESTAMP('$fecha_cierre', '$hora_cierre')
      AND estatus <> 2
    GROUP BY codigo
) c ON p.codigo = c.codigo COLLATE utf8_spanish_ci
LEFT JOIN (
    SELECT codigo, SUM(cantidad) AS cant_entrada
    FROM cc_entradas
    WHERE id_sucursal = $id_sucursal 
      AND TIMESTAMP(fecha_ingreso, hora_ingreso) > TIMESTAMP('$fecha_cierre', '$hora_cierre')
      AND estatus <> 2
    GROUP BY codigo
) e ON p.codigo = e.codigo COLLATE utf8_spanish_ci
SET p.almacen = ROUND(cs.stock + COALESCE(c.cant_comprada, 0) + COALESCE(e.cant_entrada, 0) - COALESCE(v.cant_vendida, 0), 3)
WHERE p.id_sucursal = $id_sucursal;
";

$updProd = mysqli_query($link, $sqlProd);
if (!$updProd) {
    die("Error al actualizar productos: " . mysqli_error($link) . "\n");
}
echo "[OK] Productos actualizados correctamente.\n";

// 2. Recalcular Stock de Categorías Centralizadas (centralizar_almacen = 2)
$sqlCat = "
UPDATE cc_categorias cat
INNER JOIN cc_cierre_stock cs 
    ON cat.id_sucursal = cs.id_sucursal 
   AND CAST(cat.id_categoria AS CHAR) = cs.codigo COLLATE utf8_spanish_ci 
   AND cs.id_cierre = $id_cierre 
   AND cs.tipo = 'CATEGORIA'
LEFT JOIN (
    SELECT p.id_categoria, SUM(v.cantidad) AS cant_vendida
    FROM cc_ventas v
    INNER JOIN cc_productos p ON v.id_sucursal = p.id_sucursal AND v.codigo = p.codigo COLLATE utf8_spanish_ci
    WHERE v.id_sucursal = $id_sucursal 
      AND TIMESTAMP(v.fecha_ingreso, v.hora_ingreso) > TIMESTAMP('$fecha_cierre', '$hora_cierre')
      AND v.estatus <> 2
      AND p.centralizar_almacen = 2
    GROUP BY p.id_categoria
) v ON cat.id_categoria = v.id_categoria
LEFT JOIN (
    SELECT p.id_categoria, SUM(c.cantidad) AS cant_comprada
    FROM cc_compras c
    INNER JOIN cc_productos p ON c.id_sucursal = p.id_sucursal AND c.codigo = p.codigo COLLATE utf8_spanish_ci
    WHERE c.id_sucursal = $id_sucursal 
      AND TIMESTAMP(c.fecha_ingreso, c.hora_ingreso) > TIMESTAMP('$fecha_cierre', '$hora_cierre')
      AND c.estatus <> 2
      AND p.centralizar_almacen = 2
    GROUP BY p.id_categoria
) c ON cat.id_categoria = c.id_categoria
SET cat.almacen = ROUND(cs.stock + COALESCE(c.cant_comprada, 0) - COALESCE(v.cant_vendida, 0), 3)
WHERE cat.id_sucursal = $id_sucursal;
";

$updCat = mysqli_query($link, $sqlCat);
if (!$updCat) {
    die("Error al actualizar categorías: " . mysqli_error($link) . "\n");
}
echo "[OK] Categorías centralizadas actualizadas correctamente.\n\n";

echo "=========================================================\n";
echo " RESUMEN DE STOCK RECONSTRUIDO (SUCURSAL $id_sucursal)\n";
echo "=========================================================\n";

$resResumenProd = mysqli_query($link, "SELECT codigo, descripcion, almacen FROM cc_productos WHERE id_sucursal = $id_sucursal AND almacen > 0 ORDER BY codigo");
while ($r = mysqli_fetch_assoc($resResumenProd)) {
    echo sprintf("  PRODUCTO %s - %-25s : %10.3f kg\n", $r['codigo'], mb_substr($r['descripcion'], 0, 25), (float) $r['almacen']);
}

$resResumenCat = mysqli_query($link, "SELECT id_categoria, desc_categoria, almacen FROM cc_categorias WHERE id_sucursal = $id_sucursal AND almacen > 0 ORDER BY id_categoria");
while ($rc = mysqli_fetch_assoc($resResumenCat)) {
    echo sprintf("  CATEGORIA ID %d - %-25s : %10.3f kg\n", $rc['id_categoria'], mb_substr($rc['desc_categoria'], 0, 25), (float) $rc['almacen']);
}

echo "=========================================================\n";
