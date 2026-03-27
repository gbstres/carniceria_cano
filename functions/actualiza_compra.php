<?php

// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    exit;
}
require_once "../functions/config.php";
require_once "../functions/sync_queue.php";

$cadena = $_POST['id'];
$value = mb_strtoupper($_POST['value']);
$columnName = $_POST['columnName'];

$separada = explode(',', $cadena);
$id_compra = $separada[0];
$id_consecutivo = $separada[1];
$id_proveedor = $separada[2];
$precio = $separada[3];
$cantidad = $separada[4];

date_default_timezone_set("America/Mexico_City");
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Set parameters
    $id_sucursal = $_SESSION["id_sucursal"];
    $id_usuario_act = $_SESSION['id'];
    $fecha_act = date('y-m-d');
    $hora_act = date('H:i:s');
    $update_compra = mysqli_query($link, "UPDATE cc_compras SET "
                    . "$columnName = '$value', fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                    . "WHERE id_sucursal='$id_sucursal' and id_compra='$id_compra' and id_consecutivo = '$id_consecutivo'")
            or die(mysqli_error());
    if ($update_compra) {
        if ($columnName == 'Precio') {
            
        }

        if ($id_proveedor > 0) {
            $importe_inicial = $precio * $cantidad;
            if ($columnName == 'importe') {
                $importe_final = $value * $cantidad;
            } else if ($columnName == 'cantidad') {
                $importe_final = $value * $precio;
            }

            $abono = $importe_final - $importe_inicial;
            recalcula($link, $id_sucursal, $abono, $id_proveedor, $hora_act, $fecha_act, $id_usuario_act);
        }

        if ($columnName == 'cantidad') {
            $cantidad = round($value - $cantidad,3);
            recalcula_almacen_compra($link, $id_sucursal, $id_compra, $id_consecutivo, $cantidad, $fecha_act, $hora_act, $id_usuario_act);
        }
        echo $value;
    } else {
        echo 'Error, no se pudo actualizar ';
    }
}

//recalcula saldo proveedor
function recalcula($link, $id_sucursal, $abono, $id_proveedor, $fecha_act, $hora_act, $id_usuario_act) {
    mysqli_query($link, "UPDATE cc_saldos_proveedores SET efectivo_hoy = efectivo_hoy + $abono, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and id_proveedor = $id_proveedor");
    //$row_efectivo = mysqli_fetch_assoc(mysqli_query($link, "select sum(efectivo_hoy) as 'efectivo_hoy' from cc_saldos_proveedores where id_sucursal = $id_sucursal and id_proveedor =" . $id_proveedor));
    //$efectivo = $row_efectivo['efectivo_hoy'];
    //return round($efectivo, 2);
}

function recalcula_almacen_compra($link, $id_sucursal, $id_compra, $id_consecutivo, $cantidad, $fecha_act, $hora_act, $id_usuario_act) {
    $sqlcompras = mysqli_query($link, "
SELECT 
    a.codigo,
    b.centralizar_almacen,
    c.codigo_p,
    c.codigo_d,
    c.porcentaje,
    case a.estatus 
    when 2 THEN a.cantidad * -1
    ELSE a.cantidad
    END cantidad,
    b.id_categoria,
    ROW_NUMBER() OVER(PARTITION BY a.codigo ORDER BY a.codigo) as contador,
    d.centralizar_almacen as centralizar_almacen_d,
    d.id_categoria as id_categoria_d
FROM cc_compras a 
INNER JOIN cc_productos b ON a.id_sucursal = b.id_sucursal AND a.codigo = b.codigo 
LEFT JOIN cc_derivados c ON a.id_sucursal = c.id_sucursal AND a.codigo = c.codigo_p
LEFT JOIN cc_productos d ON c.id_sucursal = d.id_sucursal AND c.codigo_d = d.codigo
WHERE a.id_sucursal = $id_sucursal AND a.id_compra = $id_compra AND a.id_consecutivo = $id_consecutivo;");
    while ($rowv = mysqli_fetch_assoc($sqlcompras)) {
        if ($rowv['codigo_p'] == null) {
            if ($rowv['contador'] == 1) {
                if ($rowv['centralizar_almacen'] == 1) {
                    recalcula_almacen_producto($link, $id_sucursal, $rowv['codigo'], $cantidad, $fecha_act, $hora_act, $id_usuario_act);
                } else if ($rowv['centralizar_almacen'] == 2) {
                    recalcula_almacen_categoria($link, $id_sucursal, $rowv['id_categoria'], $cantidad, $fecha_act, $hora_act, $id_usuario_act);
                }
            }
        } else {
            if ($rowv['centralizar_almacen_d'] == 1) {
                recalcula_almacen_producto($link, $id_sucursal, $rowv['codigo_d'], $cantidad, $fecha_act, $hora_act, $id_usuario_act);
            } else if ($rowv['centralizar_almacen'] == 2) {
                $cantidad = round($cantidad * $rowv['porcentaje'] / 100, 3);
                recalcula_almacen_categoria($link, $id_sucursal, $rowv['id_categoria_d'], $cantidad, $fecha_act, $hora_act, $id_usuario_act);
            }
        }
    }
}

function recalcula_almacen_producto($link, $id_sucursal, $codigo, $cantidad, $fecha_act, $hora_act, $id_usuario_act) {
    mysqli_query($link, "UPDATE cc_productos SET almacen = almacen + $cantidad, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and codigo = $codigo");
    cc_sync_enqueue($link, $id_sucursal, 'producto', 'upsert', [
        'codigo' => (string) $codigo,
    ], [
        'tabla' => 'cc_productos',
        'motivo' => 'movimiento_compra',
    ]);
}

function recalcula_almacen_categoria($link, $id_sucursal, $id_categoria, $cantidad, $fecha_act, $hora_act, $id_usuario_act) {
    mysqli_query($link, "UPDATE cc_categorias SET almacen = almacen + $cantidad, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and id_categoria = $id_categoria");
    cc_sync_enqueue($link, $id_sucursal, 'categoria', 'upsert', [
        'id_categoria' => (int) $id_categoria,
    ], [
        'tabla' => 'cc_categorias',
        'motivo' => 'movimiento_compra',
    ]);
}

?>
