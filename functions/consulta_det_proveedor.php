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
    $id_proveedor = trim($_POST["id_proveedor"]);
    
    $row_saldo = mysqli_fetch_assoc(mysqli_query($link, "SELECT efectivo_hoy FROM cc_saldos_proveedores where id_sucursal = $id_sucursal and id_proveedor = $id_proveedor"));

    $saldo = $row_saldo['efectivo_hoy'];
    $response_array[] = array(
        'saldo' => $saldo
    );
}
echo json_encode($response_array);
?>