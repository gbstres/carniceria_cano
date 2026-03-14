<?php

// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";
date_default_timezone_set("America/Mexico_City");
// Define variables and initialize with empty values
$codigo = 0;
$id_sucursal = $_SESSION["id_sucursal"];
$descripcion = "";
$precio_compra = 0;
$precio_compra = 0;
$fecha_ingreso = date('Y-m-d');
$hora_ingreso = date('H:i:s');
$id_usuario = $_SESSION["id"];
$body = "";
$title = "";
$id_compra = $_POST['id_compra'];
$id_proveedor = $_POST['id_proveedor'];

if ($_POST['movimiento'] == 1) {
    $codigo = trim($_POST["codigo"]);
    $id_consecutivo = intval(trim($_POST["id_consecutivo"]));
    $rowproducto = mysqli_fetch_assoc(mysqli_query($link, "SELECT precio_compra,descripcion FROM cc_productos WHERE id_sucursal = '$id_sucursal' and codigo = $codigo"));
    $precio_compra = $rowproducto['precio_compra'];
    $descripcion = $rowproducto['descripcion'];
    $clave_externa = trim($_POST["clave_externa"]);
    if ($id_compra == '' OR $id_compra == '0') {
        $rowcompra = mysqli_fetch_assoc(mysqli_query($link, "SELECT max(id_compra) as id_compra FROM `cc_compras` WHERE id_sucursal = '$id_sucursal'"));
        $id_compra = $rowcompra['id_compra'];
        if ($id_compra == null) {
            $id_compra = 1;
        } else {
            $id_compra = $id_compra + 1;
        }
        $id_consecutivo = 1;
    } else {
        $rowcompra = mysqli_fetch_assoc(mysqli_query($link, "SELECT max(id_consecutivo) as id_consecutivo FROM `cc_compras` WHERE id_sucursal = '$id_sucursal' and id_compra = $id_compra"));
        $id_consecutivo = intval($rowcompra['id_consecutivo']) + 1;
    }


    $sql = "INSERT INTO cc_compras (id_sucursal, id_compra, id_consecutivo, codigo, precio_compra, cantidad, clave_externa, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "iiisddsiss", $id_sucursal, $id_compra, $id_consecutivo, $codigo, $precio_compra, $cantidad, $clave_externa, $id_usuario, $fecha_ingreso, $hora_ingreso);
        $precio_compra = trim($_POST["precio_compra"]);
        $cantidad = trim($_POST["cantidad"]);
        if (mysqli_stmt_execute($stmt)) {
            $sql1 = "SELECT * FROM cc_det_compras where id_sucursal = '$id_sucursal' and id_compra = $id_compra";
            $result = mysqli_query($link, $sql1);
            $numero = mysqli_num_rows($result);
            if ($numero == 0) {
                $sql2 = "INSERT INTO cc_det_compras (id_sucursal, id_compra, estatus, id_proveedor, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($link, $sql2);
                mysqli_stmt_bind_param($stmt, "iiiiiss", $id_sucursal, $id_compra, $estatus, $id_proveedor, $id_usuario, $fecha_ingreso, $hora_ingreso);
                $estatus = 0;
                mysqli_stmt_execute($stmt);
            }
            $response_array [] = array('id_compra' => $id_compra, 'id_consecutivo' => $id_consecutivo, 'descripcion' => $descripcion, 'id_proveedor' => $id_proveedor, 'fecha_ingreso' => $fecha_ingreso, 'hora_ingreso' => $hora_ingreso, 'clave_externa' => $clave_externa);
        } else {
            $response_array [] = array('id_compra' => 0, 'id_consecutivo' => 0, 'id_proveedor' => 0);
        }
    }
    echo json_encode($response_array);
}

