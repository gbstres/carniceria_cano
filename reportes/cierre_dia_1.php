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
    for ($i = 1; $i <= 5; $i++) {
        $sql = "INSERT INTO cc_cierre (id_sucursal, id_cierre, clave, importe, comentarios, estatus, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
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
    if ($exito == 'N') {
        echo '<script type="text/javascript">alert("No se pudo almacenar el cierre");</script>';
    } else {
        $estatus = 3;
        $update1 = mysqli_query($link, "UPDATE cc_det_ventas SET "
                        . "estatus='$estatus', id_cierre=$id_cierre, "
                        . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                        . "WHERE id_sucursal='$id_sucursal' and fecha_ingreso = '$fecha_ingreso' and estatus not in (3)")
                or die(mysqli_error());
        $update2 = mysqli_query($link, "UPDATE cc_gastos SET "
                        . "estatus='$estatus', id_cierre=$id_cierre, "
                        . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                        . "WHERE id_sucursal='$id_sucursal' and fecha_ingreso = '$fecha_ingreso' and estatus not in (3)")
                or die(mysqli_error());
        $update3 = mysqli_query($link, "UPDATE cc_entradas SET "
                        . "estatus='$estatus', id_cierre=$id_cierre, "
                        . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                        . "WHERE id_sucursal='$id_sucursal' and fecha_ingreso = '$fecha_ingreso' and estatus not in (3)")
                or die(mysqli_error());
        $update4 = mysqli_query($link, "UPDATE cc_pagos_clientes SET "
                        . "estatus='$estatus', id_cierre=$id_cierre, "
                        . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                        . "WHERE id_sucursal='$id_sucursal' and fecha_ingreso = '$fecha_ingreso' and estatus not in (3)")
                or die(mysqli_error());
    }
}

if (isset($_POST['fecha'])) {
    $fecha = $_POST['fecha'];
} else {
    $fecha = date('Y-m-d');
}

$estatus = $totalv = $totalc = $efectivo = $entrada_efectivo = $salida_efectivo = $a_cuenta = $a_cuenta_pagados = 0;

$total_venta = 0;
//SELECT a.fecha_ingreso,max(b.fecha_ingreso), SUM( CASE WHEN a.id_cliente = 0 THEN ROUND(b.cantidad * b.precio_venta, 2) END) AS total_venta, SUM( CASE WHEN a.id_cliente > 0 THEN ROUND(b.cantidad * b.precio_venta, 2) END) as total_clientes FROM cc_det_ventas as a LEFT JOIN cc_ventas as b ON a.id_sucursal = b.id_sucursal and a.id_venta = b.id_venta and b.estatus = 0 where a.id_sucursal = 1 and a.fecha_ingreso = '2023-06-24' group by a.fecha_ingreso;
$sqlventas = mysqli_query($link, "SELECT max(b.fecha_ingreso), SUM(ROUND(b.cantidad * b.precio_venta, 2)) as totalv, "
        . "SUM( CASE WHEN a.id_cliente = 0 THEN ROUND(b.cantidad * b.precio_venta, 2) END) AS total_venta, "
        . "SUM( CASE WHEN a.id_cliente > 0 THEN ROUND(b.cantidad * b.precio_venta, 2) END) as total_clientes "
        . "FROM cc_det_ventas as a "
        . "INNER JOIN cc_ventas as b "
        . "ON a.id_sucursal = b.id_sucursal and a.id_venta = b.id_venta and b.estatus = 0 "
        . "where a.id_sucursal = '$id_sucursal' and a.fecha_ingreso = '$fecha' and a.estatus = 1 and b.estatus = 0 group by a.fecha_ingreso;");
$row_venta = mysqli_fetch_assoc($sqlventas);
if (isset($row_venta['totalv']))
    $totalv = $row_venta['totalv'];

if (isset($row_venta['total_venta']))
    $efectivo = $row_venta['total_venta'];

if (isset($row_venta['total_clientes']))
    $a_cuenta = $row_venta['total_clientes'];


$sqlgastos = mysqli_query($link, "SELECT sum(ROUND(a.cantidad * a.precio,2)) AS total_gastos "
        . "FROM cc_gastos as a "
        . "WHERE a.id_sucursal = '$id_sucursal' and a.fecha_ingreso = '$fecha' and a.estatus = 0 GROUP by a.fecha_ingreso;");
$row_gastos = mysqli_fetch_assoc($sqlgastos);
if (isset($row_gastos['total_gastos']))
    $salida_efectivo = $row_gastos['total_gastos'];

$sqlentradas = mysqli_query($link, "SELECT sum(ROUND(a.cantidad * a.precio,2)) AS total_entradas "
        . "FROM cc_entradas as a "
        . "WHERE a.id_sucursal = '$id_sucursal' and a.fecha_ingreso = '$fecha' and a.estatus = 0 GROUP by a.fecha_ingreso;");
$row_entradas = mysqli_fetch_assoc($sqlentradas);
if (isset($row_entradas['total_entradas']))
    $entrada_efectivo = $row_entradas['total_entradas'];


$sqlpagos = mysqli_query($link, "SELECT sum(a.importe) total_pagos "
        . "FROM cc_pagos_clientes as a "
        . "WHERE a.id_sucursal = '$id_sucursal' and a.fecha_ingreso = '$fecha' and a.estatus = 0 GROUP by a.fecha_ingreso;");
$row_pagos = mysqli_fetch_assoc($sqlpagos);
if (isset($row_pagos['total_pagos']))
    $a_cuenta_pagados = $row_pagos['total_pagos'];

