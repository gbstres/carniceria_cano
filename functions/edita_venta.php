<?php

// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";
require_once "../functions/sync_queue.php";
date_default_timezone_set("America/Mexico_City");
// Define variables and initialize with empty values
$codigo = 0;
$id_sucursal = $_SESSION["id_sucursal"];
$descripcion = "";
$precio_compra = 0;
$precio_venta = 0;
$fecha_ingreso = date('Y-m-d');
$hora_ingreso = date('H:i:s');
$id_usuario = $_SESSION["id"];
$body = "";
$title = "";
$id_venta = $id_venta_t = $_POST['id_venta'];
$id_cliente = $_POST['id_cliente'];

function asegurarSaldoClienteVenta($link, $id_sucursal, $id_cliente, $id_usuario, $fecha, $hora) {
    $id_sucursal = (int) $id_sucursal;
    $id_cliente = (int) $id_cliente;
    $id_usuario = (int) $id_usuario;
    if ($id_cliente <= 0) {
        return;
    }

    mysqli_query($link, "INSERT INTO cc_saldos_clientes
        (id_sucursal, id_cliente, efectivo_hoy, efectivo_ayer, efectivo_mes, id_usuario, fecha_ingreso, hora_ingreso)
        SELECT $id_sucursal, $id_cliente, 0, 0, 0, $id_usuario, '$fecha', '$hora'
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1 FROM cc_saldos_clientes
            WHERE id_sucursal = $id_sucursal
              AND id_cliente = $id_cliente
        )");
}

