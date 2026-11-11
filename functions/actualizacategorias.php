<?php
// Initialize the session
session_start();
// Check if the user is logged in, if not then redirect him to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    http_response_code(401);
    exit('No autenticado');
}

require_once "../functions/config.php";
require_once "../functions/sync_queue.php";
require_once "../functions/bitacora_stock.php";

$id_categoria = (int) ($_POST['id'] ?? 0);
$value = trim((string) ($_POST['value'] ?? ''));
$columnName = (string) ($_POST['columnName'] ?? '');
$motivo = trim((string) ($_POST['motivo'] ?? ''));
date_default_timezone_set("America/Mexico_City");

if($_SERVER["REQUEST_METHOD"] == "POST"){
    if (!in_array($columnName, ['almacen', 'precio'], true) || $id_categoria <= 0) {
        http_response_code(400);
        exit('Solicitud no válida');
    }

    // Set parameters
    $id_sucursal = (int) ($_SESSION["id_sucursal"] ?? 0);      
    $id_usuario_act = (int) ($_SESSION['id'] ?? 0);
    $nombre_usuario = $_SESSION['nombre'] ?? ($_SESSION['username'] ?? '');
    $fecha_act = date('Y-m-d');
    $hora_act = date('H:i:s');

    $stockAnterior = null;
    $descCategoria = '';
    if ($columnName === 'almacen') {
        if ($motivo === '') {
            http_response_code(400);
            exit('El motivo o comentario del porqué se actualiza el stock es obligatorio.');
        }

        $resCat = mysqli_query($link, "SELECT desc_categoria, almacen FROM cc_categorias WHERE id_categoria='$id_categoria' AND id_sucursal='$id_sucursal' LIMIT 1");
        if ($resCat && $rowCat = mysqli_fetch_assoc($resCat)) {
            $stockAnterior = (float) $rowCat['almacen'];
            $descCategoria = (string) $rowCat['desc_categoria'];
        } else {
            $stockAnterior = 0.0;
            $descCategoria = 'Categoría #' . $id_categoria;
        }
    }

    if ($columnName === 'precio' && $value === '') {
        $valueSql = 'NULL';
    } elseif (!is_numeric($value) || (float) $value < 0) {
        http_response_code(400);
        exit('Valor no válido');
    } else {
        $valueSql = "'" . mysqli_real_escape_string($link, $value) . "'";
    }

    $update_producto = mysqli_query($link, "UPDATE cc_categorias SET "
            . "$columnName = $valueSql, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
            . "WHERE id_categoria='$id_categoria' and id_sucursal='$id_sucursal'");

    if($update_producto){
        if ($columnName === 'almacen' && $stockAnterior !== null) {
            $stockNuevo = (float) $value;
            if (abs($stockNuevo - $stockAnterior) > 0.0001) {
                registrarBitacoraStock(
                    $link,
                    $id_sucursal,
                    'CATEGORIA',
                    (string) $id_categoria,
                    $descCategoria,
                    $stockAnterior,
                    $stockNuevo,
                    $motivo,
                    $id_usuario_act,
                    $nombre_usuario,
                    'Mantenimiento Categorías (Tabla / En línea)'
                );
            }
        }

        cc_sync_enqueue($link, $id_sucursal, 'categoria', 'upsert', [
            'id_categoria' => (int) $id_categoria,
        ], [
            'tabla' => 'cc_categorias',
            'columna' => (string) $columnName,
        ]);
        echo $value;
    } else {
        http_response_code(500);
        echo 'Error, no se pudo actualizar: ' . mysqli_error($link);
    }
}
?>
