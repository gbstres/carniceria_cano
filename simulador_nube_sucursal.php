<?php
require_once __DIR__ . '/functions/config.php';
require_once __DIR__ . '/storefront_logic.php';

$link = $link ?? null;
if ($link) {
    storefront_ensure_order_tables($link);
}

// Procesar acciones de prueba si se solicitan por AJAX
if (isset($_POST['action_sim'])) {
    header('Content-Type: application/json; charset=utf-8');
    $actionSim = $_POST['action_sim'];

    if ($actionSim === 'create_cloud_order') {
        $nombres = ['Gerardo Bautista', 'María Elena López', 'Carlos Ramírez', 'Ana Sofía Torres', 'Jorge Hernández'];
        $nombre = $nombres[array_rand($nombres)];
        $tel = '55' . rand(10000000, 99999999);
        $sucursal = rand(1, 2);
        $metodo = rand(0, 1) ? 'efectivo' : 'transferencia';
        $tipo = rand(0, 1) ? 'domicilio' : 'recoger';
        $dir = $tipo === 'domicilio' ? 'Av. Revolución #450, Col. Centro' : '';
        $ref = $tipo === 'domicilio' ? 'Fachada azul, portón negro' : '';
        
        $orderStmt = mysqli_prepare($link, "INSERT INTO cc_pedidos_web (
            id_sucursal, cliente_nombre, cliente_telefono, cliente_email, tipo_entrega, direccion_entrega, 
            referencias_ubicacion, metodo_pago, monto_pago_efectivo, notas, subtotal, total, estatus
        ) VALUES (?, ?, ?, 'cliente@ejemplo.com', ?, ?, ?, ?, 'Billete $500', 'Corte para asar, término medio', 450.00, 450.00, 'nuevo')");

        mysqli_stmt_bind_param($orderStmt, 'issssss', $sucursal, $nombre, $tel, $tipo, $dir, $ref, $metodo);
        if (mysqli_stmt_execute($orderStmt)) {
            $orderId = mysqli_insert_id($link);

            // Insertar detalles
            $itemStmt = mysqli_prepare($link, "INSERT INTO cc_det_pedidos_web (id_pedido, codigo, descripcion, precio_unitario, cantidad, subtotal, comentario) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $cod1 = 'RES-01'; $desc1 = 'Bistec de Res Especial 🥩'; $pu1 = 180.00; $cant1 = 2.0; $sub1 = 360.00; $not1 = 'Empacado al vacío';
            mysqli_stmt_bind_param($itemStmt, 'issddds', $orderId, $cod1, $desc1, $pu1, $cant1, $sub1, $not1);
            mysqli_stmt_execute($itemStmt);

            $cod2 = 'CER-05'; $desc2 = 'Chorizo de Cerdo Casero 🥓'; $pu2 = 90.00; $cant2 = 1.0; $sub2 = 90.00; $not2 = 'Bien fresco';
            mysqli_stmt_bind_param($itemStmt, 'issddds', $orderId, $cod2, $desc2, $pu2, $cant2, $sub2, $not2);
            mysqli_stmt_execute($itemStmt);

            echo json_encode([
                'success' => true,
                'message' => "¡Pedido #$orderId creado exitosamente en la Nube!",
                'order_id' => $orderId,
                'cliente' => $nombre,
                'sucursal' => $sucursal,
                'total' => 450.00
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($link)]);
        }
        exit;
    }
}

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Simulador de Sincronización Nube ↔ Sucursales Offline | Carnicería Cano</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: system-ui, -apple-system, sans-serif; }
        .card-cloud { background: #1e293b; border: 1px solid #334155; border-radius: 16px; }
        .card-branch { background: #182234; border: 1px solid #2563eb; border-radius: 16px; }
        .console-box { background: #090d16; border: 1px solid #1e293b; border-radius: 12px; font-family: monospace; font-size: 0.85rem; height: 260px; overflow-y: auto; padding: 12px; }
        .log-time { color: #64748b; margin-right: 6px; }
        .log-cloud { color: #38bdf8; }
        .log-sync { color: #facc15; }
        .log-branch { color: #4ade80; }
        .badge-cloud { background-color: #0284c7; color: white; }
        .badge-branch { background-color: #16a34a; color: white; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid px-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 pb-3 border-bottom border-secondary border-opacity-25">
        <div>
            <span class="badge bg-danger px-3 py-2 rounded-pill fw-bold mb-1">
                <i class="bi bi-cpu-fill me-1"></i> SIMULADOR DE ARQUITECTURA HÍBRIDA
            </span>
            <h2 class="h3 fw-bold text-white m-0 font-serif">Prueba de Sincronización Nube ↔ Sucursal Offline</h2>
            <p class="text-slate-400 small m-0">Demostración local de cómo la tienda online en GCP transmite pedidos a la sucursal física offline.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="pedido.php" class="btn btn-outline-light btn-sm fw-bold">
                <i class="bi bi-shop me-1"></i> Abrir Tienda Web
            </a>
            <a href="admin_pedidos_web.php" target="_blank" class="btn btn-danger btn-sm fw-bold">
                <i class="bi bi-journal-check me-1"></i> Abrir Panel Sucursal
            </a>
        </div>
    </div>

    <!-- Paneles Simulado: Nube vs Sucursal -->
    <div class="row g-4 mb-4">
        <!-- Panel Nube -->
        <div class="col-lg-6">
            <div class="card card-cloud p-4 h-100 shadow">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold text-info m-0 d-flex align-items-center">
                        <i class="bi bi-cloud-check-fill fs-4 me-2"></i> 1. Tienda Online en la Nube (GCP)
                    </h5>
                    <span class="badge badge-cloud px-3 py-1.5 rounded-pill">ONLINE EN NUBE</span>
                </div>
                <p class="text-slate-400 small mb-4">
                    Representa el servidor GCP público donde los clientes compran desde su celular.
                </p>

                <div class="bg-dark bg-opacity-50 p-3 rounded-3 mb-3 border border-secondary border-opacity-25">
                    <button type="button" class="btn btn-info btn-lg w-100 fw-bold shadow-sm" onclick="createCloudOrder()">
                        <i class="bi bi-phone-vibrate me-2"></i> 📱 Simular Cliente Comprando en Celular
                    </button>
                    <small class="text-slate-400 d-block text-center mt-2">Genera un pedido ficticio con productos y notas de cortes en la BD Nube.</small>
                </div>

                <div id="cloudOrderFeedback" class="mt-2"></div>
            </div>
        </div>

        <!-- Panel Sucursal Offline -->
        <div class="col-lg-6">
            <div class="card card-branch p-4 h-100 shadow">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold text-success m-0 d-flex align-items-center">
                        <i class="bi bi-building-fill-check fs-4 me-2"></i> 2. Sucursal Física 1 (Base Local Offline)
                    </h5>
                    <span class="badge badge-branch px-3 py-1.5 rounded-pill">SISTEMA LOCAL</span>
                </div>
                <p class="text-slate-400 small mb-3">
                    Representa la computadora local de la sucursal que descarga pedidos y descuenta inventario.
                </p>

                <div class="d-flex align-items-center justify-content-between p-3 bg-dark bg-opacity-50 rounded-3 mb-3 border border-primary border-opacity-25">
                    <div>
                        <strong class="d-block text-white small">Sincronizador Automático API:</strong>
                        <span id="syncStatusText" class="text-warning small"><i class="bi bi-arrow-repeat me-1"></i> En espera...</span>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input fs-5" type="checkbox" id="autoSyncSwitch" onchange="toggleAutoSync(this.checked)" checked>
                        <label class="form-check-label text-white small fw-bold" for="autoSyncSwitch">Auto-Sync (5s)</label>
                    </div>
                </div>

                <button type="button" class="btn btn-success fw-bold w-100" onclick="triggerManualSync()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Sincronizar Ahora con la Nube (API REST)
                </button>
            </div>
        </div>
    </div>

    <!-- Console Log & Live Trace -->
    <div class="card card-cloud p-4 shadow">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="fw-bold text-white m-0 d-flex align-items-center">
                <i class="bi bi-terminal-fill me-2 text-warning"></i> Bitácora de Comunicación HTTP/REST API en Tiempo Real
            </h6>
            <button class="btn btn-sm btn-outline-secondary py-0" onclick="clearConsole()">Limpiar Consola</button>
        </div>
        <div class="console-box" id="consoleBox">
            <div><span class="log-time">[SISTEMA]</span> <span class="log-sync">Simulador Nube ↔ Sucursal inicializado correctamente en local.</span></div>
        </div>
    </div>
</div>

<script>
var autoSyncTimer = null;

function logMessage(type, msg) {
    var consoleBox = document.getElementById('consoleBox');
    if (!consoleBox) return;
    var now = new Date().toLocaleTimeString();
    var colorClass = type === 'cloud' ? 'log-cloud' : (type === 'branch' ? 'log-branch' : 'log-sync');
    var prefix = type === 'cloud' ? '[NUBE GCP]' : (type === 'branch' ? '[SUCURSAL]' : '[REST API]');
    
    var div = document.createElement('div');
    div.innerHTML = '<span class="log-time">' + now + '</span> <span class="' + colorClass + '"><strong>' + prefix + '</strong> ' + msg + '</span>';
    consoleBox.appendChild(div);
    consoleBox.scrollTop = consoleBox.scrollHeight;
}

function clearConsole() {
    var consoleBox = document.getElementById('consoleBox');
    if (consoleBox) consoleBox.innerHTML = '';
}

function createCloudOrder() {
    var formData = new FormData();
    formData.append('action_sim', 'create_cloud_order');

    logMessage('cloud', 'Cliente envía pedido desde su celular a la tienda online...');

    fetch('simulador_nube_sucursal.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                logMessage('cloud', '✅ ' + data.message + ' (Cliente: ' + data.cliente + ' | Total: $' + data.total + ')');
                var fb = document.getElementById('cloudOrderFeedback');
                if (fb) {
                    fb.innerHTML = '<div class="alert alert-info p-2.5 small mb-0"><i class="bi bi-check-circle-fill me-1"></i> <strong>Pedido Nube Generado:</strong> Folio #' + data.order_id + ' para ' + data.cliente + ' ($' + data.total + '). Espere la sincronización de la sucursal.</div>';
                }
            } else {
                logMessage('cloud', '❌ Error al crear pedido: ' + data.message);
            }
        })
        .catch(err => logMessage('cloud', '❌ Error de red: ' + err));
}

function triggerManualSync() {
    logMessage('sync', 'Ejecutando petición REST API: GET api_sync_pedidos.php?action=get_orders&id_sucursal=1...');
    
    fetch('api_sync_pedidos.php?action=get_orders&id_sucursal=1')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                var syncText = document.getElementById('syncStatusText');
                if (syncText) {
                    syncText.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i> Sincronizado (' + data.count + ' pedidos)';
                }
                logMessage('branch', '📥 Sucursal 1 descargó ' + data.count + ' pedidos desde la Nube.');
                
                var newOrders = data.orders.filter(o => o.estatus === 'nuevo');
                if (newOrders.length > 0) {
                    logMessage('branch', '🔔 <strong>¡ALERTA SONORA!</strong> Se detectaron ' + newOrders.length + ' pedidos NUEVOS sin atender.');
                    playAlertBeep();
                }
            } else {
                logMessage('sync', '❌ Error API: ' + data.message);
            }
        })
        .catch(err => logMessage('sync', '❌ Error de conexión API: ' + err));
}

function playAlertBeep() {
    try {
        var ctx = new (window.AudioContext || window.webkitAudioContext)();
        var osc = ctx.createOscillator();
        osc.type = 'sine';
        osc.frequency.value = 880;
        osc.connect(ctx.destination);
        osc.start();
        setTimeout(() => osc.stop(), 300);
    } catch (e) {}
}

function toggleAutoSync(enable) {
    clearInterval(autoSyncTimer);
    if (enable) {
        logMessage('sync', 'Auto-sincronización activada cada 5 segundos.');
        autoSyncTimer = setInterval(triggerManualSync, 5000);
    } else {
        logMessage('sync', 'Auto-sincronización pausada.');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    toggleAutoSync(true);
});
</script>

</body>
</html>