else if ($_POST['movimiento'] == 2) {
    $id_consecutivo = trim($_POST["id_consecutivo"]);
    $delete = mysqli_query($link, "DELETE FROM cc_compras WHERE id_sucursal = '$id_sucursal' and id_compra = $id_compra and id_consecutivo = $id_consecutivo");
    if ($delete) {
        $sql3 = "SELECT * FROM cc_compras where id_sucursal = '$id_sucursal' and id_compra = $id_compra";
        $result = mysqli_query($link, $sql3);
        $numero = mysqli_num_rows($result);
        if ($numero == 0) {
            mysqli_query($link, "DELETE FROM cc_det_compras WHERE id_sucursal = '$id_sucursal' and id_compra = $id_compra");
            $response_array [] = array('id_compra' => 0, 'id_proveedor' => 0);
        } else {
            $response_array [] = array('id_compra' => $id_compra);
        }
    } else {
        
    }
    echo json_encode($response_array);
}
// elimina compra desde el inicio
// Solo se actualizan los movimientos
else if ($_POST['movimiento'] == 3) {
    $movimiento = 2;
    $update2 = mysqli_query($link, "UPDATE cc_compras SET "
            . "estatus = 2, "
            . "fecha_act='$fecha_ingreso', hora_act='$hora_ingreso', id_usuario_act='$id_usuario' "
            . "WHERE id_sucursal='$id_sucursal' and id_compra='$id_compra'");
    if ($update2) {
        $update3 = mysqli_query($link, "UPDATE cc_det_compras SET "
                . "estatus = 1, "
                . "fecha_act='$fecha_ingreso', hora_act='$hora_ingreso', id_usuario_act='$id_usuario' "
                . "WHERE id_sucursal='$id_sucursal' and id_compra='$id_compra'");
        $response_array [] = array('id_compra' => 0, 'id_consecutivo' => 0, 'id_proveedor' => 0);
    } else {
        
    }
    echo json_encode($response_array);
}

