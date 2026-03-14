<?php
session_start();

require_once __DIR__ . '/functions/config.php';

if (!isset($_SESSION['store_cart']) || !is_array($_SESSION['store_cart'])) {
    $_SESSION['store_cart'] = [];
}

$feedback = ['type' => '', 'message' => ''];
$orderSuccess = null;
$formData = [
    'cliente_nombre' => '',
    'cliente_telefono' => '',
    'cliente_email' => '',
    'tipo_entrega' => 'recoger',
    'direccion_entrega' => '',
    'notas' => '',
    'metodo_pago' => 'efectivo',
];

function storefront_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function storefront_money($value)
{
    return '$' . number_format((float) $value, 2);
}

function storefront_stock_label($stock)
{
    $stock = (float) $stock;
    if ($stock >= 20) {
        return 'Disponible hoy';
    }
    if ($stock > 0) {
        return 'Pocas piezas';
    }
    return 'Bajo pedido';
}

function storefront_normalize_quantity($value)
{
    $quantity = (float) str_replace(',', '.', (string) $value);
    if ($quantity < 0.25) {
        return 0.25;
    }
    return round($quantity, 2);
}

function storefront_ensure_order_tables(mysqli $link)
{
    $createOrders = "CREATE TABLE IF NOT EXISTS cc_pedidos_web (
        id_pedido INT NOT NULL AUTO_INCREMENT,
        cliente_nombre VARCHAR(150) NOT NULL,
        cliente_telefono VARCHAR(40) NOT NULL,
        cliente_email VARCHAR(120) NOT NULL DEFAULT '',
        tipo_entrega VARCHAR(20) NOT NULL DEFAULT 'recoger',
        direccion_entrega VARCHAR(255) NOT NULL DEFAULT '',
        notas TEXT NULL,
        metodo_pago VARCHAR(30) NOT NULL DEFAULT 'efectivo',
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        estatus VARCHAR(30) NOT NULL DEFAULT 'nuevo',
        fecha_ingreso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_pedido)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $createOrderItems = "CREATE TABLE IF NOT EXISTS cc_det_pedidos_web (
        id_detalle INT NOT NULL AUTO_INCREMENT,
        id_pedido INT NOT NULL,
        codigo VARCHAR(40) NOT NULL,
        descripcion VARCHAR(180) NOT NULL,
        precio_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        cantidad DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY (id_detalle),
        KEY idx_pedido (id_pedido),
        CONSTRAINT fk_det_pedido_web FOREIGN KEY (id_pedido)
            REFERENCES cc_pedidos_web(id_pedido)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!mysqli_query($link, $createOrders)) {
        return mysqli_error($link);
    }
    if (!mysqli_query($link, $createOrderItems)) {
        return mysqli_error($link);
    }
    return true;
}

function storefront_fetch_products(mysqli $link)
{
    $products = [];
    $sql = "
        SELECT
            p.codigo,
            p.descripcion,
            p.precio_venta,
            p.almacen,
            COALESCE(c.desc_categoria, 'Especialidad de la casa') AS categoria
        FROM cc_productos p
        LEFT JOIN cc_categorias c
            ON c.id_sucursal = p.id_sucursal
            AND c.id_categoria = p.id_categoria
        WHERE p.activo = 1
        ORDER BY categoria ASC, p.descripcion ASC
    ";

    if ($result = mysqli_query($link, $sql)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $row['precio_venta'] = (float) $row['precio_venta'];
            $row['almacen'] = (float) $row['almacen'];
            $products[$row['codigo']] = $row;
        }
    }

    if (!empty($products)) {
        return $products;
    }

    return [
        '101' => ['codigo' => '101', 'descripcion' => 'Arrachera marinada', 'precio_venta' => 239.00, 'almacen' => 18.5, 'categoria' => 'Cortes premium'],
        '102' => ['codigo' => '102', 'descripcion' => 'Rib eye selecto', 'precio_venta' => 289.00, 'almacen' => 12.0, 'categoria' => 'Cortes premium'],
        '103' => ['codigo' => '103', 'descripcion' => 'Milanesa de res', 'precio_venta' => 174.00, 'almacen' => 26.0, 'categoria' => 'Res'],
        '104' => ['codigo' => '104', 'descripcion' => 'Costilla cargada', 'precio_venta' => 169.00, 'almacen' => 22.0, 'categoria' => 'Parrilla'],
        '105' => ['codigo' => '105', 'descripcion' => 'Chuleta de cerdo', 'precio_venta' => 142.00, 'almacen' => 14.0, 'categoria' => 'Cerdo'],
        '106' => ['codigo' => '106', 'descripcion' => 'Pechuga de pollo', 'precio_venta' => 119.00, 'almacen' => 31.0, 'categoria' => 'Pollo'],
    ];
}

