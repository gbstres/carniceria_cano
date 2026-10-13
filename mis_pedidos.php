<?php
require_once __DIR__ . '/functions/config.php';
require_once __DIR__ . '/storefront_logic.php';
require_once __DIR__ . '/storefront_layout.php';

$cartTotals = storefront_cart_totals($_SESSION['store_cart'] ?? []);

// Acción para cerrar sesión o cambiar de número verificado
if (isset($_GET['action']) && $_GET['action'] === 'logout_phone') {
    $logoutTarget = preg_replace('/[^0-9]/', '', (string)($_GET['phone'] ?? ''));
    if (!empty($logoutTarget)) {
        unset($_SESSION['verified_phones'][$logoutTarget]);
        $cName = 'cano_ver_' . substr(md5($logoutTarget), 0, 8);
        setcookie($cName, '', time() - 3600, '/');
    }
    setcookie('cano_last_verified_phone', '', time() - 3600, '/');
    setcookie('cano_last_phone', '', time() - 3600, '/');
    header('Location: mis_pedidos.php');
    exit;
}

// 1. Detectar si este dispositivo ya tiene un teléfono verificado guardado (cookie o sesión)
$activeVerifiedPhone = '';
$cookieVerified = preg_replace('/[^0-9]/', '', (string)($_COOKIE['cano_last_verified_phone'] ?? ''));
if (strlen($cookieVerified) >= 10 && storefront_is_device_verified_phone($cookieVerified)) {
    $activeVerifiedPhone = $cookieVerified;
} elseif (!empty($_SESSION['verified_phones'])) {
    foreach (array_keys($_SESSION['verified_phones']) as $p) {
        if (strlen($p) >= 10 && storefront_is_device_verified_phone($p)) {
            $activeVerifiedPhone = $p;
            break;
        }
    }
}

// 2. Determinar el teléfono a consultar
$requestedPhone = preg_replace('/[^0-9]/', '', (string)($_REQUEST['telefono'] ?? ''));
if (empty($requestedPhone) && !empty($activeVerifiedPhone)) {
    $requestedPhone = $activeVerifiedPhone;
}

// 3. Validar si este dispositivo/navegador está autorizado para ver ese número
$isPhoneVerified = (strlen($requestedPhone) >= 10) && storefront_is_device_verified_phone($requestedPhone);

// 4. Rango de filtro (por defecto 'all')
$filterRange = isset($_REQUEST['rango']) ? trim((string)$_REQUEST['rango']) : 'all';
$validRanges = ['all', 'last_30_days', 'current_month'];
if (!in_array($filterRange, $validRanges, true)) {
    $filterRange = 'all';
}

// 5. Cargar compras ÚNICAMENTE si está verificado
$customerOrders = [];
if ($isPhoneVerified) {
    $customerOrders = storefront_fetch_customer_orders($link, $requestedPhone, $filterRange);
    setcookie('cano_last_verified_phone', $requestedPhone, time() + (180 * 86400), '/');
}

storefront_render_header('Mis Pedidos - Carnicería Cano', 'mis_pedidos', $cartTotals);
?>

