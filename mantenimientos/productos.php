<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";
date_default_timezone_set("America/Mexico_City");
// Define variables and initialize with empty values
$codigo = 0;
$id_sucursal = $_SESSION["id_sucursal"];
$descripcion = "";
$precio_compra = 0;
$precio_venta = 0;
$almacen = 0;
$id_categoria = 0;
$activo = 0;
$fecha_ingreso = date('y-m-d');
$hora_ingreso = date('H:i:s');
$id_usuario = $_SESSION["id"];
$body = "";
$title = "";
$codigo_e = $descripcion_e = $precio_compra_e = $precio_venta_e = $almacen_e = $id_categoria_e = "";
if (isset($_POST['agregar'])) {
    $codigo_e = $codigo = trim($_POST["codigo"]);
    $descripcion_e = $descripcion = mb_strtoupper(trim($_POST["descripcion"]));
    $precio_compra_e = $precio_compra = trim($_POST["precio_compra"]);
    $precio_venta_e = $precio_venta = trim($_POST["precio_venta"]);
    $almacen_e = $almacen = trim($_POST["almacen"]);
    $id_categoria_e = $id_categoria = trim($_POST["id_categoria"]);
    $centralizar_almacen = trim($_POST["centralizar_almacen"]);
    $activo = isset($_POST['activo']) ? 1 : 0;
    $consulta = mysqli_query($link, "SELECT * FROM cc_productos WHERE codigo='$codigo' and id_sucursal = '$id_sucursal'");
    if (mysqli_num_rows($consulta) > 0) {
        $title = 'No Agregado';
        $body = 'Producto ' . $descripcion . ' no se puede agregar, ya existe el código ' . $codigo_e . '.';
    } else {

        $sql = "INSERT INTO cc_productos (codigo, id_sucursal, descripcion, precio_compra, precio_venta, almacen, id_categoria, centralizar_almacen, activo, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "iisdddiiiiss", $codigo, $id_sucursal, $descripcion, $precio_compra, $precio_venta, $almacen, $id_categoria, $centralizar_almacen, $activo, $id_usuario, $fecha_ingreso, $hora_ingreso);

            if (mysqli_stmt_execute($stmt)) {
                $title = 'Agregado';
                $body = 'Producto ' . $descripcion . ' agregado correctamente.';
                $codigo_e = $codigo = "";
                $descripcion_e = $descripcion = "";
                $precio_compra_e = $precio_compra = "";
                $precio_venta_e = $precio_venta = "";
                $almacen_e = $almacen = "";
                $id_categoria_e = $id_categoria = "";
            } else {
                $title = 'No Agregado';
                $body = 'Producto ' . $descripcion . ' no se puede agregar.';
            }
        }
    }
}
if (isset($_POST['editar'])) {
    $codigo = trim($_POST["codigo_e"]);
    $descripcion = mb_strtoupper(trim($_POST["descripcion_e"]));
    $precio_compra = trim($_POST["precio_compra_e"]);
    $precio_venta = trim($_POST["precio_venta_e"]);
    $almacen = trim($_POST["almacen_e"]);
    $id_categoria = trim($_POST["id_categoria_e"]);
    $centralizar_almacen = trim($_POST["centralizar_almacen_e"]);
    $activo = isset($_POST['activo_e']) ? 1 : 0;
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('Y-m-d');
    $hora_act = date('H:i:s');
    $update1 = mysqli_query($link, "UPDATE cc_productos SET "
                    . "descripcion='$descripcion', precio_compra='$precio_compra', precio_venta='$precio_venta', almacen='$almacen', id_categoria='$id_categoria', centralizar_almacen = '$centralizar_almacen', activo='$activo', "
                    . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                    . "WHERE codigo='$codigo' and id_sucursal='$id_sucursal'")
            or die(mysqli_error());

    if ($update1) {
        $title = 'Actualizado';
        $body = 'Producto ' . $descripcion . ' actualizado correctamente.';
    } else {
        $title = 'No actualizado';
        $body = 'Producto ' . $descripcion . ' no actualizado.';
    }
}

