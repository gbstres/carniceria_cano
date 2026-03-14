<?php

// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
header('Content-type: application/json');
require_once "config.php";
$id_sucursal = $_SESSION["id_sucursal"];
$response_array = array();
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $busqueda = trim($_POST['busqueda']);
    $cek = mysqli_query($link, "SELECT * FROM cc_productos WHERE id_sucursal = '$id_sucursal' and (codigo like '%$busqueda%' or descripcion like '%$busqueda%')");
    if (mysqli_num_rows($cek) == 0) {
        $response_array['descripcion'] = 'No se encuentran productos';
    } else {
        $sqlproductos = mysqli_query($link, "SELECT * FROM cc_productos WHERE id_sucursal = '$id_sucursal' and (codigo like '%$busqueda%' or descripcion like '%$busqueda%')");
        while ($rowproducto = mysqli_fetch_array($sqlproductos)) {
            $response_array [] = array(
                'name' => $rowproducto['descripcion'],
                'id' => $rowproducto['codigo']
            );
            $id_categoria = $rowproducto['id_categoria'];
            $sqlcategoria = mysqli_fetch_assoc(mysqli_query($link, "SELECT desc_categoria FROM cc_categorias WHERE id_categoria = $id_categoria"));
        }
    }
}
echo json_encode($response_array);
?>