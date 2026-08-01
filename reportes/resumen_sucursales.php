<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $returnTo = $_SERVER['REQUEST_URI'] ?? '/reportes/resumen_sucursales.php';
    header("location: ../login/login.php?return_to=" . rawurlencode($returnTo));
    exit;
}

require_once "../functions/config.php";
date_default_timezone_set("America/Mexico_City");

if (!tienePermiso('ver')) {
    http_response_code(403);
    exit('No tienes permiso para consultar este reporte.');
}

function reporteMatrizFecha($value, $fallback)
{
    $value = trim((string) $value);
    $fecha = DateTime::createFromFormat('Y-m-d', $value);
    return ($fecha && $fecha->format('Y-m-d') === $value) ? $value : $fallback;
}

$hoy = date('Y-m-d');
$fecha1 = reporteMatrizFecha($_GET['fecha1'] ?? $hoy, $hoy);
$fecha2 = reporteMatrizFecha($_GET['fecha2'] ?? $hoy, $hoy);
if ($fecha1 > $fecha2) {
    [$fecha1, $fecha2] = [$fecha2, $fecha1];
}

$detalleTipo = isset($_GET['detalle']) ? (string) $_GET['detalle'] : 'resumen';
if (!in_array($detalleTipo, ['resumen', 'categorias', 'productos'], true)) {
    $detalleTipo = 'resumen';
}

function reporteMatrizConsultar(mysqli $link, string $sql)
{
    $resultado = mysqli_query($link, $sql);
    if (!$resultado) {
        throw new RuntimeException(mysqli_error($link));
    }
    return $resultado;
}

