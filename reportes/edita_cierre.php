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
$fecha_ingreso = date('y-m-d');
$hora_ingreso = date('H:i:s');
$id_usuario = $_SESSION["id"];


if ($_POST['movimiento'] == 1) {
$codigo = $_POST['codigo'];
$descripcion = strtoupper($_POST['descripcion']);
$precio = $_POST['precio'];
$cantidad = $_POST['cantidad'];
$comentario = strtoupper($_POST['comentario']);
    $rowgasto = mysqli_fetch_assoc(mysqli_query($link, "SELECT max(id_gasto) as id_gasto FROM cc_gastos WHERE id_sucursal = '$id_sucursal'"));
    $id_gasto = $rowgasto['id_gasto'];
    if ($id_gasto == null) {
        $id_gasto = 1;
    } else {
        $id_gasto = $id_gasto + 1;
    }

    $sql = "INSERT INTO cc_gastos (id_sucursal, id_gasto, codigo, descripcion, precio, cantidad, comentario, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "iissddsiss", $id_sucursal, $id_gasto, $codigo, $descripcion, $precio, $cantidad, $comentario, $id_usuario, $fecha_ingreso, $hora_ingreso);
        if (mysqli_stmt_execute($stmt)) {
            $importe = round($cantidad * $precio, 2);
            $usuario = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id  =" . $id_usuario));
            $response_array [] = array('id_gasto' => $id_gasto, 'codigo' => $codigo, 'descripcion' => $descripcion, 'precio' => $precio, 'cantidad' => $cantidad, 'comentario' => $comentario, 'fecha' => $fecha_ingreso, 'hora' => $hora_ingreso, 'importe' => $importe, 'usuario' => $usuario['username']);
        } else {
            $response_array [] = array('id_gasto' => 0);
        }
    }
    echo json_encode($response_array);
}

if ($_POST['movimiento'] == 2) {
    $id_gasto = trim($_POST["id_gasto"]);
    $delete = mysqli_query($link, "DELETE FROM cc_gastos WHERE id_sucursal = '$id_sucursal' and id_gasto = $id_gasto");
    if ($delete) {
        $response_array [] = array('id_gasto' => $id_gasto);
    } else {
        $response_array [] = array('id_gasto' => 0);
    }
    echo json_encode($response_array);
}
