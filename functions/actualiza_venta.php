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
$id_venta = (int) $separada[0];
$id_consecutivo = (int) $separada[1];
$id_cliente = (int) $separada[2];
$precio = (float) $separada[3];
$cantidad = (float) $separada[4];

date_default_timezone_set("America/Mexico_City");
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Set parameters
    $id_sucursal = $_SESSION["id_sucursal"];
    $id_usuario_act = $_SESSION['id'];
    $fecha_act = date('Y-m-d');
    $hora_act = date('H:i:s');

    $ventaActual = mysqli_fetch_assoc(mysqli_query($link, "
        SELECT v.precio_venta, v.cantidad, d.id_cliente
        FROM cc_ventas v
        INNER JOIN cc_det_ventas d
            ON d.id_sucursal = v.id_sucursal
           AND d.id_venta = v.id_venta
        WHERE v.id_sucursal = $id_sucursal
          AND v.id_venta = $id_venta
          AND v.id_consecutivo = $id_consecutivo
        LIMIT 1
    "));
    if ($ventaActual) {
        $precio = (float) $ventaActual['precio_venta'];
        $cantidad = (float) $ventaActual['cantidad'];
        $id_cliente = (int) $ventaActual['id_cliente'];
    }

    $update_venta = mysqli_query($link, "UPDATE cc_ventas SET "
                    . "$columnName = '$value', fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                    . "WHERE id_sucursal='$id_sucursal' and id_venta='$id_venta' and id_consecutivo = '$id_consecutivo'")
            or die(mysqli_error());
    if ($update_venta) {
        if ($columnName == 'Precio') {
            
        }

        if ($id_cliente > 0) {
            $importe_inicial = $precio * $cantidad;
            if ($columnName == 'importe') {
                $importe_final = $value * $cantidad;
            } else if ($columnName == 'cantidad') {
                $importe_final = $value * $precio;
            }

            $abono = $importe_final - $importe_inicial;
            recalcula($link, $id_sucursal, $abono, $id_cliente, $fecha_act, $hora_act, $id_usuario_act);
        }

        if ($columnName == 'cantidad') {
            $cantidad = round($value - $cantidad,3);
            recalcula_almacen_venta($link, $id_sucursal, $id_venta, $id_consecutivo, $cantidad, $fecha_act, $hora_act, $id_usuario_act);
        }
        echo $value;
    } else {
        echo 'Error, no se pudo actualizar ';
    }
}

//recalcula saldo cliente
function recalcula($link, $id_sucursal, $abono, $id_cliente, $fecha_act, $hora_act, $id_usuario_act) {
    mysqli_query($link, "UPDATE cc_saldos_clientes SET efectivo_hoy = efectivo_hoy + $abono, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and id_cliente = $id_cliente");
    //$row_efectivo = mysqli_fetch_assoc(mysqli_query($link, "select sum(efectivo_hoy) as 'efectivo_hoy' from cc_saldos_clientes where id_sucursal = $id_sucursal and id_cliente =" . $id_cliente));
    //$efectivo = $row_efectivo['efectivo_hoy'];
    //return round($efectivo, 2);
}

function recalcula_almacen_venta($link, $id_sucursal, $id_venta, $id_consecutivo, $cantidad, $fecha_act, $hora_act, $id_usuario_act) {
    $sqlventas = mysqli_query($link, "
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
FROM cc_ventas a 
INNER JOIN cc_productos b ON a.id_sucursal = b.id_sucursal AND a.codigo = b.codigo 
LEFT JOIN cc_derivados c ON a.id_sucursal = c.id_sucursal AND a.codigo = c.codigo_p
LEFT JOIN cc_productos d ON c.id_sucursal = d.id_sucursal AND c.codigo_d = d.codigo
WHERE a.id_sucursal = $id_sucursal AND a.id_venta = $id_venta AND a.id_consecutivo = $id_consecutivo;");
    while ($rowv = mysqli_fetch_assoc($sqlventas)) {
        if ($rowv['codigo_p'] == null) {
            if ($rowv['contador'] == 1) {
                if ($rowv['centralizar_almacen'] == 1 || get_equivalencia_producto_actualiza_venta($link, $id_sucursal, $rowv['codigo']) !== null) {
                    recalcula_almacen_producto($link, $id_sucursal, $rowv['codigo'], $cantidad, $fecha_act, $hora_act, $id_usuario_act);
                } else if ($rowv['centralizar_almacen'] == 2) {
                    recalcula_almacen_categoria($link, $id_sucursal, $rowv['id_categoria'], $cantidad, $fecha_act, $hora_act, $id_usuario_act);
                }
            }
        } else {
            if ($rowv['centralizar_almacen_d'] == 1 || get_equivalencia_producto_actualiza_venta($link, $id_sucursal, $rowv['codigo_d']) !== null) {
                recalcula_almacen_producto($link, $id_sucursal, $rowv['codigo_d'], $cantidad, $fecha_act, $hora_act, $id_usuario_act);
            } else if ($rowv['centralizar_almacen'] == 2) {
                $cantidad = round($cantidad * $rowv['porcentaje'] / 100, 3);
                recalcula_almacen_categoria($link, $id_sucursal, $rowv['id_categoria_d'], $cantidad, $fecha_act, $hora_act, $id_usuario_act);
            }
        }
    }
}

function get_equivalencia_producto_actualiza_venta($link, $id_sucursal, $codigo) {
    $st = mysqli_prepare($link, "
        SELECT codigo_destino, factor
        FROM cc_equivalencias_productos
        WHERE id_sucursal = ?
          AND codigo_origen = ?
          AND activo = 1
        LIMIT 1
    ");
    if (!$st) {
        return null;
    }

    $codigo = (string) $codigo;
    mysqli_stmt_bind_param($st, "is", $id_sucursal, $codigo);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    $equivalencia = mysqli_fetch_assoc($res) ?: null;
    mysqli_free_result($res);
    mysqli_stmt_close($st);

    return $equivalencia;
}

function resolver_inventario_producto_actualiza_venta($link, $id_sucursal, $codigo, $cantidad) {
    $codigoInventario = (string) $codigo;
    $cantidadInventario = (float) $cantidad;
    $equivalencia = get_equivalencia_producto_actualiza_venta($link, $id_sucursal, $codigoInventario);

    if ($equivalencia !== null) {
        $codigoInventario = (string) $equivalencia['codigo_destino'];
        $cantidadInventario = round($cantidadInventario * (float) $equivalencia['factor'], 3);
    }

    return [
        'codigo' => $codigoInventario,
        'cantidad' => $cantidadInventario,
    ];
}

function recalcula_almacen_producto($link, $id_sucursal, $codigo, $cantidad, $fecha_act, $hora_act, $id_usuario_act) {
    $inventario = resolver_inventario_producto_actualiza_venta($link, $id_sucursal, $codigo, $cantidad);
    $codigoInventario = (string) $inventario['codigo'];
    $codigoSql = mysqli_real_escape_string($link, $codigoInventario);
    $cantidadInventario = (float) $inventario['cantidad'];

    mysqli_query($link, "UPDATE cc_productos SET almacen = almacen - $cantidadInventario, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and codigo = '$codigoSql'");
    cc_sync_enqueue($link, $id_sucursal, 'producto', 'upsert', [
        'codigo' => $codigoInventario,
    ], [
        'tabla' => 'cc_productos',
        'motivo' => 'movimiento_venta',
    ]);
}

function recalcula_almacen_categoria($link, $id_sucursal, $id_categoria, $cantidad, $fecha_act, $hora_act, $id_usuario_act) {
    mysqli_query($link, "UPDATE cc_categorias SET almacen = almacen - $cantidad, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and id_categoria = $id_categoria");
    cc_sync_enqueue($link, $id_sucursal, 'categoria', 'upsert', [
        'id_categoria' => (int) $id_categoria,
    ], [
        'tabla' => 'cc_categorias',
        'motivo' => 'movimiento_venta',
    ]);
}

?>
