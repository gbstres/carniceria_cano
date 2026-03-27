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
require_once "../functions/sync_queue.php";
date_default_timezone_set("America/Mexico_City");
// Define variables and initialize with empty values

$id_sucursal = $_SESSION["id_sucursal"];
$id_proveedor = $credito = $activo = 0;
$nombre_proveedor = "";

$fecha_ingreso = date('y-m-d');
$hora_ingreso = date('H:i:s');
$id_usuario = $_SESSION["id"];
$body = "";
$title = "";

function asegurarSaldoProveedor(mysqli $link, int $idSucursal, int $idProveedor, int $idUsuario, string $fecha, string $hora): void
{
    $sql = "INSERT INTO cc_saldos_proveedores
                (id_sucursal, id_proveedor, efectivo_hoy, efectivo_ayer, efectivo_mes, id_usuario, fecha_ingreso, hora_ingreso)
            SELECT ?, ?, 0, 0, 0, ?, ?, ?
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1
                FROM cc_saldos_proveedores
                WHERE id_sucursal = ?
                  AND id_proveedor = ?
            )";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iiissii", $idSucursal, $idProveedor, $idUsuario, $fecha, $hora, $idSucursal, $idProveedor);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

if (isset($_POST['agregar'])) {

    $rowproveedor = mysqli_fetch_assoc(mysqli_query($link, "SELECT max(id_proveedor) as id_proveedor FROM `cc_proveedores` WHERE id_sucursal = '$id_sucursal'"));
    $id_proveedor = $rowproveedor['id_proveedor'];
    if ($id_proveedor == null) {
        $id_proveedor = 1;
    } else {
        $id_proveedor = $id_proveedor + 1;
    }

    $sql = "INSERT INTO cc_proveedores (id_sucursal, id_proveedor, nombre_proveedor, credito, clave_cliente, activo, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "iisdsiiss", $id_sucursal, $id_proveedor, $nombre_proveedor, $credito, $clave_cliente, $activo, $id_usuario, $fecha_ingreso, $hora_ingreso);
        $nombre_proveedor = mb_strtoupper(trim($_POST["nombre_proveedor"]));
        $credito = is_float(trim($_POST["credito"])) ? 0 : trim($_POST["credito"]);
        $activo = isset($_POST['activo']) ? 1 : 0;
        $fecha_ingreso = date('y-m-d');
        $hora_ingreso = date('H:i:s');
        $id_usuario = $_SESSION["id"];
        $clave_cliente = mb_strtoupper(trim($_POST["clave_cliente"]));
        $efectivo_hoy = $efectivo_ayer = $efectivo_mes = 0;
        $sql2 = "INSERT INTO cc_saldos_proveedores (id_sucursal, id_proveedor, efectivo_hoy, efectivo_ayer, efectivo_mes, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt2 = mysqli_prepare($link, $sql2);
        mysqli_stmt_bind_param($stmt2, "iidddiss", $id_sucursal, $id_proveedor, $efectivo_hoy, $efectivo_ayer, $efectivo_mes, $id_usuario, $fecha_ingreso, $hora_ingreso);
        $estatus = 0;
        $pagado = 0;
        if (mysqli_stmt_execute($stmt)) {
            asegurarSaldoProveedor($link, $id_sucursal, $id_proveedor, $id_usuario, $fecha_ingreso, $hora_ingreso);
            cc_sync_enqueue($link, $id_sucursal, 'proveedor', 'upsert', [
                'id_proveedor' => (int) $id_proveedor,
            ], [
                'tabla' => 'cc_proveedores',
                'clave_cliente' => (string) $clave_cliente,
            ]);
            $body = 'Proveedor ' . $nombre_proveedor . ' agregado correctamente.';
            $title = 'Agregado';
        } else {
            $body = 'Proveedor ' . $nombre_proveedor . ' eliminado correctamente.';
            $title = 'No agregado';
        }
    }
}
if (isset($_POST['editar'])) {
    $id_proveedor = trim($_POST["id_proveedor_e"]);
    $nombre_proveedor = mb_strtoupper(trim($_POST["nombre_proveedor_e"]));
    $credito = is_float(trim($_POST["credito_e"])) ? 0 : trim($_POST["credito_e"]);
    $clave_cliente = mb_strtoupper(trim($_POST["clave_cliente_e"]));
    $activo = isset($_POST['activo_e']) ? 1 : 0;
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('y-m-d');
    $hora_act = date('H:i:s');
    $update1 = mysqli_query($link, "UPDATE cc_proveedores SET "
                    . "nombre_proveedor = '$nombre_proveedor', credito = $credito, clave_cliente = '$clave_cliente', "
                    . "activo = '$activo', fecha_act = '$fecha_act', hora_act = '$hora_act', id_usuario_act = '$id_usuario_act' "
                    . "WHERE id_sucursal = '$id_sucursal' and id_proveedor = '$id_proveedor'")
            or die(mysqli_error());
    if ($update1) {
        asegurarSaldoProveedor($link, $id_sucursal, (int) $id_proveedor, (int) $id_usuario_act, $fecha_act, $hora_act);
        cc_sync_enqueue($link, $id_sucursal, 'proveedor', 'upsert', [
            'id_proveedor' => (int) $id_proveedor,
        ], [
            'tabla' => 'cc_proveedores',
            'clave_cliente' => (string) $clave_cliente,
        ]);
        $body = 'Proveedor ' . $nombre_proveedor . ' actualizado correctamente.';
        $title = 'Actualizado';
    } else {
        $body = 'Proveedor ' . $nombre_proveedor . ' no actualizado.';
        $title = 'No actualizado';
    }
}

