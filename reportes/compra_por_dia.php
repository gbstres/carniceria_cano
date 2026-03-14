<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";
date_default_timezone_set("America/Mexico_City");
// Define variables and initialize with empty values
$id_sucursal = $_SESSION["id_sucursal"];
$_SESSION['carpeta'] = '../';
$id_categoria = '0';
if (isset($_POST['fecha'])) {
    $fecha = $_POST['fecha'];
    if (isset($_POST['id_categoria'])) {
        $id_categoria = $_POST['id_categoria'];
    }
} else {
    $fecha = date('Y-m-d');
}

if (isset($_POST['movimiento'])) {

    $movimiento = $_POST['movimiento'];
    $id_compra = $_POST['id_compra'];
    $id_consecutivo = $_POST['id_consecutivo'];
    $id_proveedor = $_POST['id_proveedor'];
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('y-m-d');
    $hora_act = date('H:i:s');

    $update1 = mysqli_query($link, "UPDATE cc_compras SET "
                    . "estatus='$movimiento', "
                    . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                    . "WHERE id_sucursal='$id_sucursal' and id_compra = '$id_compra' and id_consecutivo = '$id_consecutivo'")
            or die(mysqli_error());
    if ($update1) {
        if ($id_proveedor != 0) {
            $row_importe = mysqli_fetch_assoc(mysqli_query($link, "SELECT sum(cantidad * precio_compra) as 'importe' FROM `cc_compras` WHERE id_sucursal = '$id_sucursal' and id_compra = $id_compra and id_consecutivo = '$id_consecutivo'"));
            $importe = $row_importe['importe'];
            if ($movimiento == 2) {
                $importe = $importe * -1;
            }
            recalcula($link, $id_sucursal, $importe, $id_proveedor, $fecha_act, $hora_act, $id_usuario_act);
        }
        recalcula_almacen_compra($link, $id_sucursal, $id_compra, $id_consecutivo, $fecha_act, $hora_act, $id_usuario_act);
    } else {
        echo '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>Error, no se pudo guardar el producto.</div>';
    }
}

function recalcula($link, $id_sucursal, $importe, $id_proveedor, $fecha_act, $hora_act, $id_usuario_act) {
    mysqli_query($link, "UPDATE cc_saldos_proveedors SET efectivo_hoy = efectivo_hoy + $importe, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and id_proveedor = $id_proveedor");
}

