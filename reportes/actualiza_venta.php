<?php

// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    exit;
}
require_once "../functions/config.php";

$cadena = $_POST['id'];
$value = mb_strtoupper($_POST['value']);
$columnName = $_POST['columnName'];

$separada = explode(',', $cadena);
$id_venta = $separada[0];
$id_consecutivo = $separada[1];

date_default_timezone_set("America/Mexico_City");
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Set parameters
    $id_sucursal = $_SESSION["id_sucursal"];
    $id_usuario_act = $_SESSION['id'];
    $fecha_act = date('Y-m-d');
    $hora_act = date('H:i:s');
    $update_venta = mysqli_query($link, "UPDATE cc_ventas SET "
                    . "$columnName = '$value', fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                    . "WHERE id_sucursal='$id_sucursal' and id_venta='$id_venta' and id_consecutivo = '$id_consecutivo'")
            or die(mysqli_error());
    if ($update_venta) {
        echo $value;
    } else {
        echo 'Error, no se pudo actualizar ';
    }
}


// recalcula saldo cliente
function recalcula($link, $id_sucursal, $id_venta, $id_cliente, $fecha_act, $hora_act, $id_usuario_act) {
    $row_saldo = mysqli_fetch_assoc(mysqli_query($link, "SELECT sum(cantidad * precio_venta) as 'saldo' FROM `cc_ventas` WHERE id_sucursal = '$id_sucursal' and id_venta = $id_venta"));
    $saldo_inicial = $row_saldo['saldo'];
    mysqli_query($link, "UPDATE cc_saldos_clientes SET efectivo_hoy = efectivo_hoy + $saldo, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and id_cliente = $id_cliente");
}


?>