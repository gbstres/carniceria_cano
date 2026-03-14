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
$id_sucursal = $_SESSION["id_sucursal"];
$fecha_ingreso = date('y-m-d');
$hora_ingreso = date('H:i:s');
$id_usuario = $_SESSION["id"];
$codigo_p = $_POST['codigo_p'];
$codigo_d = $_POST['codigo_d'];
$porcentaje = trim($_POST['porcentaje']);

$response_array = [];

if ($_POST['movimiento'] == 1) {
    $sql = "INSERT INTO cc_derivados (id_sucursal, codigo_p, codigo_d, porcentaje, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?)";  
    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "iiidiss", $id_sucursal, $codigo_p, $codigo_d, $porcentaje, $id_usuario, $fecha_ingreso, $hora_ingreso);
        if (mysqli_stmt_execute($stmt)) {
            $response_array [] = array('exito' => 1);
        } else {
            $response_array [] = array('exito' => 0);
        }
    }
    echo json_encode($response_array);
}

if ($_POST['movimiento'] == 2) {
    $delete = mysqli_query($link, "DELETE FROM cc_derivados WHERE id_sucursal = '$id_sucursal' and codigo_p = $codigo_p");
    if ($delete) {
        $response_array [] = array('exito' => 1);
    } else {
        $response_array [] = array('exito' => 0);
    }
    echo json_encode($response_array);
}
