<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once __DIR__ . "/../functions/config.php";
require_once __DIR__ . "/../storefront_logic.php";
date_default_timezone_set("America/Mexico_City");

$rolUsuario = (int) ($_SESSION["rol"] ?? 0);
$idUsuario = (int) ($_SESSION["id"] ?? 0);
$nombreUsuario = $_SESSION["nombre"] ?? "Repartidor";
$idSucursalSesion = (int) ($_SESSION["id_sucursal"] ?? 1);

// Asegurar que las columnas existan en cc_pedidos_web
storefront_ensure_order_tables($link);

// Si es administrador o sistemas, puede consultar cualquier sucursal
$esAdmin = tienePermiso('ver');
$id_sucursal = $idSucursalSesion;
if ($esAdmin && isset($_GET['sucursal']) && (int) $_GET['sucursal'] > 0) {
    $id_sucursal = (int) $_GET['sucursal'];
}

// Pestaña activa ('pendientes' o 'historial')
$tab = isset($_GET['tab']) && $_GET['tab'] === 'historial' ? 'historial' : 'pendientes';

// Mensaje de retroalimentación
$mensaje = '';
$tipoMensaje = 'success';

// Procesamiento de acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $idPedidoAccion = (int) ($_POST['id_pedido'] ?? 0);

    if ($accion === 'en_camino' && $idPedidoAccion > 0) {
        $upd = mysqli_query($link, "UPDATE cc_pedidos_web SET estatus = 'en_camino', id_repartidor = $idUsuario WHERE id_pedido = $idPedidoAccion AND id_sucursal = $id_sucursal");
        if ($upd) {
            if (function_exists('cc_sync_notificar_estatus_a_gcp')) {
                cc_sync_notificar_estatus_a_gcp($idPedidoAccion, 'en_camino');
            }
            $mensaje = "El pedido #$idPedidoAccion se marcó 'En camino'. ¡Buen viaje!";
            $tipoMensaje = 'info';
        } else {
            $mensaje = "Error al actualizar el pedido: " . mysqli_error($link);
            $tipoMensaje = 'danger';
        }
    } elseif ($accion === 'entregado' && $idPedidoAccion > 0) {
        // Procesar stock y POS si aún no se había descontado
        $res = storefront_process_order_and_deduct_stock($link, $idPedidoAccion, $id_sucursal, $idUsuario);
        if (!empty($res['success'])) {
            mysqli_query($link, "UPDATE cc_pedidos_web SET fecha_entrega = NOW(), id_repartidor = $idUsuario WHERE id_pedido = $idPedidoAccion");
            if (function_exists('cc_sync_notificar_estatus_a_gcp')) {
                cc_sync_notificar_estatus_a_gcp($idPedidoAccion, 'completado');
            }
            $mensaje = "¡Excelente! El pedido #$idPedidoAccion ha sido entregado exitosamente.";
            $tipoMensaje = 'success';
        } else {
            // Si ya estaba completado en stock, solo actualizar entrega
            mysqli_query($link, "UPDATE cc_pedidos_web SET estatus = 'completado', fecha_entrega = NOW(), id_repartidor = $idUsuario WHERE id_pedido = $idPedidoAccion");
            $mensaje = "El pedido #$idPedidoAccion se registró como entregado.";
            $tipoMensaje = 'success';
        }
    }
}

// Consulta de pedidos pendientes de la sucursal (en_camino, en_preparacion, nuevo)
$sqlPendientes = "SELECT p.*, s.desc_sucursal, u.nombre as repartidor_nombre 
    FROM cc_pedidos_web p 
    LEFT JOIN cc_sucursales s ON p.id_sucursal = s.id_sucursal 
    LEFT JOIN cc_users u ON p.id_repartidor = u.id 
    WHERE p.id_sucursal = $id_sucursal 
      AND p.estatus IN ('nuevo', 'en_preparacion', 'en_camino')
    ORDER BY 
      CASE p.estatus 
        WHEN 'en_camino' THEN 1 
        WHEN 'en_preparacion' THEN 2 
        WHEN 'nuevo' THEN 3 
        ELSE 4 
      END ASC, 
      p.id_pedido ASC";
$resPendientes = mysqli_query($link, $sqlPendientes);
$pedidosPendientes = [];
if ($resPendientes) {
    while ($row = mysqli_fetch_assoc($resPendientes)) {
        // Obtener productos del pedido
        $idPed = (int) $row['id_pedido'];
        $itemsRes = mysqli_query($link, "SELECT * FROM cc_det_pedidos_web WHERE id_pedido = $idPed");
        $items = [];
        if ($itemsRes) {
            while ($item = mysqli_fetch_assoc($itemsRes)) {
                $items[] = $item;
            }
        }
        $row['items'] = $items;
        $pedidosPendientes[] = $row;
    }
}
$totalPendientes = count($pedidosPendientes);

