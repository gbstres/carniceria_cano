<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
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

$sucursales = [];
$rsSucursales = mysqli_query($link, "
    SELECT id_sucursal, desc_sucursal, activo
    FROM cc_sucursales
    ORDER BY activo DESC, desc_sucursal
");
while ($rsSucursales && $row = mysqli_fetch_assoc($rsSucursales)) {
    $sucursales[(int) $row['id_sucursal']] = $row;
}

$seleccionSolicitada = isset($_GET['filtro_sucursales']);
$sucursalesSeleccionadas = [];
if ($seleccionSolicitada) {
    foreach ($_GET['sucursales'] as $idSucursal) {
        $idSucursal = (int) $idSucursal;
        if (isset($sucursales[$idSucursal])) {
            $sucursalesSeleccionadas[$idSucursal] = $idSucursal;
        }
    }
} else {
    foreach ($sucursales as $idSucursal => $sucursal) {
        if ((int) $sucursal['activo'] === 1) {
            $sucursalesSeleccionadas[$idSucursal] = $idSucursal;
        }
    }
}

$resumen = [];
foreach ($sucursalesSeleccionadas as $idSucursal) {
    $resumen[$idSucursal] = [
        'stock_productos' => 0,
        'valor_productos' => 0,
        'stock_categorias' => 0,
        'valor_categorias' => 0,
        'ventas' => 0,
        'compras' => 0,
    ];
}

function cargarResumenPorSucursal(mysqli $link, string $sql, array &$resumen, array $campos)
{
    $resultado = mysqli_query($link, $sql);
    if (!$resultado) {
        throw new RuntimeException(mysqli_error($link));
    }
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
$detalleRows = [];
if (!empty($sucursalesSeleccionadas)) {
    $listaSucursales = implode(',', array_map('intval', $sucursalesSeleccionadas));
    $fechaSql = mysqli_real_escape_string($link, $fecha);

    try {
        cargarResumenPorSucursal($link, "
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

        cargarResumenPorSucursal($link, "
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

        cargarResumenPorSucursal($link, "
            SELECT
                a.id_sucursal,
                COALESCE(SUM(ROUND(b.cantidad * b.precio_venta, 2)), 0) AS ventas
            FROM cc_det_ventas a
            INNER JOIN cc_ventas b
                ON b.id_sucursal = a.id_sucursal
               AND b.id_venta = a.id_venta
            WHERE a.id_sucursal IN ($listaSucursales)
              AND a.fecha_ingreso = '$fechaSql'
              AND a.estatus IN (1, 3)
              AND b.estatus <> 2
            GROUP BY a.id_sucursal
        ", $resumen, ['ventas']);

        cargarResumenPorSucursal($link, "
            SELECT
                a.id_sucursal,
                COALESCE(SUM(ROUND(b.cantidad * b.precio_compra, 2)), 0) AS compras
            FROM cc_det_compras a
            INNER JOIN cc_compras b
                ON b.id_sucursal = a.id_sucursal
               AND b.id_compra = a.id_compra
            WHERE a.id_sucursal IN ($listaSucursales)
              AND a.fecha_ingreso = '$fechaSql'
              AND a.estatus IN (1, 3)
              AND b.estatus <> 2
            GROUP BY a.id_sucursal
        ", $resumen, ['compras']);

        if ($detalleTipo === 'categorias') {
            $rsDetalle = mysqli_query($link, "
                SELECT
                    c.id_sucursal,
                    c.id_categoria,
                    c.desc_categoria,
                    c.almacen AS stock,
                    COALESCE(pc.precio_compra, 0) AS precio_compra,
                    CASE
                        WHEN c.almacen < 0 THEN 0
                        ELSE ROUND(c.almacen * COALESCE(pc.precio_compra, 0), 2)
                    END AS valor_stock,
                    COALESCE(v.ventas, 0) AS ventas,
                    COALESCE(co.compras, 0) AS compras
                FROM cc_categorias c
                LEFT JOIN (
                    SELECT id_sucursal, id_categoria, AVG(precio_compra) AS precio_compra
                    FROM cc_productos
                    WHERE centralizar_almacen = 2
                    GROUP BY id_sucursal, id_categoria
                ) pc
                    ON pc.id_sucursal = c.id_sucursal
                   AND pc.id_categoria = c.id_categoria
                LEFT JOIN (
                    SELECT
                        a.id_sucursal,
                        p.id_categoria,
                        SUM(ROUND(b.cantidad * b.precio_venta, 2)) AS ventas
                    FROM cc_det_ventas a
                    INNER JOIN cc_ventas b
                        ON b.id_sucursal = a.id_sucursal
                       AND b.id_venta = a.id_venta
                    INNER JOIN cc_productos p
                        ON p.id_sucursal = b.id_sucursal
                       AND p.codigo = b.codigo
                    WHERE a.id_sucursal IN ($listaSucursales)
                      AND a.fecha_ingreso = '$fechaSql'
                      AND a.estatus IN (1, 3)
                      AND b.estatus <> 2
                    GROUP BY a.id_sucursal, p.id_categoria
                ) v
                    ON v.id_sucursal = c.id_sucursal
                   AND v.id_categoria = c.id_categoria
                LEFT JOIN (
                    SELECT
                        a.id_sucursal,
                        p.id_categoria,
                        SUM(ROUND(b.cantidad * b.precio_compra, 2)) AS compras
                    FROM cc_det_compras a
                    INNER JOIN cc_compras b
                        ON b.id_sucursal = a.id_sucursal
                       AND b.id_compra = a.id_compra
                    INNER JOIN cc_productos p
                        ON p.id_sucursal = b.id_sucursal
                       AND p.codigo = b.codigo
                    WHERE a.id_sucursal IN ($listaSucursales)
                      AND a.fecha_ingreso = '$fechaSql'
                      AND a.estatus IN (1, 3)
                      AND b.estatus <> 2
                    GROUP BY a.id_sucursal, p.id_categoria
                ) co
                    ON co.id_sucursal = c.id_sucursal
                   AND co.id_categoria = c.id_categoria
                WHERE c.id_sucursal IN ($listaSucursales)
                  AND (c.almacen <> 0 OR COALESCE(v.ventas, 0) <> 0 OR COALESCE(co.compras, 0) <> 0)
                ORDER BY c.id_sucursal, c.desc_categoria
            ");
            if (!$rsDetalle) {
                throw new RuntimeException(mysqli_error($link));
            }
            while ($row = mysqli_fetch_assoc($rsDetalle)) {
                $detalleRows[] = $row;
            }
        } elseif ($detalleTipo === 'productos') {
            $rsDetalle = mysqli_query($link, "
                SELECT
                    p.id_sucursal,
                    p.codigo,
                    p.descripcion,
                    c.desc_categoria,
                    p.almacen AS stock,
                    COALESCE(eq.precio_compra_origen, p.precio_compra, 0) AS precio_compra,
                    CASE
                        WHEN p.almacen < 0 THEN 0
                        ELSE ROUND(p.almacen * COALESCE(eq.precio_compra_origen, p.precio_compra, 0), 2)
                    END AS valor_stock,
                    COALESCE(v.ventas, 0) AS ventas,
                    COALESCE(co.compras, 0) AS compras
                FROM cc_productos p
                LEFT JOIN cc_categorias c
                    ON c.id_sucursal = p.id_sucursal
                   AND c.id_categoria = p.id_categoria
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
                        a.id_sucursal,
                        b.codigo,
                        SUM(ROUND(b.cantidad * b.precio_venta, 2)) AS ventas
                    FROM cc_det_ventas a
                    INNER JOIN cc_ventas b
                        ON b.id_sucursal = a.id_sucursal
                       AND b.id_venta = a.id_venta
                    WHERE a.id_sucursal IN ($listaSucursales)
                      AND a.fecha_ingreso = '$fechaSql'
                      AND a.estatus IN (1, 3)
                      AND b.estatus <> 2
                    GROUP BY a.id_sucursal, b.codigo
                ) v
                    ON v.id_sucursal = p.id_sucursal
                   AND v.codigo = p.codigo
                LEFT JOIN (
                    SELECT
                        a.id_sucursal,
                        b.codigo,
                        SUM(ROUND(b.cantidad * b.precio_compra, 2)) AS compras
                    FROM cc_det_compras a
                    INNER JOIN cc_compras b
                        ON b.id_sucursal = a.id_sucursal
                       AND b.id_compra = a.id_compra
                    WHERE a.id_sucursal IN ($listaSucursales)
                      AND a.fecha_ingreso = '$fechaSql'
                      AND a.estatus IN (1, 3)
                      AND b.estatus <> 2
                    GROUP BY a.id_sucursal, b.codigo
                ) co
                    ON co.id_sucursal = p.id_sucursal
                   AND co.codigo = p.codigo
                WHERE p.id_sucursal IN ($listaSucursales)
                  AND (p.almacen <> 0 OR COALESCE(v.ventas, 0) <> 0 OR COALESCE(co.compras, 0) <> 0)
                ORDER BY p.id_sucursal, c.desc_categoria, p.descripcion
            ");
            if (!$rsDetalle) {
                throw new RuntimeException(mysqli_error($link));
            }
            while ($row = mysqli_fetch_assoc($rsDetalle)) {
                $detalleRows[] = $row;
            }
        }
    } catch (RuntimeException $exception) {
        $errorReporte = 'No fue posible generar el reporte: ' . $exception->getMessage();
    }
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
        <meta name="description" content="Resumen de sucursales">
        <link rel="shortcut icon" href="../img/logo_1.png">
        <title>Resumen de sucursales</title>
        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <style>
            @import "../css/bootstrap.css";
            .metric-card {
                border-left: 4px solid #0d6efd;
            }
            .sucursales-selector {
                max-height: 210px;
                overflow-y: auto;
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
                    <h1 class="text-center mb-4">Resumen de sucursales</h1>

                    <form method="get" class="card card-body mb-4">
                        <input type="hidden" name="filtro_sucursales" value="1">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="fecha" class="form-label">Fecha</label>
                                <input type="date" class="form-control" id="fecha" name="fecha" value="<?php echo htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="detalle" class="form-label">Nivel de detalle</label>
                                <select class="form-select" id="detalle" name="detalle">
                                    <option value="resumen" <?php echo $detalleTipo === 'resumen' ? 'selected' : ''; ?>>Solo resumen</option>
                                    <option value="categorias" <?php echo $detalleTipo === 'categorias' ? 'selected' : ''; ?>>Por categorías</option>
                                    <option value="productos" <?php echo $detalleTipo === 'productos' ? 'selected' : ''; ?>>Por productos</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label class="form-label mb-1">Sucursales</label>
                                    <div>
                                        <button type="button" class="btn btn-link btn-sm" id="seleccionar_todas">Seleccionar todas</button>
                                        <button type="button" class="btn btn-link btn-sm" id="limpiar_sucursales">Limpiar</button>
                                    </div>
                                </div>
                                <div class="border rounded p-2 sucursales-selector">
                                    <div class="row">
                                        <?php foreach ($sucursales as $idSucursal => $sucursal): ?>
                                            <div class="col-md-4 col-sm-6">
                                                <label class="form-check-label">
                                                    <input
                                                        class="form-check-input sucursal-check"
                                                        type="checkbox"
                                                        name="sucursales[]"
                                                        value="<?php echo $idSucursal; ?>"
                                                        <?php echo isset($sucursalesSeleccionadas[$idSucursal]) ? 'checked' : ''; ?>
                                                    >
                                                    <?php echo htmlspecialchars($sucursal['desc_sucursal'], ENT_QUOTES, 'UTF-8'); ?>
                                                    <?php if ((int) $sucursal['activo'] !== 1): ?>
                                                        <span class="text-muted">(inactiva)</span>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 text-center">
                                <button type="submit" class="btn btn-primary">Generar reporte</button>
                            </div>
                        </div>
                    </form>

                    <?php if ($errorReporte !== ''): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($errorReporte, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php elseif (empty($sucursalesSeleccionadas)): ?>
                        <div class="alert alert-warning">Selecciona al menos una sucursal.</div>
                    <?php else: ?>
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
                                        <div class="text-muted">Ventas del día</div>
                                        <div class="fs-4 fw-bold">$<?php echo number_format($totales['ventas'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card metric-card h-100">
                                    <div class="card-body">
                                        <div class="text-muted">Compras del día</div>
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
                                        <th>Stock productos</th>
                                        <th>Valor productos</th>
                                        <th>Stock categorías</th>
                                        <th>Valor categorías</th>
                                        <th>Valor stock</th>
                                        <th>Ventas del día</th>
                                        <th>Compras del día</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sucursalesSeleccionadas as $idSucursal): ?>
                                        <?php
                                        $datos = $resumen[$idSucursal];
                                        $valorStock = $datos['valor_productos'] + $datos['valor_categorias'];
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($sucursales[$idSucursal]['desc_sucursal'], ENT_QUOTES, 'UTF-8'); ?></td>
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
                            <h2 class="h4">
                                Detalle por <?php echo $detalleTipo === 'categorias' ? 'categorías' : 'productos'; ?>
                            </h2>
                            <p class="text-muted">
                                El resumen anterior no cambia. Esta tabla desglosa únicamente las sucursales y la fecha seleccionadas.
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
                                                <th>Ventas del día</th>
                                                <th>Compras del día</th>
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
                                                <th>Ventas del día</th>
                                                <th>Compras del día</th>
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
                $('#seleccionar_todas').on('click', function () {
                    $('.sucursal-check').prop('checked', true);
                });
                $('#limpiar_sucursales').on('click', function () {
                    $('.sucursal-check').prop('checked', false);
                });
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
