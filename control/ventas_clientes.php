<?php
// Initialize the session
session_start();
// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";
require_once "../functions/sync_queue.php";
date_default_timezone_set("America/Mexico_City");
// Define variables and initialize with empty values
$id_sucursal = $_SESSION["id_sucursal"];
$id_cliente = $_GET['id_cliente'];
$cliente = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_clientes where id_sucursal = $id_sucursal and id_cliente = $id_cliente"));
$fecha1 = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['form_id'])) {
        switch ($_POST['form_id']) {
            case 'form_cancelar':
                if (isset($_POST['movimiento'])) {
                    $fecha1 = $_POST['fecha_v'];
                    $movimiento = $_POST['movimiento'];
                    $id_venta = $_POST['id_venta'];
                    $id_consecutivo = $_POST['id_consecutivo'];
                    $id_usuario_act = $_SESSION["id"];
                    $fecha_act = date('y-m-d');
                    $hora_act = date('H:i:s');
                    $row_importe = mysqli_fetch_assoc(mysqli_query($link, "SELECT sum(cantidad * precio_venta) as 'importe' FROM `cc_ventas` WHERE id_sucursal = '$id_sucursal' and id_venta = $id_venta and id_consecutivo = '$id_consecutivo'"));
                    $importe = $row_importe['importe'];
                    if ($movimiento == 2) {
                        $importe = $importe * -1;
                    }
                    $update1 = mysqli_query($link, "UPDATE cc_ventas SET "
                                    . "estatus='$movimiento', "
                                    . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                                    . "WHERE id_sucursal='$id_sucursal' and id_venta = '$id_venta' and id_consecutivo = '$id_consecutivo'")
                            or die(mysqli_error());
                    if ($update1) {
                        recalcula($link, $id_sucursal, $importe, $id_cliente, $fecha_act, $hora_act, $id_usuario_act);
                    } else {
                        
                    }
                }
                break;

            case 'form_cancelar_pago':
                if (isset($_POST['movimiento_p'])) {
                    $fecha1 = $_POST['fecha_p'];
                    $movimiento_p = $_POST['movimiento_p'];
                    $id_pago = $_POST['id_pago'];
                    $id_usuario_act = $_SESSION["id"];
                    $fecha_act = date('y-m-d');
                    $hora_act = date('H:i:s');
                    $row_importe = mysqli_fetch_assoc(mysqli_query($link, "SELECT importe FROM `cc_pagos_clientes` WHERE id_sucursal = '$id_sucursal' and id_cliente = $id_cliente and id_pago = '$id_pago'"));
                    $importe = $row_importe['importe'];
                    if ($movimiento_p <> 2) {
                        $importe = $importe * -1;
                    }
                    $update1 = mysqli_query($link, "UPDATE cc_pagos_clientes SET "
                                    . "estatus='$movimiento_p', "
                                    . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                                    . "WHERE id_sucursal='$id_sucursal' and id_cliente = '$id_cliente' and id_pago = '$id_pago'")
                            or die(mysqli_error());
                    if ($update1) {
                        cc_sync_enqueue($link, $id_sucursal, 'pago_cliente', 'upsert', [
                            'id_cliente' => (int) $id_cliente,
                            'id_pago' => (int) $id_pago,
                        ], [
                            'tabla' => 'cc_pagos_clientes',
                            'estatus' => (int) $movimiento_p,
                        ]);
                        recalcula($link, $id_sucursal, $importe, $id_cliente, $fecha_act, $hora_act, $id_usuario_act);
                    } else {
                        
                    }
                }
                break;
        }
        $result = mysqli_query($link, "select efectivo_hoy from cc_saldos_clientes where id_sucursal = $id_sucursal and id_cliente =" . $id_cliente);
        $numero = mysqli_num_rows($result);
        if ($numero == 0) {
            $saldo = 0;
        } else {
            $saldos = mysqli_fetch_assoc($result);
            $saldo = $saldos['efectivo_hoy'];
        }
    }
}


