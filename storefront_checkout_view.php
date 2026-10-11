<?php
$checkoutBranches = is_array($storefrontBranches ?? null) ? $storefrontBranches : [];
$minDeliveryAmount = defined('STOREFRONT_MIN_DELIVERY_AMOUNT') ? (float)STOREFRONT_MIN_DELIVERY_AMOUNT : 200.00;
$deliveryCost = defined('STOREFRONT_DELIVERY_COST') ? (float)STOREFRONT_DELIVERY_COST : 60.00;
$cartSubtotal = (float)($cartTotals['subtotal'] ?? 0.0);
?>
<style>
.checkout-form .form-control,
.checkout-form .form-select,
.checkout-form .input-group,
.checkout-form .input-group > .form-control,
.checkout-form .input-group > .input-group-text,
.checkout-form .input-group > .btn {
    min-height: 50px !important;
    height: 50px !important;
}
.delivery-option-card {
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    border: 2px solid #dee2e6 !important;
    position: relative;
    user-select: none;
}
.delivery-option-card:hover {
    border-color: #7E1414 !important;
    background-color: #fff9f9 !important;
}
.delivery-option-card.active {
    border-color: #7E1414 !important;
    background-color: rgba(126, 20, 20, 0.06) !important;
}
.delivery-option-card input[type="radio"] {
    cursor: pointer;
    accent-color: #7E1414;
}
.branch-select-card {
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    border: 2px solid #dee2e6;
}
.branch-select-card:hover {
    border-color: #7E1414;
    background-color: #fff9f9;
}
.branch-select-card.active {
    border-color: #7E1414;
    background-color: rgba(126, 20, 20, 0.05);
}
.address-suggestions-container {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: 1060;
    background: #ffffff;
    border: 1px solid #ced4da;
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(0,0,0,0.18);
    max-height: 250px;
    overflow-y: auto;
}
.address-suggestion-item {
    padding: 10px 14px;
    cursor: pointer;
    border-bottom: 1px solid #f1f3f5;
    transition: background 0.15s;
    text-align: left;
}
.address-suggestion-item:last-child {
    border-bottom: none;
}
.address-suggestion-item:hover {
    background-color: #fff2f2;
}
</style>
<section class="checkout-section py-4" id="pedido">
    <div class="container">
        <?php if (!empty($feedback['message'])): ?>
            <div class="alert alert-<?php echo storefront_escape($feedback['type']); ?> storefront-alert alert-dismissible fade show mb-4 shadow-sm" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i>
                <?php echo storefront_escape($feedback['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <?php if ($orderSuccess !== null): ?>
            <!-- Pantalla Exclusiva de Pedido Registrado con Éxito (Sin formulario abajo) -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="success-panel p-4 p-md-5 mb-4 bg-white border border-success border-opacity-25 rounded-4 text-center shadow-sm">
                        <div class="display-3 text-success mb-3"><i class="bi bi-check-circle-fill"></i></div>
                        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-2 rounded-pill fs-6 mb-2">¡Pedido Registrado con Éxito!</span>
                        <h2 class="h3 fw-bold text-dark font-serif mt-2 mb-2">Gracias por tu compra, <?php echo storefront_escape($orderSuccess['name']); ?></h2>
                        <p class="text-muted fs-5 mb-3">Tu pedido quedó guardado con el folio <strong class="text-dark">#<?php echo storefront_escape($orderSuccess['id']); ?></strong> por un total de <strong class="text-danger"><?php echo storefront_escape(storefront_money($orderSuccess['total'])); ?></strong>.</p>
                        
                        <?php if (($orderSuccess['metodo_pago'] ?? '') === 'transferencia'): ?>
                            <div class="alert alert-light border border-primary border-opacity-25 rounded-4 p-3 text-start mb-4 bg-light">
                                <h6 class="fw-bold text-primary mb-2"><i class="bi bi-bank me-1"></i> Datos Oficiales para Transferencia SPEI:</h6>
                                <ul class="list-unstyled small mb-2 text-dark">
                                    <li><strong>Banco:</strong> BBVA Bancomer</li>
                                    <li><strong>Beneficiario:</strong> Carnicería Cano S.A. de C.V.</li>
                                    <li><strong>CLABE:</strong> 0121 8000 1234 5678 90</li>
                                    <li><strong>Concepto / Referencia:</strong> Folio #<?php echo storefront_escape($orderSuccess['id']); ?></li>
                                </ul>
                                <small class="text-muted">Envía tu comprobante vía WhatsApp para agilizar el despacho de tus cortes.</small>
                            </div>
                        <?php endif; ?>

                        <?php
                        $branchWa = '525514345414';
                        if (!empty($orderSuccess['id_sucursal']) && isset($link) && $link instanceof mysqli) {
                            $resB = mysqli_query($link, "SELECT whatsapp FROM cc_sucursales WHERE id_sucursal = " . (int)$orderSuccess['id_sucursal']);
                            if ($resB && $rowB = mysqli_fetch_assoc($resB)) {
                                $cleanWa = preg_replace('/[^0-9]/', '', (string)$rowB['whatsapp']);
                                if (!empty($cleanWa)) {
                                    $branchWa = (strlen($cleanWa) === 10) ? '52' . $cleanWa : $cleanWa;
                                }
                            }
                        }
                        $waText = urlencode("Hola Carnicería Cano, acabo de realizar el pedido #" . $orderSuccess['id'] . " por " . storefront_money($orderSuccess['total']) . ". Envío mis datos para seguimiento.");
                        $waUrl = "https://api.whatsapp.com/send?phone=" . $branchWa . "&text=" . $waText;
                        ?>

                        <div class="d-flex justify-content-center gap-3 flex-wrap mt-3">
                            <a href="index.php" class="btn btn-outline-secondary px-4 py-2.5 rounded-3 fw-bold">
                                <i class="bi bi-shop me-1"></i> Volver a la Tienda
                            </a>
                            <a href="<?php echo $waUrl; ?>" target="_blank" rel="noopener" class="btn btn-success px-4 py-2.5 rounded-3 fw-bold shadow-sm">
                                <i class="bi bi-whatsapp me-1"></i> Contactar por WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($cartTotals['items'] <= 0): ?>
            <!-- Si el carrito está vacío y no hay orden creada -->
            <div class="row justify-content-center">
                <div class="col-lg-6 text-center py-5">
                    <div class="p-5 bg-white rounded-4 border shadow-sm">
                        <i class="bi bi-bag-x text-muted" style="font-size: 3.5rem;"></i>
                        <h3 class="fw-bold text-dark mt-3 mb-2 font-serif">Tu Carrito está Vacío</h3>
                        <p class="text-muted mb-4">No tienes productos en tu carrito para finalizar un pedido.</p>
                        <a href="index.php" class="btn btn-danger px-4 py-2.5 rounded-3 fw-bold shadow-sm">
                            <i class="bi bi-grid me-1"></i> Ver Catálogo de Productos
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Formulario de Pedido (Solo se muestra cuando hay productos en carrito y no se ha finalizado el pedido) -->
            <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="checkout-panel p-4 bg-white rounded-4 shadow-sm border">
                    <form method="post" class="checkout-form" id="checkoutForm">
                        <input type="hidden" name="action" value="checkout">
                        <input type="hidden" name="csrf_token" value="<?php echo storefront_escape($_SESSION['csrf_token'] ?? ''); ?>">
                        <!-- Campo Trampa Honeypot Anti-Bots (invisible para clientes reales) -->
                        <div style="display:none !important; opacity:0; position:absolute; left:-9999px;" aria-hidden="true">
                            <input type="text" name="website_trap" value="" tabindex="-1" autocomplete="off">
                        </div>
                        <input type="hidden" id="latitud" name="latitud" value="<?php echo storefront_escape($formData['latitud']); ?>">
                        <input type="hidden" id="longitud" name="longitud" value="<?php echo storefront_escape($formData['longitud']); ?>">
                        <input type="hidden" id="distancia_km" name="distancia_km" value="<?php echo storefront_escape($formData['distancia_km']); ?>">

                        <div class="row g-3">
                            <!-- 1. Opción de Entrega (Debe estar ANTES de sucursales) -->
                            <div class="col-12">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <label class="form-label fw-bold text-dark fs-6 m-0">
                                        <i class="bi bi-box-seam-fill text-danger me-1"></i> Opción de Entrega *
                                    </label>
                                    <span class="badge bg-secondary bg-opacity-10 text-dark small">Paso importante</span>
                                </div>

                                <div class="row g-3">
                                    <!-- Tarjeta Recoger en Sucursal -->
                                    <div class="col-md-6">
                                        <div class="delivery-option-card p-3 rounded-4 <?php echo $formData['tipo_entrega'] === 'recoger' ? 'active' : ''; ?>" id="cardDeliveryRecoger" onclick="setDeliveryType('recoger')">
                                            <div class="d-flex align-items-start">
                                                <input class="form-check-input mt-1 me-3 fs-5" type="radio" name="tipo_entrega_radio" id="radioRecoger" value="recoger" <?php echo $formData['tipo_entrega'] === 'recoger' ? 'checked' : ''; ?> onchange="setDeliveryType('recoger')">
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <strong class="text-dark fs-6"><i class="bi bi-shop text-danger me-1"></i> Recoger en Sucursal</strong>
                                                        <span class="badge bg-success-subtle text-success border border-success fw-bold small">Gratis</span>
                                                    </div>
                                                    <span class="small text-muted d-block mt-1">Pasa por tu pedido a tu sucursal favorita sin costo extra.</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tarjeta Entrega a Domicilio -->
                                    <div class="col-md-6">
                                        <div class="delivery-option-card p-3 rounded-4 <?php echo $formData['tipo_entrega'] === 'domicilio' ? 'active' : ''; ?>" id="cardDeliveryDomicilio" onclick="setDeliveryType('domicilio')">
                                            <div class="d-flex align-items-start">
                                                <input class="form-check-input mt-1 me-3 fs-5" type="radio" name="tipo_entrega_radio" id="radioDomicilio" value="domicilio" <?php echo $formData['tipo_entrega'] === 'domicilio' ? 'checked' : ''; ?> onchange="setDeliveryType('domicilio')">
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <strong class="text-dark fs-6"><i class="bi bi-truck text-danger me-1"></i> Entrega a Domicilio</strong>
                                                        <span class="badge bg-danger-subtle text-danger border border-danger fw-bold small">Envío $<?php echo number_format($deliveryCost, 2); ?></span>
                                                    </div>
                                                    <span class="small text-muted d-block mt-1">Llevamos tus cortes a domicilio (Máx. 5 km • Mínimo $<?php echo number_format($minDeliveryAmount, 2); ?>).</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Select sincronizado que viaja en el formulario POST -->
                                <select class="form-select d-none" id="tipo_entrega" name="tipo_entrega" onchange="toggleDeliveryFields()">
                                    <option value="recoger" <?php echo $formData['tipo_entrega'] === 'recoger' ? 'selected' : ''; ?>>Recoger en Sucursal</option>
                                    <option value="domicilio" <?php echo $formData['tipo_entrega'] === 'domicilio' ? 'selected' : ''; ?>>Entrega a Domicilio</option>
                                </select>
                            </div>

                            <!-- 2. Al elegir Recoger en Sucursal: Se abren las sucursales disponibles -->
                            <div id="pickupBranchPanel" class="col-12 <?php echo $formData['tipo_entrega'] === 'recoger' ? '' : 'd-none'; ?>">
                                <div class="p-3 bg-light rounded-4 border border-secondary border-opacity-25 my-1">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div>
                                            <strong class="text-dark small d-block"><i class="bi bi-shop text-warning me-1"></i> Sucursales Disponibles para Recoger tu Pedido *</strong>
                                            <span class="small text-muted">Selecciona la sucursal donde deseas pasar por tus cortes:</span>
                                        </div>
                                    </div>

                                    <div class="row g-2 mt-1">
                                        <?php foreach ($checkoutBranches as $branch): ?>
                                            <div class="col-md-6">
                                                <div class="branch-select-card p-3 rounded-3 bg-white d-flex align-items-center justify-content-between <?php echo $formData['id_sucursal'] === (string) $branch['id_sucursal'] ? 'active' : ''; ?>" id="cardBranchPickup<?php echo (int) $branch['id_sucursal']; ?>" onclick="selectBranchFromMap('<?php echo (int) $branch['id_sucursal']; ?>')">
                                                    <div>
                                                        <strong class="d-block text-dark small"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?php echo storefront_escape($branch['desc_sucursal']); ?></strong>
                                                        <span class="small text-muted d-block" style="font-size: 0.78rem;"><?php echo storefront_escape($branch['direccion']); ?></span>
                                                        <span class="badge bg-warning text-dark mt-1" style="font-size: 0.7rem;"><i class="bi bi-clock me-1"></i><?php echo storefront_escape($branch['horario']); ?></span>
                                                    </div>
                                                    <span class="badge bg-danger rounded-pill px-2 py-1 select-badge <?php echo $formData['id_sucursal'] === (string) $branch['id_sucursal'] ? '' : 'd-none'; ?>"><i class="bi bi-check2"></i> Elegida</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-2 text-muted small"><i class="bi bi-info-circle me-1"></i> Tu pedido estará listo para entrega en esta sucursal sin costo adicional de envío.</div>
                                </div>
                            </div>

                            <!-- 3. Entrega a Domicilio: Se abre el mapa, GPS, cobertura y campos de dirección -->
                            <div id="deliveryFieldsPanel" class="col-12 <?php echo $formData['tipo_entrega'] === 'domicilio' ? '' : 'd-none'; ?>">
                                <!-- Alerta de compra mínima si el subtotal es menor al mínimo configurable -->
                                <div id="minDeliveryAlert" class="alert alert-warning p-3 rounded-4 shadow-sm mb-3 <?php echo $cartSubtotal >= $minDeliveryAmount ? 'd-none' : ''; ?>">
                                    <div class="d-flex align-items-start gap-2">
                                        <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
                                        <div>
                                            <strong class="d-block text-dark">Compra mínima para entrega a domicilio: $<?php echo number_format($minDeliveryAmount, 2); ?> MXN</strong>
                                            <span class="small text-dark">Tu subtotal actual es de <strong><?php echo storefront_money($cartSubtotal); ?></strong>. Te faltan <strong>$<?php echo number_format(max(0, $minDeliveryAmount - $cartSubtotal), 2); ?></strong> para solicitar envío a domicilio.</span>
                                            <div class="mt-2 d-flex gap-2 flex-wrap">
                                                <a href="index.php" class="btn btn-sm btn-dark rounded-pill fw-bold px-3"><i class="bi bi-cart-plus me-1"></i> Agregar más productos</a>
                                                <button type="button" class="btn btn-sm btn-outline-dark rounded-pill fw-bold px-3" onclick="setDeliveryType('recoger')"><i class="bi bi-shop me-1"></i> Cambiar a Recoger en Sucursal</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-3 bg-light rounded-4 border border-secondary border-opacity-25 my-1">
                                    <!-- Sucursal de despacho para entrega a domicilio -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-dark small" for="id_sucursal"><i class="bi bi-shop text-warning me-1"></i> Sucursal que prepara y despacha tu pedido *</label>
                                        <select class="form-select fw-semibold" id="id_sucursal" name="id_sucursal" onchange="selectBranchFromSelect()">
                                            <?php foreach ($checkoutBranches as $branch): ?>
                                                <option value="<?php echo (int) $branch['id_sucursal']; ?>" <?php echo $formData['id_sucursal'] === (string) $branch['id_sucursal'] ? 'selected' : ''; ?>><?php echo storefront_escape($branch['desc_sucursal']); ?> (<?php echo storefront_escape($branch['direccion']); ?> · <?php echo storefront_escape($branch['horario']); ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                        <div>
                                            <strong class="d-block text-dark small"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Cobertura y Geolocalización (Máximo 5 km)</strong>
                                            <span class="small text-muted">Usa tu GPS o haz clic en el mapa para marcar tu ubicación exacta de entrega.</span>
                                        </div>
                                        <button type="button" class="btn btn-danger btn-sm rounded-pill fw-bold px-3 shadow-sm" onclick="getGpsLocation()">
                                            <i class="bi bi-crosshair me-1"></i> Usar mi Ubicación GPS
                                        </button>
                                    </div>
                                    <div id="gpsAlertBox" class="d-none alert p-2.5 small mb-3"></div>

                                    <!-- Mapa Interactivo Leaflet -->
                                    <div class="mb-3">
                                        <div id="storefrontMap"></div>
                                        <div class="row g-2 mt-2">
                                            <?php foreach ($checkoutBranches as $branch): ?>
                                                <div class="col-md-6">
                                                    <div class="branch-select-card p-2 rounded-3 bg-white d-flex align-items-center justify-content-between <?php echo $formData['id_sucursal'] === (string) $branch['id_sucursal'] ? 'active' : ''; ?>" id="cardBranchDelivery<?php echo (int) $branch['id_sucursal']; ?>" onclick="selectBranchFromMap('<?php echo (int) $branch['id_sucursal']; ?>')">
                                                        <div>
                                                            <strong class="d-block small text-dark"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?php echo storefront_escape($branch['desc_sucursal']); ?></strong>
                                                            <span class="small text-muted d-block" style="font-size: 0.75rem;"><?php echo storefront_escape($branch['direccion']); ?></span>
                                                            <span class="badge bg-warning text-dark mt-1" style="font-size: 0.68rem;">Horario: <?php echo storefront_escape($branch['horario']); ?></span>
                                                        </div>
                                                        <a href="https://www.google.com/maps/search/?api=1&amp;query=<?php echo rawurlencode($branch['latitud'] . ',' . $branch['longitud']); ?>" target="_blank" rel="noopener" class="btn btn-outline-danger btn-sm p-1 px-2 ms-2" title="Abrir en Google Maps" onclick="event.stopPropagation();">
                                                            <i class="bi bi-box-arrow-up-right"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <!-- Dirección Completa sin errores de Google Maps -->
                                        <div class="col-12 position-relative">
                                             <label class="form-label fw-bold text-dark small" for="direccion_entrega"><i class="bi bi-house-door-fill text-danger me-1"></i> Dirección Completa (Calle y Número) *</label>
                                            <div class="input-group" style="height: 50px;">
                                                <span class="input-group-text bg-white border-end-0 text-muted" style="height: 50px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-geo-alt"></i></span>
                                                <input class="form-control border-start-0" style="height: 50px; line-height: 50px;" type="text" id="direccion_entrega" name="direccion_entrega" value="<?php echo storefront_escape($formData['direccion_entrega']); ?>" placeholder="Ej: Av. Morelos #15, San Cristóbal Centro" oninput="searchAddressSuggestions(this.value); checkPhoneStatus();" autocomplete="off">
                                            </div>
                                            <div id="addressSuggestions" class="d-none address-suggestions-container"></div>
                                            <div class="form-text small text-muted">Escribe tu calle y número, o selecciónala de la lista. También puedes arrastrar el pin azul en el mapa.</div>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-bold text-dark small" for="referencias_ubicacion"><i class="bi bi-signpost-2-fill text-warning me-1"></i> Referencias de la Ubicación para el Repartidor *</label>
                                            <div class="input-group" style="height: 50px;">
                                                <span class="input-group-text bg-white border-end-0 text-muted" style="height: 50px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-signpost-2 text-warning"></i></span>
                                                <input class="form-control border-start-0" style="height: 50px; line-height: 50px;" type="text" id="referencias_ubicacion" name="referencias_ubicacion" value="<?php echo storefront_escape($formData['referencias_ubicacion']); ?>" placeholder="Ej: Fachada blanca, zaguán negro, entre Calle Olivos y Jacarandas, frente al Oxxo">
                                            </div>
                                            <div class="form-text small text-muted">Indica color de fachada, entre qué calles se ubica o puntos clave de referencia para facilitar la entrega.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Datos Personales y de Contacto (Debajo de referencias/sucursal para saber de qué sucursal sale WhatsApp) -->
                            <div class="col-12">
                                <hr class="my-3 text-muted">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center justify-content-between mb-2" style="min-height: 21px;">
                                            <label class="form-label fw-bold text-dark small m-0" for="cliente_nombre"><i class="bi bi-person-fill text-danger me-1"></i> Nombre Completo *</label>
                                        </div>
                                        <input class="form-control" type="text" id="cliente_nombre" name="cliente_nombre" value="<?php echo storefront_escape($formData['cliente_nombre']); ?>" placeholder="Ej: Juan Pérez" required>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center justify-content-between mb-2" style="min-height: 21px;">
                                            <label class="form-label fw-bold text-dark small m-0" for="cliente_telefono"><i class="bi bi-telephone-fill text-danger me-1"></i> Teléfono de Contacto (WhatsApp) *</label>
                                            <div id="phoneVerificationBadge"></div>
                                        </div>
                                        <div class="input-group" style="height: 50px;">
                                            <input class="form-control" style="height: 50px; line-height: 50px;" type="text" id="cliente_telefono" name="cliente_telefono" value="<?php echo storefront_escape($formData['cliente_telefono']); ?>" placeholder="Ej: 55 1234 5678" oninput="checkPhoneStatus()" required>
                                            <button class="btn btn-success fw-bold px-3 d-none" type="button" id="btnVerifyWa" onclick="startWhatsAppVerification()" style="height: 50px;">
                                                <i class="bi bi-whatsapp me-1"></i> Enviar Código
                                            </button>
                                        </div>
                                        <div id="phoneHelpText" class="form-text small text-muted">Ingresa tu celular a 10 dígitos para validar tu pedido por WhatsApp.</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold text-dark small" for="cliente_email"><i class="bi bi-envelope-fill me-1"></i> Correo Electrónico (Opcional)</label>
                                        <input class="form-control" type="email" id="cliente_email" name="cliente_email" value="<?php echo storefront_escape($formData['cliente_email']); ?>" placeholder="correo@ejemplo.com">
                                    </div>
                                </div>
                            </div>

                            <hr class="my-3 text-muted">

                            <!-- Método de Pago: Solo SPEI para domicilio, y eliminación de campo 'con cuánto pagarás' -->
                            <div class="col-12">
                                <label class="form-label fw-bold text-dark small mb-2" for="metodo_pago"><i class="bi bi-wallet2 text-success me-1"></i> Método de Pago *</label>

                                <!-- Aviso exclusivo SPEI cuando es domicilio -->
                                <div id="deliveryPaymentNotice" class="alert alert-info p-3 rounded-4 small mb-3 <?php echo $formData['tipo_entrega'] === 'domicilio' ? '' : 'd-none'; ?>">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-bank fs-4 text-primary"></i>
                                        <div>
                                            <strong class="d-block text-dark">Pago Exclusivo por Transferencia SPEI para Entrega a Domicilio</strong>
                                            <span>Por seguridad de nuestros repartidores, los pedidos con entrega a domicilio se pagan exclusivamente vía transferencia bancaria SPEI antes del despacho.</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <select class="form-select fw-semibold" id="metodo_pago" name="metodo_pago" onchange="togglePaymentFields()">
                                        <?php if ($formData['tipo_entrega'] === 'domicilio'): ?>
                                            <option value="transferencia" selected>🏦 Transferencia Bancaria (SPEI)</option>
                                        <?php else: ?>
                                            <option value="efectivo" <?php echo $formData['metodo_pago'] === 'efectivo' ? 'selected' : ''; ?>>💵 Efectivo (Pago en tienda al recoger)</option>
                                            <option value="transferencia" <?php echo $formData['metodo_pago'] === 'transferencia' ? 'selected' : ''; ?>>🏦 Transferencia Bancaria (SPEI)</option>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <!-- Información para Transferencia Bancaria SPEI -->
                                <div class="col-12 <?php echo ($formData['tipo_entrega'] === 'domicilio' || $formData['metodo_pago'] === 'transferencia') ? '' : 'd-none'; ?>" id="transferInfoBox">
                                    <div class="p-3 bg-light border border-primary border-opacity-25 rounded-3">
                                        <h6 class="fw-bold text-primary m-0 mb-2"><i class="bi bi-bank me-1"></i> Datos Oficiales para Transferencia SPEI:</h6>
                                        <ul class="list-unstyled small mb-0 text-dark">
                                            <li><strong>Banco:</strong> BBVA Bancomer</li>
                                            <li><strong>Beneficiario:</strong> Carnicería Cano S.A. de C.V.</li>
                                            <li><strong>CLABE Interbancaria:</strong> 0121 8000 1234 5678 90</li>
                                            <li><strong>Concepto:</strong> Tu Nombre y Teléfono</li>
                                        </ul>
                                        <small class="text-muted d-block mt-2">Envía tu comprobante vía WhatsApp al finalizar tu pedido para agilizar el despacho de tus cortes.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 d-flex flex-wrap gap-2 align-items-center mt-4">
                                <button type="submit" id="btnSubmitOrder" class="btn btn-danger px-4 py-2.5 rounded-3 fw-bold shadow-sm">
                                    <i class="bi bi-check-circle-fill me-1"></i> Finalizar y Confirmar Pedido
                                </button>
                                <a href="carrito.php" class="btn btn-outline-secondary rounded-3 px-3">
                                    <i class="bi bi-arrow-left me-1"></i> Volver al Carrito
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Resumen de Compra -->
            <div class="col-lg-4">
                <aside class="summary-card p-4 bg-white rounded-4 shadow-sm border">
                    <span class="eyebrow-badge mb-2"><i class="bi bi-receipt me-1"></i> Resumen de Compra</span>
                    <h3 class="h4 fw-bold mb-3 font-serif">Total a Pagar</h3>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Total de Artículos</span>
                        <strong class="text-dark"><?php echo storefront_escape($cartTotals['items']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Subtotal</span>
                        <strong class="text-dark" id="summarySubtotalLabel"><?php echo storefront_escape(storefront_money($cartTotals['subtotal'])); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom" id="shippingRow">
                        <span class="text-muted">Costo de Envío</span>
                        <strong class="text-dark" id="shippingAmountLabel"><?php echo $formData['tipo_entrega'] === 'domicilio' ? storefront_money($deliveryCost) : '$0.00 (Gratis)'; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-3 my-2 border-bottom border-2">
                        <span class="h5 fw-bold text-dark m-0 font-serif">Total Final</span>
                        <strong class="h4 fw-bold text-danger m-0" id="summaryOrderTotal"><?php echo storefront_escape(storefront_money($cartTotals['total'])); ?></strong>
                    </div>
                </aside>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($orderSuccess === null && $cartTotals['items'] > 0): ?>
<script>
<?php
$branchCoordinates = [];
foreach ($checkoutBranches as $branch) {
    $branchCoordinates[(string) $branch['id_sucursal']] = [
        'lat' => (float) $branch['latitud'],
        'lng' => (float) $branch['longitud'],
        'name' => $branch['desc_sucursal'],
        'address' => $branch['direccion'],
        'hours' => $branch['horario'],
        'gmaps' => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($branch['latitud'] . ',' . $branch['longitud']),
    ];
}
?>
const BRANCH_COORDS = <?php echo json_encode($branchCoordinates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const DEFAULT_BRANCH_ID = Object.keys(BRANCH_COORDS)[0] || null;
const MIN_DELIVERY_AMOUNT = <?php echo (float)$minDeliveryAmount; ?>;
const DELIVERY_COST = <?php echo (float)$deliveryCost; ?>;
const CART_SUBTOTAL = <?php echo (float)$cartSubtotal; ?>;

var mapEngine = 'google';
var map = null;
var branchMarkers = {};
var userMarker = null;
var radiusCircle = null;
var geocoder = null;

function initStorefrontMap() {
    var mapContainer = document.getElementById('storefrontMap');
    if (!mapContainer || map !== null) return;

    if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
        mapEngine = 'google';
        initGoogleMap(mapContainer);
    } else if (typeof L !== 'undefined') {
        mapEngine = 'leaflet';
        initLeafletMap(mapContainer);
    } else {
        setTimeout(initStorefrontMap, 150);
    }
}

function initGoogleMap(mapContainer) {
    var branchId = document.getElementById('id_sucursal').value;
    var branch = BRANCH_COORDS[branchId] || BRANCH_COORDS[DEFAULT_BRANCH_ID] || { lat: 19.5950, lng: -99.0410 };
    map = new google.maps.Map(mapContainer, {
        center: { lat: branch.lat, lng: branch.lng },
        zoom: 13,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        streetViewControl: false,
        mapTypeControl: false,
        fullscreenControl: true,
        zoomControl: true
    });

    Object.keys(BRANCH_COORDS).forEach(function (key) {
        var b = BRANCH_COORDS[key];
        var marker = new google.maps.Marker({
            position: { lat: b.lat, lng: b.lng },
            map: map,
            title: b.name,
            icon: { url: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png' }
        });

        var infoWindow = new google.maps.InfoWindow({
            content: '<div style="font-family: sans-serif; padding: 4px; max-width: 220px;">' +
                '<strong style="color:#7E1414; font-weight:800; display:block; margin-bottom:2px;">' + b.name + '</strong>' +
                '<small style="color:#555; display:block; margin-bottom:4px;">' + b.address + '</small>' +
                '<span class="badge bg-warning text-dark" style="font-size:0.7rem;">Horario: ' + b.hours + '</span><br>' +
                '<a href="' + b.gmaps + '" target="_blank" class="btn btn-outline-danger btn-sm text-decoration-none mt-2 p-1 px-2 fw-bold" style="font-size:0.75rem;">' +
                    '<i class="bi bi-box-arrow-up-right"></i> Abrir en Google Maps' +
                '</a>' +
            '</div>'
        });

        marker.addListener('click', function () {
            infoWindow.open(map, marker);
        });

        branchMarkers[key] = { marker: marker, infoWindow: infoWindow };
    });

    map.addListener('click', function (e) {
        setUserLocationOnMap(e.latLng.lat(), e.latLng.lng());
    });

    // Si ya existe latitud y longitud previa (por ejemplo al recargar por código OTP), restaurar ubicación en Google Maps
    var currentLat = parseFloat(document.getElementById('latitud').value);
    var currentLng = parseFloat(document.getElementById('longitud').value);
    if (!isNaN(currentLat) && !isNaN(currentLng)) {
        setUserLocationOnMap(currentLat, currentLng, true);
    }

    updateMapVisuals();
}

function initLeafletMap(mapContainer) {
    var firstBranch = BRANCH_COORDS[DEFAULT_BRANCH_ID] || { lat: 19.5920, lng: -99.0439 };
    map = L.map('storefrontMap').setView([firstBranch.lat, firstBranch.lng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://openstreetmap.org">OpenStreetMap</a>'
    }).addTo(map);

    Object.keys(BRANCH_COORDS).forEach(function (key) {
        var b = BRANCH_COORDS[key];
        var iconHtml = '<div class="custom-map-icon" style="width: 32px; height: 32px;"><i class="bi bi-shop"></i></div>';
        var customIcon = L.divIcon({
            html: iconHtml,
            className: '',
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });

        var marker = L.marker([b.lat, b.lng], { icon: customIcon }).addTo(map);
        marker.bindPopup(
            '<div style="font-family: sans-serif; padding: 2px;">' +
                '<strong style="color:#7E1414; font-weight:800; display:block;">' + b.name + '</strong>' +
                '<small style="color:#555; display:block; margin:2px 0 4px;">' + b.address + '</small>' +
                '<span class="badge bg-warning text-dark" style="font-size:0.7rem;">Horario: ' + b.hours + '</span><br>' +
                '<a href="' + b.gmaps + '" target="_blank" class="btn btn-outline-danger btn-sm text-decoration-none mt-2 p-1 px-2 fw-bold" style="font-size:0.75rem;">' +
                    '<i class="bi bi-box-arrow-up-right"></i> Abrir en Google Maps' +
                '</a>' +
            '</div>'
        );

        branchMarkers[key] = { marker: marker };
    });

    map.on('click', function (e) {
        setUserLocationOnMap(e.latlng.lat, e.latlng.lng);
    });

    updateMapVisuals();
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

var suggestTimeout = null;
function searchAddressSuggestions(query) {
    clearTimeout(suggestTimeout);
    var box = document.getElementById('addressSuggestions');
    if (!box) return;

    query = (query || '').trim();
    if (query.length < 3) {
        box.classList.add('d-none');
        box.innerHTML = '';
        return;
    }

    suggestTimeout = setTimeout(function () {
        var formData = new FormData();
        formData.append('action', 'google_places_autocomplete');
        formData.append('query', query);

        fetch('pedido.php', { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.success && data.suggestions && data.suggestions.length > 0) {
                    var html = '';
                    data.suggestions.forEach(function (s) {
                        var main = s.main_text || s.full_text;
                        var secondary = s.secondary_text || '';
                        var placeId = s.place_id;

                        html += '<div class="address-suggestion-item" onclick="selectGooglePlaceSuggestion(\'' + encodeURIComponent(placeId) + '\', \'' + encodeURIComponent(s.full_text) + '\')">' +
                            '<div class="fw-bold text-dark"><i class="bi bi-geo-alt-fill text-danger me-1"></i>' + escapeHtml(main) + '</div>' +
                            (secondary ? '<div class="small text-muted">' + escapeHtml(secondary) + '</div>' : '') +
                            '</div>';
                    });
                    box.innerHTML = html;
                    box.classList.remove('d-none');
                } else {
                    searchPhotonFallback(query);
                }
            })
            .catch(function () {
                searchPhotonFallback(query);
            });
    }, 350);
}

function searchPhotonFallback(query) {
    var box = document.getElementById('addressSuggestions');
    if (!box) return;

    var branchId = document.getElementById('id_sucursal').value;
    var b = BRANCH_COORDS[branchId] || BRANCH_COORDS[DEFAULT_BRANCH_ID] || { lat: 19.595, lng: -99.041 };
    var url = 'https://photon.komoot.io/api/?q=' + encodeURIComponent(query) + '&lat=' + b.lat + '&lon=' + b.lng + '&limit=5&lang=es';

    fetch(url)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.features || data.features.length === 0) {
                box.classList.add('d-none');
                box.innerHTML = '';
                return;
            }

            var html = '';
            data.features.forEach(function (f) {
                var p = f.properties || {};
                var name = p.name || p.street || '';
                var parts = [];
                if (p.street && p.street !== name) parts.push(p.street);
                if (p.housenumber) parts.push('#' + p.housenumber);
                if (p.locality) parts.push(p.locality);
                if (p.district) parts.push(p.district);
                if (p.city) parts.push(p.city);
                if (p.state) parts.push(p.state);

                var fullTitle = name + (parts.length > 0 ? ', ' + parts.join(', ') : '');
                var coords = f.geometry && f.geometry.coordinates ? f.geometry.coordinates : null;
                var lon = coords ? coords[0] : 0;
                var lat = coords ? coords[1] : 0;

                html += '<div class="address-suggestion-item" onclick="selectPhotonSuggestion(\'' + encodeURIComponent(fullTitle) + '\', ' + lat + ', ' + lon + ')">' +
                    '<div class="fw-bold text-dark"><i class="bi bi-geo-alt-fill text-danger me-1"></i>' + escapeHtml(name) + '</div>' +
                    (parts.length > 0 ? '<div class="small text-muted">' + escapeHtml(parts.join(', ')) + '</div>' : '') +
                    '</div>';
            });

            box.innerHTML = html;
            box.classList.remove('d-none');
        })
        .catch(function () {
            box.classList.add('d-none');
        });
}

function selectGooglePlaceSuggestion(encodedPlaceId, encodedFullText) {
    var placeId = decodeURIComponent(encodedPlaceId);
    var fullText = decodeURIComponent(encodedFullText);
    var dirInput = document.getElementById('direccion_entrega');
    var box = document.getElementById('addressSuggestions');

    if (dirInput) dirInput.value = fullText;
    if (box) {
        box.classList.add('d-none');
        box.innerHTML = '';
    }

    var formData = new FormData();
    formData.append('action', 'google_geocode_place');
    formData.append('place_id', placeId);
    formData.append('address', fullText);

    fetch('pedido.php', { method: 'POST', body: formData })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success && data.lat && data.lng) {
                setUserLocationOnMap(data.lat, data.lng, true);
                if (data.formatted_address && dirInput) {
                    dirInput.value = data.formatted_address;
                }
            }
        })
        .catch(function (e) { console.log(e); });

    checkPhoneStatus();
}

