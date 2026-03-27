<?php

// Initialize the session
session_start();
// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
header('Content-type: application/json');
require_once "config.php";
$id_sucursal = $_SESSION["id_sucursal"];
$response_array = array();
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_cliente = (int) trim($_POST["id_cliente"]);
    $saldo = 0;

    $result = mysqli_query($link, "SELECT efectivo_hoy FROM cc_saldos_clientes WHERE id_sucursal = $id_sucursal AND id_cliente = $id_cliente");
    if ($result) {
        $row_saldo = mysqli_fetch_assoc($result);
        if ($row_saldo && isset($row_saldo['efectivo_hoy'])) {
            $saldo = $row_saldo['efectivo_hoy'];
        }
        mysqli_free_result($result);
    }

    $response_array[] = array(
        'saldo' => $saldo
    );
}
echo json_encode($response_array);
?>
