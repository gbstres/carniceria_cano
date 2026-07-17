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
        ca.descripcion_corta AS centraliza
    FROM cc_productos p
    LEFT JOIN cc_categorias c
        ON c.id_sucursal = p.id_sucursal
       AND c.id_categoria = p.id_categoria
    LEFT JOIN cc_claves ca
        ON ca.nombre_clave = 'CENTRALIZAR_ALMACEN'
       AND ca.clave = p.centralizar_almacen
    WHERE p.id_sucursal = $id_sucursal
      AND p.almacen <> 0
    ORDER BY c.desc_categoria, p.descripcion
");

$sqlCategorias = mysqli_query($link, "
    SELECT
        id_categoria,
        desc_categoria,
        almacen
    FROM cc_categorias
    WHERE id_sucursal = $id_sucursal
      AND almacen <> 0
    ORDER BY desc_categoria
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $totalProductos = 0;
                                while ($row = mysqli_fetch_assoc($sqlProductos)) {
                                    $totalProductos += (float) $row["almacen"];
                                    echo '<tr>
                                        <td>' . htmlspecialchars($row["codigo"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td>' . htmlspecialchars($row["descripcion"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td>' . htmlspecialchars(($row["desc_categoria"] ?? ""), ENT_QUOTES, "UTF-8") . '</td>
                                        <td>' . htmlspecialchars(($row["centraliza"] ?? ""), ENT_QUOTES, "UTF-8") . '</td>
                                        <td class="text-end">' . number_format((float) $row["almacen"], 3) . '</td>
                                    </tr>';
                                }
                                ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-end">Total</th>
                                    <th class="text-end"><?php echo number_format($totalProductos, 3); ?></th>
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $totalCategorias = 0;
                                while ($row = mysqli_fetch_assoc($sqlCategorias)) {
                                    $totalCategorias += (float) $row["almacen"];
                                    echo '<tr>
                                        <td>' . htmlspecialchars($row["id_categoria"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td>' . htmlspecialchars($row["desc_categoria"], ENT_QUOTES, "UTF-8") . '</td>
                                        <td class="text-end">' . number_format((float) $row["almacen"], 3) . '</td>
                                    </tr>';
                                }
                                ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2" class="text-end">Total</th>
                                    <th class="text-end"><?php echo number_format($totalCategorias, 3); ?></th>
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
                    order: [[2, 'asc'], [1, 'asc']]
                });

                $('#stock_categorias').DataTable({
                    language: language,
                    pageLength: 25,
                    order: [[1, 'asc']]
                });
            });
        </script>
    </body>
</html>
