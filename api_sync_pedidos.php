<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/functions/config.php';
require_once __DIR__ . '/functions/sync_pedidos_web.php';
require_once __DIR__ . '/storefront_logic.php';

$link = $link ?? null;
if (!$link) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']);
    exit;
}

storefront_ensure_order_tables($link);

$action = $_REQUEST['action'] ?? 'get_orders';
$idSucursal = isset($_REQUEST['id_sucursal']) ? (int)$_REQUEST['id_sucursal'] : 1;

if ($action === 'get_orders') {
    $statusFilter = isset($_REQUEST['estatus']) ? mysqli_real_escape_string($link, $_REQUEST['estatus']) : '';
    $where = ($idSucursal > 0) ? "WHERE p.id_sucursal = $idSucursal" : "WHERE 1=1";
    if ($statusFilter !== '') {
        $where .= " AND p.estatus = '$statusFilter'";
    }

    $sql = "SELECT p.*, s.desc_sucursal 
            FROM cc_pedidos_web p 
            LEFT JOIN cc_sucursales s ON s.id_sucursal = p.id_sucursal 
            $where 
            ORDER BY p.fecha_ingreso DESC 
            LIMIT 100";

    $orders = [];
    if ($result = mysqli_query($link, $sql)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $orderId = (int)$row['id_pedido'];
            $items = [];
            $itemsSql = "SELECT * FROM cc_det_pedidos_web WHERE id_pedido = $orderId";
            if ($itemsRes = mysqli_query($link, $itemsSql)) {
                while ($itemRow = mysqli_fetch_assoc($itemsRes)) {
                    $itemRow['precio_unitario'] = (float)$itemRow['precio_unitario'];
                    $itemRow['cantidad'] = (float)$itemRow['cantidad'];
                    $itemRow['subtotal'] = (float)$itemRow['subtotal'];
                    $items[] = $itemRow;
                }
                mysqli_free_result($itemsRes);
            }
            $row['items'] = $items;
            $row['subtotal'] = (float)$row['subtotal'];
            $row['total'] = (float)$row['total'];
            $orders[] = $row;
        }
        mysqli_free_result($result);
    }

    echo json_encode(['success' => true, 'id_sucursal' => $idSucursal, 'count' => count($orders), 'orders' => $orders]);
    exit;
}

if ($action === 'sync_from_gcp') {
    $res = cc_sync_pedidos_desde_gcp($link, null, $idSucursal);
    echo json_encode($res);
    exit;
}

if ($action === 'complete_order') {
    $orderId = isset($_REQUEST['id_pedido']) ? (int)$_REQUEST['id_pedido'] : 0;
    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Folio de pedido no válido.']);
        exit;
    }

    $res = storefront_process_order_and_deduct_stock($link, $orderId, $idSucursal);
    if (!empty($res['success']) && function_exists('cc_sync_notificar_estatus_a_gcp')) {
        cc_sync_notificar_estatus_a_gcp($orderId, 'completado');
    }
    echo json_encode($res);
    exit;
}

