<section class="cart-section" id="carrito">
    <div class="container">
        <?php if (!empty($feedback['message'])): ?>
            <div class="alert alert-<?php echo storefront_escape($feedback['type']); ?> storefront-alert" role="alert">
                <?php echo storefront_escape($feedback['message']); ?>
            </div>
        <?php endif; ?>
        <div class="section-heading">
            <span class="eyebrow">Carrito</span>
            <h2>Revisa lo que el cliente va a pedir</h2>
        </div>
        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="cart-panel">
                    <?php if (empty($cart)): ?>
                        <div class="empty-state">
                            <h3>Tu carrito esta vacio</h3>
                            <p>Agrega productos desde la tienda para empezar el pedido.</p>
                            <a href="index.php" class="btn-store">Ir a la tienda</a>
                        </div>
                    <?php else: ?>
                        <form method="post">
                            <input type="hidden" name="action" value="update_cart">
                            <div class="table-responsive">
                                <table class="table cart-table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Precio</th>
                                            <th>Cantidad</th>
                                            <th>Subtotal</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cart as $item): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo storefront_escape($item['descripcion']); ?></strong>
                                                    <span class="table-note"><?php echo storefront_escape($item['categoria']); ?></span>
                                                </td>
                                                <td><?php echo storefront_escape(storefront_money($item['price'])); ?></td>
                                                <td><input type="number" min="0.25" step="0.25" name="quantities[<?php echo storefront_escape($item['codigo']); ?>]" value="<?php echo storefront_escape(number_format((float) $item['quantity'], 2, '.', '')); ?>" class="form-control cart-qty"></td>
                                                <td><?php echo storefront_escape(storefront_money($item['subtotal'])); ?></td>
                                                <td class="text-end"><a href="carrito.php?remove=<?php echo storefront_escape(urlencode($item['codigo'])); ?>" class="btn-store btn-store-danger btn-store-sm">Quitar</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex gap-3 flex-wrap">
                                <button type="submit" class="btn-store">Actualizar carrito</button>
                                <a href="index.php" class="btn-store btn-store-secondary">Seguir comprando</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4">
                <aside class="summary-card">
                    <span class="eyebrow">Resumen</span>
                    <h3>Total del pedido</h3>
                    <div class="summary-line"><span>Items</span><strong><?php echo storefront_escape($cartTotals['items']); ?></strong></div>
                    <div class="summary-line"><span>Subtotal</span><strong><?php echo storefront_escape(storefront_money($cartTotals['subtotal'])); ?></strong></div>
                    <div class="summary-line summary-total"><span>Total</span><strong><?php echo storefront_escape(storefront_money($cartTotals['total'])); ?></strong></div>
                    <a href="pedido.php" class="btn-store w-100">Continuar al pedido</a>
                </aside>
            </div>
        </div>
    </div>
</section>
