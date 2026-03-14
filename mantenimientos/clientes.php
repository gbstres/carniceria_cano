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
$id_cliente = $descuento = $credito = $activo = 0;
$nombre = $apellido_paterno = $apellido_materno = "";

$fecha_ingreso = date('y-m-d');
$hora_ingreso = date('H:i:s');
$id_usuario = $_SESSION["id"];
$body = "";
$title = "";
$filtro_activo = 1;

if (isset($_POST['agregar'])) {

    // siguiente id_cliente por sucursal
    $rowcliente = mysqli_fetch_assoc(mysqli_query(
                    $link,
                    "SELECT MAX(id_cliente) AS id_cliente FROM cc_clientes WHERE id_sucursal = " . (int) $id_sucursal
            ));
    $id_cliente = (int) ($rowcliente['id_cliente'] ?? 0);
    $id_cliente = ($id_cliente <= 0) ? 1 : ($id_cliente + 1);

    // toma datos del form (sanitiza)
    $nombre = mb_strtoupper(trim($_POST["nombre"] ?? ''));
    $apellido_paterno = mb_strtoupper(trim($_POST["apellido_paterno"] ?? ''));
    $apellido_materno = mb_strtoupper(trim($_POST["apellido_materno"] ?? ''));
    $clave_proveedor = mb_strtoupper(trim($_POST["clave_proveedor"] ?? ''));

    // números (tu is_float() está mal porque recibe string)
    $credito = (float) ($_POST["credito"] ?? 0);
    $descuento = (float) ($_POST["descuento"] ?? 0);

    $activo = isset($_POST['activo']) ? 1 : 0;

    // auditoría
    $id_usuario = (int) ($_SESSION["id"] ?? 0);
    $id_usuario_act = $id_usuario;

    $fecha_ingreso = date('Y-m-d');
    $hora_ingreso = date('H:i:s');
    $fecha_act = $fecha_ingreso;
    $hora_act = $hora_ingreso;

    // campos NOT NULL que no venían en tu insert
    $saldo = 0.00;

    $sql = "INSERT INTO cc_clientes (
                id_sucursal, id_cliente,
                nombre, apellido_paterno, apellido_materno,
                clave_proveedor, credito, descuento, activo,
                saldo,
                id_usuario, fecha_ingreso, hora_ingreso,
                id_usuario_act, fecha_act, hora_act
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    if ($stmt = mysqli_prepare($link, $sql)) {

        mysqli_stmt_bind_param(
                $stmt,
                "iissssddidississ",
                $id_sucursal, $id_cliente,
                $nombre, $apellido_paterno, $apellido_materno,
                $clave_proveedor, $credito, $descuento, $activo,
                $saldo,
                $id_usuario, $fecha_ingreso, $hora_ingreso,
                $id_usuario_act, $fecha_act, $hora_act
        );

        // Inserta cliente
        if (mysqli_stmt_execute($stmt)) {

            // Inserta saldo cliente (tu tabla cc_saldos_clientes)
            $efectivo_hoy = 0.0;
            $efectivo_ayer = 0.0;
            $efectivo_mes = 0.0;

            $sql2 = "INSERT INTO cc_saldos_clientes
                        (id_sucursal, id_cliente, efectivo_hoy, efectivo_ayer, efectivo_mes, id_usuario, fecha_ingreso, hora_ingreso)
                     VALUES (?,?,?,?,?,?,?,?)";

            if ($stmt2 = mysqli_prepare($link, $sql2)) {
                mysqli_stmt_bind_param(
                        $stmt2,
                        "iidddiss",
                        $id_sucursal, $id_cliente,
                        $efectivo_hoy, $efectivo_ayer, $efectivo_mes,
                        $id_usuario, $fecha_ingreso, $hora_ingreso
                );
                mysqli_stmt_execute($stmt2);
                mysqli_stmt_close($stmt2);
            }

            $body = 'Cliente ' . $nombre . ' ' . $apellido_paterno . ' agregado correctamente.';
            $title = 'Agregado';
        } else {
            $body = 'No se pudo agregar el cliente: ' . mysqli_stmt_error($stmt);
            $title = 'No agregado';
        }

        mysqli_stmt_close($stmt);
    } else {
        $body = 'No se pudo preparar el INSERT: ' . mysqli_error($link);
        $title = 'No agregado';
    }
}
if (isset($_POST['editar'])) {
    $id_cliente = trim($_POST["id_cliente_e"]);
    $nombre = mb_strtoupper(trim($_POST["nombre_e"]));
    $apellido_paterno = mb_strtoupper(trim($_POST["apellido_paterno_e"]));
    $apellido_materno = mb_strtoupper(trim($_POST["apellido_materno_e"]));
    $credito = is_float(trim($_POST["credito_e"])) ? 0 : trim($_POST["credito_e"]);
    $descuento = is_float(trim($_POST["descuento_e"])) ? 0 : trim($_POST["descuento_e"]);
    $clave_proveedor = mb_strtoupper(trim($_POST["clave_proveedor_e"]));
    $activo = isset($_POST['activo_e']) ? 1 : 0;
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('y-m-d');
    $hora_act = date('H:i:s');
    $update1 = mysqli_query($link, "UPDATE cc_clientes SET "
                    . "nombre = '$nombre', apellido_paterno = '$apellido_paterno', apellido_materno = '$apellido_materno', credito = $credito, descuento = $descuento, "
                    . "clave_proveedor = '$clave_proveedor', activo = '$activo', fecha_act = '$fecha_act', hora_act = '$hora_act', id_usuario_act = '$id_usuario_act' "
                    . "WHERE id_sucursal = '$id_sucursal' and id_cliente = '$id_cliente'")
            or die(mysqli_error());
    if ($update1) {
        $body = 'Cliente ' . $nombre . ' ' . $apellido_paterno . ' actualizado correctamente.';
        $title = 'Actualizado';
    } else {
        $body = 'Cliente ' . $nombre . ' ' . $apellido_paterno . ' no actualizado.';
        $title = 'No actualizado';
    }
}

