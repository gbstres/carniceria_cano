<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";

if (!tienePermiso('ver')) {
    header("location: ../main/inicio.php");
    exit;
}

date_default_timezone_set("America/Mexico_City");

$id_sucursal = (int) $_SESSION["id_sucursal"];

$fecha1 = $_POST['fecha1'] ?? date('Y-m-d');
$fecha2 = $_POST['fecha2'] ?? date('Y-m-d');

$fecha1Escaped = mysqli_real_escape_string($link, $fecha1);
$fecha2Escaped = mysqli_real_escape_string($link, $fecha2);

// Consulta de Productos (Compras vs Ventas)
$sqlProductosQuery = "
    SELECT
        base.codigo,
        COALESCE(p.descripcion, base.codigo) AS descripcion,
        COALESCE(p.id_categoria, 0) AS id_categoria,
        COALESCE(c.desc_categoria, 'Sin categoría') AS desc_categoria,
        COALESCE(ca.descripcion_corta, '') AS centraliza,
        COALESCE(comp.compras_cant, 0) AS compras_cant,
        COALESCE(comp.compras_importe, 0) AS compras_importe,
        COALESCE(vent.ventas_cant, 0) AS ventas_cant,
        COALESCE(vent.ventas_costo, 0) AS ventas_costo,
        COALESCE(vent.ventas_importe, 0) AS ventas_importe
    FROM (
        SELECT codigo FROM cc_productos WHERE id_sucursal = $id_sucursal
        UNION
        SELECT c.codigo FROM cc_compras c INNER JOIN cc_det_compras dc ON dc.id_sucursal = c.id_sucursal AND dc.id_compra = c.id_compra WHERE dc.id_sucursal = $id_sucursal AND dc.fecha_ingreso BETWEEN '$fecha1Escaped' AND '$fecha2Escaped' AND dc.estatus IN (1,3) AND c.estatus <> 2
        UNION
        SELECT v.codigo FROM cc_ventas v INNER JOIN cc_det_ventas dv ON dv.id_sucursal = v.id_sucursal AND dv.id_venta = v.id_venta WHERE dv.id_sucursal = $id_sucursal AND dv.fecha_ingreso BETWEEN '$fecha1Escaped' AND '$fecha2Escaped' AND dv.estatus IN (1,3) AND v.estatus <> 2
    ) base
    LEFT JOIN cc_productos p
        ON p.id_sucursal = $id_sucursal
       AND p.codigo = base.codigo
    LEFT JOIN cc_categorias c
        ON c.id_sucursal = $id_sucursal
       AND c.id_categoria = p.id_categoria
    LEFT JOIN cc_claves ca
        ON ca.nombre_clave = 'CENTRALIZAR_ALMACEN'
       AND ca.clave = p.centralizar_almacen
    LEFT JOIN (
        SELECT
            c.codigo,
            SUM(c.cantidad) AS compras_cant,
            SUM(ROUND(c.cantidad * c.precio_compra, 2)) AS compras_importe
        FROM cc_det_compras dc
        INNER JOIN cc_compras c
            ON c.id_sucursal = dc.id_sucursal
           AND c.id_compra = dc.id_compra
        WHERE dc.id_sucursal = $id_sucursal
          AND dc.fecha_ingreso BETWEEN '$fecha1Escaped' AND '$fecha2Escaped'
          AND dc.estatus IN (1, 3)
          AND c.estatus <> 2
        GROUP BY c.codigo
    ) comp ON comp.codigo = base.codigo
    LEFT JOIN (
        SELECT
            v.codigo,
            SUM(v.cantidad) AS ventas_cant,
            SUM(ROUND(v.cantidad * v.precio_compra, 2)) AS ventas_costo,
            SUM(ROUND(v.cantidad * v.precio_venta, 2)) AS ventas_importe
        FROM cc_det_ventas dv
        INNER JOIN cc_ventas v
            ON v.id_sucursal = dv.id_sucursal
           AND v.id_venta = dv.id_venta
        WHERE dv.id_sucursal = $id_sucursal
          AND dv.fecha_ingreso BETWEEN '$fecha1Escaped' AND '$fecha2Escaped'
          AND dv.estatus IN (1, 3)
          AND v.estatus <> 2
        GROUP BY v.codigo
    ) vent ON vent.codigo = base.codigo
    WHERE (COALESCE(comp.compras_cant, 0) <> 0 
           OR COALESCE(comp.compras_importe, 0) <> 0 
           OR COALESCE(vent.ventas_cant, 0) <> 0 
           OR COALESCE(vent.ventas_importe, 0) <> 0)
    ORDER BY c.desc_categoria, p.descripcion