function selectPhotonSuggestion(encodedTitle, lat, lon) {
    var title = decodeURIComponent(encodedTitle);
    var dirInput = document.getElementById('direccion_entrega');
    var box = document.getElementById('addressSuggestions');
    if (dirInput) dirInput.value = title;
    if (box) {
        box.classList.add('d-none');
        box.innerHTML = '';
    }
    if (lat && lon) {
        setUserLocationOnMap(lat, lon, true);
    }
    checkPhoneStatus();
}

document.addEventListener('click', function (e) {
    var box = document.getElementById('addressSuggestions');
    var dirInput = document.getElementById('direccion_entrega');
    if (box && !box.contains(e.target) && e.target !== dirInput) {
        box.classList.add('d-none');
    }
});

function updateMapVisuals() {
    var branchId = document.getElementById('id_sucursal').value;
    var branch = BRANCH_COORDS[branchId] || BRANCH_COORDS[DEFAULT_BRANCH_ID];

    // Actualizar tarjetas de sucursal
    Object.keys(BRANCH_COORDS).forEach(function (key) {
        var cardP = document.getElementById('cardBranchPickup' + key);
        if (cardP) {
            var isSel = (branchId === key);
            cardP.classList.toggle('active', isSel);
            cardP.classList.toggle('border-danger', isSel);
            cardP.classList.toggle('bg-danger', isSel);
            cardP.classList.toggle('bg-opacity-10', isSel);
            var bBadge = cardP.querySelector('.select-badge');
            if (bBadge) bBadge.classList.toggle('d-none', !isSel);
        }
        var cardD = document.getElementById('cardBranchDelivery' + key);
        if (cardD) {
            var isSelD = (branchId === key);
            cardD.classList.toggle('active', isSelD);
            cardD.classList.toggle('border-danger', isSelD);
            cardD.classList.toggle('bg-danger', isSelD);
            cardD.classList.toggle('bg-opacity-10', isSelD);
        }
    });

    if (!map || !branch) return;

    var lat = parseFloat(document.getElementById('latitud').value);
    var lng = parseFloat(document.getElementById('longitud').value);
    var hasUserLoc = !isNaN(lat) && !isNaN(lng);

    var isWithin = true;
    if (hasUserLoc) {
        var dist = calculateDistance(lat, lng, branch.lat, branch.lng);
        isWithin = dist <= 5.0;
    }

    var circleColor = isWithin ? '#198754' : '#DC3545';

    if (mapEngine === 'google') {
        if (radiusCircle) {
            radiusCircle.setCenter({ lat: branch.lat, lng: branch.lng });
            radiusCircle.setOptions({
                strokeColor: circleColor, fillColor: circleColor,
                strokeOpacity: 0.8, strokeWeight: 2, fillOpacity: 0.15
            });
        } else {
            radiusCircle = new google.maps.Circle({
                strokeColor: circleColor, strokeOpacity: 0.8, strokeWeight: 2,
                fillColor: circleColor, fillOpacity: 0.15, map: map,
                center: { lat: branch.lat, lng: branch.lng }, radius: 5000
            });
        }
    } else {
        if (radiusCircle) {
            radiusCircle.setLatLng([branch.lat, branch.lng]);
            radiusCircle.setStyle({ color: circleColor, fillColor: circleColor });
        } else {
            radiusCircle = L.circle([branch.lat, branch.lng], {
                color: circleColor, fillColor: circleColor, fillOpacity: 0.15, radius: 5000
            }).addTo(map);
        }
    }
}

