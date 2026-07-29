<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";
date_default_timezone_set("America/Mexico_City");

$id_sucursal = (int) $_SESSION["id_sucursal"];

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
        COALESCE(AVG(p.precio_compra), 0) AS precio_compra
    FROM cc_categorias c
    LEFT JOIN cc_productos p
        ON p.id_sucursal = c.id_sucursal
       AND p.id_categoria = c.id_categoria
       AND p.centralizar_almacen = 2
    WHERE c.id_sucursal = $id_sucursal
      AND c.almacen <> 0
    GROUP BY c.id_categoria, c.desc_categoria, c.almacen
    ORDER BY c.desc_categoria
");
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
                                $totalProductos = 0;
                                $importeProductos = 0;
                                while ($row = mysqli_fetch_assoc($sqlProductos)) {
                                    $stock = (float) $row["almacen"];
                                    $precioCompra = (float) $row["precio_compra"];
                                    $totalProducto = $stock < 0 ? 0 : round($stock * $precioCompra, 2);
                                    $totalProductos += $stock;
                                    $importeProductos += $totalProducto;
                                    echo '<tr>
                                        <td>' . htmlspecialchars($row["codigo"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td>' . htmlspecialchars($row["descripcion"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td>' . htmlspecialchars(($row["desc_categoria"] ?? ""), ENT_QUOTES, "UTF-8") . '</td>
                                        <td>' . htmlspecialchars(($row["centraliza"] ?? ""), ENT_QUOTES, "UTF-8") . '</td>
                                        <td class="text-end editable-stock" data-id="' . htmlspecialchars($row["codigo"], ENT_QUOTES, "UTF-8") . '" data-url="../functions/actualizaproductos.php">' . number_format($stock, 3) . '</td>
                                        <td class="text-end">' . number_format($precioCompra, 2) . '</td>
                                        <td class="text-end">' . number_format($totalProducto, 2) . '</td>
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
                                    <th>Precio compra promedio</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $totalCategorias = 0;
                                $importeCategorias = 0;
                                while ($row = mysqli_fetch_assoc($sqlCategorias)) {
                                    $stock = (float) $row["almacen"];
                                    $precioCompra = (float) $row["precio_compra"];
                                    $totalCategoria = $stock < 0 ? 0 : round($stock * $precioCompra, 2);
                                    $totalCategorias += $stock;
                                    $importeCategorias += $totalCategoria;
                                    echo '<tr>
                                        <td>' . htmlspecialchars($row["id_categoria"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td>' . htmlspecialchars($row["desc_categoria"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td class="text-end editable-stock" data-id="' . htmlspecialchars($row["id_categoria"], ENT_QUOTES, "UTF-8") . '" data-url="../functions/actualizacategorias.php">' . number_format($stock, 3) . '</td>
                                        <td class="text-end">' . number_format($precioCompra, 2) . '</td>
                                        <td class="text-end">' . number_format($totalCategoria, 2) . '</td>
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
        </main>

        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
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
