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
            <div class="success-panel p-4 mb-4 bg-success bg-opacity-10 border border-success rounded-4 text-center shadow-sm">
                <div class="display-4 text-success mb-2"><i class="bi bi-check-circle-fill"></i></div>
                <span class="eyebrow-badge text-success mb-1">¡Pedido Registrado con Éxito!</span>
                <h2 class="h3 fw-bold text-dark font-serif">Gracias por tu compra, <?php echo storefront_escape($orderSuccess['name']); ?></h2>
                <p class="mb-0 text-muted fs-6">Tu pedido quedó guardado con el folio <strong class="text-dark">#<?php echo storefront_escape($orderSuccess['id']); ?></strong> por un total de <strong class="text-danger"><?php echo storefront_escape(storefront_money($orderSuccess['total'])); ?></strong>.</p>
                <div class="mt-3 d-flex justify-content-center gap-2 flex-wrap">
                    <a href="index.php" class="btn btn-success px-4 rounded-pill fw-bold"><i class="bi bi-shop me-1"></i> Volver a la Tienda</a>
                    <a href="https://wa.me/?text=Hola%20Carnicer%C3%ADa%20Cano,%20acabo%20de%20realizar%20el%20pedido%20%23<?php echo storefront_escape($orderSuccess['id']); ?>" target="_blank" rel="noopener" class="btn btn-outline-success px-4 rounded-pill fw-bold"><i class="bi bi-whatsapp me-1"></i> Contactar por WhatsApp</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Banner de Encabezado Dinámico -->
        <div class="page-header-banner">
            <span class="eyebrow-badge"><i class="bi bi-clipboard-check-fill me-1"></i> Datos de Entrega y Pago</span>
            <h2 class="results-heading">Confirma tu Pedido</h2>
            <p class="mb-0">Consulta la ubicación de nuestras Sucursales en el mapa interactivo, valida el radio de 5 km de entrega a domicilio y elige tu método de pago.</p>
        </div>

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
                            <!-- Datos Personales -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark small" for="cliente_nombre"><i class="bi bi-person-fill text-danger me-1"></i> Nombre Completo *</label>
                                <input class="form-control" type="text" id="cliente_nombre" name="cliente_nombre" value="<?php echo storefront_escape($formData['cliente_nombre']); ?>" placeholder="Ej: Juan Pérez" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark small" for="cliente_telefono"><i class="bi bi-telephone-fill text-danger me-1"></i> Teléfono de Contacto *</label>
                                <input class="form-control" type="text" id="cliente_telefono" name="cliente_telefono" value="<?php echo storefront_escape($formData['cliente_telefono']); ?>" placeholder="Ej: 55 1234 5678" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold text-dark small" for="cliente_email"><i class="bi bi-envelope-fill me-1"></i> Correo Electrónico (Opcional)</label>
                                <input class="form-control" type="email" id="cliente_email" name="cliente_email" value="<?php echo storefront_escape($formData['cliente_email']); ?>" placeholder="correo@ejemplo.com">
                            </div>

                            <hr class="my-3 text-muted">

                            <!-- Sucursal & Opción de Entrega -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark small" for="id_sucursal"><i class="bi bi-shop text-warning me-1"></i> Sucursal para Reparto / Recolección *</label>
                                <select class="form-select fw-semibold" id="id_sucursal" name="id_sucursal" onchange="selectBranchFromSelect()">
                                    <option value="1" <?php echo $formData['id_sucursal'] === '1' ? 'selected' : ''; ?>>Sucursal 1 (San Cristóbal Centro: 7:30 AM - 3:30 PM)</option>
                                    <option value="2" <?php echo $formData['id_sucursal'] === '2' ? 'selected' : ''; ?>>Sucursal 2 (La Principal: 8:00 AM - 4:00 PM)</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark small" for="tipo_entrega"><i class="bi bi-truck text-warning me-1"></i> Opción de Entrega *</label>
                                <select class="form-select fw-semibold" id="tipo_entrega" name="tipo_entrega" onchange="toggleDeliveryFields()">
                                    <option value="recoger" <?php echo $formData['tipo_entrega'] === 'recoger' ? 'selected' : ''; ?>>Recoger en Sucursal (Sin costo extra)</option>
                                    <option value="domicilio" <?php echo $formData['tipo_entrega'] === 'domicilio' ? 'selected' : ''; ?>>Entrega a Domicilio (Máximo 5 km)</option>
                                </select>
                            </div>

                            <!-- Panel de Dirección, Geolocalización & Mapa Interactivo -->
                            <div id="deliveryFieldsPanel" class="col-12 <?php echo $formData['tipo_entrega'] === 'domicilio' ? '' : 'd-none'; ?>">
                                <div class="p-3 bg-light rounded-4 border border-secondary border-opacity-25 my-2">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                        <div>
                                            <strong class="d-block text-dark small"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Cobertura y Geolocalización (Máximo 5 km)</strong>
                                            <span class="small text-muted">Usa tu GPS o haz clic en el mapa para marcar tu ubicación exacta de entrega.</span>
                                        </div>
                                        <button type="button" class="btn btn-outline-danger btn-sm rounded-pill fw-bold" onclick="getGpsLocation()">
                                            <i class="bi bi-crosshair me-1"></i> Usar mi Ubicación GPS
                                        </button>
                                    </div>
                                    <div id="gpsAlertBox" class="d-none alert p-2.5 small mb-3"></div>

                                    <!-- Mapa Interactivo Leaflet -->
                                    <div class="mb-3">
                                        <div id="storefrontMap"></div>
                                        <div class="row g-2 mt-2">
                                            <div class="col-md-6">
                                                <div class="map-branch-card active d-flex align-items-center justify-content-between" id="cardBranch1" onclick="selectBranchFromMap('1')">
                                                    <div>
                                                        <strong class="d-block small text-dark"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Sucursal 1 (Ecatepec Centro)</strong>
                                                        <span class="small text-muted d-block" style="font-size: 0.75rem;">C. Miguel Hidalgo 19, San Cristóbal Centro</span>
                                                        <span class="badge bg-warning text-dark mt-1" style="font-size: 0.68rem;">Horario: 7:30 AM - 3:30 PM</span>
                                                    </div>
                                                    <a href="https://www.google.com/maps/place/Carniceria+%22Cano%22/@19.6021539,-99.0451077,17z" target="_blank" rel="noopener" class="btn btn-outline-danger btn-sm p-1 px-2 ms-2" title="Abrir en Google Maps" onclick="event.stopPropagation();">
                                                        <i class="bi bi-box-arrow-up-right"></i>
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="map-branch-card d-flex align-items-center justify-content-between" id="cardBranch2" onclick="selectBranchFromMap('2')">
                                                    <div>
                                                        <strong class="d-block small text-dark"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Sucursal 2 (La Principal)</strong>
                                                        <span class="small text-muted d-block" style="font-size: 0.75rem;">Nezahualcóyotl 1-Int 1, Olímpica Jajalpa</span>
                                                        <span class="badge bg-warning text-dark mt-1" style="font-size: 0.68rem;">Horario: 8:00 AM - 4:00 PM</span>
                                                    </div>
                                                    <a href="https://www.google.com/maps/place/Carniceria+LA+PRINCIPAL/@19.588193,-99.0366936,17z" target="_blank" rel="noopener" class="btn btn-outline-danger btn-sm p-1 px-2 ms-2" title="Abrir en Google Maps" onclick="event.stopPropagation();">
                                                        <i class="bi bi-box-arrow-up-right"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label fw-bold text-dark small" for="direccion_entrega"><i class="bi bi-house-door-fill text-secondary me-1"></i> Dirección Completa (Calle y Número) *</label>
                                            <input class="form-control" type="text" id="direccion_entrega" name="direccion_entrega" value="<?php echo storefront_escape($formData['direccion_entrega']); ?>" placeholder="Ej: Av. Juárez #123, Int 4B, Col. Centro">
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-bold text-dark small" for="referencias_ubicacion"><i class="bi bi-signpost-2-fill text-warning me-1"></i> Referencias de la Ubicación para el Repartidor *</label>
                                            <textarea class="form-control" id="referencias_ubicacion" name="referencias_ubicacion" rows="2" placeholder="Ej: Casa blanca de 2 pisos con zaguán negro, entre Calle Olivos y Jacarandas, frente a la farmacia."><?php echo storefront_escape($formData['referencias_ubicacion']); ?></textarea>
                                            <div class="form-text small">Indica color de fachada, entre qué calles se ubica o puntos clave de referencia para facilitar la entrega.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-3 text-muted">

                            <!-- Métodos de Pago: Efectivo y Transferencia -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark small" for="metodo_pago"><i class="bi bi-wallet2 text-success me-1"></i> Método de Pago *</label>
                                <select class="form-select fw-semibold" id="metodo_pago" name="metodo_pago" onchange="togglePaymentFields()">
                                    <option value="efectivo" <?php echo $formData['metodo_pago'] === 'efectivo' ? 'selected' : ''; ?>>💵 Efectivo (Pago contra entrega)</option>
                                    <option value="transferencia" <?php echo $formData['metodo_pago'] === 'transferencia' ? 'selected' : ''; ?>>🏦 Transferencia Bancaria (SPEI)</option>
                                </select>
                            </div>

                            <!-- Campo Billete para Efectivo -->
                            <div class="col-md-6" id="cashFieldBox">
                                <label class="form-label fw-bold text-dark small" for="monto_pago_efectivo"><i class="bi bi-cash-stack text-success me-1"></i> ¿Con cuánto pagarás? (Para llevar cambio)</label>
                                <input class="form-control" type="text" id="monto_pago_efectivo" name="monto_pago_efectivo" value="<?php echo storefront_escape($formData['monto_pago_efectivo']); ?>" placeholder="Ej: Billete de $500">
                            </div>

                            <!-- Información para Transferencia Bancaria SPEI -->
                            <div class="col-12 d-none" id="transferInfoBox">
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
                        <strong class="text-dark"><?php echo storefront_escape(storefront_money($cartTotals['subtotal'])); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-3 my-2 border-bottom border-2">
                        <span class="h5 fw-bold text-dark m-0 font-serif">Total Final</span>
                        <strong class="h4 fw-bold text-danger m-0"><?php echo storefront_escape(storefront_money($cartTotals['total'])); ?></strong>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</section>