function setUserLocationOnMap(userLat, userLng, skipReverseGeocode) {
    document.getElementById('latitud').value = userLat;
    document.getElementById('longitud').value = userLng;

    if (map) {
        if (mapEngine === 'google') {
            var pos = { lat: userLat, lng: userLng };
            if (userMarker) {
                userMarker.setPosition(pos);
            } else {
                userMarker = new google.maps.Marker({
                    position: pos, map: map, title: 'Tu Ubicación de Entrega', draggable: true,
                    icon: { url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png' }
                });
                userMarker.addListener('dragend', function (e) {
                    setUserLocationOnMap(e.latLng.lat(), e.latLng.lng());
                });
            }
            map.setCenter(pos);
            map.setZoom(15);
        } else {
            if (userMarker) {
                userMarker.setLatLng([userLat, userLng]);
            } else {
                var userIconHtml = '<div class="custom-map-icon" style="background:#0D6EFD; width: 34px; height: 34px;"><i class="bi bi-house-door-fill"></i></div>';
                var userIcon = L.divIcon({ html: userIconHtml, className: '', iconSize: [34, 34], iconAnchor: [17, 17] });
                userMarker = L.marker([userLat, userLng], { icon: userIcon, draggable: true }).addTo(map);
                userMarker.bindPopup('<strong>Tu Ubicación de Entrega</strong><br><small>Puedes arrastrar el pin azul</small>').openPopup();
                userMarker.on('dragend', function () {
                    var p = userMarker.getLatLng();
                    setUserLocationOnMap(p.lat, p.lng);
                });
            }
            map.setView([userLat, userLng], 15);
        }
    }

    if (!skipReverseGeocode) {
        reverseGeocodeAddress(userLat, userLng);
    }

    validateBranchDistance();
}

function reverseGeocodeAddress(userLat, userLng) {
    var dirInput = document.getElementById('direccion_entrega');
    if (!dirInput) return;

    if (mapEngine === 'google' && typeof google !== 'undefined' && google.maps && google.maps.Geocoder) {
        if (!geocoder) geocoder = new google.maps.Geocoder();
        geocoder.geocode({ location: { lat: userLat, lng: userLng } }, function (results, status) {
            if (status === 'OK' && results && results[0]) {
                dirInput.value = results[0].formatted_address;
                checkPhoneStatus();
            }
        });
    } else {
        fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + userLat + '&lon=' + userLng)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.display_name) {
                    dirInput.value = data.display_name;
                    checkPhoneStatus();
                }
            })
            .catch(function (err) { console.log(err); });
    }
}