if ($fecha1 == '') {
    if (isset($_POST['fecha1'])) {
        $fecha1 = $_POST['fecha1'];
    } else {
        $fechaActual = date('Y-m-d');
        $fecha1 = date('Y-m-d', strtotime('-3 days', strtotime($fechaActual))); // Resta 3 días a la fecha actual
        $result = mysqli_query($link, "select efectivo_hoy from cc_saldos_clientes where id_sucursal = $id_sucursal and id_cliente =" . $id_cliente);
        $numero = mysqli_num_rows($result);
        if ($numero == 0) {
            $saldo = 0;
        } else {
            $saldos = mysqli_fetch_assoc($result);
            $saldo = $saldos['efectivo_hoy'];
        }
    }
}
if (isset($_POST['fecha2'])) {
    $fecha2 = $_POST['fecha2'];
} else {
    $fecha2 = date('Y-m-d');
}
if (isset($_POST['saldo'])) {
    $saldo = $_POST['saldo'];
}

function saldo($link, $id_sucursal, $id_cliente) {
    $v_cliente = mysqli_fetch_assoc(mysqli_query($link, "select round(sum(b.cantidad * b.precio_venta),2) importe from cc_det_ventas as a left join cc_ventas as b on a.id_sucursal = b.id_sucursal and a.id_venta = b.id_venta where a.id_sucursal = $id_sucursal and id_cliente = $id_cliente and a.estatus in (1,3) and b.estatus in (0)"));
    $p_cliente = mysqli_fetch_assoc(mysqli_query($link, "SELECT sum(importe) importe FROM cc_pagos_clientes where id_sucursal = $id_sucursal and id_cliente = $id_cliente and estatus in (0,3)"));
    $ve_cliente = $pa_cliente = 0;
    if ($v_cliente['importe'] != null) {
        $ve_cliente = floatval($v_cliente['importe']);
    }
    if ($p_cliente['importe'] != null) {
        $pa_cliente = floatval($p_cliente['importe']);
    }
    return round($ve_cliente - $pa_cliente, 2);
}