";

$sqlProductos = mysqli_query($link, $sqlProductosQuery);

$listaProductos = [];
$totProdComprasCant = 0;
$totProdComprasImp = 0;
$totProdVentasCant = 0;
$totProdVentasImp = 0;
$totProdBalanceImp = 0;
$totProdGananciaImp = 0;

if ($sqlProductos) {
    while ($row = mysqli_fetch_assoc($sqlProductos)) {
        $cCant = (float) $row['compras_cant'];
        $cImp = (float) $row['compras_importe'];
        $vCant = (float) $row['ventas_cant'];
        $vCost = (float) $row['ventas_costo'];
        $vImp = (float) $row['ventas_importe'];
        
        $difCant = $vCant - $cCant;
        $balance = $vImp - $cImp;
        $ganancia = $vImp - $vCost;

        $totProdComprasCant += $cCant;
        $totProdComprasImp += $cImp;
        $totProdVentasCant += $vCant;
        $totProdVentasImp += $vImp;
        $totProdBalanceImp += $balance;
        $totProdGananciaImp += $ganancia;

        $listaProductos[] = [
            'codigo' => (string) $row['codigo'],
            'descripcion' => (string) $row['descripcion'],
            'id_categoria' => $row['id_categoria'],
            'desc_categoria' => (string) $row['desc_categoria'],
            'centraliza' => (string) $row['centraliza'],
            'compras_cant' => $cCant,
            'compras_importe' => $cImp,
            'ventas_cant' => $vCant,
            'ventas_importe' => $vImp,
            'dif_cant' => $difCant,
            'balance' => $balance,
            'ganancia' => $ganancia,
        ];
    }
}

// Consulta de Categorías (Compras vs Ventas)
$sqlCategoriasQuery = "
    SELECT
        cat.id_categoria,
        cat.desc_categoria,
        COALESCE(comp.compras_cant, 0) AS compras_cant,
        COALESCE(comp.compras_importe, 0) AS compras_importe,
        COALESCE(vent.ventas_cant, 0) AS ventas_cant,
        COALESCE(vent.ventas_costo, 0) AS ventas_costo,
        COALESCE(vent.ventas_importe, 0) AS ventas_importe
    FROM cc_categorias cat
    LEFT JOIN (
        SELECT
            p.id_categoria,
            SUM(c.cantidad) AS compras_cant,
            SUM(ROUND(c.cantidad * c.precio_compra, 2)) AS compras_importe
        FROM cc_det_compras dc
        INNER JOIN cc_compras c
            ON c.id_sucursal = dc.id_sucursal
           AND c.id_compra = dc.id_compra
        INNER JOIN cc_productos p
            ON p.id_sucursal = c.id_sucursal
           AND p.codigo = c.codigo
        WHERE dc.id_sucursal = $id_sucursal
          AND dc.fecha_ingreso BETWEEN '$fecha1Escaped' AND '$fecha2Escaped'
          AND dc.estatus IN (1, 3)
          AND c.estatus <> 2
        GROUP BY p.id_categoria
    ) comp ON comp.id_categoria = cat.id_categoria
    LEFT JOIN (
        SELECT
            p.id_categoria,
            SUM(v.cantidad) AS ventas_cant,
            SUM(ROUND(v.cantidad * v.precio_compra, 2)) AS ventas_costo,
            SUM(ROUND(v.cantidad * v.precio_venta, 2)) AS ventas_importe
        FROM cc_det_ventas dv
        INNER JOIN cc_ventas v
            ON v.id_sucursal = dv.id_sucursal
           AND v.id_venta = dv.id_venta
        INNER JOIN cc_productos p
            ON p.id_sucursal = v.id_sucursal
           AND p.codigo = v.codigo
        WHERE dv.id_sucursal = $id_sucursal
          AND dv.fecha_ingreso BETWEEN '$fecha1Escaped' AND '$fecha2Escaped'
          AND dv.estatus IN (1, 3)
          AND v.estatus <> 2
        GROUP BY p.id_categoria
    ) vent ON vent.id_categoria = cat.id_categoria
    WHERE cat.id_sucursal = $id_sucursal
      AND (COALESCE(comp.compras_cant, 0) <> 0 
           OR COALESCE(comp.compras_importe, 0) <> 0 
           OR COALESCE(vent.ventas_cant, 0) <> 0 
           OR COALESCE(vent.ventas_importe, 0) <> 0)
    ORDER BY cat.desc_categoria