function selectBranchFromMap(branchId) {
    document.getElementById('id_sucursal').value = branchId;
    selectBranchFromSelect();
}

function selectBranchFromSelect() {
    var branchId = document.getElementById('id_sucursal').value;
    var branch = BRANCH_COORDS[branchId];
    if (map && branch) {
        if (mapEngine === 'google') {
            map.panTo({ lat: branch.lat, lng: branch.lng });
        } else {
            map.panTo([branch.lat, branch.lng]);
        }
        if (branchMarkers[branchId] && branchMarkers[branchId].infoWindow) {
            branchMarkers[branchId].infoWindow.open(map, branchMarkers[branchId].marker);
        }
    }
    updateMapVisuals();
    validateBranchDistance();
    checkPhoneStatus();
}

function setDeliveryType(type) {
    var tipoSelect = document.getElementById('tipo_entrega');
    if (tipoSelect) {
        tipoSelect.value = type;
    }
    var radioRecoger = document.getElementById('radioRecoger');
    var radioDomicilio = document.getElementById('radioDomicilio');
    if (radioRecoger) radioRecoger.checked = (type === 'recoger');
    if (radioDomicilio) radioDomicilio.checked = (type === 'domicilio');

    var cardRecoger = document.getElementById('cardDeliveryRecoger');
    var cardDomicilio = document.getElementById('cardDeliveryDomicilio');
    if (cardRecoger) {
        cardRecoger.classList.toggle('active', type === 'recoger');
    }
    if (cardDomicilio) {
        cardDomicilio.classList.toggle('active', type === 'domicilio');
    }

    toggleDeliveryFields();
}

