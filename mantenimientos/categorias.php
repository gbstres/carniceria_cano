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
$desc_categoria = "";
$id_categoria = 0;
$mayoreo = 0;
$activo = 0;
$fecha_ingreso = date('y-m-d');
$hora_ingreso = date('H:i:s');
$id_usuario = $_SESSION["id"];
$body = "";
$title = "";

if (isset($_POST['agregar'])) {

    $rowcategoria = mysqli_fetch_assoc(mysqli_query($link, "SELECT max(id_categoria) as id_categoria FROM cc_categorias WHERE id_sucursal = '$id_sucursal'"));
    $id_categoria = $rowcategoria['id_categoria'];
    if ($id_categoria == null) {
        $id_categoria = 1;
    } else {
        $id_categoria = $id_categoria + 1;
    }


    $sql = "INSERT INTO cc_categorias (id_sucursal, id_categoria, desc_categoria, almacen, mayoreo, activo, id_usuario, fecha_ingreso,hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "iisdiiiss", $id_sucursal, $id_categoria, $desc_categoria, $almacen, $mayoreo, $activo, $id_usuario, $fecha_ingreso, $hora_ingreso);
        $desc_categoria = mb_strtoupper(trim($_POST["desc_categoria"]));
        $almacen_e = $almacen = trim($_POST["almacen"]);
        $mayoreo = isset($_POST['mayoreo']) ? 1 : 0;
        $activo = isset($_POST['activo']) ? 1 : 0;
        $fecha_ingreso = date('y-m-d');
        $hora_ingreso = date('H:i:s');
        $id_usuario = $_SESSION["id"];
        if (mysqli_stmt_execute($stmt)) {
            cc_sync_enqueue($link, $id_sucursal, 'categoria', 'upsert', [
                'id_categoria' => (int) $id_categoria,
            ], [
                'tabla' => 'cc_categorias',
            ]);
            $title = 'Agregado';
            $body = 'Categoría ' . $desc_categoria . ' agregada correctamente.';
        } else {
            $title = 'No Agregado';
            $body = 'Categoría ' . $desc_categoria . ' no se puede agregar.';
        }
    }
}
if (isset($_POST['editar'])) {
    $id_categoria = trim($_POST["id_categoria_e"]);
    $desc_categoria = mb_strtoupper(trim($_POST["desc_categoria_e"]));
    $almacen = trim($_POST["almacen_e"]);
    $mayoreo = isset($_POST['mayoreo_e']) ? 1 : 0;
    $activo = isset($_POST['activo_e']) ? 1 : 0;
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('y-m-d');
    $hora_act = date('H:i:s');
    $update1 = mysqli_query($link, "UPDATE cc_categorias SET "
                    . "desc_categoria='$desc_categoria', almacen='$almacen', mayoreo='$mayoreo', activo='$activo', "
                    . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                    . "WHERE id_categoria='$id_categoria' and id_sucursal='$id_sucursal'")
            or die(mysqli_error());
    if ($update1) {
        cc_sync_enqueue($link, $id_sucursal, 'categoria', 'upsert', [
            'id_categoria' => (int) $id_categoria,
        ], [
            'tabla' => 'cc_categorias',
        ]);
        $title = 'Actualizado';
        $body = 'Categoría ' . $desc_categoria . ' actualizada correctamente.';
    } else {
        $title = 'No actualizado';
        $body = 'Categoría ' . $desc_categoria . ' no actualizada.';
    }
}
if (isset($_GET['accion']) == 'delete' and $title == '') {
    // escaping, additionally removing everything that could be (html/javascript-) code
    $id_categoria = mysqli_real_escape_string($link, (strip_tags($_GET["id_categoria"], ENT_QUOTES)));
    $cek1 = mysqli_query($link, "SELECT * FROM cc_productos WHERE id_sucursal = '$id_sucursal' and id_categoria='$id_categoria'");
    $cek2 = mysqli_query($link, "SELECT * FROM cc_categorias WHERE id_sucursal = '$id_sucursal' and id_categoria='$id_categoria'");
    $sqlcategorias = mysqli_fetch_assoc($cek2);
    if (mysqli_num_rows($cek1) > 0) {
        $title = 'No Eliminado';
        $body = 'Categoría ' . $sqlcategorias['desc_categoria'] . ' no eliminada. Ya está asociado a productos';
    } else {

        $delete = mysqli_query($link, "DELETE FROM cc_categorias WHERE id_sucursal = '$id_sucursal' and id_categoria='$id_categoria'");
        if ($delete) {
            cc_sync_enqueue($link, $id_sucursal, 'categoria', 'delete', [
                'id_categoria' => (int) $id_categoria,
            ], [
                'tabla' => 'cc_categorias',
            ]);
            $title = 'Eliminado';
            $body = 'Categoría ' . $sqlcategorias['desc_categoria'] . ' eliminada correctamente.';
        } else {
            $title = 'No Eliminado';
            $body = 'Categoría ' . $sqlcategorias['desc_categoria'] . ' no eliminada.';
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
        <title>Categorías</title>

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
            input[type=number]::-webkit-inner-spin-button,
            input[type=number]::-webkit-outer-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }

            input[type=number] {
                -moz-appearance:textfield;
            }
            td .form-control{
                text-transform: uppercase;
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
                    <div class="bg-light p-4 rounded ">
                        <div class="col-sm-8 mx-auto">
                            <h1 class="text-center">Categorías</h1>
                        </div>
                        <br>
                        <br>
                        <form class="row g-3 needs-validation" action="#" method="post" novalidate>
                            <div class="row">
                                <div class="col-6">
                                    <label for="validationCustomUsername" class="form-label">Categoría:</label>
                                    <div class="input-group has-validation">
                                        <input type="text" class="form-control" id="descripcion" name="desc_categoria" placeholder="Descripción de la categoría" required>
                                        <div class="invalid-feedback">
                                            Favor de ingresar descripción.
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label for="almacen" class="form-label">En almacén</label>
                                    <div class="input-group has-validation">
                                        <input type="number" step="0.001" class="form-control" id="almacen" name="almacen" placeholder="kg en almacén" autocomplete="off" required>
                                        <div class="invalid-feedback">
                                            Ingresar stock.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <label for="checkMayoreo" class="form-label">&nbsp;</label><br>
                                    <input class="form-check-input" name="mayoreo" type="checkbox" value="0" id="mayoreo" >&nbsp;&nbsp;Mayoreo<br>
                                </div>
                                <div class="col-6">
                                    <label for="checkMayoreo" class="form-label">&nbsp;</label><br>
                                    <input class="form-check-input" name="activo" type="checkbox" value="1" id="mayoreo" checked>&nbsp;&nbsp;Activo<br>
                                </div>
                            </div>
                            <div class="col-12 text-center" >
                                <button type="submit" class="btn btn-primary" name="agregar">Agregar</button>
                            </div>
                        </form>
                        <br>
                        <br>
                        <div class="table-responsive">
                            <table id="categorias" class="display" style="width:100%" >
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th>Stock</th>
                                        <th>Mayoreo</th>
                                        <th>Activo</th>
                                        <th>Usuario</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <?php
                                $sqlcategorias = mysqli_query($link, "SELECT * FROM cc_categorias where id_sucursal = '$id_sucursal'");
                                $renglon = 0;
                                while ($rowc = mysqli_fetch_assoc($sqlcategorias)) {
                                    $renglon = $renglon + 1;
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = 'ROL' and id_clave =" . $rowp['rol']));
                                    $sqluser = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id =" . $rowc['id_usuario']));
                                    echo '
                                    <tr id="' . $rowc['id_categoria'] . '">
                                        <td>' . $rowc['id_categoria'] . '</td>
                                        <td>' . $rowc['desc_categoria'] . '</td>
                                        <td>' . $rowc['almacen'] . '</td>
                                        <td class="' . $rowc['mayoreo'] . '"><input type="checkbox" class="form-check-input" disabled ';
                                    if ($rowc['mayoreo'] == "1") {
                                        echo 'checked';
                                    } echo '></td>
                                        <td class="' . $rowc['activo'] . '"><input type="checkbox" class="form-check-input" disabled ';
                                    if ($rowc['activo'] == "1") {
                                        echo 'checked';
                                    } echo '></td>
                                        <td>' . $sqluser['username'] . '</td>
                                        <td align="center">
                                            <a href="?accion=delete&id_categoria=' . $rowc['id_categoria'] . '" title="Eliminar" onclick="return confirm(\'¿Esta seguro de borrar la categoría ' . $rowc['desc_categoria'] . '?\')"><img class="imga" src="../img/icons/trash.svg"></a>
                                            <a href="#" title="Editar categoría" data-bs-toggle="modal" data-bs-target="#editModal"><img class="imga" src="../img/icons/pencil-square.svg"></a>
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
                        <input type="hidden" name="id_categoria_e" id="id_categoria_e">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="validationCustomUsername" class="form-label">Categoría:</label>
                                <div class="input-group has-validation">
                                    <input type="text" class="form-control" id="desc_categoria_e" name="desc_categoria_e" placeholder="Descripción de la categoría" required>
                                    <div class="invalid-feedback">
                                        Favor de ingresar descripción.
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="almacen" class="form-label">En almacén</label>
                                <div class="input-group has-validation">
                                    <input type="number" step="0.001" class="form-control" id="almacen_e" name="almacen_e" placeholder="kg en almacén" autocomplete="off" required>
                                    <div class="invalid-feedback">
                                        Ingresar stock.
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="checkMayoreo" class="form-label">¿Mayoreo?</label><br>
                                <input class="form-check-input" name="mayoreo_e" type="checkbox" value="1" id="mayoreo_e" checked><br>
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
                var t = $('#categorias').dataTable(
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
                ).makeEditable({
                    sUpdateURL: "../functions/actualizacategorias.php",
                    aoColumns: [
                        null, null,
                        {
                            type: 'number',
                            indicator: 'Saving platforms...',
                            tooltip: 'Click para editar Stock',
                            cssclass: 'required',
                            sColumnName: 'almacen'
                        },
                        null, null, null
                    ]
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
            var editModal = document.getElementById('editModal')
            editModal.addEventListener('show.bs.modal', function (event) {
                // Botón que activó el modal
                var icono = event.relatedTarget
                // Extraer información de los atributos data-bs-*
                var id_categoria = $(icono).parents("tr").find("td").eq(0).text();
                var desc_categoria = $(icono).parents("tr").find("td").eq(1).text();
                var almacen = $(icono).parents("tr").find("td").eq(2).text();
                var mayoreo = $(icono).parents("tr").find("td").eq(3).attr('class');
                var activo = $(icono).parents("tr").find("td").eq(4).attr('class');
                $("#ModalLabelTitle").html("Editar categoría: " + id_categoria);
                $("#id_categoria_e").val(id_categoria);
                $("#desc_categoria_e").val(desc_categoria);
                $("#almacen_e").val(almacen);

                if (mayoreo == 0)
                    $("#mayoreo_e").prop("checked", false);
                else
                    $("#mayoreo_e").prop("checked", true);
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

