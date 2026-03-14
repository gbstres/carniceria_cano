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
    $cek = mysqli_query($link, "SELECT id_empleado FROM cc_empleados where id_sucursal = '$id_sucursal' and CONCAT(id_empleado, ' ', nombre,' ',apellido_paterno,' ',apellido_materno) like '%$busqueda%'");
    if (mysqli_num_rows($cek) == 0) {
        $response_array['descripcion'] = 'No se encuentran clientes';
    } else {
        $sqlproductos = mysqli_query($link, "SELECT id_empleado, CONCAT(nombre,' ',apellido_paterno,' ',apellido_materno) as nombre FROM cc_empleados where id_sucursal = '$id_sucursal' and CONCAT(id_empleado, ' ', nombre,' ',apellido_paterno,' ',apellido_materno) like '%$busqueda%'");
        while ($rowproducto = mysqli_fetch_array($sqlproductos)) {
            $response_array[] = array(
                'id' => $rowproducto['id_empleado'],
                'name' => $rowproducto['nombre']
                    
            );
            //$response_array[] = $rowproducto['descripcion'];
        }
    }
}
echo json_encode($response_array);
?>