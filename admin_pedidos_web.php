<?php
require_once __DIR__ . '/functions/config.php';
require_once __DIR__ . '/storefront_logic.php';

$link = $link ?? null;
if ($link) {
    storefront_ensure_order_tables($link);
}

$idSucursalSel = isset($_GET['sucursal']) ? (int)$_GET['sucursal'] : 1;
if (!in_array($idSucursalSel, [1, 2], true)) {
    $idSucursalSel = 1;
}

$branchNames = [
    1 => 'Sucursal 1 (San Cristóbal Centro)',
    2 => 'Sucursal 2 (La Principal / Matriz)'
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestión de Pedidos Web | Carnicería Cano</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: system-ui, -apple-system, sans-serif; }
        .navbar-admin { background: #7E1414; }
        .order-card { border-radius: 12px; border: 1px solid #e2e8f0; transition: all 0.2s ease-in-out; }
        .order-card:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
        .order-card.new-order { border-left: 5px solid #dc3545; }
        .order-card.processing { border-left: 5px solid #ffc107; }
        .order-card.completed { border-left: 5px solid #198754; opacity: 0.9; }
        .comment-badge { background-color: #fff8e1; border-left: 3px solid #ffc107; padding: 6px 10px; font-size: 0.82rem; color: #856404; border-radius: 4px; }
        .badge-status-nuevo { background-color: #dc3545; }
        .badge-status-en_preparacion { background-color: #ffc107; color: #000; }
        .badge-status-completado { background-color: #198754; }
        .badge-status-cancelado { background-color: #6c757d; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-admin shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="admin_pedidos_web.php">
            <i class="bi bi-shop me-2"></i> Carnicería Cano - Panel de Pedidos Web
        </a>
        <div class="d-flex align-items-center gap-3">
            <div class="input-group input-group-sm">
                <label class="input-group-text bg-warning text-dark fw-bold" for="sucursalSelect"><i class="bi bi-geo-alt-fill me-1"></i> Sucursal:</label>
                <select class="form-select fw-bold text-dark" id="sucursalSelect" onchange="changeBranch(this.value)">
                    <option value="1" <?php echo $idSucursalSel === 1 ? 'selected' : ''; ?>>Sucursal 1 - San Cristóbal (7:30 AM - 3:30 PM)</option>
                    <option value="2" <?php echo $idSucursalSel === 2 ? 'selected' : ''; ?>>Sucursal 2 - La Principal (8:00 AM - 4:00 PM)</option>
                </select>
            </div>
            <button class="btn btn-sm btn-outline-light" onclick="loadOrders(true)">
                <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
            </button>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 py-4">
    <!-- Header status & Filters -->
    <div class="row align-items-center mb-4 g-3">
        <div class="col-md-6">
            <h3 class="fw-bold text-dark mb-1 font-serif">
                <i class="bi bi-journal-check text-danger me-2"></i>Pedidos de <?php echo storefront_escape($branchNames[$idSucursalSel]); ?>
            </h3>
            <p class="text-muted small mb-0">Revisa los pedidos online, consulta notas de cortes y procesa la venta para descontar stock.</p>
        </div>
        <div class="col-md-6 text-md-end">
            <div class="btn-group" role="group" id="filterBtnGroup">
                <button type="button" class="btn btn-outline-secondary btn-sm active" onclick="setFilter('')" id="btnFilterAll">Todos</button>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="setFilter('nuevo')" id="btnFilterNuevo">🔴 Nuevos</button>
                <button type="button" class="btn btn-outline-warning text-dark btn-sm" onclick="setFilter('en_preparacion')" id="btnFilterPrep">🟡 En Preparación</button>
                <button type="button" class="btn btn-outline-success btn-sm" onclick="setFilter('completado')" id="btnFilterComp">🟢 Completados</button>
            </div>
        </div>
    </div>

    <!-- Alert Message Box -->
    <div id="alertContainer"></div>

    <!-- Orders Container -->
    <div id="ordersList" class="row g-3">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-danger" role="status"></div>
            <p class="mt-2 text-muted">Cargando pedidos web de la sucursal...</p>
        </div>
    </div>
</div>

<script>
var currentBranch = <?php echo $idSucursalSel; ?>;
var currentFilter = '';
var knownOrderCount = 0;
var isFirstLoad = true;

function changeBranch(newBranch) {
    currentBranch = parseInt(newBranch);
    window.history.pushState(null, '', 'admin_pedidos_web.php?sucursal=' + currentBranch);
    loadOrders(true);
}

function setFilter(filterVal) {
    currentFilter = filterVal;
    document.querySelectorAll('#filterBtnGroup .btn').forEach(b => b.classList.remove('active'));
    if (filterVal === '') document.getElementById('btnFilterAll').classList.add('active');
    if (filterVal === 'nuevo') document.getElementById('btnFilterNuevo').classList.add('active');
    if (filterVal === 'en_preparacion') document.getElementById('btnFilterPrep').classList.add('active');
    if (filterVal === 'completado') document.getElementById('btnFilterComp').classList.add('active');
    loadOrders(false);
}

function playNewOrderSound() {
    try {
        var ctx = new (window.AudioContext || window.webkitAudioContext)();
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
        osc.frequency.setValueAtTime(880, ctx.currentTime + 0.15); // A5
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.5);
    } catch(e){}
}

function loadOrders(manualRefresh) {
    var url = 'api_sync_pedidos.php?action=get_orders&id_sucursal=' + currentBranch;
    if (currentFilter !== '') {
        url += '&estatus=' + encodeURIComponent(currentFilter);
    }

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('ordersList').innerHTML = '<div class="alert alert-danger">Error: ' + data.message + '</div>';
                return;
            }

            var orders = data.orders || [];
            
            // Sonido de alerta si hay nuevos pedidos entrantes
            var newCount = orders.filter(o => o.estatus === 'nuevo').length;
            if (!isFirstLoad && newCount > knownOrderCount && manualRefresh !== true) {
                playNewOrderSound();
            }
            knownOrderCount = newCount;
            isFirstLoad = false;

            if (orders.length === 0) {
                document.getElementById('ordersList').innerHTML = `
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                        <h5 class="text-muted">No hay pedidos web registrados en esta categoría.</h5>
                    </div>`;
                return;
            }

            var html = '';
            orders.forEach(order => {
                var statusClass = 'new-order';
                var statusBadgeClass = 'badge-status-nuevo';
                var statusText = '🔴 Nuevo Pedido';

                if (order.estatus === 'en_preparacion') {
                    statusClass = 'processing';
                    statusBadgeClass = 'badge-status-en_preparacion';
                    statusText = '🟡 En Preparación';
                } else if (order.estatus === 'completado') {
                    statusClass = 'completed';
                    statusBadgeClass = 'badge-status-completado';
                    statusText = '🟢 Completado / Vendido';
                } else if (order.estatus === 'cancelado') {
                    statusClass = '';
                    statusBadgeClass = 'badge-status-cancelado';
                    statusText = '⚪ Cancelado';
                }

                var deliveryBadge = order.tipo_entrega === 'domicilio' 
                    ? '<span class="badge bg-danger"><i class="bi bi-truck me-1"></i> Entrega a Domicilio</span>'
                    : '<span class="badge bg-primary"><i class="bi bi-bag-check me-1"></i> Recoger en Sucursal</span>';

                var paymentBadge = order.metodo_pago === 'efectivo'
                    ? '<span class="badge bg-success"><i class="bi bi-cash me-1"></i> Efectivo (' + (order.monto_pago_efectivo ? order.monto_pago_efectivo : 'Pago exacto') + ')</span>'
                    : '<span class="badge bg-info text-dark"><i class="bi bi-bank me-1"></i> Transferencia SPEI</span>';

                var itemsHtml = '';
                (order.items || []).forEach(item => {
                    var commentBox = item.comentario && item.comentario.trim() !== ''
                        ? '<div class="comment-badge mt-1"><i class="bi bi-chat-left-text me-1"></i><strong>Indicación Butcher:</strong> ' + escapeHtml(item.comentario) + '</div>'
                        : '';

                    itemsHtml += `
                        <tr>
                            <td><strong class="text-dark">${escapeHtml(item.codigo)}</strong></td>
                            <td>
                                <strong class="d-block text-dark">${escapeHtml(item.descripcion)}</strong>
                                ${commentBox}
                            </td>
                            <td class="text-center fw-bold text-danger">${parseFloat(item.cantidad).toFixed(2)} kg/pza</td>
                            <td class="text-end">$${parseFloat(item.precio_unitario).toFixed(2)}</td>
                            <td class="text-end fw-bold">$${parseFloat(item.subtotal).toFixed(2)}</td>
                        </tr>`;
                });

                var mapsBtn = '';
                if (order.latitud && order.longitud) {
                    var gmapsUrl = 'https://www.google.com/maps?q=' + order.latitud + ',' + order.longitud;
                    mapsBtn = `<a href="${gmapsUrl}" target="_blank" class="btn btn-outline-danger btn-sm p-1 px-2 me-1" title="Ver pin en Google Maps">
                        <i class="bi bi-geo-alt-fill me-1"></i> Ver en Maps (${order.distancia_km ? order.distancia_km + ' km' : 'GPS'})
                    </a>`;
                }

                var actionButtons = '';
                if (order.estatus !== 'completado' && order.estatus !== 'cancelado') {
                    actionButtons = `
                        <button onclick="processOrder(${order.id_pedido}, 'completado')" class="btn btn-success fw-bold shadow-sm">
                            <i class="bi bi-check-circle-fill me-1"></i> Aprobar y Descontar Stock
                        </button>
                        ${order.estatus === 'nuevo' ? `
                            <button onclick="updateStatus(${order.id_pedido}, 'en_preparacion')" class="btn btn-warning text-dark fw-bold">
                                <i class="bi bi-clock-history me-1"></i> En Preparación
                            </button>
                        ` : ''}
                        <button onclick="updateStatus(${order.id_pedido}, 'cancelado')" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i> Cancelar
                        </button>
                    `;
                } else {
                    actionButtons = `<span class="text-muted small fw-bold"><i class="bi bi-info-circle me-1"></i> Este pedido ya fue finalizado y su stock fue procesado.</span>`;
                }

                html += `
                    <div class="col-12">
                        <div class="card order-card ${statusClass} bg-white shadow-sm p-3">
                            <div class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-2 mb-3">
                                <div>
                                    <span class="badge ${statusBadgeClass} me-2 fs-6 px-3 py-2">${statusText}</span>
                                    <strong class="h5 m-0 font-serif text-dark me-3">Folio #${order.id_pedido}</strong>
                                    <small class="text-muted"><i class="bi bi-clock me-1"></i>${order.fecha_ingreso}</small>
                                </div>
                                <div class="d-flex gap-1 mt-2 mt-md-0">
                                    ${deliveryBadge}
                                    ${paymentBadge}
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-5 border-end">
                                    <h6 class="fw-bold text-dark border-bottom pb-1"><i class="bi bi-person-fill text-danger me-1"></i> Datos del Cliente</h6>
                                    <p class="mb-1 small"><strong>Nombre:</strong> ${escapeHtml(order.cliente_nombre)}</p>
                                    <p class="mb-1 small"><strong>Teléfono:</strong> <a href="tel:${escapeHtml(order.cliente_telefono)}" class="text-decoration-none fw-bold text-primary"><i class="bi bi-telephone me-1"></i>${escapeHtml(order.cliente_telefono)}</a></p>
                                    ${order.cliente_email ? `<p class="mb-1 small"><strong>Email:</strong> ${escapeHtml(order.cliente_email)}</p>` : ''}
                                    
                                    ${order.tipo_entrega === 'domicilio' ? `
                                        <div class="mt-3 p-2 bg-light rounded border">
                                            <strong class="d-block small text-danger"><i class="bi bi-house-door me-1"></i> Dirección de Entrega:</strong>
                                            <span class="small d-block text-dark fw-semibold">${escapeHtml(order.direccion_entrega)}</span>
                                            ${order.referencias_ubicacion ? `<small class="d-block text-muted mt-1"><strong>Ref:</strong> ${escapeHtml(order.referencias_ubicacion)}</small>` : ''}
                                            <div class="mt-2">${mapsBtn}</div>
                                        </div>
                                    ` : '<div class="mt-2 alert alert-info p-2 small m-0"><i class="bi bi-shop me-1"></i> El cliente recogerá su pedido en mostrador.</div>'}

                                    ${order.notas ? `<div class="mt-2 alert alert-secondary p-2 small"><strong>Notas del Pedido:</strong> ${escapeHtml(order.notas)}</div>` : ''}
                                </div>

                                <div class="col-md-7">
                                    <h6 class="fw-bold text-dark border-bottom pb-1"><i class="bi bi-basket-fill text-danger me-1"></i> Productos Solicitados</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped align-middle small mb-2">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Código</th>
                                                    <th>Descripción</th>
                                                    <th class="text-center">Cant/Kilos</th>
                                                    <th class="text-end">Precio</th>
                                                    <th class="text-end">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${itemsHtml}
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center bg-light p-2.5 rounded border mt-2">
                                        <span class="fw-bold text-dark fs-5">TOTAL A PAGAR:</span>
                                        <strong class="h4 fw-bold text-danger m-0">$${parseFloat(order.total).toFixed(2)}</strong>
                                    </div>

                                    <div class="mt-3 d-flex flex-wrap gap-2 justify-content-end align-items-center">
                                        ${actionButtons}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>`;
            });

            document.getElementById('ordersList').innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            document.getElementById('ordersList').innerHTML = '<div class="alert alert-danger">Error al conectar con la API de sincronización.</div>';
        });
}

function processOrder(orderId, statusVal) {
    if (!confirm('¿Deseas confirmar este pedido y DESCONTAR EL STOCK del inventario de la Sucursal ' + currentBranch + '?')) {
        return;
    }

    var formData = new FormData();
    formData.append('action', 'complete_order');
    formData.append('id_pedido', orderId);
    formData.append('id_sucursal', currentBranch);

    fetch('api_sync_pedidos.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showAlert('success', '✅ ' + data.message);
            loadOrders(true);
        } else {
            showAlert('danger', '❌ ' + data.message);
        }
    })
    .catch(err => {
        showAlert('danger', 'Error de comunicación al procesar pedido.');
    });
}

function updateStatus(orderId, statusVal) {
    var formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('id_pedido', orderId);
    formData.append('id_sucursal', currentBranch);
    formData.append('estatus', statusVal);

    fetch('api_sync_pedidos.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showAlert('info', 'ℹ️ ' + data.message);
            loadOrders(true);
        } else {
            showAlert('danger', '❌ ' + data.message);
        }
    })
    .catch(err => {
        showAlert('danger', 'Error al actualizar estatus.');
    });
}

function showAlert(type, msg) {
    var alertHtml = `<div class="alert alert-${type} alert-dismissible fade show shadow-sm" role="alert">
        ${msg}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', function () {
    loadOrders(true);
    // Polling automático cada 15 segundos para nuevas compras online
    setInterval(function () {
        loadOrders(false);
    }, 15000);
});
</script>

<script src="css/bootstrap.bundle.min.js"></script>
</body>
</html>