if ($action === 'update_status') {
    $orderId = isset($_REQUEST['id_pedido']) ? (int)$_REQUEST['id_pedido'] : 0;
    $newStatus = isset($_REQUEST['estatus']) ? mysqli_real_escape_string($link, $_REQUEST['estatus']) : '';

    $allowed = ['nuevo', 'en_preparacion', 'en_camino', 'completado', 'cancelado'];
    if ($orderId <= 0 || !in_array($newStatus, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Estatus o folio no válido.']);
        exit;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $idRepartidor = isset($_REQUEST['id_repartidor']) ? (int)$_REQUEST['id_repartidor'] : (int)($_SESSION['id'] ?? 0);

    if ($newStatus === 'completado') {
        $res = storefront_process_order_and_deduct_stock($link, $orderId, $idSucursal);
        if (!empty($res['success'])) {
            $repSql = ($idRepartidor > 0) ? ", id_repartidor = $idRepartidor" : "";
            mysqli_query($link, "UPDATE cc_pedidos_web SET fecha_entrega = NOW() $repSql WHERE id_pedido = $orderId");
            if (function_exists('cc_sync_notificar_estatus_a_gcp')) {
                cc_sync_notificar_estatus_a_gcp($orderId, 'completado');
            }
        }
        echo json_encode($res);
        exit;
    }

    $whereBranch = ($idSucursal > 0) ? "AND id_sucursal = $idSucursal" : "";
    $repSet = ($idRepartidor > 0 && in_array($newStatus, ['en_camino', 'en_preparacion'])) ? ", id_repartidor = $idRepartidor" : "";
    $upd = mysqli_query($link, "UPDATE cc_pedidos_web SET estatus = '$newStatus' $repSet WHERE id_pedido = $orderId $whereBranch");
    if ($upd) {
        if (function_exists('cc_sync_notificar_estatus_a_gcp')) {
            cc_sync_notificar_estatus_a_gcp($orderId, $newStatus);
        }
        echo json_encode(['success' => true, 'message' => "Estatus actualizado a '$newStatus'."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo actualizar el estatus.']);
    }
    exit;
}

if ($action === 'load_to_pos') {
    $orderId = isset($_REQUEST['id_pedido']) ? (int)$_REQUEST['id_pedido'] : 0;
    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Folio de pedido inválido.']);
        exit;
    }

    $qOrder = mysqli_query($link, "SELECT * FROM cc_pedidos_web WHERE id_pedido = $orderId LIMIT 1");
    if (!$qOrder || !($order = mysqli_fetch_assoc($qOrder))) {
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado.']);
        exit;
    }

    $orderSucursal = (int)$order['id_sucursal'];
    $targetSucursal = ($orderSucursal > 0) ? $orderSucursal : $idSucursal;
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $userId = (int)($_SESSION['id'] ?? 1);
    $fecha = date('Y-m-d');
    $hora = date('H:i:s');

    $maxQuery = mysqli_query($link, "SELECT GREATEST(
        COALESCE((SELECT MAX(id_venta) FROM cc_ventas WHERE id_sucursal = $targetSucursal), 0),
        COALESCE((SELECT MAX(id_venta) FROM cc_det_ventas WHERE id_sucursal = $targetSucursal), 0)
    ) as max_id");
    $maxRow = $maxQuery ? mysqli_fetch_assoc($maxQuery) : null;
    $newIdVenta = ((int)($maxRow['max_id'] ?? 0)) + 1;

    $tipoPago = ($order['metodo_pago'] === 'efectivo') ? 1 : 2;
    $total = (float)$order['total'];

    mysqli_begin_transaction($link);
    try {
        $insHeader = mysqli_query($link, "INSERT INTO cc_det_ventas 
            (id_sucursal, id_venta, estatus, id_cierre, id_cliente, id_empleado, pagado, importe_recibido, tipo_pago, id_usuario, fecha_ingreso, hora_ingreso, fecha_act, hora_act, id_usuario_act)
            VALUES 
            ($targetSucursal, $newIdVenta, 0, 0, 0, 0, 0, $total, $tipoPago, $userId, '$fecha', '$hora', '$fecha', '$hora', $userId)");

        if (!$insHeader) {
            throw new Exception("Error al crear encabezado de venta: " . mysqli_error($link));
        }

        $qItems = mysqli_query($link, "SELECT * FROM cc_det_pedidos_web WHERE id_pedido = $orderId");
        $consecutivo = 1;
        while ($item = mysqli_fetch_assoc($qItems)) {
            $codigoEsc = mysqli_real_escape_string($link, $item['codigo']);
            $cant = (float)$item['cantidad'];
            $pu = (float)$item['precio_unitario'];

            $prodQuery = mysqli_query($link, "SELECT precio_compra FROM cc_productos WHERE id_sucursal = $targetSucursal AND codigo = '$codigoEsc' LIMIT 1");
            $prodRow = $prodQuery ? mysqli_fetch_assoc($prodQuery) : null;
            $precioCompra = (float)($prodRow['precio_compra'] ?? 0.00);

            $insItem = mysqli_query($link, "INSERT INTO cc_ventas
                (id_sucursal, id_venta, id_consecutivo, codigo, precio_compra, precio_venta, cantidad, clave_externa, estatus, id_usuario, fecha_ingreso, hora_ingreso, fecha_act, hora_act, id_usuario_act)
                VALUES
                ($targetSucursal, $newIdVenta, $consecutivo, '$codigoEsc', $precioCompra, $pu, $cant, 'ONLINE', 0, $userId, '$fecha', '$hora', '$fecha', '$hora', $userId)");

            if (!$insItem) {
                throw new Exception("Error al insertar partida en POS: " . mysqli_error($link));
            }
            $consecutivo++;
        }

        mysqli_query($link, "UPDATE cc_pedidos_web SET estatus = 'en_preparacion', id_venta = $newIdVenta WHERE id_pedido = $orderId");
        if (function_exists('cc_sync_notificar_estatus_a_gcp')) {
            cc_sync_notificar_estatus_a_gcp($orderId, 'en_preparacion', $newIdVenta);
        }

        mysqli_commit($link);

        echo json_encode([
            'success' => true,
            'id_venta' => $newIdVenta,
            'id_pedido' => $orderId,
            'redirect_url' => 'inicio.php?id_venta=' . $newIdVenta,
            'message' => "Pedido #$orderId cargado con éxito en Punto de Venta como Venta #$newIdVenta."
        ]);
        exit;
    } catch (Throwable $e) {
        mysqli_rollback($link);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'get_branch_stock') {
    $sql = "SELECT codigo, descripcion, almacen, precio_venta FROM cc_productos WHERE id_sucursal = $idSucursal AND activo = 1 ORDER BY descripcion ASC";
    $products = [];
    if ($result = mysqli_query($link, $sql)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $row['almacen'] = (float)$row['almacen'];
            $row['precio_venta'] = (float)$row['precio_venta'];
            $products[] = $row;
        }
        mysqli_free_result($result);
    }
    echo json_encode(['success' => true, 'id_sucursal' => $idSucursal, 'products' => $products]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