function storefront_cart_totals(array $cart)
{
    $totals = ['items' => 0, 'subtotal' => 0.0, 'total' => 0.0];
    foreach ($cart as $item) {
        $totals['items']++;
        $totals['subtotal'] += (float) $item['subtotal'];
    }
    $totals['subtotal'] = round($totals['subtotal'], 2);
    $totals['total'] = $totals['subtotal'];
    return $totals;
}

function storefront_build_url(array $params = [])
{
    $query = [];

    if (isset($_GET['categoria']) && trim((string) $_GET['categoria']) !== '') {
        $query['categoria'] = trim((string) $_GET['categoria']);
    }

    if (isset($_GET['buscar']) && trim((string) $_GET['buscar']) !== '') {
        $query['buscar'] = trim((string) $_GET['buscar']);
    }

    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
            continue;
        }
        $query[$key] = $value;
    }

    $queryString = http_build_query($query);
    return 'index.php' . ($queryString !== '' ? '?' . $queryString : '') . '#catalogo';
}

$products = storefront_fetch_products($link);
$selectedCategory = isset($_GET['categoria']) ? trim((string) $_GET['categoria']) : '';
$searchTerm = isset($_GET['buscar']) ? trim((string) $_GET['buscar']) : '';
$currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'add_to_cart') {
        $productCode = isset($_POST['product_code']) ? trim($_POST['product_code']) : '';
        $quantity = storefront_normalize_quantity(isset($_POST['quantity']) ? $_POST['quantity'] : 1);

        if (!isset($products[$productCode])) {
            $feedback = ['type' => 'danger', 'message' => 'El producto seleccionado ya no esta disponible.'];
        } else {
            $product = $products[$productCode];
            if (isset($_SESSION['store_cart'][$productCode])) {
                $quantity += (float) $_SESSION['store_cart'][$productCode]['quantity'];
            }

            $_SESSION['store_cart'][$productCode] = [
                'codigo' => $product['codigo'],
                'descripcion' => $product['descripcion'],
                'categoria' => $product['categoria'],
                'price' => (float) $product['precio_venta'],
                'quantity' => $quantity,
                'subtotal' => round($quantity * (float) $product['precio_venta'], 2),
            ];
            $feedback = ['type' => 'success', 'message' => $product['descripcion'] . ' se agrego al carrito.'];
        }
    }

    if ($action === 'update_cart' && isset($_POST['quantities']) && is_array($_POST['quantities'])) {
        foreach ($_POST['quantities'] as $code => $quantityValue) {
            if (!isset($_SESSION['store_cart'][$code])) {
                continue;
            }
            $quantity = storefront_normalize_quantity($quantityValue);
            $_SESSION['store_cart'][$code]['quantity'] = $quantity;
            $_SESSION['store_cart'][$code]['subtotal'] = round($quantity * (float) $_SESSION['store_cart'][$code]['price'], 2);
        }
        $feedback = ['type' => 'success', 'message' => 'El carrito se actualizo correctamente.'];
    }

    if ($action === 'checkout') {
        foreach ($formData as $key => $value) {
            if (isset($_POST[$key])) {
                $formData[$key] = trim((string) $_POST[$key]);
            }
        }

        $cartTotals = storefront_cart_totals($_SESSION['store_cart']);

        if (empty($_SESSION['store_cart'])) {
            $feedback = ['type' => 'danger', 'message' => 'Tu carrito esta vacio.'];
        } elseif ($formData['cliente_nombre'] === '' || $formData['cliente_telefono'] === '') {
            $feedback = ['type' => 'danger', 'message' => 'Nombre y telefono son obligatorios para registrar el pedido.'];
        } elseif ($formData['tipo_entrega'] === 'domicilio' && $formData['direccion_entrega'] === '') {
            $feedback = ['type' => 'danger', 'message' => 'La direccion es obligatoria para entrega a domicilio.'];
        } else {
            $tableStatus = storefront_ensure_order_tables($link);
            if ($tableStatus !== true) {
                $feedback = ['type' => 'danger', 'message' => 'No se pudo preparar la base para pedidos web: ' . $tableStatus];
            } else {
                mysqli_begin_transaction($link);
                try {
                    $orderStmt = mysqli_prepare($link, "INSERT INTO cc_pedidos_web (
                        cliente_nombre, cliente_telefono, cliente_email, tipo_entrega, direccion_entrega, notas, metodo_pago, subtotal, total, estatus
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'nuevo')");
                    if (!$orderStmt) {
                        throw new Exception(mysqli_error($link));
                    }

                    mysqli_stmt_bind_param($orderStmt, 'sssssssdd',
                        $formData['cliente_nombre'],
                        $formData['cliente_telefono'],
                        $formData['cliente_email'],
                        $formData['tipo_entrega'],
                        $formData['direccion_entrega'],
                        $formData['notas'],
                        $formData['metodo_pago'],
                        $cartTotals['subtotal'],
                        $cartTotals['total']
                    );

                    if (!mysqli_stmt_execute($orderStmt)) {
                        throw new Exception(mysqli_stmt_error($orderStmt));
                    }

                    $orderId = mysqli_insert_id($link);
                    $itemStmt = mysqli_prepare($link, "INSERT INTO cc_det_pedidos_web (
                        id_pedido, codigo, descripcion, precio_unitario, cantidad, subtotal
                    ) VALUES (?, ?, ?, ?, ?, ?)");
                    if (!$itemStmt) {
                        throw new Exception(mysqli_error($link));
                    }

                    foreach ($_SESSION['store_cart'] as $item) {
                        $price = (float) $item['price'];
                        $quantity = (float) $item['quantity'];
                        $subtotal = (float) $item['subtotal'];
                        mysqli_stmt_bind_param($itemStmt, 'issddd', $orderId, $item['codigo'], $item['descripcion'], $price, $quantity, $subtotal);
                        if (!mysqli_stmt_execute($itemStmt)) {
                            throw new Exception(mysqli_stmt_error($itemStmt));
                        }
                    }

                    mysqli_commit($link);
                    $orderSuccess = ['id' => $orderId, 'name' => $formData['cliente_nombre'], 'total' => $cartTotals['total']];
                    $_SESSION['store_cart'] = [];
                    foreach ($formData as $key => $value) {
                        $formData[$key] = $key === 'tipo_entrega' ? 'recoger' : ($key === 'metodo_pago' ? 'efectivo' : '');
                    }
                    $feedback = ['type' => 'success', 'message' => 'Pedido registrado correctamente con folio #' . $orderId . '.'];
                } catch (Exception $exception) {
                    mysqli_rollback($link);
                    $feedback = ['type' => 'danger', 'message' => 'No se pudo guardar el pedido: ' . $exception->getMessage()];
                }
            }
        }
    }
}