function toggleDeliveryFields() {
    var tipo = document.getElementById('tipo_entrega').value;
    var pickupPanel = document.getElementById('pickupBranchPanel');
    var deliveryPanel = document.getElementById('deliveryFieldsPanel');
    var dirInput = document.getElementById('direccion_entrega');
    var refInput = document.getElementById('referencias_ubicacion');
    var paymentSelect = document.getElementById('metodo_pago');
    var paymentNotice = document.getElementById('deliveryPaymentNotice');
    var transferInfoBox = document.getElementById('transferInfoBox');
    var minAlert = document.getElementById('minDeliveryAlert');
    var shippingLabel = document.getElementById('shippingAmountLabel');
    var totalLabel = document.getElementById('summaryOrderTotal');

    if (tipo === 'domicilio') {
        if (pickupPanel) pickupPanel.classList.add('d-none');
        if (deliveryPanel) deliveryPanel.classList.remove('d-none');
        if (dirInput) dirInput.required = true;
        if (refInput) refInput.required = true;

        // Actualizar resumen con envío
        if (shippingLabel) shippingLabel.textContent = '$' + DELIVERY_COST.toFixed(2);
        if (totalLabel) totalLabel.textContent = '$' + (CART_SUBTOTAL + DELIVERY_COST).toFixed(2);

        // Pago: Solo SPEI para domicilio
        if (paymentSelect) {
            paymentSelect.innerHTML = '<option value="transferencia" selected>🏦 Transferencia Bancaria (SPEI)</option>';
        }
        if (paymentNotice) paymentNotice.classList.remove('d-none');
        if (transferInfoBox) transferInfoBox.classList.remove('d-none');

        // Validar monto mínimo de compra
        var isSubtotalOk = CART_SUBTOTAL >= MIN_DELIVERY_AMOUNT;
        if (minAlert) {
            minAlert.classList.toggle('d-none', isSubtotalOk);
        }

        setTimeout(function () {
            initStorefrontMap();
            if (map && mapEngine === 'google' && typeof google !== 'undefined' && google.maps) {
                google.maps.event.trigger(map, 'resize');
                var branchId = document.getElementById('id_sucursal').value;
                var b = BRANCH_COORDS[branchId] || BRANCH_COORDS[DEFAULT_BRANCH_ID];
                if (b) map.setCenter({ lat: b.lat, lng: b.lng });
            } else if (map && map.invalidateSize) {
                map.invalidateSize();
            }
        }, 150);

        validateBranchDistance();
    } else {
        // Recoger en sucursal
        if (pickupPanel) pickupPanel.classList.remove('d-none');
        if (deliveryPanel) deliveryPanel.classList.add('d-none');
        if (dirInput) dirInput.required = false;
        if (refInput) refInput.required = false;

        // Actualizar resumen sin envío
        if (shippingLabel) shippingLabel.textContent = '$0.00 (Gratis)';
        if (totalLabel) totalLabel.textContent = '$' + CART_SUBTOTAL.toFixed(2);

        // Pago: Permitir Efectivo o Transferencia
        if (paymentSelect) {
            var currentVal = paymentSelect.value;
            paymentSelect.innerHTML = '<option value="efectivo"' + (currentVal === 'efectivo' ? ' selected' : '') + '>💵 Efectivo (Pago en tienda al recoger)</option>' +
                                      '<option value="transferencia"' + (currentVal === 'transferencia' ? ' selected' : '') + '>🏦 Transferencia Bancaria (SPEI)</option>';
        }
        if (paymentNotice) paymentNotice.classList.add('d-none');
        if (minAlert) minAlert.classList.add('d-none');

        togglePaymentFields();
        validateBranchDistance();
    }
}

