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
$id_empleado = $activo = 0;
$nombre = $apellido_paterno = $apellido_materno = "";

$fecha_ingreso = date('y-m-d');
$hora_ingreso = date('H:i:s');
$id_usuario = $_SESSION["id"];
$body = "";
$title = "";

if (isset($_POST['agregar'])) {

    $rowempleado = mysqli_fetch_assoc(mysqli_query($link, "SELECT max(id_empleado) as id_empleado FROM `cc_empleados` WHERE id_sucursal = '$id_sucursal'"));
    $id_empleado = $rowempleado['id_empleado'];
    if ($id_empleado == null) {
        $id_empleado = 1;
    } else {
        $id_empleado = $id_empleado + 1;
    }

    $sql = "INSERT INTO cc_empleados (id_sucursal, id_empleado, nombre, apellido_paterno, apellido_materno, activo, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "iisssiiss", $id_sucursal, $id_empleado, $nombre, $apellido_paterno, $apellido_materno, $activo, $id_usuario, $fecha_ingreso, $hora_ingreso);
        $nombre = mb_strtoupper(trim($_POST["nombre"]));
        $apellido_paterno = mb_strtoupper(trim($_POST["apellido_paterno"]));
        $apellido_materno = mb_strtoupper(trim($_POST["apellido_materno"]));
        $activo = isset($_POST['activo']) ? 1 : 0;
        $fecha_ingreso = date('y-m-d');
        $hora_ingreso = date('H:i:s');
        $id_usuario = $_SESSION["id"];
        if (mysqli_stmt_execute($stmt)) {
        } else {
            echo "Algo salió mal, por favor inténtalo de nuevo.";
        }
    }
}
if (isset($_POST['editar'])) {
    $id_empleado = trim($_POST["id_empleado_e"]);
    $nombre = mb_strtoupper(trim($_POST["nombre_e"]));
    $apellido_paterno = mb_strtoupper(trim($_POST["apellido_paterno_e"]));
    $apellido_materno = mb_strtoupper(trim($_POST["apellido_materno_e"]));
    $activo = isset($_POST['activo_e']) ? 1 : 0;
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('y-m-d');
    $hora_act = date('H:i:s');
    $update1 = mysqli_query($link, "UPDATE cc_empleados SET "
                    . "nombre = '$nombre', apellido_paterno = '$apellido_paterno', apellido_materno = '$apellido_materno', "
                    . "activo = '$activo', fecha_act = '$fecha_act', hora_act = '$hora_act', id_usuario_act = '$id_usuario_act' "
                    . "WHERE id_sucursal = '$id_sucursal' and id_empleado = '$id_empleado'")
            or die(mysqli_error());
    if ($update1) {

    } else {
        echo '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>Error, no se pudo guardar el producto.</div>';
    }
}