<section class="mis-pedidos-section py-4">
    <div class="container">
        <!-- Encabezado de Sección -->
        <div class="card rounded-4 border-0 shadow-sm mb-4 bg-white">
            <div class="card-body p-4">
                <div class="row align-items-center g-3">
                    <div class="col-md-7">
                        <span class="badge bg-danger bg-opacity-10 text-danger fw-bold px-3 py-2 rounded-pill mb-2">
                            <i class="bi bi-clock-history me-1"></i> Historial de Compras
                        </span>
                        <h2 class="h3 fw-bold text-dark m-0 font-serif">Mis Pedidos Realizados</h2>
                        <p class="text-muted small m-0 mt-1">Consulta tus compras anteriores, el estatus de entrega y el desglose de productos con total privacidad.</p>
                    </div>
                    <div class="col-md-5">
                        <form method="get" action="mis_pedidos.php" class="search-phone-form">
                            <input type="hidden" name="rango" value="<?php echo storefront_escape($filterRange); ?>">
                            <label class="form-label fw-bold text-dark small mb-1">
                                <i class="bi bi-telephone-fill text-danger me-1"></i> Ingresa tu número de teléfono:
                            </label>
                            <div class="input-group">
                                <input type="text" name="telefono" class="form-control fw-bold" placeholder="Ej: 55 1234 5678" value="<?php echo storefront_escape($requestedPhone); ?>" autocomplete="off" inputmode="numeric">
                                <button type="submit" class="btn btn-danger px-3 fw-bold">
                                    <i class="bi bi-search me-1"></i> Consultar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($isPhoneVerified): ?>
            <!-- Selector de Rangos de Fecha y Estatus de Seguridad -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div class="btn-group rounded-pill shadow-sm bg-white p-1" role="group">
                    <a href="mis_pedidos.php?telefono=<?php echo urlencode($requestedPhone); ?>&rango=all" class="btn btn-sm rounded-pill fw-bold <?php echo $filterRange === 'all' ? 'btn-danger' : 'btn-light text-dark'; ?>">
                        <i class="bi bi-list-stars me-1"></i> Todos
                    </a>
                    <a href="mis_pedidos.php?telefono=<?php echo urlencode($requestedPhone); ?>&rango=last_30_days" class="btn btn-sm rounded-pill fw-bold <?php echo $filterRange === 'last_30_days' ? 'btn-danger' : 'btn-light text-dark'; ?>">
                        <i class="bi bi-calendar3 me-1"></i> Últimos 30 días
                    </a>
                    <a href="mis_pedidos.php?telefono=<?php echo urlencode($requestedPhone); ?>&rango=current_month" class="btn btn-sm rounded-pill fw-bold <?php echo $filterRange === 'current_month' ? 'btn-danger' : 'btn-light text-dark'; ?>">
                        <i class="bi bi-calendar-event me-1"></i> Mes Actual
                    </a>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success bg-opacity-10 text-success border border-success fw-bold px-3 py-2 rounded-pill small">
                        <i class="bi bi-shield-check me-1"></i> <?php echo storefront_escape($requestedPhone); ?> (Verificado 180 días)
                    </span>
                    <a href="mis_pedidos.php?action=logout_phone&phone=<?php echo urlencode($requestedPhone); ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-semibold" title="Salir o consultar otro número">
                        <i class="bi bi-box-arrow-right me-1"></i> Cambiar número
                    </a>
                </div>
            </div>

            <!-- Lista de Pedidos -->
            <?php if (empty($customerOrders)): ?>
                <div class="card rounded-4 border-0 shadow-sm bg-white text-center p-5 my-3">
                    <div class="display-4 text-muted mb-3"><i class="bi bi-inbox"></i></div>
                    <h4 class="fw-bold text-dark">No se encontraron pedidos en este periodo</h4>
                    <p class="text-muted small max-w-md mx-auto">
                        No hay compras registradas para el número <strong><?php echo storefront_escape($requestedPhone); ?></strong> en el rango seleccionado.
                    </p>
                    <div class="mt-2">
                        <a href="index.php" class="btn btn-danger px-4 rounded-pill fw-bold">
                            <i class="bi bi-shop me-1"></i> Ir al Catálogo y Comprar
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($customerOrders as $order): ?>
                        <?php
                        $statusClass = 'bg-secondary';
                        $statusLabel = 'Nuevo';
                        switch ($order['estatus']) {
                            case 'nuevo':
                                $statusClass = 'bg-danger text-white';
                                $statusLabel = 'Nuevo';
                                break;
                            case 'en_preparacion':
                            case 'en_proceso':
                                $statusClass = 'bg-warning text-dark';
                                $statusLabel = 'En Preparación';
                                break;
                            case 'enviado':
                            case 'en_camino':
                                $statusClass = 'bg-info text-dark';
                                $statusLabel = 'En Camino';
                                break;
                            case 'completado':
                            case 'entregado':
                                $statusClass = 'bg-success text-white';
                                $statusLabel = 'Completado';
                                break;
                            case 'cancelado':
                                $statusClass = 'bg-secondary text-white';
                                $statusLabel = 'Cancelado';
                                break;
                            default:
                                $statusClass = 'bg-secondary text-white';
                                $statusLabel = ucfirst($order['estatus'] ?? 'Nuevo');
                                break;
                        }
                        $sucursalNombre = !empty($order['desc_sucursal']) 
                            ? $order['desc_sucursal'] 
                            : (function_exists('storefront_branch_name') ? storefront_branch_name($link, (int)$order['id_sucursal']) : ('Sucursal ' . $order['id_sucursal']));
                        ?>
                        <div class="col-12">
                            <div class="card rounded-4 border-0 shadow-sm bg-white overflow-hidden">
                                <div class="card-header bg-light p-3 d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom">
                                    <div>
                                        <strong class="fs-5 text-dark font-serif me-2">Folio #<?php echo storefront_escape($order['id_pedido']); ?></strong>
                                        <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i> <?php echo storefront_escape(date('d/m/Y h:i A', strtotime($order['fecha_ingreso']))); ?></span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="small fw-bold text-muted me-2">Estatus:</span>
                                        <span class="badge <?php echo $statusClass; ?> px-3 py-2 rounded-pill fw-bold">
                                            <?php echo storefront_escape($statusLabel); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body p-3 p-md-4">
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <div class="p-3 bg-light rounded-3 border h-100">
                                                <h6 class="fw-bold text-dark small mb-2"><i class="bi bi-person-fill text-danger me-1"></i> Cliente & Entrega:</h6>
                                                <p class="small mb-1"><strong>Nombre:</strong> <?php echo storefront_escape($order['cliente_nombre']); ?></p>
                                                <p class="small mb-1"><strong>Modalidad:</strong> <?php echo $order['tipo_entrega'] === 'domicilio' ? '🚚 Entrega a Domicilio' : '🏪 Recoger en Sucursal'; ?></p>
                                                <p class="small mb-1"><strong>Sucursal:</strong> <?php echo storefront_escape($sucursalNombre); ?></p>
                                                <?php if ($order['tipo_entrega'] === 'domicilio' && !empty($order['direccion_entrega'])): ?>
                                                    <p class="small mb-0 text-truncate"><strong>Dirección:</strong> <?php echo storefront_escape($order['direccion_entrega']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3 bg-light rounded-3 border h-100">
                                                <h6 class="fw-bold text-dark small mb-2"><i class="bi bi-credit-card-fill text-success me-1"></i> Pago & Totales:</h6>
                                                <p class="small mb-1"><strong>Método de Pago:</strong> <?php echo $order['metodo_pago'] === 'transferencia' ? '🏦 Transferencia SPEI' : '💵 Efectivo (Pago contra entrega)'; ?></p>
                                                <?php if (!empty($order['monto_pago_efectivo'])): ?>
                                                    <p class="small mb-1"><strong>Pago con:</strong> <?php echo storefront_escape($order['monto_pago_efectivo']); ?></p>
                                                <?php endif; ?>
                                                <p class="small mb-0 fs-5"><strong>Total:</strong> <span class="text-danger fw-bold"><?php echo storefront_escape(storefront_money($order['total'])); ?></span></p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tabla de Productos del Pedido -->
                                    <h6 class="fw-bold text-dark small mb-2"><i class="bi bi-basket2-fill text-secondary me-1"></i> Detalle de Cortes y Productos:</h6>
                                    <div class="table-responsive rounded-3 border">
                                        <table class="table table-hover align-middle mb-0 small">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Código</th>
                                                    <th>Descripción</th>
                                                    <th class="text-center">Cantidad</th>
                                                    <th class="text-end">P. Unitario</th>
                                                    <th class="text-end">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($order['items'] as $item): ?>
                                                    <tr>
                                                        <td><code class="text-dark fw-bold"><?php echo storefront_escape($item['codigo']); ?></code></td>
                                                        <td>
                                                             <strong><?php echo storefront_escape($item['descripcion']); ?></strong>
                                                            <?php if (!empty($item['comentario'])): ?>
                                                                <span class="d-block text-muted small"><i class="bi bi-chat-left-text me-1"></i> Nota: <?php echo storefront_escape($item['comentario']); ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center fw-bold"><?php echo storefront_escape(number_format((float)$item['cantidad'], 2)); ?> kg/pza</td>
                                                        <td class="text-end"><?php echo storefront_escape(storefront_money($item['precio_unitario'])); ?></td>
                                                        <td class="text-end fw-bold"><?php echo storefront_escape(storefront_money($item['subtotal'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif (strlen($requestedPhone) >= 10): ?>
            <!-- Tarjeta de Verificación por WhatsApp OTP (Protección de Datos) -->
            <div class="card rounded-4 border-0 shadow-sm bg-white text-center p-4 p-md-5 max-w-lg mx-auto my-3" style="max-width: 540px;">
                <div class="display-5 text-success mb-3"><i class="bi bi-shield-lock-fill"></i></div>
                <h3 class="fw-bold text-dark font-serif mb-2">Verificación de Seguridad</h3>
                <p class="text-muted small mb-4">
                    Para proteger la privacidad de la dirección de entrega y el historial de compras del número 
                    <strong class="text-dark"><?php echo substr($requestedPhone, 0, 2) . ' **** ' . substr($requestedPhone, -4); ?></strong>, 
                    te enviaremos un código de 4 dígitos a tu WhatsApp.
                </p>

                <div id="otpStepSend">
                    <button type="button" class="btn btn-success btn-lg px-4 py-3 fw-bold rounded-pill shadow-sm w-100" id="btnSendOtp" onclick="sendOrdersOtp('<?php echo storefront_escape($requestedPhone); ?>')">
                        <i class="bi bi-whatsapp me-2"></i> Enviar Código por WhatsApp
                    </button>
                    <div class="text-muted small mt-3">
                        <i class="bi bi-lock me-1"></i> Tu dispositivo quedará recordado por 6 meses para que no tengas que verificarlo cada vez.
                    </div>
                </div>

                <div id="otpStepVerify" class="d-none">
                    <div class="alert alert-success p-3 text-start small mb-3 rounded-3 shadow-sm">
                        <i class="bi bi-check-circle-fill text-success me-1"></i>
                        <strong>¡Código enviado con éxito!</strong> Revisa la aplicación de WhatsApp en tu teléfono e ingresa los 4 dígitos:
                    </div>

                    <div class="d-flex justify-content-center my-3">
                        <input type="text" id="otpCodeInput" class="form-control text-center fw-bold fs-2 text-success border-2 border-success rounded-3" style="max-width: 190px; letter-spacing: 8px;" maxlength="4" placeholder="0000" autocomplete="off" inputmode="numeric">
                    </div>

                    <button type="button" class="btn btn-success btn-lg px-4 py-3 fw-bold rounded-pill shadow-sm w-100 mb-2" id="btnVerifyOtp" onclick="verifyOrdersOtp('<?php echo storefront_escape($requestedPhone); ?>')">
                        <i class="bi bi-check2-circle me-1"></i> Confirmar e Ingresar
                    </button>

                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                        <button type="button" class="btn btn-link text-muted small p-0 text-decoration-none" onclick="sendOrdersOtp('<?php echo storefront_escape($requestedPhone); ?>')">
                            <i class="bi bi-arrow-clockwise me-1"></i> Reenviar código
                        </button>
                        <a href="mis_pedidos.php" class="text-danger small text-decoration-none">
                            <i class="bi bi-x-circle me-1"></i> Cancelar
                        </a>
                    </div>
                </div>

                <div id="otpAlert" class="mt-3"></div>
            </div>

        <?php else: ?>
            <!-- Estado Inicial: Sin teléfono ingresado -->
            <div class="card rounded-4 border-0 shadow-sm bg-white text-center p-5 my-3">
                <div class="display-4 text-danger mb-3"><i class="bi bi-phone-vibrate"></i></div>
                <h4 class="fw-bold text-dark">Consulta tus compras anteriores</h4>
                <p class="text-muted small max-w-md mx-auto mb-4">
                    Ingresa tu número de WhatsApp a 10 dígitos en la barra superior para consultar los pedidos realizados y dar seguimiento a tus entregas.
                </p>
                <div class="max-w-xs mx-auto" style="max-width: 320px;">
                    <form method="get" action="mis_pedidos.php">
                        <div class="input-group">
                            <input type="text" name="telefono" class="form-control text-center fw-bold fs-5" placeholder="Ej: 5512345678" maxlength="10" required inputmode="numeric">
                            <button type="submit" class="btn btn-danger fw-bold px-3">
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
function sendOrdersOtp(phone) {
    var btn = document.getElementById('btnSendOtp');
    var alertBox = document.getElementById('otpAlert');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Enviando a WhatsApp...';
    }
    if (alertBox) alertBox.innerHTML = '';

    var formData = new FormData();
    formData.append('action', 'send_whatsapp_otp');
    formData.append('phone', phone);
    formData.append('context', 'mis_pedidos');

    fetch('storefront_logic.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-whatsapp me-2"></i> Enviar Código por WhatsApp';
        }
        if (data && data.ok) {
            document.getElementById('otpStepSend').classList.add('d-none');
            document.getElementById('otpStepVerify').classList.remove('d-none');
            var codeInput = document.getElementById('otpCodeInput');
            if (codeInput) {
                codeInput.focus();
                codeInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') verifyOrdersOtp(phone);
                });
            }
        } else {
            if (alertBox) {
                alertBox.innerHTML = '<div class="alert alert-danger p-2 small mb-0 rounded-3">' + 
                    (data.message || 'No fue posible enviar el código a WhatsApp. Intenta de nuevo.') + 
                    '</div>';
            }
        }
    })
    .catch(function(err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-whatsapp me-2"></i> Enviar Código por WhatsApp';
        }
        if (alertBox) {
            alertBox.innerHTML = '<div class="alert alert-danger p-2 small mb-0 rounded-3">Error de comunicación con el servidor.</div>';
        }
    });
}