// Cliente inicio
else if ($_POST['movimiento'] == 4) {
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('Y-m-d');
    $hora_act = date('H:i:s');
    $update1 = mysqli_query($link, "UPDATE cc_det_compras SET "
                    . "id_proveedor='$id_proveedor', fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                    . "WHERE id_sucursal='$id_sucursal' and id_compra='$id_compra'")
            or die(mysqli_error());
    if ($update1) {
        $response_array [] = array('id_compra' => $id_compra);
    } else {
        echo '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>Error, no se pudo guardar el producto.</div>';
    }
    echo json_encode($response_array);
}

// Cierra compra
else if ($_POST['movimiento'] == 5) {
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('Y-m-d');
    $hora_act = date('H:i:s');
    $id_empleado = $_POST['id_empleado'];
    //$importe_recibido = $_POST['importe_recibido'];
    $tipo_pago = $_POST['tipo_pago'];
    $update1 = mysqli_query($link, "UPDATE cc_det_compras SET estatus=1, id_empleado = $id_empleado, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act', tipo_pago='$tipo_pago' WHERE id_sucursal='$id_sucursal' and id_compra='$id_compra'")
            or die(mysqli_error());
    if ($update1) {
        $row_det_compra = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM `cc_det_compras` WHERE id_sucursal = '$id_sucursal' and id_compra = $id_compra"));
        $response_array [] = array('id_compra' => $id_compra, 'fecha_ingreso' => $row_det_compra['fecha_ingreso'], 'hora_ingreso' => $row_det_compra['hora_ingreso']);
        if ($row_det_compra['id_proveedor'] <> 0) {
            $id_proveedor = $row_det_compra['id_proveedor'];
            recalcula($link, $id_sucursal, $id_compra, $id_proveedor, $fecha_act, $hora_act, $id_usuario_act);
        }
        recalcula_almacen_compra($link,$id_sucursal, $id_compra, $fecha_act, $hora_act, $id_usuario_act);
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
    //$update1 = mysqli_query($link, "UPDATE cc_det_compras SET estatus=1, id_empleado = $id_empleado, importe_recibido= $importe_recibido, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' WHERE id_sucursal='$id_sucursal' and id_compra='$id_compra'")
    //        or die(mysqli_error());
    //if ($update1) {
    //    $row_det_compra = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM `cc_det_compras` WHERE id_sucursal = '$id_sucursal' and id_compra = $id_compra"));
    $response_array [] = array('fecha_ingreso' => $fecha_ingreso, 'hora_ingreso' => $hora_ingreso, 'comentarios' => $comentarios);
    //} else {
    //    echo '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>Error, no se pudo guardar el producto.</div>';
    //}
    echo json_encode($response_array);
}

// Elimina compra desde reporte/compras
else if ($_POST['movimiento'] == 7) {
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('Y-m-d');
    $hora_act = date('H:i:s');
    if ($id_proveedor != 0) {
        recalcula_eliminacion($link, $id_sucursal, $id_compra, $id_proveedor, $fecha_act, $hora_act, $id_usuario_act);
    }
    recalcula_almacen_cancela_compra($link, $id_sucursal, $id_compra, $fecha_act, $hora_act, $id_usuario_act);
    $update4 = mysqli_query($link, "UPDATE cc_compras SET "
                    . "estatus = 2, "
                    . "fecha_act='$fecha_ingreso', hora_act='$hora_ingreso', id_usuario_act='$id_usuario' "
                    . "WHERE id_sucursal='$id_sucursal' and id_compra='$id_compra' and estatus in (0)")
            or die(mysqli_error());
    if ($update4) {
        $response_array [] = array('id_compra' => $id_compra);
    } else {
        echo '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>Error, no se pudo guardar el producto.</div>';
    }
    echo json_encode($response_array);
}

else if ($_POST['movimiento'] == 8) {
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('Y-m-d');
    $hora_act = date('H:i:s');
    $codigo = trim($_POST["codigo"]);
    $rowproducto = mysqli_fetch_assoc(mysqli_query($link, "SELECT precio_compra,descripcion FROM cc_productos WHERE id_sucursal = '$id_sucursal' and codigo = $codigo"));
    $descripcion = $rowproducto['descripcion'];
    $clave_externa = trim($_POST["clave_externa"]);
    $rowcompra = mysqli_fetch_assoc(mysqli_query($link, "SELECT max(id_consecutivo) as id_consecutivo FROM `cc_compras` WHERE id_sucursal = '$id_sucursal' and id_compra = $id_compra"));
    $id_consecutivo = intval($rowcompra['id_consecutivo']) + 1;

    $sql = "INSERT INTO cc_compras (id_sucursal, id_compra, id_consecutivo, codigo, precio_compra, cantidad, clave_externa, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iiisddsiss", $id_sucursal, $id_compra, $id_consecutivo, $codigo, $precio_compra, $cantidad, $clave_externa, $id_usuario, $fecha_ingreso, $hora_ingreso);
        $precio_compra = trim($_POST["precio_compra"]);
        $cantidad = trim($_POST["cantidad"]);
        if (mysqli_stmt_execute($stmt)) {
            if ($id_proveedor != 0) {
                $importe = $precio_compra * $cantidad;
                recalcula_proveedor_producto($link, $id_sucursal, $importe, $id_proveedor, $fecha_act, $hora_act, $id_usuario_act);
            }
            recalcula_almacen_compra_producto($link, $id_sucursal, $id_compra, $id_consecutivo, $fecha_act, $hora_act, $id_usuario_act);
            $response_array [] = array('id_compra' => $id_compra, 'id_consecutivo' => $id_consecutivo, 'descripcion' => $descripcion, 'id_proveedor' => $id_proveedor, 'fecha_ingreso' => $fecha_ingreso, 'hora_ingreso' => $hora_ingreso, 'clave_externa' => $clave_externa);
        } else {
            $response_array [] = array('id_compra' => 0, 'id_consecutivo' => 0, 'id_proveedor' => 0);
        }
    }
    echo json_encode($response_array);
}

// recalcula saldo proveedor
function recalcula($link, $id_sucursal, $id_compra, $id_proveedor, $fecha_act, $hora_act, $id_usuario_act) {
    $row_saldo = mysqli_fetch_assoc(mysqli_query($link, "SELECT sum(cantidad * precio_compra) as 'saldo' FROM `cc_compras` WHERE id_sucursal = '$id_sucursal' and id_compra = $id_compra"));
    $saldo = $row_saldo['saldo'];
    mysqli_query($link, "UPDATE cc_saldos_proveedores SET efectivo_hoy = efectivo_hoy + $saldo, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and id_proveedor = $id_proveedor");
}

