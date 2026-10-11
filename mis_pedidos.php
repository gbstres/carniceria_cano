<?php
require_once __DIR__ . '/functions/config.php';
require_once __DIR__ . '/storefront_logic.php';
require_once __DIR__ . '/storefront_layout.php';

$cartTotals = storefront_cart_totals($_SESSION['store_cart'] ?? []);

// Detectar teléfono por GET, POST, Cookie o Sesión
$searchPhone = trim((string)($_REQUEST['telefono'] ?? $_SESSION['otp_phone'] ?? $_COOKIE['cano_last_phone'] ?? ''));
$cleanPhone = preg_replace('/[^0-9]/', '', $searchPhone);

// Rango de filtro (por defecto 'all')
$filterRange = isset($_REQUEST['rango']) ? trim((string)$_REQUEST['rango']) : 'all';
$validRanges = ['all', 'last_30_days', 'current_month'];
if (!in_array($filterRange, $validRanges, true)) {
    $filterRange = 'all';
}

$customerOrders = [];
if (strlen($cleanPhone) >= 10) {
    $customerOrders = storefront_fetch_customer_orders($link, $cleanPhone, $filterRange);
    // Guardar teléfono buscado en cookie para recordar en este dispositivo
    setcookie('cano_last_phone', $cleanPhone, time() + (180 * 86400), '/');
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
                        <p class="text-muted small m-0 mt-1">Consulta tus compras anteriores, el estatus de entrega y el desglose de productos.</p>
                    </div>
                    <div class="col-md-5">
                        <form method="get" action="mis_pedidos.php" class="search-phone-form">
                            <input type="hidden" name="rango" value="<?php echo storefront_escape($filterRange); ?>">
                            <label class="form-label fw-bold text-dark small mb-1">
                                <i class="bi bi-telephone-fill text-danger me-1"></i> Ingresa tu número de teléfono:
                            </label>
                            <div class="input-group">
                                <input type="text" name="telefono" class="form-control fw-bold" placeholder="Ej: 55 1234 5678" value="<?php echo storefront_escape($cleanPhone); ?>" autocomplete="off" inputmode="numeric">
                                <button type="submit" class="btn btn-danger px-3 fw-bold">
                                    <i class="bi bi-search me-1"></i> Buscar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if (strlen($cleanPhone) >= 10): ?>
            <!-- Selector de Rangos de Fecha -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div class="btn-group rounded-pill shadow-sm bg-white p-1" role="group">
                    <a href="mis_pedidos.php?telefono=<?php echo urlencode($cleanPhone); ?>&rango=all" class="btn btn-sm rounded-pill fw-bold <?php echo $filterRange === 'all' ? 'btn-danger' : 'btn-light text-dark'; ?>">
                        <i class="bi bi-list-stars me-1"></i> Todos
                    </a>
                    <a href="mis_pedidos.php?telefono=<?php echo urlencode($cleanPhone); ?>&rango=last_30_days" class="btn btn-sm rounded-pill fw-bold <?php echo $filterRange === 'last_30_days' ? 'btn-danger' : 'btn-light text-dark'; ?>">
                        <i class="bi bi-calendar3 me-1"></i> Últimos 30 días
                    </a>
                    <a href="mis_pedidos.php?telefono=<?php echo urlencode($cleanPhone); ?>&rango=current_month" class="btn btn-sm rounded-pill fw-bold <?php echo $filterRange === 'current_month' ? 'btn-danger' : 'btn-light text-dark'; ?>">
                        <i class="bi bi-calendar-event me-1"></i> Mes Actual
                    </a>
                </div>

                <span class="text-muted small">
                    Mostrando resultados para: <strong class="text-dark"><?php echo storefront_escape($cleanPhone); ?></strong>
                </span>
            </div>

            <!-- Lista de Pedidos -->
            <?php if (empty($customerOrders)): ?>
                <div class="card rounded-4 border-0 shadow-sm bg-white text-center p-5 my-3">
                    <div class="display-4 text-muted mb-3"><i class="bi bi-inbox"></i></div>
                    <h4 class="fw-bold text-dark">No se encontraron pedidos en este periodo</h4>
                    <p class="text-muted small max-w-md mx-auto">
                        <?php if ($filterRange === 'previous_month'): ?>
                            No registraste pedidos durante el <strong>mes previo</strong>. Puedes cambiar el filtro arriba a "Mes Actual" o "Todos" para revisar otras fechas.
                        <?php else: ?>
                            No hay compras registradas para el número <strong><?php echo storefront_escape($cleanPhone); ?></strong> en el rango seleccionado.
                        <?php endif; ?>
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
                                $statusClass = 'bg-warning text-dark';
                                $statusLabel = '🟡 Nuevo Pedido';
                                break;
                            case 'en_proceso':
                                $statusClass = 'bg-primary';
                                $statusLabel = '🔵 En Preparación';
                                break;
                            case 'enviado':
                                $statusClass = 'bg-info text-dark';
                                $statusLabel = '🟣 En Camino';
                                break;
                            case 'completado':
                            case 'entregado':
                                $statusClass = 'bg-success';
                                $statusLabel = '🟢 Entregado / Completado';
                                break;
                            case 'cancelado':
                                $statusClass = 'bg-danger';
                                $statusLabel = '🔴 Cancelado';
                                break;
                        }
                        $sucursalNombre = $order['id_sucursal'] == 1 ? 'Sucursal 1 (San Cristóbal Centro)' : 'Sucursal 2 (La Principal)';
                        ?>
                        <div class="col-12">
                            <div class="card rounded-4 border-0 shadow-sm bg-white overflow-hidden">
                                <div class="card-header bg-light p-3 d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom">
                                    <div>
                                        <strong class="fs-5 text-dark font-serif me-2">Folio #<?php echo storefront_escape($order['id_pedido']); ?></strong>
                                        <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i> <?php echo storefront_escape(date('d/m/Y h:i A', strtotime($order['fecha_ingreso']))); ?></span>
                                    </div>
                                    <div>
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
        <?php else: ?>
            <div class="card rounded-4 border-0 shadow-sm bg-white text-center p-5">
                <div class="display-4 text-danger mb-3"><i class="bi bi-phone-vibrate"></i></div>
                <h4 class="fw-bold text-dark">Consulta tus compras anteriores</h4>
                <p class="text-muted small max-w-md mx-auto mb-4">
                    Ingresa tu número de teléfono de 10 dígitos en la barra superior para consultar los pedidos realizados durante el <strong>mes previo</strong>.
                </p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
storefront_render_footer();
?>
