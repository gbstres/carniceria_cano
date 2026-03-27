<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}
require_once "../functions/config.php";
$id_sucursal = isset($_SESSION["id_sucursal"]) ? (int) $_SESSION["id_sucursal"] : 0;

if ($id_sucursal <= 0) {
    header("location: ../login/cambio_sucursal.php");
    exit;
}

$_SESSION['carpeta'] = '';

//Eliminar la venta
if (isset($_GET['accion']) && $_GET['accion'] === 'delete') {
    $fecha_ingreso = date('Y-m-d');
    $hora_ingreso = date('H:i:s');
    $id_usuario = $_SESSION["id"];

    $id_venta = mysqli_real_escape_string($link, (strip_tags($_GET["id"], ENT_QUOTES)));
    $cek = mysqli_query($link, "SELECT * FROM cc_det_ventas WHERE id_sucursal = '$id_sucursal' and id_venta = $id_venta");
    $sqlproductos = mysqli_fetch_assoc($cek);
    if (mysqli_num_rows($cek) == 0) {
        echo '<div class="alert alert-info alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button> No se encontraron datos.</div>';
    } else {
        $movimiento = 2;
        $update2 = mysqli_query($link, "UPDATE cc_ventas SET "
                . "estatus = 2, "
                . "fecha_act='$fecha_ingreso', hora_act='$hora_ingreso', id_usuario_act='$id_usuario' "
                . "WHERE id_sucursal='$id_sucursal' and id_venta='$id_venta'");
        if ($update2) {
            $update3 = mysqli_query($link, "UPDATE cc_det_ventas SET "
                    . "estatus = 1, "
                    . "fecha_act='$fecha_ingreso', hora_act='$hora_ingreso', id_usuario_act='$id_usuario' "
                    . "WHERE id_sucursal='$id_sucursal' and id_venta='$id_venta'");
            $response_array [] = array('id_venta' => 0, 'id_consecutivo' => 0, 'id_cliente' => 0);
        } else {
            
        }
    }
}
// Extrae si se requiere clave externa
$descripcion_corta = 0;
$sql_clave = mysqli_query($link, "SELECT descripcion_corta FROM cc_claves WHERE nombre_clave = 'CLAVE_EXTERNA' and clave = '$id_sucursal'");
if ($sql_clave) {
    $sqlproductos = mysqli_fetch_assoc($sql_clave);
    if ($sqlproductos &&
            isset($sqlproductos['descripcion_corta']) &&
            $sqlproductos['descripcion_corta'] != '0' &&
            $sqlproductos['descripcion_corta'] !== 0 &&
            !empty($sqlproductos['descripcion_corta']) &&
            $sqlproductos['descripcion_corta'] !== null) {
        $descripcion_corta = $sqlproductos['descripcion_corta'];
    }
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
            #tabla_preimpresion, #tabla_preimpresion th, #tabla_preimpresion td {
                border: 1px solid;
                border-collapse: collapse;
                font-size: 16px;
            }
            #preencabezado, #preencabezado tr, #preencabezado td {
                border-collapse: collapse;
                font-size: 16px ;
                text-align: center;
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
                            <h1>Punto de venta</h1>
                        </div>
                        <br>
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
                            <table id="venta" class="display" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th>Precio</th>
                                        <th>Cantidad</th>
                                        <?php
                                        if ($descripcion_corta == 1) {
                                            echo
                                            '<th>CVE E</th>';
                                        }
                                        ?>
                                        <th>Total</th>
                                        <th>Borrar</th>
                                    </tr>
                                </thead>
                                <?php
                                $estatus = 0;
                                if (isset($_GET['id_venta'])) {
                                    $id_venta = $_GET['id_venta'];
                                    $rowdetventas = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_det_ventas WHERE id_sucursal = '$id_sucursal' and id_venta = $id_venta"));
                                    $sqlventas = mysqli_query($link, "select * from cc_ventas where id_sucursal = '$id_sucursal' and id_venta = $id_venta");
                                    while ($rowv = mysqli_fetch_assoc($sqlventas)) {
                                        $codigo = $rowv['codigo'];
                                        $rowproducto = mysqli_fetch_assoc(mysqli_query($link, "SELECT codigo,descripcion,precio_venta FROM cc_productos WHERE id_sucursal = '$id_sucursal' and codigo = $codigo"));
                                        $precio_venta = $rowproducto['descripcion'];
                                        $total = round($rowv['precio_venta'] * $rowv['cantidad'], 2);
                                        echo '
                                    <tr id="' . $rowv['id_venta'] . ',' . $rowv['id_consecutivo'] . '">
                                        <td>' . $rowv['codigo'] . '</td>
                                        <td>' . $rowproducto['descripcion'] . '</td>
                                        <td>' . $rowv['precio_venta'] . '</td>
                                        <td>' . $rowv['cantidad'] . '</td>';
                                        if ($descripcion_corta == 1) {
                                            echo
                                            '<td>' . $rowv['clave_externa'] . '</td>';
                                        }
                                        echo '<td>' . $total . '</td>
                                        <td align="center">
                                            <a href="#" onclick="remove(this,' . $rowv['id_consecutivo'] . ')"><img class="imga" src="../img/icons/trash.svg"></a>
                                        </td>
                                    </tr>';
                                    }
                                    $id_cliente = $rowdetventas["id_cliente"];
                                    $estatus = $rowdetventas["estatus"];
                                    if ($id_cliente != '0') {
                                        $rowcliente = mysqli_fetch_assoc(mysqli_query($link, "SELECT CONCAT(nombre,' ',apellido_paterno,' ',apellido_materno) as nombre, credito FROM cc_clientes WHERE id_sucursal = '$id_sucursal' and id_cliente = $id_cliente"));
                                    }
                                }
                                ?> 
                                <tfoot>
                                    <tr>
                                        <th></th>
                                        <th></th>
                                        <?php
                                        if ($descripcion_corta == 1) {
                                            echo
                                            '<th></th>';
                                        }
                                        ?>
                                        <th></th>
                                        <th>Total de venta</th>
                                        <th id="total_venta"></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div id="alert_cliente">
                        </div>

                        <br>
                        <div class="align-content-center ">
                            <a href="#" onclick="agrega_cliente()" class="btn btn-primary m-1" role="button">Agrega cliente</a>
                            <a href="inicio.php" class="btn btn-primary m-1" role="button">Nueva venta</a>
                            <a href="#" onclick="elimina_venta()" class="btn btn-primary m-1" role="button">Eliminar venta</a>
                            <a href="#" onclick="abrir_caja()" class="btn btn-primary m-1" role="button" id="btn_abrir">Abrir Caja</a>
                            <a href="#" onclick="cerrar_venta()" class="btn btn-primary m-1" role="button" id="btn_cerrarventa">Cerrar venta</a>

                        </div>
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
                                        <tr><td id="td_venta">Número venta: 
                                                <?php
                                                if (isset($_GET['id_venta'])) {
                                                    echo $id_venta;
                                                }
                                                ?>
                                            </td></tr>
                                        <tr><td id="td_fecha">Fecha: 
                                                <?php
                                                if (isset($_GET['id_venta'])) {
                                                    echo $rowdetventas['fecha_ingreso'];
                                                }
                                                ?>
                                            </td></tr>
                                        <tr><td id="td_hora">Hora: 
                                                <?php
                                                if (isset($_GET['id_venta'])) {
                                                    echo $rowdetventas['hora_ingreso'];
                                                }
                                                ?>
                                            </td></tr>
                                        <tr><td id="td_cliente">
                                                <?php
                                                if (isset($_GET['id_venta'])) {
                                                    if ($id_cliente != '0') {
                                                        echo 'Cliente: ' . $rowcliente["nombre"];
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
                                <thead>
                                    <tr>
                                        <th>Descripción</th>
                                        <th>Precio</th>
                                        <th>Cantidad</th>
                                        <?php
                                        if ($descripcion_corta == 1) {
                                            echo
                                            '<th>CVE E</th>';
                                        }
                                        ?>
                                        <th>Importe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                                <tfoot>
                                    <?php
                                    if ($descripcion_corta == 1) {
                                        $colspan = 4;
                                    } else {
                                        $colspan = 3;
                                    }
                                    ?>
                                    <tr>
                                        <th colspan="<?php echo $colspan ?>" style="border-bottom: hidden;border-left: hidden; text-align: right; font-size:15">Total Venta</th>
                                        <th style="font-size:15" id="total_venta_i"></th>
                                    </tr>
                                    <tr>
                                        <th colspan="<?php echo $colspan ?>" style="border-bottom: hidden;border-left: hidden; text-align: right">Importe recibido</th>
                                        <th id="recibido_i"></th>
                                    </tr>
                                    <tr>
                                        <th colspan="<?php echo $colspan ?>" style="border-bottom: hidden;border-left: hidden; text-align: right">Cambio</th>
                                        <th id="cambio_i"></th>
                                    </tr>
                                </tfoot>                            
                            </table>
                            <br><br><br><br>
                        </div>
                        <!-- Div de impresión para abrir caja -->
                        <div class="table-responsive" id="div_impresion_abrir" style="display:none">
                            <style>
                                #tabla_impresion_a, #tabla_impresion_a th, #tabla_impresion_a td {
                                    border: 1px solid;
                                    border-collapse: collapse;
                                    font-size: 10px;
                                }
                                #encabezado_a, #encabezado_a tr, #encabezado_a td {
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
                                    <table id="encabezado_a"  style="width:100%;">
                                        <tr><td id="td_fecha_a">Fecha: 
                                            </td></tr>
                                        <tr><td id="td_hora_a">Hora: 
                                            </td></tr>
                                    </table>
                                </div>
                            </div>
                            <div style="text-align: center">
                                <img src="../img/logo_1.jpeg" alt="MDN" width="100" height="100">
                            </div>
                            <table id="tabla_impresion_a"  style="width:100%;">
                                <tr>
                                    <th>Comentarios</th>
                                </tr>
                                <tr>
                                    <td id="td_comentarios_a"></td>
                                </tr>
                            </table>
                            <br><br><br><br>
                        </div>
                        <br>
                        <br>
                        <br>
                        <div class="col-sm-8 mx-auto">
                            <h3>Ventas pendientes</h3>
                        </div>
                        <div class="table-responsive">
                            <table id="det_ventas" class="table_ventas table-striped table-hover" style="width:40%" >
                                <thead>
                                    <tr>
                                        <th>Id </th>
                                        <th>Hora</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <?php
                                $sqldetventas = mysqli_query($link, "select * from cc_det_ventas where id_sucursal = '$id_sucursal' and estatus = 0");
                                while ($rowv = mysqli_fetch_assoc($sqldetventas)) {
                                    echo '
                                    <tr id="fila' . $rowv['id_venta'] . '">
                                        <td>' . $rowv['id_venta'] . '</td>
                                        <td>' . $rowv['hora_ingreso'] . '</td>
                                        <td align="center">
                                            <a href="?accion=delete&id=' . $rowv['id_venta'] . '" title="Eliminar" onclick="return confirm(\'¿Esta seguro de borrar venta ' . $rowv['id_venta'] . '?\')"><img class="imga" src="../img/icons/trash.svg"></a>
                                            <a href="?id_venta=' . $rowv['id_venta'] . '" title="Seleccionar venta"><img class="imga" src="../img/icons/pencil-square.svg"></a>
                                        </td>
                                    </tr>';
                                }
                                ?>  
                                <tfoot>  
                                </tfoot>
                            </table> 
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Modal para complementar el producto a vender -->
        <div class="modal fade modal-lg" id="editModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ModalLabelTitle1">Agregar producto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form class="needs-validation" action="#" method="post" novalidate id="form_producto">
                        <?php
                        if (isset($_GET['id_venta'])) {
                            echo'
                            <input type="hidden" name="id_venta" id="id_venta" value = "' . $_GET['id_venta'] . '">';
                        } else {
                            echo'
                            <input type="hidden" name="id_venta" id="id_venta" value = "0">';
                        }
                        ?>
                        <input type="hidden" name="estatus" id="estatus" value = "<?php echo $estatus ?>">
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
                                <label for="precioventa" class="form-label">Precio venta</label>
                                <div class="input-group has-validation">
                                    <input type="number" step="0.01" class="form-control" id="precio_venta_e" name="precio_venta_e" placeholder="Precio de venta" autocomplete="off" required>
                                    <div class="invalid-feedback">
                                        Favor de ingresar precio de venta.
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
                            <?php
                            if ($descripcion_corta == 1) {
                                echo
                                '<div class="mb-3" >';
                            } else {
                                echo
                                '<div class="mb-3 d-none">';
                            }
                            ?>
                            <label for="clave_externa" class="form-label">Clave externa</label>
                            <input type="text" class="form-control" id="clave_externa_e" name="clave_ext_e" placeholder="Clave externa" autocomplete="off">
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

    <!-- Modal para agreagar cliente -->
    <div class="modal fade modal-lg" id="Modalclientes" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ModalLabelTitle2"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form class="needs-validation" action="#" method="post" novalidate id="form_cliente">
                    <?php
                    if (isset($_GET['id_venta'])) {
                        echo'
                            <input type="hidden" name="id_cliente" id="id_cliente" value = "' . $rowdetventas['id_cliente'] . '">';
                    } else {
                        echo'
                            <input type="hidden" name="id_cliente" id="id_cliente" value = "0">';
                    }
                    ?>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Cliente</label>
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Nombre del cliente" id="cliente" name="cliente" autocomplete="off">
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

    <!-- Modal para cierre de venta -->
    <div class="modal fade modal-lg" id="Modalventa" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ModalLabelTitle3">Nuevo mensaje</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form class="needs-validation" action="#" method="post" novalidate id="form_venta">
                    <input type="hidden" name="id_empleado" id="id_empleado" value = "0">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Total de venta:</label>
                            <div class="input-group">
                                <label for="descripcion" class="form-label" id="total_v"></label>
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
                            <label for="precioventa" class="form-label">Efectivo</label>
                            <div class="input-group has-validation">
                                <input type="number" step="0.01" class="form-control" id="pago_v" name="pago_v" placeholder="Efectivo" autocomplete="off" required min="0" onkeyup="calculacambio()">
                                <div class="invalid-feedback">
                                    Mayor que 0
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="almacen" class="form-label">Cambio</label>
                            <div class="input-group has-validation">
                                <label for="descripcion" class="form-label" id="cambio_v">0.00</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Empleado:</label>
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Ingrese el empleado que atendió" id="empleados" name="cliente" autocomplete="off">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <?php
                            if (tienePermiso('ver')) {
                                echo '
                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="preimpresion()">Imprimir</button>';
                            }
                            ?>
                            <button type="submit" class="btn btn-primary">CERRAR VENTA</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para abrir caja-->
    <div class="modal fade modal-lg" id="Modalabrir" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Abrir caja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form class="needs-validation" action="#" method="post" novalidate id="form_abrir">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="comentarios" class="form-label">Comentarios</label>
                            <div class="input-group has-validation">
                                <textarea class="form-control" id="comentarios_v" name="comentarios_v" placeholder="Comentarios" autocomplete="off" required></textarea>
                                <div class="invalid-feedback">
                                    Favor de ingresar el comentario
                                </div>
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

    <!-- Modal para previsualizar la impresión-->
    <div class="modal fade modal-lg" id="Modal_pre_impresion" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Previsualización venta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form class="needs-validation" action="#" method="post" novalidate id="form_pre_imprimir">
                    <div class="modal-body">
                        <div class="mb-3" id="body_pre_impresion">

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Aceptar</button>
                        </div>
                    </div>
                </form>';
            </div>
        </div>
    </div>

    <br>
    <br>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
                                    var tD;
                                    var td;
                                    $(document).ready(function () {
                                        $('#venta').DataTable({
                                            paging: false,
                                            ordering: false,
                                            info: false,
                                            searching: false,
                                            footerCallback: function (tfoot, data, start, end, display) {
                                                if (data.length > 0)
                                                    $('#btn_cerrarventa').removeClass('disabled');
                                                else
                                                    $('#btn_cerrarventa').addClass('disabled');
                                            },
                                            columnDefs: [
                                                {width: '70px', targets: [5]},
                                                {className: 'dt-left', targets: '_all'},
                                                {className: 'dt-center', targets: [5]}
                                            ],
                                            language: {zeroRecords: 'Sin productos'}
                                        });


                                        makeEditable();
                                        sumaTotal();
                                        var t1 = $('#det_ventas').DataTable(
                                                {
                                                    paging: false,
                                                    ordering: false,
                                                    info: false,
                                                    searching: false,
                                                    columnDefs: [
                                                        {width: '70px', targets: [2]}, {className: 'dt-left', targets: '_all'}, ],
                                                    language: {zeroRecords: 'Sin Ventas pendientes'}
                                                }
                                        );



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
                                                        $("#precio_venta_e").val(response[0].precio_venta);
                                                        $("#cantidad_e").val("");
                                                        $("#total_e").val("");
                                                        $("#clave_externa_e").val("");
                                                        setTimeout(() => {
                                                            $("#cantidad_e").focus();
                                                        }, 500);

                                                    } else {
                                                        edita_venta(response[0].codigo, response[0].peso, response[0].precio_venta, 0, 1, '', '');
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

                                        $('#cliente').typeahead({
                                            source: function (busqueda, process) {
                                                $.ajax({
                                                    url: "../functions/consulta_clientes.php",
                                                    data: 'busqueda=' + busqueda,
                                                    dataType: "json",
                                                    type: "POST",
                                                    async: false,
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
                                                $("#td_cliente").html('Cliente: ' + item.name);
                                            },
                                            minLength: 2,
                                            delay: 600
                                        });
                                        //Tablajeros cierre de venta
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
if (isset($_GET['id_venta'])) {
    if ($id_cliente != '0') {
        echo
        '                                       agrega_alerta(\'' . $rowcliente["nombre"] . '\', ' . $rowdetventas["id_cliente"] . ',' . $rowcliente["credito"] . ');';
    }
}
?>
                                    });

                                    function getdesc(arreglo) {
                                        var desc = [arreglo.id] + ' - ' + [arreglo.name];
                                        return desc;
                                    }
                                    function remove(objeto, consecutivo) {
                                        var table = $('#venta').DataTable();
                                        table.row($(objeto).parents('tr')).remove().draw();
                                        edita_venta(0, 0, 0, consecutivo, 2, '', '');
                                        sumaTotal();
                                    }

                                    function calculatotal(valor)
                                    {
                                        $("#total_e").val((parseFloat($("#precio_venta_e").val()) * valor).toFixed(2));
                                    }

                                    function calculacantidad(valor)
                                    {
                                        $("#cantidad_e").val((valor / parseFloat($("#precio_venta_e").val())).toFixed(3));
                                    }
                                    function calculacambio()
                                    {
                                        if (isNaN($("#pago_v").val()) || $("#pago_v").val() == '')
                                        {
                                            valor = 0;
                                        } else
                                        {
                                            valor = parseFloat($("#pago_v").val())
                                        }
                                        if ((valor - parseFloat($("#total_venta").html())).toFixed(2) < 0)
                                        {
                                            $("#cambio_v").html(0);
                                        } else
                                        {
                                            $("#cambio_v").html((valor - parseFloat($("#total_venta").html())).toFixed(2));
                                        }
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
                                    document.getElementById("form_cliente").addEventListener("submit", function (event) {
                                        event.preventDefault(); // Evita que el formulario se envíe automáticamente
                                        agregarCliente(); // Llama a la función JavaScript para procesar los datos del formulario
                                    });

                                    var myForm_venta = document.getElementById('form_venta');
                                    myForm_venta.addEventListener('submit', function (event) {
                                        event.preventDefault(); // Evitar que el formulario se envíe automáticamente
                                        // Validar el formulario
                                        if (myForm_venta.checkValidity() === false) {
                                            // Si hay errores, no hacer nada
                                            event.stopPropagation();
                                        } else {
                                            guarda_venta();// Llama a la función JavaScript para agregar a la tabla
                                            $('#Modalventa').modal('hide');
                                        }
                                        // Agregar la clase "was-validated" para mostrar los errores
                                        myForm_venta.classList.add('was-validated');
                                    });

                                    document.addEventListener('keydown', function (event) {
                                        if (event.key === 'Enter' && event.ctrlKey) {
                                            // Acción a ejecutar cuando se presiona Ctrl + Enter
                                            if ($('#venta').DataTable().data().count() > 0)
                                            {
                                                cerrar_venta();
                                            }
                                        }
                                    });
                                    //eventos form pre-impresión
                                    var myForm_preimpresion = document.getElementById('form_pre_imprimir');
                                    myForm_preimpresion.addEventListener('submit', function (event) {
                                        event.preventDefault(); // Evitar que el formulario se envíe automáticamente
                                        abrepaginaimpresion('div_impresion');
                                        $('#Modal_pre_impresion').modal('hide');
                                        var myModal = new bootstrap.Modal(document.getElementById("Modalventa"), {});
                                        myModal.show();
                                        // Agregar la clase "was-validated" para mostrar los errores

                                    });
                                    $('#Modal_pre_impresion').on('hidden.bs.modal', function (e) {
                                        var myModal = new bootstrap.Modal(document.getElementById("Modalventa"), {});
                                        myModal.show();
                                    });

                                    function agregarDatos() {
                                        //$("#precio_venta_e").val();
                                        edita_venta($("#codigo_e").html(), $("#cantidad_e").val(), $("#precio_venta_e").val(), 0, 1, $("#clave_externa_e").val(), '');
                                        $("#codigo").val("");
                                        $("#codigo").focus();
                                    }

                                    function agregarCliente() {
                                        $("#precio_venta_e").val();
                                        edita_venta($("#codigo_e").html(), $("#cantidad_e").val(), $("#precio_venta_e").val(), 0, 1, '', '');
                                        $("#codigo").val("");
                                        $("#codigo").focus();
                                    }
                                    function edita_venta(codigo, cantidad, precio_venta, consecutivo, movimiento, clave_externa, tipo_producto)
                                    {
                                        id_cliente = $("#id_cliente").val();
                                        id_venta = $("#id_venta").val();
                                        id_empleado = $("#id_empleado").val();
                                        importe_recibido = $("#pago_v").val();
                                        comentarios = $("#comentarios_v").val();

                                        tipo_pago = obtenerValorSeleccionado();
                                        //id_consecutivo = $("#id_consecutivo").val();
                                        id_consecutivo = consecutivo;
                                        var parametros = {"codigo": codigo, "precio_venta": precio_venta, "cantidad": cantidad, "id_cliente": id_cliente, "id_venta": id_venta,
                                            "id_consecutivo": id_consecutivo, "movimiento": movimiento, "id_empleado": id_empleado, "importe_recibido": importe_recibido,
                                            "comentarios": comentarios, "tipo_pago": tipo_pago, "clave_externa": clave_externa, "tipo_producto": tipo_producto};
                                        $.ajax({
                                            url: "../functions/edita_venta.php",
                                            data: parametros,
                                            dataType: "json",
                                            type: "POST",
                                            async: false,
                                            success: function (response) {
                                                $("#id_venta").val(response[0].id_venta);
                                                console.log(response);
                                                if (movimiento === 1)
                                                {
                                                    total = (parseFloat(precio_venta) * parseFloat(cantidad)).toFixed(2);
                                                    tD = $('#venta').DataTable();

<?php
if ($descripcion_corta == 1) {
    echo 'tD.row.add([codigo, response[0].descripcion, precio_venta, cantidad, response[0].clave_externa, total, \'<a href="#" onclick="remove(this,\' + response[0].id_consecutivo + \');" title="Eliminar" class=".remove"><img src="../img/icons/trash.svg"></a>\']).node().id = response[0].id_venta + \',\' + response[0].id_consecutivo;';
} else {
    echo 'tD.row.add([codigo, response[0].descripcion, precio_venta, cantidad, total, \'<a href="#" onclick="remove(this,\' + response[0].id_consecutivo + \');" title="Eliminar" class=".remove"><img src="../img/icons/trash.svg"></a>\']).node().id = response[0].id_venta + \',\' + response[0].id_consecutivo;';
}
?>



                                                    tD.draw(false);
                                                    makeEditable();
                                                    sumaTotal();
                                                }
                                                if (movimiento === 3)
                                                {
                                                    $("#id_cliente").val(0);
                                                    location.href = "inicio.php";
                                                }
                                                if (movimiento === 5) {
                                                    $("#td_venta").html('Número de venta: ' + response[0].id_venta);
                                                    $("#td_fecha").html('Fecha: ' + response[0].fecha_ingreso);
                                                    $("#td_hora").html('Hora: ' + response[0].hora_ingreso);
                                                    //Realiza la impresión después de almacenar la información
                                                    imprSelec('div_impresion');
                                                    location.href = "inicio.php";
                                                }
                                                if (movimiento === 6) {
                                                    $("#td_comentarios_a").html(response[0].comentarios);
                                                    $("#td_fecha_a").html('Fecha: ' + response[0].fecha_ingreso);
                                                    $("#td_hora_a").html('Hora: ' + response[0].hora_ingreso);
                                                }
                                            },
                                            error: function (response) {
                                                console.log(response);
                                                id_venta = 0;
                                            }
                                        });
                                    }
                                    function elimina_venta(codigo, cantidad, precio_venta, consecutivo, movimiento) {
                                        edita_venta(0, 0, 0, 0, 3, '', '');
                                    }
                                    function cerrar_venta(codigo, cantidad, precio_venta, consecutivo, movimiento) {
                                        var myModal = new bootstrap.Modal(document.getElementById("Modalventa"), {});
                                        myModal.show();
                                        $("#ModalLabelTitle3").html("Cerrar venta");
                                        $("#total_v").html($("#total_venta").html());
                                        calculacambio();
                                        setTimeout(() => {
                                            $("#pago_v").focus();
                                        }, 500);
                                    }
                                    function agrega_cliente() {
                                        var myModal = new bootstrap.Modal(document.getElementById("Modalclientes"), {});
                                        myModal.show();
                                        $("#ModalLabelTitle2").html("Agregar cliente");
                                        $("#cliente").val("");
                                        setTimeout(() => {
                                            $("#cliente").focus();
                                        }, 500);
                                    }
                                    function agrega_alerta(nom_cliente, id_cliente, credito)
                                    {
                                        saldo = 0;
                                        parametros = {"id_cliente": id_cliente};
                                        $.ajax({
                                            url: "../functions/consulta_det_cliente.php",
                                            data: parametros,
                                            dataType: "json",
                                            type: "POST",
                                            async: false,
                                            success: function (data) {
                                                saldo = data[0].saldo;
                                            },
                                            error: function (response) {
                                                console.log(response);
                                                alert('Error');
                                            }
                                        });
                                        $("#id_cliente").val(id_cliente);
                                        edita_venta(0, 0, 0, 0, 4, '', '');
                                        $('#div_alert').remove();
                                        var wrapper = document.createElement('div').innerHTML = '<div class="alert alert-secondary alert-dismissible" role="alert" id="div_alert"><strong>CLIENTE:</strong> ' + nom_cliente + ' | Saldo: ' + saldo + ' <a href="#" onclick="elimina_cliente()" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>'
                                        $('#alert_cliente').append(wrapper);
                                    }
                                    function elimina_cliente() {
                                        $("#id_cliente").val(0);
                                        edita_venta(0, 0, 0, 0, 4, '', '');
                                        $("#td_cliente").html('');
                                    }
                                    function guarda_venta()
                                    {
                                        edita_venta(0, 0, 0, 0, 5, '', '');
                                    }
                                    function llenadatosimpresion()
                                    {
                                        var tablaOriginal = document.getElementById('venta');
                                        var tbody = document.querySelector('#tabla_impresion tbody');
                                        $('#tabla_impresion tbody').empty();
                                        var columna2 = tablaOriginal.querySelectorAll('td:nth-child(2)');
                                        var columna3 = tablaOriginal.querySelectorAll('td:nth-child(3)');
                                        var columna4 = tablaOriginal.querySelectorAll('td:nth-child(4)');
                                        var columna5 = tablaOriginal.querySelectorAll('td:nth-child(5)');
<?php
if ($descripcion_corta == 1) {
    echo
    "var columna6 = tablaOriginal.querySelectorAll('td:nth-child(6)');";
}
?>
                                        columna2.forEach(function (elemento, index) {
                                            var nuevaFila = document.createElement('tr');
                                            var nuevaCelda2 = document.createElement('td');
                                            var nuevaCelda3 = document.createElement('td');
                                            var nuevaCelda4 = document.createElement('td');
                                            var nuevaCelda5 = document.createElement('td');
<?php
if ($descripcion_corta == 1) {
    echo
    "var nuevaCelda6 = document.createElement('td');";
}
?>
                                            nuevaCelda2.textContent = elemento.textContent;
                                            nuevaCelda3.textContent = columna3[index].textContent;
                                            nuevaCelda4.textContent = columna4[index].textContent;
                                            nuevaCelda5.textContent = columna5[index].textContent;
<?php
if ($descripcion_corta == 1) {
    echo
    "nuevaCelda6.textContent = columna6[index].textContent;";
}
?>
                                            nuevaFila.appendChild(nuevaCelda2);
                                            nuevaFila.appendChild(nuevaCelda3);
                                            nuevaFila.appendChild(nuevaCelda4);
                                            nuevaFila.appendChild(nuevaCelda5);
<?php
if ($descripcion_corta == 1) {
    echo
    "nuevaFila.appendChild(nuevaCelda6);";
}
?>
                                            tbody.appendChild(nuevaFila);
                                        });

                                        $("#total_venta_i").html($("#total_venta").html());
                                        $("#recibido_i").html($("#pago_v").val());
                                        calculacambio();
                                        $("#cambio_i").html($("#cambio_v").html());
                                    }
                                    function abrepaginaimpresion(nombre) {
                                        var ficha = document.getElementById(nombre);
                                        var altura = 480;
                                        var anchura = 630;
                                        var y = parseInt((window.screen.height / 2) - (altura / 2));
                                        var x = parseInt((window.screen.width / 2) - (anchura / 2));
                                        var ventimp = window.open('Imprimir.html', target = 'blank', 'width=' + anchura + ',height=' + altura + ',top=' + y + ',left=' + x + ',toolbar=no,location=no,status=no,menubar=no,scrollbars=no,directories=no,resizable=no')
                                        ventimp.document.write(ficha.innerHTML);
                                        ventimp.document.close();
                                        ventimp.print();
                                        ventimp.close();
                                    }
                                    function imprSelec(nombre) {
                                        llenadatosimpresion();
                                        abrepaginaimpresion(nombre)
                                    }
                                    function makeEditable() {
                                        $('#venta').dataTable().makeEditable({
                                            sUpdateURL: "../functions/actualiza_venta_previo.php",
                                            aoColumns: [
                                                null,

                                                null,
                                                {
                                                    type: 'number',
                                                    indicator: 'guardando información...',
                                                    tooltip: 'Click para editar precio',
                                                    cssclass: 'required',
                                                    sColumnName: 'precio_venta',
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
                                                const table = document.getElementById('venta');
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
<?php
if ($descripcion_corta == 1) {
    echo
    'const thirdCell = row.cells[5];';
} else {
    echo
    'const thirdCell = row.cells[4];';
}
?>

                                                const firstValue = parseFloat(firstCell.innerText);
                                                const secondValue = parseFloat(secondCell.innerText);
                                                const multiplicationResult = firstValue * secondValue;
                                                const roundedResult = multiplicationResult.toFixed(2);
                                                thirdCell.innerText = roundedResult;
                                                sumaTotal();

                                            }
                                        });

                                    }

                                    function abrir_caja()
                                    {
                                        var myModal = new bootstrap.Modal(document.getElementById("Modalabrir"));
                                        myModal.show();
                                        setTimeout(() => {
                                            $("#comentarios_v").focus();
                                        }, 500);
                                    }
                                    //Eventos para abrir caja
                                    var myForm_abrir = document.getElementById('form_abrir');
                                    myForm_abrir.addEventListener('submit', function (event) {
                                        event.preventDefault(); // Evitar que el formulario se envíe automáticamente
                                        // Validar el formulario
                                        if (myForm_abrir.checkValidity() === false) {
                                            // Si hay errores, no hacer nada
                                            event.stopPropagation();
                                        } else {
                                            edita_venta(0, 0, 0, 0, 6, '', '');// Llama a la función JavaScript para abrir caja
                                            $('#Modalabrir').modal('hide');
                                            imprime_abrir('div_impresion_abrir')
                                        }
                                        // Agregar la clase "was-validated" para mostrar los errores
                                        myForm_abrir.classList.add('was-validated');
                                    });
                                    function imprime_abrir(nombre)
                                    {
                                        var ficha = document.getElementById(nombre);
                                        var altura = 480;
                                        var anchura = 630;
                                        var y = parseInt((window.screen.height / 2) - (altura / 2));
                                        var x = parseInt((window.screen.width / 2) - (anchura / 2));
                                        var ventimp = window.open('Imprimir.html', target = 'blank', 'width=' + anchura + ',height=' + altura + ',top=' + y + ',left=' + x + ',toolbar=no,location=no,status=no,menubar=no,scrollbars=no,directories=no,resizable=no')
                                        ventimp.document.write(ficha.innerHTML);
                                        ventimp.document.close();
                                        ventimp.print();
                                        ventimp.close();
                                    }
                                    function sumaTotal()
                                    {
                                        var total = 0;
                                        $("#venta tr").each(function () {
<?php
if ($descripcion_corta == 1) {
    echo
    'var valor = parseFloat($(this).find("td:nth-child(6)").text());';
} else {
    echo
    'var valor = parseFloat($(this).find("td:nth-child(5)").text());';
}
?>



                                            if (!isNaN(valor)) {
                                                total += valor;
                                            }
                                        });
                                        $("#total_venta").html(total.toFixed(2));
                                    }
                                    function preimpresion()
                                    {
                                        llenadatosimpresion();
                                        //alert($('#body_impresion').text());
                                        $('#body_pre_impresion').html($('#div_impresion').html());
                                        $('#body_pre_impresion style').remove();
                                        $('#body_pre_impresion table').eq(0).attr('id', 'preencabezado');
                                        $('#body_pre_impresion table').eq(1).attr('id', 'tabla_preimpresion');
                                        $('#body_pre_impresion table tfoot tr').eq(0).find('th').addClass('fs-4')
                                        var myModal = new bootstrap.Modal(document.getElementById("Modal_pre_impresion"));
                                        myModal.show();
                                    }
                                    function obtenerValorSeleccionado() {
                                        var radioSeleccionado = document.querySelector('input[name="opcion"]:checked');
                                        if (radioSeleccionado) {
                                            return radioSeleccionado.value;
                                        } else {
                                            return null; // En caso de que ningún botón esté seleccionado
                                        }
                                    }
                                    document.addEventListener('DOMContentLoaded', function () {
                                        const radios = document.querySelectorAll('input[name="opcion"]');
                                        const pagoInput = document.getElementById('pago_v');

                                        radios.forEach(radio => {
                                            radio.addEventListener('change', function () {
                                                if (this.value === '1') {
                                                    pagoInput.disabled = false;
                                                    pagoInput.value = '';
                                                    //pagoInput.placeholder = "Ingrese el monto";
                                                } else {
                                                    pagoInput.disabled = true;
                                                    pagoInput.value = '0';
                                                    //pagoInput.placeholder = "No aplica para esta opción";
                                                }
                                                calculacambio();
                                            });
                                        });

                                    });

    </script>      
</body>
</html>