if (isset($_GET['accion']) == 'delete' and $title == '') {
    // escaping, additionally removing everything that could be (html/javascript-) code
    $codigo = mysqli_real_escape_string($link, (strip_tags($_GET["codigo"], ENT_QUOTES)));
    $cek1 = mysqli_query($link, "SELECT * FROM cc_ventas WHERE id_sucursal = '$id_sucursal' and codigo = '$codigo'");
    $cek2 = mysqli_query($link, "SELECT * FROM cc_productos WHERE id_sucursal = '$id_sucursal' and codigo = '$codigo'");
    $sqlproductos = mysqli_fetch_assoc($cek2);
    if (mysqli_num_rows($cek1) > 0) {
        $title = 'No Eliminado';
        $body = 'Producto ' . $sqlproductos['descripcion'] . ' no eliminado. Ya está asociado a ventas';
    } else {

        $delete = mysqli_query($link, "DELETE FROM cc_productos WHERE codigo='$codigo' and id_sucursal = '$id_sucursal'");
        if ($delete) {
            $title = 'Eliminado';
            $body = 'Producto ' . $sqlproductos['descripcion'] . ' eliminado correctamente.';
        } else {
            $title = 'No Eliminado';
            $body = 'Producto ' . $sqlproductos['descripcion'] . ' no eliminado.';
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
        <title>Productos</title>

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
                            <h1 class="text-center">Productos</h1>
                        </div>
                        <br>
                        <br>
                        <form class="row g-3 needs-validation" action="#" method="post" novalidate>
                            <div class="row g-3">
                                <div class="col-4">
                                    <label for="codigo" class="form-label">Código</label>
                                    <input type="number" class="form-control" id="codigo" name="codigo" placeholder="Código de barras" autocomplete="off" required="required" pattern="^[0-9]+$" value="<?php echo $codigo_e ?>">
                                    <div class="invalid-feedback">
                                        Favor de ingresar una clave válida.
                                    </div>
                                </div>
                                <div class="col-8">
                                    <label for="descripcion" class="form-label">Descripción</label>
                                    <div class="input-group has-validation">
                                        <input type="text" class="form-control" id="descripcion" name="descripcion" placeholder="Descripción del producto" autocomplete="off" required value="<?php echo $descripcion_e ?>">
                                        <div class="invalid-feedback">
                                            Favor de ingresar la descripción del producto.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="preciocompra" class="form-label">Precio Compra</label>
                                    <div class="input-group has-validation">
                                        <input type="number" step="0.01" class="form-control" id="precio_compra" name="precio_compra" placeholder="Precio de compra" autocomplete="off" required value="<?php echo $precio_compra_e ?>">
                                        <div class="invalid-feedback">
                                            Favor de ingresar precio de compra.
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label for="precioventa" class="form-label">Precio venta</label>
                                    <div class="input-group has-validation">
                                        <input type="number" step="0.01" class="form-control" id="precio_venta" name="precio_venta" placeholder="Precio de venta" autocomplete="off" required value="<?php echo $precio_venta_e ?>">
                                        <div class="invalid-feedback">
                                            Favor de ingresar precio de venta.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="almacen" class="form-label">En almacén</label>
                                    <div class="input-group has-validation">
                                        <input type="number" step="0.001" class="form-control" id="almacen" name="almacen" placeholder="kg en almacén" autocomplete="off" required value="<?php echo $almacen_e ?>">
                                        <div class="invalid-feedback">
                                            Ingresar stock.
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label for="inputState" class="form-label">Categoría</label>
                                    <div class="input-group has-validation">
                                        <select id="id_categoria" name="id_categoria" class="form-select" required>
                                            <option selected disabled value="">Seleccione...</option>
                                            <?php
//$query = $link -> query ("SELECT * FROM sbb_telas");
                                            $query = mysqli_query($link, "SELECT * FROM cc_categorias where id_sucursal = $id_sucursal order by mayoreo,desc_categoria");
                                            while ($valores = mysqli_fetch_array($query)) {
                                                $mayoreo = $valores['mayoreo'] ? 'MAYOREO' : 'MENUDEO';
                                                if ($valores['id_categoria'] == $id_categoria_e) {
                                                    echo '<option value="' . $valores['id_categoria'] . '" selected="selected">' . $valores['desc_categoria'] . ' - ' . $mayoreo . '</option>';
                                                } else {
                                                    echo '<option value="' . $valores['id_categoria'] . '">' . $valores['desc_categoria'] . ' - ' . $mayoreo . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                        <div class="invalid-feedback">
                                            Favor de seleccionar la categoría
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="inputcentraliza" class="form-label">¿Dónde centraliza almacén?</label>
                                    <div class="input-group has-validation">
                                        <select id="centralizar_almacen" name="centralizar_almacen" class="form-select" required>
                                            <option selected disabled value="">Seleccione...</option>
                                            <?php
//$query = $link -> query ("SELECT * FROM sbb_telas");
                                            $queryca = mysqli_query($link, "SELECT * FROM cc_claves where nombre_clave = 'CENTRALIZAR_ALMACEN'");
                                            while ($rowca = mysqli_fetch_array($queryca)) {
                                                echo '<option value="' . $rowca['clave'] . '">' . $rowca['descripcion'] . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <div class="invalid-feedback">
                                            Favor de seleccionar dónde centraliza almacen
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label for="checkMayoreo" class="form-label">¿Activo?</label><br>
                                    <input class="form-check-input" name="activo" type="checkbox" value="1" id="mayoreo" checked><br>
                                </div>
                            </div>
                            <div class="col-12 text-center" >
                                <button type="submit" class="btn btn-primary" name="agregar">Agregar</button>
                            </div>
                        </form>
                        <br>
                        <br>
                        <div class="table-responsive">
                            <table id="productos" class="display" style="width:100%" >
                                <thead>
                                    <tr>
                                        <th class="codigo">Código</th>
                                        <th class="descripcion">Descripción</th>
                                        <th class="precio_compra">Precio compra</th>
                                        <th class="precio_venta">Precio venta</th>
                                        <th>Stock</th>
                                        <th>Categoría</th>
                                        <th>May.</th>
                                        <th>CA</th>
                                        <th>Act.</th>
                                        <th>Usuario</th>
                                        <th>&nbsp;</th>
                                    </tr>
                                </thead>
                                <?php
                                $sqlproductos = mysqli_query($link, "SELECT * FROM cc_productos where id_sucursal = '$id_sucursal' order by id_categoria");
                                while ($rowp = mysqli_fetch_assoc($sqlproductos)) {
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = 'ROL' and id_clave =" . $rowp['rol']));
                                    $sqluser = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id =" . $rowp['id_usuario']));
                                    $sqlcategorias = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_categorias where id_sucursal = '$id_sucursal' and id_categoria =" . $rowp['id_categoria']));
                                    $sqlca = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_claves where nombre_clave = 'CENTRALIZAR_ALMACEN' and clave =" . $rowp['centralizar_almacen']));
                                    echo '
                                    <tr id="' . $rowp['codigo'] . '">
                                        <td>' . $rowp['codigo'] . '</td>
                                        <td>' . $rowp['descripcion'] . '</td>
                                        <td>' . $rowp['precio_compra'] . '</td>
                                        <td>' . $rowp['precio_venta'] . '</td>
                                        <td>' . $rowp['almacen'] . '</td>
                                        <td class="' . $rowp['id_categoria'] . '">' . $sqlcategorias['desc_categoria'] . '</td>
                                        <td><input type="checkbox" class="form-check-input" disabled ';
                                    if ($sqlcategorias['mayoreo'] == "1") {
                                        echo 'checked';
                                    } echo '></td>
                                        <td class="' . $rowp['centralizar_almacen'] . '">' . $sqlca['descripcion_corta'] . '</td>   
                                        <td class="' . $rowp['activo'] . '"><input type="checkbox" class="form-check-input" disabled ';
                                    if ($rowp['activo'] == "1") {
                                        echo 'checked';
                                    } echo '></td>
                                        <td>' . $sqluser['username'] . '</td>
                                        <td align="center">
                                            <a href="?accion=delete&codigo=' . $rowp['codigo'] . '" title="Eliminar" onclick="return confirm(\'¿Esta seguro de borrar el producto ' . $rowp['descripcion'] . '?\')"><img class="imga" src="../img/icons/trash.svg"></a>
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
                        <h5 class="modal-title" id="ModalLabelTitle">Nuevo mensaje</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form class="needs-validation" action="#" method="post" novalidate>
                        <input type="hidden" name="codigo_e" id="codigo_e">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción</label>
                                <div class="input-group has-validation">
                                    <input type="text" class="form-control" id="descripcion_e" name="descripcion_e" placeholder="Descripción del producto" autocomplete="off" required>
                                    <div class="invalid-feedback">
                                        Favor de ingresar la descripción del producto.
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="preciocompra" class="col-form-label">Precio Compra</label>
                                <div class="input-group has-validation">
                                    <input type="number" step="0.01" class="form-control" id="precio_compra_e" name="precio_compra_e" placeholder="Precio de compra" autocomplete="off" required>
                                    <div class="invalid-feedback">
                                        Favor de ingresar precio de compra.
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="precioventa" class="form-label">Precio venta</label>
                                <div class="input-group has-validation">
                                    <input type="number" step="0.01" class="form-control" id="precio_venta_e" name="precio_venta_e" placeholder="Precio de venta" autocomplete="off" required>
                                    <div class="invalid-feedback">
                                        Favor de ingresar precio de venta.
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
                                <label for="inputState" class="form-label">Categoría</label>
                                <div class="input-group has-validation">
                                    <select id="id_categoria_e" name="id_categoria_e" class="form-select" required>
                                        <option selected disabled value="">Seleccione...</option>
                                        <?php
//$query = $link -> query ("SELECT * FROM sbb_telas");
                                        $query = mysqli_query($link, "SELECT * FROM cc_categorias where id_sucursal = $id_sucursal order by mayoreo,desc_categoria");
                                        while ($valores = mysqli_fetch_array($query)) {
                                            $mayoreo = $valores['mayoreo'] ? 'MAYOREO' : 'MENUDEO';
                                            echo '<option value="' . $valores['id_categoria'] . '">' . $valores['desc_categoria'] . ' - ' . $mayoreo . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Favor de seleccionar la categoría
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="inputcentraliza" class="form-label">¿Dónde centraliza almacén?</label>
                                <div class="input-group has-validation">
                                    <select id="centralizar_almacen_e" name="centralizar_almacen_e" class="form-select" required>
                                        <option selected disabled value="">Seleccione...</option>
                                        <?php
                                        $queryca = mysqli_query($link, "SELECT * FROM cc_claves where nombre_clave = 'CENTRALIZAR_ALMACEN'");
                                        while ($rowca = mysqli_fetch_array($queryca)) {
                                            echo '<option value="' . $rowca['clave'] . '">' . $rowca['descripcion'] . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Favor de seleccionar dónde centraliza almacen
                                    </div>
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

                var t = $('#productos').dataTable(
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
                    sUpdateURL: "../functions/actualizaproductos.php",
                    aoColumns: [
                        null,
                        {
                            type: 'text',
                            indicator: 'Saving platforms...',
                            tooltip: 'Click para editar descripción',
                            cssclass: 'required',
                            sColumnName: 'descripcion',
                            onkeyup: 'this.value = this.value.toUpperCase()'
                        },
                        {
                            type: 'number',
                            indicator: 'Saving platforms...',
                            tooltip: 'Click para editar precio',
                            cssclass: 'required',
                            sColumnName: 'precio_compra'
                        },
                        {
                            type: 'number',
                            indicator: 'Saving platforms...',
                            tooltip: 'Click para editar precio',
                            cssclass: 'required',
                            sColumnName: 'precio_venta'
                        },
                        {
                            type: 'number',
                            indicator: 'Saving platforms...',
                            tooltip: 'Click para editar Stock',
                            cssclass: 'required',
                            sColumnName: 'almacen'
                        }, null, null, null, null, null
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
                var codigo = $(icono).parents("tr").find("td").eq(0).text();
                var descripcion = $(icono).parents("tr").find("td").eq(1).text();
                var precio_compra = $(icono).parents("tr").find("td").eq(2).text();
                var precio_venta = $(icono).parents("tr").find("td").eq(3).text();
                var almacen = $(icono).parents("tr").find("td").eq(4).text();
                var categoria = $(icono).parents("tr").find("td").eq(5).attr('class');
                var centralizar_almacen = $(icono).parents("tr").find("td").eq(7).attr('class');
                var activo = $(icono).parents("tr").find("td").eq(8).attr('class');
                $("#ModalLabelTitle").html("Editar el código: " + codigo);
                $("#codigo_e").val(codigo);
                $("#descripcion_e").val(descripcion);
                $("#precio_compra_e").val(precio_compra);
                $("#precio_venta_e").val(precio_venta);
                $("#almacen_e").val(almacen);
                $("#id_categoria_e").val(categoria);
                $("#centralizar_almacen_e").val(centralizar_almacen);
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
