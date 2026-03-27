<?php
// Initialize the session
session_start();
$redirectAfterLogin = "../main/inicio.php";

// Check if the user is already logged in, if yes then redirect him to welcome page
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: " . $redirectAfterLogin);
    exit;
}

require_once "../functions/config.php";

$err = "";
// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST["usuario"]);
    $contrasena = trim($_POST["contrasena"]);

    // Prepare a select statement
    $sql = "SELECT id, nombre, username, password, rol, id_sucursal FROM cc_users WHERE username = ?";

    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "s", $param_username);

        // Set parameters
        $param_username = $usuario;

        // Attempt to execute the prepared statement
        if (mysqli_stmt_execute($stmt)) {
            // Store result
            mysqli_stmt_store_result($stmt);

            // Check if username exists, if yes then verify password
            if (mysqli_stmt_num_rows($stmt) == 1) {
                // Bind result variables
                mysqli_stmt_bind_result($stmt, $id, $nombre, $username, $hashed_password, $rol, $sucursal);
                if (mysqli_stmt_fetch($stmt)) {
                    if (password_verify($contrasena, $hashed_password)) {
                        // Password is correct, so start a new session
                        if (!isset($_SESSION["loggedin"])) {
                            session_destroy();
                            session_start();
                        }
                        $sucursal = (int) $sucursal;
                        // Store data in session variables
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id"] = $id;
                        $_SESSION["nombre"] = $nombre;
                        $_SESSION["username"] = $username;
                        $_SESSION["rol"] = $rol;
                        $sqlsucursal = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_sucursales where id_sucursal = '$sucursal'"));
                        if ($sqlsucursal && !empty($sqlsucursal['id_sucursal'])) {
                            $_SESSION["id_sucursal"] = (int) $sqlsucursal['id_sucursal'];
                            $_SESSION["desc_sucursal"] = $sqlsucursal['desc_sucursal'];
                        } else {
                            $_SESSION["id_sucursal"] = 0;
                            $_SESSION["desc_sucursal"] = '';
                        }
                        date_default_timezone_set("America/Mexico_City");
                        $fecha_acceso = date('y-m-d');
                        $hora_acceso = date('H:i:s');
                        mysqli_query($link, "UPDATE cc_users SET fecha_acceso='$fecha_acceso', hora_acceso='$hora_acceso' WHERE id=$id");

                        if ((int) $_SESSION["id_sucursal"] > 0) {
                            header("location: " . $redirectAfterLogin);
                        } else {
                            header("location: ../login/cambio_sucursal.php");
                        }
                        exit;
                    } else {
                        // Display an error message if password is not valid
                        $err = "La contraseña que has ingresado no es válida.";
                    }
                }
            } else {
                // Display an error message if username doesn't exist
                $err = "No existe cuenta registrada con ese nombre de usuario.";
            }
        } else {
            echo "Algo salió mal, por favor vuelve a intentarlo.";
        }
    }

    // Close statement
    mysqli_stmt_close($stmt);

    login_end:

    // Close connection
    mysqli_close($link);
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

        <script src="https://code.jquery.com/jquery-3.5.1.js"></script>




        <style>

            @import "../css/bootstrap.css";
            .login-form {
                width: 340px;
                margin: 100px auto;
            }
        </style>

        <!-- Custom styles for this template -->
        <link href="../css/navbar.css" rel="stylesheet">


    </head>
    <body>
        <section class="h-100">
            <div class="container h-100">
                <div class="row justify-content-sm-center h-100">
                    <div class="col-xxl-4 col-xl-5 col-lg-5 col-md-7 col-sm-9">
                        <div class="text-center my-5">
                            <img src="../img/logo_1.jpeg" alt="logo" width="100">
                        </div>
                        <div class="card shadow-lg">
                            <div class="card-body p-5">
                                <h1 class="fs-4 card-title fw-bold mb-4">Acceso</h1>
                                <form method="POST" class="needs-validation" novalidate="" autocomplete="off">
                                    <div class="mb-3">
                                        <label class="mb-2 text-muted" for="usuario">Usuario</label>
                                        <input id="usuario" type="text" class="form-control" name="usuario" value="" required autofocus>
                                        <div class="invalid-feedback">
                                            Usuario es requerido
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="mb-2 w-100">
                                            <label class="text-muted" for="password">Contraseña</label>
                                            <a href="forgot.html" class="float-end">
                                                ¿Olvidaste tu contraseña?
                                            </a>
                                        </div>
                                        <input id="contrasena" type="password" class="form-control" name="contrasena" required>
                                        <div class="invalid-feedback">
                                            Contraseña es requerida
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center">
                                        <div class="form-check">
                                                <!--<input type="checkbox" name="remember" id="remember" class="form-check-input">
                                                <label for="remember" class="form-check-label">Remember Me</label>-->
                                        </div>
                                        <button type="submit" class="btn btn-primary ms-auto">
                                            Ingresar
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="card-footer py-3 border-0">
                                <div class="text-center">
                                    <!--Don't have an account? <a href="register.html" class="text-dark">Create One</a>-->
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-5 text-muted">
                            Copyright &copy; 2022-2023 &mdash; Carnicerías Cano
                        </div>
                    </div>
                </div>
            </div>

        </section>
        <!-- Modal -->
        <div class="modal fade" id="Modallogin" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ModalLabel">Modal title</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div id="modal-body" class="modal-body">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </body>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>

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
        }
        )()

        function alertModal(title, body) {
            $(function () {
                $('#ModalLabel').html(title);
                $('#modal-body').html(body);
                $('#Modallogin').modal('show');
            });
        }
<?php
if ($err != '')
    echo "alertModal('Atención', '$err')";
?>
    </script>
</html>