// recalcula saldo proveedor si elimina la compra (update)
function recalcula_eliminacion($link, $id_sucursal, $id_compra, $id_proveedor, $fecha_act, $hora_act, $id_usuario_act) {
    $row_saldo = mysqli_fetch_assoc(mysqli_query($link, "SELECT sum(cantidad * precio_compra) as 'saldo' FROM `cc_compras` WHERE id_sucursal = '$id_sucursal' and id_compra = $id_compra and estatus in (0)"));
    $saldo = $row_saldo['saldo'];
    if ($row_saldo['saldo'] == null) {
        $saldo = 0;
    }
    mysqli_query($link, "UPDATE cc_saldos_proveedores SET efectivo_hoy = efectivo_hoy - $saldo, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and id_proveedor = $id_proveedor");
}

//recalcula saldo cliente por producto
function recalcula_proveedor_producto($link, $id_sucursal, $importe, $id_cliente, $fecha_act, $hora_act, $id_usuario_act) {
    mysqli_query($link, "UPDATE cc_saldos_clientes SET efectivo_hoy = efectivo_hoy + $importe, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and id_cliente = $id_cliente");
}

function recalcula_almacen_compra($link, $id_sucursal, $id_compra, $fecha_act, $hora_act, $id_usuario_act) {
    $sqlcompras = mysqli_query($link, "
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
FROM cc_compras a 
INNER JOIN cc_productos b ON a.id_sucursal = b.id_sucursal AND a.codigo = b.codigo 
LEFT JOIN cc_derivados c ON a.id_sucursal = c.id_sucursal AND a.codigo = c.codigo_p
LEFT JOIN cc_productos d ON c.id_sucursal = d.id_sucursal AND c.codigo_d = d.codigo
WHERE a.id_sucursal = $id_sucursal AND a.id_compra = $id_compra ;");
    while ($rowc = mysqli_fetch_assoc($sqlcompras)) {
        if ($rowc['codigo_p'] == null) {
            if ($rowc['contador'] == 1) {
                if ($rowc['centralizar_almacen'] == 1) {
                    recalcula_almacen_producto($link, $id_sucursal, $rowc['codigo'], $rowc['cantidad'], $fecha_act, $hora_act, $id_usuario_act);
                } else if ($rowc['centralizar_almacen'] == 2) {
                    recalcula_almacen_categoria($link, $id_sucursal, $rowc['id_categoria'], $rowc['cantidad'], $fecha_act, $hora_act, $id_usuario_act);
                }
            }
        } else {
            if ($rowc['centralizar_almacen_d'] == 1) {
                recalcula_almacen_producto($link, $id_sucursal, $rowc['codigo_d'], $rowc['cantidad'], $fecha_act, $hora_act, $id_usuario_act);
            } else if ($rowc['centralizar_almacen'] == 2) {
                $cantidad = round($rowc['cantidad'] * $rowc['porcentaje'] / 100, 3);
                recalcula_almacen_categoria($link, $id_sucursal, $rowc['id_categoria_d'], $cantidad, $fecha_act, $hora_act, $id_usuario_act);
            }
        }
    }
}

function recalcula_almacen_cancela_compra($link, $id_sucursal, $id_compra, $fecha_act, $hora_act, $id_usuario_act) {
    $sqlcompras = mysqli_query($link, "
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
FROM cc_compras a 
INNER JOIN cc_productos b ON a.id_sucursal = b.id_sucursal AND a.codigo = b.codigo 
LEFT JOIN cc_derivados c ON a.id_sucursal = c.id_sucursal AND a.codigo = c.codigo_p
LEFT JOIN cc_productos d ON c.id_sucursal = d.id_sucursal AND c.codigo_d = d.codigo
WHERE a.id_sucursal = $id_sucursal AND a.id_compra = $id_compra  and a.estatus in (0);");
    while ($rowv = mysqli_fetch_assoc($sqlcompras)) {
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

function recalcula_almacen_compra_producto($link, $id_sucursal, $id_compra, $id_consecutivo, $fecha_act, $hora_act, $id_usuario_act) {
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
    mysqli_query($link, "UPDATE cc_productos SET almacen = almacen + $cantidad, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and codigo = $codigo");
}

function recalcula_almacen_categoria($link, $id_sucursal, $id_categoria, $cantidad, $fecha_act, $hora_act, $id_usuario_act) {
    mysqli_query($link, "UPDATE cc_categorias SET almacen = almacen + $cantidad, fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act= $id_usuario_act WHERE id_sucursal= $id_sucursal and id_categoria = $id_categoria");
}
