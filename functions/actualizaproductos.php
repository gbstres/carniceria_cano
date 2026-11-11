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

$codigo = $_POST['id'] ?? '';
$value = mb_strtoupper(trim($_POST['value'] ?? ''));
$columnName = trim($_POST['columnName'] ?? '');
$motivo = trim($_POST['motivo'] ?? '');
date_default_timezone_set("America/Mexico_City");

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $id_sucursal = (int) ($_SESSION["id_sucursal"] ?? 0);      
    $id_usuario_act = (int) ($_SESSION['id'] ?? 0);
    $nombre_usuario = $_SESSION['nombre'] ?? ($_SESSION['username'] ?? '');
    $fecha_act = date('Y-m-d');
    $hora_act = date('H:i:s');

    $codigoActualizacion = (string) $codigo;
    $codigoActualizacionSql = mysqli_real_escape_string($link, $codigoActualizacion);

    // Si se actualiza el stock (almacen), es obligatorio el motivo/comentario
    $stockAnterior = null;
    $descProducto = '';
    if ($columnName === 'almacen') {
        if ($motivo === '') {
            http_response_code(400);
            exit('El motivo o comentario del porqué se actualiza el stock es obligatorio.');
        }

        $resProd = mysqli_query($link, "SELECT descripcion, almacen FROM cc_productos WHERE codigo='$codigoActualizacionSql' AND id_sucursal='$id_sucursal' LIMIT 1");
        if ($resProd && $rowProd = mysqli_fetch_assoc($resProd)) {
            $stockAnterior = (float) $rowProd['almacen'];
            $descProducto = (string) $rowProd['descripcion'];
        } else {
            $stockAnterior = 0.0;
            $descProducto = $codigoActualizacion;
        }
    }

    $valueSql = mysqli_real_escape_string($link, $value);
    $columnNameSql = mysqli_real_escape_string($link, $columnName);

    $update_producto = mysqli_query($link, "UPDATE cc_productos SET "
            . "$columnNameSql = '$valueSql', fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
            . "WHERE codigo='$codigoActualizacionSql' and id_sucursal='$id_sucursal'");

    if($update_producto){
        // Si fue cambio de stock, registrar en bitácora
        if ($columnName === 'almacen' && $stockAnterior !== null) {
            $stockNuevo = (float) $value;
            if (abs($stockNuevo - $stockAnterior) > 0.0001) {
                registrarBitacoraStock(
                    $link,
                    $id_sucursal,
                    'PRODUCTO',
                    $codigoActualizacion,
                    $descProducto,
                    $stockAnterior,
                    $stockNuevo,
                    $motivo,
                    $id_usuario_act,
                    $nombre_usuario,
                    'Mantenimiento Productos (Tabla / En línea)'
                );
            }
        }

        cc_sync_enqueue($link, $id_sucursal, 'producto', 'upsert', [
            'codigo' => $codigoActualizacion,
        ], [
            'tabla' => 'cc_productos',
            'columna' => (string) $columnName,
        ]);
        echo trim($value);
    } else {
        http_response_code(500);
        echo 'Error, no se pudo actualizar: ' . mysqli_error($link);
    }
}
?>
