<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";

date_default_timezone_set("America/Mexico_City");
header('Content-Type: text/html; charset=utf-8');

$mensaje = "";
$detalle = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_REQUEST['limit'] = 5000;
    $cc_sync_emit_json_header = false;

    ob_start();
    require __DIR__ . "/../functions/run_sync_queue_processor.php";
    $raw = trim(ob_get_clean());

    $detalle = json_decode($raw, true);

    if (is_array($detalle) && ($detalle['ok'] ?? false)) {
        $mensaje = "Respaldo manual completado. Procesados: "
            . (int) ($detalle['processed'] ?? 0)
            . ". Correctos: "
            . (int) ($detalle['done'] ?? 0)
            . ". Errores: "
            . (int) ($detalle['failed'] ?? 0)
            . ".";
    } else {
        $mensaje = "ERROR: No se pudo ejecutar el respaldo manual.";
    }
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

            <form class="row g-3 needs-validation" action="#" method="post" novalidate>
                <div class="col-12 text-center">
                    <input class="btn btn-primary black bg-silver" type="submit" value="Ejecutar respaldo manual" id="buscar_fecha">
                </div>
            </form>

            <br><br>

            <?php if (!empty($mensaje)): ?>
                <div class="alert <?php echo (strpos($mensaje, 'ERROR') === 0) ? 'alert-danger' : 'alert-success'; ?>">
                    <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</main>

<script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
