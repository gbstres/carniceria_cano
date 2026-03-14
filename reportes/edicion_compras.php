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
$id_sucursal = $_SESSION["id_sucursal"];
if (isset($_POST['movimiento'])) {

    $movimiento = $_POST['movimiento'];
    $id_compra = $_POST['id_compra'];
    $id_consecutivo = $_POST['id_consecutivo'];
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('y-m-d');
    $hora_act = date('H:i:s');

    $update1 = mysqli_query($link, "UPDATE cc_compras SET "
                    . "estatus='$movimiento', "
                    . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                    . "WHERE id_sucursal='$id_sucursal' and id_compra = '$id_compra' and id_consecutivo = '$id_consecutivo'")
            or die(mysqli_error());
    if ($update1) {
        //header("Location: " . $_GET["regresar"]);
        $row_importe = mysqli_fetch_assoc(mysqli_query($link, "SELECT precio_compra * cantidad as 'importe' FROM cc_compras WHERE id_sucursal='$id_sucursal' and id_compra = '$id_compra' and id_consecutivo = '$id_consecutivo'"));
        $row_proveedor = mysqli_fetch_assoc(mysqli_query($link, "SELECT id_proveedor FROM cc_det_compras WHERE id_sucursal='$id_sucursal' and id_compra = '$id_compra'"));
        $abono = $row_importe['importe'];
        $id_proveedor = $row_proveedor['id_proveedor'];
        if ($movimiento == 2) {
            $abono = $abono * -1;
        }
        $efectivo = recalcula($link, $id_sucursal, $abono, $id_proveedor, $hora_act, $fecha_act, $id_usuario_act);
        recalcula_almacen_compra($link, $id_sucursal, $id_compra, $id_consecutivo, $fecha_act, $hora_act, $id_usuario_act);
    } else {
        echo '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>Error, no se pudo guardar el producto.</div>';
    }
}



if (isset($_GET['id_compra'])) {
    $id_compra = $_GET['id_compra'];
    $rowdetcompras = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_det_compras WHERE id_sucursal = '$id_sucursal' and id_compra = $id_compra"));
}

