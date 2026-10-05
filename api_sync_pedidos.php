<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/functions/config.php';
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
    $where = "WHERE p.id_sucursal = $idSucursal";
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

if ($action === 'complete_order') {
    $orderId = isset($_REQUEST['id_pedido']) ? (int)$_REQUEST['id_pedido'] : 0;
    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Folio de pedido no válido.']);
        exit;
    }

    $res = storefront_process_order_and_deduct_stock($link, $orderId, $idSucursal);
    echo json_encode($res);
    exit;
}

if ($action === 'update_status') {
    $orderId = isset($_REQUEST['id_pedido']) ? (int)$_REQUEST['id_pedido'] : 0;
    $newStatus = isset($_REQUEST['estatus']) ? mysqli_real_escape_string($link, $_REQUEST['estatus']) : '';

    $allowed = ['nuevo', 'en_preparacion', 'completado', 'cancelado'];
    if ($orderId <= 0 || !in_array($newStatus, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Estatus o folio no válido.']);
        exit;
    }

    if ($newStatus === 'completado') {
        $res = storefront_process_order_and_deduct_stock($link, $orderId, $idSucursal);
        echo json_encode($res);
        exit;
    }

    $upd = mysqli_query($link, "UPDATE cc_pedidos_web SET estatus = '$newStatus' WHERE id_pedido = $orderId AND id_sucursal = $idSucursal");
    if ($upd) {
        echo json_encode(['success' => true, 'message' => "Estatus actualizado a '$newStatus'."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo actualizar el estatus.']);
    }
    exit;
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