//Agrega productos de inicio
if ($_POST['movimiento'] == 1) {
    $codigo = trim($_POST["codigo"]);
    $id_consecutivo = intval(trim($_POST["id_consecutivo"]));
    $rowproducto = mysqli_fetch_assoc(mysqli_query($link, "SELECT precio_compra,descripcion FROM cc_productos WHERE id_sucursal = '$id_sucursal' and codigo = $codigo"));
    $precio_compra = $rowproducto['precio_compra'];
    $descripcion = $rowproducto['descripcion'];
    $clave_externa = trim($_POST["clave_externa"]);
    if ($id_venta == '' OR $id_venta == '0') {
        $rowventa = mysqli_fetch_assoc(mysqli_query($link, "SELECT max(id_venta) as id_venta FROM `cc_det_ventas` WHERE id_sucursal = '$id_sucursal'"));
        $id_venta = $rowventa['id_venta'];
        if ($id_venta == null) {
            $id_venta = 1;
        } else {
            $id_venta = $id_venta + 1;
        }
        $id_consecutivo = 1;
    } else {
        $rowventa = mysqli_fetch_assoc(mysqli_query($link, "SELECT max(id_consecutivo) as id_consecutivo FROM `cc_ventas` WHERE id_sucursal = '$id_sucursal' and id_venta = $id_venta"));
        $id_consecutivo = intval($rowventa['id_consecutivo']) + 1;
    }


    $sql = "INSERT INTO cc_ventas (id_sucursal, id_venta, id_consecutivo, codigo, precio_compra, precio_venta, cantidad, clave_externa, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
// Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "iiisdddsiss", $id_sucursal, $id_venta, $id_consecutivo, $codigo, $precio_compra, $precio_venta, $cantidad, $clave_externa, $id_usuario, $fecha_ingreso, $hora_ingreso);
        $precio_venta = trim($_POST["precio_venta"]);
        $cantidad = trim($_POST["cantidad"]);
        if (mysqli_stmt_execute($stmt)) {
            if ($id_venta_t == '' OR $id_venta_t == '0') {
                $sql2 = "INSERT INTO cc_det_ventas (id_sucursal, id_venta, estatus, id_cliente, pagado, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($link, $sql2);
                mysqli_stmt_bind_param($stmt, "iiiiiiss", $id_sucursal, $id_venta, $estatus, $id_cliente, $pagado, $id_usuario, $fecha_ingreso, $hora_ingreso);
                $estatus = 0;
                $pagado = 0;
                mysqli_stmt_execute($stmt);
            }
            cc_sync_enqueue($link, $id_sucursal, 'venta', 'upsert', [
                'id_venta' => (int) $id_venta,
                'id_consecutivo' => (int) $id_consecutivo,
            ], [
                'tabla' => 'cc_ventas',
                'id_cliente' => (int) $id_cliente,
                'codigo' => (string) $codigo,
            ]);
            $response_array [] = array('id_venta' => $id_venta, 'id_consecutivo' => $id_consecutivo, 'descripcion' => $descripcion, 'id_cliente' => $id_cliente, 'fecha_ingreso' => $fecha_ingreso, 'hora_ingreso' => $hora_ingreso, 'clave_externa' => $clave_externa);
        } else {
            $response_array [] = array('id_venta' => 0, 'id_consecutivo' => 0, 'id_cliente' => 0);
        }
    }
    echo json_encode($response_array);
}
//borrar  (solo se cambia el estatus)
else if ($_POST['movimiento'] == 2) {
    $id_consecutivo = trim($_POST["id_consecutivo"]);
    $delete = mysqli_query($link, "DELETE FROM cc_ventas WHERE id_sucursal = '$id_sucursal' and id_venta = $id_venta and id_consecutivo = $id_consecutivo");
    if ($delete) {
        cc_sync_enqueue($link, $id_sucursal, 'venta', 'delete', [
            'id_venta' => (int) $id_venta,
            'id_consecutivo' => (int) $id_consecutivo,
        ], [
            'tabla' => 'cc_ventas',
        ]);
        $sql3 = "SELECT * FROM cc_ventas where id_sucursal = '$id_sucursal' and id_venta = $id_venta";
        $result = mysqli_query($link, $sql3);
        $numero = mysqli_num_rows($result);
        if ($numero == 0) {
            mysqli_query($link, "DELETE FROM cc_det_ventas WHERE id_sucursal = '$id_sucursal' and id_venta = $id_venta");
            cc_sync_enqueue($link, $id_sucursal, 'venta_detalle', 'delete', [
                'id_venta' => (int) $id_venta,
            ], [
                'tabla' => 'cc_det_ventas',
            ]);
            $response_array [] = array('id_venta' => 0, 'id_cliente' => 0);
        } else {
            $response_array [] = array('id_venta' => $id_venta);
        }
    } else {
        
    }
    echo json_encode($response_array);
}
// elimina venta desde el inicio. Solo se actualizan los movimientos
else if ($_POST['movimiento'] == 3) {
    $movimiento = 2;
    $update2 = mysqli_query($link, "UPDATE cc_ventas SET "
            . "estatus = 2, "
            . "fecha_act='$fecha_ingreso', hora_act='$hora_ingreso', id_usuario_act='$id_usuario' "
            . "WHERE id_sucursal='$id_sucursal' and id_venta='$id_venta'");
    if ($update2) {
        $update3 = mysqli_query($link, "UPDATE cc_det_ventas SET "
                . "estatus = 1, "
                . "fecha_act='$fecha_ingreso', hora_act='$hora_ingreso', id_usuario_act='$id_usuario' "
                . "WHERE id_sucursal='$id_sucursal' and id_venta='$id_venta'");
        cc_sync_enqueue($link, $id_sucursal, 'venta', 'cancel', [
            'id_venta' => (int) $id_venta,
        ], [
            'tabla' => 'cc_ventas',
        ]);
        cc_sync_enqueue($link, $id_sucursal, 'venta_detalle', 'upsert', [
            'id_venta' => (int) $id_venta,
        ], [
            'tabla' => 'cc_det_ventas',
        ]);
        $response_array [] = array('id_venta' => 0, 'id_consecutivo' => 0, 'id_cliente' => 0);
    } else {
        
    }
    echo json_encode($response_array);
}
// Cliente inicio
else if ($_POST['movimiento'] == 4) {
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('Y-m-d');
    $hora_act = date('H:i:s');
    $update1 = mysqli_query($link, "UPDATE cc_det_ventas SET "
                    . "id_cliente='$id_cliente', fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                    . "WHERE id_sucursal='$id_sucursal' and id_venta='$id_venta'")
            or die(mysqli_error());
    if ($update1) {
        cc_sync_enqueue($link, $id_sucursal, 'venta_detalle', 'upsert', [
            'id_venta' => (int) $id_venta,
        ], [
            'tabla' => 'cc_det_ventas',
            'id_cliente' => (int) $id_cliente,
        ]);
        $response_array [] = array('id_venta' => $id_venta);
    } else {
        echo '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>Error, no se pudo guardar el producto.</div>';
    }
    echo json_encode($response_array);
}
// Cierra venta
else if ($_POST['movimiento'] == 5) {
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('Y-m-d');
    $hora_act = date('H:i:s');
    $id_empleado = $_POST['id_empleado'];
    $importe_recibido = $_POST['importe_recibido'];
    $tipo_pago = $_POST['tipo_pago'];
    $update1 = mysqli_query($link, "UPDATE cc_det_ventas SET estatus=1, id_empleado = $id_empleado, importe_recibido= $importe_recibido, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act', tipo_pago='$tipo_pago' WHERE id_sucursal='$id_sucursal' and id_venta='$id_venta'")
            or die(mysqli_error());
    if ($update1) {
        $row_det_venta = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM `cc_det_ventas` WHERE id_sucursal = '$id_sucursal' and id_venta = $id_venta"));
        cc_sync_enqueue($link, $id_sucursal, 'venta_detalle', 'close', [
            'id_venta' => (int) $id_venta,
        ], [
            'tabla' => 'cc_det_ventas',
            'tipo_pago' => (int) $tipo_pago,
            'id_empleado' => (int) $id_empleado,
        ]);
        cc_sync_enqueue($link, $id_sucursal, 'venta', 'upsert', [
            'id_venta' => (int) $id_venta,
        ], [
            'tabla' => 'cc_ventas',
            'motivo' => 'cierre',
        ]);
        $response_array [] = array('id_venta' => $id_venta, 'fecha_ingreso' => $row_det_venta['fecha_ingreso'], 'hora_ingreso' => $row_det_venta['hora_ingreso']);
        if ($row_det_venta['id_cliente'] <> 0) {
            $id_cliente = $row_det_venta['id_cliente'];
            recalcula($link, $id_sucursal, $id_venta, $id_cliente, $fecha_act, $hora_act, $id_usuario_act);
        }
        recalcula_almacen_venta($link, $id_sucursal, $id_venta, $fecha_act, $hora_act, $id_usuario_act);
    } else {
        echo '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>Error, no se pudo guardar el producto.</div>';
    }
    echo json_encode($response_array);
}
// Abrir caja
else if ($_POST['movimiento'] == 6) {
    $id_usuario = $_SESSION["id"];
    $fecha_ingreso = date('Y-m-d');
    $hora_ingreso = date('H:i:s');
    $comentarios = trim($_POST["comentarios"]);
//$importe_recibido = $_POST['importe_recibido'];
//$update1 = mysqli_query($link, "UPDATE cc_det_ventas SET estatus=1, id_empleado = $id_empleado, importe_recibido= $importe_recibido, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' WHERE id_sucursal='$id_sucursal' and id_venta='$id_venta'")
//        or die(mysqli_error());
//if ($update1) {
//    $row_det_venta = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM `cc_det_ventas` WHERE id_sucursal = '$id_sucursal' and id_venta = $id_venta"));
    $response_array [] = array('fecha_ingreso' => $fecha_ingreso, 'hora_ingreso' => $hora_ingreso, 'comentarios' => $comentarios);
//} else {
//    echo '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>Error, no se pudo guardar el producto.</div>';
//}
    echo json_encode($response_array);
}
// Elimina venta desde reporte/ventas. Solo cambia estatus sin eliminar registros
else if ($_POST['movimiento'] == 7) {
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('Y-m-d');
    $hora_act = date('H:i:s');
    if ($id_cliente != 0) {
        recalcula_eliminacion($link, $id_sucursal, $id_venta, $id_cliente, $fecha_act, $hora_act, $id_usuario_act);
    }
    recalcula_almacen_cancela_venta($link, $id_sucursal, $id_venta, $fecha_act, $hora_act, $id_usuario_act);
    $update4 = mysqli_query($link, "UPDATE cc_ventas SET "
                    . "estatus = 2, "
                    . "fecha_act='$fecha_ingreso', hora_act='$hora_ingreso', id_usuario_act='$id_usuario' "
                    . "WHERE id_sucursal='$id_sucursal' and id_venta='$id_venta' and estatus in (0)")
            or die(mysqli_error());
    if ($update4) {
        cc_sync_enqueue($link, $id_sucursal, 'venta', 'cancel', [
            'id_venta' => (int) $id_venta,
        ], [
            'tabla' => 'cc_ventas',
            'motivo' => 'reporte',
        ]);
        $response_array [] = array('id_venta' => $id_venta);
    } else {
        echo '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>Error, no se pudo guardar el producto.</div>';
    }
    echo json_encode($response_array);
}
// Agregar producto desde venta cerrada
else if ($_POST['movimiento'] == 8) {
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('Y-m-d');
    $hora_act = date('H:i:s');
    $codigo = trim($_POST["codigo"]);
    $rowproducto = mysqli_fetch_assoc(mysqli_query($link, "SELECT precio_compra,descripcion FROM cc_productos WHERE id_sucursal = '$id_sucursal' and codigo = $codigo"));
    $precio_compra = $rowproducto['precio_compra'];
    $descripcion = $rowproducto['descripcion'];
    $clave_externa = trim($_POST["clave_externa"]);
    $rowventa = mysqli_fetch_assoc(mysqli_query($link, "SELECT max(id_consecutivo) as id_consecutivo FROM `cc_ventas` WHERE id_sucursal = '$id_sucursal' and id_venta = $id_venta"));
    $id_consecutivo = intval($rowventa['id_consecutivo']) + 1;

    $sql = "INSERT INTO cc_ventas (id_sucursal, id_venta, id_consecutivo, codigo, precio_compra, precio_venta, cantidad, clave_externa, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iiisdddsiss", $id_sucursal, $id_venta, $id_consecutivo, $codigo, $precio_compra, $precio_venta, $cantidad, $clave_externa, $id_usuario, $fecha_ingreso, $hora_ingreso);
        $precio_venta = trim($_POST["precio_venta"]);
        $cantidad = trim($_POST["cantidad"]);
        if (mysqli_stmt_execute($stmt)) {
            if ($id_cliente != 0) {
                $importe = $precio_venta * $cantidad;
                recalcula_cliente_producto($link, $id_sucursal, $importe, $id_cliente, $fecha_act, $hora_act, $id_usuario_act);
            }
            recalcula_almacen_venta_producto($link, $id_sucursal, $id_venta, $id_consecutivo, $fecha_act, $hora_act, $id_usuario_act);
            cc_sync_enqueue($link, $id_sucursal, 'venta', 'upsert', [
                'id_venta' => (int) $id_venta,
                'id_consecutivo' => (int) $id_consecutivo,
            ], [
                'tabla' => 'cc_ventas',
                'id_cliente' => (int) $id_cliente,
                'codigo' => (string) $codigo,
            ]);
            $response_array [] = array('id_venta' => $id_venta, 'id_consecutivo' => $id_consecutivo, 'descripcion' => $descripcion, 'id_cliente' => $id_cliente, 'fecha_ingreso' => $fecha_ingreso, 'hora_ingreso' => $hora_ingreso, 'clave_externa' => $clave_externa);
        } else {
            $response_array [] = array('id_venta' => 0, 'id_consecutivo' => 0, 'id_cliente' => 0);
        }
    }
    echo json_encode($response_array);
}