if (isset($_GET['remove'])) {
    $removeCode = trim((string) $_GET['remove']);
    if (isset($_SESSION['store_cart'][$removeCode])) {
        unset($_SESSION['store_cart'][$removeCode]);
        $feedback = ['type' => 'warning', 'message' => 'El producto se elimino del carrito.'];
    }
}

$cart = $_SESSION['store_cart'];
$cartTotals = storefront_cart_totals($cart);
$categoryCounts = [];
foreach ($products as $product) {
    if (!isset($categoryCounts[$product['categoria']])) {
        $categoryCounts[$product['categoria']] = 0;
    }
    $categoryCounts[$product['categoria']]++;
}
ksort($categoryCounts, SORT_NATURAL | SORT_FLAG_CASE);

$filteredProducts = [];
foreach ($products as $product) {
    $matchesCategory = $selectedCategory === '' || $product['categoria'] === $selectedCategory;
    $matchesSearch = $searchTerm === ''
        || stripos($product['descripcion'], $searchTerm) !== false
        || stripos($product['codigo'], $searchTerm) !== false
        || stripos($product['categoria'], $searchTerm) !== false;

    if ($matchesCategory && $matchesSearch) {
        $filteredProducts[] = $product;
    }
}

$productsPerPage = 10;
$totalFilteredProducts = count($filteredProducts);
$totalPages = max(1, (int) ceil($totalFilteredProducts / $productsPerPage));

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $productsPerPage;
$paginatedProducts = array_slice($filteredProducts, $offset, $productsPerPage);