";

$sqlCategorias = mysqli_query($link, $sqlCategoriasQuery);

$listaCategorias = [];
$totCatComprasCant = 0;
$totCatComprasImp = 0;
$totCatVentasCant = 0;
$totCatVentasImp = 0;
$totCatBalanceImp = 0;
$totCatGananciaImp = 0;

if ($sqlCategorias) {
    while ($row = mysqli_fetch_assoc($sqlCategorias)) {
        $cCant = (float) $row['compras_cant'];
        $cImp = (float) $row['compras_importe'];
        $vCant = (float) $row['ventas_cant'];
        $vCost = (float) $row['ventas_costo'];
        $vImp = (float) $row['ventas_importe'];

        $difCant = $vCant - $cCant;
        $balance = $vImp - $cImp;
        $ganancia = $vImp - $vCost;

        $totCatComprasCant += $cCant;
        $totCatComprasImp += $cImp;
        $totCatVentasCant += $vCant;
        $totCatVentasImp += $vImp;
        $totCatBalanceImp += $balance;
        $totCatGananciaImp += $ganancia;

        $listaCategorias[] = [
            'id_categoria' => (string) $row['id_categoria'],
            'desc_categoria' => (string) $row['desc_categoria'],
            'compras_cant' => $cCant,
            'compras_importe' => $cImp,
            'ventas_cant' => $vCant,
            'ventas_importe' => $vImp,
            'dif_cant' => $difCant,
            'balance' => $balance,
            'ganancia' => $ganancia,
        ];
    }
}
?>
<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Carnicería Cano">
        <meta name="author" content="Gerardo Bautista">
        <link rel="shortcut icon" href="../img/logo_1.png">
        <title>Reporte de Compras vs Ventas</title>

        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <style>
            @import "../css/bootstrap.css";
            #cv_productos tr.category-group td {
                background-color: #e9ecef;
                font-weight: 600;
                color: #495057;
            }
            .text-end { text-align: right; }
            .text-center { text-align: center; }
        </style>
        <link href="../css/navbar.css" rel="stylesheet">
        <link href="../css/jquery.dataTables.min.css" rel="stylesheet">
    </head>
    <body>
        <main>
            <div class="container">
                <?php require_once "../components/nav.php" ?>

                <div class="bg-light p-4 rounded">
                    <div class="col-sm-8 mx-auto">
                        <h1 class="text-center">Reporte de Compras vs Ventas</h1>
                    </div>
                    <br>

                    <!-- Barra de filtros -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-body">
                            <form method="POST" action="compras_vs_ventas.php" id="form_filtro_cv" class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label for="fecha1" class="form-label fw-bold">Fecha inicio:</label>
                                    <input type="date" class="form-control" name="fecha1" id="fecha1" value="<?php echo htmlspecialchars($fecha1, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="fecha2" class="form-label fw-bold">Fecha fin:</label>
                                    <input type="date" class="form-control" name="fecha2" id="fecha2" value="<?php echo htmlspecialchars($fecha2, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-4 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-grow-1">
                                        <i class="bi bi-search"></i> Consultar
                                    </button>
                                    <button type="button" class="btn btn-secondary flex-grow-1" onclick="imprimirReporte()">
                                        <i class="bi bi-printer"></i> Imprimir
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="col-sm mx-auto">
                        <h3 class="text-left">Productos (Compras vs Ventas)</h3>
                    </div>
                    <br>
                    <div class="table-responsive">
                        <table id="cv_productos" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th>Categoría</th>
                                    <th>Centraliza</th>
                                    <th>Compras Cant.</th>
                                    <th>Compras ($)</th>
                                    <th>Ventas Cant.</th>
                                    <th>Ventas ($)</th>
                                    <th>Dif. Cant.</th>
                                    <th>Balance ($)</th>
                                    <th>Ganancia Est. ($)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($listaProductos as $prod) {
                                    $classBalance = $prod['balance'] >= 0 ? 'text-success' : 'text-danger';
                                    $classGanancia = $prod['ganancia'] >= 0 ? 'text-success' : 'text-danger';
                                    echo '<tr>
                                        <td>' . htmlspecialchars($prod["codigo"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td>' . htmlspecialchars($prod["descripcion"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td>' . htmlspecialchars($prod["desc_categoria"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td>' . htmlspecialchars($prod["centraliza"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td class="text-end">' . number_format($prod["compras_cant"], 3) . '</td>
                                        <td class="text-end">$' . number_format($prod["compras_importe"], 2) . '</td>
                                        <td class="text-end">' . number_format($prod["ventas_cant"], 3) . '</td>
                                        <td class="text-end">$' . number_format($prod["ventas_importe"], 2) . '</td>
                                        <td class="text-end">' . number_format($prod["dif_cant"], 3) . '</td>
                                        <td class="text-end fw-bold ' . $classBalance . '">$' . number_format($prod["balance"], 2) . '</td>
                                        <td class="text-end fw-bold ' . $classGanancia . '">$' . number_format($prod["ganancia"], 2) . '</td>
                                    </tr>';
                                }
                                ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-end">Total General:</th>
                                    <th class="text-end"><?php echo number_format($totProdComprasCant, 3); ?></th>
                                    <th class="text-end">$<?php echo number_format($totProdComprasImp, 2); ?></th>
                                    <th class="text-end"><?php echo number_format($totProdVentasCant, 3); ?></th>
                                    <th class="text-end">$<?php echo number_format($totProdVentasImp, 2); ?></th>
                                    <th class="text-end"><?php echo number_format($totProdVentasCant - $totProdComprasCant, 3); ?></th>
                                    <th class="text-end">$<?php echo number_format($totProdBalanceImp, 2); ?></th>
                                    <th class="text-end">$<?php echo number_format($totProdGananciaImp, 2); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <br>
                    <div class="col-sm mx-auto">
                        <h3 class="text-left">Categorías (Compras vs Ventas)</h3>
                    </div>
                    <br>
                    <div class="table-responsive">
                        <table id="cv_categorias" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Categoría</th>
                                    <th>Compras Cant.</th>
                                    <th>Compras ($)</th>
                                    <th>Ventas Cant.</th>
                                    <th>Ventas ($)</th>
                                    <th>Dif. Cant.</th>
                                    <th>Balance ($)</th>
                                    <th>Ganancia Est. ($)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($listaCategorias as $cat) {
                                    $classBalance = $cat['balance'] >= 0 ? 'text-success' : 'text-danger';
                                    $classGanancia = $cat['ganancia'] >= 0 ? 'text-success' : 'text-danger';
                                    echo '<tr>
                                        <td>' . htmlspecialchars($cat["id_categoria"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td>' . htmlspecialchars($cat["desc_categoria"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td class="text-end">' . number_format($cat["compras_cant"], 3) . '</td>
                                        <td class="text-end">$' . number_format($cat["compras_importe"], 2) . '</td>
                                        <td class="text-end">' . number_format($cat["ventas_cant"], 3) . '</td>
                                        <td class="text-end">$' . number_format($cat["ventas_importe"], 2) . '</td>
                                        <td class="text-end">' . number_format($cat["dif_cant"], 3) . '</td>
                                        <td class="text-end fw-bold ' . $classBalance . '">$' . number_format($cat["balance"], 2) . '</td>
                                        <td class="text-end fw-bold ' . $classGanancia . '">$' . number_format($cat["ganancia"], 2) . '</td>
                                    </tr>';
                                }
                                ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2" class="text-end">Total General:</th>
                                    <th class="text-end"><?php echo number_format($totCatComprasCant, 3); ?></th>
                                    <th class="text-end">$<?php echo number_format($totCatComprasImp, 2); ?></th>
                                    <th class="text-end"><?php echo number_format($totCatVentasCant, 3); ?></th>
                                    <th class="text-end">$<?php echo number_format($totCatVentasImp, 2); ?></th>
                                    <th class="text-end"><?php echo number_format($totCatVentasCant - $totCatComprasCant, 3); ?></th>
                                    <th class="text-end">$<?php echo number_format($totCatBalanceImp, 2); ?></th>
                                    <th class="text-end">$<?php echo number_format($totCatGananciaImp, 2); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Div de impresión -->
            <div id="div_impresion" style="display:none">
                <style>
                    #tabla_cv_prod_imp, #tabla_cv_prod_imp table, #tabla_cv_prod_imp th, #tabla_cv_prod_imp td,
                    #tabla_cv_cat_imp, #tabla_cv_cat_imp table, #tabla_cv_cat_imp th, #tabla_cv_cat_imp td {
                        border: 1px solid;
                        border-collapse: collapse;
                        font-size: 10px;
                    }
                    #encabezado_cv, #encabezado_cv tr, #encabezado_cv td {
                        border-collapse: collapse;
                        font-size: 13px;
                        text-align: center;
                    }
                    .text-end { text-align: right; }
                    .text-center { text-align: center; }
                    h5 { margin: 8px 0 4px 0; }
                </style>
                <div style="text-align: center">
                    <h3><?php echo htmlspecialchars($_SESSION["desc_sucursal"] ?? "Carnicería Cano", ENT_QUOTES, "UTF-8"); ?></h3>
                    <h4>Reporte de Compras vs Ventas</h4>
                </div>
                <div style="text-align: center">
                    <div id="header_info_cv">
                        <table id="encabezado_cv" style="width:100%;">
                            <tr>
                                <td>Intervalo de Fechas: <?php echo htmlspecialchars($fecha1, ENT_QUOTES, "UTF-8") . ' al ' . htmlspecialchars($fecha2, ENT_QUOTES, "UTF-8"); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div style="text-align: center; margin-top: 5px;">
                        <img src="../img/logo_1.jpeg" alt="Carnicería Cano" width="80" height="80">
                    </div>
                </div>
                <br>
                <h5>Productos (Compras vs Ventas)</h5>
                <div id="tabla_cv_prod_imp">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Categoría</th>
                                <th class="text-end">Comp. Cant.</th>
                                <th class="text-end">Comp. ($)</th>
                                <th class="text-end">Vent. Cant.</th>
                                <th class="text-end">Vent. ($)</th>
                                <th class="text-end">Balance ($)</th>
                                <th class="text-end">Ganancia ($)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($listaProductos)): ?>
                                <tr><td colspan="9" class="text-center">Sin movimientos de compras o ventas en el intervalo</td></tr>
                            <?php else: ?>
                                <?php foreach ($listaProductos as $prod): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($prod['codigo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($prod['descripcion'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($prod['desc_categoria'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-end"><?php echo number_format($prod['compras_cant'], 1); ?></td>
                                        <td class="text-end">$<?php echo number_format($prod['compras_importe'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($prod['ventas_cant'], 1); ?></td>
                                        <td class="text-end">$<?php echo number_format($prod['ventas_importe'], 2); ?></td>
                                        <td class="text-end">$<?php echo number_format($prod['balance'], 2); ?></td>
                                        <td class="text-end">$<?php echo number_format($prod['ganancia'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total</th>
                                <th class="text-end"><?php echo number_format($totProdComprasCant, 1); ?></th>
                                <th class="text-end">$<?php echo number_format($totProdComprasImp, 2); ?></th>
                                <th class="text-end"><?php echo number_format($totProdVentasCant, 1); ?></th>
                                <th class="text-end">$<?php echo number_format($totProdVentasImp, 2); ?></th>
                                <th class="text-end">$<?php echo number_format($totProdBalanceImp, 2); ?></th>
                                <th class="text-end">$<?php echo number_format($totProdGananciaImp, 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <br>
                <h5>Categorías (Compras vs Ventas)</h5>
                <div id="tabla_cv_cat_imp">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Categoría</th>
                                <th class="text-end">Comp. Cant.</th>
                                <th class="text-end">Comp. ($)</th>
                                <th class="text-end">Vent. Cant.</th>
                                <th class="text-end">Vent. ($)</th>
                                <th class="text-end">Balance ($)</th>
                                <th class="text-end">Ganancia ($)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($listaCategorias)): ?>
                                <tr><td colspan="8" class="text-center">Sin movimientos de categorías en el intervalo</td></tr>
                            <?php else: ?>
                                <?php foreach ($listaCategorias as $cat): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $cat['id_categoria'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($cat['desc_categoria'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-end"><?php echo number_format($cat['compras_cant'], 1); ?></td>
                                        <td class="text-end">$<?php echo number_format($cat['compras_importe'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($cat['ventas_cant'], 1); ?></td>
                                        <td class="text-end">$<?php echo number_format($cat['ventas_importe'], 2); ?></td>
                                        <td class="text-end">$<?php echo number_format($cat['balance'], 2); ?></td>
                                        <td class="text-end">$<?php echo number_format($cat['ganancia'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2" class="text-end">Total</th>
                                <th class="text-end"><?php echo number_format($totCatComprasCant, 1); ?></th>
                                <th class="text-end">$<?php echo number_format($totCatComprasImp, 2); ?></th>
                                <th class="text-end"><?php echo number_format($totCatVentasCant, 1); ?></th>
                                <th class="text-end">$<?php echo number_format($totCatVentasImp, 2); ?></th>
                                <th class="text-end">$<?php echo number_format($totCatBalanceImp, 2); ?></th>
                                <th class="text-end">$<?php echo number_format($totCatGananciaImp, 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </main>

        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
            function abrepaginaimpresion(nombre) {
                var ficha = document.getElementById(nombre);
                var altura = 600;
                var anchura = 800;
                var y = parseInt((window.screen.height / 2) - (altura / 2));
                var x = parseInt((window.screen.width / 2) - (anchura / 2));
                var ventimp = window.open('Imprimir.html', target = 'blank', 'width=' + anchura + ',height=' + altura + ',top=' + y + ',left=' + x + ',toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes');
                ventimp.document.write(ficha.innerHTML);
                ventimp.document.close();
                ventimp.print();
                ventimp.close();
            }

            function imprimirReporte() {
                abrepaginaimpresion("div_impresion");
            }

            $(document).ready(function () {
                const language = {
                    "emptyTable": "No hay información para el periodo seleccionado",
                    "info": "Mostrando _START_ a _END_ de _TOTAL_",
                    "infoEmpty": "Mostrando 0 a 0 de 0",
                    "infoFiltered": "(Filtrado de _MAX_ total)",
                    "lengthMenu": "Mostrar _MENU_",
                    "loadingRecords": "Cargando...",
                    "processing": "Procesando...",
                    "search": "Buscar:",
                    "zeroRecords": "Sin resultados",
                    "paginate": {"first": "Primero", "last": "Último", "next": "Siguiente", "previous": "Anterior"}
                };

                $('#cv_productos').DataTable({
                    language: language,
                    pageLength: 50,
                    orderFixed: [[2, 'asc']],
                    order: [[1, 'asc']],
                    columnDefs: [
                        {targets: 2, visible: false}
                    ],
                    drawCallback: function () {
                        const api = this.api();
                        const rows = api.rows({page: 'current'}).nodes();
                        let ultimaCategoria = null;

                        api.column(2, {page: 'current'}).data().each(function (categoria, indice) {
                            const nombreCategoria = categoria || 'Sin categoría';

                            if (nombreCategoria !== ultimaCategoria) {
                                $(rows).eq(indice).before(
                                    '<tr class="category-group"><td colspan="10">' +
                                    $('<div>').text(nombreCategoria).html() +
                                    '</td></tr>'
                                );
                                ultimaCategoria = nombreCategoria;
                            }
                        });
                    }
                });

                $('#cv_categorias').DataTable({
                    language: language,
                    pageLength: 25,
                    order: [[1, 'asc']]
                });
            });
        </script>
    </body>
</html>