function togglePaymentFields() {
    var metodo = document.getElementById('metodo_pago') ? document.getElementById('metodo_pago').value : 'transferencia';
    var transferBox = document.getElementById('transferInfoBox');
    var tipo = document.getElementById('tipo_entrega').value;

    if (tipo === 'domicilio' || metodo === 'transferencia') {
        if (transferBox) transferBox.classList.remove('d-none');
    } else {
        if (transferBox) transferBox.classList.add('d-none');
    }
}

function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return parseFloat((R * c).toFixed(2));
}

function getGpsLocation() {
    var gpsBox = document.getElementById('gpsAlertBox');
    if (!navigator.geolocation) {
        if (gpsBox) {
            gpsBox.className = 'mt-2 alert alert-warning p-2.5 small d-block';
            gpsBox.textContent = 'Tu dispositivo o navegador no soporta geolocalización GPS.';
        }
        return;
    }

    if (gpsBox) {
        gpsBox.className = 'mt-2 alert alert-info p-2.5 small d-block';
        gpsBox.textContent = 'Obteniendo tu ubicación GPS actual... Por favor acepta los permisos en tu navegador.';
    }

    navigator.geolocation.getCurrentPosition(function (pos) {
        var userLat = pos.coords.latitude;
        var userLng = pos.coords.longitude;
        setUserLocationOnMap(userLat, userLng);
    }, function (err) {
        if (gpsBox) {
            gpsBox.className = 'mt-2 alert alert-danger p-2.5 small d-block';
            gpsBox.textContent = 'No se pudo obtener la ubicación GPS (' + err.message + '). Puedes hacer clic en el mapa o buscar tu dirección.';
        }
    }, { enableHighAccuracy: true, timeout: 10000 });
}

