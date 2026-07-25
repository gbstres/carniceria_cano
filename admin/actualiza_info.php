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
$alertMessage = "";
$alertType = "info";

if (isset($_POST['fecha'])) {
    $fecha = $_POST['fecha'];
} else {
    $fecha = date('Y-m-d');
}

$idSucursalOrigen = isset($_POST['id_sucursal_origen'])
    ? (int) $_POST['id_sucursal_origen']
    : (int) $id_sucursal;
$forzarCatalogo = isset($_POST['forzar_catalogo']) && (string) $_POST['forzar_catalogo'] === '1';

$sucursalesOrigen = [];
$rsSucursalesOrigen = mysqli_query($link2, "SELECT id_sucursal, desc_sucursal FROM cc_sucursales ORDER BY desc_sucursal");
if ($rsSucursalesOrigen) {
    while ($rowSucursalOrigen = mysqli_fetch_assoc($rsSucursalesOrigen)) {
        $sucursalesOrigen[(int) $rowSucursalOrigen['id_sucursal']] = $rowSucursalOrigen['desc_sucursal'];
    }
    mysqli_free_result($rsSucursalesOrigen);
}
if (empty($sucursalesOrigen)) {
    $alertType = "danger";
    $alertMessage = "No fue posible obtener las sucursales desde GCP.";
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // valida fecha
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $alertType = "danger";
        $alertMessage = "Fecha inválida.";
    } elseif ($idSucursalOrigen <= 0 || !array_key_exists($idSucursalOrigen, $sucursalesOrigen)) {
        $alertType = "danger";
        $alertMessage = "La sucursal de origen seleccionada no es válida.";
    } elseif (!$forzarCatalogo && (!isset($_POST['check']) || !is_array($_POST['check']))) {
        $alertType = "warning";
        $alertMessage = "No se marcó ningún checkbox.";
    } else {
        $tablasSeleccionadas = isset($_POST['check']) && is_array($_POST['check']) ? $_POST['check'] : [];

        $tablasCatalogo = ['cc_categorias', 'cc_productos', 'cc_derivados', 'cc_equivalencias_productos'];

        if ($forzarCatalogo) {
            $tablasCatalogoSql = "'" . implode("','", array_map(fn($t) => mysqli_real_escape_string($link, $t), $tablasCatalogo)) . "'";
            $rsCatalogo = mysqli_query($link, "SELECT id FROM cc_tablas_respaldo WHERE nombre_tabla IN ($tablasCatalogoSql)");
            while ($rowCatalogo = mysqli_fetch_assoc($rsCatalogo)) {
                $tablasSeleccionadas[(int) $rowCatalogo['id']] = 1;
            }
            if ($rsCatalogo) {
                mysqli_free_result($rsCatalogo);
            }
        }

        // Transacción en LOCAL (porque estás escribiendo en local)
        mysqli_begin_transaction($link);
        try {

            // opcional
            mysqli_query($link, "SET FOREIGN_KEY_CHECKS=0");

            foreach ($tablasSeleccionadas as $id => $value) {

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

            $forzarTablaCatalogo = $forzarCatalogo && in_array($tabla, $tablasCatalogo, true);

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

            $columnasConfiguradas = !$forzarCatalogo
                && isset($_POST['columnas_configuradas'][$id])
                && (string) $_POST['columnas_configuradas'][$id] === '1';
            $columnasActualizar = array_values(array_diff($columnas, $llaves));
            if ($columnasConfiguradas) {
                $columnasSolicitadas = isset($_POST['columnas'][$id]) && is_array($_POST['columnas'][$id])
                    ? array_map('strval', $_POST['columnas'][$id])
                    : [];
                $columnasActualizar = array_values(array_intersect($columnasActualizar, $columnasSolicitadas));
            }
            if ($columnasConfiguradas && empty($columnasActualizar)) {
                throw new Exception("Seleccione al menos una columna para actualizar en $tabla.");
            }

            $fechaEsc = mysqli_real_escape_string($link2, $fecha);
            $idSuc = $idSucursalOrigen;

            if ($forzarTablaCatalogo || $columnasConfiguradas) {
                $cad1 = "SELECT * FROM `$tabla`
                         WHERE id_sucursal = $idSuc";
            } else {
                // Si tu tabla NO tiene id_sucursal, este WHERE fallará.
                // (Si algunas no lo tienen, hay que detectarlo con hasColumn.)
                $cad1 = "SELECT * FROM `$tabla`
                         WHERE id_sucursal = $idSuc
                           AND (fecha_ingreso = '$fechaEsc' OR fecha_act = '$fechaEsc')";
            }

            $sqltabla = mysqli_query($link2, $cad1);
            if (!$sqltabla) continue;

            // UPSERT armado
            $colList = implode(",", array_map(fn($c) => "`$c`", $columnas));
            $updateList = implode(",", array_map(fn($c) => "`$c`=VALUES(`$c`)", $columnasActualizar));

            while ($rows = mysqli_fetch_assoc($sqltabla)) {

                $vals = [];
                foreach ($columnas as $c) {
                    $v = $rows[$c];
                    if (in_array($c, ['fecha_ingreso', 'fecha_act'], true) && ($v === '0000-00-00' || $v === null || $v === '')) {
                        $v = date('Y-m-d');
                    }
                    if (in_array($c, ['hora_ingreso', 'hora_act'], true) && ($v === '00:00:00' || $v === null || $v === '')) {
                        $v = date('H:i:s');
                    }
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

            $alertType = "success";
            $nombreSucursalOrigen = $sucursalesOrigen[$idSucursalOrigen] ?? ('Sucursal ' . $idSucursalOrigen);
            $alertMessage = "Recuperación completada correctamente desde " . $nombreSucursalOrigen . ".";

        } catch (Throwable $e) {
            mysqli_rollback($link);
            mysqli_query($link, "SET FOREIGN_KEY_CHECKS=1");
            $alertType = "danger";
            $alertMessage = "ERROR: " . $e->getMessage();
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
            .btn-columnas {
                line-height: 1;
                padding: .3rem .5rem;
            }
            .btn-columnas:disabled {
                cursor: not-allowed;
                opacity: .4;
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
                        <?php if ($alertMessage !== "") { ?>
                            <div class="alert alert-<?php echo htmlspecialchars($alertType, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8'); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } ?>
                        <form class="row g-3 needs-validation" action="#" method="post" novalidate>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="Fecha" class="form-label">Seleccione la fecha:</label>
                                    <input name="fecha" id="datepicker" width="276" autocomplete="off" readonly="" value="<?php echo htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?>"/>
                                </div>
                                <div class="col-6">
                                    <label for="id_sucursal_origen" class="form-label">Sucursal origen:</label>
                                    <select class="form-select" name="id_sucursal_origen" id="id_sucursal_origen" required>
                                        <?php foreach ($sucursalesOrigen as $idSucursalOpcion => $nombreSucursalOpcion) { ?>
                                            <option value="<?php echo $idSucursalOpcion; ?>" <?php echo $idSucursalOpcion === $idSucursalOrigen ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($nombreSucursalOpcion, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                    <div class="form-text">La información se consultará en GCP para esta sucursal.</div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="1" id="forzar_catalogo" name="forzar_catalogo">
                                        <label class="form-check-label" for="forzar_catalogo">
                                            Forzar actualización completa de categorías, productos, derivados y equivalencias sin filtrar por fecha
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 text-center" >
                                <input class="btn btn-primary black bg-silver" type="submit" value="Extrae información desde GCP" id="extrae" <?php echo empty($sucursalesOrigen) ? 'disabled' : ''; ?>>
                            </div>
                            <br>
                            <div class="table-responsive">
                                <table id="tablas" class="display" style="width:50%" >
                                    <thead>
                                        <tr>
                                            <th>Id</th>
                                            <th>Tabla</th>
                                            <th>Seleccionar</th>
                                            <th>Columnas</th>
                                        </tr>
                                    </thead>
                                    <?php
                                    $columnasPorTabla = [];
                                    $sqltablas = mysqli_query($link, "SELECT id,nombre_comun,nombre_tabla "
                                            . "FROM cc_tablas_respaldo as a "
                                            . " order by a.secuencia");

                                    $renglon = 0;
                                    while ($rowc = mysqli_fetch_assoc($sqltablas)) {
                                        $renglon = $renglon + 1;
                                        $idTabla = (int) $rowc['id'];
                                        $nombreTabla = $rowc['nombre_tabla'];
                                        $columnasPorTabla[$idTabla] = [];
                                        if (preg_match('/^[a-zA-Z0-9_]+$/', $nombreTabla)) {
                                            $rsColumnas = mysqli_query($link, "SHOW COLUMNS FROM `$nombreTabla`");
                                            while ($rsColumnas && $columna = mysqli_fetch_assoc($rsColumnas)) {
                                                $columnasPorTabla[$idTabla][] = [
                                                    'nombre' => $columna['Field'],
                                                    'primaria' => $columna['Key'] === 'PRI'
                                                ];
                                            }
                                            if ($rsColumnas) {
                                                mysqli_free_result($rsColumnas);
                                            }
                                        }
                                        echo '
                                    <tr id="' . $idTabla . '">
                                        <td>' . $idTabla . '</td>
                                        <td>' . htmlspecialchars($rowc['nombre_comun'], ENT_QUOTES, 'UTF-8') . '</td>
                                        <td><input type="checkbox" name="check[' . $idTabla . ']" value="1" class="form-check-input check-tabla" data-tabla-id="' . $idTabla . '"></td>
                                        <td>
                                            <button type="button" class="btn btn-outline-primary btn-sm btn-columnas" data-tabla-id="' . $idTabla . '" title="Seleccionar columnas" aria-label="Seleccionar columnas" disabled>
                                                &#9776;
                                            </button>
                                        </td>
                                        </tr>
                                        ';
                                    }
                                    ?> 
                                </table> 
                            </div>
                        </form>

                        <div class="modal fade" id="modal_columnas" tabindex="-1" aria-labelledby="modal_columnas_titulo" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="modal_columnas_titulo">Columnas a actualizar</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="text-muted small">Las columnas seleccionadas se actualizarán para todos los registros de la sucursal origen, sin filtrar por fecha. Las llaves primarias se utilizan para localizar los registros y no pueden modificarse.</p>
                                        <div class="form-check border-bottom pb-2 mb-3">
                                            <input class="form-check-input" type="checkbox" id="seleccionar_todas_columnas">
                                            <label class="form-check-label fw-bold" for="seleccionar_todas_columnas">
                                                Seleccionar/quitar todas
                                            </label>
                                        </div>
                                        <div id="lista_columnas"></div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-primary" id="guardar_columnas" data-bs-dismiss="modal">Aceptar</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
            const columnasPorTabla = <?php echo json_encode($columnasPorTabla, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            const seleccionColumnas = {};
            const columnasConfiguradas = {};
            let tablaColumnasActiva = null;

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

                function actualizarIconosColumnas() {
                    const forzar = $('#forzar_catalogo').is(':checked');
                    $('.btn-columnas').each(function () {
                        const id = String($(this).data('tabla-id'));
                        const seleccionada = $('.check-tabla[data-tabla-id="' + id + '"]').is(':checked');
                        $(this).prop('disabled', forzar || !seleccionada);
                    });
                }

                $('#tablas').on('change', '.check-tabla', actualizarIconosColumnas);
                $('#forzar_catalogo').on('change', actualizarIconosColumnas);
                $('#tablas').on('draw.dt', actualizarIconosColumnas);

                $('#tablas').on('click', '.btn-columnas', function () {
                    tablaColumnasActiva = String($(this).data('tabla-id'));
                    const columnas = columnasPorTabla[tablaColumnasActiva] || [];
                    if (!seleccionColumnas[tablaColumnasActiva]) {
                        seleccionColumnas[tablaColumnasActiva] = columnas
                            .filter(columna => !columna.primaria)
                            .map(columna => columna.nombre);
                    }

                    const seleccionadas = seleccionColumnas[tablaColumnasActiva];
                    $('#lista_columnas').empty();
                    columnas.forEach(function (columna) {
                        const idControl = 'columna_' + tablaColumnasActiva + '_' + columna.nombre;
                        const checked = columna.primaria || seleccionadas.includes(columna.nombre);
                        const textoPrimaria = columna.primaria ? ' <span class="badge bg-secondary">Llave primaria</span>' : '';
                        $('#lista_columnas').append(
                            '<div class="form-check mb-2">' +
                            '<input class="form-check-input columna-opcion" type="checkbox" value="' +
                            $('<div>').text(columna.nombre).html() + '" id="' + idControl + '"' +
                            (checked ? ' checked' : '') + (columna.primaria ? ' disabled' : '') + '>' +
                            '<label class="form-check-label" for="' + idControl + '">' +
                            $('<div>').text(columna.nombre).html() + textoPrimaria + '</label></div>'
                        );
                    });
                    actualizarSeleccionTodasColumnas();
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modal_columnas')).show();
                });

                function actualizarSeleccionTodasColumnas() {
                    const opciones = $('#lista_columnas .columna-opcion:not(:disabled)');
                    const seleccionadas = opciones.filter(':checked').length;
                    $('#seleccionar_todas_columnas')
                        .prop('checked', opciones.length > 0 && seleccionadas === opciones.length)
                        .prop('indeterminate', seleccionadas > 0 && seleccionadas < opciones.length)
                        .prop('disabled', opciones.length === 0);
                }

                $('#seleccionar_todas_columnas').on('change', function () {
                    $('#lista_columnas .columna-opcion:not(:disabled)').prop('checked', this.checked);
                    actualizarSeleccionTodasColumnas();
                });

                $('#lista_columnas').on('change', '.columna-opcion', actualizarSeleccionTodasColumnas);

                $('#guardar_columnas').on('click', function () {
                    if (tablaColumnasActiva === null) return;
                    seleccionColumnas[tablaColumnasActiva] = $('#lista_columnas .columna-opcion:not(:disabled):checked')
                        .map(function () { return this.value; }).get();
                    columnasConfiguradas[tablaColumnasActiva] = true;
                });

                actualizarIconosColumnas();
            })
            $('#datepicker').datepicker({
                uiLibrary: 'bootstrap5',
                format: 'yyyy-mm-dd'
            });

            $('form').on('submit', function (event) {
                if ($('#forzar_catalogo').is(':checked')) {
                    var mensaje = 'Advertencia: se actualizará la información completa desde GCP para categorías, productos, derivados y equivalencias, sin filtrar por fecha. ¿Desea continuar?';
                    if (!confirm(mensaje)) {
                        event.preventDefault();
                        event.stopPropagation();
                        return false;
                    }
                } else {
                    $(this).find('input.columna-seleccionada').remove();
                    let columnasValidas = true;
                    $(this).find('.check-tabla:checked').each(function () {
                        const id = String($(this).data('tabla-id'));
                        if (!columnasConfiguradas[id]) {
                            return;
                        }
                        const columnas = seleccionColumnas[id] || (columnasPorTabla[id] || [])
                            .filter(columna => !columna.primaria)
                            .map(columna => columna.nombre);
                        if (columnas.length === 0) {
                            columnasValidas = false;
                            return false;
                        }
                        columnas.forEach(columna => {
                            $('<input>', {
                                type: 'hidden',
                                class: 'columna-seleccionada',
                                name: 'columnas[' + id + '][]',
                                value: columna
                            }).appendTo(this.form);
                        });
                        $('<input>', {
                            type: 'hidden',
                            class: 'columna-seleccionada',
                            name: 'columnas_configuradas[' + id + ']',
                            value: '1'
                        }).appendTo(this.form);
                    });
                    if (!columnasValidas) {
                        event.preventDefault();
                        alert('Seleccione al menos una columna para cada tabla marcada.');
                        return false;
                    }
                }
            });

        </script>      
    </body>
</html>
