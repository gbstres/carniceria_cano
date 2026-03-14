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
    $cek = mysqli_query($link, "SELECT id_cliente FROM cc_clientes where id_sucursal = '$id_sucursal' and activo = 1 and CONCAT(id_cliente, ' ', nombre,' ',apellido_paterno,' ',apellido_materno) like '%$busqueda%'");
    if (mysqli_num_rows($cek) == 0) {
        $response_array['descripcion'] = 'No se encuentran clientes';
    } else {
        $sqlclientes = mysqli_query($link, "SELECT id_cliente, CONCAT(nombre,' ',apellido_paterno,' ',apellido_materno) as nombre, credito FROM cc_clientes where id_sucursal = '$id_sucursal' and activo = 1 and CONCAT(id_cliente, ' ', nombre,' ',apellido_paterno,' ',apellido_materno) like '%$busqueda%'");
        while ($rowcliente = mysqli_fetch_array($sqlclientes)) {
            $response_array[] = array(
                'id' => $rowcliente['id_cliente'],
                'name' => $rowcliente['nombre'],
                'credito' => $rowcliente['credito']
            );
            //$response_array[] = $rowcliente['descripcion'];
        }
    }
}
echo json_encode($response_array);
?>