//Eliminar proveedor
if (isset($_GET['accion']) == 'delete' and (!isset($_POST['agregar'])) and (!isset($_POST['editar']))) {
    $id_proveedor = mysqli_real_escape_string($link, (strip_tags($_GET["id"], ENT_QUOTES)));
    $compras = mysqli_query($link, "SELECT * FROM cc_det_compras WHERE id_sucursal = '$id_sucursal' and id_proveedor = $id_proveedor");
    $proveedores = mysqli_query($link, "SELECT * FROM cc_proveedores WHERE id_sucursal = '$id_sucursal' and id_proveedor = $id_proveedor");
    $sqlproveedores = mysqli_fetch_assoc($proveedores);
    if (mysqli_num_rows($compras) > 0) {
        $body = 'Proveedor ' . $sqlproveedores['nombre_proveedor'] . ' no eliminado porque existen asociaciones';
        $title = 'No eliminado';
    } else {
        $delete = mysqli_query($link, "DELETE FROM cc_proveedores WHERE id_sucursal = '$id_sucursal' and id_proveedor = $id_proveedor");
        if ($delete) {
            cc_sync_enqueue($link, $id_sucursal, 'proveedor', 'delete', [
                'id_proveedor' => (int) $id_proveedor,
            ], [
                'tabla' => 'cc_proveedores',
            ]);
            $body = 'Proveedor ' . $sqlproveedores['nombre_proveedor'] . ' eliminado correctamente';
            $title = 'Eliminado';
        }
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
        <title>Proveedores</title>

        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery-ui.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <script src="../js/sum().js"></script>
        <script src="../js/jquery.jeditable.js" type="text/javascript"></script>
        <script src="../js/jquery.dataTables.editable.js" type="text/javascript"></script>
        <script src="../js/jquery.jeditable.js" type="text/javascript"></script>
        <script src="../js/jquery.validate.js" type="text/javascript"></script>




        <style>
            @import "../css/bootstrap.css";
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
                    <div class="bg-light p-4 rounded ">
                        <div class="col-sm-8 mx-auto">
                            <h1 class="text-center">Proveedores</h1>
                        </div>
                        <br>
                        <br>
                        <form class="row g-3 needs-validation" action="#" method="post" novalidate>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="Nombre" class="form-label">Nombre</label>
                                    <div class="input-group has-validation">
                                        <input type="text" class="form-control" id="nombre_proveedor" name="nombre_proveedor" placeholder="Nombre" autocomplete="off" required>
                                        <div class="invalid-feedback">
                                            Favor de ingresar nombre_proveedor
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="LimiteCredito" class="form-label">Límite crédito</label>
                                    <div class="input-group has-validation">
                                        <input type="number" step="0.01" class="form-control" id="credito" name="credito" placeholder="Límite de crédito" autocomplete="off" required min="0">
                                        <div class="invalid-feedback">
                                            Favor de ingresar límite de crédito
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label for="LimiteCredito" class="form-label">Clave Cliente</label>
                                    <input type="text" class="form-control" id="clave_cliente" name="clave_cliente" placeholder="Clave externa del cliente" autocomplete="off">
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="checkMayoreo" class="form-label">¿Activo?</label><br>
                                    <input class="form-check-input" name="activo" type="checkbox" value="1" id="activo" checked><br>
                                </div>
                            </div>
                            <div class="col-12 text-center" >
                                <button type="submit" class="btn btn-primary" name="agregar">Agregar</button>
                            </div>
                        </form>
                        <br>
                        <br>
                        <div class="table-responsive">
                            <table id="proveedores" class="display" style="width:100%" >
                                <thead>
                                    <tr>
                                        <th>Id</th>
                                        <th>Nombre</th>
                                        <th>Crédito</th>
                                        <th>Saldo</th>
                                        <th>Activo</th>
                                        <th>Usuario</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <?php
                                $sqlproveedores = mysqli_query($link, "SELECT * FROM cc_proveedores where id_sucursal = '$id_sucursal'");
                                $renglon = 0;
                                while ($rowc = mysqli_fetch_assoc($sqlproveedores)) {
                                    $renglon = $renglon + 1;
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_proveedor_clave = 'ROL' and id_clave =" . $rowp['rol']));
                                    $sqluser = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id =" . $rowc['id_usuario']));
                                    $result = mysqli_query($link, "select efectivo_hoy from cc_saldos_proveedores where id_sucursal = $id_sucursal and id_proveedor =" . $rowc['id_proveedor']);
                                    $numero = mysqli_num_rows($result);
                                    if ($numero == 0) {
                                        $saldo = 0;
                                    } else {
                                        $saldos = mysqli_fetch_assoc($result);
                                        $saldo = $saldos['efectivo_hoy'];
                                    }
                                    echo '
                                    <tr id="fila' . $renglon . '">
                                        <td>' . $rowc['id_proveedor'] . '</td>
                                        <td class="' . $rowc['clave_cliente'] . '">' . $rowc['nombre_proveedor'] . '</td>
                                        <td>' . $rowc['credito'] . '</td>
                                        <td>' . $saldo . '</td>    
                                        <td class="' . $rowc['activo'] . '"><input type="checkbox" class="form-check-input" onclick="setValue(this)" ';
                                    if ($rowc['activo'] == "1") {
                                        echo 'checked';
                                    } echo ' disabled></td>
                                        <td>' . $sqluser['username'] . '</td>
                                        <td align="center">
                                            <a href="?accion=delete&id=' . $rowc['id_proveedor'] . '" title="Eliminar" onclick="return confirm(\'¿Esta seguro de borrar el proveedor ' . $rowc['nombre_proveedor'] . '?\')"><img class="imga" src="../img/icons/trash.svg"></a>
                                            <a href="#" title="Editar proveedor" data-bs-toggle="modal" data-bs-target="#editModal"><img class="imga" src="../img/icons/pencil-square.svg"></a>
                                            <a href="javascript:compras_pagos(' . $rowc['id_proveedor'] . ')" title="Revisar compras y pagos"><img class="imga" src="../img/icons/cash_icon.svg"></a>
                                        </td>
                                        </tr>
                                        ';
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

        <div class="modal fade modal-lg" id="editModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ModalLabelTitle"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form class="needs-validation" action="#" method="post" novalidate>
                        <input type="hidden" name="id_proveedor_e" id="id_proveedor_e">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="validationCustomUsername" class="form-label">Nombre:</label>
                                <div class="input-group has-validation">
                                    <input type="text" class="form-control" id="nombre_proveedor_e" name="nombre_proveedor_e" placeholder="Nombre" required>
                                    <div class="invalid-feedback">
                                        Favor de ingresar Nombre.
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="LimiteCredito" class="form-label">Límite crédito</label>
                                <div class="input-group has-validation">
                                    <input type="number" step="0.01" class="form-control" id="credito_e" name="credito_e" placeholder="Límite de crédito" autocomplete="off" required min="0">
                                    <div class="invalid-feedback">
                                        Favor de ingresar límite de crédito
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3"> 
                                <label for="cliente" class="form-label">Clave cliente</label>
                                <input type="text" class="form-control" id="clave_cliente_e" name="clave_cliente_e" placeholder="Clave externa del cliente" autocomplete="off">
                            </div>
                            <div class="mb-3">
                                <label for="checkMayoreo" class="form-label">¿Activo?</label><br>
                                <input class="form-check-input" name="activo_e" type="checkbox" value="1" id="activo_e" checked><br>
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

        <?php
        if ($title != "") {
            echo
            '<div class="modal fade" id="ModalRespuesta" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="staticBackdropLabel">' . $title . '</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">' . $body . '</div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>';
        }
        ?>

        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
            $(document).ready(function () {
                var t = $('#proveedores').DataTable(
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
                $('#ModalRespuesta').modal('show');
            });
            var editModal = document.getElementById('editModal')
            editModal.addEventListener('show.bs.modal', function (event) {
                // Botón que activó el modal
                var icono = event.relatedTarget
                // Extraer información de los atributos data-bs-*
                var id_proveedor = $(icono).parents("tr").find("td").eq(0).text();
                var nombre_proveedor = $(icono).parents("tr").find("td").eq(1).text();
                var credito = $(icono).parents("tr").find("td").eq(2).text();
                var activo = $(icono).parents("tr").find("td").eq(4).attr('class');
                var clave_cliente = $(icono).parents("tr").find("td").eq(1).attr('class');
                $("#ModalLabelTitle").html("Editar proveedor: " + id_proveedor);
                $("#id_proveedor_e").val(id_proveedor);
                $("#nombre_proveedor_e").val(nombre_proveedor);
                $("#credito_e").val(credito);
                $("#clave_cliente_e").val(clave_cliente);
                if (activo == 0)
                    $("#activo_e").prop("checked", false);
                else
                    $("#activo_e").prop("checked", true);
            });
            function compras_pagos(id_proveedor)
            {
                window.location = "../control/compras_proveedores.php?id_proveedor=" + id_proveedor;
            }


<?php
if ($title != "") {
    echo
    'var myModal = new bootstrap.Modal(document.getElementById("staticBackdrop"), {});
            document.onreadystatechange = function () {
            myModal.show();
            }; ';
}
?>
        </script>      
    </body>
</html>

