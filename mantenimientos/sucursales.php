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
$desc_sucursal = "";
$activo = 0;
$fecha_ingreso = 0;
$hora_ingreso = 0;
$id_usuario = 0;
$body = "";
$title = "";

if (isset($_POST['agregar'])) {
    $sql = "INSERT INTO cc_sucursales (desc_sucursal, activo, id_usuario, fecha_ingreso,hora_ingreso) VALUES (?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "siiss", $desc_sucursal, $activo, $id_usuario, $fecha_ingreso, $hora_ingreso);
        
        $desc_sucursal = mb_strtoupper(trim($_POST["desc_sucursal"]));
        $activo = isset($_POST['activo']) ? 1 : 0;
        $fecha_ingreso = date('y-m-d');
        $hora_ingreso = date('H:i:s');
        $id_usuario = $_SESSION["id"];
        if (mysqli_stmt_execute($stmt)) {
            $title = 'Agregado';
            $body = 'Sucursal ' . $desc_sucursal . ' agregada correctamente.';
        } else {
            $title = 'No Agregado';
            $body = 'Sucursal ' . $desc_sucursal . ' no se puede agregar.';
        }
    }
}

if (isset($_POST['editar'])) {
    $id_sucursal = trim($_POST["id_sucursal_e"]);
    $desc_sucursal = mb_strtoupper(trim($_POST["desc_sucursal_e"]));
    $activo = isset($_POST['activo_e']) ? 1 : 0;
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('y-m-d');
    $hora_act = date('H:i:s');
    $update1 = mysqli_query($link, "UPDATE cc_sucursales SET "
                    . "desc_sucursal='$desc_sucursal', activo='$activo', "
                    . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                    . "WHERE id_sucursal='$id_sucursal'")
            or die(mysqli_error());
    if ($update1) {
        $title = 'Actualizado';
        $body = 'Sucursal ' . $desc_sucursal . ' actualizada correctamente.';
    } else {
        $title = 'No actualizado';
        $body = 'Sucursal ' . $desc_sucursal . ' no actualizada.';
    }
}

if (isset($_GET['accion']) == 'delete' and $title == '') {
    // escaping, additionally removing everything that could be (html/javascript-) code
    $id_sucursal = mysqli_real_escape_string($link, (strip_tags($_GET["id_sucursal"], ENT_QUOTES)));
    $cek1 = mysqli_query($link, "SELECT * FROM cc_categorias WHERE id_sucursal = '$id_sucursal'");
    $cek2 = mysqli_query($link, "SELECT * FROM cc_sucursales WHERE id_sucursal = '$id_sucursal'");
    $sqlsucursales = mysqli_fetch_assoc($cek2);
    if (mysqli_num_rows($cek1) > 0) {
        $title = 'No Eliminado';
        $body = 'Sucursal ' . $sqlsucursales['desc_sucursal'] . ' no eliminada. Ya está asociado a categorías';
    } else {
        $delete = mysqli_query($link, "DELETE FROM cc_sucursales WHERE id_sucursal = '$id_sucursal'");
        if ($delete) {
            $title = 'Eliminado';
            $body = 'Categoría ' . $sqlsucursales['desc_sucursal'] . ' eliminada correctamente.';
        } else {
            $title = 'No Eliminado';
            $body = 'Categoría ' . $sqlsucursales['desc_sucursal'] . ' no eliminada.';
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
        <link rel="shortcut icon" href="img/logo_1.png">
        <title>Sucursales</title>

        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <script src="../js/sum().js"></script>



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
                            <h1 class="text-center">Sucursales</h1>
                        </div>
                        <br>
                        <br>
                        <form class="row g-3 needs-validation" action="#" method="post" novalidate>
                            <div class="row">
                                <div class="col-8">
                                    <label for="validationCustomUsername" class="form-label">Sucursal:</label>
                                    <div class="input-group has-validation">
                                        <input type="text" class="form-control" id="descripcion" name="desc_sucursal" placeholder="Descripción de la sucursal" required>
                                        <div class="invalid-feedback">
                                            Favor de ingresar descripción.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <label for="checkActivo" class="form-label">&nbsp;</label><br>
                                    <input class="form-check-input" name="activo" type="checkbox" value="1" id="activo" checked>&nbsp;&nbsp;Activo<br>
                                </div>
                            </div>
                            <div class="col-12 text-center" >
                                <button type="submit" class="btn btn-primary" name="agregar">Agregar</button>
                            </div>
                        </form>
                        <br>
                        <br>
                        <div class="table-responsive">
                            <table id="sucursales" class="display" style="width:100%" >
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th>Activo</th>
                                        <th>Usuario</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <?php
                                $sqlsucursales = mysqli_query($link, "SELECT * FROM cc_sucursales");
                                $renglon = 0;
                                while ($rowc = mysqli_fetch_assoc($sqlsucursales)) {
                                    $renglon = $renglon + 1;
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = 'ROL' and id_clave =" . $rowp['rol']));
                                    $sqluser = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id =" . $rowc['id_usuario']));
                                    echo '
                                    <tr id="fila' . $renglon . '">
                                        <td>' . $rowc['id_sucursal'] . '</td>
                                        <td>' . $rowc['desc_sucursal'] . '</td>
                                        <td><input type="checkbox" class="form-check-input" disabled ';
                                    if ($rowc['activo'] == "1") {
                                        echo 'checked';
                                    } echo '></td>
                                        <td>' . $sqluser['username'] . '</td>
                                        <td align="center">
                                            <a href="?accion=delete&id_sucursal=' . $rowc['id_sucursal'] . '" title="Eliminar" onclick="return confirm(\'¿Esta seguro de borrar la sucursal ' . $rowc['desc_sucursal'] . '?\')"><img class="imga" src="../img/icons/trash.svg"></a>
                                            <a href="#" title="Editar sucursal" data-bs-toggle="modal" data-bs-target="#editModal"><img class="imga" src="../img/icons/pencil-square.svg"></a>
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
                        <input type="hidden" name="id_sucursal_e" id="id_sucursal_e">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="validationCustomUsername" class="form-label">Sucursal:</label>
                                <div class="input-group has-validation">
                                    <input type="text" class="form-control" id="desc_sucursal_e" name="desc_sucursal_e" placeholder="Descripción de la sucursal" required>
                                    <div class="invalid-feedback">
                                        Favor de ingresar descripción.
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="checkActivo" class="form-label">¿Activo?</label><br>
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
            '<div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
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
                var t = $('#sucursales').DataTable(
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


            });
            var editModal = document.getElementById('editModal')
            editModal.addEventListener('show.bs.modal', function (event) {
                // Botón que activó el modal
                var icono = event.relatedTarget
                // Extraer información de los atributos data-bs-*
                var id_sucursal = $(icono).parents("tr").find("td").eq(0).text();
                var desc_sucursal = $(icono).parents("tr").find("td").eq(1).text();
                var activo = $(icono).parents("tr").find("td").eq(2).attr('class');
                $("#ModalLabelTitle").html("Editar sucursal: " + id_sucursal);
                $("#id_sucursal_e").val(id_sucursal);
                $("#desc_sucursal_e").val(desc_sucursal);
                if (activo == 0)
                    $("#activo_e").prop("checked", false);
                else
                    $("#activo_e").prop("checked", true);
            })
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

