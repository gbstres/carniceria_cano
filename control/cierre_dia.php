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

if (isset($_POST['fecha_cierre'])) {
    $hora_ingreso = date('H:i:s');
    $fecha_ingreso = $_POST['fecha_cierre'];
    $comentarios = "";
    $estatus = 0;
    $id_usuario = $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('y-m-d');
    $hora_act = date('H:i:s');
    $rowcierre = mysqli_fetch_assoc(mysqli_query($link, "SELECT max(id_cierre) as id_cierre FROM cc_cierre WHERE id_sucursal = '$id_sucursal'"));
    $id_cierre = $rowcierre['id_cierre'];
    if ($id_cierre == null) {
        $id_cierre = 1;
    } else {
        $id_cierre = $ultimo_cierre = $id_cierre + 1;
    }

    $exito = "S";
    $sql = "INSERT INTO cc_cierre (id_sucursal, id_cierre, clave, importe, comentarios, estatus, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    for ($i = 1; $i <= 9; $i++) {
        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            $importe = $_POST['clave_' . strval($i)];
            $clave = $i;
            mysqli_stmt_bind_param($stmt, "iiidsiiss", $id_sucursal, $id_cierre, $clave, $importe, $comentarios, $estatus, $id_usuario, $fecha_ingreso, $hora_ingreso);
            if (mysqli_stmt_execute($stmt)) {
                
            } else {
                $exito = 'N';
            }
        }
    }

    //Insertar saldos de clientes
    $sqlclientes = mysqli_query($link,
            "SELECT  a.id_cliente, b.efectivo_hoy as saldo
                FROM cc_clientes AS a
                JOIN cc_saldos_clientes AS b
                ON a.id_sucursal = b.id_sucursal AND a.id_cliente = b.id_cliente
                WHERE a.id_sucursal = $id_sucursal AND activo = 1 and b.efectivo_hoy >= 100");
    $sql = "INSERT INTO cc_cierre_clientes (id_sucursal, id_cierre, id_cliente, saldo, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?)";
    while ($rowcte = mysqli_fetch_assoc($sqlclientes)) {
        if ($stmt = mysqli_prepare($link, $sql)) {
            $id_cliente = $rowcte['id_cliente'];
            $saldo = $rowcte['saldo'];
            mysqli_stmt_bind_param($stmt, "iiidiss", $id_sucursal, $id_cierre, $id_cliente, $saldo, $id_usuario, $fecha_ingreso, $hora_ingreso);
            if (mysqli_stmt_execute($stmt)) {
                
            } else {
                $exito = 'N';
            }
        }
    }



    if ($exito == 'N') {
        echo '<script type="text/javascript">alert("No se pudo almacenar el cierre");</script>';
    } else {
        $estatus = 3;
        $update1 = mysqli_query($link, "UPDATE cc_det_ventas SET "
                        . "estatus='$estatus', id_cierre=$id_cierre, "
                        . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                        . "WHERE id_sucursal='$id_sucursal' and estatus not in (3)")
                or die(mysqli_error());
        $update2 = mysqli_query($link, "UPDATE cc_gastos SET "
                        . "estatus='$estatus', id_cierre=$id_cierre, "
                        . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                        . "WHERE id_sucursal='$id_sucursal' and estatus not in (3)")
                or die(mysqli_error());
        $update3 = mysqli_query($link, "UPDATE cc_entradas SET "
                        . "estatus='$estatus', id_cierre=$id_cierre, "
                        . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                        . "WHERE id_sucursal='$id_sucursal' and estatus not in (3)")
                or die(mysqli_error());
        $update4 = mysqli_query($link, "UPDATE cc_pagos_clientes SET "
                        . "estatus='$estatus', id_cierre=$id_cierre, "
                        . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                        . "WHERE id_sucursal='$id_sucursal' and estatus not in (3)")
                or die(mysqli_error());
        $update5 = mysqli_query($link, "UPDATE cc_gastos SET "
                        . "estatus='$estatus', id_cierre=$id_cierre, "
                        . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                        . "WHERE id_sucursal='$id_sucursal' and estatus not in (3)")
                or die(mysqli_error());
    }
}

if (isset($_POST['fecha'])) {
    $fecha = $_POST['fecha'];
} else {
    $fecha = date('Y-m-d');
}

$estatus = $totalv = $totalc = $efectivo = $entrada_efectivo = $salida_efectivo = $a_cuenta = $a_cuenta_efectivo = $a_cuenta_bancario = 0;

