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
require_once "../config.php";
date_default_timezone_set("America/Mexico_City");
// Define variables and initialize with empty values
$id_sucursal = $_SESSION["id_sucursal"];
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

//$id_cliente = $_GET['id_cliente'];
//$cliente = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_clientes where id_cliente = $id_cliente"));
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Carnicería Cano">
        <meta name="author" content="Gerardo Bautista">
        <link rel="shortcut icon" href="img/logo_1.png">
        <title>Reporte por fecha</title>

        <script src="js/jquery-3.5.1.js"></script>
        <script src="js/jquery-ui.js"></script>
        <script src="js/jquery.dataTables.min.js"></script>
        <script src="js/sum().js"></script>
        <script src="js/jquery.jeditable.js" type="text/javascript"></script>
        <script src="js/jquery.dataTables.editable.js" type="text/javascript"></script>
        <script src="js/jquery.jeditable.js" type="text/javascript"></script>
        <script src="js/jquery.validate.js" type="text/javascript"></script>
        <script src="js/gijgo.min.js" type="text/javascript"></script>
        <script src="js/xlsx.full.min.js"></script>




        <style>
            @import "css/bootstrap.css";
        </style>


        <!-- Custom styles for this template -->
        <link href="css/navbar.css" rel="stylesheet">
        <link href="css/jquery.dataTables.min.css" rel="stylesheet">
        <link href="css/gijgo.min.css" rel="stylesheet" type="text/css" />

    </head>
    <body>
        <main>
            <div class="container">
                <?php require_once "nav.php" ?>
                <div>
                    <div class="bg-light p-4 rounded ">
                        <div class="col-sm-8 mx-auto">
                            <h1 class="text-center">Ventas por fecha</h1>
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
                            <div class="col-12 text-center">
                                <input class="btn btn-primary black bg-silver" type="submit" value="Buscar" id="buscar_fecha">
                            </div>

                        </form>


                        <br>
                        <div class="col-sm mx-auto">
                            <h3 class="text-left">Detalle de Ventas</h3>
                        </div>
                        <br>
                        <div class="table-responsive">
                            <table id="ventas" class="display" style="width:100%" >
                                <thead>
                                    <tr>
                                        <th>Id</th>
                                        <th>Fecha</th>
                                        <th>Hora</th>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th>Cliente</th>
                                        <th>Precio C</th>
                                        <th>Precio V</th>
                                        <th>Cantidad</th>
                                        <th>Importe</th>
                                        <th>Ganancia</th>
                                    </tr>
                                </thead>
                                <?php
                                $sqlventas = mysqli_query($link, "SELECT a.id_venta,a.fecha_ingreso,a.hora_ingreso,b.codigo,b.precio_compra,b.precio_venta,b.cantidad,"
                                        . "round(b.precio_venta * b.cantidad,2) as importe, a.id_cliente, b.id_consecutivo,b.estatus "
                                        . "FROM cc_det_ventas as a inner join cc_ventas as b on a.id_sucursal = b.id_sucursal and a.id_venta = b.id_venta "
                                        . "WHERE a.id_sucursal = '$id_sucursal' and a.fecha_ingreso between '$fecha1' and '$fecha2' and a.estatus in (1,3)"
                                        . " order by a.id_venta DESC");

                                $renglon = $ganancia = 0 ;
                                while ($rowc = mysqli_fetch_assoc($sqlventas)) {
                                    $renglon = $renglon + 1;
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = 'ROL' and id_clave =" . $rowp['rol']));
                                    $producto = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_productos where id_sucursal = '$id_sucursal' and codigo =" . $rowc['codigo']));
                                    $cliente = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_clientes where id_sucursal = '$id_sucursal' and id_cliente =" . $rowc['id_cliente']));
                                    if (empty($cliente)) {
                                        $nombre_cliente = '';
                                    } else {
                                        $nombre_cliente = $cliente['nombre'] . ' ' . $cliente['apellido_paterno'];
                                    }
                                    if ($rowc['estatus'] == 2) {
                                        $estatus = "C";
                                        $importe = 0;
                                        $ganancia = 0;
                                    } else {
                                        $estatus = "N";
                                        $importe = $rowc['importe'];
                                        $ganancia = round(($rowc['precio_venta'] - $rowc['precio_compra']) * $rowc['cantidad'],2);
                                    }
                                    
                                    echo '
                                    <tr id="fila' . $renglon . '">
                                        <td>' . $rowc['id_venta'] . '</td>
                                        <td>' . $rowc['fecha_ingreso'] . '</td>
                                        <td>' . $rowc['hora_ingreso'] . '</td>
                                        <td>' . $rowc['codigo'] . '</td>
                                        <td>' . $producto['descripcion'] . '</td>    
                                        <td>' . $nombre_cliente . '</td>
                                        <td>' . $rowc['precio_compra'] . '</td>
                                        <td>' . $rowc['precio_venta'] . '</td>
                                        <td>' . $rowc['cantidad'] . '</td>
                                        <td>' . $importe . '</td>
                                        <td>' . $ganancia . '</td>
                                    </tr>
                                        ';
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
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table> 
                        </div>
                        <br>
                        <form  action="#" method="post" novalidate id="form_cancelar">
                            <input type="hidden" name="id_venta" id="id_venta">
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



        <script src="js/bootstrap.bundle.min.js"></script>
        <script>
                                var p;
                                $(document).ready(function () {
                                    var v = $('#ventas').DataTable(
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
                                                    var api = this.api();
                                                    $(api.column(4).footer()).html('Total de venta');
                                                    $(api.table().column(9).footer()).html(api.column(9, {page: 'current'}).data().sum().toFixed(2));
                                                    $(api.table().column(10).footer()).html(api.column(10, {page: 'current'}).data().sum().toFixed(2));
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
                                    const table = document.getElementById('ventas');
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
                                function cancelar_reactivar(id_venta, id_consecutivo, movimiento)
                                {
                                    $('#id_venta').val(id_venta);
                                    $('#id_consecutivo').val(id_consecutivo);
                                    $('#movimiento').val(movimiento);
                                    $("#form_cancelar").submit();
                                }
        </script>      
    </body>
</html>