<script>
const BRANCH_COORDS = {
    '1': { 
        lat: 19.6021539, 
        lng: -99.0451077, 
        name: 'Sucursal 1 - Ecatepec Centro', 
        address: 'C. Miguel Hidalgo 19, San Cristóbal Centro, 55000 Ecatepec de Morelos, Méx.',
        hours: '7:30 AM - 3:30 PM',
        gmaps: 'https://www.google.com/maps/place/Carniceria+%22Cano%22/@19.6021539,-99.0451077,17z'
    },
    '2': { 
        lat: 19.588193, 
        lng: -99.0366936, 
        name: 'Sucursal 2 - La Principal / Matriz', 
        address: 'Nezahualcóyotl 1-Interior 1, Olímpica Jajalpa, 55090 Ecatepec de Morelos, Méx.',
        hours: '8:00 AM - 4:00 PM',
        gmaps: 'https://www.google.com/maps/place/Carniceria+LA+PRINCIPAL/@19.588193,-99.0366936,17z'
    }
};

var mapEngine = 'leaflet';
var map = null;
var branchMarkers = {};
var userMarker = null;
var radiusCircle = null;
var autocomplete = null;
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
    }
}

function initGoogleMap(mapContainer) {
    map = new google.maps.Map(mapContainer, {
        center: { lat: 19.5950, lng: -99.0410 },
        zoom: 13,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        streetViewControl: false,
        mapTypeControl: true,
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

    initAddressAutocomplete();
    updateMapVisuals();
}

function initLeafletMap(mapContainer) {
    map = L.map('storefrontMap').setView([19.5920, -99.0439], 13);

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

function initAddressAutocomplete() {
    var input = document.getElementById('direccion_entrega');
    if (!input || autocomplete !== null) return;
    if (typeof google === 'undefined' || typeof google.maps === 'undefined' || typeof google.maps.places === 'undefined') return;

    autocomplete = new google.maps.places.Autocomplete(input, {
        componentRestrictions: { country: 'mx' }
    });

    autocomplete.addListener('place_changed', function () {
        var place = autocomplete.getPlace();
        if (place.geometry && place.geometry.location) {
            var lat = place.geometry.location.lat();
            var lng = place.geometry.location.lng();
            setUserLocationOnMap(lat, lng, true);
        }
    });
}

function updateMapVisuals() {
    if (!map) return;

    var branchId = document.getElementById('id_sucursal').value;
    var branch = BRANCH_COORDS[branchId] || BRANCH_COORDS['1'];

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

    document.getElementById('cardBranch1').classList.toggle('active', branchId === '1');
    document.getElementById('cardBranch2').classList.toggle('active', branchId === '2');
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
                userMarker.bindPopup('<strong>Tu Ubicación de Entrega</strong><br><small>Puedes arrastrar el pin</small>').openPopup();
                userMarker.on('dragend', function (e) {
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

    if (mapEngine === 'google' && typeof google !== 'undefined' && typeof google.maps !== 'undefined' && google.maps.Geocoder) {
        if (!geocoder) geocoder = new google.maps.Geocoder();
        geocoder.geocode({ location: { lat: userLat, lng: userLng } }, function (results, status) {
            if (status === 'OK' && results && results[0]) {
                dirInput.value = results[0].formatted_address;
            }
        });
    } else {
        fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + userLat + '&lon=' + userLng)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.display_name) {
                    dirInput.value = data.display_name;
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
        map.panTo({ lat: branch.lat, lng: branch.lng });
        if (branchMarkers[branchId] && branchMarkers[branchId].infoWindow) {
            branchMarkers[branchId].infoWindow.open(map, branchMarkers[branchId].marker);
        }
    }
    updateMapVisuals();
    validateBranchDistance();
}

function toggleDeliveryFields() {
    var tipo = document.getElementById('tipo_entrega').value;
    var panel = document.getElementById('deliveryFieldsPanel');
    var dirInput = document.getElementById('direccion_entrega');
    var refInput = document.getElementById('referencias_ubicacion');

    if (tipo === 'domicilio') {
        panel.classList.remove('d-none');
        if (dirInput) dirInput.required = true;
        if (refInput) refInput.required = true;

        setTimeout(function () {
            initStorefrontMap();
            if (map && window.google && window.google.maps) {
                google.maps.event.trigger(map, 'resize');
            }
        }, 150);
    } else {
        panel.classList.add('d-none');
        if (dirInput) dirInput.required = false;
        if (refInput) refInput.required = false;
    }
    validateBranchDistance();
}

function togglePaymentFields() {
    var metodo = document.getElementById('metodo_pago').value;
    var cashBox = document.getElementById('cashFieldBox');
    var transferBox = document.getElementById('transferInfoBox');

    if (metodo === 'efectivo') {
        if (cashBox) cashBox.classList.remove('d-none');
        if (transferBox) transferBox.classList.add('d-none');
    } else {
        if (cashBox) cashBox.classList.add('d-none');
        if (transferBox) transferBox.classList.remove('d-none');
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
        gpsBox.textContent = 'Obteniendo tu ubicación GPS actual... Por favor acepta los permisos.';
    }

    navigator.geolocation.getCurrentPosition(function (pos) {
        var userLat = pos.coords.latitude;
        var userLng = pos.coords.longitude;
        setUserLocationOnMap(userLat, userLng);
    }, function (err) {
        if (gpsBox) {
            gpsBox.className = 'mt-2 alert alert-danger p-2.5 small d-block';
            gpsBox.textContent = 'No se pudo obtener la ubicación GPS automáticamente (' + err.message + '). Puedes hacer clic directamente en el mapa o ingresar tu dirección manualmente.';
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

    if (isNaN(lat) || isNaN(lng) || !BRANCH_COORDS[branchId]) {
        if (gpsBox && gpsBox.classList.contains('d-none')) {
            gpsBox.className = 'mt-2 alert alert-info p-2.5 small d-block';
            gpsBox.innerHTML = '<i class="bi bi-info-circle-fill me-1"></i> Usa el botón <strong>"Usar mi Ubicación GPS"</strong> o haz clic en el mapa para verificar tu cobertura de 5 km.';
        }
        return;
    }

    var branch = BRANCH_COORDS[branchId];
    var dist = calculateDistance(lat, lng, branch.lat, branch.lng);
    document.getElementById('distancia_km').value = dist;

    if (dist > 5.0) {
        if (gpsBox) {
            gpsBox.className = 'mt-2 alert alert-danger p-2.5 small d-block';
            gpsBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Ubicación a ' + dist + ' km de ' + branch.name + ':</strong> Excede el radio máximo de 5 km para entrega a domicilio. Selecciona <em>Recoger en Sucursal</em> o cambia a la otra Sucursal.';
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

document.addEventListener('DOMContentLoaded', function () {
    toggleDeliveryFields();
    togglePaymentFields();
});
</script>