//recalcula saldo cliente
function recalcula($link, $id_sucursal, $importe, $id_cliente, $fecha_act, $hora_act, $id_usuario_act) {
    mysqli_query($link, "UPDATE cc_saldos_clientes SET efectivo_hoy = efectivo_hoy + $importe, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and id_cliente = $id_cliente");
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
        <title>Ventas y pagos x cliente</title>

        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery-ui.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <script src="../js/sum().js"></script>
        <script src="../js/jquery.jeditable.js" type="text/javascript"></script>
        <script src="../js/jquery.dataTables.editable.js" type="text/javascript"></script>
        <script src="../js/jquery.jeditable.js" type="text/javascript"></script>
        <script src="../js/jquery.validate.js" type="text/javascript"></script>
        <script src="../js/gijgo.min.js" type="text/javascript"></script>



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
                            <h1 class="text-center">Ventas y pagos x cliente</h1>
                        </div>
                        <br>
                        <br>
                        <div class="col-sm mx-auto">
                            <h3 class="text-left">
                                <?php
                                echo $cliente['nombre'] . ' ' . $cliente['apellido_paterno'];
                                ?>
                            </h3>
                            <h4 class="text-left" id="suma_total">
                                <?php
                                echo 'Saldo: ' . $saldo;
                                ?>
                            </h4>
                        </div>
                        <br>
                        <form class="row g-3 needs-validation" action="#" method="post" novalidate>
                            <input type="hidden" name="saldo" id="saldo" value="<?php echo $saldo ?>">
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
                            <div class="text-center">
                                <input class="btn btn-primary m-1" type="submit" value="Buscar" id="buscar_fecha">
                                <a href="#" onclick="reconstruir()" class="btn btn-primary m-1" role="button">Reconstruir Saldo</a>
                                <a href="#" onclick="regresar()" class="btn btn-primary m-1" role="button">Regresar</a>
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
                                        <th>Estatus</th>
                                        <th>Precio</th>
                                        <th>Cantidad</th>
                                        <th>Importe</th>
                                        <th>T. V.</th>
                                        <th>...</th>
                                    </tr>
                                </thead>
                                <?php
                                $sqlventas = mysqli_query($link, "SELECT a.id_venta,a.fecha_ingreso,a.hora_ingreso,b.codigo,b.estatus,b.precio_venta,b.cantidad,"
                                        . "round(b.precio_venta * b.cantidad,2) as importe,b.id_consecutivo "
                                        . "FROM cc_det_ventas as a inner join cc_ventas as b on a.id_sucursal = b.id_sucursal and a.id_venta = b.id_venta "
                                        . "WHERE a.id_sucursal = '$id_sucursal' and a.id_cliente = $id_cliente and a.fecha_ingreso between '$fecha1' and '$fecha2'"
                                        . " and a.estatus in (1,3) order by a.id_venta DESC");

                                $renglon = 0;

                                while ($rowc = mysqli_fetch_assoc($sqlventas)) {
                                    $renglon = $renglon + 1;
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = 'ROL' and id_clave =" . $rowp['rol']));
                                    $producto = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_productos where id_sucursal = $id_sucursal and codigo =" . $rowc['codigo']));

                                    $total_id = mysqli_fetch_assoc(mysqli_query($link, "SELECT sum(round(cantidad * precio_venta,2)) total_id FROM cc_ventas WHERE id_sucursal = $id_sucursal  and id_venta = " . $rowc['id_venta'] . " and id_consecutivo = " . $rowc['id_consecutivo'] . " and estatus not in (2)"));
                                    if ($rowc['estatus'] == 2) {
                                        $estatus = "C";
                                        $importe = 0;
                                    } else {
                                        $estatus = "N";
                                        $importe = $rowc['importe'];
                                    }
                                    echo '
                                    <tr id="' . $rowc['id_venta'] . ',' . $rowc['id_consecutivo'] . '">
                                        <td class="id_venta">' . $rowc['id_venta'] . '</td>
                                        <td>' . $rowc['fecha_ingreso'] . '</td>
                                        <td>' . $rowc['hora_ingreso'] . '</td>
                                        <td>' . $rowc['codigo'] . '</td>
                                        <td>' . $producto['descripcion'] . '</td>
                                        <td>' . $estatus . '</td>
                                        <td>' . $rowc['precio_venta'] . '</td>
                                        <td>' . $rowc['cantidad'] . '</td>
                                        <td>' . $importe . '</td>
                                        <td>' . $total_id['total_id'] . '</td>
                                        <td align="center">
                                        <a href="javascript:ventas(' . $rowc['id_venta'] . ')"  title="Editar venta"><img class="imga p-1<" src="../img/icons/pencil-square.svg"></a>';
                                    if ($rowc['estatus'] == 2) {
                                        echo '<a href="javascript:cancelar_reactivar(' . $rowc['id_venta'] . ',' . $rowc['id_consecutivo'] . ',0)" title = "Reactivar"><img class = "imga p-1" src = "../img/icons/check-circle.svg"></a>';
                                    } else {
                                        echo '<a href="javascript:cancelar_reactivar(' . $rowc['id_venta'] . ',' . $rowc['id_consecutivo'] . ',2)" title = "Cancelar" onclick = "return confirm(\'¿Esta seguro de cancelar la compra ' . $rowc['id_venta'] . '?\')"><img class = "imga p-1" src = "../img/icons/x-circle.svg"></a>';
                                    }
                                    echo '
                                        </td>
                                    </tr>';
                                }
                                ?>  
                                <tfoot>
                                    <tr>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th id="total_venta"></th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table> 
                        </div>
                        <form  action="#" method="post" novalidate id="form_cancelar" name="form_cancelar">
                            <input type="hidden" name="form_id" value="form_cancelar">
                            <input type="hidden" name="id_venta" id="id_venta">
                            <input type="hidden" name="id_consecutivo" id="id_consecutivo" >
                            <input type="hidden" name="movimiento" id="movimiento" >
                            <input type="hidden" name="fecha_v" value="<?php echo $fecha1 ?>">
                        </form>    
                        <br>
                        <div class="col-sm mx-auto">
                            <h3 class="text-left">Detalle de pagos</h3>
                        </div>
                        <br>
                        <div class="table-responsive">
                            <table id="pagos" class="display" style="width:100%" >
                                <thead>
                                    <tr>
                                        <th>Id</th>
                                        <th>Fecha</th>
                                        <th>Hora</th>
                                        <th>Observaciones</th>
                                        <th>Importe</th>
                                        <th>Tipo</th>
                                        <th>Usuario registro</th>
                                        <th>...</th>
                                    </tr>
                                </thead>
                                <?php
                                $sqlventas = mysqli_query($link, "select * from cc_pagos_clientes as a "
                                        . "WHERE a.id_sucursal = '$id_sucursal' and a.id_cliente = $id_cliente and a.fecha_ingreso between '$fecha1' and '$fecha2'"
                                        . " order by a.id_pago DESC");

                                $renglon = 0;
                                while ($rowc = mysqli_fetch_assoc($sqlventas)) {
                                    $renglon = $renglon + 1;
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = ' ROL' and id_clave =" . $rowp['rol']));
                                    $usuario = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id  =" . $rowc['id_usuario']));
                                    $desc_pago = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_claves where nombre_clave = 'TIPO_PAGO' and clave  =" . $rowc['tipo_pago']));
                                    if ($rowc['estatus'] == 2) {
                                        $estatus = "C";
                                        $importe = 0;
                                    } else {
                                        $estatus = "N";
                                        $importe = $rowc['importe'];
                                    }
                                    echo '
                                    <tr id = "fila' . $renglon . '">
                                        <td>' . $rowc['id_pago'] . ' </td>
                                        <td>' . $rowc['fecha_ingreso'] . ' </td>
                                        <td>' . $rowc['hora_ingreso'] . ' </td>
                                        <td>' . $rowc['observaciones'] . ' </td>
                                        <td>' . $importe . ' </td>
                                        <td>' . $desc_pago['descripcion_corta'] . ' </td>
                                        <td>' . $usuario['username'] . ' </td>
                                        <td align = "center">';
                                    if ($rowc['estatus'] == 2) {
                                        echo '<a href="javascript:cancelar_reactivar_pago(' . $rowc['id_pago'] . ',0)" title = "Reactivar"><img class = "imga p-1" src = "../img/icons/check-circle.svg"></a>';
                                    } else {
                                        echo '<a href="javascript:cancelar_reactivar_pago(' . $rowc['id_pago'] . ',2)" title = "Cancelar" onclick = "return confirm(\'¿Esta seguro de cancelar la compra ' . $rowc['id_pago'] . '?\')"><img class = "imga p-1" src = "../img/icons/x-circle.svg"></a>';
                                    }
                                    echo '
                                <a href = "#" onclick = "imprimir(\'' . $rowc['id_pago'] . '\',\'' . $rowc['fecha_ingreso'] . '\',\'' . $rowc['hora_ingreso'] . '\',\'' . $rowc['importe'] . '\',\'' . $rowc['observaciones'] . '\')" title = "Imprimir"><img class = "imga" src = "../img/icons/printer.svg"></a>
                                </td>
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
                                        <th id="total_pago"></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table> 
                        </div>
                        <form  action="#" method="post" novalidate name="form_cancelar_pago" id="form_cancelar_pago">
                            <input type="hidden" name="form_id" value="form_cancelar_pago">
                            <input type="hidden" name="id_pago" id="id_pago">
                            <input type="hidden" name="movimiento_p" id="movimiento_p" >
                            <input type="hidden" name="fecha_p" value="<?php echo $fecha1 ?>">
                        </form>
                        <br>
                        <div class="align-content-center text-center">
                            <a href="#" class="btn btn-primary m-1" role="button" id="addRow">Agrega pago</a>
                        </div>
                        <br>
                        <div class="table-responsive" id="div_impresion" style="display:none" >
                            <style>
                                #tabla_impresion, #tabla_impresion th, #tabla_impresion td {
                                    border: 1px solid;
                                    border-collapse: collapse;
                                    font-size: 10px;
                                }
                                #encabezado, #encabezado th, #encabezado td {
                                    border-collapse: collapse;
                                }
                            </style>


                            <div style="text-align: center">
                                <h3><?php echo $_SESSION["desc_sucursal"]; ?></h3>
                            </div>
                            <div style="text-align: center">
                                <h6 id="header_info">
                                    <table id="encabezado"  style="width:100%;">
                                        <tr>
                                            <th id="td_venta">
                                            </th>
                                        </tr>
                                        <tr>
                                            <th id="td_fecha">
                                            </th>
                                        </tr>
                                        <tr>
                                            <th id="td_hora">
                                            </th>
                                        </tr>
                                        <tr>
                                            <th id="td_cliente">
                                                Cliente: 
                                                <?php echo $cliente['nombre'] . ' ' . $cliente['apellido_paterno'] ?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th id="td_saldo">
                                            </th>
                                        </tr>
                                    </table>    
                                </h6>
                            </div>
                            <div style="text-align: center">
                                <img src="../img/logo_1.jpeg" alt="MDN" width="100" height="100">
                            </div>
                            <table id="tabla_impresion"  style="width:100%;">
                                <tr>
                                    <th>Descripción</th>
                                    <th>Importe</th>
                                    <th>Observaciones</th>
                                </tr>
                                <tr>
                                    <td>Pago a cuenta</td>
                                    <td id="td_importe"></td>
                                    <td id="td_observaciones"></td>
                                </tr>
                            </table>
                            <br><br><br><br><!-- <br> -->
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <div class="modal fade modal-lg" id="agregaModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ModalLabelTitle"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form class="needs-validation" action="#" method="post" novalidate id="form_pago">
                        <input type="hidden" name="id_cliente_e" id="id_cliente_e" value="<?php echo $id_cliente ?>">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="Importe" class="form-label">Importe</label>
                                <div class="input-group has-validation">
                                    <input type="number" step="0.01" class="form-control" id="importe_e" name="importe_e" placeholder="Importe" autocomplete="off" required min="1">
                                    <div class="invalid-feedback">
                                        Favor de ingresar importe
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="pago" class="form-label">Pago</label>
                                <div class="input-group has-validation">
                                    <!-- Radio Button 1 -->
                                    <div class="form-check mb-3 me-3"> <!-- mb-3 agrega un margen inferior -->
                                        <input class="form-check-input" type="radio" name="opcion" id="opcion1" value="1" checked required>
                                        <label class="form-check-label" for="opcion1">
                                            Efectivo
                                        </label>
                                    </div>

                                    <!-- Radio Button 2 -->
                                    <div class="form-check mb-3 me-3"> <!-- mb-3 agrega un margen inferior -->
                                        <input class="form-check-input" type="radio" name="opcion" id="opcion2" value="2" required>
                                        <label class="form-check-label" for="opcion2">
                                            Transferencia
                                        </label>
                                    </div>

                                    <!-- Radio Button 3 -->
                                    <div class="form-check mb-3 me-3"> <!-- mb-3 agrega un margen inferior -->
                                        <input class="form-check-input" type="radio" name="opcion" id="opcion3" value="3" required>
                                        <label class="form-check-label" for="opcion3">
                                            Tarjeta bancaria
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Observaciones:</label>
                                <input type="text" class="form-control" id="observaciones_e" name="observaciones_e" placeholder="Observaciones">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="submit" class="btn btn-primary" name="guardar_pago">Guardar Pago</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
                                    var p;
                                    $(document).ready(function () {
                                        var v = $('#ventas').dataTable(
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
                                                        $(api.table().column(8).footer()).html(api.column(8, {page: 'current'}).data().sum().toFixed(2));
                                                    },
                                                }
                                        ).makeEditable({
                                            sUpdateURL: "../functions/actualiza_venta.php",
                                            aoColumns: [
                                                null,
                                                null,
                                                null,
                                                null,
                                                null,
                                                null,
                                                {
                                                    type: 'number',
                                                    indicator: 'guardando información...',
                                                    tooltip: 'Click para editar precio',
                                                    cssclass: 'required',
                                                    sColumnName: 'precio_venta'
                                                },
                                                {
                                                    type: 'number',
                                                    step: '0.001',
                                                    indicator: 'Guardando información...',
                                                    tooltip: 'Click para editar cantidad',
                                                    cssclass: 'required',
                                                    sColumnName: 'cantidad'
                                                }, null, null
                                            ],
                                            fnOnEdited: function (status, sOldValue, sNewCellDisplayValue, aPos0, aPos1, aPos2, idTr) {
                                                //this function runs after the edit has been made
                                                /* the edit has been made to some cell in the row, and the text in our cell (which is another cell in that same row) has reverted to what it was before we changed it. So now we immediately change it back */
                                                //alert(status + '|' + sOldValue + '|' + sNewCellDisplayValue + '|' + aPos0 + '|' + aPos1 + '|' + aPos2);


                                                const table = document.getElementById('ventas');

                                                const rowId = idTr; // ID de la fila

                                                const rows = table.getElementsByTagName('tr');
                                                let row = null;

                                                for (let i = 0;
                                                        i < rows.length;
                                                        i++) {
                                                    if (rows[i].id === rowId) {
                                                        row = rows[i];
                                                        break;
                                                    }
                                                }

                                                console.log('Fila extraída:', row);
                                                const firstCell = row.cells[6];
                                                const secondCell = row.cells[7];
                                                const thirdCell = row.cells[8];

                                                const firstValue = parseFloat(firstCell.innerText);
                                                const secondValue = parseFloat(secondCell.innerText);
                                                const multiplicationResult = firstValue * secondValue;
                                                const roundedResult = multiplicationResult.toFixed(2);

                                                thirdCell.innerText = roundedResult;

                                            }
                                        });
                                        p = $('#pagos').DataTable(
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
                                                        $(api.column(3).footer()).html('Total de pagos');
                                                        $(api.table().column(4).footer()).html(api.column(4, {page: 'current'}).data().sum().toFixed(2));
                                                        //sumatotal();
                                                    },
                                                    columnDefs: [
                                                        {className: 'dt-center', targets: [7]}
                                                    ],
                                                }
                                        );

                                        $('#addRow').on('click', function () {
                                            var $this = $(this);
                                            var parametros = {"busqueda": $("#codigo").val()};

                                            var myModal = new bootstrap.Modal(document.getElementById("agregaModal"), {});
                                            $("#ModalLabelTitle").html("Agregar pago de cliente");
                                            $("#importe_e").val("");
                                            myModal.show();
                                            setTimeout(() => {
                                                $("#importe_e").focus();
                                            }, 500);
                                        });

                                        (function () {
                                            'use strict'
                                            // Fetch all the forms we want to apply custom Bootstrap validation styles to
                                            var forms = document.querySelectorAll('.needs-validation')
                                            // Loop over them and prevent submission
                                            Array.prototype.slice.call(forms)
                                                    .forEach(function (form) {
                                                        form.addEventListener('submit', function (event) {
                                                            if (!form.checkValidity()) {
                                                                event.preventDefault()
                                                                event.stopPropagation()
                                                            }
                                                            form.classList.add('was-validated')
                                                        }, false)
                                                    })
                                        })()

                                    });
                                    var myForm_pago = document.getElementById('form_pago');
                                    myForm_pago.addEventListener('submit', function (event) {
                                        event.preventDefault(); // Evitar que el formulario se envíe automáticamente
                                        // Validar el formulario
                                        if (myForm_pago.checkValidity() === false) {
                                            // Si hay errores, no hacer nada
                                            event.stopPropagation();
                                        } else {
                                            guarda_pago(); // Llama a la función JavaScript para agregar a la tabla
                                            $('#agregaModal').modal('hide');
                                        }
                                        // Agregar la clase "was-validated" para mostrar los errores
                                        myForm_pago.classList.add('was-validated');
                                    });
                                    function guarda_pago()
                                    {
                                        id_cliente = $("#id_cliente_e").val();
                                        importe = $("#importe_e").val();
                                        observaciones = $("#observaciones_e").val();
                                        movimiento = 1;
                                        tipo_pago = obtenerValorSeleccionado();
                                        //id_consecutivo = $("#id_consecutivo").val();
                                        var parametros = {"movimiento": movimiento, "importe": importe, "tipo_pago": tipo_pago, "observaciones": observaciones, "id_cliente": id_cliente};
                                        $.ajax({
                                            url: "../functions/edita_pagos.php",
                                            data: parametros,
                                            dataType: "json",
                                            type: "POST",
                                            async: false,
                                            success: function (response) {
                                                p.row.add([response[0].id_pago, response[0].fecha, response[0].hora, response[0].observaciones, response[0].importe, response[0].desc_pago, response[0].usuario, '<a href="javascript:cancelar_reactivar_pago(' + response[0].id_pago + ',2)" title="Eliminar" class=".remove" onclick="return confirm(\'¿Esta seguro de cancelar la compra ' + response[0].id_pago + '?\')"><img src="../img/icons/x-circle.svg"></a>\n\
                        <a href = "#" onclick = "imprimir(\'' + response[0].id_pago + '\',\'' + response[0].fecha + '\',\'' + response[0].hora + '\',\'' + response[0].importe + '\',\'' + response[0].observaciones + '\')" title="Imprimir"><img class = "imga" src = "../img/icons/printer.svg"></a>']).draw(false);
                                                $('#suma_total').html('Saldo: ' + (response[0].efectivo).toFixed(2));
                                                imprimir(response[0].id_pago, response[0].fecha, response[0].hora, response[0].importe, response[0].observaciones);
                                            },
                                            error: function (response) {
                                                console.log(response);
                                                id_venta = 0;
                                            }
                                        });
                                    }
                                    function reconstruir()
                                    {
                                        if (confirm('¿Desea reconstruir el saldo del cliente?'))
                                        {
                                            id_cliente = $("#id_cliente_e").val();
                                            var parametros = {"id_cliente": id_cliente};
                                            $.ajax({
                                                url: "../functions/reconstruir.php",
                                                data: parametros,
                                                dataType: "json",
                                                type: "POST",
                                                async: false,
                                                success: function (response) {
                                                    $('#suma_total').html('Saldo: ' + (response[0].saldo).toFixed(2));
                                                },
                                                error: function (response) {
                                                    console.log(response);
                                                }
                                            });
                                        }
                                    }
                                    function elimina_pago(id_pago)
                                    {
                                        id_cliente = $("#id_cliente_e").val();
                                        movimiento = 2;
                                        //id_consecutivo = $("#id_consecutivo").val();
                                        var parametros = {"movimiento": movimiento, "id_cliente": id_cliente, "id_pago": id_pago};
                                        $.ajax({
                                            url: "../functions/edita_pagos.php",
                                            data: parametros,
                                            dataType: "json",
                                            type: "POST",
                                            success: function (response) {

                                            },
                                            error: function (response) {
                                                console.log(response);
                                                id_venta = 0;
                                            }
                                        });
                                    }
                                    function remove(objeto, id_pago) {
                                        if (confirm('¿Desea eliminar el pago ' + id_pago + '?'))
                                        {
                                            var table = $('#pagos').DataTable();
                                            table.row($(objeto).parents('tr')).remove().draw();
                                            elimina_pago(id_pago);
                                        }
                                    }
                                    function cancelar_reactivar(id_venta, id_consecutivo, movimiento)
                                    {
                                        $('#id_venta').val(id_venta);
                                        $('#id_consecutivo').val(id_consecutivo);
                                        $('#movimiento').val(movimiento);
                                        $('#form_cancelar').submit();
                                    }
                                    function cancelar_reactivar_pago(id_pago, movimiento_p)
                                    {
                                        $('#id_pago').val(id_pago);
                                        $('#movimiento_p').val(movimiento_p);
                                        $('#form_cancelar_pago').submit();

                                    }
                                    function agregar(id_venta)
                                    {
                                        $('#id_venta').val(id_venta);
                                        $('#id_consecutivo').val(id_consecutivo);
                                        $('#movimiento').val(movimiento);
                                        $("#form_cancelar").submit();
                                    }
                                    $('#datepicker1').datepicker({
                                        uiLibrary: 'bootstrap5',
                                        format: 'yyyy-mm-dd'
                                    });
                                    $('#datepicker2').datepicker({
                                        uiLibrary: 'bootstrap5',
                                        format: 'yyyy-mm-dd'
                                    });
                                    function imprimir(id, fecha, hora, importe, observaciones)
                                    {
                                        $("#td_venta").html('Número de pago: ' + id);
                                        $("#td_fecha").html('Fecha: ' + fecha);
                                        $("#td_hora").html('Hora: ' + hora);
                                        $("#td_importe").html(importe);
                                        $("#td_observaciones").html(observaciones);
                                        $("#td_saldo").html($("#suma_total").html());
                                        var ficha = document.getElementById('div_impresion');
                                        var altura = 480;
                                        var anchura = 630;
                                        var y = parseInt((window.screen.height / 2) - (altura / 2));
                                        var x = parseInt((window.screen.width / 2) - (anchura / 2));
                                        var ventimp = window.open('Impresion.html', target = 'blank', 'width=' + anchura + ',height=' + altura + ',top=' + y + ',left=' + x + ',toolbar=no,location=no,status=no,menubar=no,scrollbars=no,directories=no,resizable=no')
                                        ventimp.document.write(ficha.innerHTML);
                                        ventimp.document.close();
                                        ventimp.print();
                                        ventimp.close();
                                    }
                                    function sumatotal() {
                                        $('#suma_total').html('Suma total:' + (parseFloat($('#total_venta').html()) - parseFloat($('#total_pago').html())).toFixed(2));
                                    }
                                    function regresar() {
                                        window.location = "../mantenimientos/clientes.php";
                                    }
                                    function ventas(id_venta)
                                    {
                                        window.location = "../reportes/ventas.php?id_venta=" + id_venta + "&id_cliente=" + <?php echo $id_cliente
                                                ?>;
                                    }
                                    function obtenerValorSeleccionado() {
                                        var radioSeleccionado = document.querySelector('input[name="opcion"]:checked');
                                        if (radioSeleccionado) {
                                            return radioSeleccionado.value;
                                        } else {
                                            return null; // En caso de que ningún botón esté seleccionado
                                        }
                                    }
        </script>      
    </body>
</html>
