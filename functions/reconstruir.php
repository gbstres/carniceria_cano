<?php

// Initialize the session
session_start();
// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    exit;
}

require_once "../functions/config.php";
$id_cliente = $_POST['id_cliente'];
date_default_timezone_set("America/Mexico_City");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Set parameters
    $id_sucursal = $_SESSION["id_sucursal"];
    $saldo = saldo($link, $id_sucursal, $id_cliente);
    $id_sucursal = $_SESSION["id_sucursal"];
    $id_usuario_act = $id_usuario = $_SESSION['id'];
    $fecha_act = $fecha_ingreso = date('y-m-d');
    $hora_act = $hora_ingreso = date('H:i:s');

    $sql1 = "SELECT * FROM cc_saldos_clientes where id_sucursal = '$id_sucursal' and id_cliente = $id_cliente";
    $result = mysqli_query($link, $sql1);
    $numero = mysqli_num_rows($result);
    if ($numero == 0) {
        $efectivo_hoy = $efectivo_ayer = $efectivo_mes = 0;
        $sql2 = "INSERT INTO cc_saldos_clientes (id_sucursal, id_cliente, efectivo_hoy, efectivo_ayer, efectivo_mes, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($link, $sql2);
        mysqli_stmt_bind_param($stmt, "iidddiss", $id_sucursal, $id_cliente, $efectivo_hoy, $efectivo_ayer, $efectivo_mes, $id_usuario, $fecha_ingreso, $hora_ingreso);
        $estatus = 0;
        $pagado = 0;
        mysqli_stmt_execute($stmt);
    }

    $update_producto = mysqli_query($link, "UPDATE cc_saldos_clientes SET "
                    . "efectivo_hoy = '$saldo', fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                    . "WHERE id_sucursal='$id_sucursal' and id_cliente = $id_cliente")
            or die(mysqli_error());
    if ($update_producto) {
        $response_array [] = array('saldo' => $saldo);
    } else {
        $response_array [] = array('saldo' => 0);
    }
    echo json_encode($response_array);
}

function saldo($link, $id_sucursal, $id_cliente) {
    $v_cliente = mysqli_fetch_assoc(mysqli_query($link, "select round(sum(b.cantidad * b.precio_venta),2) importe from cc_det_ventas as a left join cc_ventas as b on a.id_sucursal = b.id_sucursal and a.id_venta = b.id_venta where a.id_sucursal = $id_sucursal and id_cliente = $id_cliente and a.estatus in (1,3) and b.estatus in (0)"));
    $p_cliente = mysqli_fetch_assoc(mysqli_query($link, "SELECT sum(importe) importe FROM cc_pagos_clientes where id_sucursal = $id_sucursal and id_cliente = $id_cliente and estatus in (0,3)"));
    $ve_cliente = $pa_cliente = 0;
    if ($v_cliente['importe'] != null) {
        $ve_cliente = floatval($v_cliente['importe']);
    }
    if ($p_cliente['importe'] != null) {
        $pa_cliente = floatval($p_cliente['importe']);
    }
    return round($ve_cliente - $pa_cliente, 2);
}

?>