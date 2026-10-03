<section class="checkout-section py-4" id="pedido">
    <div class="container">
        <?php if (!empty($feedback['message'])): ?>
            <div class="alert alert-<?php echo storefront_escape($feedback['type']); ?> storefront-alert alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i>
                <?php echo storefront_escape($feedback['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <?php if ($orderSuccess !== null): ?>
            <div class="success-panel p-4 mb-4 bg-success bg-opacity-10 border border-success rounded-4 text-center">
                <div class="display-4 text-success mb-2"><i class="bi bi-check-circle-fill"></i></div>
                <span class="eyebrow-badge text-success mb-1">¡Pedido Registrado con Éxito!</span>
                <h2 class="h3 fw-bold text-dark">Gracias por tu compra, <?php echo storefront_escape($orderSuccess['name']); ?></h2>
                <p class="mb-0 text-muted">Tu pedido quedó guardado con el folio <strong class="text-dark">#<?php echo storefront_escape($orderSuccess['id']); ?></strong> por un total de <strong class="text-danger"><?php echo storefront_escape(storefront_money($orderSuccess['total'])); ?></strong>.</p>
                <a href="index.php" class="btn btn-success mt-3 px-4 rounded-3 fw-bold">Volver al inicio</a>
            </div>
        <?php endif; ?>

        <!-- Banner de Encabezado Dinámico con Ajuste de Contenedor -->
        <div class="page-header-banner">
            <span class="eyebrow-badge"><i class="bi bi-clipboard-check-fill me-1"></i> Datos del Cliente</span>
            <h2 class="results-heading">Confirma tu Pedido y Entrega</h2>
            <p class="mb-0">Captura tus datos de contacto para preparar tus cortes frescos.</p>
        </div>

        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="checkout-panel p-4 bg-white rounded-4 shadow-sm border">
                    <form method="post" class="checkout-form">
                        <input type="hidden" name="action" value="checkout">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark small" for="cliente_nombre"><i class="bi bi-person-fill me-1"></i> Nombre Completo</label>
                                <input class="form-control" type="text" id="cliente_nombre" name="cliente_nombre" value="<?php echo storefront_escape($formData['cliente_nombre']); ?>" placeholder="Ej: Juan Pérez" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark small" for="cliente_telefono"><i class="bi bi-telephone-fill me-1"></i> Teléfono de Contacto</label>
                                <input class="form-control" type="text" id="cliente_telefono" name="cliente_telefono" value="<?php echo storefront_escape($formData['cliente_telefono']); ?>" placeholder="Ej: 55 1234 5678" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark small" for="cliente_email"><i class="bi bi-envelope-fill me-1"></i> Correo Electrónico</label>
                                <input class="form-control" type="email" id="cliente_email" name="cliente_email" value="<?php echo storefront_escape($formData['cliente_email']); ?>" placeholder="correo@ejemplo.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark small" for="metodo_pago"><i class="bi bi-credit-card-fill me-1"></i> Método de Pago</label>
                                <select class="form-select" id="metodo_pago" name="metodo_pago">
                                    <option value="efectivo" <?php echo $formData['metodo_pago'] === 'efectivo' ? 'selected' : ''; ?>>Efectivo</option>
                                    <option value="transferencia" <?php echo $formData['metodo_pago'] === 'transferencia' ? 'selected' : ''; ?>>Transferencia Bancaria</option>
                                    <option value="tarjeta" <?php echo $formData['metodo_pago'] === 'tarjeta' ? 'selected' : ''; ?>>Tarjeta Débito/Crédito</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark small" for="tipo_entrega"><i class="bi bi-truck me-1"></i> Opción de Entrega</label>
                                <select class="form-select" id="tipo_entrega" name="tipo_entrega">
                                    <option value="recoger" <?php echo $formData['tipo_entrega'] === 'recoger' ? 'selected' : ''; ?>>Recoger en Sucursal</option>
                                    <option value="domicilio" <?php echo $formData['tipo_entrega'] === 'domicilio' ? 'selected' : ''; ?>>Entrega a Domicilio</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark small" for="direccion_entrega"><i class="bi bi-geo-alt-fill me-1"></i> Dirección de Entrega (si aplica)</label>
                                <input class="form-control" type="text" id="direccion_entrega" name="direccion_entrega" value="<?php echo storefront_escape($formData['direccion_entrega']); ?>" placeholder="Calle, número, colonia...">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold text-dark small" for="notas"><i class="bi bi-pencil-square me-1"></i> Indicaciones o Notas Especiales para el Carnicero</label>
                                <textarea class="form-control" id="notas" name="notas" rows="3" placeholder="Ej: Favor de entregar el bistec aplanado y sin grasa."></textarea>
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-2 align-items-center mt-4">
                                <button type="submit" class="btn btn-danger px-4 py-2.5 rounded-3 fw-bold shadow-sm">
                                    <i class="bi bi-check-circle-fill me-1"></i> Finalizar y Enviar Pedido
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
                    <h3 class="h4 fw-bold mb-3">Total a Pagar</h3>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Total de Artículos</span>
                        <strong class="text-dark"><?php echo storefront_escape($cartTotals['items']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Subtotal</span>
                        <strong class="text-dark"><?php echo storefront_escape(storefront_money($cartTotals['subtotal'])); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-3 my-2 border-bottom border-2">
                        <span class="h5 fw-bold text-dark m-0">Total Final</span>
                        <strong class="h4 fw-bold text-danger m-0"><?php echo storefront_escape(storefront_money($cartTotals['total'])); ?></strong>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</section>