$totalc = $efectivo + $entrada_efectivo + $a_cuenta_pagados - $salida_efectivo;
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Carnicería Cano">
        <meta name="author" content="Gerardo Bautista">
        <link rel="shortcut icon" href="img/logo_1.png">
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
                        </form>
                        <br>
                        <div class="table-responsive">
                            <table id="cierre" class="display" style="width:100%" >
                                <thead>
                                    <tr>
                                        <th>Descripción</th>
                                        <th>Importe</th>
                                    </tr>
                                </thead>
                                <tr class="read_only" id="1">
                                    <td>EFECTIVO</td>
                                    <td class="read_only"><?php echo $efectivo ?></td>
                                </tr>
                                <tr id="2">
                                    <td>ENTRADA EFECTIVO</td>
                                    <td><?php echo $entrada_efectivo ?></td>
                                </tr>
                                <tr class="read_only" id="3">
                                    <td>SALIDA EFECTIVO</td>
                                    <td><?php echo $salida_efectivo ?></td>
                                </tr>
                                <tr class="read_only" id="4">
                                    <td>A CUENTA</td>
                                    <td><?php echo $a_cuenta ?></td>
                                </tr>
                                <tr class="read_only" id="5">
                                    <td>A CUENTA PAGADOS</td>
                                    <td><?php echo $a_cuenta_pagados ?></td>
                                </tr>
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
                            <input type="hidden" name="clave_1" id="clave_1" value="<?php echo $efectivo ?>">
                            <input type="hidden" name="clave_2" id="clave_2" value="<?php echo $entrada_efectivo ?>">
                            <input type="hidden" name="clave_3" id="clave_3" value="<?php echo $salida_efectivo ?>">
                            <input type="hidden" name="clave_4" id="clave_4" value="<?php echo $a_cuenta ?>">
                            <input type="hidden" name="clave_5" id="clave_5" value="<?php echo $a_cuenta_pagados ?>">

                            <div class="align-content-center text-center">
                                <a href="#" onclick="cierre_caja()" class="btn btn-primary m-1" role="button" id="cierra">Cerrar caja</a>
                            </div>
                        </form>
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
                                        . "WHERE a.id_sucursal = '$id_sucursal' and a.fecha_ingreso between '$fecha1' and '$fecha2' and a.estatus in (1,3) and a.id_cierre in (0) and b.id_usuario_act > 0 "
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
                        </div>




                        <br>
                        <br>
                        <div>
                            <?php
                            $sql = "SELECT id_cierre FROM cc_cierre where id_sucursal = '$id_sucursal' and fecha_ingreso = '$fecha' GROUP by id_cierre";
                            $sqlcierres = mysqli_query($link, $sql);
                            while ($rowcierres = mysqli_fetch_assoc($sqlcierres)) {
                                $id_cierre = $rowcierres['id_cierre'];
                                echo '
                            <div class="col-12 d-flex justify-content-center" >
                            <table id="cierre" class="display table" style="width: 50%;" >
                                <thead>
                                    <tr>
                                        <th colspan="2" style="text-align: center; text-height:15px; font-size: 20px">CIERRE ' . $id_cierre . '</th>
                                    </tr>

                                    <tr>
                                        <th>Descripción</th>
                                        <th>Importe</th>
                                    </tr>
                                </thead>';
                                $totalgastoh = 0;
                                $sqlclaves = mysqli_query($link, "SELECT * FROM cc_claves where nombre_clave = 'CIERRE' order by clave");
                                while ($rowclaves = mysqli_fetch_assoc($sqlclaves)) {
                                    $clave = $rowclaves['clave'];
                                    $sqlcierre = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_cierre where id_sucursal = '$id_sucursal' and id_cierre= $id_cierre and clave = $clave"));
                                    //$sqluser = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id =" . $rowp['id_usuario']));
                                    //$sqlcategorias = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_categorias where id_sucursal = '$id_sucursal' and id_categoria =" . $rowp['id_categoria']));
                                    echo '
                                    <tr id="' . $rowclaves['clave'] . '">
                                        <td>' . $rowclaves['descripcion'] . '</td>
                                        <td >' . $sqlcierre['importe'] . '</td>
                                        ';

                                    switch ($rowclaves['clave']) {
                                        case 1:
                                            $totalgastoh = $totalgastoh + $sqlcierre['importe'];
                                            break;
                                        case 2:
                                            $totalgastoh = $totalgastoh + $sqlcierre['importe'];
                                            break;
                                        case 3:
                                            $totalgastoh = $totalgastoh - $sqlcierre['importe'];
                                            break;
                                        case 5:
                                            $totalgastoh = $totalgastoh + $sqlcierre['importe'];
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

                                echo '
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
                                        <th>Precio V</th>
                                        <th>Cantidad</th>
                                        <th>Imp V</th>
                                        <th>Usuario</th>
                                    </tr>
                                </thead>';

                                $fecha1 = $fecha2 = date('Y-m-d');
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
                                echo'    
                            </table> 
                        </div><br><br>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
                                    var g;
                                    $(document).ready(function () {
                                        $('#cierre').dataTable(
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
    echo 'alert("Fin de cierre ejecutado");';
    echo 'abrepaginaimpresion("div_impresion");';
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
                                                $("#form_cierre").submit();
                                            }
                                        }
                                    }

                                    function respaldar_informacion() {
                                        if (confirm("¿Desea ejecutar la actualización?")) {
                                            var parametros = {"fecha": <?php echo "'$fecha'" ?>};
                                            $.ajax({
                                                url: "../functions/respaldo.php",
                                                data: parametros,
                                                dataType: "text",
                                                type: "POST",
                                                success: function (response) {
                                                    alert(response);
                                                    console.log(response);
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
        </script> 
    </body>
</html>

