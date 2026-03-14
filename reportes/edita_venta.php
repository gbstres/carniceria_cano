<?php

// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../config.php";
date_default_timezone_set("America/Mexico_City");
// Define variables and initialize with empty values
$codigo = 0;
$id_sucursal = $_SESSION["id_sucursal"];
$descripcion = "";
$precio_compra = 0;
$precio_venta = 0;
$fecha_ingreso = date('y-m-d');
$hora_ingreso = date('H:i:s');
$id_usuario = $_SESSION["id"];
$body = "";
$title = "";
$id_venta = $_POST['id_venta'];
$id_cliente = $_POST['id_cliente'];

if ($_POST['movimiento'] == 1) {
    $codigo = trim($_POST["codigo"]);
    $id_consecutivo = intval(trim($_POST["id_consecutivo"]));
    $rowproducto = mysqli_fetch_assoc(mysqli_query($link, "SELECT precio_compra,descripcion FROM cc_productos WHERE id_sucursal = '$id_sucursal' and codigo = $codigo"));
    $precio_compra = $rowproducto['precio_compra'];
    $descripcion = $rowproducto['descripcion'];
    if ($id_venta == '' OR $id_venta == '0') {
        $rowventa = mysqli_fetch_assoc(mysqli_query($link, "SELECT max(id_venta) as id_venta FROM `cc_ventas` WHERE id_sucursal = '$id_sucursal'"));
        $id_venta = $rowventa['id_venta'];
        if ($id_venta == null) {
            $id_venta = 1;
        } else {
            $id_venta = $id_venta + 1;
        }
        $id_consecutivo = 1;
    } else {
        $rowventa = mysqli_fetch_assoc(mysqli_query($link, "SELECT max(id_consecutivo) as id_consecutivo FROM `cc_ventas` WHERE id_sucursal = '$id_sucursal' and id_venta = $id_venta"));
        $id_consecutivo = intval($rowventa['id_consecutivo']) + 1;
    }


    $sql = "INSERT INTO cc_ventas (id_sucursal, id_venta, id_consecutivo, codigo, precio_compra, precio_venta, cantidad, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "iiisdddiss", $id_sucursal, $id_venta, $id_consecutivo, $codigo, $precio_compra, $precio_venta, $cantidad, $id_usuario, $fecha_ingreso, $hora_ingreso);
        $precio_venta = trim($_POST["precio_venta"]);
        $cantidad = trim($_POST["cantidad"]);
        if (mysqli_stmt_execute($stmt)) {
            $sql1 = "SELECT * FROM cc_det_ventas where id_sucursal = '$id_sucursal' and id_venta = $id_venta";
            $result = mysqli_query($link, $sql1);
            $numero = mysqli_num_rows($result);
            if ($numero == 0) {
                $sql2 = "INSERT INTO cc_det_ventas (id_sucursal, id_venta, estatus, id_cliente, pagado, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($link, $sql2);
                mysqli_stmt_bind_param($stmt, "iiiiiiss", $id_sucursal, $id_venta, $estatus, $id_cliente, $pagado, $id_usuario, $fecha_ingreso, $hora_ingreso);
                $estatus = 0;
                $pagado = 0;
                mysqli_stmt_execute($stmt);
            }
            $response_array [] = array('id_venta' => $id_venta, 'id_consecutivo' => $id_consecutivo, 'descripcion' => $descripcion, 'id_cliente' => $id_cliente);
        } else {
            $response_array [] = array('id_venta' => 0, 'id_consecutivo' => 0, 'id_cliente' => 0);
        }
    }
    echo json_encode($response_array);
}

if ($_POST['movimiento'] == 2) {
    $id_consecutivo = trim($_POST["id_consecutivo"]);
    $delete = mysqli_query($link, "DELETE FROM cc_ventas WHERE id_sucursal = '$id_sucursal' and id_venta = $id_venta and id_consecutivo = $id_consecutivo");
    if ($delete) {
        $sql3 = "SELECT * FROM cc_ventas where id_sucursal = '$id_sucursal' and id_venta = $id_venta";
        $result = mysqli_query($link, $sql3);
        $numero = mysqli_num_rows($result);
        if ($numero == 0) {
            mysqli_query($link, "DELETE FROM cc_det_ventas WHERE id_sucursal = '$id_sucursal' and id_venta = $id_venta");
            $response_array [] = array('id_venta' => 0, 'id_cliente' => 0);
        } else {
            $response_array [] = array('id_venta' => $id_venta);
        }
    } else {
        
    }
    echo json_encode($response_array);
}
if ($_POST['movimiento'] == 3) {
    $delete = mysqli_query($link, "DELETE FROM cc_ventas WHERE id_sucursal = '$id_sucursal' and id_venta = $id_venta");
    if ($delete) {
        mysqli_query($link, "DELETE FROM cc_det_ventas WHERE id_sucursal = '$id_sucursal' and id_venta = $id_venta");
        $response_array [] = array('id_venta' => 0, 'id_consecutivo' => 0, 'id_cliente' => 0);
    } else {
        
    }
    echo json_encode($response_array);
}
//Cliente
if ($_POST['movimiento'] == 4) {
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('Y-m-d');
    $hora_act = date('H:i:s');
    $update1 = mysqli_query($link, "UPDATE cc_det_ventas SET "
                    . "id_cliente='$id_cliente', fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                    . "WHERE id_sucursal='$id_sucursal' and id_venta='$id_venta'")
            or die(mysqli_error());

    if ($update1) {
        $response_array [] = array('id_venta' => $id_venta);
    } else {
        echo '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>Error, no se pudo guardar el producto.</div>';
    }
    echo json_encode($response_array);
}
//Cierra venta
if ($_POST['movimiento'] == 5) {
    $id_empleado = $_POST['id_empleado'];
    $update1 = mysqli_query($link, "UPDATE cc_det_ventas SET estatus=1, id_empleado = $id_empleado WHERE id_sucursal='$id_sucursal' and id_venta='$id_venta'")
            or die(mysqli_error());

    if ($update1) {
        //header("Location: " . $_GET["regresar"]);
    } else {
        echo '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>Error, no se pudo guardar el producto.</div>';
    }
}