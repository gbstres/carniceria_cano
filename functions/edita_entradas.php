<?php

// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";
require_once "../functions/sync_queue.php";
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
    $rowentrada = mysqli_fetch_assoc(mysqli_query($link, "SELECT max(id_entrada) as id_entrada FROM cc_entradas WHERE id_sucursal = '$id_sucursal'"));
    $id_entrada = $rowentrada['id_entrada'];
    if ($id_entrada == null) {
        $id_entrada = 1;
    } else {
        $id_entrada = $id_entrada + 1;
    }

    $sql = "INSERT INTO cc_entradas (id_sucursal, id_entrada, codigo, descripcion, precio, cantidad, comentario, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "iissddsiss", $id_sucursal, $id_entrada, $codigo, $descripcion, $precio, $cantidad, $comentario, $id_usuario, $fecha_ingreso, $hora_ingreso);
        if (mysqli_stmt_execute($stmt)) {
            $importe = round($cantidad * $precio, 2);
            $usuario = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id  =" . $id_usuario));
            cc_sync_enqueue($link, $id_sucursal, 'entrada', 'upsert', [
                'id_entrada' => (int) $id_entrada,
            ], [
                'tabla' => 'cc_entradas',
                'codigo' => (string) $codigo,
            ]);
            $response_array [] = array('id_entrada' => $id_entrada, 'codigo' => $codigo, 'descripcion' => $descripcion, 'precio' => $precio, 'cantidad' => $cantidad, 'comentario' => $comentario, 'fecha' => $fecha_ingreso, 'hora' => $hora_ingreso, 'importe' => $importe, 'usuario' => $usuario['username']);
        } else {
            $response_array [] = array('id_entrada' => 0);
        }
    }
    echo json_encode($response_array);
}

if ($_POST['movimiento'] == 2) {
    $id_entrada = trim($_POST["id_entrada"]);
    $delete = mysqli_query($link, "DELETE FROM cc_entradas WHERE id_sucursal = '$id_sucursal' and id_entrada = $id_entrada");
    if ($delete) {
        cc_sync_enqueue($link, $id_sucursal, 'entrada', 'delete', [
            'id_entrada' => (int) $id_entrada,
        ], [
            'tabla' => 'cc_entradas',
        ]);
        $response_array [] = array('id_entrada' => $id_entrada);
    } else {
        $response_array [] = array('id_entrada' => 0);
    }
    echo json_encode($response_array);
}
