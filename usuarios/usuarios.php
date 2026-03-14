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


$id_sucursal = $id_usuario = $activo = $rol = 0;
$username = $nombre = $password = $password_r = $correo_electronico = "";

$fecha_ingreso = date('y-m-d');
$hora_ingreso = date('H:i:s');
$id_usuario = $_SESSION["id"];
$body = "";
$title = "";

if (isset($_POST['agregar'])) {
    $username = trim($_POST["username"]);
    $rowusername = mysqli_fetch_assoc(mysqli_query($link, "SELECT username as id FROM cc_users where username = '$username'"));
    if ($rowusername == null) {
        $rowusuario = mysqli_fetch_assoc(mysqli_query($link, "SELECT max(id) as id FROM cc_users"));
        $id = $rowusuario['id'];
        if ($id == null) {
            $id = 1;
        } else {
            $id = $id + 1;
        }



        $sql = "INSERT INTO cc_users (id, nombre, username, password, rol, correo_electronico, id_sucursal, activo, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "isssisiiiss", $id, $nombre, $username, $password, $rol, $correo_electronico, $id_sucursal, $activo, $id_usuario, $fecha_ingreso, $hora_ingreso);
            $nombre = mb_strtoupper(trim($_POST["nombre"]));
            $password = trim($_POST["password"]);
            $password = password_hash($password, PASSWORD_DEFAULT);
            $rol = trim($_POST["rol"]);
            $correo_electronico = trim($_POST["correo_electronico"]);
            $id_sucursal = trim($_POST["id_sucursal"]);
            $activo = isset($_POST['activo']) ? 1 : 0;
            $fecha_ingreso = date('y-m-d');
            $hora_ingreso = date('H:i:s');
            $id_usuario = $_SESSION["id"];
            if (mysqli_stmt_execute($stmt)) {
                $id_sucursal = $id_usuario = $rol = 0;
                $username = $nombre = $password = $password_r = $correo_electronico = "";
                $title = "Usuario agregado";
                $body = "El usuario ha sido agregado correctamente";
            } else {
                echo "Algo salió mal, por favor inténtalo de nuevo.";
            }
        }
    } else {
        $nombre = $_POST["nombre"];
        $password = $_POST["password"];
        $password_r = $_POST["password_r"];
        $rol = trim($_POST["rol"]);
        $correo_electronico = trim($_POST["correo_electronico"]);
        $id_sucursal = trim($_POST["id_sucursal"]);
        $activo = isset($_POST['activo']) ? 1 : 0;

        $title = "Alerta";
        $body = "El usuario ya existe en el sistema, favor de elegir otro";
    }
}
if (isset($_POST['editar'])) {
    $id = trim($_POST["id_usuario_e"]);
    $username = trim($_POST["username_e"]);
    $rowusername = mysqli_fetch_assoc(mysqli_query($link, "SELECT username FROM cc_users where id <> $id and username = '$username'"));
    if ($rowusername == null) {
        $nombre = mb_strtoupper(trim($_POST["nombre_e"]));
        $password = trim($_POST["password_e"]);
        $longitud = strlen($password);
        $password = password_hash($password, PASSWORD_DEFAULT);
        $rol = trim($_POST["rol_e"]);
        $correo_electronico = trim($_POST["correo_electronico_e"]);
        $id_sucursal = trim($_POST["id_sucursal_e"]);
        $activo = isset($_POST['activo_e']) ? 1 : 0;
        $id_usuario_act = $_SESSION["id"];
        $fecha_act = date('y-m-d');
        $hora_act = date('H:i:s');

        if ($longitud > 0) {
            $update1 = mysqli_query($link, "UPDATE cc_users SET "
                            . "username ='$username', nombre = '$nombre', password = '$password', rol = '$rol', correo_electronico = '$correo_electronico', "
                            . "id_sucursal = '$id_sucursal', activo = '$activo', fecha_act = '$fecha_act', hora_act = '$hora_act', id_usuario_act = '$id_usuario_act' "
                            . "WHERE id = '$id'")
                    or die(mysqli_error());
        } else {
            $update1 = mysqli_query($link, "UPDATE cc_users SET "
                            . "username ='$username', nombre = '$nombre', rol = '$rol', correo_electronico = '$correo_electronico', "
                            . "id_sucursal = '$id_sucursal', activo = '$activo', fecha_act = '$fecha_act', hora_act = '$hora_act', id_usuario_act = '$id_usuario_act' "
                            . "WHERE id = '$id'")
                    or die(mysqli_error());
        }
        if ($update1) {
            $title = "Usuario actualizado";
            $body = "El usuario fue actualizado correctamente";
        } else {
            $title = "No actualizado";
            $body = "El usuario no fue actualizado, favor de interntar nuevamente";
        }
    } else {
        $title = "No actualizado";
        $body = "El usuario ya existe en el sistema, favor de elegir otro";
    }
    $id_sucursal = $id_usuario = $rol = 0;
    $username = $nombre = $password = $password_r = $correo_electronico = "";
}

