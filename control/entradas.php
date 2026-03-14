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
$id_sucursal = $_SESSION["id_sucursal"];

//$cliente = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_clientes where id_cliente = $id_cliente"));

if (isset($_POST['fecha'])) {
    $fecha = $_POST['fecha'];
} else {
    $fecha = date('Y-m-d');
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
        <title>Entradas</title>

        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../s/jquery-ui.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <script src="../js/sum().js"></script>
        <script src="../js/jquery.jeditable.js" type="text/javascript"></script>
        <script src="../js/jquery.dataTables.editable.js" type="text/javascript"></script>
        <script src="../js/jquery.jeditable.js" type="text/javascript"></script>
        <script src="../js/jquery.validate.js" type="text/javascript"></script>
        <script src="../js/gijgo.min.js" type="text/javascript"></script>




        <style>
            @import "../css/bootstrap.css";
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
                            <h1 class="text-center">Entradas</h1>
                        </div>
                        <br>
                        <br>
                        <form class="row g-3 needs-validation" action="#" method="post" novalidate>
                            <div class="row g-3">
                                <div class="col-4">
                                    <label for="Fecha" class="form-label">Seleccione la fecha:</label>
                                    <input name="fecha" id="datepicker" width="276" autocomplete="off" readonly="" value="<?php echo $fecha ?>"/>
                                </div>
                                <div class="col-1">
                                    <label for="Fecha" class="form-label">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</label>
                                    <input class="btn btn-primary black bg-silver" type="submit" value="Buscar" id="buscar_fecha">
                                </div>
                            </div>
                        </form>

                        <br>
                        <div class="table-responsive">
                            <table id="entradas" class="display" style="width:100%" >
                                <thead>
                                    <tr>
                                        <th>Id</th>
                                        <th>Fecha</th>
                                        <th>Hora</th>
                                        <th>Descripción</th>
                                        <th>Importe</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <?php
                                $sqlentradas = mysqli_query($link, "SELECT a.id_entrada,a.fecha_ingreso,a.hora_ingreso,"
                                        . "round(a.precio * a.cantidad,2) as importe, a.descripcion "
                                        . "FROM cc_entradas as a "
                                        . "WHERE a.id_sucursal = '$id_sucursal' and a.fecha_ingreso = '$fecha'"
                                        . " order by a.id_entrada DESC");

                                $renglon = 0;
                                while ($rowc = mysqli_fetch_assoc($sqlentradas)) {
                                    $renglon = $renglon + 1;
                                    //$sqlcatalogo = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_catalogos where nombre_clave = 'ROL' and id_clave =" . $rowp['rol']));
                                    //$producto = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_entradas where id_sucursal = '$id_sucursal' and codigo =" . $rowc['codigo']));
                                    //$cliente = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_clientes where id_sucursal = '$id_sucursal' and id_cliente =" . $rowc['id_cliente']));

                                    echo '
                                    <tr id="' . $rowc['id_entrada'] . '">
                                        <td>' . $rowc['id_entrada'] . '</td>
                                        <td>' . $rowc['fecha_ingreso'] . '</td>
                                        <td>' . $rowc['hora_ingreso'] . '</td>
                                        <td>' . $rowc['descripcion'] . '</td>    
                                        <td>' . $rowc['importe'] . '</td>
                                        <td align="center">
                                            <a href="#" class=".remove" title="Eliminar" onclick="elimina_entrada(this,' . $rowc['id_entrada'] . ',\'' . $rowc['descripcion'] . '\')"><img class="imga" src="../img/icons/trash.svg"></a>
                                        </td>
                                        </tr>
                                        ';
                                }
                                ?> 
                                <tfoot>
                                    <tr>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th id="total_entrada"></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table> 
                        </div>
                        <form  action="#" method="post" novalidate id="form_cancelar">
                            <input type="hidden" name="id_entrada" id="id_entrada">
                            <input type="hidden" name="id_consecutivo" id="id_consecutivo" >
                            <input type="hidden" name="movimiento" id="movimiento" >
                        </form>    
                        <br>
                        <br>
                        <br>
                        <div class="align-content-center text-center">
                            <a href="#" class="btn btn-primary m-1" role="button" id="addRow">Agrega entrada</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <div class="modal fade modal-lg" id="agregaModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ModalLabelTitle"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form class="needs-validation" action="#" method="post" novalidate id="form_entrada">
                        <!--<input type="hidden" name="id_cliente_e" id="id_cliente_e" value=""> -->
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="Importe" class="form-label">Importe:</label>
                                <div class="input-group has-validation">
                                    <input type="number" step="0.01" class="form-control" id="importe_e" name="importe_e" placeholder="Importe" autocomplete="off" required min="1">
                                    <div class="invalid-feedback">
                                        Favor de ingresar importe
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descripción:</label>
                                <input type="text" class="form-control" id="descripcion_e" name="descripcion_e" placeholder="Descripción">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="submit" class="btn btn-primary" name="editar">Guardar entrada</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
            var g;
            $(document).ready(function () {
                $('#entradas').dataTable(
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
                            footerCallback: function () {
                                var api = this.api();
                                $(api.column(3).footer()).html('Total de entrada');
                                $(api.table().column(4).footer()).html(api.column(4, {page: 'current'}).data().sum().toFixed(2));
                            },
                            columnDefs: [
                                {className: 'dt-center', targets: [5]}
                            ],
                        }
                ).makeEditable({
                    sUpdateURL: "../functions/actualiza_entrada.php",
                    aoColumns: [
                        null,
                        null,
                        null,
                        {
                            type: 'text',
                            indicator: 'guardando información...',
                            tooltip: 'Click para editar descripción',
                            cssclass: 'required',
                            sColumnName: 'descripcion',
                            onkeyup: 'this.value = this.value.toUpperCase()'
                        },
                        {
                            type: 'number',
                            step: '0.001',
                            indicator: 'Guardando información...',
                            tooltip: 'Click para editar importe',
                            cssclass: 'required',
                            sColumnName: 'precio'
                        }, null
                    ],
                });


                $('#addRow').on('click', function () {
                    var $this = $(this);
                    var parametros = {"busqueda": $("#codigo").val()};

                    var myModal = new bootstrap.Modal(document.getElementById("agregaModal"), {});
                    $("#ModalLabelTitle").html("Agregar entrada");
                    $("#importe_e").val("");
                    myModal.show();
                    setTimeout(() => {
                        $("#importe_e").focus();
                    }, 500);
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
            $('#datepicker').datepicker({
                uiLibrary: 'bootstrap5',
                format: 'yyyy-mm-dd'
            });
            var myForm_entrada = document.getElementById('form_entrada');
            myForm_entrada.addEventListener('submit', function (event) {
                event.preventDefault(); // Evitar que el formulario se envíe automáticamente
                // Validar el formulario
                if (myForm_entrada.checkValidity() === false) {
                    // Si hay errores, no hacer nada
                    event.stopPropagation();
                } else {
                    guarda_entrada(); // Llama a la función JavaScript para agregar a la tabla
                    $('#agregaModal').modal('hide');
                }
                // Agregar la clase "was-validated" para mostrar los errores
                myForm_entrada.classList.add('was-validated');
            });
            function guarda_entrada()
            {
                id_cliente = $("#id_cliente_e").val();
                cantidad = 1;
                codigo = 0;
                precio = parseFloat($("#importe_e").val()) / cantidad;
                descripcion = $("#descripcion_e").val();
                movimiento = 1;
                comentario = "";
                //id_consecutivo = $("#id_consecutivo").val();
                var parametros = {"movimiento": movimiento, "codigo": codigo, "descripcion": descripcion, "precio": precio, "cantidad": cantidad, "comentario": comentario};
                $.ajax({
                    url: "../functions/edita_entradas.php",
                    data: parametros,
                    dataType: "json",
                    type: "POST",
                    success: function (response) {
                        var tabla = $("#entradas").DataTable();
                        tabla.row.add([response[0].id_entrada, response[0].fecha, response[0].hora, response[0].descripcion, response[0].importe, '<a href="#" class=".remove" onclick="elimina_entrada(this,' + response[0].id_entrada + ',\'' + response[0].descripcion + '\')" title="Eliminar entrada" class=".remove"><img src="../img/icons/trash.svg"></a>']).draw(false);
                    },
                    error: function (response) {
                        console.log(response);
                        id_entrada = 0;
                    }
                });
            }
            function elimina_entrada(objeto, id_entrada, descripcion)
            {
                if (confirm("¿Desea eliminar el entrada " + descripcion + "?"))
                {
                    movimiento = 2;
                    //id_consecutivo = $("#id_consecutivo").val();
                    var parametros = {"movimiento": movimiento, "id_entrada": id_entrada};
                    $.ajax({
                        url: "../functions/edita_entradas.php",
                        data: parametros,
                        dataType: "json",
                        type: "POST",
                        success: function (response) {
                            var tabla = $('#entradas').DataTable();
                            tabla.row($(objeto).parents('tr')).remove().draw();
                        },
                        error: function (response) {
                            console.log(response);
                            id_entrada = 0;
                        }
                    });
                }
            }
        </script>      
    </body>
</html>