//recalcula saldo proveedor
function recalcula($link, $id_sucursal, $abono, $id_proveedor, $fecha_act, $hora_act, $id_usuario_act) {
    mysqli_query($link, "UPDATE cc_saldos_proveedores SET efectivo_hoy = efectivo_hoy + $abono, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and id_proveedor = $id_proveedor");
    $row_efectivo = mysqli_fetch_assoc(mysqli_query($link, "select sum(efectivo_hoy) as 'efectivo_hoy' from cc_saldos_proveedores where id_sucursal = $id_sucursal and id_proveedor =" . $id_proveedor));
    $efectivo = $row_efectivo['efectivo_hoy'];
    return round($efectivo, 2);
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
?>



<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Carnicería Cano">
        <meta name="author" content="Gerardo Bautista">
        <link rel="shortcut icon" href="../img/logo_1.png">
        <title>Carnicería Cano</title>
        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <script src="../js/sum().js"></script>
        <script src="../js/typeahead.js"></script>
        <script src="../js/jquery-ui.js"></script>
        <script src="../js/jquery.jeditable.js" type="text/javascript"></script>
        <script src="../js/jquery.dataTables.editable.js" type="text/javascript"></script>
        <script src="../js/jquery.jeditable.js" type="text/javascript"></script>
        <script src="../js/jquery.validate.js" type="text/javascript"></script>

        <style>
            @import "../css/bootstrap.css";

            .bd-placeholder-img {
                font-size: 1.125rem;
                text-anchor: middle;
                -webkit-user-select: none;
                -moz-user-select: none;
                user-select: none;
            }

            @media (min-width: 768px) {
                .bd-placeholder-img-lg {
                    font-size: 3.5rem;
                }
            }

            .b-example-divider {
                height: 3rem;
                background-color: rgba(0, 0, 0, .1);
                border: solid rgba(0, 0, 0, .15);
                border-width: 1px 0;
                box-shadow: inset 0 .5em 1.5em rgba(0, 0, 0, .1), inset 0 .125em .5em rgba(0, 0, 0, .15);
            }

            .b-example-vr {
                flex-shrink: 0;
                width: 1.5rem;
                height: 100vh;
            }

            .bi {
                vertical-align: -.125em;
                fill: currentColor;
            }

            .nav-scroller {
                position: relative;
                z-index: 2;
                height: 2.75rem;
                overflow-y: hidden;
            }

            .nav-scroller .nav {
                display: flex;
                flex-wrap: nowrap;
                padding-bottom: 1rem;
                margin-top: -1px;
                overflow-x: auto;
                text-align: center;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
            }

            .typeahead {
                border: 1px solid #000;
                border-radius: 4px;
                padding: 8px 12px;
                max-width: 300px;
                min-width: 290px;
                color: #FFF;
            }
            .tt-menu {
                width:300px;
            }
            ul.typeahead{
                margin:0px;
                padding:0px 0px;
            }
            ul.typeahead.dropdown-menu li a {
                padding: 5px !important;
                border-bottom:#CCC 1px solid;
            }
            ul.typeahead.dropdown-menu li:last-child a {
                border-bottom:0px !important;
            }
            .lista-color {
                max-width: 450px;
                min-width: 290px;
                max-height:340px;
                border-radius:4px;
                text-align:left;
                margin:10px;
                margin-bottom:120px;
            }
            .Busca-pais {
                font-size:1.5em;
                color: #686868;
                font-weight: 700;
                text-align:left
            }
            .dropdown-menu>.active>a, .dropdown-menu>.active>a:focus, .dropdown-menu>.active>a:hover {
                text-decoration: none;
                background-color: #bfbfbf;
                outline: 0;
            }
        </style>


        <!-- Custom styles for this template -->
        <link href="../css/navbar.css" rel="stylesheet">
        <link href="../css/jquery.dataTables.min.css" rel="stylesheet">


    </head>
    <body>
        <main>
            <div class="container">
                <?php require_once "../components/nav.php" ?>
                <div>
                    <div class="bg-light p-2 rounded text-center">
                        <div class="col-sm-8 mx-auto">
                            <h1>Edición de compra</h1>
                        </div>
                        <br>
                        <div style="text-align: center">
                            <h5>
                                <table id=""  style="width:100%;">
                                    <tr><td>Número compra: 
                                            <?php
                                            if (isset($_GET['id_compra'])) {
                                                echo $id_compra;
                                            }
                                            ?>
                                        </td></tr>
                                    <tr><td>Fecha: 
                                            <?php
                                            if (isset($_GET['id_compra'])) {
                                                echo $rowdetcompras['fecha_ingreso'];
                                            }
                                            ?>
                                        </td></tr>
                                    <tr><td>Hora: 
                                            <?php
                                            if (isset($_GET['id_compra'])) {
                                                echo $rowdetcompras['hora_ingreso'];
                                            }
                                            ?>
                                        </td></tr>
                                </table>                                
                            </h5>
                        </div>
                        <br>
                        <form>
                            <div class="row justify-content-center mb-3">

                                <div class="col-4 text-end" >
                                    <input type="text" class="form-control" placeholder="Código o descripción de producto" id="codigo" name="codigo" autocomplete="off">

                                </div>
                                <div class="col-4 text-start">
                                    <input class="btn btn-primary black bg-silver" type="submit" value="Agregar" id="addRow">
                                </div>
                            </div>
                        </form>
                        <div class="table-responsive">
                            <table id="compra" class="display" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th>Precio</th>
                                        <th>Cantidad</th>
                                        <th>Total</th>
                                        <th>Cancelar</th>
                                    </tr>
                                </thead>
                                <?php
                                if (isset($_GET['id_compra'])) {
                                    $sqlcompras = mysqli_query($link, "select * from cc_compras where id_sucursal = '$id_sucursal' and id_compra = $id_compra");
                                    while ($rowv = mysqli_fetch_assoc($sqlcompras)) {
                                        $codigo = $rowv['codigo'];
                                        $rowproducto = mysqli_fetch_assoc(mysqli_query($link, "SELECT codigo,descripcion,precio_compra FROM cc_productos WHERE id_sucursal = '$id_sucursal' and codigo = $codigo"));
                                        $precio_compra = $rowproducto['descripcion'];
                                        if ($rowv['estatus'] == 2) {
                                            $estatus = "C";
                                            $total = 0;
                                        } else {
                                            $estatus = "N";
                                            $total = round($rowv['precio_compra'] * $rowv['cantidad'], 2);
                                        }
                                        echo '
                                    <tr id="' . $rowv['id_compra'] . ',' . $rowv['id_consecutivo'] . ',' . $rowdetcompras['id_proveedor'] . ',' . $rowv['precio_compra'] . ',' . $rowv['cantidad'] . '" class="estatus' . $estatus . '">
                                        <td>' . $rowv['codigo'] . '</td>
                                        <td>' . $rowproducto['descripcion'] . '</td>
                                        <td>' . $rowv['precio_compra'] . '</td>
                                        <td>' . $rowv['cantidad'] . '</td>
                                        <td>' . $total . '</td>
                                        <td align="center">';
                                        if ($rowv['estatus'] == 2) {
                                            echo '<a href="javascript:" onclick="cancelar_reactivar(' . $id_compra . ',' . $rowv['id_consecutivo'] . ',0)"><img class="imga" src="../img/icons/check-circle.svg"></a>';
                                        } else {
                                            echo '<a href = "javascript:" onclick = "cancelar_reactivar(' . $id_compra . ',' . $rowv['id_consecutivo'] . ',2)"><img class = "imga" src = "../img/icons/x-circle.svg"></a > ';
                                        }
                                        echo
                                        '</td>
                                    </tr>';
                                    }
                                    $id_proveedor = $rowdetcompras['id_proveedor'];
                                    if ($id_proveedor != '0') {
                                        $rowproveedor = mysqli_fetch_assoc(mysqli_query($link, "SELECT nombre_proveedor as nombre, credito FROM cc_proveedores WHERE id_sucursal = '$id_sucursal' and id_proveedor = $id_proveedor"));
                                    }
                                }
                                ?> 
                                <tfoot>
                                    <tr>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th>Total de compra</th>
                                        <th id="total_compra"></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div id="alert_proveedor">
                        </div>
                        <?php
                        if (isset($_GET['id_compra'])) {
                            $id_empleado = $rowdetcompras['id_empleado'];
                            $nombre_empleado = "";
                            if ($id_empleado <> 0) {
                                $sqlempleado = mysqli_fetch_assoc(mysqli_query($link, "select * from cc_empleados where id_sucursal = '$id_sucursal' and id_empleado = $id_empleado"));
                                $nombre_empleado = $sqlempleado['nombre'] . ' ' . $sqlempleado['apellido_paterno'];
                            }
                            echo
                            '<div style="text-align: center">
                                    <h5>Empleado que atendió:' . $nombre_empleado . '</h5>
                                </div>';
                        }
                        ?>

                        <br>
                        <div class="align-content-center ">
                            <a href="javascript:" onclick="elimina_compra()" class="btn btn-primary m-1" role="button">Eliminar compra</a>
                            <a href="javascript:" onclick="cerrar_compra()" class="btn btn-primary m-1" role="button" id="btn_cerrarcompra">Impresión</a>
                            <a href="javascript:" onclick="regresar()" class="btn btn-primary m-1" role="button" id="btn_cerrarcompra">Regresar</a>
                        </div>
                        <br>
                        <form  action="#" method="post" novalidate id="form_cancelar">
                            <input type="hidden" name="id_compra" id="id_compra">
                            <input type="hidden" name="id_consecutivo" id="id_consecutivo" >
                            <input type="hidden" name="movimiento" id="movimiento" >
                        </form>   
                        <br>
                        <div class="table-responsive" id="div_impresion" style="display:none" >
                            <style>
                                #tabla_impresion, #tabla_impresion th, #tabla_impresion td {
                                    border: 1px solid;
                                    border-collapse: collapse;
                                    font-size: 10px;
                                }
                                #encabezado, #encabezado tr, #encabezado td {
                                    border-collapse: collapse;
                                    font-size: 13px ;
                                    text-align: center;
                                }
                            </style>
                            <div style="text-align: center">
                                <h3><?php echo $_SESSION["desc_sucursal"]; ?></h3>
                            </div>
                            <div style="text-align: center">
                                <div id="header_info">
                                    <table id="encabezado"  style="width:100%;">
                                        <tr><td id="td_compra">Número compra: 
                                                <?php
                                                if (isset($_GET['id_compra'])) {
                                                    echo $id_compra;
                                                }
                                                ?>
                                            </td></tr>
                                        <tr><td id="td_fecha">Fecha: 
                                                <?php
                                                if (isset($_GET['id_compra'])) {
                                                    echo $rowdetcompras['fecha_ingreso'];
                                                }
                                                ?>
                                            </td></tr>
                                        <tr><td id="td_hora">Hora: 
                                                <?php
                                                if (isset($_GET['id_compra'])) {
                                                    echo $rowdetcompras['hora_ingreso'];
                                                }
                                                ?>
                                            </td></tr>
                                        <tr><td id="td_proveedor">
                                                <?php
                                                if (isset($_GET['id_compra'])) {
                                                    if ($id_proveedor != '0') {
                                                        echo 'Proveedor: ' . $rowproveedor["nombre"];
                                                    }
                                                }
                                                ?>
                                            </td></tr>
                                    </table>    
                                </div>
                            </div>
                            <div style="text-align: center">
                                <img src="../img/logo_1.jpeg" alt="MDN" width="100" height="100">
                            </div>
                            <table id="tabla_impresion"  style="width:100%;">
                                <tr>
                                    <th>Descripción</th>
                                    <th>Precio</th>
                                    <th>Cantidad</th>
                                    <th>Importe</th>
                                </tr>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" style="border-bottom: hidden;border-left: hidden; text-align: right; font-size:15">Total Compra</th>
                                        <th style="font-size:15" id="total_compra_i"></th>
                                    </tr>
                                    <tr>
                                        <th colspan="3" style="border-bottom: hidden;border-left: hidden; text-align: right">Importe recibido</th>
                                        <th id="recibido_i"></th>
                                    </tr>
                                    <tr>
                                        <th colspan="3" style="border-bottom: hidden;border-left: hidden; text-align: right">Cambio</th>
                                        <th id="cambio_i"></th>
                                    </tr>
                                </tfoot>                            
                            </table>
                            <br><br><br><br><!-- <br> -->
                        </div>
                        <br>
                    </div>
                </div>
            </div>
        </main>




        <div class="modal fade modal-lg" id="editModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ModalLabelTitle1">Agregar producto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form class="needs-validation" action="#" method="post" novalidate id="form_producto">
                        <?php
                        if (isset($_GET['id_compra'])) {
                            echo'
                            <input type="hidden" name="id_compra" id="id_compra_a" value = "' . $_GET['id_compra'] . '">';
                        } else {
                            echo'
                            <input type="hidden" name="id_compra" id="id_compra_a" value = "0">';
                        }
                        ?>
                        <input type="hidden" name="estatus" id="estatus" value = "<?php echo $rowdetcompras['estatus'] ?>">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Código</label>
                                <div class="input-group">
                                    <label for="codigo" class="form-label" id="codigo_e"></label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción</label>
                                <div class="input-group">
                                    <label for="descripcion" class="form-label" id="descripcion_e"></label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="preciocompra" class="form-label">Precio compra</label>
                                <div class="input-group has-validation">
                                    <input type="number" step="0.01" class="form-control" id="precio_compra_e" name="precio_compra_e" placeholder="Precio de compra" autocomplete="off" required>
                                    <div class="invalid-feedback">
                                        Favor de ingresar precio de compra.
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="almacen" class="form-label">Cantidad</label>
                                <div class="input-group has-validation">
                                    <input type="number" step="0.001" class="form-control" id="cantidad_e" name="almacen_e" placeholder="kg de producto" autocomplete="off" required onkeyup="calculatotal(this.value);" min = 0.01>
                                    <div class="invalid-feedback">
                                        Cantidad
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="almacen" class="form-label">Total</label>
                                <div class="input-group has-validation">
                                    <input type="number" step="0.01" class="form-control" id="total_e" name="total_e" placeholder="Total" autocomplete="off" required onkeyup="calculacantidad(this.value)">
                                    <div class="invalid-feedback">
                                        Total
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="submit" class="btn btn-primary" name="editar">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade modal-lg" id="Modalproveedores" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ModalLabelTitle2"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form class="needs-validation" action="#" method="post" novalidate id="form_proveedor">
                        <?php
                        if (isset($_GET['id_compra'])) {
                            echo'
                            <input type="hidden" name="id_proveedor" id="id_proveedor" value = "' . $rowdetcompras['id_proveedor'] . '">';
                        } else {
                            echo'
                            <input type="hidden" name="id_proveedor" id="id_proveedor" value = "0">';
                        }
                        ?> 
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Proveedor</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Nombre del proveedor" id="proveedor" name="proveedor" autocomplete="off">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Modal cierre de compra -->
        <div class="modal fade modal-lg" id="Modalcompra" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ModalLabelTitle3">Nuevo mensaje</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form class="needs-validation" action="#" method="post" novalidate id="form_compra">
                        <input type="hidden" name="id_empleado" id="id_empleado" value = "0">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Total de compra:</label>
                                <div class="input-group">
                                    <label for="descripcion" class="form-label" id="total_v"></label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Aceptar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <br>
        <br>

        <script src="js/bootstrap.bundle.min.js"></script>
        <script>
                                        var t;
                                        $(document).ready(function () {
                                            $('#compra').DataTable({
                                                paging: false,
                                                ordering: false,
                                                info: false,
                                                searching: false,

                                                columnDefs: [
                                                    {width: '70px', targets: [5]},
                                                    {className: 'dt-left', targets: '_all'},
                                                    {className: 'dt-center', targets: [5]}
                                                ],
                                                language: {zeroRecords: 'Sin productos'}
                                            });
                                            makeEditable();
                                            sumaTotal();


                                            var counter = 1;
                                            $('#addRow').on('click', function () {
                                                var $this = $(this);
                                                var parametros = {"busqueda": $("#codigo").val()};
                                                $.ajax({
                                                    type: "POST", //we are using POST method to submit the data to the server side
                                                    url: "../functions/extrae_producto.php", // get the route value
                                                    data: parametros, // our serialized array data for server side
                                                    dataType: 'json',
                                                    //var parametros = {"codigo" : $("#codigo").val()}
                                                    beforeSend: function () {//We add this before send to disable the button once we submit it so that we prevent the multiple click
                                                        $this.attr('disabled', true).html("Processing...");
                                                    },
                                                    success: function (response) {//once the request successfully process to the server side it will return result here
                                                        $this.attr('disabled', false);
                                                        // We will display the result using alert
                                                        // $peso = ( response[0].peso / 1000);
                                                        console.log(response);
                                                        if (response[0].total == 0)
                                                        {
                                                            var myModal = new bootstrap.Modal(document.getElementById("editModal"), {});
                                                            myModal.show();
                                                            $("#ModalLabelTitle1").html("Agregar cantidad y/o total");
                                                            $("#codigo_e").html(response[0].codigo);
                                                            $("#descripcion_e").html(response[0].descripcion);
                                                            $("#precio_compra_e").val(response[0].precio_compra);
                                                            $("#cantidad_e").val("");
                                                            $("#total_e").val("");
                                                            setTimeout(() => {
                                                                $("#precio_compra_e").focus();
                                                            }, 500);

                                                        } else {
                                                            edita_compra(response[0].codigo, response[0].peso, response[0].precio_compra, 0, 8);
                                                        }
                                                        $("#codigo").val("");
                                                        $("#codigo").focus();
                                                    },
                                                    error: function (XMLHttpRequest, textStatus, errorThrown, response) {
                                                        console.log(response);
                                                        $this.attr('disabled', false);
                                                        alert('Agregar datos de entrada');
                                                    }
                                                });

                                            });
                                            //$('#addRow').click();

                                            $('#codigo').typeahead({
                                                source: function (busqueda, resultado) {
                                                    $.ajax({
                                                        url: "../functions/consulta_productos.php",
                                                        data: 'busqueda=' + busqueda,
                                                        dataType: "json",
                                                        type: "POST",
                                                        success: function (data) {
                                                            resultado($.map(data, function (item) {
                                                                return getdesc(item);
                                                            }));
                                                        },
                                                        error: function (response) {
                                                            alert(response);
                                                        }
                                                    });
                                                },
                                                minLength: 2,
                                                delay: 600
                                            });

                                            $('#proveedor').typeahead({
                                                source: function (busqueda, process) {
                                                    $.ajax({
                                                        url: "../functions/consulta_proveedores.php",
                                                        data: 'busqueda=' + busqueda,
                                                        dataType: "json",
                                                        type: "POST",
                                                        success: function (data) {
                                                            var suggestions = [];
                                                            process(data);
                                                        },
                                                        error: function (response) {
                                                            console.log(response);
                                                            alert('Error');
                                                        }
                                                    });
                                                },
                                                display: 'name',
                                                val: 'id',
                                                afterSelect: function (item) {
                                                    agrega_alerta(item.name, item.id, item.credito);
                                                    $("#td_proveedor").html('Proveedor: ' + item.name);
                                                },
                                                minLength: 2,
                                                delay: 600
                                            });
                                            //Tablajeros cierre de compra
                                            $('#empleados').typeahead({
                                                source: function (busqueda, process) {
                                                    $.ajax({
                                                        url: "../functions/consulta_empleados.php",
                                                        data: 'busqueda=' + busqueda,
                                                        dataType: "json",
                                                        type: "POST",
                                                        success: function (data) {
                                                            console.log(data);
                                                            // Procesa los resultados del servidor
                                                            var suggestions = [];
                                                            // Llama a la función de devolución de llamada con las sugerencias
                                                            process(data);
                                                        },
                                                        error: function (response) {
                                                            console.log(response);
                                                            alert('Error');
                                                        }
                                                    });
                                                },
                                                display: 'name',
                                                val: 'id',
                                                afterSelect: function (item) {
                                                    $("#id_empleado").val(item.id);
                                                },
                                                minLength: 2,
                                                delay: 100
                                            });

                                            $("#codigo").focus();

                                            $('.needs-validation').on('submit', function (event) {
                                                // Verifica si el formulario es válido
                                                if (!this.checkValidity()) {
                                                    // Si el formulario no es válido, detiene el envío y muestra los mensajes de validación
                                                    event.preventDefault();
                                                    event.stopPropagation();
                                                    this.classList.add('was-validated');
                                                } else {
                                                    // Si el formulario es válido, cierra el modal
                                                    $('#this').modal('hide');
                                                }
                                            });
<?php
if (isset($_GET['id_compra'])) {
    $id_proveedor = $rowdetcompras["id_proveedor"];
    if ($id_proveedor != '0') {
        $rowproveedor = mysqli_fetch_assoc(mysqli_query($link, "SELECT nombre_proveedor as nombre, credito FROM cc_proveedores WHERE id_sucursal = '$id_sucursal' and id_proveedor = $id_proveedor"));

        echo
        '                                       muestra_proveedor(\'' . $rowproveedor["nombre"] . '\', ' . $rowdetcompras["id_proveedor"] . ',' . $rowproveedor["credito"] . ');';
    }
}
?>
                                        });

                                        function getdesc(arreglo) {
                                            var desc = [arreglo.id] + ' - ' + [arreglo.name];
                                            return desc;
                                        }
                                        function cancelar(objeto, consecutivo, descripcion) {
                                            if (confirm("¿Desea eliminar el producto " + descripcion + "?"))
                                                var table = $('#compra').DataTable();
                                            table.row($(objeto).parents('tr')).remove().draw();
                                            edita_compra(0, 0, 0, consecutivo, 2);
                                        }
                                        function cancelar_reactivar(id_compra, id_consecutivo, movimiento)
                                        {
                                            $('#id_compra').val(id_compra);
                                            $('#id_consecutivo').val(id_consecutivo);
                                            $('#movimiento').val(movimiento);
                                            $("#form_cancelar").submit();
                                        }
                                        function calculatotal(valor)
                                        {
                                            $("#total_e").val((parseFloat($("#precio_compra_e").val()) * valor).toFixed(2));
                                        }
                                        function calculacantidad(valor)
                                        {
                                            $("#cantidad_e").val((valor / parseFloat($("#precio_compra_e").val())).toFixed(3));
                                        }
                                        function calculacambio(valor)
                                        {
                                            $("#cambio_v").html((valor - parseFloat($("#total_compra").html())).toFixed(2));
                                        }

                                        var myForm_prodct = document.getElementById('form_producto');
                                        myForm_prodct.addEventListener('submit', function (event) {
                                            event.preventDefault(); // Evitar que el formulario se envíe automáticamente
                                            // Validar el formulario
                                            if (myForm_prodct.checkValidity() === false) {
                                                // Si hay errores, no hacer nada
                                                event.stopPropagation();
                                            } else {
                                                $('#editModal').modal('hide');
                                                agregarDatos();// Llama a la función JavaScript para agregar a la tabla
                                            }
                                            // Agregar la clase "was-validated" para mostrar los errores
                                            myForm_prodct.classList.add('was-validated');
                                        });


                                        var myForm_compra = document.getElementById('form_compra');
                                        myForm_compra.addEventListener('submit', function (event) {
                                            event.preventDefault(); // Evitar que el formulario se envíe automáticamente
                                            // Validar el formulario
                                            if (myForm_compra.checkValidity() === false) {
                                                // Si hay errores, no hacer nada
                                                event.stopPropagation();
                                            } else {
                                                guarda_compra();// Llama a la función JavaScript para agregar a la tabla
                                                $('#Modalcompra').modal('hide');
                                            }
                                            // Agregar la clase "was-validated" para mostrar los errores
                                            myForm_compra.classList.add('was-validated');
                                        });


                                        function agregarDatos() {
                                            edita_compra($("#codigo_e").html(), $("#cantidad_e").val(), $("#precio_compra_e").val(), 0, 8, '', 0);
                                            $("#codigo").val("");
                                            $("#codigo").focus();
                                        }

                                        function agregarProveedor() {
                                            $("#precio_compra_e").val();
                                            edita_compra($("#codigo_e").html(), $("#cantidad_e").val(), $("#precio_compra_e").val(), 0, 1);
                                            $("#codigo").val("");
                                            $("#codigo").focus();
                                        }
                                        function edita_compra(codigo, cantidad, precio_compra, consecutivo, movimiento, clave_externa, tipo_producto)
                                        {
                                            id_proveedor = $("#id_proveedor").val();
                                            id_compra = $("#id_compra_a").val();
                                            id_empleado = $("#id_empleado").val();
                                            comentarios = $("#comentarios_v").val();
                                            //tipo_pago = obtenerValorSeleccionado();
                                            //id_consecutivo = $("#id_consecutivo").val();
                                            id_consecutivo = consecutivo;
                                            var parametros = {"codigo": codigo, "precio_compra": precio_compra, "cantidad": cantidad, "id_proveedor": id_proveedor, "id_compra": id_compra,
                                                "id_consecutivo": id_consecutivo, "movimiento": movimiento, "id_empleado": id_empleado, "clave_externa": clave_externa, "tipo_producto": tipo_producto};
                                            $.ajax({
                                                url: "../functions/edita_compra.php",
                                                data: parametros,
                                                dataType: "json",
                                                type: "POST",
                                                async: false,
                                                success: function (response) {
                                                    $("#id_compra").val(response[0].id_compra);
                                                    console.log(response);
                                                    if (movimiento === 1)
                                                    {
                                                        total = (parseFloat(precio_compra) * parseFloat(cantidad)).toFixed(2);
                                                        tD = $('#compra').DataTable();
                                                        tD.row.add([codigo, response[0].descripcion, response[0].clave_externa, precio_compra, cantidad, total, '<a href="#" onclick="remove(this,' + response[0].id_consecutivo + ');" title="Eliminar" class=".remove"><img src="../img/icons/trash.svg"></a>']).node().id = response[0].id_compra + ',' + response[0].id_consecutivo;
                                                        tD.draw(false);
                                                        makeEditable();
                                                        sumaTotal();
                                                    }
                                                    if (movimiento === 8)
                                                    {
                                                        total = (parseFloat(precio_compra) * parseFloat(cantidad)).toFixed(2);
                                                        tD = $('#compra').DataTable();
                                                        tD.row.add([codigo, response[0].descripcion, precio_compra, cantidad, total, '<a href="javascript:" onclick="cancelar_reactivar(' + response[0].id_compra + ',' + response[0].id_consecutivo + ',2);" title="Cancelar" class=".remove"><img src="img/icons/x-circle.svg"></a>']).node().id = response[0].id_compra + ',' + response[0].id_consecutivo;
                                                        tD.draw(false);
                                                        makeEditable();
                                                        sumaTotal();
                                                    }
                                                    if (movimiento === 7)
                                                    {
                                                        $("#id_proveedor").val(0);
                                                        location.href = "compra_por_dia.php";
                                                    }
                                                },
                                                error: function (response) {
                                                    console.log(response);
                                                    id_compra = 0;
                                                }
                                            });
                                        }
                                        function elimina_compra(codigo, cantidad, precio_compra, consecutivo, movimiento) {
                                            id_compra = $("#id_compra").val();
                                            if (confirm("¿Desea eliminar la compra " + id_compra + "?"))
                                            {
                                                // Se modifica a movimiento 4 
                                                edita_compra(0, 0, 0, 0, 7, '', 0);
                                            }
                                        }
                                        function cerrar_compra(codigo, cantidad, precio_compra, consecutivo, movimiento) {
                                            var myModal = new bootstrap.Modal(document.getElementById("Modalcompra"), {});
                                            myModal.show();
                                            $("#ModalLabelTitle3").html("Cerrar compra");
                                            $("#total_v").html($("#total_compra").html());
                                            calculacambio($("#pago_v").val());
                                            setTimeout(() => {
                                                $("#pago_v").focus();
                                            }, 500);

                                        }

                                        function agrega_alerta(nom_proveedor, id_proveedor, credito)
                                        {
                                            $("#id_proveedor").val(id_proveedor);
                                            edita_compra(0, 0, 0, 0, 7);
                                            $('#div_alert').remove();
                                            var wrapper = document.createElement('div').innerHTML = '<div class="alert alert-secondary alert-dismissible" role="alert" id="div_alert"><strong>CLIENTE:</strong> ' + nom_proveedor + ' CREDITO: ' + credito + ' <a href="#" onclick="elimina_proveedor()" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>'
                                            $('#alert_proveedor').append(wrapper);
                                        }
                                        function muestra_proveedor(nom_proveedor, id_proveedor, credito)
                                        {
                                            $("#id_proveedor").val(id_proveedor);
                                            $('#div_alert').remove();
                                            var wrapper = document.createElement('div').innerHTML = '<div class="alert alert-secondary alert-dismissible" role="alert" id="div_alert"><strong>CLIENTE:</strong> ' + nom_proveedor + ' CREDITO: ' + credito + '</div>'
                                            $('#alert_proveedor').append(wrapper);
                                        }
                                        function elimina_proveedor() {
                                            $("#id_proveedor").val(0);
                                            edita_compra(0, 0, 0, 0, 7);
                                            $("#td_proveedor").html('');
                                        }
                                        function guarda_compra()
                                        {
                                            //edita_compra(0, 0, 0, 0, 5);
                                            imprSelec('div_impresion');
                                            //location.href = "index.php";
                                        }
                                        function imprSelec(nombre) {

                                            var tablaOriginal = document.getElementById('compra');
                                            var tablaDestino = document.getElementById('tabla_impresion');
                                            var columna2 = tablaOriginal.querySelectorAll('td:nth-child(2)');
                                            var columna3 = tablaOriginal.querySelectorAll('td:nth-child(3)');
                                            var columna4 = tablaOriginal.querySelectorAll('td:nth-child(4)');
                                            var columna5 = tablaOriginal.querySelectorAll('td:nth-child(5)');

                                            columna2.forEach(function (elemento, index) {
                                                if (elemento.parentElement.classList[0] != 'estatusC') {
                                                    var nuevaFila = document.createElement('tr');
                                                    var nuevaCelda2 = document.createElement('td');
                                                    var nuevaCelda3 = document.createElement('td');
                                                    var nuevaCelda4 = document.createElement('td');
                                                    var nuevaCelda5 = document.createElement('td');
                                                    nuevaCelda2.textContent = elemento.textContent;
                                                    nuevaCelda3.textContent = columna3[index].textContent;
                                                    nuevaCelda4.textContent = columna4[index].textContent;
                                                    nuevaCelda5.textContent = columna5[index].textContent;
                                                    nuevaFila.appendChild(nuevaCelda2);
                                                    nuevaFila.appendChild(nuevaCelda3);
                                                    nuevaFila.appendChild(nuevaCelda4);
                                                    nuevaFila.appendChild(nuevaCelda5);
                                                    tablaDestino.appendChild(nuevaFila);
                                                }
                                            });

                                            $("#total_compra_i").html($("#total_compra").html());
                                            $("#recibido_i").html($("#pago_v").val());
                                            $("#cambio_i").html($("#cambio_v").html());
                                            var ficha = document.getElementById(nombre);
                                            var altura = 480;
                                            var anchura = 630;
                                            var y = parseInt((window.screen.height / 2) - (altura / 2));
                                            var x = parseInt((window.screen.width / 2) - (anchura / 2));
                                            var ventimp = window.open('archivo.html', target = 'blank', 'width=' + anchura + ',height=' + altura + ',top=' + y + ',left=' + x + ',toolbar=no,location=no,status=no,menubar=no,scrollbars=no,directories=no,resizable=no')
                                            ventimp.document.write(ficha.innerHTML);
                                            ventimp.document.close();
                                            ventimp.print();
                                            ventimp.close();
                                        }
                                        function makeEditable() {
                                            $('#compra').dataTable().makeEditable({
                                                sUpdateURL: "../functions/actualiza_compra.php",
                                                aoColumns: [
                                                    null,
                                                    null,
                                                    {
                                                        type: 'number',
                                                        indicator: 'guardando información...',
                                                        tooltip: 'Click para editar precio',
                                                        cssclass: 'required',
                                                        sColumnName: 'precio_compra',
                                                        width: 110,
                                                    },
                                                    {
                                                        type: 'number',
                                                        step: '0.001',
                                                        indicator: 'Guardando información...',
                                                        tooltip: 'Click para editar cantidad',
                                                        cssclass: 'required',
                                                        sColumnName: 'cantidad',
                                                        width: 110,
                                                    }, null, null
                                                ],
                                                fnOnEdited: function (status, sOldValue, sNewCellDisplayValue, aPos0, aPos1, aPos2, idTr) {
                                                    const table = document.getElementById('compra');
                                                    const rowId = idTr; // ID de la fila
                                                    const rows = table.getElementsByTagName('tr');
                                                    let row = null;
                                                    for (let i = 0; i < rows.length; i++) {
                                                        if (rows[i].id === rowId) {
                                                            row = rows[i];
                                                            break;
                                                        }
                                                    }
                                                    const firstCell = row.cells[2];
                                                    const secondCell = row.cells[3];
                                                    const thirdCell = row.cells[4];
                                                    const firstValue = parseFloat(firstCell.innerText);
                                                    const secondValue = parseFloat(secondCell.innerText);
                                                    const multiplicationResult = firstValue * secondValue;
                                                    const roundedResult = multiplicationResult.toFixed(2);
                                                    thirdCell.innerText = roundedResult;
                                                    sumaTotal();

                                                }
                                            });

                                        }
                                        function sumaTotal()
                                        {
                                            var total = 0;
                                            $("#compra tr").each(function () {
                                                var valor = parseFloat($(this).find("td:nth-child(5)").text());
                                                if (!isNaN(valor)) {
                                                    total += valor;
                                                }
                                            });
                                            $("#total_compra").html(total.toFixed(2));
                                        }
                                        function regresar() {
<?php
if (isset($_GET['id_proveedor'])) {
    echo 'window.location = "../control/compras_proveedores.php?id_proveedor=' . $_GET['id_proveedor'] . '"';
} else {
    echo 'window.location = "../reportes/compra_por_dia.php"';
}
?>
                                        }

        </script>      
    </body>
</html>