$total_venta = 0;
$sql = "
    SELECT
        MAX(b.fecha_ingreso),
        SUM(ROUND(b.cantidad * b.precio_venta, 2)) AS totalv,
        SUM(CASE WHEN a.tipo_pago = 1 THEN ROUND(b.cantidad * b.precio_venta, 2) END) AS clave1,
        SUM(CASE WHEN a.tipo_pago = 2 THEN ROUND(b.cantidad * b.precio_venta, 2) END) AS clave8,
	SUM(CASE WHEN a.tipo_pago = 3 THEN ROUND(b.cantidad * b.precio_venta, 2) END) AS clave9,
	SUM(CASE WHEN a.id_cliente > 0 THEN ROUND(b.cantidad * b.precio_venta, 2) END) AS clave4
    FROM
        cc_det_ventas AS a
    INNER JOIN cc_ventas AS b
    ON
        a.id_sucursal = b.id_sucursal AND a.id_venta = b.id_venta "
        . " where a.id_sucursal = '$id_sucursal' and a.estatus = 1 and b.estatus = 0";
$sqlventa = mysqli_query($link, $sql);
$row_venta = mysqli_fetch_assoc($sqlventa);

$val_claves = array();
for ($i = 0; $i <= 9; $i++) {
    $val_claves[$i] = 0;
}
//efectivo
if (isset($row_venta['clave1'])) {
    $val_claves[1] = $row_venta['clave1'];
}
//Transferencia
if (isset($row_venta['clave8'])) {
    $val_claves[8] = $row_venta['clave8'];
}
//Tarjeta
if (isset($row_venta['clave9'])) {
    $val_claves[9] = $row_venta['clave9'];
}

// A cuenta (venta a clientes)
if (isset($row_venta['clave4'])) {
    $val_claves[4] = $row_venta['clave4'];
}

if (isset($row_venta['totalv'])) {
    $totalv = $row_venta['totalv'];
}

if (isset($row_venta['total_venta'])) {
    $efectivo = $row_venta['total_venta'];
}

if (isset($row_venta['total_clientes'])) {
    $a_cuenta = $row_venta['total_clientes'];
}

//Salida efectivo
$sqlgastos = mysqli_query($link, "SELECT sum(ROUND(a.cantidad * a.precio,2)) AS total_gastos "
        . "FROM cc_gastos as a "
        . "WHERE a.id_sucursal = '$id_sucursal' and a.estatus = 0;");
$row_gastos = mysqli_fetch_assoc($sqlgastos);
if (isset($row_gastos['total_gastos'])) {
    $salida_efectivo = $row_gastos['total_gastos'];
    $val_claves[3] = $row_gastos['total_gastos'];
}

//Entrada efectivo
$sqlentradas = mysqli_query($link, "SELECT sum(ROUND(a.cantidad * a.precio,2)) AS total_entradas "
        . "FROM cc_entradas as a "
        . "WHERE a.id_sucursal = '$id_sucursal' and a.estatus = 0;");
$row_entradas = mysqli_fetch_assoc($sqlentradas);
if (isset($row_entradas['total_entradas'])) {
    $val_claves[2] = $row_entradas['total_entradas'];
}


$sqlpagos = mysqli_query($link, "SELECT sum(a.importe) total_pagos "
        . "FROM cc_pagos_clientes as a "
        . "WHERE a.id_sucursal = '$id_sucursal' and a.estatus = 0 and tipo_pago = 1;");
$row_pagos = mysqli_fetch_assoc($sqlpagos);
if (isset($row_pagos['total_pagos']))
    $a_cuenta_efectivo = $row_pagos['total_pagos'];

$sqlpagos_bancario = mysqli_query($link, "
        SELECT
	SUM(CASE WHEN a.tipo_pago = 1 THEN a.importe END) AS efectivo,
	SUM(CASE WHEN a.tipo_pago = 2 THEN a.importe END) AS transferencia,
	SUM(CASE WHEN a.tipo_pago = 3 THEN a.importe END) AS tarjeta 
        FROM cc_pagos_clientes as a  "
        . " WHERE a.id_sucursal = '$id_sucursal' and a.estatus = 0;");
$row_pagos_bancario = mysqli_fetch_assoc($sqlpagos_bancario);
//
if (isset($row_pagos_bancario['efectivo']))
    $val_claves[5] = $row_pagos_bancario['efectivo'];
if (isset($row_pagos_bancario['transferencia']))
    $val_claves[6] = $row_pagos_bancario['transferencia'];
if (isset($row_pagos_bancario['tarjeta']))
    $val_claves[7] = $row_pagos_bancario['tarjeta'];

