<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}
?>

<?php
require_once "../functions/config.php";
date_default_timezone_set("America/Mexico_City");
// Define variables and initialize with empty values
$id_sucursal = $_SESSION["id_sucursal"];
$id_categoria = '0';
if (isset($_POST['fecha1'])) {
    $fecha1 = $_POST['fecha1'];
} else {
    $fecha1 = date('Y-m-d');
}

if (isset($_POST['fecha2'])) {
    $fecha2 = $_POST['fecha2'];
} else {
    $fecha2 = date('Y-m-d');
}

if (isset($_POST['id_categoria'])) {
    $id_categoria = $_POST['id_categoria'];
}


//$id_proveedor = $_GET['id_proveedor'];
//$proveedor = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_proveedores where id_proveedor = $id_proveedor"));
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Carnicería Cano">
        <meta name="author" content="Gerardo Bautista">
        <link rel="shortcut icon" href="../img/logo_1.png">
        <title>Reporte por fecha</title>

        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery-ui.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <script src="../js/sum().js"></script>
        <script src="../js/jquery.jeditable.js" type="text/javascript"></script>
        <script src="../js/jquery.dataTables.editable.js" type="text/javascript"></script>
        <script src="../js/jquery.jeditable.js" type="text/javascript"></script>
        <script src="../js/jquery.validate.js" type="text/javascript"></script>
        <script src="../js/gijgo.min.js" type="text/javascript"></script>
        <script src="../js/xlsx.full.min.js"></script>




        <style>
            @import "../css/bootstrap.css";
        </style>


        <!-- Custom styles for this template -->
        <link href="../css/navbar.css" rel="stylesheet">
        <link href="../css/jquery.dataTables.min.css" rel="stylesheet">
        <link href="../css/gijgo.min.css" rel="stylesheet" type="text/css" />

    </head>
    <body>
        <main>
            <div class="container">
                <?php require_once "../components/nav.php" ?>
                <div>
                    <div class="bg-light p-4 rounded ">
                        <div class="col-sm-8 mx-auto">
                            <h1 class="text-center">Compras por fecha</h1>
                        </div>
                        <br>
                        <br>
                        <form class="row g-3 needs-validation" action="#" method="post" novalidate>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="Fecha" class="form-label">Seleccione la fecha de inicio:</label>
                                    <input name="fecha1" id="datepicker1" autocomplete="off" readonly="" value="<?php echo $fecha1 ?>" style="max-width: 300px;"/>
                                </div>
                                <div class="col-6">
                                    <label for="Fecha" class="form-label">Seleccione la fecha de fin:</label>
                                    <input name="fecha2" id="datepicker2" autocomplete="off" readonly="" value="<?php echo $fecha2 ?>" style="max-width: 300px;"/>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="inputState" class="form-label">Categoría</label>
                                    <select id="id_categoria" name="id_categoria" class="form-select" required>
                                        <option value="0">Seleccione...</option>
                                        <?php