//Eliminar cliente
if (isset($_GET['accion']) == 'delete' and (!isset($_POST['agregar'])) and (!isset($_POST['editar']))) {
    $id_cliente = mysqli_real_escape_string($link, (strip_tags($_GET["id"], ENT_QUOTES)));
    $ventas = mysqli_query($link, "SELECT * FROM cc_det_ventas WHERE id_sucursal = '$id_sucursal' and id_cliente = $id_cliente");
    $clientes = mysqli_query($link, "SELECT * FROM cc_clientes WHERE id_sucursal = '$id_sucursal' and id_cliente = $id_cliente");
    $sqlclientes = mysqli_fetch_assoc($clientes);
    if (mysqli_num_rows($ventas) > 0) {
        $body = 'Cliente ' . $sqlclientes['nombre'] . ' ' . $sqlclientes['apellido_paterno'] . ' no eliminado porque existen asociaciones';
        $title = 'No eliminado';
    } else {
        $delete = mysqli_query($link, "DELETE FROM cc_clientes WHERE id_sucursal = '$id_sucursal' and id_cliente = $id_cliente");
        if ($delete) {
            $body = 'Cliente ' . $sqlclientes['nombre'] . ' ' . $sqlclientes['apellido_paterno'] . ' eliminado correctamente';
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
        <title>Clientes</title>

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
                            <h1 class="text-center">Clientes</h1>
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
                                <div class="col-6">
                                    <label for="proveedor" class="form-label">Clave proveedor</label>
                                    <input type="text" class="form-control" id="clave_proveedor" name="clave_proveedor" placeholder="Clave externa del proveedor" autocomplete="off">
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
                                    <label for="Descuento" class="form-label">Descuento</label>
                                    <div class="input-group has-validation">
                                        <input type="number" step="0.01" class="form-control" id="descuento" name="descuento" placeholder="Descuento en %" autocomplete="off" min="0" max="100">
                                    </div>
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
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Filtro</label>
                                <select id="filtroActivo" class="form-select">
                                    <option value="1" <?php echo ($filtro_activo === 1 ? 'selected' : ''); ?>>Activos</option>
                                    <option value="0" <?php echo ($filtro_activo === 0 ? 'selected' : ''); ?>>Inactivos</option>
                                    <option value="2" <?php echo ($filtro_activo === 2 ? 'selected' : ''); ?>>Todos</option>
                                </select>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table id="clientes" class="display" style="width:100%" >
                                <thead>
                                    <tr>
                                        <th>Id</th>
                                        <th>Nombre</th>
                                        <th>A. Paterno</th>
                                        <th>A. Materno</th>
                                        <th>Crédito</th>
                                        <th>Descuento</th>
                                        <th>Saldo</th>
                                        <th>Activo</th>
                                        <th>Usuario</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <?php
                                // Filtro Activo (default: 1)
                                $filtro_activo = isset($_GET['activo']) ? (int) $_GET['activo'] : 1; // 1=activos, 0=inactivos, 2=todos

                                $whereActivo = "";
                                if ($filtro_activo === 1) {
                                    $whereActivo = " AND activo = 1 ";
                                } elseif ($filtro_activo === 0) {
                                    $whereActivo = " AND activo = 0 ";
                                } // si es 2 -> todos (sin filtro)

                                $sqlclientes = mysqli_query($link, "SELECT * FROM cc_clientes WHERE id_sucursal = '$id_sucursal' $whereActivo");
                                $renglon = 0;
                                while ($rowc = mysqli_fetch_assoc($sqlclientes)) {
                                    $renglon = $renglon + 1;
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = 'ROL' and id_clave =" . $rowp['rol']));
                                    $sqluser = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id =" . $rowc['id_usuario']));
                                    $result = mysqli_query($link, "select efectivo_hoy from cc_saldos_clientes where id_sucursal = $id_sucursal and id_cliente =" . $rowc['id_cliente']);
                                    $numero = mysqli_num_rows($result);
                                    if ($numero == 0) {
                                        $saldo = 0;
                                    } else {
                                        $saldos = mysqli_fetch_assoc($result);
                                        $saldo = $saldos['efectivo_hoy'];
                                    }
                                    echo '
                                    <tr id="fila' . $renglon . '">
                                        <td>' . $rowc['id_cliente'] . '</td>
                                        <td class="' . $rowc['clave_proveedor'] . '">' . $rowc['nombre'] . '</td>
                                        <td>' . $rowc['apellido_paterno'] . '</td>
                                        <td>' . $rowc['apellido_materno'] . '</td>
                                        <td>' . $rowc['credito'] . '</td>
                                        <td>' . $rowc['descuento'] . '</td>
                                        <td>' . $saldo . '</td>    
                                        <td class="' . $rowc['activo'] . '"><input type="checkbox" class="form-check-input" onclick="setValue(this)" ';
                                    if ($rowc['activo'] == "1") {
                                        echo 'checked';
                                    } echo ' disabled></td>
                                        <td>' . $sqluser['username'] . '</td>
                                        <td align="center">
                                            <a href="?accion=delete&id=' . $rowc['id_cliente'] . '" title="Eliminar" onclick="return confirm(\'¿Esta seguro de borrar el cliente ' . $rowc['nombre'] . ' ' . $rowc['apellido_paterno'] . '?\')"><img class="imga" src="../img/icons/trash.svg"></a>
                                            <a href="#" title="Editar cliente" data-bs-toggle="modal" data-bs-target="#editModal"><img class="imga" src="../img/icons/pencil-square.svg"></a>
                                            <a href="javascript:ventas_pagos(' . $rowc['id_cliente'] . ')" title="Revisar ventas y pagos"><img class="imga" src="../img/icons/cash_icon.svg"></a>
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
                        <input type="hidden" name="id_cliente_e" id="id_cliente_e">
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
                                <label for="proveedor" class="form-label">Clave proveedor</label>
                                <input type="text" class="form-control" id="clave_proveedor_e" name="clave_proveedor_e" placeholder="Clave externa del proveedor" autocomplete="off">
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
                                <label for="Descuento" class="form-label">Descuento</label>
                                <div class="input-group has-validation">
                                    <input type="number" step="0.01" class="form-control" id="descuento_e" name="descuento_e" placeholder="Descuento en %" autocomplete="off" min="0" max="100">
                                </div>
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
                var t = $('#clientes').DataTable(
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
                var id_cliente = $(icono).parents("tr").find("td").eq(0).text();
                var nombre = $(icono).parents("tr").find("td").eq(1).text();
                var apellido_paterno = $(icono).parents("tr").find("td").eq(2).text();
                var apellido_materno = $(icono).parents("tr").find("td").eq(3).text();
                var credito = $(icono).parents("tr").find("td").eq(4).text();
                var descuento = $(icono).parents("tr").find("td").eq(5).text();
                var activo = $(icono).parents("tr").find("td").eq(6).attr('class');
                var clave_proveedor = $(icono).parents("tr").find("td").eq(1).attr('class');
                $("#ModalLabelTitle").html("Editar cliente: " + id_cliente);
                $("#id_cliente_e").val(id_cliente);
                $("#nombre_e").val(nombre);
                $("#apellido_paterno_e").val(apellido_paterno);
                $("#apellido_materno_e").val(apellido_materno);
                $("#clave_proveedor_e").val(clave_proveedor);
                $("#credito_e").val(credito);
                $("#descuento_e").val(descuento);
                if (activo == 0)
                    $("#activo_e").prop("checked", false);
                else
                    $("#activo_e").prop("checked", true);
            });
            function ventas_pagos(id_cliente)
            {
                window.location = "../control/ventas_clientes.php?id_cliente=" + id_cliente;
            }

            $('#filtroActivo').on('change', function () {
                const v = this.value;
                const url = new URL(window.location.href);
                url.searchParams.set('activo', v);
                window.location.href = url.toString();
            });
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

