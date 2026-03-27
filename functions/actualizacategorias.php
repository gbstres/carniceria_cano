<?php
// Initialize the session
session_start();
// Check if the user is logged in, if not then redirect him to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    exit;
}

require_once "../functions/config.php";
require_once "../functions/sync_queue.php";
$id_categoria = $_POST['id'];
$value = mb_strtoupper($_POST['value']);
$columnName = $_POST['columnName'];
date_default_timezone_set("America/Mexico_City");
	if($_SERVER["REQUEST_METHOD"] == "POST"){
                    // Set parameters
                    $id_sucursal = $_SESSION["id_sucursal"];      
                    $id_usuario_act = $_SESSION['id'];
                    $fecha_act= date('y-m-d');
                    $hora_act = date('H:i:s');
                    $update_producto = mysqli_query($link, "UPDATE cc_categorias SET "
                            . "$columnName = '$value', fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                            . "WHERE id_categoria='$id_categoria' and id_sucursal='$id_sucursal'") 
                            or die(mysqli_error());
                    if($update_producto){
                        cc_sync_enqueue($link, $id_sucursal, 'categoria', 'upsert', [
                            'id_categoria' => (int) $id_categoria,
                        ], [
                            'tabla' => 'cc_categorias',
                            'columna' => (string) $columnName,
                        ]);
			echo trim($value);
                    }else{
			echo 'Error, no se pudo actualizar ';
                    }
	}
?>
