<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";
require_once "../functions/config_2.php";
date_default_timezone_set("America/Mexico_City");
// Define variables and initialize with empty values
$id_sucursal = $_SESSION["id_sucursal"];

if (isset($_POST['fecha'])) {
    
}


if (isset($_POST['fecha'])) {
    $fecha = $_POST['fecha'];
} else {
    $fecha = date('Y-m-d');
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // valida fecha
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        echo "Fecha inválida.";
        exit;
    }

    if (!isset($_POST['check']) || !is_array($_POST['check'])) {
        echo "No se marcó ningún checkbox.";
        exit;
    }

    // Transacción en LOCAL (porque estás escribiendo en local)
    mysqli_begin_transaction($link);
    try {

        // opcional
        mysqli_query($link, "SET FOREIGN_KEY_CHECKS=0");

        foreach ($_POST['check'] as $id => $value) {

            $id = (int)$id;
            if ((int)$value !== 1) continue;

            // Obtén tabla de manera segura
            $st = mysqli_prepare($link, "SELECT nombre_tabla FROM cc_tablas_respaldo WHERE id = ?");
            mysqli_stmt_bind_param($st, "i", $id);
            mysqli_stmt_execute($st);
            $res = mysqli_stmt_get_result($st);
            $sqltable = mysqli_fetch_assoc($res);
            mysqli_stmt_close($st);

            if (!$sqltable) continue;

            $tabla = $sqltable['nombre_tabla'];

            // valida nombre de tabla
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabla)) continue;

            // columnas y PK
            $columnas = [];
            $llaves = [];

            $rsDesc = mysqli_query($link, "SHOW COLUMNS FROM `$tabla`");
            if (!$rsDesc) continue;

            while ($r = mysqli_fetch_assoc($rsDesc)) {
                $columnas[] = $r['Field'];
                if ($r['Key'] === 'PRI') $llaves[] = $r['Field'];
            }
            mysqli_free_result($rsDesc);

            if (empty($columnas) || empty($llaves)) {
                // sin PK no conviene sincronizar así
                continue;
            }

            // WHERE correcto (paréntesis)
            $fechaEsc = mysqli_real_escape_string($link2, $fecha);
            $idSuc = (int)$id_sucursal;

            // Si tu tabla NO tiene id_sucursal, este WHERE fallará.
            // (Si algunas no lo tienen, hay que detectarlo con hasColumn.)
            $cad1 = "SELECT * FROM `$tabla`
                     WHERE id_sucursal = $idSuc
                       AND (fecha_ingreso = '$fechaEsc' OR fecha_act = '$fechaEsc')";

            $sqltabla = mysqli_query($link2, $cad1);
            if (!$sqltabla) continue;

            // UPSERT armado
            $colList = implode(",", array_map(fn($c) => "`$c`", $columnas));
            $updateList = implode(",", array_map(fn($c) => "`$c`=VALUES(`$c`)", $columnas));

            while ($rows = mysqli_fetch_assoc($sqltabla)) {

                $vals = [];
                foreach ($columnas as $c) {
                    $v = $rows[$c];
                    if ($v === null) {
                        $vals[] = "NULL";
                    } else {
                        $vals[] = "'" . mysqli_real_escape_string($link, (string)$v) . "'";
                    }
                }

                $sqlUp = "INSERT INTO `$tabla` ($colList)
                          VALUES (" . implode(",", $vals) . ")
                          ON DUPLICATE KEY UPDATE $updateList";

                if (!mysqli_query($link, $sqlUp)) {
                    throw new Exception("Error insert/upsert en $tabla: " . mysqli_error($link));
                }
            }

            mysqli_free_result($sqltabla);
        }

        mysqli_query($link, "SET FOREIGN_KEY_CHECKS=1");
        mysqli_commit($link);

        echo "Recuperación completada correctamente.";

    } catch (Throwable $e) {
        mysqli_rollback($link);
        echo "ERROR: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
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
        <title>Actualiza info</title>

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
        <link href="../css/gijgo.min.css" rel="stylesheet" type="text/css" />

    </head>
    <body>
        <main>
            <div class="container">
                <?php require_once "../components/nav.php" ?>
                <div>
                    <div class="bg-light p-4 rounded ">
                        <div class="col-sm-8 mx-auto">
                            <h1 class="text-center">Actualiza información del servidor</h1>
                        </div>
                        <form class="row g-3 needs-validation" action="#" method="post" novalidate>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="Fecha" class="form-label">Seleccione la fecha:</label>
                                    <input name="fecha" id="datepicker" width="276" autocomplete="off" readonly="" value="<?php echo $fecha ?>"/>
                                </div>
                            </div>
                            <div class="col-12 text-center" >
                                <input class="btn btn-primary black bg-silver" type="submit" value="Extrae información" id="extrae">
                            </div>
                            <br>
                            <div class="table-responsive">
                                <table id="tablas" class="display" style="width:50%" >
                                    <thead>
                                        <tr>
                                            <th>Id</th>
                                            <th>Tabla</th>
                                            <th>Seleccionar</th>
                                        </tr>
                                    </thead>
                                    <?php
                                    $sqltablas = mysqli_query($link, "SELECT id,nombre_comun "
                                            . "FROM cc_tablas_respaldo as a "
                                            . " order by a.secuencia");

                                    $renglon = 0;
                                    while ($rowc = mysqli_fetch_assoc($sqltablas)) {
                                        $renglon = $renglon + 1;
                                        echo '
                                    <tr id="' . $rowc['id'] . '">
                                        <td>' . $rowc['id'] . '</td>
                                        <td>' . $rowc['nombre_comun'] . '</td>
                                        <td><input type="checkbox" name="check[' . $rowc['id'] . ']" value="1" class="form-check-input"></td>
                                        </tr>
                                        ';
                                    }
                                    ?> 
                                </table> 
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
            $(document).ready(function () {
                var t = $('#tablas').dataTable(
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
                )

            })
            $('#datepicker').datepicker({
                uiLibrary: 'bootstrap5',
                format: 'yyyy-mm-dd'
            });

        </script>      
    </body>
</html>