function verifyOrdersOtp(phone) {
    var codeInput = document.getElementById('otpCodeInput');
    var btn = document.getElementById('btnVerifyOtp');
    var alertBox = document.getElementById('otpAlert');
    var code = codeInput ? codeInput.value.trim() : '';

    if (code.length < 4) {
        if (alertBox) alertBox.innerHTML = '<div class="alert alert-warning p-2 small mb-0 rounded-3">Ingresa los 4 dígitos que recibiste en WhatsApp.</div>';
        if (codeInput) codeInput.focus();
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Verificando...';
    }
    if (alertBox) alertBox.innerHTML = '';

    var formData = new FormData();
    formData.append('action', 'verify_whatsapp_otp');
    formData.append('phone', phone);
    formData.append('code', code);
    formData.append('context', 'mis_pedidos');

    fetch('storefront_logic.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data && data.ok) {
            if (alertBox) {
                alertBox.innerHTML = '<div class="alert alert-success p-2 small mb-0 rounded-3"><i class="bi bi-check2-all me-1"></i> ¡Verificación exitosa! Cargando tus compras...</div>';
            }
            setTimeout(function() {
                window.location.href = 'mis_pedidos.php?telefono=' + encodeURIComponent(phone);
            }, 800);
        } else {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Confirmar e Ingresar';
            }
            if (alertBox) {
                alertBox.innerHTML = '<div class="alert alert-danger p-2 small mb-0 rounded-3">' + 
                    (data.message || 'Código incorrecto. Verifica los 4 dígitos en tu WhatsApp.') + 
                    '</div>';
            }
            if (codeInput) {
                codeInput.select();
            }
        }
    })
    .catch(function(err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Confirmar e Ingresar';
        }
        if (alertBox) {
            alertBox.innerHTML = '<div class="alert alert-danger p-2 small mb-0 rounded-3">Error al validar el código.</div>';
        }
    });
}
</script>

<?php
storefront_render_footer();
?>
