<?php
// Initialize the session
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}
require_once "../functions/config.php";
$id_sucursal = $_SESSION["id_sucursal"];
$err = "";
// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sucursal = trim($_POST["id_sucursal"]);
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('y-m-d');
    $hora_act = date('H:i:s');
    $update1 = mysqli_query($link, "UPDATE cc_users SET "
                    . "id_sucursal='$sucursal', "
                    . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                    . "WHERE id='$id_usuario_act'")
            or die(mysqli_error());

    if ($update1) {
        $_SESSION["id_sucursal"] = $sucursal;
        $rowsucursal = mysqli_fetch_assoc(mysqli_query($link, "SELECT desc_sucursal FROM cc_sucursales WHERE id_sucursal = $sucursal"));
        $_SESSION["desc_sucursal"] = $rowsucursal['desc_sucursal'];
        header("location: ../index.php");
    } else {
        header("location: ../login/login.php");
    }
    // Close statement
    mysqli_stmt_close($stmt);
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
        <link href="css/navbar.css" rel="stylesheet">


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
                                <h1 class="fs-4 card-title fw-bold mb-4">Cambia sucursal</h1>
                                <form method="POST" class="needs-validation" novalidate="" autocomplete="off">
                                    <div class="mb-3">
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
                                    <div class="d-flex align-items-center">
                                        <div class="form-check">
                                                <!--<input type="checkbox" name="remember" id="remember" class="form-check-input">
                                                <label for="remember" class="form-check-label">Remember Me</label>-->
                                        </div>
                                        <button type="submit" class="btn btn-primary ms-auto">
                                            Cambiar
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

    <script src="../js/bootstrap.bundle.min.js"></script>
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