// Parámetros de filtro para el historial
$filtroRango = $_GET['rango'] ?? 'hoy';
$fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-d');
$fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$busquedaHistorial = trim($_GET['buscar'] ?? '');

if ($filtroRango === 'hoy') {
    $fechaDesde = date('Y-m-d');
    $fechaHasta = date('Y-m-d');
} elseif ($filtroRango === 'ayer') {
    $fechaDesde = date('Y-m-d', strtotime('-1 day'));
    $fechaHasta = date('Y-m-d', strtotime('-1 day'));
} elseif ($filtroRango === 'semana') {
    $fechaDesde = date('Y-m-d', strtotime('-7 days'));
    $fechaHasta = date('Y-m-d');
} elseif ($filtroRango === 'mes') {
    $fechaDesde = date('Y-m-01');
    $fechaHasta = date('Y-m-d');
}

// Consulta de historial
$whereHistorial = "p.id_sucursal = $id_sucursal AND p.estatus = 'completado'";
if ($fechaDesde !== '' && $fechaHasta !== '') {
    $whereHistorial .= " AND (DATE(COALESCE(p.fecha_entrega, p.fecha_ingreso)) BETWEEN '$fechaDesde' AND '$fechaHasta')";
}
if ($busquedaHistorial !== '') {
    $busqEsc = mysqli_real_escape_string($link, $busquedaHistorial);
    $whereHistorial .= " AND (p.id_pedido LIKE '%$busqEsc%' OR p.cliente_nombre LIKE '%$busqEsc%' OR p.direccion_entrega LIKE '%$busqEsc%' OR p.cliente_telefono LIKE '%$busqEsc%')";
}

$sqlHistorial = "SELECT p.*, s.desc_sucursal, u.nombre as repartidor_nombre 
    FROM cc_pedidos_web p 
    LEFT JOIN cc_sucursales s ON p.id_sucursal = s.id_sucursal 
    LEFT JOIN cc_users u ON p.id_repartidor = u.id 
    WHERE $whereHistorial 
    ORDER BY COALESCE(p.fecha_entrega, p.fecha_ingreso) DESC 
    LIMIT 150";
$resHistorial = mysqli_query($link, $sqlHistorial);
$pedidosHistorial = [];
$totalCobradoEfectivo = 0.0;
$totalCobradoOtros = 0.0;
$totalGeneralHistorial = 0.0;

if ($resHistorial) {
    while ($row = mysqli_fetch_assoc($resHistorial)) {
        $idPed = (int) $row['id_pedido'];
        $itemsRes = mysqli_query($link, "SELECT * FROM cc_det_pedidos_web WHERE id_pedido = $idPed");
        $items = [];
        if ($itemsRes) {
            while ($item = mysqli_fetch_assoc($itemsRes)) {
                $items[] = $item;
            }
        }
        $row['items'] = $items;
        $totalP = (float) $row['total'];
        $totalGeneralHistorial += $totalP;
        if (strtolower($row['metodo_pago']) === 'efectivo') {
            $totalCobradoEfectivo += $totalP;
        } else {
            $totalCobradoOtros += $totalP;
        }
        $pedidosHistorial[] = $row;
    }
}
$countHistorial = count($pedidosHistorial);