function validateBranchDistance() {
    var lat = parseFloat(document.getElementById('latitud').value);
    var lng = parseFloat(document.getElementById('longitud').value);
    var branchId = document.getElementById('id_sucursal').value;
    var tipoEntrega = document.getElementById('tipo_entrega').value;
    var gpsBox = document.getElementById('gpsAlertBox');
    var btnSubmit = document.getElementById('btnSubmitOrder');

    if (tipoEntrega !== 'domicilio') {
        if (btnSubmit) btnSubmit.disabled = false;
        return;
    }

    // Validar primero si alcanza el monto mínimo para entrega a domicilio
    if (CART_SUBTOTAL < MIN_DELIVERY_AMOUNT) {
        if (btnSubmit) btnSubmit.disabled = true;
        return;
    }

    if (isNaN(lat) || isNaN(lng) || !BRANCH_COORDS[branchId]) {
        if (gpsBox && gpsBox.classList.contains('d-none')) {
            gpsBox.className = 'mt-2 alert alert-info p-2.5 small d-block';
            gpsBox.innerHTML = '<i class="bi bi-info-circle-fill me-1"></i> Usa el botón <strong>"Usar mi Ubicación GPS"</strong> o haz clic en el mapa para verificar tu cobertura de 5 km.';
        }
        if (btnSubmit) btnSubmit.disabled = false;
        return;
    }

    var branch = BRANCH_COORDS[branchId];
    var dist = calculateDistance(lat, lng, branch.lat, branch.lng);
    document.getElementById('distancia_km').value = dist;

    if (dist > 5.0) {
        if (gpsBox) {
            gpsBox.className = 'mt-2 alert alert-danger p-2.5 small d-block';
            gpsBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Ubicación a ' + dist + ' km de ' + branch.name + ':</strong> Excede el radio máximo de 5 km para entrega a domicilio. Selecciona <em>Recoger en Sucursal</em> o cambia a otra sucursal disponible.';
        }
        if (btnSubmit) btnSubmit.disabled = true;
    } else {
        if (gpsBox) {
            gpsBox.className = 'mt-2 alert alert-success p-2.5 small d-block';
            gpsBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> <strong>Ubicación válida (' + dist + ' km de ' + branch.name + '):</strong> Cobertura de entrega a domicilio confirmada.';
        }
        if (btnSubmit) btnSubmit.disabled = false;
    }

    updateMapVisuals();
}

var isPhoneVerified = false;
var checkPhoneTimer = null;