//Eliminar empleado
if (isset($_GET['accion']) == 'delete' and (!isset($_POST['agregar'])) and (!isset($_POST['editar']))) {
    $id_empleado = mysqli_real_escape_string($link, (strip_tags($_GET["id"], ENT_QUOTES)));
    $ventas = mysqli_query($link, "SELECT * FROM cc_det_ventas WHERE id_sucursal = '$id_sucursal' and id_empleado = $id_empleado");
    $empleados = mysqli_query($link, "SELECT * FROM cc_empleados WHERE id_sucursal = '$id_sucursal' and id_empleado = $id_empleado");
    $sqlempleados = mysqli_fetch_assoc($empleados);
    if (mysqli_num_rows($ventas) > 0) {
        $body = 'Cliente ' . $sqlempleados['nombre'] . ' ' . $sqlempleados['apellido_paterno'] . ' no eliminado porque existen asociaciones';
        $title = 'No eliminado';
    } else {
        $delete = mysqli_query($link, "DELETE FROM cc_empleados WHERE id_sucursal = '$id_sucursal' and id_empleado = $id_empleado");
        if ($delete) {
            $body = 'Cliente ' . $sqlempleados['nombre'] . ' ' . $sqlempleados['apellido_paterno'] . ' eliminado correctamente';
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
        <title>Empleados</title>

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
                            <h1 class="text-center">Empleados</h1>
                        </div>
                        <br>
                        <br>
                        <form class="row g-3 needs-validation" action="#" method="post" novalidate>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="Nombre" class="form-label">Nombre</label>
                                    <div class="input-group has-validation">
                                        <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Nombre" autocomplete="off" required>
                                        <div class="invalid-feedback">
                                            Favor de ingresar nombre
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label for="ApellidoPaterno" class="form-label">Apellido Paterno</label>
                                    <div class="input-group has-validation">
                                        <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" placeholder="Apellido paterno" autocomplete="off" required>
                                        <div class="invalid-feedback">
                                            Favor de ingresar apellido paterno
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="ApellidoMaterno" class="form-label">Apellido Materno</label>
                                    <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" placeholder="Apellido Materno" autocomplete="off">
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
                            <table id="empleados" class="display" style="width:100%" >
                                <thead>
                                    <tr>
                                        <th>Id</th>
                                        <th>Nombre</th>
                                        <th>A. Paterno</th>
                                        <th>A. Materno</th>
                                        <th>Activo</th>
                                        <th>Usuario</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <?php
                                $sqlempleados = mysqli_query($link, "SELECT * FROM cc_empleados where id_sucursal = '$id_sucursal'");
                                $renglon = 0;
                                while ($rowc = mysqli_fetch_assoc($sqlempleados)) {
                                    $renglon = $renglon + 1;
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = 'ROL' and id_clave =" . $rowp['rol']));
                                    $sqluser = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id =" . $rowc['id_usuario']));
                                    echo '
                                    <tr id="fila' . $renglon . '">
                                        <td>' . $rowc['id_empleado'] . '</td>
                                        <td>' . $rowc['nombre'] . '</td>
                                        <td>' . $rowc['apellido_paterno'] . '</td>
                                        <td>' . $rowc['apellido_materno'] . '</td>
                                        <td class="' . $rowc['activo'] . '"><input type="checkbox" class="form-check-input" onclick="setValue(this)" ';
                                    if ($rowc['activo'] == "1") {
                                        echo 'checked';
                                    } echo ' disabled></td>
                                        <td>' . $sqluser['username'] . '</td>
                                        <td align="center">
                                            <a href="?accion=delete&id=' . $rowc['id_empleado'] . '" title="Eliminar" onclick="return confirm(\'¿Esta seguro de borrar el empleado ' . $rowc['nombre'] . ' ' . $rowc['apellido_paterno'] . '?\')"><img class="imga" src="../img/icons/trash.svg"></a>
                                            <a href="#" title="Editar empleado" data-bs-toggle="modal" data-bs-target="#editModal"><img class="imga" src="../img/icons/pencil-square.svg"></a>
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
                        <input type="hidden" name="id_empleado_e" id="id_empleado_e">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="validationCustomUsername" class="form-label">Nombre:</label>
                                <div class="input-group has-validation">
                                    <input type="text" class="form-control" id="nombre_e" name="nombre_e" placeholder="Nombre" required>
                                    <div class="invalid-feedback">
                                        Favor de ingresar Nombre.
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="ApellidoPaterno" class="form-label">Apellido Paterno</label>
                                <div class="input-group has-validation">
                                    <input type="text" class="form-control" id="apellido_paterno_e" name="apellido_paterno_e" placeholder="Apellido paterno" autocomplete="off" required>
                                    <div class="invalid-feedback">
                                        Favor de ingresar apellido paterno
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="ApellidoMaterno" class="form-label">Apellido Materno</label>
                                <input type="text" class="form-control" id="apellido_materno_e" name="apellido_materno_e" placeholder="Apellido Materno" autocomplete="off">
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
                var t = $('#empleados').DataTable(
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
                var id_empleado = $(icono).parents("tr").find("td").eq(0).text();
                var nombre = $(icono).parents("tr").find("td").eq(1).text();
                var apellido_paterno = $(icono).parents("tr").find("td").eq(2).text();
                var apellido_materno = $(icono).parents("tr").find("td").eq(3).text();
                var activo = $(icono).parents("tr").find("td").eq(6).attr('class');
                $("#ModalLabelTitle").html("Editar empleado: " + id_empleado);
                $("#id_empleado_e").val(id_empleado);
                $("#nombre_e").val(nombre);
                $("#apellido_paterno_e").val(apellido_paterno);
                $("#apellido_materno_e").val(apellido_materno);
                if (activo == 0)
                    $("#activo_e").prop("checked", false);
                else
                    $("#activo_e").prop("checked", true);
            });
            function ventas_pagos(id_empleado)
            {
                window.location = "ventas_empleados.php?id_empleado=" + id_empleado;
            }
        </script>      
    </body>
</html>

