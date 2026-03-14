<?php
// control/respaldo.php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";    // $link (local) o constantes locales
require_once "../functions/config_2.php";  // $link2 + DB_*_REMOTE (sin modificar)
date_default_timezone_set("America/Mexico_City");

$id_sucursal = $_SESSION["id_sucursal"] ?? 0;

$fecha = $_POST['fecha'] ?? date('Y-m-d');

// Helpers
function hasColumn(mysqli $conn, string $table, string $col): bool
{
    $sql = "
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ";
    $st = mysqli_prepare($conn, $sql);
    if (!$st) return false;
    mysqli_stmt_bind_param($st, "ss", $table, $col);
    mysqli_stmt_execute($st);
    mysqli_stmt_store_result($st);
    $ok = (mysqli_stmt_num_rows($st) > 0);
    mysqli_stmt_close($st);
    return $ok;
}

function getColumns(mysqli $conn, string $table): array
{
    $cols = [];
    $rs = mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
    if (!$rs) return $cols;
    while ($r = mysqli_fetch_assoc($rs)) {
        $cols[] = $r['Field'];
    }
    mysqli_free_result($rs);
    return $cols;
}

function sqlValue(mysqli $conn, $val): string
{
    if ($val === null) return "NULL";
    return "'" . mysqli_real_escape_string($conn, (string)$val) . "'";
}

$mensaje = "";

if (isset($_POST['fecha'])) {

    // ✅ Lista blanca de tablas (RECOMENDADO).
    // Si lo dejas vacío, toma todas las tablas (pero solo procesa las que tengan fecha_ingreso/fecha_act).
    $tables = [
        // 'cc_clientes',
        // 'cc_ventas',
        // 'cc_ventas_det',
        // agrega aquí las tablas que sí quieres respaldar
    ];

    if (empty($tables)) {
        $tables = [];
        $rs = mysqli_query($link, "SHOW TABLES");
        if ($rs) {
            while ($r = mysqli_fetch_row($rs)) $tables[] = $r[0];
            mysqli_free_result($rs);
        }
    }

    // Validación simple de fecha (yyyy-mm-dd)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $mensaje = "Fecha inválida.";
    } else {

        // Transacción en remoto
        mysqli_begin_transaction($link2);
        try {

            // opcional: mejora si hay FK, pero úsalo con cuidado
            mysqli_query($link2, "SET FOREIGN_KEY_CHECKS=0");

            $totalFilas = 0;
            $totalTablas = 0;

            foreach ($tables as $table) {

                // solo tablas con fecha_ingreso/fecha_act
                $hasFI = hasColumn($link, $table, 'fecha_ingreso');
                $hasFA = hasColumn($link, $table, 'fecha_act');
                if (!$hasFI && !$hasFA) continue;

                $cols = getColumns($link, $table);
                if (empty($cols)) continue;

                // WHERE incremental
                $whereParts = [];
                $fechaEscLocal = mysqli_real_escape_string($link, $fecha);
                if ($hasFI) $whereParts[] = "fecha_ingreso = '$fechaEscLocal'";
                if ($hasFA) $whereParts[] = "fecha_act = '$fechaEscLocal'";
                $where = implode(" OR ", $whereParts);

                $sqlSel = "SELECT * FROM `$table` WHERE ($where)";
                $rs = mysqli_query($link, $sqlSel);
                if (!$rs) continue;

                // Pre-armado del upsert
                $colList = implode(",", array_map(fn($c) => "`$c`", $cols));
                $updateList = implode(",", array_map(fn($c) => "`$c`=VALUES(`$c`)", $cols));

                $filasTabla = 0;

                while ($row = mysqli_fetch_assoc($rs)) {

                    $vals = [];
                    foreach ($cols as $c) {
                        // escapamos con conexión REMOTA, porque allá se ejecuta
                        $vals[] = sqlValue($link2, $row[$c]);
                    }

                    $sqlUp = "
                        INSERT INTO `$table` ($colList)
                        VALUES (" . implode(",", $vals) . ")
                        ON DUPLICATE KEY UPDATE $updateList
                    ";

                    if (!mysqli_query($link2, $sqlUp)) {
                        throw new Exception("Error en tabla $table: " . mysqli_error($link2));
                    }

                    $filasTabla++;
                    $totalFilas++;
                }

                mysqli_free_result($rs);

                if ($filasTabla > 0) $totalTablas++;
            }

            mysqli_query($link2, "SET FOREIGN_KEY_CHECKS=1");
            mysqli_commit($link2);

            $mensaje = "Respaldo completado. Tablas afectadas: $totalTablas. Filas sincronizadas: $totalFilas.";

        } catch (Throwable $e) {
            mysqli_rollback($link2);
            $mensaje = "ERROR: " . $e->getMessage();
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
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance:none; margin:0; }
        input[type=number] { -moz-appearance:textfield; }
        td .form-control { text-transform: uppercase; }
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
                <div class="row g-3">
                    <div class="col-6">
                        <label for="Fecha" class="form-label">Seleccione la fecha:</label>
                        <input name="fecha" id="datepicker" width="276" autocomplete="off" readonly value="<?php echo htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?>"/>
                    </div>
                </div>
                <div class="col-12 text-center">
                    <input class="btn btn-primary black bg-silver" type="submit" value="Generar respaldo" id="buscar_fecha">
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
<script>
    $('#datepicker').datepicker({
        uiLibrary: 'bootstrap5',
        format: 'yyyy-mm-dd'
    });
</script>
</body>
</html>
