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
require_once "config.php";
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

if (isset($_POST['agregar'])) {

    $rowcliente = mysqli_fetch_assoc(mysqli_query($link, "SELECT max(id_cliente) as id_cliente FROM `cc_clientes` WHERE id_sucursal = '$id_sucursal'"));
    $id_cliente = $rowcliente['id_cliente'];
    if ($id_cliente == null) {
        $id_cliente = 1;
    } else {
        $id_cliente = $id_cliente + 1;
    }

    $sql = "INSERT INTO cc_clientes (id_sucursal, id_cliente, nombre, apellido_paterno, apellido_materno, credito, descuento, activo, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "iisssddiiss", $id_sucursal, $id_cliente, $nombre, $apellido_paterno, $apellido_materno, $credito, $descuento, $activo, $id_usuario, $fecha_ingreso, $hora_ingreso);
        $nombre = mb_strtoupper(trim($_POST["nombre"]));
        $apellido_paterno = mb_strtoupper(trim($_POST["apellido_paterno"]));
        $apellido_materno = mb_strtoupper(trim($_POST["apellido_materno"]));
        $credito = is_float(trim($_POST["credito"])) ? 0 : trim($_POST["credito"]);
        $descuento = is_float(trim($_POST["descuento"])) ? 0 : trim($_POST["descuento"]);
        $activo = isset($_POST['activo']) ? 1 : 0;
        $fecha_ingreso = date('y-m-d');
        $hora_ingreso = date('H:i:s');
        $id_usuario = $_SESSION["id"];
        if (mysqli_stmt_execute($stmt)) {
            echo '<script type="text/javascript">alert("Registro insertado");</script>';
        } else {
            echo "Algo salió mal, por favor inténtalo de nuevo.";
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
        <title>Derivados</title>

        <script src="https://code.jquery.com/jquery-3.5.1.js"></script>
        <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
        <script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/plug-ins/1.12.1/api/sum().js"></script>
        <script src="js/jquery.jeditable.js" type="text/javascript"></script>
        <script type="text/javascript" src="js/jquery.dataTables.editable.js"></script>
        <script src="js/jquery.jeditable.js" type="text/javascript"></script>
        <script src="js/jquery.validate.js" type="text/javascript"></script>
        <script src="js/typeahead.js"></script>
        <style>
            @import "css/bootstrap.css";

            .bd-placeholder-img {
                font-size: 1.125rem;
                text-anchor: middle;
                -webkit-user-select: none;
                -moz-user-select: none;
                user-select: none;
            }

            @media (min-width: 768px) {
                .bd-placeholder-img-lg {
                    font-size: 3.5rem;
                }
            }

            .b-example-divider {
                height: 3rem;
                background-color: rgba(0, 0, 0, .1);
                border: solid rgba(0, 0, 0, .15);
                border-width: 1px 0;
                box-shadow: inset 0 .5em 1.5em rgba(0, 0, 0, .1), inset 0 .125em .5em rgba(0, 0, 0, .15);
            }

            .b-example-vr {
                flex-shrink: 0;
                width: 1.5rem;
                height: 100vh;
            }

            .bi {
                vertical-align: -.125em;
                fill: currentColor;
            }

            .nav-scroller {
                position: relative;
                z-index: 2;
                height: 2.75rem;
                overflow-y: hidden;
            }

            .nav-scroller .nav {
                display: flex;
                flex-wrap: nowrap;
                padding-bottom: 1rem;
                margin-top: -1px;
                overflow-x: auto;
                text-align: center;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
            }

            .typeahead {
                border: 1px solid #000;
                border-radius: 4px;
                padding: 8px 12px;
                max-width: 300px;
                min-width: 290px;
                color: #FFF;
            }
            .tt-menu {
                width:300px;
            }
            ul.typeahead{
                margin:0px;
                padding:0px 0px;
            }
            ul.typeahead.dropdown-menu li a {
                padding: 5px !important;
                border-bottom:#CCC 1px solid;
            }
            ul.typeahead.dropdown-menu li:last-child a {
                border-bottom:0px !important;
            }
            .dropdown-menu>.active>a, .dropdown-menu>.active>a:focus, .dropdown-menu>.active>a:hover {
                text-decoration: none;
                background-color: #bfbfbf;
                outline: 0;
            }
        </style>
        <!-- Custom styles for this template -->
        <link href="css/navbar.css" rel="stylesheet">
        <link href="https://cdn.datatables.net/1.12.1/css/jquery.dataTables.min.css" rel="stylesheet">
    </head>
    <body>
        <main>
            <div class="container">
                <?php require_once "nav.php" ?>
                <div>
                    <div class="bg-light p-4 rounded ">
                        <div class="col-sm-8 mx-auto">
                            <h1 class="text-center">Derivados</h1>
                        </div>
                        <br>
                        <br>
                        <form action="#" method="post" id="form_derivado" novalidate>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="Nombre" class="form-label">Producto</label>
                                    <input type="text" class="form-control" id="producto" name="producto" placeholder="Producto" autocomplete="off" required>
                                </div>
                                <div class="col-6">
                                    <label for="ApellidoPaterno" class="form-label">Derivados</label>
                                    <input type="text" class="form-control" id="derivado" name="derivado" placeholder="Derivados" autocomplete="off" required>

                                </div>
                            </div>
                            <div class="col-12 text-center mt-4" >
                                <button type="submit" class="btn btn-primary m-1" name="agregar">Guardar</button>
                            </div>
                        </form>
                        <br>
                        <br>
                        <div class="container">
                            <div class="row justify-content-start">
                                <div class="col-4 border">
                                    <div id="alert_producto">
                                    </div>
                                </div>
                                <div class="col-8 border">
                                    <div id="alert_derivado">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <br>
                        <br>
                        <div class="table-responsive">
                            <table id="derivados" class="display" style="width:100%" >
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th>Usuario</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <?php
                                $sqlderivados = mysqli_query($link, "SELECT id_sucursal,codigo_p,id_usuario FROM cc_derivados where id_sucursal = '$id_sucursal' group by id_sucursal,codigo_p,id_usuario");
                                $renglon = 0;
                                while ($rowc = mysqli_fetch_assoc($sqlderivados)) {
                                    $renglon = $renglon + 1;
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = 'ROL' and id_clave =" . $rowp['rol']));
                                    $sqluser = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id_usuario =" . $rowc['id_usuario']));
                                    $sqlproducto = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_productos where id_sucursal = '$id_sucursal' and codigo =" . $rowc['codigo_p']));
                                    echo '
                                    <tr id="fila' . $renglon . '">
                                        <td>' . $rowc['codigo_p'] . '</td>
                                        <td>' . $sqlproducto['descripcion'] . '</td>
                                        <td>' . $sqluser['username'] . '</td>
                                        <td align="center">
                                            <a href="?aksi=delete&id=' . $rowc['codigo_p'] . '" title="Eliminar" onclick="return confirm(\'¿Esta seguro de borrar los derivados de ' . $sqlproducto['descripcion'] . '?\')"><img class="imga" src="img/icons/trash.svg"></a>
                                            <a href="#" title="Editar derivado" data-bs-toggle="modal" data-bs-target="#editModal"><img class="imga" src="img/icons/pencil-square.svg"></a>
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

        <!-- Modal porcentaje -->
        <div class="modal fade modal-lg" id="Modalderivado" tabindex="-1" aria-labelledby="Modalderivado" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ModalLabelTitle3">Ingrese el porcentaje</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form class="needs-validation" action="#" method="post" novalidate id="form_porcentaje">
                        <input type="hidden" name="id_empleado" id="id_empleado" value = "0">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Código</label>
                                <div class="input-group">
                                    <label for="codigo" class="form-label" id="codigo_e"></label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción</label>
                                <div class="input-group">
                                    <label for="descripcion" class="form-label" id="descripcion_e"></label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="porcentaje" class="form-label">Porcentaje</label>
                                <div class="input-group has-validation">
                                    <input type="number" step="0.01" class="form-control" id="porcentaje_i" name="porcentaje_i" placeholder="Ingresar porcentaje" autocomplete="off" required min="1" max="99">
                                    <div class="invalid-feedback">
                                        Ingresar porcentaje
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary" >Aceptar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>


        <script src="js/bootstrap.bundle.min.js"></script>
        <script>
            $(document).ready(function () {
                $('.needs-validation').on('submit', function (event) {
                    // Verifica si el formulario es válido
                    if (!this.checkValidity()) {
                        // Si el formulario no es válido, detiene el envío y muestra los mensajes de validación
                        event.preventDefault();
                        event.stopPropagation();
                        this.classList.add('was-validated');
                    } else {
                        // Si el formulario es válido, cierra el modal
                        $('#this').modal('hide');
                    }
                });

                $('#producto').typeahead({
                    source: function (busqueda, process) {
                        $.ajax({
                            url: "consulta_productos.php",
                            data: 'busqueda=' + busqueda,
                            dataType: "json",
                            type: "POST",
                            success: function (data) {
                                console.log(data);
                                process(data);
                            },
                            error: function (response) {
                                alert(response);
                            }
                        });
                    },
                    display: 'name',
                    afterSelect: function (item) {
                        agrega_alerta_producto(item.name, item.id);
                        $("#producto").val("");
                    },
                    minLength: 2,
                    delay: 600
                });

                $('#derivado').typeahead({
                    source: function (busqueda, process) {
                        $.ajax({
                            url: "consulta_productos.php",
                            data: 'busqueda=' + busqueda,
                            dataType: "json",
                            type: "POST",
                            success: function (data) {
                                console.log(data);
                                process(data);
                            },
                            error: function (response) {
                                alert(response);
                            }
                        });
                    },
                    display: 'name',
                    val: 'id',
                    afterSelect: function (item) {
                        var myModal = new bootstrap.Modal(document.getElementById("Modalderivado"), {});
                        myModal.show();
                        $("#codigo_e").html(item.id);
                        $("#descripcion_e").html(item.name);
                        $("#derivado").val("");
                        $("#porcentaje_i").val("");
                        $("#porcentaje_i").focus();
                    },
                    minLength: 2,
                    delay: 600
                });
                var t = $('#derivados').DataTable(
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
            });
            function agrega_alerta_producto(nom_producto, id_producto)
            {
                $('#div_alert').remove();
                var wrapper = document.createElement('div').innerHTML = '<div id="' + id_producto + '"class="alert alert-secondary alert-dismissible mb-0 align-middle" role="alert"><strong></strong> ' + nom_producto + ' <a href="#" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                $('#alert_producto').append(wrapper);
            }

            function agrega_alerta_derivado()
            {
                var porcentaje = $('#porcentaje_i').val();
                var id_producto = $("#codigo_e").html();
                var nom_derivado = $("#descripcion_e").html();
                $("#descripcion_e").html();
                var wrapper = document.createElement('div').innerHTML = '<div id="' + id_producto + '"class="alert alert-secondary alert-dismissible mb-0" role="alert" attribute1="' + porcentaje + '"><strong>' + porcentaje + '% </strong> ' + nom_derivado + ' <a href="#" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                $('#alert_derivado').append(wrapper);
            }

            var myForm_prodct = document.getElementById('form_porcentaje');
            myForm_prodct.addEventListener('submit', function (event) {
                event.preventDefault(); // Evitar que el formulario se envíe automáticamente
                // Validar el formulario
                if (myForm_prodct.checkValidity() === false) {
                    // Si hay errores, no hacer nada
                    event.stopPropagation();
                } else {
                    agrega_alerta_derivado();// Llama a la función JavaScript para agregar a la tabla
                    $('#Modalderivado').modal('hide');
                }
                // Agregar la clase "was-validated" para mostrar los errores
                myForm_prodct.classList.add('was-validated');
            });

            document.getElementById("form_derivado").addEventListener("submit", function (event) {
                event.preventDefault(); // Evita que el formulario se envíe automáticamente
                guarda_derivado(); // Llama a la función JavaScript para procesar los datos del formulario
            });
            function guarda_derivado()
            {
                var derivados = $('#alert_derivado').children('div');
                var sumPorcentaje = 0;
                let arregloComponentes = [];
                derivados.each(function () {
                    sumPorcentaje = sumPorcentaje + Number($(this).attr('attribute1'));
                    arregloComponentes.push($(this).attr('id'));
                });

                var idproducto = $("#alert_producto").children().first().attr("id");

                if ($('#alert_producto').children().length != 1 || $('#alert_derivado').children().length <= 1)
                    alert('Asignación incorrecta');
                else if (sumPorcentaje != 100)
                    alert('Porcentaje incorrecto');
                else if (tieneRepetidos(arregloComponentes))
                    alert('No puede utilizar 2 derivados iguales');
                else if (arregloComponentes.includes(idproducto))
                    alert('No puede usar el mismo producto en derivado');
                else {
                    edita_derivado(idproducto, 0, 0, 2);
                    derivados.each(function () {
                        sumPorcentaje = sumPorcentaje + Number($(this).attr('attribute1'));
                        arregloComponentes.push($(this).attr('id'));
                        codigo_d = $(this).attr('id');
                        porcentaje = Number($(this).attr('attribute1'));
                        edita_derivado(idproducto, codigo_d, porcentaje, 1);
                    });
                    location.reload();
                }
            }
            function tieneRepetidos(arreglo) {
                const valoresVistos = {};
                for (let i = 0; i < arreglo.length; i++) {
                    const valor = arreglo[i];
                    if (valoresVistos[valor]) {
                        return true;
                    }
                    valoresVistos[valor] = true;
                }
                return false;
            }
            function edita_derivado(codigo_p, codigo_d, porcentaje, movimiento)
            {
                var parametros = {"codigo_p": codigo_p, "codigo_d": codigo_d, "porcentaje": porcentaje, "movimiento": movimiento};
                $.ajax({
                    url: "edita_derivado.php",
                    data: parametros,
                    dataType: "json",
                    type: "POST",
                    success: function (response) {
                        console.log(response);
                    },
                    error: function (response) {
                        console.log(response);
                    }
                });
            }
        </script>      
    </body>
</html>