//$query = $link -> query ("SELECT * FROM sbb_telas");
                                        $query = mysqli_query($link, "SELECT * FROM cc_categorias where id_sucursal = $id_sucursal order by mayoreo,desc_categoria");
                                        while ($valores = mysqli_fetch_array($query)) {
                                            $mayoreo = $valores['mayoreo'] ? 'MAYOREO' : 'MENUDEO';
                                            if ($valores['id_categoria'] == $id_categoria) {
                                                echo '<option value="' . $valores['id_categoria'] . '" selected="selected">' . $valores['desc_categoria'] . ' - ' . $mayoreo . '</option>';
                                            } else {
                                                echo '<option value="' . $valores['id_categoria'] . '">' . $valores['desc_categoria'] . ' - ' . $mayoreo . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="text-center p-3" >
                                <input class="btn btn-primary black bg-silver" type="submit" value="Buscar" id="buscar_fecha">
                            </div>

                        </form>


                        <br>
                        <div class="col-sm mx-auto">
                            <h3 class="text-left">Detalle de Compras</h3>
                        </div>
                        <br>
                        <div class="table-responsive">
                            <table id="compras" class="display" style="width:100%" >
                                <thead>
                                    <tr>
                                        <th>Id</th>
                                        <th>Fecha</th>
                                        <th>Hora</th>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th>Cliente</th>
                                        <th>Precio C</th>
                                        <th>Cantidad</th>
                                        <th>Imp C</th>
                                    </tr>
                                </thead>
                                <?php
                                $sqlcompras = mysqli_query($link, "SELECT a.id_compra,a.fecha_ingreso,a.hora_ingreso,b.codigo,b.precio_compra,b.precio_compra,b.cantidad,"
                                        . "round(b.precio_compra * b.cantidad,2) as importec, round(b.precio_compra * b.cantidad,2) as importev, a.id_proveedor, b.id_consecutivo,b.estatus "
                                        . "FROM cc_det_compras as a inner join cc_compras as b on a.id_sucursal = b.id_sucursal and a.id_compra = b.id_compra "
                                        . "WHERE a.id_sucursal = '$id_sucursal' and a.fecha_ingreso between '$fecha1' and '$fecha2' and a.estatus in (1,3)"
                                        . " order by a.id_compra DESC");

                                $renglon = $ganancia = 0;
                                while ($rowc = mysqli_fetch_assoc($sqlcompras)) {
                                    $renglon = $renglon + 1;
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = 'ROL' and id_clave =" . $rowp['rol']));
                                    $producto = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_productos where id_sucursal = '$id_sucursal' and codigo =" . $rowc['codigo']));
                                    $proveedor = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_proveedores where id_sucursal = '$id_sucursal' and id_proveedor =" . $rowc['id_proveedor']));
                                    if ($id_categoria == '0' or ($producto['id_categoria'] == $id_categoria)) {
                                        if (empty($proveedor)) {
                                            $nombre_proveedor = '';
                                        } else {
                                            $nombre_proveedor = $proveedor['nombre_proveedor'];
                                        }
                                        if ($rowc['estatus'] == 2) {
                                            $estatus = "C";
                                            $importeC = 0;
                                            $importeV = 0;
                                            $ganancia = 0;
                                        } else {
                                            $estatus = "N";
                                            $importeC = $rowc['importec'];
                                            $importeV = $rowc['importev'];
                                        }

                                        echo '
                                    <tr id="fila' . $renglon . '">
                                        <td>' . $rowc['id_compra'] . '</td>
                                        <td>' . $rowc['fecha_ingreso'] . '</td>
                                        <td>' . $rowc['hora_ingreso'] . '</td>
                                        <td>' . $rowc['codigo'] . '</td>
                                        <td>' . $producto['descripcion'] . '</td>    
                                        <td>' . $nombre_proveedor . '</td>
                                        <td>' . $rowc['precio_compra'] . '</td>
                                        <td>' . $rowc['cantidad'] . '</td>
                                        <td>' . $importeC . '</td>
                                    </tr>
                                        ';
                                    }
                                }
                                ?>  
                                <tfoot>
                                    <tr>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th id="totales"></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>

                                    </tr>
                                </tfoot>
                            </table> 
                        </div>
                        <br>
                        <form  action="#" method="post" novalidate id="form_cancelar">
                            <input type="hidden" name="id_compra" id="id_compra">
                            <input type="hidden" name="id_consecutivo" id="id_consecutivo" >
                            <input type="hidden" name="movimiento" id="movimiento" >
                        </form>  
                        <br>
                        <div class="align-content-center text-center">
                            <a href="#" onclick="htmlTableToExcel('xlsx')" class="btn btn-primary m-1" role="button" id="addRow">Extrae Excel</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>



        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
                                var p;
                                $(document).ready(function () {
                                    var v = $('#compras').DataTable(
                                            {
                                                language: {
                                                    "decimal": "",
                                                    "emptyTable": "No hay información",
                                                    "info": "Mostrando _START_ a _END_ de _TOTAL_ Entradas",
                                                    "infoEmpty": "Mostrando 0 to 0 of 0 Entradas",
                                                    "infoFiltered": "(Filtrado de _MAX_ total entradas)",
                                                    "infoPostFix": "",
                                                    "thousands": ",",
                                                    "lengthMenu": "Mostrar _MENU_ Entradas",
                                                    "loadingRecords": "Cargando...",
                                                    "processing": "Procesando...",
                                                    "search": "Buscar:",
                                                    "zeroRecords": "Sin resultados encontrados",
                                                    "paginate": {
                                                        "first": "Primero",
                                                        "last": "Ultimo",
                                                        "next": "Siguiente",
                                                        "previous": "Anterior"
                                                    }

                                                },
                                                footerCallback: function () {
                                                <?php
                                                    if (tienePermiso('ver')) {
                                                    echo '
                                                    var api = this.api();
                                                    $(api.column(4).footer()).html(\'Total de compra\');
                                                    $(api.table().column(7).footer()).html(api.column(7, {page: \'current\'}).data().sum().toFixed(2));
                                                    $(api.table().column(8).footer()).html(api.column(8, {page: \'current\'}).data().sum().toFixed(2));
                                                        ';
                                                    }
                                                ?>
                                                },
                                                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Mostrar todo']],

                                            }
                                    );
                                });
                                $('#datepicker1').datepicker({
                                    uiLibrary: 'bootstrap5',
                                    format: 'yyyy-mm-dd'
                                });
                                $('#datepicker2').datepicker({
                                    uiLibrary: 'bootstrap5',
                                    format: 'yyyy-mm-dd'
                                });

                                function remove(objeto, id_pago) {
                                    var table = $('#pagos').DataTable();
                                    table.row($(objeto).parents('tr')).remove().draw();
                                    elimina_pago(id_pago);
                                }

                                function htmlTableToExcel(type) {
                                    const table = document.getElementById('compras');
                                    const datosTabla = [];
                                    const filas = table.rows;

                                    for (let i = 0; i < filas.length; i++) {
                                        const celdas = filas[i].cells;
                                        const filaTabla = [];

                                        for (let j = 0; j < celdas.length; j++) {
                                            filaTabla.push(celdas[j].innerText);
                                        }

                                        datosTabla.push(filaTabla);
                                    }

                                    const workbook = XLSX.utils.book_new();
                                    const worksheet = XLSX.utils.aoa_to_sheet(datosTabla);
                                    XLSX.utils.book_append_sheet(workbook, worksheet, 'Hoja1');
                                    XLSX.writeFile(workbook, 'tabla.xlsx');
                                }
                                function cancelar_reactivar(id_compra, id_consecutivo, movimiento)
                                {
                                    $('#id_compra').val(id_compra);
                                    $('#id_consecutivo').val(id_consecutivo);
                                    $('#movimiento').val(movimiento);
                                    $("#form_cancelar").submit();
                                }
        </script>      
    </body>
</html>