// recalcula saldo cliente
function recalcula($link, $id_sucursal, $id_venta, $id_cliente, $fecha_act, $hora_act, $id_usuario_act) {
    asegurarSaldoClienteVenta($link, $id_sucursal, $id_cliente, $id_usuario_act, $fecha_act, $hora_act);
    $row_saldo = mysqli_fetch_assoc(mysqli_query($link, "SELECT sum(cantidad * precio_venta) as 'saldo' FROM `cc_ventas` WHERE id_sucursal = '$id_sucursal' and id_venta = $id_venta"));
    $saldo = $row_saldo['saldo'];
    mysqli_query($link, "UPDATE cc_saldos_clientes SET efectivo_hoy = efectivo_hoy + $saldo, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and id_cliente = $id_cliente");
}

//recalcula saldo cliente por producto
function recalcula_cliente_producto($link, $id_sucursal, $importe, $id_cliente, $fecha_act, $hora_act, $id_usuario_act) {
    asegurarSaldoClienteVenta($link, $id_sucursal, $id_cliente, $id_usuario_act, $fecha_act, $hora_act);
    mysqli_query($link, "UPDATE cc_saldos_clientes SET efectivo_hoy = efectivo_hoy + $importe, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and id_cliente = $id_cliente");
}

// recalcula saldo cliente si elimina la venta (update)
function recalcula_eliminacion($link, $id_sucursal, $id_venta, $id_cliente, $fecha_act, $hora_act, $id_usuario_act) {
    asegurarSaldoClienteVenta($link, $id_sucursal, $id_cliente, $id_usuario_act, $fecha_act, $hora_act);
    $row_saldo = mysqli_fetch_assoc(mysqli_query($link, "SELECT sum(cantidad * precio_venta) as 'saldo' FROM `cc_ventas` WHERE id_sucursal = '$id_sucursal' and id_venta = $id_venta and estatus in (0)"));
    $saldo = $row_saldo['saldo'];
    if ($row_saldo['saldo'] == null) {
        $saldo = 0;
    }
    mysqli_query($link, "UPDATE cc_saldos_clientes SET efectivo_hoy = efectivo_hoy - $saldo, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and id_cliente = $id_cliente");
}