$totalc = $val_claves[1] + $val_claves[2] + $val_claves[5] - $val_claves[3] - $val_claves[4]
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Carnicería Cano">
        <meta name="author" content="Gerardo Bautista">
        <link rel="shortcut icon" href="../img/logo_1.png">
        <title>Reporte de caja</title>

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
            .spinner-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            }
            .hidden {
                display: none !important;
            }
            .card {
                margin-top: 20px;
            }
            .w-70 {
                width: 70% !important;
            }
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
                            <h1 class="text-center">Reporte caja</h1>
                        </div>
                        <br>
                        <br>
                        <form class="row g-3 needs-validation" action="#" method="post" novalidate>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="Fecha" class="form-label">Seleccione la fecha:</label>
                                    <input name="fecha" id="datepicker" width="276" autocomplete="off" readonly="" value="<?php echo $fecha ?>"/>
                                </div>
                            </div>
                            <div class="col-12 text-center" >
                                <input class="btn btn-primary black bg-silver" type="submit" value="Buscar" id="buscar_fecha">
                            </div>
                            <div class="col-12 text-center">
                                <div id="resultado" class="card mt-4 hidden">
                                    <div class="card-header">
                                        Resultado del respaldo
                                    </div>
                                    <div class="card-body">
                                        <p id="resultadoTexto"></p>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <br>
                        <div class="table-responsive">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Ventas editadas</h5>
                                </div>
                            </div>
                            <table id="ventas0" class="display" style="width:100%" >
                                <thead>
                                    <tr>
                                        <th>Id</th>
                                        <th>Fecha</th>
                                        <th>Hora</th>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th>Cliente</th>
                                        <th>Precio V</th>
                                        <th>Cantidad</th>
                                        <th>Imp V</th>
                                        <th>Usuario</th>
                                    </tr>
                                </thead>
                                <?php
                                $fecha1 = $fecha2 = date('Y-m-d');
                                $id_categoria = '0';
                                $sqlventas = mysqli_query($link, "SELECT a.id_venta,a.fecha_ingreso,a.hora_ingreso,b.codigo,b.precio_compra,b.precio_venta,b.cantidad,"
                                        . "round(b.precio_compra * b.cantidad,2) as importec, round(b.precio_venta * b.cantidad,2) as importev, a.id_cliente, b.id_consecutivo,b.estatus,b.id_usuario "
                                        . "FROM cc_det_ventas as a inner join cc_ventas as b on a.id_sucursal = b.id_sucursal and a.id_venta = b.id_venta "
                                        . "WHERE a.id_sucursal = '$id_sucursal' and a.estatus in (1) and a.id_cierre in (0) and b.id_usuario_act > 0 "
                                        . " order by a.id_venta DESC");

                                $renglon = $ganancia = 0;
                                while ($rowc = mysqli_fetch_assoc($sqlventas)) {
                                    $renglon = $renglon + 1;
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = 'ROL' and id_clave =" . $rowp['rol']));
                                    $producto = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_productos where id_sucursal = '$id_sucursal' and codigo =" . $rowc['codigo']));
                                    $cliente = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_clientes where id_sucursal = '$id_sucursal' and id_cliente =" . $rowc['id_cliente']));
                                    $usuario = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id =" . $rowc['id_usuario']));
                                    if ($id_categoria == '0' or ($producto['id_categoria'] == $id_categoria)) {
                                        if (empty($cliente)) {
                                            $nombre_cliente = '';
                                        } else {
                                            $nombre_cliente = $cliente['nombre'] . ' ' . $cliente['apellido_paterno'];
                                        }
                                        if ($rowc['estatus'] == 2) {
                                            $estatus = "C";
                                            $importeV = 0;
                                        } else {
                                            $estatus = "N";
                                            $importeV = $rowc['importev'];
                                        }

                                        echo '
                                    <tr id="fila' . $renglon . '">
                                        <td>' . $rowc['id_venta'] . '</td>
                                        <td>' . $rowc['fecha_ingreso'] . '</td>
                                        <td>' . $rowc['hora_ingreso'] . '</td>
                                        <td>' . $rowc['codigo'] . '</td>
                                        <td>' . $producto['descripcion'] . '</td>    
                                        <td>' . $nombre_cliente . '</td>
                                        <td>' . $rowc['precio_venta'] . '</td>
                                        <td>' . $rowc['cantidad'] . '</td>
                                        <td>' . $importeV . '</td>
                                        <td>' . $usuario['username'] . '</td>
                                    </tr>
                                        ';
                                    }
                                }
                                ?>  
                            </table>
                            <br>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Gastos</h5>
                                </div>
                            </div>
                            <table id="gastos0" class="display w-70 mx-auto">
                                <thead>
                                    <tr>
                                        <th>Id</th>
                                        <th>Descripción</th>
                                        <th>Saldo</th>
                                    </tr>
                                </thead>
                                <?php
                                $sqlgastos = mysqli_query($link,
                                        "SELECT
                                        a.id_gasto,
                                        a.descripcion,
                                        a.precio,
                                        a.cantidad
                                    FROM
                                        cc_gastos as a
                                    WHERE
                                        a.id_sucursal = $id_sucursal AND a.estatus = 0");
                                $totalg = 0;
                                while ($rowgas = mysqli_fetch_assoc($sqlgastos)) {
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = 'ROL' and id_clave =" . $rowp['rol']));
                                    //$producto = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_productos where id_sucursal = '$id_sucursal' and codigo =" . $rowc['codigo']));
                                    //$cliente = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_clientes where id_sucursal = '$id_sucursal' and id_cliente =" . $rowc['id_cliente']));
                                    //$usuario = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id =" . $rowc['id_usuario']));
                                    echo '
                                    <tr id="">
                                        <td>' . $rowgas['id_gasto'] . '</td>
                                        <td>' . $rowgas['descripcion'] . '</td>
                                        <td>' . $rowgas['precio'] . '</td>
                                    </tr>
                                        ';
                                    $totalg = $totalg + $rowgas['precio'];
                                }
                                ?>
                                <tfoot>
                                    <tr>
                                        <th></th>
                                        <th id="total_gastos">Total</th>
                                        <th><?php echo number_format($totalg, 2) ?></th>
                                    </tr>
                                </tfoot>
                            </table>  
                            <br>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Clientes con saldo</h5>
                                </div>
                            </div>
                            <table id="clientes0" class="display w-70 mx-auto">
                                <thead>
                                    <tr>
                                        <th>Id</th>
                                        <th>Cliente</th>
                                        <th>Saldo</th>
                                    </tr>
                                </thead>
                                <?php
                                $sqlclientes = mysqli_query($link,
                                        "SELECT
                                        a.id_cliente,
                                        a.nombre,
                                        a.apellido_paterno,
                                        b.efectivo_hoy
                                    FROM
                                        cc_clientes AS a
                                    JOIN cc_saldos_clientes AS b
                                    ON
                                        a.id_sucursal = b.id_sucursal AND a.id_cliente = b.id_cliente
                                    WHERE
                                        a.id_sucursal = $id_sucursal AND a.activo = 1 and b.efectivo_hoy >= 100");

                                while ($rowcte = mysqli_fetch_assoc($sqlclientes)) {
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = 'ROL' and id_clave =" . $rowp['rol']));
                                    //$producto = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_productos where id_sucursal = '$id_sucursal' and codigo =" . $rowc['codigo']));
                                    //$cliente = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_clientes where id_sucursal = '$id_sucursal' and id_cliente =" . $rowc['id_cliente']));
                                    //$usuario = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id =" . $rowc['id_usuario']));


                                    $nombre_cliente = $rowcte['nombre'] . ' ' . $rowcte['apellido_paterno'];
                                    echo '
                                    <tr id="fila' . $renglon . '">
                                        <td>' . $rowcte['id_cliente'] . '</td>
                                        <td>' . $nombre_cliente . '</td>
                                        <td>' . $rowcte['efectivo_hoy'] . '</td>
                                    </tr>
                                        ';
                                }
                                ?>
                            </table>    

                            <br>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Resumen</h5>
                                </div>
                            </div>
                            <div class="table-responsive" id="cierr0">
                                <table id="cierre0" class="display" style="width:50%" >
                                    <thead>
                                        <tr>
                                            <th>Descripción</th>
                                            <th>Importe</th>
                                        </tr>
                                    </thead>


                                    <?php
                                    $sqlclaves = mysqli_query($link, "SELECT * FROM `cc_claves` WHERE nombre_clave = 'CIERRE' ORDER BY orden");
                                    while ($rowclave = mysqli_fetch_assoc($sqlclaves)) {
                                        echo'
                                        <tr class="read_only" id="' . $rowclave['clave'] . '">
                                            <td>' . $rowclave['descripcion'] . '</td>
                                            <td class="read_only">' . $val_claves[$rowclave['clave']] . '</td>
                                        </tr>
                                            ';
                                    }
                                    ?>
                                    <tfoot>
                                        <tr>
                                            <th id="total_gasto">Dinero en caja</th>
                                            <th><?php echo $totalc ?></th>
                                        </tr>
                                    </tfoot>
                                </table> 
                            </div>
                            <br>
                            <form  action="#" method="post" novalidate id="form_cierre">
                                <input type="hidden" name="fecha_cierre" id="fecha_cierre" value="<?php echo $fecha ?>">
                                <?php
                                $sqlclaves = mysqli_query($link, "SELECT * FROM `cc_claves` WHERE nombre_clave = 'CIERRE' ORDER BY orden");
                                while ($rowclave = mysqli_fetch_assoc($sqlclaves)) {
                                    echo'
                            <input type="hidden" name="clave_' . $rowclave['clave'] . '" id="clave_' . $rowclave['clave'] . '" value="' . $val_claves[$rowclave['clave']] . '">';
                                }
                                ?>

                                <div class="align-content-center text-center">
                                    <a href="#" onclick="cierre_caja()" class="btn btn-primary m-1" role="button" id="cierra">Cerrar caja</a>
                                </div>
                            </form>
                        </div>
                        <br>
                        <br>


















                        <div class="table-responsive">
                            <?php
                            $sql = "SELECT id_cierre FROM cc_cierre where id_sucursal = '$id_sucursal' and fecha_ingreso = '$fecha' GROUP by id_cierre";
                            $sqlcierres = mysqli_query($link, $sql);
                            while ($rowcierres = mysqli_fetch_assoc($sqlcierres)) {
                                $id_cierre = $rowcierres['id_cierre'];
                                echo '
                            <hr style="border-width: 2px;"><!-- comment -->
                            <div class="col-12 d-flex justify-content-center" >
                                <a href="#" onclick="imprimir(' . $id_cierre . ')" class="btn btn-primary m-1" role="button" id="cierra">Imprimir</a>
                            </div>
                            <br>
                            <div class="col-12 d-flex justify-content-center" style="text-align: center; text-height:15px; font-size: 20px; font-weight: bold;">
                                CIERRE ' . $id_cierre . '
                            </div>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Ventas editadas</h5>
                                </div>
                            </div>
                            <table id="ventas' . $id_cierre . '" class="display" style="width:100%" >
                                <thead>
                                    <tr>
                                        <th>Id</th>
                                        <th>Fecha</th>
                                        <th>Hora</th>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th>Cliente</th>
                                        <th>Precio V</th>
                                        <th>Cantidad</th>
                                        <th>Imp V</th>
                                        <th>Usuario</th>
                                    </tr>
                                </thead>';

                                $fecha2 = $fecha1 = $fecha;
                                $id_categoria = '0';
                                $sqlventas = mysqli_query($link, "SELECT a.id_venta,a.fecha_ingreso,a.hora_ingreso,b.codigo,b.precio_compra,b.precio_venta,b.cantidad,"
                                        . "round(b.precio_compra * b.cantidad,2) as importec, round(b.precio_venta * b.cantidad,2) as importev, a.id_cliente, b.id_consecutivo,b.estatus,b.id_usuario "
                                        . "FROM cc_det_ventas as a inner join cc_ventas as b on a.id_sucursal = b.id_sucursal and a.id_venta = b.id_venta "
                                        . "WHERE a.id_sucursal = '$id_sucursal' and a.fecha_ingreso between '$fecha1' and '$fecha2' and a.estatus in (1,3) and a.id_cierre in ($id_cierre) and b.id_usuario_act > 0 "
                                        . " order by a.id_venta DESC");

                                $renglon = $ganancia = 0;
                                while ($rowc = mysqli_fetch_assoc($sqlventas)) {
                                    $renglon = $renglon + 1;
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = 'ROL' and id_clave =" . $rowp['rol']));
                                    $producto = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_productos where id_sucursal = '$id_sucursal' and codigo =" . $rowc['codigo']));
                                    $cliente = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_clientes where id_sucursal = '$id_sucursal' and id_cliente =" . $rowc['id_cliente']));
                                    $usuario = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id =" . $rowc['id_usuario']));
                                    if ($id_categoria == '0' or ($producto['id_categoria'] == $id_categoria)) {
                                        if (empty($cliente)) {
                                            $nombre_cliente = '';
                                        } else {
                                            $nombre_cliente = $cliente['nombre'] . ' ' . $cliente['apellido_paterno'];
                                        }
                                        if ($rowc['estatus'] == 2) {
                                            $estatus = "C";
                                            $importeV = 0;
                                        } else {
                                            $estatus = "N";
                                            $importeV = $rowc['importev'];
                                        }

                                        echo '
                                        <tr id="fila' . $renglon . '">
                                            <td>' . $rowc['id_venta'] . '</td>
                                            <td>' . $rowc['fecha_ingreso'] . '</td>
                                            <td>' . $rowc['hora_ingreso'] . '</td>
                                            <td>' . $rowc['codigo'] . '</td>
                                            <td>' . $producto['descripcion'] . '</td>    
                                            <td>' . $nombre_cliente . '</td>
                                            <td>' . $rowc['precio_venta'] . '</td>
                                            <td>' . $rowc['cantidad'] . '</td>
                                            <td>' . $importeV . '</td>
                                            <td>' . $usuario['username'] . '</td>
                                        </tr>
                                        ';
                                    }
                                }
                                echo '    
                            </table> 
                            <br>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Gastos</h5>
                                </div>
                            </div>
                            <div class="col-12 d-flex justify-content-center" id=tabla_gastos' . $id_cierre . '>
                            <table id="gastos' . $id_cierre . '" class="display w-70 mx-auto" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Id</th>
                                        <th>Descripción</th>
                                        <th>Saldo</th>
                                    </tr>
                                </thead>';

                                $sqlgastos = mysqli_query($link,
                                        "SELECT
                                        a.id_gasto,
                                        a.descripcion,
                                        a.precio,
                                        a.cantidad
                                    FROM
                                        cc_gastos as a
                                    WHERE
                                        a.id_sucursal = $id_sucursal AND a.id_cierre = $id_cierre");
                                $totalg = 0;
                                while ($rowgas = mysqli_fetch_assoc($sqlgastos)) {
                                    echo '
                                    <tr id="">
                                        <td>' . $rowgas['id_gasto'] . '</td>
                                        <td>' . $rowgas['descripcion'] . '</td>
                                        <td>' . $rowgas['precio'] . '</td>
                                    </tr>
                                        ';
                                    $totalg = $totalg + $rowgas['precio'];
                                }
                                echo '
                                <tfoot>
                                    <tr>
                                        <th></th>
                                        <th id="total_gastos">Total</th>
                                        <th>' . number_format($totalg, 2) . '</th>
                                    </tr>
                                </tfoot>
                            </table>
                            </div>
                            <br>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Clientes con saldo</h5>
                                </div>
                            </div>
                            <div class="col-12 d-flex justify-content-center" id=tabla_clientes' . $id_cierre . '>
                            <table id="clientes' . $id_cierre . '" class="display w-70 mx-auto" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Id</th>
                                        <th>Cliente</th>
                                        <th>Saldo</th>
                                    </tr>
                                </thead>';

                                $sqlclientes = mysqli_query($link,
                                        "SELECT
                                        a.id_cliente, a.saldo, b.nombre,b.apellido_paterno
                                    FROM
                                        cc_cierre_clientes as a
                                        JOIN cc_clientes as b
                                    ON
                                        a.id_sucursal = b.id_sucursal and a.id_cliente = b.id_cliente
                                    WHERE
                                        a.id_sucursal = $id_sucursal and a.id_cierre = $id_cierre");

                                while ($rowcte = mysqli_fetch_assoc($sqlclientes)) {
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = 'ROL' and id_clave =" . $rowp['rol']));
                                    //$producto = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_productos where id_sucursal = '$id_sucursal' and codigo =" . $rowc['codigo']));
                                    //$cliente = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_clientes where id_sucursal = '$id_sucursal' and id_cliente =" . $rowc['id_cliente']));
                                    //$usuario = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id =" . $rowc['id_usuario']));


                                    $nombre_cliente = $rowcte['nombre'] . ' ' . $rowcte['apellido_paterno'];
                                    echo '
                                    <tr id="fila' . $renglon . '">
                                        <td>' . $rowcte['id_cliente'] . '</td>
                                        <td>' . $nombre_cliente . '</td>
                                        <td>' . $rowcte['saldo'] . '</td>
                                    </tr>
                                        ';
                                }
                                echo '
                            </table>
                            </div>
                            <br>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Resumen</h5>
                                </div>
                            </div>
                            <div class="col-12 d-flex justify-content-center" id=tabla_principal' . $id_cierre . '>
                            <table id="cierre' . $id_cierre . '" class="display table" style="width: 50%;" >
                                <thead>
                                    <tr>
                                        <th>Descripción</th>
                                        <th>Importe</th>
                                    </tr>
                                </thead>';
                                $totalgastoh = 0;
                                $sqlclaves = mysqli_query($link, "SELECT * FROM cc_claves where nombre_clave = 'CIERRE' order by orden");
                                while ($rowclaves = mysqli_fetch_assoc($sqlclaves)) {
                                    $clave = $rowclaves['clave'];
                                    $sqlcierre = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_cierre where id_sucursal = '$id_sucursal' and id_cierre= $id_cierre and clave = '$clave'"));
                                    //$sqluser = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id =" . $rowp['id_usuario']));
                                    //$sqlcategorias = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_categorias where id_sucursal = '$id_sucursal' and id_categoria =" . $rowp['id_categoria']));
                                    $importeC = 0;
                                    if (!isset($sqlcierre['importe'])) {
                                        $importeC = 0;
                                    } else {
                                        $importeC = $sqlcierre['importe'];
                                    }

                                    echo '
                                    <tr id="' . $rowclaves['clave'] . '">
                                        <td>' . $rowclaves['descripcion'] . '</td>
                                        <td >' . $importeC . '</td>
                                        ';

                                    switch ($rowclaves['clave']) {
                                        case 1:
                                            $totalgastoh = $totalgastoh + $importeC;
                                            break;
                                        case 2:
                                            $totalgastoh = $totalgastoh + $importeC;
                                            break;
                                        case 3:
                                            $totalgastoh = $totalgastoh - $importeC;
                                            break;
                                        case 5:
                                            $totalgastoh = $totalgastoh + $importeC;
                                            break;
                                        case 4:
                                            $totalgastoh = $totalgastoh - $importeC;
                                            break;
                                    }
                                }

                                echo '
                                    <tfoot>
                                    <tr>
                                        <th id="total_gasto">Dinero en caja</th>
                                        <th> ' . $totalgastoh . '</th>
                                    </tr>
                                </tfoot>
                            </table>
                            </div><br>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Spinner overlay -->
            <div id="spinnerOverlay" class="spinner-overlay hidden">
                <div class="text-center">
                    <div class="spinner-border text-light" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="text-light mt-2">Procesando petición...</p>
                </div>
            </div>


            <!-- div de impresión -->
            <div id="div_impresion" style="display:none">
                <style>
                    #tabla_principal_cierre, #tabla_principal_cierre table, #tabla_principal_cierre th, #tabla_principal_cierre td, #tabla_movimientos_cierre table, #tabla_movimientos_cierre th, #tabla_movimientos_cierre td,
                    #tabla_gastos_cierre, #tabla_gastos_cierre table, #tabla_gastos_cierre th, #tabla_gastos_cierre td,
                    #tabla_clientes_cierre, #tabla_clientes_cierre table, #tabla_clientes_cierre th, #tabla_clientes_cierre td
                    {
                        border: 1px solid;
                        border-collapse: collapse;
                        font-size: 10px;
                    }
                    #encabezado, #encabezado tr, #encabezado td {
                        border-collapse: collapse;
                        font-size: 13px ;
                        text-align: center;
                    }
                    h5 {
                        margin: 0;
                    }


                </style>
                <div style="text-align: center">
                    <h3><?php echo $_SESSION["desc_sucursal"]; ?></h3>
                </div>
                <div style="text-align: center">
                    <div id="header_info">
                        <table id="encabezado"  style="width:100%;">
                            <tr><td id="td_fecha">Fecha: 
                                    <?php
                                    echo $fecha;
                                    ?>
                                </td></tr>
                        </table>    
                    </div>
                    <div style="text-align: center">
                        <img src="../img/logo_1.jpeg" alt="MDN" width="100" height="100">
                    </div>
                </div>
                <br>
                <h5>Resumen<h5>
                <div id="tabla_movimientos_cierre">
                </div>
                <br>
                <h5>Gastos<h5>
                <div id="tabla_gastos_cierre">
                </div>
                <br>
                <h5>Clientes con saldo<h5>
                <div id="tabla_clientes_cierre">
                </div>
                <br>
                <h5>Resumen<h5>
                <div id="tabla_principal_cierre">
                </div>

            </div>    
        </main>
        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
                                        var g;
                                        $(document).ready(function () {
                                            $('#cierre0').dataTable(
                                                    {
                                                        paging: false,
                                                        ordering: false,
                                                        info: false,
                                                        searching: false,
                                                        columnDefs: [
                                                            {className: 'dt-center', targets: [1]}
                                                        ],
                                                    }
                                            );

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

<?php
if (isset($_POST['fecha_cierre'])) {
    echo 'imprimir(' . $id_cierre . ');';
    echo 'alert("Fin de cierre ejecutado");';
    echo 'respaldar_informacion();';
}
?>
                                        });
                                        $('#datepicker').datepicker({
                                            uiLibrary: 'bootstrap5',
                                            format: 'yyyy-mm-dd'
                                        });


                                        function cierre_caja() {
                                            if (confirm("¿Desea ejecutar el cierre?")) {
                                                {
                                                    guardarCierre();
                                                    $("#form_cierre").submit();
                                                }
                                            }
                                        }

                                        function respaldar_informacion() {
                                            if (confirm("¿Desea ejecutar la actualización?")) {
                                                mostrarSpinner();
                                                var parametros = {"fecha": <?php echo "'$fecha'" ?>};
                                                $.ajax({
                                                    url: "../functions/respaldo.php",
                                                    data: parametros,
                                                    dataType: "text",
                                                    type: "POST",
                                                    success: function (response) {
                                                        ocultarSpinner();
                                                        console.log(response);
                                                        const fecha = new Date().toLocaleTimeString();
                                                        mostrarResultado(response + ' a las ' + fecha);

                                                    },
                                                    error: function (response) {
                                                        alert('No se realizó la actualización');
                                                        console.log(response);
                                                    }
                                                });
                                            }

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
                                        function imprimir(consecutivo)
                                        {

                                            $('#tabla_movimientos_cierre').empty();
                                            $('#tabla_gastos_cierre').empty();
                                            $('#tabla_clientes_cierre').empty();
                                            $('#tabla_principal_cierre').empty();
                                            extrae_tabla(consecutivo);

                                            // Copiar contenido, reemplazar '50%' y pegarlo
                                            var miVariable = "tabla_principal" + consecutivo;
                                            var contenidoOriginal = $('#' + miVariable).html();
                                            var contenidoModificado = contenidoOriginal.replace(/50%/g, '100%');
                                            $('#tabla_principal_cierre').html(contenidoModificado);

                                            var miVariable = "tabla_gastos" + consecutivo;
                                            $('#tabla_gastos_cierre').html($('#' + miVariable).html());


                                            var miVariable = "tabla_clientes" + consecutivo;
                                            $('#tabla_clientes_cierre').html($('#' + miVariable).html());



                                            var contenidoOriginal = $('#' + miVariable).html();

                                            abrepaginaimpresion("div_impresion");
                                        }

                                        function extrae_tabla(consecutivo) {
                                            const columnasNecesarias = [0, 4, 6, 7, 8]; // Equivalen a las columnas 1,4,5,9 (contando desde 1)
                                            var miVariable = "ventas" + consecutivo;
                                            // 1. Crear nueva tabla
                                            let nuevaTabla = $('<table>');

                                            // 2. Copiar cabecera (si es necesario)
                                            let $headerRow = $('#' + miVariable + ' thead tr').clone();
                                            let $newHeaderRow = $('<tr>');

                                            // Filtrar columnas en la cabecera
                                            $headerRow.children().each(function (index) {
                                                if (columnasNecesarias.includes(index)) {
                                                    $newHeaderRow.append($(this).clone());
                                                }
                                            });
                                            nuevaTabla.append($('<thead>').append($newHeaderRow));

                                            // 3. Copiar filas del cuerpo (solo columnas seleccionadas)
                                            let $nuevoTbody = $('<tbody>');

                                            $('#' + miVariable + ' tbody tr').each(function () {
                                                let $nuevaFila = $('<tr>');
                                                $(this).find('td').each(function (index) {
                                                    if (columnasNecesarias.includes(index)) {
                                                        $nuevaFila.append($(this).clone());
                                                    }
                                                });
                                                $nuevoTbody.append($nuevaFila);
                                            });

                                            nuevaTabla.append($nuevoTbody);
                                            // 4. Pegar la nueva tabla en el div destino
                                            $('#tabla_movimientos_cierre').html(nuevaTabla);
                                            $('#tabla_movimientos_cierre table').attr('style', 'width:100%;');
                                        }

                                        function guardarCierre() {
                                            // Recoger datos de la tabla
                                            const datos = [];
                                            document.querySelectorAll('#cierre0 tr.read_only').forEach(tr => {
                                                const celdas = tr.querySelectorAll('td');
                                                datos.push({
                                                    id: tr.id,
                                                    descripcion: celdas[0].textContent,
                                                    importe: parseFloat(celdas[1].textContent) || 0
                                                });
                                            });

                                            // Enviar a PHP
                                            fetch('guardar_cierre.php', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                },
                                                body: JSON.stringify(datos)
                                            })
                                                    .then(response => response.json())
                                                    .then(data => {
                                                        alert(data.message);
                                                    });
                                        }
                                        // Función para mostrar resultado
                                        function mostrarResultado(texto, esExito = true) {
                                            resultadoTexto.textContent = texto;
                                            resultado.classList.remove('hidden');

                                            if (esExito) {
                                                resultado.classList.remove('alert-danger');
                                                resultado.classList.add('alert-success');
                                            } else {
                                                resultado.classList.remove('alert-success');
                                                resultado.classList.add('alert-danger');
                                        }
                                        }

                                        // Función para mostrar el spinner
                                        function mostrarSpinner() {
                                            spinnerOverlay.classList.remove('hidden');
                                        }

                                        // Función para ocultar el spinner
                                        function ocultarSpinner() {
                                            spinnerOverlay.classList.add('hidden');
                                        }


        </script> 
    </body>
</html>

