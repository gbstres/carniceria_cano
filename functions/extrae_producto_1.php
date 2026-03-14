<?php

session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login.php");
    exit;
}
require_once "../functions/config.php";
header('Content-type: application/json');

$busqueda = trim($_POST['busqueda']);
$longitud = strlen($busqueda);
$codigo = encontrarPrimerEspacio($busqueda);
//$codigo = substr($busqueda, 0, 7);
$id_sucursal = $_SESSION["id_sucursal"];
if ($longitud == 13 && is_numeric($busqueda)) {
    $codigo = substr($busqueda, 0, 7);
    $peso = floatval(substr($busqueda, 7, 6));
    $peso = $peso / 10000;
} else if (is_numeric($codigo)) {
    $peso = 0;
} else {
    return;
}


$rowproducto = mysqli_fetch_assoc(mysqli_query($link, "SELECT codigo,descripcion,precio_venta FROM cc_productos WHERE id_sucursal = '$id_sucursal' and codigo = $codigo"));

$precio_venta = $rowproducto['precio_venta'];
$total = $precio_venta * $peso;

$response_array [] = array(
    'codigo' => $rowproducto['codigo'],
    'descripcion' => $rowproducto['descripcion'],
    'precio_venta' => $rowproducto['precio_venta'],
    'peso' => $peso,
    'total' => $total
);
// Fetch Associative array

echo json_encode($response_array);

function encontrarPrimerEspacio($cadena) {
    $indice = strpos($cadena, ' '); // Buscar el primer espacio en blanco

    if ($indice !== false) {
        $subcadena = substr($cadena, 0, $indice); // Obtener la subcadena antes del primer espacio
        return $subcadena;
    } else {
        return $cadena;
    }
}