function recalcula_almacen_venta($link, $id_sucursal, $id_venta, $fecha_act, $hora_act, $id_usuario_act) {
    $sqlventas = mysqli_query($link, "
SELECT 
    a.codigo,
    b.centralizar_almacen,
    c.codigo_p,
    c.codigo_d,
    c.porcentaje,
    a.cantidad,
    b.id_categoria,
    ROW_NUMBER() OVER(PARTITION BY a.codigo ORDER BY a.codigo) as contador,
    d.centralizar_almacen as centralizar_almacen_d,
    d.id_categoria as id_categoria_d
FROM cc_ventas a 
INNER JOIN cc_productos b ON a.id_sucursal = b.id_sucursal AND a.codigo = b.codigo 
LEFT JOIN cc_derivados c ON a.id_sucursal = c.id_sucursal AND a.codigo = c.codigo_p
LEFT JOIN cc_productos d ON c.id_sucursal = d.id_sucursal AND c.codigo_d = d.codigo
WHERE a.id_sucursal = $id_sucursal AND a.id_venta = $id_venta ;");
    while ($rowv = mysqli_fetch_assoc($sqlventas)) {
        if ($rowv['codigo_p'] == null) {
            if ($rowv['contador'] == 1) {
                if ($rowv['centralizar_almacen'] == 1) {
                    recalcula_almacen_producto($link, $id_sucursal, $rowv['codigo'], $rowv['cantidad'], $fecha_act, $hora_act, $id_usuario_act);
                } else if ($rowv['centralizar_almacen'] == 2) {
                    recalcula_almacen_categoria($link, $id_sucursal, $rowv['id_categoria'], $rowv['cantidad'], $fecha_act, $hora_act, $id_usuario_act);
                }
            }
        } else {
            if ($rowv['centralizar_almacen_d'] == 1) {
                recalcula_almacen_producto($link, $id_sucursal, $rowv['codigo_d'], $rowv['cantidad'], $fecha_act, $hora_act, $id_usuario_act);
            } else if ($rowv['centralizar_almacen'] == 2) {
                $cantidad = round($rowv['cantidad'] * $rowv['porcentaje'] / 100, 3);
                recalcula_almacen_categoria($link, $id_sucursal, $rowv['id_categoria_d'], $cantidad, $fecha_act, $hora_act, $id_usuario_act);
            }
        }
    }
}

function recalcula_almacen_venta_producto($link, $id_sucursal, $id_venta, $id_consecutivo, $fecha_act, $hora_act, $id_usuario_act) {
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
                if ($rowv['centralizar_almacen'] == 1) {
                    recalcula_almacen_producto($link, $id_sucursal, $rowv['codigo'], $rowv['cantidad'], $fecha_act, $hora_act, $id_usuario_act);
                } else if ($rowv['centralizar_almacen'] == 2) {
                    recalcula_almacen_categoria($link, $id_sucursal, $rowv['id_categoria'], $rowv['cantidad'], $fecha_act, $hora_act, $id_usuario_act);
                }
            }
        } else {
            if ($rowv['centralizar_almacen_d'] == 1) {
                recalcula_almacen_producto($link, $id_sucursal, $rowv['codigo_d'], $rowv['cantidad'], $fecha_act, $hora_act, $id_usuario_act);
            } else if ($rowv['centralizar_almacen'] == 2) {
                $cantidad = round($rowv['cantidad'] * $rowv['porcentaje'] / 100, 3);
                recalcula_almacen_categoria($link, $id_sucursal, $rowv['id_categoria_d'], $cantidad, $fecha_act, $hora_act, $id_usuario_act);
            }
        }
    }
}