function recalcula_almacen_compra($link, $id_sucursal, $id_compra, $id_consecutivo, $fecha_act, $hora_act, $id_usuario_act) {
    $sqlcompras = mysqli_query($link, "
SELECT 
    a.codigo,
    b.centralizar_almacen,
    c.codigo_p,
    c.codigo_d,
    c.porcentaje,
    case a.estatus 
    when 2 THEN a.cantidad * -1
    ELSE a.cantidad
    END cantidad,
    b.id_categoria,
    ROW_NUMBER() OVER(PARTITION BY a.codigo ORDER BY a.codigo) as contador,
    d.centralizar_almacen as centralizar_almacen_d,
    d.id_categoria as id_categoria_d
FROM cc_compras a 
INNER JOIN cc_productos b ON a.id_sucursal = b.id_sucursal AND a.codigo = b.codigo 
LEFT JOIN cc_derivados c ON a.id_sucursal = c.id_sucursal AND a.codigo = c.codigo_p
LEFT JOIN cc_productos d ON c.id_sucursal = d.id_sucursal AND c.codigo_d = d.codigo
WHERE a.id_sucursal = $id_sucursal AND a.id_compra = $id_compra AND a.id_consecutivo = $id_consecutivo;");
    while ($rowv = mysqli_fetch_assoc($sqlcompras)) {
        if ($rowv['codigo_p'] == null) {
            if ($rowv['contador'] == 1) {
                if ($rowv['centralizar_almacen'] == 1) {
                    recalcula_almacen_producto($link, $id_sucursal, $rowv['codigo'], $rowv['cantidad'], $fecha_act, $hora_act, $id_usuario_act);
                } else if ($rowv['centralizar_almacen'] == 2) {
                    recalcula_almacen_categoria($link, $id_sucursal, $rowv['id_categoria'], $rowv['cantidad'], $fecha_act, $hora_act, $id_usuario_act);
                }
            }
        } else {
            if ($rowv['centralizar_almacen_d'] == 1) {
                recalcula_almacen_producto($link, $id_sucursal, $rowv['codigo_d'], $rowv['cantidad'], $fecha_act, $hora_act, $id_usuario_act);
            } else if ($rowv['centralizar_almacen'] == 2) {
                $cantidad = round($rowv['cantidad'] * $rowv['porcentaje'] / 100, 3);
                recalcula_almacen_categoria($link, $id_sucursal, $rowv['id_categoria_d'], $cantidad, $fecha_act, $hora_act, $id_usuario_act);
            }
        }
    }
}

function recalcula_almacen_producto($link, $id_sucursal, $codigo, $cantidad, $fecha_act, $hora_act, $id_usuario_act) {
    mysqli_query($link, "UPDATE cc_productos SET almacen = almacen + $cantidad, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and codigo = $codigo");
}

function recalcula_almacen_categoria($link, $id_sucursal, $id_categoria, $cantidad, $fecha_act, $hora_act, $id_usuario_act) {
    mysqli_query($link, "UPDATE cc_categorias SET almacen = almacen + $cantidad, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and id_categoria = $id_categoria");
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
        <title>Reporte x día</title>

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
                            <h1 class="text-center">Compras por día</h1>
                        </div>
                        <br>
                        <br>
                        <form class="row g-3" action="#" method="post" novalidate>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="Fecha" class="form-label">Seleccione la fecha:</label>
                                    <input name="fecha" id="datepicker" width="276" autocomplete="off" readonly="" value="<?php echo $fecha ?>"/>
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
                            <br>
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
                                        <th>Categoria</th>
                                        <th>Proveedor</th>
                                        <th>Precio C</th>
                                        <th>Cantidad</th>
                                        <th>Imp C</th>
                                        <th>T. V.</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <?php
                                $sqlcompras = mysqli_query($link, "SELECT a.id_compra,a.fecha_ingreso,a.hora_ingreso,b.codigo,b.precio_compra,b.precio_compra,b.cantidad,"
                                        . "round(b.precio_compra * b.cantidad,2) as importec,round(b.precio_compra * b.cantidad,2) as importev, a.id_proveedor, b.id_consecutivo,b.estatus "
                                        . "FROM cc_det_compras as a inner join cc_compras as b on a.id_sucursal = b.id_sucursal and a.id_compra = b.id_compra "
                                        . "WHERE a.id_sucursal = '$id_sucursal' and a.fecha_ingreso = '$fecha' and a.estatus in (1,3)"
                                        . " order by a.id_compra DESC");

                                $renglon = 0;
                                while ($rowc = mysqli_fetch_assoc($sqlcompras)) {

                                    $renglon = $renglon + 1;
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = 'ROL' and id_clave =" . $rowp['rol']));
                                    $producto = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_productos where id_sucursal = '$id_sucursal' and codigo =" . $rowc['codigo']));
                                    if ($id_categoria == '0' or ($producto['id_categoria'] == $id_categoria)) {
                                        $proveedor = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_proveedores where id_sucursal = '$id_sucursal' and id_proveedor =" . $rowc['id_proveedor']));
                                        $total_id = mysqli_fetch_assoc(mysqli_query($link, "SELECT sum(round(cantidad * precio_compra,2)) total_id FROM cc_compras WHERE id_sucursal = $id_sucursal  and id_compra = " . $rowc['id_compra'] . " and estatus not in (2)"));
                                        $categoria = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_categorias where id_sucursal = '$id_sucursal' and id_categoria =" . $producto['id_categoria']));
                                        if (empty($proveedor)) {
                                            $nombre_proveedor = '';
                                        } else {
                                            $nombre_proveedor = $proveedor['nombre_proveedor'];
                                        }
                                        if ($rowc['estatus'] == 2) {
                                            $estatus = "C";
                                            $importec = 0;
                                            $importev = 0;
                                        } else {
                                            $estatus = "N";
                                            $importec = $rowc['importec'];
                                            $importev = $rowc['importev'];
                                        }
                                        echo '
                                    <tr id="fila' . $renglon . '">
                                        <td>' . $rowc['id_compra'] . '</td>
                                        <td>' . $rowc['fecha_ingreso'] . '</td>
                                        <td>' . $rowc['hora_ingreso'] . '</td>
                                        <td>' . $rowc['codigo'] . '</td>
                                        <td>' . $producto['descripcion'] . '</td>
                                        <td>' . $categoria['desc_categoria'] . '</td>
                                        <td>' . $nombre_proveedor . '</td>
                                        <td>' . $rowc['precio_compra'] . '</td>
                                        <td>' . $rowc['cantidad'] . '</td>
                                        <td>' . $importec . '</td>
                                        <td>' . $total_id['total_id'] . '</td>
                                        <td align="center">';
                                        if ($rowc['estatus'] == 2) {
                                            echo '<a href="#" onclick="cancelar_reactivar(' . $rowc['id_compra'] . ',' . $rowc['id_consecutivo'] . ',0,' . $rowc['id_proveedor'] . ')" title = "Reactivar"><img class = "imga" src = "../img/icons/check-circle.svg"></a>';
                                        } else {
                                            echo '<a href="javascript:cancelar_reactivar(' . $rowc['id_compra'] . ',' . $rowc['id_consecutivo'] . ',2,' . $rowc['id_proveedor'] . ')"  title = "Cancelar" onclick = "return confirm(\'¿Esta seguro de cancelar la compra ' . $rowc['id_compra'] . '?\')"><img class = "imga" src = "../img/icons/x-circle.svg"></a>';
                                        }
                                        echo '
                                            <a href="edicion_compras.php?id_compra=' . $rowc['id_compra'] . '"  title="Editar compra"><img class="imga" src="../img/icons/pencil-square.svg"></a>
                                        </td>
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
                                        <th id="total_compra"></th>
                                        <th></th>
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
                            <input type="hidden" name="id_compra" id="id_compra">
                            <input type="hidden" name="id_consecutivo" id="id_consecutivo" >
                            <input type="hidden" name="movimiento" id="movimiento" >
                            <input type="hidden" name="id_proveedor" id="id_proveedor" >
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
                                                    }},
                                                footerCallback: function () {
                                                <?php
                                                    if (tienePermiso('ver')) {
                                                    echo '
                                                    var api = this.api();
                                                    $(api.column(4).footer()).html(\'Total de compra\');
                                                    $(api.table().column(8).footer()).html(api.column(8, {page: \'current\'}).data().sum().toFixed(2));
                                                    $(api.table().column(9).footer()).html(api.column(9, {page: \'current\'}).data().sum().toFixed(2));
                                                    ';
                                                    }
                                                ?>
                                                },
                                                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Mostrar todo']],
                                            });
                                });
                                $('#datepicker').datepicker({
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
                                            if (j !== 13) {
                                                filaTabla.push(celdas[j].innerText);
                                            }
                                        }

                                        datosTabla.push(filaTabla);
                                    }

                                    const workbook = XLSX.utils.book_new();
                                    const worksheet = XLSX.utils.aoa_to_sheet(datosTabla);
                                    XLSX.utils.book_append_sheet(workbook, worksheet, 'Hoja1');
                                    XLSX.writeFile(workbook, 'tabla.xlsx');
                                }
                                function cancelar_reactivar(id_compra, id_consecutivo, movimiento, id_proveedor)
                                {
                                    $('#id_compra').val(id_compra);
                                    $('#id_consecutivo').val(id_consecutivo);
                                    $('#movimiento').val(movimiento);
                                    $('#id_proveedor').val(id_proveedor);
                                    $("#form_cancelar").submit();
                                }
        </script>      
    </body>
</html>