//Eliminar usuario
if (isset($_GET['accion']) == 'delete' and (!isset($_POST['agregar'])) and (!isset($_POST['editar']))) {
    $id = mysqli_real_escape_string($link, (strip_tags($_GET["id"], ENT_QUOTES)));
    $rowusuario = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users WHERE id = $id"));

    if ($rowusuario['fecha_acceso'] != '0000-00-00') {
        $body = 'Usuario ' . $rowusuario['nombre'] . ' no eliminado porque ya tiene accesos';
        $title = 'No eliminado';
    } else {
        $delete = mysqli_query($link, "DELETE FROM cc_users WHERE id = $id");
        if ($delete) {
            $body = 'Usuario ' . $rowusuario['nombre'] . ' eliminado correctamente';
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
        <link rel="shortcut icon" href="img/logo_1.png">
        <title>Usuarios</title>

        <script src="js/jquery-3.5.1.js"></script>
        <script src="js/jquery-ui.js"></script>
        <script src="js/jquery.dataTables.min.js"></script>
        <script src="js/sum().js"></script>
        <script src="js/jquery.jeditable.js" type="text/javascript"></script>
        <script type="text/javascript" src="js/jquery.dataTables.editable.js"></script>
        <script src="js/jquery.jeditable.js" type="text/javascript"></script>
        <script src="js/jquery.validate.js" type="text/javascript"></script>




        <style>
            @import "css/bootstrap.css";
        </style>


        <!-- Custom styles for this template -->
        <link href="css/navbar.css" rel="stylesheet">
        <link href="css/jquery.dataTables.min.css" rel="stylesheet">

    </head>
    <body>
        <main>
            <div class="container">
<?php require_once "../components/nav.php" ?>
                <div>
                    <div class="bg-light p-4 rounded ">
                        <div class="col-sm-8 mx-auto">
                            <h1 class="text-center">Usuarios</h1>
                        </div>
                        <br>
                        <br>
                        <form class="row g-3 needs-validation" action="#" method="post" novalidate id="form_usuario">
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="Nombre" class="form-label">Usuario</label>
                                    <div class="input-group has-validation">
                                        <input type="text" class="form-control" id="username" name="username" value="<?php echo $username ?>" placeholder="Username" autocomplete="off" required minlength="4" readonly>
                                        <div class="invalid-feedback">
                                            Ingresar username de al menos 4 caracteres
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label for="nombre" class="form-label">Nombre completo</label>
                                    <div class="input-group has-validation">
                                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo $nombre ?>" placeholder="Nombre completo" autocomplete="off" required minlength="4" oninput="this.value = this.value.toUpperCase()">
                                        <div class="invalid-feedback">
                                            Favor de ingresar apellido paterno
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="contrasena" class="form-label">Contraseña</label>
                                    <div class="input-group has-validation">
                                        <input type="password" class="form-control" id="password" name="password" value="<?php echo $password ?>" placeholder="Contraseña" autocomplete="off" required minlength="4">
                                        <div class="invalid-feedback">
                                            Ingresar contraseña
                                        </div>
                                    </div>
                                    <div class="alert p-1 mb-0" role="alert" id="capsLockAlert" style="display: none;width: 100%">
                                        ¡Mayúsculas está activado!
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label for="pass" class="form-label">Repetir contraseña</label>
                                    <div class="input-group has-validation">
                                        <input type="password" class="form-control" id="password_r" name="password_r" value="<?php echo $password_r ?>"placeholder="Repetir contraseña" autocomplete="off" required minlength="4">
                                        <div class="invalid-feedback" id="passwordError">
                                            Favor de escribir la misma contraseña
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="rol" class="form-label">Rol</label>
                                    <select id="rol" name="rol" class="form-select" required>
                                        <option selected disabled value="">Seleccione rol...</option>
<?php
//$query = $link -> query ("SELECT * FROM sbb_telas");
$query = mysqli_query($link, "SELECT * FROM cc_claves where nombre_clave = 'ROL'");
while ($valores = mysqli_fetch_array($query)) {
    if ($valores['clave'] == $rol) {
        echo '<option value="' . $valores['clave'] . '" selected="selected">' . $valores['descripcion'] . '</option>';
    } else {
        echo '<option value="' . $valores['clave'] . '">' . $valores['descripcion'] . '</option>';
    }
}
?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Favor de ingresar rol
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label for="correo" class="form-label">Correo electrónico</label>
                                    <div class="input-group has-validation">
                                        <input type="email" class="form-control" id="correo_electronico" name="correo_electronico" value="<?php echo $correo_electronico ?>" placeholder="Correo electrónico" autocomplete="off" required>
                                        <div class="invalid-feedback">
                                            Favor de ingresar correctamente el correo electrónico
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="mb-2 text-muted" for="usuario">Selecciona la sucursal</label>
                                    <select id="id_sucursal" name="id_sucursal" class="form-select" required>
                                        <option selected value="">Seleccione...</option>
<?php
//$query = $link -> query ("SELECT * FROM sbb_telas");
$query = mysqli_query($link, "SELECT * FROM cc_sucursales");
while ($sucursales = mysqli_fetch_array($query)) {
    if ($sucursales['id_sucursal'] == $id_sucursal) {
        echo '<option value="' . $sucursales['id_sucursal'] . '" selected="selected">' . $sucursales['desc_sucursal'] . '</option>';
    } else {
        echo '<option value="' . $sucursales['id_sucursal'] . '">' . $sucursales['desc_sucursal'] . '</option>';
    }
}
?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Sucursal es requerido
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label for="checkMayoreo" class="form-label">¿Activo?</label><br>
                                    <input class="form-check-input" name="activo" type="checkbox" value="1" id="activo" checked><br>
                                </div>
                            </div>
                            <div class="col-12 text-center" >
                                <input type="hidden" name="agregar" value="1">
                                <button type="submit" class="btn btn-primary" >Agregar</button>
                            </div>
                        </form>
                        <br>
                        <br>
                        <div class="table-responsive">
                            <table id="usuarios" class="display" style="width:100%" >
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Nombre</th>
                                        <th>Rol</th>
                                        <th>Sucursal</th>
                                        <th>Activo</th>
                                        <th>Usuario alta</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
<?php
$sqlusuarios = mysqli_query($link, "SELECT * FROM cc_users");
$renglon = 0;
while ($rowc = mysqli_fetch_assoc($sqlusuarios)) {
    $renglon = $renglon + 1;
    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = 'ROL' and id_clave =" . $rowp['rol']));
    $sqluser = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id =" . $rowc['id_usuario']));
    $sqlrol = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_claves where nombre_clave='ROL' and clave =" . $rowc['rol']));
    $sqlsucursal = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_sucursales where id_sucursal = " . $rowc['id_sucursal']));
    echo '
                                    <tr id="fila' . $renglon . '">
                                        <td>' . $rowc['id'] . '</td>
                                        <td class="' . $rowc['correo_electronico'] . '">' . $rowc['username'] . '</td>
                                        <td>' . $rowc['nombre'] . '</td>
                                        <td class="' . $rowc['rol'] . '">' . $sqlrol['descripcion'] . '</td>
                                        <td class="' . $rowc['id_sucursal'] . '">' . $sqlsucursal['desc_sucursal'] . '</td>
                                        <td class="' . $rowc['activo'] . '"><input type="checkbox" class="form-check-input" onclick="setValue(this)" ';
    if ($rowc['activo'] == "1") {
        echo 'checked';
    } echo ' disabled></td>
                                        <td>' . $sqluser['username'] . '</td>
                                        <td align="center">
                                            <a href="?accion=delete&id=' . $rowc['id'] . '" title="Eliminar" onclick="return confirm(\'¿Esta seguro de borrar el usuario ' . $rowc['nombre'] . '?\')"><img class="imga" src="img/icons/trash.svg"></a>
                                            <a href="#" title="Editar usuario" data-bs-toggle="modal" data-bs-target="#editModal"><img class="imga" src="img/icons/pencil-square.svg"></a>
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
                    <form class="needs-validation" action="#" method="post" novalidate id="form_edit">
                        <input type="hidden" name="id_usuario_e" id="id_usuario_e">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="Nombre" class="form-label">Usuario</label>
                                <div class="input-group has-validation">
                                    <input type="text" class="form-control" id="username_e" name="username_e" placeholder="Username" autocomplete="off" required minlength="4" readonly>
                                    <div class="invalid-feedback">
                                        Ingresar username de al menos 4 caracteres
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre completo</label>
                                <div class="input-group has-validation">
                                    <input type="text" class="form-control" id="nombre_e" name="nombre_e" placeholder="Nombre completo" autocomplete="off" required minlength="4" oninput="this.value = this.value.toUpperCase()">
                                    <div class="invalid-feedback">
                                        Favor de ingresar apellido paterno
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="contrasena" class="form-label">Contraseña</label>
                                <div class="input-group has-validation">
                                    <input type="password" class="form-control" id="password_e" name="password_e" value="" placeholder="Contraseña" autocomplete="off" minlength="4">
                                    <div class="invalid-feedback">
                                        Ingresar contraseña
                                    </div>
                                </div>
                                <div class="alert p-1 mb-0" role="alert" id="capsLockAlert_e" style="display: none;width: 100%">
                                    ¡Mayúsculas está activado!
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="pass" class="form-label">Repetir contraseña</label>
                                <div class="input-group has-validation">
                                    <input type="password" class="form-control" id="password_r_e" name="password_r_e" placeholder="Repetir contraseña" autocomplete="off" minlength="4">
                                    <div class="invalid-feedback" id="passwordError_e">
                                        Favor de escribir la misma contraseña
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="rol" class="form-label">Rol</label>
                                <select id="rol_e" name="rol_e" class="form-select" required>
                                    <option selected disabled value="">Seleccione rol...</option>
<?php
//$query = $link -> query ("SELECT * FROM sbb_telas");
$query = mysqli_query($link, "SELECT * FROM cc_claves where nombre_clave = 'ROL'");
while ($valores = mysqli_fetch_array($query)) {
    echo '<option value="' . $valores['clave'] . '">' . $valores['descripcion'] . '</option>';
}
?>
                                </select>
                                <div class="invalid-feedback">
                                    Favor de ingresar rol
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="correo" class="form-label">Correo electrónico</label>
                                <div class="input-group has-validation">
                                    <input type="email" class="form-control" id="correo_electronico_e" name="correo_electronico_e" placeholder="Correo electrónico" autocomplete="off" required>
                                    <div class="invalid-feedback">
                                        Favor de ingresar correctamente el correo electrónico
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="mb-2 text-muted" for="usuario">Selecciona la sucursal</label>
                                <select id="id_sucursal_e" name="id_sucursal_e" class="form-select" required>
                                    <option selected value="">Seleccione...</option>
<?php
//$query = $link -> query ("SELECT * FROM sbb_telas");
$query = mysqli_query($link, "SELECT * FROM cc_sucursales");
while ($sucursales = mysqli_fetch_array($query)) {
    echo '<option value="' . $sucursales['id_sucursal'] . '">' . $sucursales['desc_sucursal'] . '</option>';
}
?>
                                </select>
                                <div class="invalid-feedback">
                                    Sucursal es requerido
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="checkactivo" class="form-label">¿Activo?</label><br>
                                <input class="form-check-input" name="activo_e" type="checkbox" value="1" id="activo_e" checked><br>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <input type="hidden" name="editar" value="1">
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

        <script src="js/bootstrap.bundle.min.js"></script>
        <script>
            $(document).ready(function () {
                var t = $('#usuarios').DataTable(
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


                function quitarReadOnly() {
                    $(this).removeAttr("readonly");
                }

                // Asociar la función al evento focus de los campos de usuario y contraseña
                $("#username, #username_e").on("focus", quitarReadOnly);


            });


            var myForm_usuario = document.getElementById('form_usuario');
            var passwordInput = document.getElementById('password');
            var confirmPasswordInput = document.getElementById('password_r');
            var passwordError = document.getElementById('passwordError');
            var myForm_edit = document.getElementById('form_edit');
            var passwordInput_e = document.getElementById('password_e');
            var confirmPasswordInput_e = document.getElementById('password_r_e');
            var passwordError_e = document.getElementById('passwordError_e');

            myForm_usuario.addEventListener('submit', function (event) {
                event.preventDefault(); // Evitar que el formulario se envíe automáticamente
                //               
                // Validar el formulario
                if (myForm_usuario.checkValidity() === false) {
                    // Si hay errores, no hacer nada
                    event.stopPropagation();
                } else {
                    checkPasswordsMatch();
                    if (myForm_usuario.checkValidity() === true) {
                        myForm_usuario.submit();
                    }

                }
                // Agregar la clase "was-validated" para mostrar los errores
                myForm_usuario.classList.add('was-validated');
            });

            myForm_edit.addEventListener('submit', function (event) {
                event.preventDefault(); // Evitar que el formulario se envíe automáticamente
                //               
                // Validar el formulario
                if (myForm_edit.checkValidity() === false) {
                    // Si hay errores, no hacer nada
                    event.stopPropagation();
                } else {
                    checkPasswordsMatch();
                    if (myForm_edit.checkValidity() === true) {
                        myForm_edit.submit();
                    }
                }
                // Agregar la clase "was-validated" para mostrar los errores
                myForm_edit.classList.add('was-validated');
            });


            function checkPasswordsMatch() {
                if (passwordInput.value !== confirmPasswordInput.value) {
                    passwordError.style.display = 'block';
                    confirmPasswordInput.setCustomValidity('Las contraseñas no coinciden.');
                } else {
                    passwordError.style.display = 'none';
                    confirmPasswordInput.setCustomValidity('');
                }
            }
            passwordInput.addEventListener('input', checkPasswordsMatch);
            confirmPasswordInput.addEventListener('input', checkPasswordsMatch);

            function checkPasswordsMatch_e() {
                if (passwordInput_e.value !== confirmPasswordInput_e.value) {
                    passwordError_e.style.display = 'block';
                    confirmPasswordInput_e.setCustomValidity('Las contraseñas no coinciden.');
                } else {
                    passwordError_e.style.display = 'none';
                    confirmPasswordInput_e.setCustomValidity('');
                }
            }
            passwordInput_e.addEventListener('input', checkPasswordsMatch_e);
            confirmPasswordInput_e.addEventListener('input', checkPasswordsMatch_e);

            //Revisa si están bloqueadas las letras de mayúsculas para la contraseña
            document.getElementById("password").addEventListener("keydown", function (event) {
                var capsLockOn = event.getModifierState && event.getModifierState("CapsLock");
                if (capsLockOn) {
                    // Mostrar alguna notificación o indicación de que Caps Lock está activado
                    capsLockAlert.style.display = "block";
                } else {
                    // Caps Lock está apagado
                    capsLockAlert.style.display = "none";
                }
            });
            document.getElementById("password_e").addEventListener("keydown", function (event) {
                var capsLockOn = event.getModifierState && event.getModifierState("CapsLock");
                if (capsLockOn) {
                    capsLockAlert_e.style.display = "block";
                } else {
                    capsLockAlert_e.style.display = "none";
                }
            });
            document.getElementById("password").addEventListener("blur", function (event) {
                // Ocultar la alerta cuando el usuario sale del campo de texto
                capsLockAlert.style.display = "none";

            });
            document.getElementById("password_e").addEventListener("blur", function (event) {
                capsLockAlert_e.style.display = "none";
            });

            var editModal = document.getElementById('editModal')
            editModal.addEventListener('show.bs.modal', function (event) {
                // Botón que activó el modal
                var icono = event.relatedTarget
                // Extraer información de los atributos data-bs-*
                var id = $(icono).parents("tr").find("td").eq(0).text();
                var username = $(icono).parents("tr").find("td").eq(1).text();
                var nombre = $(icono).parents("tr").find("td").eq(2).text();
                var rol = $(icono).parents("tr").find("td").eq(3).attr('class');
                var sucursal = $(icono).parents("tr").find("td").eq(4).attr('class');
                var activo = $(icono).parents("tr").find("td").eq(6).attr('class');
                var correo_electronico = $(icono).parents("tr").find("td").eq(1).attr('class');
                $("#ModalLabelTitle").html("Editar usuario: " + id);
                $("#id_usuario_e").val(id);
                $("#username_e").val(username);
                $("#nombre_e").val(nombre);
                $("#rol_e").val(rol);
                $("#id_sucursal_e").val(sucursal);
                $("#correo_electronico_e").val(correo_electronico);
                if (activo == 0)
                    $("#activo_e").prop("checked", false);
                else
                    $("#activo_e").prop("checked", true);
            });
            function ventas_pagos(id_usuario)
            {
                window.location = "ventas_usuarios.php?id_usuario=" + id_usuario;
            }
        </script>      
    </body>
</html>