function recalcula_almacen_cancela_venta($link, $id_sucursal, $id_venta, $fecha_act, $hora_act, $id_usuario_act) {
    $sqlventas = mysqli_query($link, "
SELECT 
    a.codigo,
    b.centralizar_almacen,
    c.codigo_p,
    c.codigo_d,
    c.porcentaje,
    a.cantidad * -1 as cantidad,
    b.id_categoria,
    ROW_NUMBER() OVER(PARTITION BY a.codigo ORDER BY a.codigo) as contador,
    d.centralizar_almacen as centralizar_almacen_d,
    d.id_categoria as id_categoria_d
FROM cc_ventas a 
INNER JOIN cc_productos b ON a.id_sucursal = b.id_sucursal AND a.codigo = b.codigo 
LEFT JOIN cc_derivados c ON a.id_sucursal = c.id_sucursal AND a.codigo = c.codigo_p
LEFT JOIN cc_productos d ON c.id_sucursal = d.id_sucursal AND c.codigo_d = d.codigo
WHERE a.id_sucursal = $id_sucursal AND a.id_venta = $id_venta  and a.estatus in (0);");
    while ($rowv = mysqli_fetch_assoc($sqlventas)) {
        if ($rowv['codigo_p'] == null) {
            if ($rowv['contador'] == 1) {
                if ($rowv['centralizar_almacen'] == 1) {
                    recalcula_almacen_producto($link, $id_sucursal, $rowv['codigo'], $rowv['cantidad'], $fecha_act, $hora_act, $id_usuario_act);
                } else if ($rowv['centralizar_almacen'] == 2) {
                    recalcula_almacen_categoria($link, $id_sucursal, $rowv['id_categoria'], $rowv['cantidad'], $fecha_act, $hora_act, $id_usuario_act);
                }
            }
        } else {
            if ($rowv['centralizar_almacen_d'] == 1) {
                recalcula_almacen_producto($link, $id_sucursal, $rowv['codigo_d'], $rowv['cantidad'], $fecha_act, $hora_act, $id_usuario_act);
            } else if ($rowv['centralizar_almacen'] == 2) {
                $cantidad = round($rowv['cantidad'] * $rowv['porcentaje'] / 100, 3);
                recalcula_almacen_categoria($link, $id_sucursal, $rowv['id_categoria_d'], $cantidad, $fecha_act, $hora_act, $id_usuario_act);
            }
        }
    }
}

function recalcula_almacen_producto($link, $id_sucursal, $codigo, $cantidad, $fecha_act, $hora_act, $id_usuario_act) {
    mysqli_query($link, "UPDATE cc_productos SET almacen = almacen - $cantidad, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and codigo = $codigo");
    cc_sync_enqueue($link, $id_sucursal, 'producto', 'upsert', [
        'codigo' => (string) $codigo,
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
