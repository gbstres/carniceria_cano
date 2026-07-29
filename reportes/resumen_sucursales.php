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

$fecha = isset($_GET['fecha']) ? trim((string) $_GET['fecha']) : date('Y-m-d');
$fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
    $fecha = date('Y-m-d');
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

function reporteMatrizCargarResumen(mysqli $link, string $sql, array &$resumen, array $campos)
{
    $resultado = reporteMatrizConsultar($link, $sql);
    while ($row = mysqli_fetch_assoc($resultado)) {
        $idSucursal = (int) $row['id_sucursal'];
        if (!isset($resumen[$idSucursal])) {
            continue;
        }
        foreach ($campos as $campo) {
            $resumen[$idSucursal][$campo] = (float) ($row[$campo] ?? 0);
        }
    }
}

$errorReporte = '';
$idSucursalMatriz = 0;
$nombreMatriz = 'MATRIZ';
$sucursales = [];
$resumen = [];
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

    $resultadoVinculos = reporteMatrizConsultar($link, "
        SELECT DISTINCT
            s.id_sucursal,
            s.desc_sucursal,
            c.id_cliente,
            TRIM(CONCAT_WS(' ', c.nombre, c.apellido_paterno, c.apellido_materno)) AS nombre_cliente,
            c.clave_proveedor
        FROM cc_clientes c
        INNER JOIN cc_proveedores p
            ON p.clave_cliente = c.clave_proveedor
           AND p.clave_cliente <> ''
           AND p.activo = 1
        INNER JOIN cc_sucursales s
            ON s.id_sucursal = p.id_sucursal
           AND s.activo = 1
           AND UPPER(s.desc_sucursal) NOT LIKE '%PRUEBAS%'
        WHERE c.id_sucursal = $idSucursalMatriz
          AND c.activo = 1
        ORDER BY s.desc_sucursal, c.id_cliente
    ");

    while ($row = mysqli_fetch_assoc($resultadoVinculos)) {
        $idSucursal = (int) $row['id_sucursal'];
        if (!isset($sucursales[$idSucursal])) {
            $sucursales[$idSucursal] = [
                'desc_sucursal' => $row['desc_sucursal'],
                'clientes' => [],
            ];
            $resumen[$idSucursal] = [
                'stock_productos' => 0,
                'valor_productos' => 0,
                'stock_categorias' => 0,
                'valor_categorias' => 0,
                'ventas' => 0,
                'compras' => 0,
            ];
        }
        $sucursales[$idSucursal]['clientes'][(int) $row['id_cliente']] = [
            'nombre' => $row['nombre_cliente'],
            'clave' => $row['clave_proveedor'],
        ];
    }

    if (!empty($sucursales)) {
        $listaSucursales = implode(',', array_map('intval', array_keys($sucursales)));
        $fechaSql = mysqli_real_escape_string($link, $fecha);
        $sqlRelacion = "
            SELECT DISTINCT
                p.id_sucursal AS id_sucursal,
                c.id_cliente
            FROM cc_clientes c
            INNER JOIN cc_proveedores p
                ON p.clave_cliente = c.clave_proveedor
               AND p.clave_cliente <> ''
               AND p.activo = 1
            INNER JOIN cc_sucursales s
                ON s.id_sucursal = p.id_sucursal
               AND s.activo = 1
               AND UPPER(s.desc_sucursal) NOT LIKE '%PRUEBAS%'
            WHERE c.id_sucursal = $idSucursalMatriz
              AND c.activo = 1
        ";

        reporteMatrizCargarResumen($link, "
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
        ", $resumen, ['stock_productos', 'valor_productos']);

        reporteMatrizCargarResumen($link, "
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
        ", $resumen, ['stock_categorias', 'valor_categorias']);

        reporteMatrizCargarResumen($link, "
            SELECT
                relacion.id_sucursal,
                COALESCE(SUM(ROUND(v.cantidad * v.precio_venta, 2)), 0) AS ventas
            FROM cc_det_ventas dv
            INNER JOIN cc_ventas v
                ON v.id_sucursal = dv.id_sucursal
               AND v.id_venta = dv.id_venta
            INNER JOIN ($sqlRelacion) relacion
                ON relacion.id_cliente = dv.id_cliente
            WHERE dv.id_sucursal = $idSucursalMatriz
              AND dv.fecha_ingreso = '$fechaSql'
              AND dv.estatus IN (1, 3)
              AND v.estatus <> 2
            GROUP BY relacion.id_sucursal
        ", $resumen, ['ventas']);

        reporteMatrizCargarResumen($link, "
            SELECT
                dc.id_sucursal,
                COALESCE(SUM(ROUND(c.cantidad * c.precio_compra, 2)), 0) AS compras
            FROM cc_det_compras dc
            INNER JOIN cc_compras c
                ON c.id_sucursal = dc.id_sucursal
               AND c.id_compra = dc.id_compra
            WHERE dc.id_sucursal IN ($listaSucursales)
              AND dc.fecha_ingreso = '$fechaSql'
              AND dc.estatus IN (1, 3)
              AND c.estatus <> 2
            GROUP BY dc.id_sucursal
        ", $resumen, ['compras']);

        if ($detalleTipo === 'categorias') {
            $resultadoDetalle = reporteMatrizConsultar($link, "
                SELECT
                    cat.id_sucursal,
                    cat.desc_categoria,
                    cat.almacen AS stock,
                    COALESCE(pc.precio_compra, 0) AS precio_compra,
                    CASE
                        WHEN cat.almacen < 0 THEN 0
                        ELSE ROUND(cat.almacen * COALESCE(pc.precio_compra, 0), 2)
                    END AS valor_stock,
                    COALESCE(vm.ventas, 0) AS ventas,
                    COALESCE(cs.compras, 0) AS compras
                FROM cc_categorias cat
                LEFT JOIN (
                    SELECT id_sucursal, id_categoria, AVG(precio_compra) AS precio_compra
                    FROM cc_productos
                    WHERE centralizar_almacen = 2
                    GROUP BY id_sucursal, id_categoria
                ) pc
                    ON pc.id_sucursal = cat.id_sucursal
                   AND pc.id_categoria = cat.id_categoria
                LEFT JOIN (
                    SELECT
                        relacion.id_sucursal,
                        pm.id_categoria,
                        SUM(ROUND(v.cantidad * v.precio_venta, 2)) AS ventas
                    FROM cc_det_ventas dv
                    INNER JOIN cc_ventas v
                        ON v.id_sucursal = dv.id_sucursal
                       AND v.id_venta = dv.id_venta
                    INNER JOIN cc_productos pm
                        ON pm.id_sucursal = v.id_sucursal
                       AND pm.codigo = v.codigo
                    INNER JOIN ($sqlRelacion) relacion
                        ON relacion.id_cliente = dv.id_cliente
                    WHERE dv.id_sucursal = $idSucursalMatriz
                      AND dv.fecha_ingreso = '$fechaSql'
                      AND dv.estatus IN (1, 3)
                      AND v.estatus <> 2
                    GROUP BY relacion.id_sucursal, pm.id_categoria
                ) vm
                    ON vm.id_sucursal = cat.id_sucursal
                   AND vm.id_categoria = cat.id_categoria
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
                    WHERE dc.id_sucursal IN ($listaSucursales)
                      AND dc.fecha_ingreso = '$fechaSql'
                      AND dc.estatus IN (1, 3)
                      AND c.estatus <> 2
                    GROUP BY dc.id_sucursal, ps.id_categoria
                ) cs
                    ON cs.id_sucursal = cat.id_sucursal
                   AND cs.id_categoria = cat.id_categoria
                WHERE cat.id_sucursal IN ($listaSucursales)
                  AND (cat.almacen <> 0 OR COALESCE(vm.ventas, 0) <> 0 OR COALESCE(cs.compras, 0) <> 0)
                ORDER BY cat.id_sucursal, cat.desc_categoria
            ");
            while ($row = mysqli_fetch_assoc($resultadoDetalle)) {
                $detalleRows[] = $row;
            }
        } elseif ($detalleTipo === 'productos') {
            $resultadoDetalle = reporteMatrizConsultar($link, "
                SELECT
                    p.id_sucursal,
                    p.codigo,
                    p.descripcion,
                    cat.desc_categoria,
                    p.almacen AS stock,
                    COALESCE(eq.precio_compra_origen, p.precio_compra, 0) AS precio_compra,
                    CASE
                        WHEN p.almacen < 0 THEN 0
                        ELSE ROUND(p.almacen * COALESCE(eq.precio_compra_origen, p.precio_compra, 0), 2)
                    END AS valor_stock,
                    COALESCE(vm.ventas, 0) AS ventas,
                    COALESCE(cs.compras, 0) AS compras
                FROM cc_productos p
                LEFT JOIN cc_categorias cat
                    ON cat.id_sucursal = p.id_sucursal
                   AND cat.id_categoria = p.id_categoria
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
                LEFT JOIN (
                    SELECT
                        relacion.id_sucursal,
                        v.codigo,
                        SUM(ROUND(v.cantidad * v.precio_venta, 2)) AS ventas
                    FROM cc_det_ventas dv
                    INNER JOIN cc_ventas v
                        ON v.id_sucursal = dv.id_sucursal
                       AND v.id_venta = dv.id_venta
                    INNER JOIN ($sqlRelacion) relacion
                        ON relacion.id_cliente = dv.id_cliente
                    WHERE dv.id_sucursal = $idSucursalMatriz
                      AND dv.fecha_ingreso = '$fechaSql'
                      AND dv.estatus IN (1, 3)
                      AND v.estatus <> 2
                    GROUP BY relacion.id_sucursal, v.codigo
                ) vm
                    ON vm.id_sucursal = p.id_sucursal
                   AND vm.codigo = p.codigo
                LEFT JOIN (
                    SELECT
                        dc.id_sucursal,
                        c.codigo,
                        SUM(ROUND(c.cantidad * c.precio_compra, 2)) AS compras
                    FROM cc_det_compras dc
                    INNER JOIN cc_compras c
                        ON c.id_sucursal = dc.id_sucursal
                       AND c.id_compra = dc.id_compra
                    WHERE dc.id_sucursal IN ($listaSucursales)
                      AND dc.fecha_ingreso = '$fechaSql'
                      AND dc.estatus IN (1, 3)
                      AND c.estatus <> 2
                    GROUP BY dc.id_sucursal, c.codigo
                ) cs
                    ON cs.id_sucursal = p.id_sucursal
                   AND cs.codigo = p.codigo
                WHERE p.id_sucursal IN ($listaSucursales)
                  AND (p.almacen <> 0 OR COALESCE(vm.ventas, 0) <> 0 OR COALESCE(cs.compras, 0) <> 0)
                ORDER BY p.id_sucursal, cat.desc_categoria, p.descripcion
            ");
            while ($row = mysqli_fetch_assoc($resultadoDetalle)) {
                $detalleRows[] = $row;
            }
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
    'compras' => 0,
];
foreach ($resumen as $datos) {
    foreach ($totales as $campo => $valor) {
        $totales[$campo] += $datos[$campo];
    }
}
?>
<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Ventas de matriz a sucursales cliente">
        <link rel="shortcut icon" href="../img/logo_1.png">
        <title>Ventas de matriz a sucursales</title>
        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <style>
            @import "../css/bootstrap.css";
            .metric-card {
                border-left: 4px solid #0d6efd;
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
                    <h1 class="text-center mb-2">Ventas de matriz a sucursales</h1>
                    <p class="text-center text-muted mb-4">
                        Matriz: <?php echo htmlspecialchars($nombreMatriz, ENT_QUOTES, 'UTF-8'); ?>
                    </p>

                    <form method="get" class="card card-body mb-4">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="fecha" class="form-label">Fecha</label>
                                <input type="date" class="form-control" id="fecha" name="fecha" value="<?php echo htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="detalle" class="form-label">Nivel de detalle</label>
                                <select class="form-select" id="detalle" name="detalle">
                                    <option value="resumen" <?php echo $detalleTipo === 'resumen' ? 'selected' : ''; ?>>Solo resumen</option>
                                    <option value="categorias" <?php echo $detalleTipo === 'categorias' ? 'selected' : ''; ?>>Por categorías</option>
                                    <option value="productos" <?php echo $detalleTipo === 'productos' ? 'selected' : ''; ?>>Por productos</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">Generar reporte</button>
                            </div>
                        </div>
                    </form>

                    <?php if ($errorReporte !== ''): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($errorReporte, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php elseif (empty($sucursales)): ?>
                        <div class="alert alert-warning">
                            No se encontraron sucursales relacionadas con clientes activos de MATRIZ.
                            Revisa que <code>cc_clientes.clave_proveedor</code> coincida con
                            <code>cc_proveedores.clave_cliente</code>.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Las sucursales se detectan automáticamente mediante la relación
                            <code>cliente.clave_proveedor = proveedor.clave_cliente</code>.
                            “Ventas matriz” incluye sólo las ventas de MATRIZ al cliente relacionado.
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="card metric-card h-100">
                                    <div class="card-body">
                                        <div class="text-muted">Valor total de stock</div>
                                        <div class="fs-4 fw-bold">$<?php echo number_format($totales['valor_productos'] + $totales['valor_categorias'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card metric-card h-100">
                                    <div class="card-body">
                                        <div class="text-muted">Ventas de matriz</div>
                                        <div class="fs-4 fw-bold">$<?php echo number_format($totales['ventas'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card metric-card h-100">
                                    <div class="card-body">
                                        <div class="text-muted">Compras de sucursales</div>
                                        <div class="fs-4 fw-bold">$<?php echo number_format($totales['compras'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="resumen_sucursales" class="display" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Sucursal</th>
                                        <th>Cliente en matriz</th>
                                        <th>Stock productos</th>
                                        <th>Valor productos</th>
                                        <th>Stock categorías</th>
                                        <th>Valor categorías</th>
                                        <th>Valor stock</th>
                                        <th>Ventas matriz</th>
                                        <th>Compras sucursal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sucursales as $idSucursal => $sucursal): ?>
                                        <?php
                                        $datos = $resumen[$idSucursal];
                                        $valorStock = $datos['valor_productos'] + $datos['valor_categorias'];
                                        $nombresClientes = array_column($sucursal['clientes'], 'nombre');
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($sucursal['desc_sucursal'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars(implode(', ', $nombresClientes), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end"><?php echo number_format($datos['stock_productos'], 3); ?></td>
                                            <td class="text-end"><?php echo number_format($datos['valor_productos'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($datos['stock_categorias'], 3); ?></td>
                                            <td class="text-end"><?php echo number_format($datos['valor_categorias'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($valorStock, 2); ?></td>
                                            <td class="text-end"><?php echo number_format($datos['ventas'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($datos['compras'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>Total</th>
                                        <th></th>
                                        <th class="text-end"><?php echo number_format($totales['stock_productos'], 3); ?></th>
                                        <th class="text-end"><?php echo number_format($totales['valor_productos'], 2); ?></th>
                                        <th class="text-end"><?php echo number_format($totales['stock_categorias'], 3); ?></th>
                                        <th class="text-end"><?php echo number_format($totales['valor_categorias'], 2); ?></th>
                                        <th class="text-end"><?php echo number_format($totales['valor_productos'] + $totales['valor_categorias'], 2); ?></th>
                                        <th class="text-end"><?php echo number_format($totales['ventas'], 2); ?></th>
                                        <th class="text-end"><?php echo number_format($totales['compras'], 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <?php if ($detalleTipo !== 'resumen'): ?>
                            <hr class="my-4">
                            <h2 class="h4">Detalle por <?php echo $detalleTipo === 'categorias' ? 'categorías' : 'productos'; ?></h2>
                            <p class="text-muted">
                                Las ventas provienen de MATRIZ y se asignan al cliente vinculado con cada sucursal.
                                Las compras y el stock pertenecen a la sucursal.
                            </p>
                            <div class="table-responsive">
                                <table id="detalle_sucursales" class="display" style="width:100%">
                                    <thead>
                                        <?php if ($detalleTipo === 'categorias'): ?>
                                            <tr>
                                                <th>Sucursal</th>
                                                <th>Categoría</th>
                                                <th>Stock</th>
                                                <th>Precio promedio</th>
                                                <th>Valor stock</th>
                                                <th>Ventas matriz</th>
                                                <th>Compras sucursal</th>
                                            </tr>
                                        <?php else: ?>
                                            <tr>
                                                <th>Sucursal</th>
                                                <th>Código</th>
                                                <th>Producto</th>
                                                <th>Categoría</th>
                                                <th>Stock</th>
                                                <th>Precio compra</th>
                                                <th>Valor stock</th>
                                                <th>Ventas matriz</th>
                                                <th>Compras sucursal</th>
                                            </tr>
                                        <?php endif; ?>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($detalleRows as $detalle): ?>
                                            <?php if ($detalleTipo === 'categorias'): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($sucursales[(int) $detalle['id_sucursal']]['desc_sucursal'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($detalle['desc_categoria'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td class="text-end"><?php echo number_format((float) $detalle['stock'], 3); ?></td>
                                                    <td class="text-end"><?php echo number_format((float) $detalle['precio_compra'], 2); ?></td>
                                                    <td class="text-end"><?php echo number_format((float) $detalle['valor_stock'], 2); ?></td>
                                                    <td class="text-end"><?php echo number_format((float) $detalle['ventas'], 2); ?></td>
                                                    <td class="text-end"><?php echo number_format((float) $detalle['compras'], 2); ?></td>
                                                </tr>
                                            <?php else: ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($sucursales[(int) $detalle['id_sucursal']]['desc_sucursal'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($detalle['codigo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($detalle['descripcion'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($detalle['desc_categoria'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td class="text-end"><?php echo number_format((float) $detalle['stock'], 3); ?></td>
                                                    <td class="text-end"><?php echo number_format((float) $detalle['precio_compra'], 2); ?></td>
                                                    <td class="text-end"><?php echo number_format((float) $detalle['valor_stock'], 2); ?></td>
                                                    <td class="text-end"><?php echo number_format((float) $detalle['ventas'], 2); ?></td>
                                                    <td class="text-end"><?php echo number_format((float) $detalle['compras'], 2); ?></td>
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
                $('#resumen_sucursales').DataTable({
                    paging: false,
                    info: false,
                    searching: false,
                    order: [[0, 'asc']],
                    language: {
                        emptyTable: 'No hay información',
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
