<section class="checkout-section" id="pedido">
    <div class="container">
        <?php if (!empty($feedback['message'])): ?>
            <div class="alert alert-<?php echo storefront_escape($feedback['type']); ?> storefront-alert" role="alert">
                <?php echo storefront_escape($feedback['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($orderSuccess !== null): ?>
            <div class="success-panel">
                <span class="eyebrow">Pedido registrado</span>
                <h2>Gracias, <?php echo storefront_escape($orderSuccess['name']); ?></h2>
                <p>Tu pedido web quedo guardado con el folio <strong>#<?php echo storefront_escape($orderSuccess['id']); ?></strong> por un total de <strong><?php echo storefront_escape(storefront_money($orderSuccess['total'])); ?></strong>.</p>
            </div>
        <?php endif; ?>

        <div class="section-heading">
            <span class="eyebrow">Pedido</span>
            <h2>Captura datos del cliente y confirma la compra</h2>
            <p>El carrito se revisa por separado. Aqui solo se capturan los datos del cliente y se registra el pedido.</p>
        </div>

        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="checkout-panel">
                    <form method="post" class="checkout-form">
                        <input type="hidden" name="action" value="checkout">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label" for="cliente_nombre">Nombre completo</label>
                                <input class="form-control" type="text" id="cliente_nombre" name="cliente_nombre" value="<?php echo storefront_escape($formData['cliente_nombre']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="cliente_telefono">Telefono</label>
                                <input class="form-control" type="text" id="cliente_telefono" name="cliente_telefono" value="<?php echo storefront_escape($formData['cliente_telefono']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="cliente_email">Correo</label>
                                <input class="form-control" type="email" id="cliente_email" name="cliente_email" value="<?php echo storefront_escape($formData['cliente_email']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="metodo_pago">Metodo de pago</label>
                                <select class="form-select" id="metodo_pago" name="metodo_pago">
                                    <option value="efectivo" <?php echo $formData['metodo_pago'] === 'efectivo' ? 'selected' : ''; ?>>Efectivo</option>
                                    <option value="transferencia" <?php echo $formData['metodo_pago'] === 'transferencia' ? 'selected' : ''; ?>>Transferencia</option>
                                    <option value="tarjeta" <?php echo $formData['metodo_pago'] === 'tarjeta' ? 'selected' : ''; ?>>Tarjeta</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="tipo_entrega">Tipo de entrega</label>
                                <select class="form-select" id="tipo_entrega" name="tipo_entrega">
                                    <option value="recoger" <?php echo $formData['tipo_entrega'] === 'recoger' ? 'selected' : ''; ?>>Recoger en tienda</option>
                                    <option value="domicilio" <?php echo $formData['tipo_entrega'] === 'domicilio' ? 'selected' : ''; ?>>Entrega a domicilio</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="direccion_entrega">Direccion de entrega</label>
                                <input class="form-control" type="text" id="direccion_entrega" name="direccion_entrega" value="<?php echo storefront_escape($formData['direccion_entrega']); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="notas">Notas del pedido</label>
                                <textarea class="form-control" id="notas" name="notas" rows="4"><?php echo storefront_escape($formData['notas']); ?></textarea>
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-3 align-items-center">
                                <button type="submit" class="btn-store">Registrar pedido</button>
                                <a href="carrito.php" class="btn-store btn-store-secondary">Volver al carrito</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-4">
                <aside class="summary-card">
                    <span class="eyebrow">Resumen</span>
                    <h3>Total del pedido</h3>
                    <div class="summary-line"><span>Items</span><strong><?php echo storefront_escape($cartTotals['items']); ?></strong></div>
                    <div class="summary-line"><span>Subtotal</span><strong><?php echo storefront_escape(storefront_money($cartTotals['subtotal'])); ?></strong></div>
                    <div class="summary-line summary-total"><span>Total</span><strong><?php echo storefront_escape(storefront_money($cartTotals['total'])); ?></strong></div>
                </aside>
            </div>
        </div>
    </div>
</section>
