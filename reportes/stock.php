<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";
date_default_timezone_set("America/Mexico_City");

$id_sucursal = (int) $_SESSION["id_sucursal"];

// Endpoint AJAX para obtener los cierres de una fecha específica
if (isset($_GET['action']) && $_GET['action'] === 'obtener_cierres') {
    header('Content-Type: application/json');
    $fechaReq = mysqli_real_escape_string($link, $_GET['fecha'] ?? date('Y-m-d'));
    $sqlC = mysqli_query($link, "
        SELECT id_cierre, fecha_ingreso, hora_ingreso
        FROM cc_cierre
        WHERE id_sucursal = $id_sucursal AND fecha_ingreso = '$fechaReq'
        GROUP BY id_cierre, fecha_ingreso, hora_ingreso
        ORDER BY id_cierre DESC
    ");
    $cierres = [];
    while ($r = mysqli_fetch_assoc($sqlC)) {
        $cierres[] = [
            'id_cierre' => (int) $r['id_cierre'],
            'hora_ingreso' => (string) $r['hora_ingreso'],
        ];
    }
    echo json_encode(['cierres' => $cierres]);
    exit;
}

$modo = $_POST['modo'] ?? 'en_linea';
$fecha = $_POST['fecha'] ?? date('Y-m-d');
$id_cierre = isset($_POST['id_cierre']) ? (int) $_POST['id_cierre'] : 0;

$fechaEscaped = mysqli_real_escape_string($link, $fecha);
$sqlCierresDisponibles = mysqli_query($link, "
    SELECT id_cierre, fecha_ingreso, hora_ingreso
    FROM cc_cierre
    WHERE id_sucursal = $id_sucursal
      AND fecha_ingreso = '$fechaEscaped'
    GROUP BY id_cierre, fecha_ingreso, hora_ingreso
    ORDER BY id_cierre DESC
");
$cierresDisponibles = [];
while ($rowc = mysqli_fetch_assoc($sqlCierresDisponibles)) {
    $cierresDisponibles[] = $rowc;
}

if ($modo === 'historico') {
    if ($id_cierre <= 0 && !empty($cierresDisponibles)) {
        $id_cierre = (int) $cierresDisponibles[0]['id_cierre'];
    }
}

$esHistorico = false;
$cierreFechaHora = date('Y-m-d H:i:s');
$listaProductos = [];
$totalProductos = 0;
$importeProductos = 0;
$listaCategorias = [];
$totalCategorias = 0;
$importeCategorias = 0;

if ($modo === 'historico' && $id_cierre > 0) {
    $esHistorico = true;
    $rowInfoCierre = mysqli_fetch_assoc(mysqli_query($link, "
        SELECT id_cierre, fecha_ingreso, hora_ingreso
        FROM cc_cierre
        WHERE id_sucursal = $id_sucursal AND id_cierre = $id_cierre
        LIMIT 1
    "));
    if ($rowInfoCierre) {
        $cierreFechaHora = $rowInfoCierre['fecha_ingreso'] . ' ' . $rowInfoCierre['hora_ingreso'];
    }

    $sqlProductos = mysqli_query($link, "
        SELECT
            codigo,
            descripcion,
            stock AS almacen,
            id_categoria,
            desc_categoria,
            centraliza,
            precio_compra,
            total
        FROM cc_cierre_stock
        WHERE id_sucursal = $id_sucursal
          AND id_cierre = $id_cierre
          AND tipo = 'PRODUCTO'
        ORDER BY desc_categoria, descripcion
    ");

    $sqlCategorias = mysqli_query($link, "
        SELECT
            codigo AS id_categoria,
            descripcion AS desc_categoria,
            stock AS almacen,
            precio_compra AS precio,
            total
        FROM cc_cierre_stock
        WHERE id_sucursal = $id_sucursal
          AND id_cierre = $id_cierre
          AND tipo = 'CATEGORIA'
        ORDER BY desc_categoria
    ");
} else {
    // Consulta en vivo
    $sqlProductos = mysqli_query($link, "
        SELECT
            p.codigo,
            p.descripcion,
            p.almacen,
            p.id_categoria,
            c.desc_categoria,
            p.centralizar_almacen,
            ca.descripcion_corta AS centraliza,
            COALESCE(eq.precio_compra_origen, p.precio_compra, 0) AS precio_compra
        FROM cc_productos p
        LEFT JOIN cc_categorias c
            ON c.id_sucursal = p.id_sucursal
           AND c.id_categoria = p.id_categoria
        LEFT JOIN cc_claves ca
            ON ca.nombre_clave = 'CENTRALIZAR_ALMACEN'
           AND ca.clave = p.centralizar_almacen
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
        WHERE p.id_sucursal = $id_sucursal
          AND p.almacen <> 0
        ORDER BY c.desc_categoria, p.descripcion
    ");

    $sqlCategorias = mysqli_query($link, "
        SELECT
            c.id_categoria,
            c.desc_categoria,
            c.almacen,
            c.precio
        FROM cc_categorias c
        WHERE c.id_sucursal = $id_sucursal
          AND c.almacen <> 0
        ORDER BY c.desc_categoria
    ");
}

if ($sqlProductos) {
    while ($row = mysqli_fetch_assoc($sqlProductos)) {
        $stock = (float) $row["almacen"];
        $precioCompra = (float) $row["precio_compra"];
        $totalProducto = isset($row["total"]) ? (float) $row["total"] : ($stock < 0 ? 0 : round($stock * $precioCompra, 2));
        $totalProductos += $stock;
        $importeProductos += $totalProducto;
        $listaProductos[] = [
            'codigo' => (string) $row['codigo'],
            'descripcion' => (string) $row['descripcion'],
            'id_categoria' => $row['id_categoria'] ?? '',
            'desc_categoria' => (string) ($row['desc_categoria'] ?? ''),
            'centraliza' => (string) ($row['centraliza'] ?? ''),
            'stock' => $stock,
            'precio_compra' => $precioCompra,
            'total' => $totalProducto,
        ];
    }
}

if ($sqlCategorias) {
    while ($row = mysqli_fetch_assoc($sqlCategorias)) {
        $stock = (float) $row["almacen"];
        $precioCategoria = (float) ($row["precio"] ?? 0);
        $totalCategoria = isset($row["total"]) ? (float) $row["total"] : ($stock < 0 ? 0 : round($stock * $precioCategoria, 2));
        $totalCategorias += $stock;
        $importeCategorias += $totalCategoria;
        $listaCategorias[] = [
            'id_categoria' => (string) $row['id_categoria'],
            'desc_categoria' => (string) $row['desc_categoria'],
            'stock' => $stock,
            'precio' => $row['precio'],
            'precio_val' => $precioCategoria,
            'total' => $totalCategoria,
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
        <title>Reporte de stock</title>

        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <style>
            @import "../css/bootstrap.css";
            .editable-stock {
                cursor: pointer;
            }
            .editable-stock:hover {
                background-color: #fff3cd;
            }
            .stock-input {
                max-width: 110px;
                text-align: right;
            }
            #stock_productos tr.category-group td {
                background-color: #e9ecef;
                font-weight: 600;
                color: #495057;
            }
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
                        <h1 class="text-center">Reporte de stock</h1>
                    </div>
                    <br>

                    <!-- Barra de filtros y botones -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-body">
                            <form method="POST" action="stock.php" id="form_filtro_stock" class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label for="modo" class="form-label fw-bold">Tipo de consulta:</label>
                                    <select name="modo" id="modo" class="form-select" onchange="toggleModo(this.value)">
                                        <option value="en_linea" <?php echo $modo === 'en_linea' ? 'selected' : ''; ?>>Stock actual (En línea)</option>
                                        <option value="historico" <?php echo $modo === 'historico' ? 'selected' : ''; ?>>Historial por fecha de cierre</option>
                                    </select>
                                </div>
                                <div class="col-md-3" id="grupo_fecha" style="<?php echo $modo === 'historico' ? '' : 'display:none;'; ?>">
                                    <label for="fecha" class="form-label fw-bold">Fecha del cierre:</label>
                                    <input type="date" class="form-control" name="fecha" id="fecha" value="<?php echo htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-3" id="grupo_cierre" style="<?php echo $modo === 'historico' ? '' : 'display:none;'; ?>">
                                    <label for="id_cierre" class="form-label fw-bold">Cierre:</label>
                                    <select name="id_cierre" id="id_cierre" class="form-select">
                                        <?php if (empty($cierresDisponibles)): ?>
                                            <option value="0">Sin cierres en esta fecha</option>
                                        <?php else: ?>
                                            <?php foreach ($cierresDisponibles as $cierreItem): ?>
                                                <option value="<?php echo $cierreItem['id_cierre']; ?>" <?php echo ((int)$id_cierre === (int)$cierreItem['id_cierre']) ? 'selected' : ''; ?>>
                                                    Cierre #<?php echo $cierreItem['id_cierre'] . ' (' . $cierreItem['hora_ingreso'] . ')'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-grow-1">
                                        <i class="bi bi-search"></i> Consultar
                                    </button>
                                    <button type="button" class="btn btn-secondary flex-grow-1" onclick="imprimirStock()">
                                        <i class="bi bi-printer"></i> Imprimir
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if ($esHistorico): ?>
                        <div class="alert alert-info d-flex align-items-center justify-content-between mb-4" role="alert">
                            <div>
                                <i class="bi bi-clock-history me-2"></i>
                                <strong>Historial de Stock:</strong> Mostrando el inventario registrado en el <strong>Cierre #<?php echo $id_cierre; ?></strong> del día <strong><?php echo htmlspecialchars($cierreFechaHora, ENT_QUOTES, 'UTF-8'); ?></strong>.
                                <span class="badge bg-secondary ms-2">Solo lectura</span>
                            </div>
                            <a href="stock.php" class="btn btn-sm btn-outline-primary">Ver stock actual en línea</a>
                        </div>
                    <?php elseif ($modo === 'historico' && empty($cierresDisponibles)): ?>
                        <div class="alert alert-warning mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            No se encontraron cierres de caja registrados para la fecha <strong><?php echo htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?></strong>.
                        </div>
                    <?php endif; ?>

                    <div class="col-sm mx-auto">
                        <h3 class="text-left">Productos con stock</h3>
                    </div>
                    <br>
                    <div class="table-responsive">
                        <table id="stock_productos" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th>Categoría</th>
                                    <th>Centraliza</th>
                                    <th>Stock</th>
                                    <th>Precio compra</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($listaProductos as $prod) {
                                    $claseEditable = !$esHistorico ? 'editable-stock' : '';
                                    $dataAttr = !$esHistorico ? 'data-id="' . htmlspecialchars($prod["codigo"], ENT_QUOTES, "UTF-8") . '" data-url="../functions/actualizaproductos.php"' : '';
                                    echo '<tr>
                                        <td>' . htmlspecialchars($prod["codigo"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td>' . htmlspecialchars($prod["descripcion"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td>' . htmlspecialchars($prod["desc_categoria"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td>' . htmlspecialchars($prod["centraliza"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td class="text-end ' . $claseEditable . '" ' . $dataAttr . '>' . number_format($prod["stock"], 3) . '</td>
                                        <td class="text-end">' . number_format($prod["precio_compra"], 2) . '</td>
                                        <td class="text-end">' . number_format($prod["total"], 2) . '</td>
                                    </tr>';
                                }
                                ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-end">Total</th>
                                    <th class="text-end"><?php echo number_format($totalProductos, 3); ?></th>
                                    <th></th>
                                    <th class="text-end"><?php echo number_format($importeProductos, 2); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <br>
                    <div class="col-sm mx-auto">
                        <h3 class="text-left">Categorías con stock</h3>
                    </div>
                    <br>
                    <div class="table-responsive">
                        <table id="stock_categorias" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Categoría</th>
                                    <th>Stock</th>
                                    <th>Precio categoría</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($listaCategorias as $cat) {
                                    $claseEditable = !$esHistorico ? 'editable-stock' : '';
                                    $dataAttr = !$esHistorico ? 'data-id="' . htmlspecialchars($cat["id_categoria"], ENT_QUOTES, "UTF-8") . '" data-url="../functions/actualizacategorias.php"' : '';
                                    echo '<tr>
                                        <td>' . htmlspecialchars($cat["id_categoria"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td>' . htmlspecialchars($cat["desc_categoria"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td class="text-end ' . $claseEditable . '" ' . $dataAttr . '>' . number_format($cat["stock"], 3) . '</td>
                                        <td class="text-end">' . ($cat["precio"] === null ? '—' : number_format($cat["precio_val"], 2)) . '</td>
                                        <td class="text-end">' . number_format($cat["total"], 2) . '</td>
                                    </tr>';
                                }
                                ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2" class="text-end">Total</th>
                                    <th class="text-end"><?php echo number_format($totalCategorias, 3); ?></th>
                                    <th></th>
                                    <th class="text-end"><?php echo number_format($importeCategorias, 2); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Div de impresión idéntico a cierre_dia.php -->
            <div id="div_impresion" style="display:none">
                <style>
                    #tabla_stock_prod_imp, #tabla_stock_prod_imp table, #tabla_stock_prod_imp th, #tabla_stock_prod_imp td,
                    #tabla_stock_cat_imp, #tabla_stock_cat_imp table, #tabla_stock_cat_imp th, #tabla_stock_cat_imp td {
                        border: 1px solid;
                        border-collapse: collapse;
                        font-size: 10px;
                    }
                    #encabezado_stock, #encabezado_stock tr, #encabezado_stock td {
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
                    <h4><?php echo $esHistorico ? "Historial de Stock - Cierre #$id_cierre" : "Reporte de Stock - En línea"; ?></h4>
                </div>
                <div style="text-align: center">
                    <div id="header_info_stock">
                        <table id="encabezado_stock" style="width:100%;">
                            <tr>
                                <td>Fecha / Hora: <?php echo htmlspecialchars($cierreFechaHora, ENT_QUOTES, "UTF-8"); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div style="text-align: center; margin-top: 5px;">
                        <img src="../img/logo_1.jpeg" alt="Carnicería Cano" width="80" height="80">
                    </div>
                </div>
                <br>
                <h5>Productos con stock</h5>
                <div id="tabla_stock_prod_imp">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Categoría</th>
                                <th>Centraliza</th>
                                <th class="text-end">Stock</th>
                                <th class="text-end">Precio compra</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($listaProductos)): ?>
                                <tr><td colspan="7" class="text-center">Sin productos con stock</td></tr>
                            <?php else: ?>
                                <?php foreach ($listaProductos as $prod): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($prod['codigo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($prod['descripcion'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($prod['desc_categoria'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($prod['centraliza'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-end"><?php echo number_format($prod['stock'], 3); ?></td>
                                        <td class="text-end"><?php echo number_format($prod['precio_compra'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($prod['total'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Total</th>
                                <th class="text-end"><?php echo number_format($totalProductos, 3); ?></th>
                                <th></th>
                                <th class="text-end"><?php echo number_format($importeProductos, 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <br>
                <h5>Categorías con stock</h5>
                <div id="tabla_stock_cat_imp">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Categoría</th>
                                <th class="text-end">Stock</th>
                                <th class="text-end">Precio categoría</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($listaCategorias)): ?>
                                <tr><td colspan="5" class="text-center">Sin categorías con stock</td></tr>
                            <?php else: ?>
                                <?php foreach ($listaCategorias as $cat): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $cat['id_categoria'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($cat['desc_categoria'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-end"><?php echo number_format($cat['stock'], 3); ?></td>
                                        <td class="text-end"><?php echo ($cat['precio'] === null ? '—' : number_format($cat['precio_val'], 2)); ?></td>
                                        <td class="text-end"><?php echo number_format($cat['total'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2" class="text-end">Total</th>
                                <th class="text-end"><?php echo number_format($totalCategorias, 3); ?></th>
                                <th></th>
                                <th class="text-end"><?php echo number_format($importeCategorias, 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <br>
                <div style="font-size: 11px; font-weight: bold; text-align: right; border-top: 1px solid #000; padding-top: 4px;">
                    Total General Inventario: $<?php echo number_format($importeProductos + $importeCategorias, 2); ?>
                </div>
            </div>
        </main>

        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
            function toggleModo(valor) {
                if (valor === 'historico') {
                    $('#grupo_fecha').show();
                    $('#grupo_cierre').show();
                } else {
                    $('#grupo_fecha').hide();
                    $('#grupo_cierre').hide();
                }
            }

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

            function imprimirStock() {
                abrepaginaimpresion("div_impresion");
            }

            $(document).ready(function () {
                const language = {
                    "emptyTable": "No hay información",
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

                $('#stock_productos').DataTable({
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
                                    '<tr class="category-group"><td colspan="6">' +
                                    $('<div>').text(nombreCategoria).html() +
                                    '</td></tr>'
                                );
                                ultimaCategoria = nombreCategoria;
                            }
                        });
                    }
                });

                $('#stock_categorias').DataTable({
                    language: language,
                    pageLength: 25,
                    order: [[1, 'asc']]
                });

                $('#fecha').on('change', function () {
                    const f = $(this).val();
                    if (!f) return;
                    $.getJSON('stock.php?action=obtener_cierres&fecha=' + encodeURIComponent(f), function (data) {
                        const $select = $('#id_cierre');
                        $select.empty();
                        if (data.cierres && data.cierres.length > 0) {
                            $.each(data.cierres, function (i, c) {
                                $select.append($('<option>', {
                                    value: c.id_cierre,
                                    text: 'Cierre #' + c.id_cierre + ' (' + c.hora_ingreso + ')'
                                }));
                            });
                        } else {
                            $select.append($('<option>', {
                                value: '0',
                                text: 'Sin cierres en esta fecha'
                            }));
                        }
                    });
                });

                $(document).on('dblclick', '.editable-stock', function () {
                    const $cell = $(this);
                    if ($cell.find('input').length > 0) {
                        return;
                    }

                    const originalText = $cell.text().trim();
                    const originalValue = originalText.replace(/,/g, '');
                    const $input = $('<input type="number" step="0.001" class="form-control form-control-sm stock-input">').val(originalValue);

                    $cell.empty().append($input);
                    $input.trigger('focus').select();
                    let cerrado = false;

                    function cancelar() {
                        if (cerrado) {
                            return;
                        }
                        cerrado = true;
                        $cell.text(originalText);
                    }

                    function guardar() {
                        if (cerrado) {
                            return;
                        }
                        const nuevoValor = $input.val();
                        if (nuevoValor === '' || isNaN(parseFloat(nuevoValor))) {
                            cancelar();
                            return;
                        }
                        cerrado = true;

                        $.ajax({
                            url: $cell.data('url'),
                            type: 'POST',
                            data: {
                                id: $cell.data('id'),
                                value: nuevoValor,
                                columnName: 'almacen'
                            },
                            success: function () {
                                $cell.text(parseFloat(nuevoValor).toFixed(3));
                            },
                            error: function () {
                                alert('No se pudo actualizar el stock.');
                                cancelar();
                            }
                        });
                    }

                    $input.on('keydown', function (event) {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            guardar();
                        }
                        if (event.key === 'Escape') {
                            event.preventDefault();
                            cancelar();
                        }
                    });

                    $input.on('blur', guardar);
                });
            });
        </script>
    </body>
</html>
