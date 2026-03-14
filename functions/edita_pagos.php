<?php

// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "config.php";
date_default_timezone_set("America/Mexico_City");
// Define variables and initialize with empty values
$id_sucursal = $_SESSION["id_sucursal"];
$fecha_ingreso = date('Y-m-d');
$hora_ingreso = date('H:i:s');
$id_usuario = $_SESSION["id"];
$id_cliente = $_POST['id_cliente'];

if ($_POST['movimiento'] == 1) {
$observaciones = mb_strtoupper($_POST['observaciones'], 'UTF-8');
$importe = $_POST['importe'];
$tipo_pago = $_POST['tipo_pago'];
    $rowpago = mysqli_fetch_assoc(mysqli_query($link, "SELECT max(id_pago) as id_pago FROM cc_pagos_clientes WHERE id_sucursal = '$id_sucursal' and id_cliente = $id_cliente"));
    $id_pago = $rowpago['id_pago'];
    if ($id_pago == null) {
        $id_pago = 1;
    } else {
        $id_pago = $id_pago + 1;
    }




    $sql = "INSERT INTO cc_pagos_clientes (id_sucursal, id_cliente, id_pago, importe, tipo_pago, observaciones, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "iiidisiss", $id_sucursal, $id_cliente, $id_pago, $importe, $tipo_pago, $observaciones, $id_usuario, $fecha_ingreso, $hora_ingreso);
        if (mysqli_stmt_execute($stmt)) {
            $usuario = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id  =" . $id_usuario));
            $desc_pago = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_claves where nombre_clave = 'TIPO_PAGO' and clave  =" . $tipo_pago));
            $abono = $importe * -1;
            $efectivo = recalcula($link, $id_sucursal, $abono, $id_cliente, $fecha_ingreso, $hora_ingreso, $id_usuario);
            $response_array [] = array('id_pago' => $id_pago, 'observaciones' => $observaciones, 'id_cliente' => $id_cliente, 'fecha' => $fecha_ingreso, 'hora' => $hora_ingreso, 'importe' => $importe, 'usuario' => $usuario['username'], 'efectivo' => $efectivo, 'desc_pago' => $desc_pago['descripcion_corta']);
        } else {
            $response_array [] = array('id_pago' => 0, 'id_cliente' => 0);
        }
    }
    echo json_encode($response_array);
}

if ($_POST['movimiento'] == 2) {
    $id_pago = trim($_POST["id_pago"]);
    $row_importe = mysqli_fetch_assoc(mysqli_query($link, "SELECT importe FROM cc_pagos_clientes WHERE id_sucursal = '$id_sucursal' and id_cliente = $id_cliente and id_pago = $id_pago"));
    $abono = $row_importe['importe'];
    $delete = mysqli_query($link, "DELETE FROM cc_pagos_clientes WHERE id_sucursal = '$id_sucursal' and id_cliente = $id_cliente and id_pago = $id_pago");
    $efectivo = recalcula($link, $id_sucursal, $abono, $id_cliente, $fecha_ingreso, $hora_ingreso, $id_usuario);
    if ($delete) {
        $response_array [] = array('id_venta' => $id_pago, 'id_cliente' => $id_cliente, 'efectivo' => $efectivo);
    } else {
        $response_array [] = array('id_venta' => 0, 'id_cliente' => 0);
    }
    echo json_encode($response_array);
}

//recalcula saldo cliente
function recalcula($link, $id_sucursal, $abono, $id_cliente, $fecha_act, $hora_act, $id_usuario_act)
{
    mysqli_query($link, "UPDATE cc_saldos_clientes SET efectivo_hoy = efectivo_hoy + $abono, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and id_cliente = $id_cliente");
    $row_efectivo = mysqli_fetch_assoc(mysqli_query($link, "select sum(efectivo_hoy) as 'efectivo_hoy' from cc_saldos_clientes where id_sucursal = $id_sucursal and id_cliente =" . $id_cliente));
    $efectivo = $row_efectivo['efectivo_hoy'];
    return round($efectivo, 2);
}
