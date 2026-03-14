<?php

// Initialize the session
session_start();
// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
header('Content-type: application/json');
require_once "../functions/config.php";
$id_sucursal = $_SESSION["id_sucursal"];
$response_array = array();
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $busqueda = trim($_POST['busqueda']);
    $cek = mysqli_query($link, "SELECT id_proveedor FROM cc_proveedores where id_sucursal = '$id_sucursal' and nombre_proveedor like '%$busqueda%'");
    if (mysqli_num_rows($cek) == 0) {
        $response_array['descripcion'] = 'No se encuentran proveedores';
    } else {
        $sqlproveedores = mysqli_query($link, "SELECT id_proveedor,nombre_proveedor, credito FROM cc_proveedores where id_sucursal = '$id_sucursal' and nombre_proveedor like '%$busqueda%'");
        while ($rowproveedor = mysqli_fetch_array($sqlproveedores)) {
            $response_array[] = array(
                'id' => $rowproveedor['id_proveedor'],
                'name' => $rowproveedor['nombre_proveedor'],
                'credito' => $rowproveedor['credito']
            );
            //$response_array[] = $rowproveedor['descripcion'];
        }
    }
}
echo json_encode($response_array);
?>