<?php

// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
header('Content-type: application/json; charset=utf-8');
require_once "config.php";
$id_sucursal = (int) $_SESSION["id_sucursal"];
$response_array = array();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $busqueda = trim($_POST['busqueda'] ?? '');
    $busquedaEsc = mysqli_real_escape_string($link, $busqueda);

    // Si se especifica 'todos=1' (por ejemplo para configurar recetas o inventario), incluye insumos (para_venta = 0)
    // De lo contrario (ej. punto de venta/caja), solo muestra productos marcados con para_venta = 1
    $filtroVenta = " AND para_venta = 1 ";
    if (isset($_POST['todos']) && (int) $_POST['todos'] === 1) {
        $filtroVenta = "";
    } elseif (isset($_POST['solo_insumos']) && (int) $_POST['solo_insumos'] === 1) {
        $filtroVenta = " AND para_venta = 0 ";
    }

    $sql = "SELECT codigo, descripcion, precio_venta, almacen, id_categoria, para_venta 
            FROM cc_productos 
            WHERE id_sucursal = '$id_sucursal' 
              AND (codigo LIKE '%$busquedaEsc%' OR descripcion LIKE '%$busquedaEsc%')
              $filtroVenta
            ORDER BY descripcion ASC 
            LIMIT 50";

    $sqlproductos = mysqli_query($link, $sql);
    if (!$sqlproductos || mysqli_num_rows($sqlproductos) == 0) {
        $response_array['descripcion'] = 'No se encuentran productos';
    } else {
        while ($rowproducto = mysqli_fetch_assoc($sqlproductos)) {
            $response_array[] = array(
                'name' => $rowproducto['descripcion'],
                'id' => $rowproducto['codigo'],
                'precio' => (float) $rowproducto['precio_venta'],
                'stock' => (float) $rowproducto['almacen'],
                'id_categoria' => (int) $rowproducto['id_categoria'],
                'para_venta' => (int) $rowproducto['para_venta']
            );
        }
    }
}
echo json_encode($response_array);