function reporteMatrizIdSucursal(mysqli $link)
{
    $resultado = reporteMatrizConsultar($link, "
        SELECT clave
        FROM cc_claves
        WHERE nombre_clave = 'MATRIZ_ID_SUCURSAL'
        ORDER BY orden, clave
        LIMIT 1
    ");
    $row = mysqli_fetch_assoc($resultado);
    $idMatriz = (int) ($row['clave'] ?? 0);

    if ($idMatriz <= 0) {
        $resultado = reporteMatrizConsultar($link, "
            SELECT id_sucursal
            FROM cc_sucursales
            WHERE activo = 1
              AND UPPER(TRIM(desc_sucursal)) = 'MATRIZ'
            ORDER BY id_sucursal
            LIMIT 1
        ");
        $row = mysqli_fetch_assoc($resultado);
        $idMatriz = (int) ($row['id_sucursal'] ?? 0);
    }

    if ($idMatriz <= 0) {
        throw new RuntimeException('No está configurada la sucursal MATRIZ.');
    }
    return $idMatriz;
}

function reporteMatrizCargarPorSucursal(mysqli $link, string $sql, array &$datosSucursales, array $campos)
{
    $resultado = reporteMatrizConsultar($link, $sql);
    while ($row = mysqli_fetch_assoc($resultado)) {
        $idSucursal = (int) $row['id_sucursal'];
        if (!isset($datosSucursales[$idSucursal])) {
            continue;
        }
        foreach ($campos as $campo) {
            $datosSucursales[$idSucursal][$campo] = (float) ($row[$campo] ?? 0);
        }
    }
}

$errorReporte = '';
$idSucursalMatriz = 0;
$nombreMatriz = 'MATRIZ';
$clientes = [];
$sucursalesVinculadas = [];
$nombresSucursales = [];
$datosSucursales = [];
$detalleRows = [];

try {
    $idSucursalMatriz = reporteMatrizIdSucursal($link);
    $rowMatriz = mysqli_fetch_assoc(reporteMatrizConsultar($link, "
        SELECT desc_sucursal
        FROM cc_sucursales
        WHERE id_sucursal = $idSucursalMatriz
        LIMIT 1
    "));
    if ($rowMatriz) {
        $nombreMatriz = (string) $rowMatriz['desc_sucursal'];
    }

    $resultadoClientes = reporteMatrizConsultar($link, "
        SELECT
            c.id_cliente,
            TRIM(CONCAT_WS(' ', c.nombre, c.apellido_paterno, c.apellido_materno)) AS nombre_cliente,
            c.clave_proveedor,
            MIN(s.id_sucursal) AS id_sucursal,
            GROUP_CONCAT(DISTINCT s.desc_sucursal ORDER BY s.desc_sucursal SEPARATOR ', ') AS desc_sucursal
        FROM cc_clientes c
        LEFT JOIN cc_proveedores p
            ON p.clave_cliente = c.clave_proveedor
           AND p.clave_cliente <> ''
           AND p.activo = 1
        LEFT JOIN cc_sucursales s
            ON s.id_sucursal = p.id_sucursal
           AND s.activo = 1
           AND UPPER(s.desc_sucursal) NOT LIKE '%PRUEBAS%'
        WHERE c.id_sucursal = $idSucursalMatriz
          AND c.activo = 1
        GROUP BY
            c.id_cliente,
            c.nombre,
            c.apellido_paterno,
            c.apellido_materno,
            c.clave_proveedor
        ORDER BY nombre_cliente, c.id_cliente
    ");

    while ($row = mysqli_fetch_assoc($resultadoClientes)) {
        $idCliente = (int) $row['id_cliente'];
        $idSucursal = $row['id_sucursal'] === null ? null : (int) $row['id_sucursal'];
        $clientes[$idCliente] = [
            'id_cliente' => $idCliente,
            'nombre' => $row['nombre_cliente'],
            'clave_proveedor' => $row['clave_proveedor'],
            'id_sucursal' => $idSucursal,
            'desc_sucursal' => $row['desc_sucursal'],
            'ventas' => 0,
            'ventas_compra' => 0,
            'ganancia_ventas' => 0,
            'cantidad_ventas' => 0,
        ];

        if ($idSucursal !== null) {
            $sucursalesVinculadas[$idSucursal] = $idSucursal;
            $datosSucursales[$idSucursal] = [
                'stock_productos' => 0,
                'valor_productos' => 0,
                'stock_categorias' => 0,
                'valor_categorias' => 0,
                'ventas_sucursal' => 0,
                'ventas_sucursal_compra' => 0,
                'cantidad_sucursal' => 0,
                'ganancia_sucursal' => 0,
                'compras' => 0,
            ];
        }
    }

    $fecha1Sql = mysqli_real_escape_string($link, $fecha1);
    $fecha2Sql = mysqli_real_escape_string($link, $fecha2);

    $resultadoVentas = reporteMatrizConsultar($link, "
        SELECT
            dv.id_cliente,
            COALESCE(SUM(ROUND(v.cantidad * v.precio_venta, 2)), 0) AS ventas,
            COALESCE(SUM(ROUND(v.cantidad * v.precio_compra, 2)), 0) AS ventas_compra,
            COALESCE(SUM(v.cantidad), 0) AS cantidad_ventas
        FROM cc_det_ventas dv
        INNER JOIN cc_ventas v
            ON v.id_sucursal = dv.id_sucursal
           AND v.id_venta = dv.id_venta
        WHERE dv.id_sucursal = $idSucursalMatriz
          AND dv.fecha_ingreso BETWEEN '$fecha1Sql' AND '$fecha2Sql'
          AND dv.estatus IN (1, 3)
          AND v.estatus <> 2
        GROUP BY dv.id_cliente
    ");
    while ($row = mysqli_fetch_assoc($resultadoVentas)) {
        $idCliente = (int) $row['id_cliente'];
        if ($idCliente === 0 && !isset($clientes[0])) {
            $clientes[0] = [
                'id_cliente' => 0,
                'nombre' => 'OTROS CLIENTES',
                'clave_proveedor' => '',
                'id_sucursal' => null,
                'desc_sucursal' => null,
                'ventas' => 0,
                'ventas_compra' => 0,
                'ganancia_ventas' => 0,
                'cantidad_ventas' => 0,
            ];
        }
        if (isset($clientes[$idCliente])) {
            $clientes[$idCliente]['ventas'] = (float) $row['ventas'];
            $clientes[$idCliente]['ventas_compra'] = (float) $row['ventas_compra'];
            $clientes[$idCliente]['ganancia_ventas'] = (float) $row['ventas'] - (float) $row['ventas_compra'];
            $clientes[$idCliente]['cantidad_ventas'] = (float) $row['cantidad_ventas'];
        }
    }

    $clientes = array_filter($clientes, function ($cliente) {
        return abs((float) $cliente['ventas']) > 0.00001;
    });

    $sucursalesVinculadas = [];
    $nombresSucursales = [];
    $datosSucursales = [];
    foreach ($clientes as $cliente) {
        if ($cliente['id_sucursal'] === null) {
            continue;
        }
        $idSucursal = (int) $cliente['id_sucursal'];
        $sucursalesVinculadas[$idSucursal] = $idSucursal;
        $nombresSucursales[$idSucursal] = $cliente['desc_sucursal'];
        $datosSucursales[$idSucursal] = [
            'stock_productos' => 0,
            'valor_productos' => 0,
            'stock_categorias' => 0,
            'valor_categorias' => 0,
            'ventas_sucursal' => 0,
            'ventas_sucursal_compra' => 0,
            'cantidad_sucursal' => 0,
            'ganancia_sucursal' => 0,
            'compras' => 0,
        ];
    }

    if (!empty($sucursalesVinculadas)) {
        $listaSucursales = implode(',', array_map('intval', $sucursalesVinculadas));

        reporteMatrizCargarPorSucursal($link, "
            SELECT
                p.id_sucursal,
                COALESCE(SUM(p.almacen), 0) AS stock_productos,
                COALESCE(SUM(
                    CASE
                        WHEN p.almacen < 0 THEN 0
                        ELSE ROUND(p.almacen * COALESCE(eq.precio_compra_origen, p.precio_compra, 0), 2)
                    END
                ), 0) AS valor_productos
            FROM cc_productos p
            LEFT JOIN (
                SELECT
                    e.id_sucursal,
                    e.codigo_destino,
                    AVG(po.precio_compra) AS precio_compra_origen
                FROM cc_equivalencias_productos e
                INNER JOIN cc_productos po
                    ON po.id_sucursal = e.id_sucursal
                   AND po.codigo = e.codigo_origen
                WHERE e.activo = 1
                GROUP BY e.id_sucursal, e.codigo_destino
            ) eq
                ON eq.id_sucursal = p.id_sucursal
               AND eq.codigo_destino = p.codigo
            WHERE p.id_sucursal IN ($listaSucursales)
              AND p.almacen <> 0
            GROUP BY p.id_sucursal
        ", $datosSucursales, ['stock_productos', 'valor_productos']);

        reporteMatrizCargarPorSucursal($link, "
            SELECT
                c.id_sucursal,
                COALESCE(SUM(c.almacen), 0) AS stock_categorias,
                COALESCE(SUM(
                    CASE
                        WHEN c.almacen < 0 THEN 0
                        ELSE ROUND(c.almacen * COALESCE(pc.precio_compra, 0), 2)
                    END
                ), 0) AS valor_categorias
            FROM cc_categorias c
            LEFT JOIN (
                SELECT id_sucursal, id_categoria, AVG(precio_compra) AS precio_compra
                FROM cc_productos
                WHERE centralizar_almacen = 2
                GROUP BY id_sucursal, id_categoria
            ) pc
                ON pc.id_sucursal = c.id_sucursal
               AND pc.id_categoria = c.id_categoria
            WHERE c.id_sucursal IN ($listaSucursales)
              AND c.almacen <> 0
            GROUP BY c.id_sucursal
        ", $datosSucursales, ['stock_categorias', 'valor_categorias']);

        reporteMatrizCargarPorSucursal($link, "
            SELECT
                dv.id_sucursal,
                COALESCE(SUM(ROUND(v.cantidad * v.precio_venta, 2)), 0) AS ventas_sucursal,
                COALESCE(SUM(ROUND(v.cantidad * v.precio_compra, 2)), 0) AS ventas_sucursal_compra,
                COALESCE(SUM(v.cantidad), 0) AS cantidad_sucursal,
                COALESCE(SUM(ROUND((v.precio_venta - v.precio_compra) * v.cantidad, 2)), 0) AS ganancia_sucursal
            FROM cc_det_ventas dv
            INNER JOIN cc_ventas v
                ON v.id_sucursal = dv.id_sucursal
               AND v.id_venta = dv.id_venta
            WHERE dv.id_sucursal IN ($listaSucursales)
              AND dv.fecha_ingreso BETWEEN '$fecha1Sql' AND '$fecha2Sql'
              AND dv.estatus IN (1, 3)
              AND v.estatus <> 2
            GROUP BY dv.id_sucursal
        ", $datosSucursales, ['ventas_sucursal', 'ventas_sucursal_compra', 'cantidad_sucursal', 'ganancia_sucursal']);

        reporteMatrizCargarPorSucursal($link, "
            SELECT
                dc.id_sucursal,
                COALESCE(SUM(ROUND(c.cantidad * c.precio_compra, 2)), 0) AS compras
            FROM cc_det_compras dc
            INNER JOIN cc_compras c
                ON c.id_sucursal = dc.id_sucursal
               AND c.id_compra = dc.id_compra
            WHERE dc.id_sucursal IN ($listaSucursales)
              AND dc.fecha_ingreso BETWEEN '$fecha1Sql' AND '$fecha2Sql'
              AND dc.estatus IN (1, 3)
              AND c.estatus <> 2
            GROUP BY dc.id_sucursal
        ", $datosSucursales, ['compras']);
    }

    $sqlRelacionCliente = "
        SELECT
            c.id_cliente,
            MIN(s.id_sucursal) AS id_sucursal
        FROM cc_clientes c
        LEFT JOIN cc_proveedores p
            ON p.clave_cliente = c.clave_proveedor
           AND p.clave_cliente <> ''
           AND p.activo = 1
        LEFT JOIN cc_sucursales s
            ON s.id_sucursal = p.id_sucursal
           AND s.activo = 1
           AND UPPER(s.desc_sucursal) NOT LIKE '%PRUEBAS%'
        WHERE c.id_sucursal = $idSucursalMatriz
          AND c.activo = 1
        GROUP BY c.id_cliente
    ";

    if ($detalleTipo === 'categorias') {
        $resultadoDetalle = reporteMatrizConsultar($link, "
            SELECT
                vm.id_cliente,
                cli.nombre_cliente,
                rel.id_sucursal,
                suc.desc_sucursal,
                vm.id_categoria,
                vm.desc_categoria,
                cat.almacen AS stock,
                pc.precio_compra,
                CASE
                    WHEN cat.almacen IS NULL THEN NULL
                    WHEN cat.almacen < 0 THEN 0
                    ELSE ROUND(cat.almacen * COALESCE(pc.precio_compra, 0), 2)
                END AS valor_stock,
                vm.ventas,
                ventas_sucursal.ventas AS ventas_sucursal,
                ventas_sucursal.ventas_compra AS ventas_sucursal_compra,
                ventas_sucursal.cantidad AS cantidad_sucursal,
                ventas_sucursal.ganancia AS ganancia_sucursal,
                compras.compras
            FROM (
                SELECT
                    dv.id_cliente,
                    pm.id_categoria,
                    cm.desc_categoria,
                    SUM(ROUND(v.cantidad * v.precio_venta, 2)) AS ventas
                FROM cc_det_ventas dv
                INNER JOIN cc_ventas v
                    ON v.id_sucursal = dv.id_sucursal
                   AND v.id_venta = dv.id_venta
                INNER JOIN cc_productos pm
                    ON pm.id_sucursal = v.id_sucursal
                   AND pm.codigo = v.codigo
                LEFT JOIN cc_categorias cm
                    ON cm.id_sucursal = pm.id_sucursal
                   AND cm.id_categoria = pm.id_categoria
                WHERE dv.id_sucursal = $idSucursalMatriz
                  AND dv.fecha_ingreso BETWEEN '$fecha1Sql' AND '$fecha2Sql'
                  AND dv.estatus IN (1, 3)
                  AND v.estatus <> 2
                GROUP BY dv.id_cliente, pm.id_categoria, cm.desc_categoria
            ) vm
            INNER JOIN (
                SELECT
                    id_cliente,
                    TRIM(CONCAT_WS(' ', nombre, apellido_paterno, apellido_materno)) AS nombre_cliente
                FROM cc_clientes
                WHERE id_sucursal = $idSucursalMatriz
                  AND activo = 1
            ) cli
                ON cli.id_cliente = vm.id_cliente
            LEFT JOIN ($sqlRelacionCliente) rel
                ON rel.id_cliente = vm.id_cliente
            LEFT JOIN cc_sucursales suc
                ON suc.id_sucursal = rel.id_sucursal
            LEFT JOIN cc_categorias cat
                ON cat.id_sucursal = rel.id_sucursal
               AND cat.id_categoria = vm.id_categoria
            LEFT JOIN (
                SELECT id_sucursal, id_categoria, AVG(precio_compra) AS precio_compra
                FROM cc_productos
                WHERE centralizar_almacen = 2
                GROUP BY id_sucursal, id_categoria
            ) pc
                ON pc.id_sucursal = rel.id_sucursal
               AND pc.id_categoria = vm.id_categoria
            LEFT JOIN (
                SELECT
                    dv.id_sucursal,
                    ps.id_categoria,
                    SUM(ROUND(v.cantidad * v.precio_venta, 2)) AS ventas,
                    SUM(ROUND(v.cantidad * v.precio_compra, 2)) AS ventas_compra,
                    SUM(v.cantidad) AS cantidad,
                    SUM(ROUND((v.precio_venta - v.precio_compra) * v.cantidad, 2)) AS ganancia
                FROM cc_det_ventas dv
                INNER JOIN cc_ventas v
                    ON v.id_sucursal = dv.id_sucursal
                   AND v.id_venta = dv.id_venta
                INNER JOIN cc_productos ps
                    ON ps.id_sucursal = v.id_sucursal
                   AND ps.codigo = v.codigo
                WHERE dv.fecha_ingreso BETWEEN '$fecha1Sql' AND '$fecha2Sql'
                  AND dv.estatus IN (1, 3)
                  AND v.estatus <> 2
                GROUP BY dv.id_sucursal, ps.id_categoria
            ) ventas_sucursal
                ON ventas_sucursal.id_sucursal = rel.id_sucursal
               AND ventas_sucursal.id_categoria = vm.id_categoria
            LEFT JOIN (
                SELECT
                    dc.id_sucursal,
                    ps.id_categoria,
                    SUM(ROUND(c.cantidad * c.precio_compra, 2)) AS compras
                FROM cc_det_compras dc
                INNER JOIN cc_compras c
                    ON c.id_sucursal = dc.id_sucursal
                   AND c.id_compra = dc.id_compra
                INNER JOIN cc_productos ps
                    ON ps.id_sucursal = c.id_sucursal
                   AND ps.codigo = c.codigo
                WHERE dc.fecha_ingreso BETWEEN '$fecha1Sql' AND '$fecha2Sql'
                  AND dc.estatus IN (1, 3)
                  AND c.estatus <> 2
                GROUP BY dc.id_sucursal, ps.id_categoria
            ) compras
                ON compras.id_sucursal = rel.id_sucursal
               AND compras.id_categoria = vm.id_categoria
            ORDER BY cli.nombre_cliente, vm.desc_categoria
        ");
        while ($row = mysqli_fetch_assoc($resultadoDetalle)) {
            $detalleRows[] = $row;
        }
    } elseif ($detalleTipo === 'productos') {
        $resultadoDetalle = reporteMatrizConsultar($link, "
            SELECT
                vm.id_cliente,
                cli.nombre_cliente,
                rel.id_sucursal,
                suc.desc_sucursal,
                vm.codigo,
                vm.descripcion,
                vm.desc_categoria,
                ps.almacen AS stock,
                COALESCE(eq.precio_compra_origen, ps.precio_compra) AS precio_compra,
                CASE
                    WHEN ps.almacen IS NULL THEN NULL
                    WHEN ps.almacen < 0 THEN 0
                    ELSE ROUND(ps.almacen * COALESCE(eq.precio_compra_origen, ps.precio_compra, 0), 2)
                END AS valor_stock,
                vm.ventas,
                ventas_sucursal.ventas AS ventas_sucursal,
                ventas_sucursal.ventas_compra AS ventas_sucursal_compra,
                ventas_sucursal.cantidad AS cantidad_sucursal,
                ventas_sucursal.ganancia AS ganancia_sucursal,
                compras.compras
            FROM (
                SELECT
                    dv.id_cliente,
                    v.codigo,
                    pm.descripcion,
                    cm.desc_categoria,
                    SUM(ROUND(v.cantidad * v.precio_venta, 2)) AS ventas
                FROM cc_det_ventas dv
                INNER JOIN cc_ventas v
                    ON v.id_sucursal = dv.id_sucursal
                   AND v.id_venta = dv.id_venta
                LEFT JOIN cc_productos pm
                    ON pm.id_sucursal = v.id_sucursal
                   AND pm.codigo = v.codigo
                LEFT JOIN cc_categorias cm
                    ON cm.id_sucursal = pm.id_sucursal
                   AND cm.id_categoria = pm.id_categoria
                WHERE dv.id_sucursal = $idSucursalMatriz
                  AND dv.fecha_ingreso BETWEEN '$fecha1Sql' AND '$fecha2Sql'
                  AND dv.estatus IN (1, 3)
                  AND v.estatus <> 2
                GROUP BY dv.id_cliente, v.codigo, pm.descripcion, cm.desc_categoria
            ) vm
            INNER JOIN (
                SELECT
                    id_cliente,
                    TRIM(CONCAT_WS(' ', nombre, apellido_paterno, apellido_materno)) AS nombre_cliente
                FROM cc_clientes
                WHERE id_sucursal = $idSucursalMatriz
                  AND activo = 1
            ) cli
                ON cli.id_cliente = vm.id_cliente
            LEFT JOIN ($sqlRelacionCliente) rel
                ON rel.id_cliente = vm.id_cliente
            LEFT JOIN cc_sucursales suc
                ON suc.id_sucursal = rel.id_sucursal
            LEFT JOIN cc_productos ps
                ON ps.id_sucursal = rel.id_sucursal
               AND ps.codigo = vm.codigo
            LEFT JOIN (
                SELECT
                    e.id_sucursal,
                    e.codigo_destino,
                    AVG(po.precio_compra) AS precio_compra_origen
                FROM cc_equivalencias_productos e
                INNER JOIN cc_productos po
                    ON po.id_sucursal = e.id_sucursal
                   AND po.codigo = e.codigo_origen
                WHERE e.activo = 1
                GROUP BY e.id_sucursal, e.codigo_destino
            ) eq
                ON eq.id_sucursal = ps.id_sucursal
               AND eq.codigo_destino = ps.codigo
            LEFT JOIN (
                SELECT
                    dv.id_sucursal,
                    v.codigo,
                    SUM(ROUND(v.cantidad * v.precio_venta, 2)) AS ventas,
                    SUM(ROUND(v.cantidad * v.precio_compra, 2)) AS ventas_compra,
                    SUM(v.cantidad) AS cantidad,
                    SUM(ROUND((v.precio_venta - v.precio_compra) * v.cantidad, 2)) AS ganancia
                FROM cc_det_ventas dv
                INNER JOIN cc_ventas v
                    ON v.id_sucursal = dv.id_sucursal
                   AND v.id_venta = dv.id_venta
                WHERE dv.fecha_ingreso BETWEEN '$fecha1Sql' AND '$fecha2Sql'
                  AND dv.estatus IN (1, 3)
                  AND v.estatus <> 2
                GROUP BY dv.id_sucursal, v.codigo
            ) ventas_sucursal
                ON ventas_sucursal.id_sucursal = rel.id_sucursal
               AND ventas_sucursal.codigo = vm.codigo
            LEFT JOIN (
                SELECT
                    dc.id_sucursal,
                    c.codigo,
                    SUM(ROUND(c.cantidad * c.precio_compra, 2)) AS compras
                FROM cc_det_compras dc
                INNER JOIN cc_compras c
                    ON c.id_sucursal = dc.id_sucursal
                   AND c.id_compra = dc.id_compra
                WHERE dc.fecha_ingreso BETWEEN '$fecha1Sql' AND '$fecha2Sql'
                  AND dc.estatus IN (1, 3)
                  AND c.estatus <> 2
                GROUP BY dc.id_sucursal, c.codigo
            ) compras
                ON compras.id_sucursal = rel.id_sucursal
               AND compras.codigo = vm.codigo
            ORDER BY cli.nombre_cliente, vm.desc_categoria, vm.descripcion
        ");
        while ($row = mysqli_fetch_assoc($resultadoDetalle)) {
            $detalleRows[] = $row;
        }
    }
} catch (RuntimeException $exception) {
    $errorReporte = 'No fue posible generar el reporte: ' . $exception->getMessage();
}

$totales = [
    'stock_productos' => 0,
    'valor_productos' => 0,
    'stock_categorias' => 0,
    'valor_categorias' => 0,
    'ventas' => 0,
    'ventas_compra' => 0,
    'ganancia_ventas' => 0,
    'cantidad_ventas' => 0,
    'ventas_sucursal' => 0,
    'ventas_sucursal_compra' => 0,
    'cantidad_sucursal' => 0,
    'ganancia_sucursal' => 0,
    'compras' => 0,
];
foreach ($clientes as $cliente) {
    $totales['ventas'] += $cliente['ventas'];
    $totales['ventas_compra'] += $cliente['ventas_compra'];
    $totales['ganancia_ventas'] += $cliente['ganancia_ventas'];
    $totales['cantidad_ventas'] += $cliente['cantidad_ventas'];
}
foreach ($datosSucursales as $datos) {
    foreach (['stock_productos', 'valor_productos', 'stock_categorias', 'valor_categorias', 'ventas_sucursal', 'ventas_sucursal_compra', 'cantidad_sucursal', 'ganancia_sucursal', 'compras'] as $campo) {
        $totales[$campo] += $datos[$campo];
    }
}

function reporteMatrizNumeroNullable($value, $decimales)
{
    return $value === null ? '—' : number_format((float) $value, $decimales);
}
?>
<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Ventas de matriz por cliente">
        <link rel="shortcut icon" href="../img/logo_1.png">
        <title>Ventas de matriz por cliente</title>
        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <style>
            @import "../css/bootstrap.css";
            .metric-card {
                border-left: 4px solid #0d6efd;
            }
            #informacion_sucursales .encabezado-sucursal,
            #informacion_sucursales thead tr:nth-child(2) th:nth-child(6),
            #informacion_sucursales tbody td:nth-child(6),
            #informacion_sucursales tfoot th:nth-child(6) {
                box-shadow: inset 3px 0 0 #6c757d;
            }
        </style>
        <link href="../css/navbar.css" rel="stylesheet">
        <link href="../css/jquery.dataTables.min.css" rel="stylesheet">
    </head>
    <body>
        <main>
            <div class="container">
                <?php require_once "../components/nav.php"; ?>
                <div class="bg-light p-4 rounded">
                    <h1 class="text-center mb-2">Ventas de matriz por cliente</h1>
                    <p class="text-center text-muted mb-4">
                        Matriz: <?php echo htmlspecialchars($nombreMatriz, ENT_QUOTES, 'UTF-8'); ?>
                    </p>

                    <form method="get" class="card card-body mb-4">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="fecha1" class="form-label">Desde</label>
                                <input type="date" class="form-control" id="fecha1" name="fecha1" value="<?php echo htmlspecialchars($fecha1, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="fecha2" class="form-label">Hasta</label>
                                <input type="date" class="form-control" id="fecha2" name="fecha2" value="<?php echo htmlspecialchars($fecha2, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="detalle" class="form-label">Nivel de detalle</label>
                                <select class="form-select" id="detalle" name="detalle">
                                    <option value="resumen" <?php echo $detalleTipo === 'resumen' ? 'selected' : ''; ?>>Solo resumen</option>
                                    <option value="categorias" <?php echo $detalleTipo === 'categorias' ? 'selected' : ''; ?>>Por categorías</option>
                                    <option value="productos" <?php echo $detalleTipo === 'productos' ? 'selected' : ''; ?>>Por productos</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">Generar reporte</button>
                            </div>
                        </div>
                    </form>

                    <?php if ($errorReporte !== ''): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($errorReporte, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php elseif (empty($clientes)): ?>
                        <div class="alert alert-warning">No se encontraron clientes con ventas en el rango seleccionado.</div>
                    <?php else: ?>
                        <div class="row g-3 mb-4">
                            <div class="col-md">
                                <div class="card metric-card h-100">
                                    <div class="card-body">
                                        <div class="text-muted">Valor total de stock relacionado</div>
                                        <div class="fs-4 fw-bold">$<?php echo number_format($totales['valor_productos'] + $totales['valor_categorias'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md">
                                <div class="card metric-card h-100">
                                    <div class="card-body">
                                        <div class="text-muted">Ventas de matriz</div>
                                        <div class="fs-4 fw-bold">$<?php echo number_format($totales['ventas'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md">
                                <div class="card metric-card h-100">
                                    <div class="card-body">
                                        <div class="text-muted">Venta sucursal (imp. V)</div>
                                        <div class="fs-4 fw-bold">$<?php echo number_format($totales['ventas_sucursal'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md">
                                <div class="card metric-card h-100">
                                    <div class="card-body">
                                        <div class="text-muted">Venta sucursal (imp. C)</div>
                                        <div class="fs-4 fw-bold">$<?php echo number_format($totales['ventas_sucursal_compra'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md">
                                <div class="card metric-card h-100">
                                    <div class="card-body">
                                        <div class="text-muted">Compras de sucursales relacionadas</div>
                                        <div class="fs-4 fw-bold">$<?php echo number_format($totales['compras'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive mt-4">
                            <table id="informacion_sucursales" class="display" style="width:100%">
                                <thead>
                                    <tr>
                                        <th colspan="5" class="text-center">Matriz</th>
                                        <th colspan="7" class="text-center encabezado-sucursal">Sucursal</th>
                                    </tr>
                                    <tr>
                                        <th>Cliente en matriz</th>
                                        <th>Cantidad</th>
                                        <th>Ventas (imp. V)</th>
                                        <th>Ventas (imp. C)</th>
                                        <th>Ganancia</th>
                                        <th>Sucursal relacionada</th>
                                        <th>Valor stock</th>
                                        <th>Cantidad</th>
                                        <th>Venta (imp. V)</th>
                                        <th>Venta (imp. C)</th>
                                        <th>Ganancia</th>
                                        <th>Compras sucursal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <?php $datos = $cliente['id_sucursal'] !== null ? ($datosSucursales[(int) $cliente['id_sucursal']] ?? null) : null; ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cliente['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end"><?php echo number_format($cliente['cantidad_ventas'], 3); ?></td>
                                            <td class="text-end"><?php echo number_format($cliente['ventas'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($cliente['ventas_compra'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($cliente['ganancia_ventas'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($cliente['desc_sucursal'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end"><?php echo $datos === null ? '—' : number_format($datos['valor_productos'] + $datos['valor_categorias'], 2); ?></td>
                                            <td class="text-end"><?php echo $datos === null ? '—' : number_format($datos['cantidad_sucursal'], 3); ?></td>
                                            <td class="text-end"><?php echo $datos === null ? '—' : number_format($datos['ventas_sucursal'], 2); ?></td>
                                            <td class="text-end"><?php echo $datos === null ? '—' : number_format($datos['ventas_sucursal_compra'], 2); ?></td>
                                            <td class="text-end"><?php echo $datos === null ? '—' : number_format($datos['ganancia_sucursal'], 2); ?></td>
                                            <td class="text-end"><?php echo $datos === null ? '—' : number_format($datos['compras'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>Total</th>
                                        <th class="text-end"><?php echo number_format($totales['cantidad_ventas'], 3); ?></th>
                                        <th class="text-end"><?php echo number_format($totales['ventas'], 2); ?></th>
                                        <th class="text-end"><?php echo number_format($totales['ventas_compra'], 2); ?></th>
                                        <th class="text-end"><?php echo number_format($totales['ganancia_ventas'], 2); ?></th>
                                        <th></th>
                                        <th class="text-end"><?php echo number_format($totales['valor_productos'] + $totales['valor_categorias'], 2); ?></th>
                                        <th class="text-end"><?php echo number_format($totales['cantidad_sucursal'], 3); ?></th>
                                        <th class="text-end"><?php echo number_format($totales['ventas_sucursal'], 2); ?></th>
                                        <th class="text-end"><?php echo number_format($totales['ventas_sucursal_compra'], 2); ?></th>
                                        <th class="text-end"><?php echo number_format($totales['ganancia_sucursal'], 2); ?></th>
                                        <th class="text-end"><?php echo number_format($totales['compras'], 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <?php if ($detalleTipo !== 'resumen'): ?>
                            <hr class="my-4">
                            <h2 class="h4">Detalle por <?php echo $detalleTipo === 'categorias' ? 'categorías' : 'productos'; ?></h2>
                            <p class="text-muted">
                                El detalle compara las ventas de MATRIZ con la información disponible de la sucursal relacionada.
                            </p>
                            <div class="table-responsive">
                                <table id="detalle_sucursales" class="display" style="width:100%">
                                    <thead>
                                        <?php if ($detalleTipo === 'categorias'): ?>
                                            <tr>
                                                <th>Cliente</th>
                                                <th>Sucursal relacionada</th>
                                                <th>Categoría</th>
                                                <th>Stock</th>
                                                <th>Precio promedio</th>
                                                <th>Valor stock</th>
                                                <th>Ventas matriz</th>
                                                <th>Cantidad sucursal</th>
                                                <th>Venta (imp. V)</th>
                                                <th>Venta (imp. C)</th>
                                                <th>Ganancia</th>
                                                <th>Compras sucursal</th>
                                            </tr>
                                        <?php else: ?>
                                            <tr>
                                                <th>Cliente</th>
                                                <th>Sucursal relacionada</th>
                                                <th>Código</th>
                                                <th>Producto</th>
                                                <th>Categoría</th>
                                                <th>Stock</th>
                                                <th>Precio compra</th>
                                                <th>Valor stock</th>
                                                <th>Ventas matriz</th>
                                                <th>Cantidad sucursal</th>
                                                <th>Venta (imp. V)</th>
                                                <th>Venta (imp. C)</th>
                                                <th>Ganancia</th>
                                                <th>Compras sucursal</th>
                                            </tr>
                                        <?php endif; ?>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($detalleRows as $detalle): ?>
                                            <?php if ($detalleTipo === 'categorias'): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($detalle['nombre_cliente'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($detalle['desc_sucursal'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($detalle['desc_categoria'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td class="text-end"><?php echo reporteMatrizNumeroNullable($detalle['stock'], 3); ?></td>
                                                    <td class="text-end"><?php echo reporteMatrizNumeroNullable($detalle['precio_compra'], 2); ?></td>
                                                    <td class="text-end"><?php echo reporteMatrizNumeroNullable($detalle['valor_stock'], 2); ?></td>
                                                    <td class="text-end"><?php echo number_format((float) $detalle['ventas'], 2); ?></td>
                                                    <td class="text-end"><?php echo reporteMatrizNumeroNullable($detalle['cantidad_sucursal'], 3); ?></td>
                                                    <td class="text-end"><?php echo reporteMatrizNumeroNullable($detalle['ventas_sucursal'], 2); ?></td>
                                                    <td class="text-end"><?php echo reporteMatrizNumeroNullable($detalle['ventas_sucursal_compra'], 2); ?></td>
                                                    <td class="text-end"><?php echo reporteMatrizNumeroNullable($detalle['ganancia_sucursal'], 2); ?></td>
                                                    <td class="text-end"><?php echo reporteMatrizNumeroNullable($detalle['compras'], 2); ?></td>
                                                </tr>
                                            <?php else: ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($detalle['nombre_cliente'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($detalle['desc_sucursal'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($detalle['codigo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($detalle['descripcion'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($detalle['desc_categoria'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td class="text-end"><?php echo reporteMatrizNumeroNullable($detalle['stock'], 3); ?></td>
                                                    <td class="text-end"><?php echo reporteMatrizNumeroNullable($detalle['precio_compra'], 2); ?></td>
                                                    <td class="text-end"><?php echo reporteMatrizNumeroNullable($detalle['valor_stock'], 2); ?></td>
                                                    <td class="text-end"><?php echo number_format((float) $detalle['ventas'], 2); ?></td>
                                                    <td class="text-end"><?php echo reporteMatrizNumeroNullable($detalle['cantidad_sucursal'], 3); ?></td>
                                                    <td class="text-end"><?php echo reporteMatrizNumeroNullable($detalle['ventas_sucursal'], 2); ?></td>
                                                    <td class="text-end"><?php echo reporteMatrizNumeroNullable($detalle['ventas_sucursal_compra'], 2); ?></td>
                                                    <td class="text-end"><?php echo reporteMatrizNumeroNullable($detalle['ganancia_sucursal'], 2); ?></td>
                                                    <td class="text-end"><?php echo reporteMatrizNumeroNullable($detalle['compras'], 2); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
            $(document).ready(function () {
                $('#informacion_sucursales').DataTable({
                    paging: false,
                    info: false,
                    searching: true,
                    order: [[0, 'asc']],
                    language: {
                        emptyTable: 'No hay información',
                        search: 'Buscar:',
                        zeroRecords: 'Sin resultados'
                    }
                });
                $('#detalle_sucursales').DataTable({
                    pageLength: 50,
                    lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'Mostrar todo']],
                    order: [[0, 'asc']],
                    language: {
                        emptyTable: 'No hay información',
                        info: 'Mostrando _START_ a _END_ de _TOTAL_',
                        infoEmpty: 'Mostrando 0 a 0 de 0',
                        infoFiltered: '(filtrado de _MAX_)',
                        lengthMenu: 'Mostrar _MENU_',
                        search: 'Buscar:',
                        zeroRecords: 'Sin resultados',
                        paginate: {
                            first: 'Primero',
                            last: 'Último',
                            next: 'Siguiente',
                            previous: 'Anterior'
                        }
                    }
                });
            });
        </script>
    </body>
</html>