// Listado de sucursales para el filtro de administrador
$sucursalesLista = [];
if ($esAdmin) {
    $qSuc = mysqli_query($link, "SELECT id_sucursal, desc_sucursal FROM cc_sucursales ORDER BY id_sucursal ASC");
    if ($qSuc) {
        while ($sRow = mysqli_fetch_assoc($qSuc)) {
            $sucursalesLista[] = $sRow;
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Módulo de Repartos - Carnicerías "EL Cinco"">
    <meta name="author" content="Carnicerías "EL Cinco"">
    <link rel="shortcut icon" href="../img/logo_1.png">
    <title>Repartidor - Carnicerías "EL Cinco"</title>
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-icons.css">
    <script src="../js/jquery-3.5.1.js"></script>
    <script src="../js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background-color: #f4f6f9;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .order-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            margin-bottom: 20px;
            background: #fff;
            overflow: hidden;
        }
        .order-card:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
        }
        .order-card.status-en_camino {
            border-left: 6px solid #0d6efd;
        }
        .order-card.status-en_preparacion {
            border-left: 6px solid #ffc107;
        }
        .order-card.status-nuevo {
            border-left: 6px solid #dc3545;
        }
        .order-card.status-completado {
            border-left: 6px solid #198754;
        }
        .status-badge {
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .btn-action-call {
            background-color: #0d6efd;
            color: #fff;
            border-radius: 8px;
        }
        .btn-action-call:hover {
            background-color: #0b5ed7;
            color: #fff;
        }
        .btn-action-wa {
            background-color: #25d366;
            color: #fff;
            border-radius: 8px;
            font-weight: 600;
        }
        .btn-action-wa:hover {
            background-color: #1ebc59;
            color: #fff;
        }
        .btn-action-maps {
            background-color: #ea4335;
            color: #fff;
            border-radius: 8px;
            font-weight: 600;
        }
        .btn-action-maps:hover {
            background-color: #d33426;
            color: #fff;
        }
        .ref-box {
            background-color: #fff8e1;
            border: 1px dashed #f59e0b;
            border-radius: 8px;
            padding: 10px 14px;
            color: #92400e;
            font-size: 0.95rem;
        }
        .total-badge {
            font-size: 1.45rem;
            font-weight: 800;
            color: #198754;
        }
        .tab-btn {
            font-size: 1.05rem;
            font-weight: 700;
            padding: 12px 20px;
            border-radius: 10px;
        }
        .kpi-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            background: #fff;
            padding: 16px;
            margin-bottom: 16px;
        }
        .kpi-number {
            font-size: 1.6rem;
            font-weight: 800;
        }
        .product-item {
            border-bottom: 1px solid #f1f3f5;
            padding: 6px 0;
            font-size: 0.92rem;
        }
        .product-item:last-child {
            border-bottom: none;
        }
        @media print {
            body * {
                visibility: hidden;
            }
            #modalTicketPrint, #modalTicketPrint * {
                visibility: visible;
            }
            #modalTicketPrint {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
<main class="mb-5">
    <div class="container-fluid px-md-4">
        <?php require_once __DIR__ . "/../components/nav.php"; ?>

        <!-- Encabezado del Módulo -->
        <div class="row align-items-center mt-3 mb-3">
            <div class="col-12 col-md-6 mb-2 mb-md-0">
                <h2 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-bicycle text-primary me-2"></i>Pedidos para Reparto
                </h2>
                <div class="text-muted mt-1">
                    <span><i class="bi bi-person-fill text-secondary me-1"></i>Repartidor: <strong><?php echo htmlspecialchars($nombreUsuario); ?></strong></span>
                    <span class="mx-2">•</span>
                    <span><i class="bi bi-geo-alt-fill text-danger me-1"></i>Sucursal: <strong><?php echo htmlspecialchars($_SESSION["desc_sucursal"] ?? ("Sucursal " . $id_sucursal)); ?></strong></span>
                </div>
            </div>
            <div class="col-12 col-md-6 text-md-end">
                <?php if ($esAdmin): ?>
                    <form method="get" class="d-inline-flex align-items-center me-2">
                        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                        <label class="form-label me-2 mb-0 fw-bold small text-muted">Sucursal:</label>
                        <select name="sucursal" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                            <?php foreach ($sucursalesLista as $suc): ?>
                                <option value="<?php echo (int)$suc['id_sucursal']; ?>" <?php echo ($id_sucursal === (int)$suc['id_sucursal']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($suc['desc_sucursal']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill fw-bold" onclick="location.reload();">
                    <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
                </button>
            </div>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-<?php echo $tipoMensaje; ?> alert-dismissible fade show shadow-sm" role="alert">
                <strong><i class="bi bi-info-circle-fill me-2"></i></strong> <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Navegación de Pestañas: Pendientes vs Historial -->
        <ul class="nav nav-pills mb-4 gap-2">
            <li class="nav-item">
                <a class="nav-link tab-btn <?php echo ($tab === 'pendientes') ? 'active bg-primary' : 'bg-white text-dark shadow-sm'; ?>" 
                   href="pedidos.php?tab=pendientes<?php echo $esAdmin ? '&sucursal=' . $id_sucursal : ''; ?>">
                    <i class="bi bi-box-seam me-2"></i>Pedidos Pendientes
                    <?php if ($totalPendientes > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-2"><?php echo $totalPendientes; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link tab-btn <?php echo ($tab === 'historial') ? 'active bg-primary' : 'bg-white text-dark shadow-sm'; ?>" 
                   href="pedidos.php?tab=historial<?php echo $esAdmin ? '&sucursal=' . $id_sucursal : ''; ?>">
                    <i class="bi bi-clock-history me-2"></i>Historial de Entregas
                </a>
            </li>
        </ul>

        <!-- ========================================== -->
        <!-- PESTAÑA 1: PEDIDOS PENDIENTES             -->
        <!-- ========================================== -->
        <?php if ($tab === 'pendientes'): ?>
            <?php if (empty($pedidosPendientes)): ?>
                <div class="card shadow-sm border-0 py-5 text-center bg-white rounded-3">
                    <div class="card-body">
                        <i class="bi bi-check2-circle text-success" style="font-size: 4rem;"></i>
                        <h4 class="fw-bold mt-3 text-dark">¡Todo listo! No hay entregas pendientes</h4>
                        <p class="text-muted">No se encontraron pedidos pendientes de entrega para esta sucursal en este momento.</p>
                        <button class="btn btn-outline-primary fw-bold mt-2 rounded-pill px-4" onclick="location.reload();">
                            <i class="bi bi-arrow-repeat me-1"></i> Verificar si hay nuevos pedidos
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($pedidosPendientes as $ped): 
                        $idPed = (int) $ped['id_pedido'];
                        $telLimpio = preg_replace('/[^\d]/', '', $ped['cliente_telefono']);
                        $direccionTxt = $ped['direccion_entrega'] ?: 'Sin dirección especificada';
                        $tieneCoords = (!empty($ped['latitud']) && !empty($ped['longitud']));
                        $urlMaps = $tieneCoords 
                            ? "https://www.google.com/maps/dir/?api=1&destination=" . urlencode($ped['latitud'] . ',' . $ped['longitud'])
                            : "https://www.google.com/maps/dir/?api=1&destination=" . urlencode($direccionTxt);
                        $msjWa = "Hola " . ($ped['cliente_nombre'] ?: 'Cliente') . ", te escribo de Carnicerías \"EL Cinco\" para la entrega de tu pedido #" . $idPed . ".";
                        $urlWa = "https://wa.me/52" . $telLimpio . "?text=" . urlencode($msjWa);
                        
                        $statusClass = 'status-' . $ped['estatus'];
                        $statusBadgeClass = 'bg-secondary';
                        $statusTexto = 'Pendiente';
                        if ($ped['estatus'] === 'en_camino') {
                            $statusBadgeClass = 'bg-primary text-white';
                            $statusTexto = '<i class="bi bi-truck me-1"></i> En Camino';
                        } elseif ($ped['estatus'] === 'en_preparacion') {
                            $statusBadgeClass = 'bg-warning text-dark';
                            $statusTexto = '<i class="bi bi-hourglass-split me-1"></i> En Preparación';
                        } elseif ($ped['estatus'] === 'nuevo') {
                            $statusBadgeClass = 'bg-danger text-white';
                            $statusTexto = '<i class="bi bi-bell-fill me-1"></i> Nuevo Pedido';
                        }
                    ?>
                        <div class="col-12 col-lg-6 col-xl-4">
                            <div class="card order-card <?php echo $statusClass; ?>">
                                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="fw-bold mb-0 text-dark">
                                            Folio #<?php echo $idPed; ?>
                                        </h5>
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($ped['fecha_ingreso'])); ?>
                                        </small>
                                    </div>
                                    <span class="status-badge <?php echo $statusBadgeClass; ?>">
                                        <?php echo $statusTexto; ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <!-- Cliente y Teléfono -->
                                    <div class="mb-3">
                                        <label class="text-muted small fw-bold text-uppercase d-block mb-1">Cliente</label>
                                        <h6 class="fw-bold text-dark mb-2">
                                            <i class="bi bi-person-fill text-secondary me-1"></i>
                                            <?php echo htmlspecialchars($ped['cliente_nombre'] ?: 'Público General'); ?>
                                        </h6>
                                        <?php if (!empty($ped['cliente_telefono'])): ?>
                                            <div class="d-flex gap-2">
                                                <a href="tel:<?php echo htmlspecialchars($telLimpio); ?>" class="btn btn-action-call btn-sm flex-fill fw-bold">
                                                    <i class="bi bi-telephone-fill me-1"></i> Llamar (<?php echo htmlspecialchars($ped['cliente_telefono']); ?>)
                                                </a>
                                                <a href="<?php echo $urlWa; ?>" target="_blank" class="btn btn-action-wa btn-sm flex-fill fw-bold">
                                                    <i class="bi bi-whatsapp me-1"></i> WhatsApp
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">Sin teléfono registrado</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Dirección y Mapa -->
                                    <div class="mb-3">
                                        <label class="text-muted small fw-bold text-uppercase d-block mb-1">Dirección de Entrega</label>
                                        <p class="mb-2 text-dark" style="font-size: 0.95rem;">
                                            <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                                            <strong><?php echo htmlspecialchars($direccionTxt); ?></strong>
                                        </p>
                                        <?php if (!empty($ped['referencias_ubicacion'])): ?>
                                            <div class="ref-box mb-2">
                                                <strong><i class="bi bi-info-circle-fill me-1"></i> Referencias:</strong> 
                                                <?php echo htmlspecialchars($ped['referencias_ubicacion']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <a href="<?php echo $urlMaps; ?>" target="_blank" class="btn btn-action-maps btn-sm w-100 fw-bold">
                                            <i class="bi bi-map-fill me-1"></i> Abrir en Google Maps / Navegar
                                        </a>
                                    </div>

                                    <!-- Método de Pago y Total -->
                                    <div class="mb-3 p-3 bg-light rounded-3 d-flex justify-content-between align-items-center">
                                        <div>
                                            <label class="text-muted small fw-bold text-uppercase d-block mb-0">Cobrar al cliente</label>
                                            <span class="badge bg-secondary text-uppercase fw-bold">
                                                <i class="bi bi-credit-card-2-front me-1"></i><?php echo htmlspecialchars($ped['metodo_pago'] ?: 'Efectivo'); ?>
                                            </span>
                                            <?php if (!empty($ped['monto_pago_efectivo'])): ?>
                                                <small class="d-block text-danger fw-bold mt-1">
                                                    <i class="bi bi-cash-coin me-1"></i><?php echo htmlspecialchars($ped['monto_pago_efectivo']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <span class="total-badge">$<?php echo number_format((float)$ped['total'], 2); ?></span>
                                            <?php if ((float)$ped['costo_envio'] > 0): ?>
                                                <small class="d-block text-muted">Incluye envío: $<?php echo number_format((float)$ped['costo_envio'], 2); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Productos Acordión / Desplegable -->
                                    <div class="mb-3">
                                        <button class="btn btn-sm btn-outline-secondary w-100 fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseItems<?php echo $idPed; ?>">
                                            <i class="bi bi-basket me-1"></i> Ver productos (<?php echo count($ped['items']); ?> artículos)
                                        </button>
                                        <div class="collapse mt-2" id="collapseItems<?php echo $idPed; ?>">
                                            <div class="p-2 border rounded bg-white">
                                                <?php if (empty($ped['items'])): ?>
                                                    <span class="text-muted small">Sin detalle registrado</span>
                                                <?php else: ?>
                                                    <?php foreach ($ped['items'] as $item): ?>
                                                        <div class="product-item d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong><?php echo (float)$item['cantidad']; ?>x</strong> 
                                                                <?php echo htmlspecialchars($item['descripcion']); ?>
                                                                <?php if (!empty($item['comentario'])): ?>
                                                                    <small class="d-block text-primary"><i class="bi bi-chat-left-dots me-1"></i><?php echo htmlspecialchars($item['comentario']); ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                            <span class="text-muted fw-bold">$<?php echo number_format((float)$item['subtotal'], 2); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                                <?php if (!empty($ped['notas'])): ?>
                                                    <div class="mt-2 pt-2 border-top text-muted small">
                                                        <strong>Notas generales:</strong> <?php echo htmlspecialchars($ped['notas']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Acciones de Reparto -->
                                    <div class="d-grid gap-2">
                                        <?php if ($ped['estatus'] !== 'en_camino'): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="accion" value="en_camino">
                                                <input type="hidden" name="id_pedido" value="<?php echo $idPed; ?>">
                                                <button type="submit" class="btn btn-warning text-dark w-100 fw-bold py-2 shadow-sm">
                                                    <i class="bi bi-bicycle me-1"></i> Salir a Ruta / En Camino
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <button type="button" class="btn btn-success w-100 fw-bold py-2 shadow-sm" 
                                                onclick="confirmarEntrega(<?php echo $idPed; ?>, '<?php echo htmlspecialchars(addslashes($ped['cliente_nombre'] ?: 'Cliente')); ?>', '<?php echo number_format((float)$ped['total'], 2); ?>', '<?php echo htmlspecialchars(addslashes($ped['metodo_pago'] ?: 'Efectivo')); ?>');">
                                            <i class="bi bi-check-circle-fill me-1"></i> Marcar como Entregado
                                        </button>

                                        <button type="button" class="btn btn-outline-dark btn-sm fw-bold" onclick="imprimirTicketPedido(<?php echo $idPed; ?>);">
                                            <i class="bi bi-printer-fill me-1"></i> Imprimir Nota / Comanda
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <!-- ========================================== -->
        <!-- PESTAÑA 2: HISTORIAL DE ENTREGAS          -->
        <!-- ========================================== -->
        <?php else: ?>
            <!-- Tarjetas de Resumen (KPIs) -->
            <div class="row mb-3">
                <div class="col-6 col-md-3">
                    <div class="kpi-card text-center">
                        <span class="text-muted small fw-bold text-uppercase">Entregas Realizadas</span>
                        <div class="kpi-number text-primary"><?php echo $countHistorial; ?></div>
                        <small class="text-muted">Pedidos</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="kpi-card text-center">
                        <span class="text-muted small fw-bold text-uppercase">Total Efectivo</span>
                        <div class="kpi-number text-success">$<?php echo number_format($totalCobradoEfectivo, 2); ?></div>
                        <small class="text-muted">En mano / caja</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="kpi-card text-center">
                        <span class="text-muted small fw-bold text-uppercase">Transf. / Tarjeta</span>
                        <div class="kpi-number text-info">$<?php echo number_format($totalCobradoOtros, 2); ?></div>
                        <small class="text-muted">Digital</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="kpi-card text-center">
                        <span class="text-muted small fw-bold text-uppercase">Total General</span>
                        <div class="kpi-number text-dark">$<?php echo number_format($totalGeneralHistorial, 2); ?></div>
                        <small class="text-muted">Recaudado</small>
                    </div>
                </div>
            </div>

            <!-- Filtros de Historial -->
            <div class="card shadow-sm border-0 mb-4 bg-white rounded-3">
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-center">
                        <input type="hidden" name="tab" value="historial">
                        <?php if ($esAdmin): ?>
                            <input type="hidden" name="sucursal" value="<?php echo $id_sucursal; ?>">
                        <?php endif; ?>

                        <!-- Botones rápidos de rango -->
                        <div class="col-12 col-md-auto mb-2 mb-md-0">
                            <div class="btn-group w-100" role="group">
                                <a href="pedidos.php?tab=historial&rango=hoy<?php echo $esAdmin ? '&sucursal=' . $id_sucursal : ''; ?>" 
                                   class="btn btn-sm <?php echo ($filtroRango === 'hoy') ? 'btn-primary' : 'btn-outline-secondary'; ?> fw-bold">Hoy</a>
                                <a href="pedidos.php?tab=historial&rango=ayer<?php echo $esAdmin ? '&sucursal=' . $id_sucursal : ''; ?>" 
                                   class="btn btn-sm <?php echo ($filtroRango === 'ayer') ? 'btn-primary' : 'btn-outline-secondary'; ?> fw-bold">Ayer</a>
                                <a href="pedidos.php?tab=historial&rango=semana<?php echo $esAdmin ? '&sucursal=' . $id_sucursal : ''; ?>" 
                                   class="btn btn-sm <?php echo ($filtroRango === 'semana') ? 'btn-primary' : 'btn-outline-secondary'; ?> fw-bold">7 Días</a>
                                <a href="pedidos.php?tab=historial&rango=mes<?php echo $esAdmin ? '&sucursal=' . $id_sucursal : ''; ?>" 
                                   class="btn btn-sm <?php echo ($filtroRango === 'mes') ? 'btn-primary' : 'btn-outline-secondary'; ?> fw-bold">Mes</a>
                            </div>
                        </div>

                        <!-- Selector de Fechas Personalizadas -->
                        <div class="col-6 col-md-2">
                            <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($fechaDesde); ?>">
                        </div>
                        <div class="col-6 col-md-2">
                            <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?php echo htmlspecialchars($fechaHasta); ?>">
                        </div>

                        <!-- Buscador por texto -->
                        <div class="col-12 col-md-3">
                            <input type="text" name="buscar" class="form-control form-control-sm" placeholder="Buscar folio, cliente, dirección..." value="<?php echo htmlspecialchars($busquedaHistorial); ?>">
                        </div>

                        <div class="col-12 col-md-auto">
                            <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                                <i class="bi bi-search me-1"></i> Filtrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Listado de Historial -->
            <?php if (empty($pedidosHistorial)): ?>
                <div class="card shadow-sm border-0 py-5 text-center bg-white rounded-3">
                    <div class="card-body">
                        <i class="bi bi-calendar-x text-muted" style="font-size: 3.5rem;"></i>
                        <h5 class="fw-bold mt-3 text-dark">No se encontraron entregas en el período seleccionado</h5>
                        <p class="text-muted">Prueba cambiando las fechas o el término de búsqueda.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm border-0 bg-white rounded-3 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Folio</th>
                                    <th>Fecha / Hora Entrega</th>
                                    <th>Cliente</th>
                                    <th>Dirección</th>
                                    <th>Método de Pago</th>
                                    <th class="text-end">Total Cobrado</th>
                                    <th>Repartidor</th>
                                    <th class="text-center">Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidosHistorial as $h): 
                                    $idPed = (int) $h['id_pedido'];
                                    $fechaEntregaTxt = !empty($h['fecha_entrega']) 
                                        ? date('d/m/Y H:i', strtotime($h['fecha_entrega'])) 
                                        : date('d/m/Y H:i', strtotime($h['fecha_ingreso']));
                                ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary">#<?php echo $idPed; ?></strong>
                                        </td>
                                        <td>
                                            <span class="small"><i class="bi bi-check-circle-fill text-success me-1"></i><?php echo $fechaEntregaTxt; ?></span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($h['cliente_nombre'] ?: 'Público General'); ?></strong>
                                            <?php if (!empty($h['cliente_telefono'])): ?>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($h['cliente_telefono']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width: 250px;">
                                            <small class="text-dark d-block text-truncate" title="<?php echo htmlspecialchars($h['direccion_entrega']); ?>">
                                                <?php echo htmlspecialchars($h['direccion_entrega'] ?: 'Mostrador'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border text-uppercase">
                                                <?php echo htmlspecialchars($h['metodo_pago'] ?: 'Efectivo'); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <strong class="text-success fs-6">$<?php echo number_format((float)$h['total'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($h['repartidor_nombre'] ?: 'Sucursal'); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="abrirModalDetalleHistorial(<?php echo htmlspecialchars(json_encode($h), ENT_QUOTES, 'UTF-8'); ?>)">
                                                <i class="bi bi-eye-fill"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-dark fw-bold ms-1" onclick="imprimirTicketPedido(<?php echo $idPed; ?>)">
                                                <i class="bi bi-printer-fill"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Modal de Confirmación de Entrega -->
<div class="modal fade" id="modalConfirmarEntrega" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-check-circle me-2"></i>Confirmar Entrega de Pedido</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="post">
                <input type="hidden" name="accion" value="entregado">
                <input type="hidden" name="id_pedido" id="modalEntregarIdPedido" value="0">
                <div class="modal-body text-center py-4">
                    <i class="bi bi-box2-heart text-success" style="font-size: 3.5rem;"></i>
                    <h5 class="fw-bold mt-2" id="modalEntregarClienteNombre">Cliente</h5>
                    <p class="text-muted mb-3" id="modalEntregarDetalleTexto">¿Deseas registrar este pedido como entregado?</p>
                    <div class="p-3 bg-light rounded-3 d-inline-block text-center px-4">
                        <small class="text-muted text-uppercase fw-bold d-block">Monto Cobrado</small>
                        <span class="fs-2 fw-bold text-success" id="modalEntregarMonto">$0.00</span>
                        <small class="d-block text-dark fw-bold" id="modalEntregarMetodo">Efectivo</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">
                        <i class="bi bi-check-lg me-1"></i> Sí, Marcar Entregado
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detalle de Historial -->
<div class="modal fade" id="modalDetalleHistorial" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="modalHistorialTitulo">Detalle de Entrega</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="modalHistorialCuerpo">
                <!-- Se llena dinámicamente con JS -->
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-dark fw-bold" onclick="imprimirTicketModal();">
                    <i class="bi bi-printer me-1"></i> Imprimir Nota
                </button>
                <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Contenedor oculto para impresión de comanda térmica -->
<div id="modalTicketPrint" class="d-none"></div>

<script>
    // Variables globales
    var pedidoActualImpresion = null;

    function confirmarEntrega(idPedido, clienteNombre, totalFormateado, metodoPago) {
        document.getElementById('modalEntregarIdPedido').value = idPedido;
        document.getElementById('modalEntregarClienteNombre').innerText = clienteNombre;
        document.getElementById('modalEntregarMonto').innerText = '$' + totalFormateado;
        document.getElementById('modalEntregarMetodo').innerText = 'Método: ' + metodoPago;
        var myModal = new bootstrap.Modal(document.getElementById('modalConfirmarEntrega'));
        myModal.show();
    }

    function abrirModalDetalleHistorial(pedido) {
        pedidoActualImpresion = pedido;
        document.getElementById('modalHistorialTitulo').innerText = 'Entrega Folio #' + pedido.id_pedido;
        var html = '<div class="mb-3">' +
            '<strong>Cliente:</strong> ' + (pedido.cliente_nombre || 'Público General') + '<br>' +
            '<strong>Teléfono:</strong> ' + (pedido.cliente_telefono || 'No registrado') + '<br>' +
            '<strong>Dirección:</strong> ' + (pedido.direccion_entrega || 'Mostrador') + '<br>' +
            (pedido.referencias_ubicacion ? '<strong>Referencias:</strong> ' + pedido.referencias_ubicacion + '<br>' : '') +
            '<strong>Fecha entrega:</strong> ' + (pedido.fecha_entrega || pedido.fecha_ingreso) + '<br>' +
            '<strong>Repartidor:</strong> ' + (pedido.repartidor_nombre || 'Sucursal') +
            '</div>' +
            '<h6 class="fw-bold border-bottom pb-1">Productos entregados:</h6>' +
            '<div class="mb-3">';
        
        if (pedido.items && pedido.items.length > 0) {
            pedido.items.forEach(function(it) {
                html += '<div class="d-flex justify-content-between py-1 border-bottom small">' +
                    '<div><strong>' + parseFloat(it.cantidad) + 'x</strong> ' + it.descripcion + 
                    (it.comentario ? '<div class="text-muted">' + it.comentario + '</div>' : '') +
                    '</div>' +
                    '<span class="fw-bold">$' + parseFloat(it.subtotal).toFixed(2) + '</span>' +
                    '</div>';
            });
        } else {
            html += '<p class="text-muted small">Sin productos registrados.</p>';
        }

        html += '</div>' +
            '<div class="p-3 bg-light rounded d-flex justify-content-between align-items-center">' +
            '<div><strong>Método:</strong> ' + (pedido.metodo_pago || 'Efectivo').toUpperCase() + '</div>' +
            '<div class="fs-4 fw-bold text-success">Total: $' + parseFloat(pedido.total).toFixed(2) + '</div>' +
            '</div>';

        document.getElementById('modalHistorialCuerpo').innerHTML = html;
        var myModal = new bootstrap.Modal(document.getElementById('modalDetalleHistorial'));
        myModal.show();
    }

    function imprimirTicketPedido(idPedido) {
        // Encontrar datos del pedido en el DOM o solicitar por AJAX
        fetch('../api_sync_pedidos.php?action=get_orders&id_sucursal=<?php echo $id_sucursal; ?>')
            .then(res => res.json())
            .then(data => {
                if (data && data.orders) {
                    var ped = data.orders.find(o => parseInt(o.id_pedido) === parseInt(idPedido));
                    if (ped) {
                        generarYImprimirTicket(ped);
                    } else {
                        // Intentar imprimir con datos mínimos
                        generarYImprimirTicket({ id_pedido: idPedido });
                    }
                }
            })
            .catch(err => {
                window.print();
            });
    }

    function imprimirTicketModal() {
        if (pedidoActualImpresion) {
            generarYImprimirTicket(pedidoActualImpresion);
        }
    }

    function generarYImprimirTicket(order) {
        var itemsHtml = '';
        if (order.items && order.items.length > 0) {
            order.items.forEach(function(it) {
                itemsHtml += '<tr>' +
                    '<td style="text-align:left; padding: 2px 0;">' + parseFloat(it.cantidad) + ' ' + it.descripcion + 
                    (it.comentario ? '<br><small><i>' + it.comentario + '</i></small>' : '') + '</td>' +
                    '<td style="text-align:right; padding: 2px 0;">$' + parseFloat(it.subtotal).toFixed(2) + '</td>' +
                    '</tr>';
            });
        }

        var printContent = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Comanda #' + order.id_pedido + '</title>' +
            '<style>' +
            '@page { size: 80mm auto; margin: 3mm; }' +
            'body { font-family: monospace; font-size: 12px; margin: 0; padding: 0; width: 72mm; }' +
            'h3, p { text-align: center; margin: 2px 0; }' +
            '.divider { border-bottom: 1px dashed #000; margin: 4px 0; }' +
            'table { width: 100%; border-collapse: collapse; font-size: 11px; }' +
            '.text-right { text-align: right; }' +
            '.bold { font-weight: bold; }' +
            '</style></head><body>' +
            '<h3>CARNICERÍAS "EL CINCO"</h3>' +
            '<p>TICKET DE ENTREGA A DOMICILIO</p>' +
            '<div class="divider"></div>' +
            '<div><strong>FOLIO:</strong> #' + order.id_pedido + '</div>' +
            '<div><strong>FECHA:</strong> ' + (order.fecha_ingreso || new Date().toLocaleString()) + '</div>' +
            '<div><strong>CLIENTE:</strong> ' + (order.cliente_nombre || 'Público General') + '</div>' +
            '<div><strong>TEL:</strong> ' + (order.cliente_telefono || '') + '</div>' +
            '<div><strong>DIRECCIÓN:</strong> ' + (order.direccion_entrega || 'Mostrador') + '</div>' +
            (order.referencias_ubicacion ? '<div><strong>REF:</strong> ' + order.referencias_ubicacion + '</div>' : '') +
            '<div class="divider"></div>' +
            '<table><thead><tr><th style="text-align:left;">Cant/Prod</th><th style="text-align:right;">Total</th></tr></thead>' +
            '<tbody>' + itemsHtml + '</tbody></table>' +
            '<div class="divider"></div>' +
            '<div class="bold text-right" style="font-size: 14px;">TOTAL: $' + (parseFloat(order.total) || 0).toFixed(2) + '</div>' +
            '<div><strong>PAGO:</strong> ' + (order.metodo_pago || 'EFECTIVO').toUpperCase() + '</div>' +
            (order.monto_pago_efectivo ? '<div><strong>CAMBIO / NOTA:</strong> ' + order.monto_pago_efectivo + '</div>' : '') +
            (order.notas ? '<div class="divider"></div><div><strong>NOTAS:</strong> ' + order.notas + '</div>' : '') +
            '<br><p>¡Gracias por su compra!</p>' +
            '</body></html>';

        var printWin = window.open('', '_blank', 'width=380,height=600');
        printWin.document.open();
        printWin.document.write(printContent);
        printWin.document.close();
        printWin.focus();
        setTimeout(function() {
            printWin.print();
            printWin.close();
        }, 350);
    }
</script>
</body>
</html>