function checkPhoneStatus() {
    clearTimeout(checkPhoneTimer);
    checkPhoneTimer = setTimeout(function () {
        var phoneInput = document.getElementById('cliente_telefono');
        var dirInput = document.getElementById('direccion_entrega');
        var tipoSelect = document.getElementById('tipo_entrega');
        if (!phoneInput) return;

        var phone = phoneInput.value.replace(/[^0-9]/g, '');
        var direccion = dirInput ? dirInput.value.trim() : '';
        var tipoEntrega = tipoSelect ? tipoSelect.value : 'recoger';

        var badge = document.getElementById('phoneVerificationBadge');
        var btnWa = document.getElementById('btnVerifyWa');
        var helpText = document.getElementById('phoneHelpText');

        if (phone.length < 10) {
            if (badge) badge.innerHTML = '';
            if (btnWa) btnWa.classList.add('d-none');
            if (helpText) helpText.innerHTML = 'Ingresa tu celular a 10 dígitos para validar tu pedido.';
            isPhoneVerified = false;
            return;
        }

        var branchSelect = document.getElementById('id_sucursal');
        var branchId = branchSelect ? branchSelect.value : '1';

        var formData = new FormData();
        formData.append('action', 'check_phone_verification');
        formData.append('phone', phone);
        formData.append('direccion', direccion);
        formData.append('tipo_entrega', tipoEntrega);
        formData.append('id_sucursal', branchId);

        fetch('pedido.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.verified) {
                    isPhoneVerified = true;
                    if (badge) badge.innerHTML = '<span class="badge bg-success small"><i class="bi bi-shield-check me-1"></i> Verificado</span>';
                    if (btnWa) btnWa.classList.add('d-none');
                    if (helpText) helpText.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Dispositivo y dirección autorizados. ¡Todo listo!</span>';
                } else {
                    isPhoneVerified = false;
                    if (badge) badge.innerHTML = '<span class="badge bg-warning text-dark small"><i class="bi bi-shield-exclamation me-1"></i> Requiere Verificación</span>';
                    if (btnWa) btnWa.classList.remove('d-none');
                    if (tipoEntrega === 'domicilio' && direccion.length > 5) {
                        if (helpText) helpText.innerHTML = '<span class="text-danger small fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i> Nueva dirección detectada: Presiona "Enviar Código" para autorizar la entrega a este domicilio por WhatsApp.</span>';
                    } else {
                        if (helpText) helpText.innerHTML = 'Presiona <strong>"Enviar Código"</strong> para recibir tus 4 dígitos en WhatsApp.';
                    }
                }
            })
            .catch(err => console.log(err));
    }, 400);
}

function startWhatsAppVerification() {
    var phoneInput = document.getElementById('cliente_telefono');
    var dirInput = document.getElementById('direccion_entrega');
    var tipoSelect = document.getElementById('tipo_entrega');
    var branchSelect = document.getElementById('id_sucursal');
    if (!phoneInput) return;

    var phone = phoneInput.value.replace(/[^0-9]/g, '');
    var direccion = dirInput ? dirInput.value.trim() : '';
    var tipoEntrega = tipoSelect ? tipoSelect.value : 'recoger';
    var branchId = branchSelect ? branchSelect.value : '1';

    if (phone.length < 10) {
        alert('Ingresa un número telefónico válido a 10 dígitos.');
        return;
    }

    var formData = new FormData();
    formData.append('action', 'send_whatsapp_otp');
    formData.append('phone', phone);
    formData.append('direccion', direccion);
    formData.append('tipo_entrega', tipoEntrega);
    formData.append('id_sucursal', branchId);

    var alertBox = document.getElementById('otpAlertBox');
    if (alertBox) alertBox.innerHTML = '';

    fetch('pedido.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.already_verified) {
                isPhoneVerified = true;
                checkPhoneStatus();
                alert('Este número y dirección ya están autorizados.');
                return;
            }

            if (data.ok) {
                var otpNoticeText = document.getElementById('otpNoticeText');
                if (otpNoticeText) {
                    otpNoticeText.innerHTML = '<div class="alert alert-success p-3 text-center small mb-3 rounded-3 shadow-sm">' +
                        '<i class="bi bi-whatsapp fs-4 d-block mb-1 text-success"></i>' +
                        '<strong>¡Te enviamos un código a tu WhatsApp!</strong><br>' +
                        'Revisa la aplicación de WhatsApp en tu teléfono e ingresa los 4 dígitos a continuación:' +
                        '</div>';
                }

                var codeInput = document.getElementById('otp_input_code');
                if (codeInput) {
                    codeInput.value = '';
                    setTimeout(() => codeInput.focus(), 500);
                }

                var modalEl = document.getElementById('otpModal');
                if (modalEl) {
                    var modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            } else {
                alert(data.message || 'No se pudo enviar el código OTP.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error de conexión al generar el código OTP.');
        });
}

function submitOtpCode() {
    var phoneInput = document.getElementById('cliente_telefono');
    var codeInput = document.getElementById('otp_input_code');
    var dirInput = document.getElementById('direccion_entrega');
    var branchSelect = document.getElementById('id_sucursal');
    var alertBox = document.getElementById('otpAlertBox');
    if (!phoneInput || !codeInput) return;

    var phone = phoneInput.value.replace(/[^0-9]/g, '');
    var code = codeInput.value.trim();
    var direccion = dirInput ? dirInput.value.trim() : '';
    var branchId = branchSelect ? branchSelect.value : '1';

    if (code.length !== 4) {
        if (alertBox) alertBox.innerHTML = '<div class="alert alert-danger p-2 small"><i class="bi bi-exclamation-triangle-fill me-1"></i> Ingresa los 4 dígitos del código.</div>';
        return;
    }

    var formData = new FormData();
    formData.append('action', 'verify_whatsapp_otp');
    formData.append('phone', phone);
    formData.append('code', code);
    formData.append('direccion', direccion);
    formData.append('id_sucursal', branchId);

    fetch('pedido.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.ok) {
                isPhoneVerified = true;
                checkPhoneStatus();
                var modalEl = document.getElementById('otpModal');
                if (modalEl) {
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                alert('¡Teléfono y dirección autorizados correctamente!');
            } else {
                if (alertBox) alertBox.innerHTML = '<div class="alert alert-danger p-2 small"><i class="bi bi-x-circle-fill me-1"></i> ' + data.message + '</div>';
            }
        })
        .catch(err => console.log(err));
}

document.addEventListener('DOMContentLoaded', function () {
    toggleDeliveryFields();
    togglePaymentFields();
    checkPhoneStatus();
});
</script>

<!-- Modal de Verificación WhatsApp OTP ($0 Costo) -->
<div class="modal fade" id="otpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-shield-check me-1"></i> Verificación por WhatsApp</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <div id="otpNoticeText">
          <div class="alert alert-success p-3 text-center small mb-3 rounded-3 shadow-sm">
            <i class="bi bi-whatsapp fs-4 d-block mb-1 text-success"></i>
            <strong>¡Te enviamos un código a tu WhatsApp!</strong><br>
            Revisa tus mensajes en WhatsApp e ingresa los 4 dígitos a continuación:
          </div>
        </div>

        <label class="form-label fw-bold small text-dark mb-2">Código de 4 dígitos:</label>
        <div class="d-flex justify-content-center gap-2 mb-3">
          <input type="text" id="otp_input_code" class="form-control text-center fw-bold fs-3 text-success border-2 border-success" style="max-width: 170px; letter-spacing: 6px;" maxlength="4" placeholder="0000" autocomplete="off" inputmode="numeric">
        </div>
        <button type="button" onclick="submitOtpCode()" class="btn btn-success btn-lg px-4 fw-bold shadow-sm w-100 mb-2">
          <i class="bi bi-check-circle-fill me-1"></i> Confirmar Código
        </button>
        <div id="otpAlertBox" class="mt-3"></div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
