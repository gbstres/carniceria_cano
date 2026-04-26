<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";

date_default_timezone_set("America/Mexico_City");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'run_batch')) {
    header('Content-Type: application/json; charset=utf-8');

    $_REQUEST['limit'] = 500;
    $cc_sync_emit_json_header = false;

    ob_start();
    require __DIR__ . "/../functions/run_sync_queue_processor.php";
    $raw = trim(ob_get_clean());

    $detalle = json_decode($raw, true);
    if (!is_array($detalle)) {
        echo json_encode([
            'ok' => false,
            'error' => 'No se pudo interpretar la respuesta del procesador.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Carniceria Cano">
    <meta name="author" content="Gerardo Bautista">
    <link rel="shortcut icon" href="../img/logo_1.png">
    <title>Respaldo</title>

    <script src="../js/jquery-3.5.1.js"></script>
    <script src="../js/jquery-ui.js"></script>
    <script src="../js/jquery.dataTables.min.js"></script>
    <script src="../js/sum().js"></script>
    <script src="../js/jquery.jeditable.js" type="text/javascript"></script>
    <script src="../js/jquery.dataTables.editable.js" type="text/javascript"></script>
    <script src="../js/jquery.validate.js" type="text/javascript"></script>
    <script src="../js/gijgo.min.js" type="text/javascript"></script>

    <style>
        @import "../css/bootstrap.css";
    </style>

    <link href="../css/navbar.css" rel="stylesheet">
    <link href="../css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="../css/gijgo.min.css" rel="stylesheet" type="text/css" />
</head>
<body>
<main>
    <div class="container">
        <?php require_once "../components/nav.php" ?>

        <div class="bg-light p-4 rounded">
            <div class="col-sm-8 mx-auto">
                <h1 class="text-center">Respaldo</h1>
            </div>

            <form class="row g-3 needs-validation" action="#" method="post" novalidate id="formRespaldo">
                <div class="col-12 text-center">
                    <input class="btn btn-primary black bg-silver" type="submit" value="Ejecutar respaldo manual" id="buscar_fecha">
                </div>
            </form>

            <br><br>

            <div id="mensajeRespaldo" class="alert d-none"></div>
        </div>
    </div>
</main>

<script src="../js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const form = document.getElementById('formRespaldo');
        const boton = document.getElementById('buscar_fecha');
        const mensaje = document.getElementById('mensajeRespaldo');

        function mostrarMensaje(texto, esError) {
            mensaje.textContent = texto;
            mensaje.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-info');
            mensaje.classList.add(esError ? 'alert-danger' : 'alert-success');
        }

        function mostrarProcesando(texto) {
            mensaje.textContent = texto;
            mensaje.classList.remove('d-none', 'alert-success', 'alert-danger');
            mensaje.classList.add('alert-info');
        }

        function ejecutarLote(acumulado) {
            $.ajax({
                url: 'respaldo.php',
                data: {action: 'run_batch'},
                dataType: 'json',
                type: 'POST',
                success: function (response) {
                    if (!response || response.ok !== true) {
                        boton.disabled = false;
                        boton.value = 'Ejecutar respaldo manual';
                        mostrarMensaje('No se pudo ejecutar el respaldo manual.', true);
                        return;
                    }

                    acumulado.processed += parseInt(response.processed || 0, 10);
                    acumulado.done += parseInt(response.done || 0, 10);
                    acumulado.failed += parseInt(response.failed || 0, 10);

                    if (parseInt(response.processed || 0, 10) > 0) {
                        mostrarProcesando('Procesando respaldo... Llevamos ' + acumulado.processed + ' registros.');
                        ejecutarLote(acumulado);
                        return;
                    }

                    boton.disabled = false;
                    boton.value = 'Ejecutar respaldo manual';
                    mostrarMensaje(
                        'Respaldo manual completado. Procesados: ' + acumulado.processed +
                        '. Correctos: ' + acumulado.done +
                        '. Errores: ' + acumulado.failed + '.',
                        acumulado.failed > 0
                    );
                },
                error: function () {
                    boton.disabled = false;
                    boton.value = 'Ejecutar respaldo manual';
                    mostrarMensaje('No se pudo ejecutar el respaldo manual.', true);
                }
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            boton.disabled = true;
            boton.value = 'Procesando...';
            mostrarProcesando('Iniciando respaldo...');

            ejecutarLote({
                processed: 0,
                done: 0,
                failed: 0
            });
        });
    })();
</script>
</body>
</html>
