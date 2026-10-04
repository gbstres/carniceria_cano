<section class="cart-section py-4" id="carrito">
    <div class="container">
        <?php if (!empty($feedback['message'])): ?>
            <div class="alert alert-<?php echo storefront_escape($feedback['type']); ?> storefront-alert alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i>
                <?php echo storefront_escape($feedback['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <!-- Banner de Encabezado Dinámico con Ajuste de Contenedor -->
        <div class="page-header-banner">
            <span class="eyebrow-badge"><i class="bi bi-cart-check-fill me-1"></i> Carrito de Compras</span>
            <h2 class="results-heading">Revisa tu Selección de Productos</h2>
        </div>

        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="cart-panel p-4 bg-white rounded-4 shadow-sm border">
                    <?php if (empty($cart)): ?>
                        <div class="empty-state text-center py-5">
                            <div class="empty-icon mb-3"><i class="bi bi-cart-x text-muted display-4"></i></div>
                            <h3 class="fw-bold text-dark">Tu carrito está vacío</h3>
                            <p class="text-muted">Explora el catálogo de Carnicería Cano y agrega productos frescos.</p>
                            <a href="index.php" class="btn-hero btn-hero-primary mt-2">
                                <i class="bi bi-grid-fill me-1"></i> Ir al Catálogo
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="post">
                            <input type="hidden" name="action" value="update_cart">
                            <div class="table-responsive mb-4">
                                <table class="table cart-table align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Producto</th>
                                            <th>Precio</th>
                                            <th style="width: 140px;">Cantidad</th>
                                            <th>Subtotal</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cart as $item): ?>
                                            <tr>
                                                <td>
                                                    <strong class="d-block text-dark"><?php echo storefront_escape($item['descripcion']); ?></strong>
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary"><?php echo storefront_escape($item['categoria']); ?></span>
                                                    <?php if (!empty($item['comentario'])): ?>
                                                        <div class="small text-muted mt-1 bg-light p-1 rounded border-start border-warning border-3">
                                                            <i class="bi bi-chat-left-text-fill text-warning me-1"></i> Nota: <em><?php echo storefront_escape($item['comentario']); ?></em>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="fw-bold text-dark"><?php echo storefront_escape(storefront_money($item['price'])); ?></td>
                                                <td>
                                                    <input type="number" min="0.25" step="0.25" name="quantities[<?php echo storefront_escape($item['codigo']); ?>]" value="<?php echo storefront_escape(number_format((float) $item['quantity'], 2, '.', '')); ?>" class="form-control text-center fw-bold">
                                                </td>
                                                <td class="fw-bold text-danger"><?php echo storefront_escape(storefront_money($item['subtotal'])); ?></td>
                                                <td class="text-end">
                                                    <a href="carrito.php?remove=<?php echo storefront_escape(urlencode($item['codigo'])); ?>" class="btn btn-outline-danger btn-sm rounded-3">
                                                        <i class="bi bi-trash-fill me-1"></i> Quitar
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <a href="index.php" class="btn btn-outline-secondary rounded-3">
                                    <i class="bi bi-arrow-left me-1"></i> Seguir Comprando
                                </a>
                                <button type="submit" class="btn btn-primary-store rounded-3 px-4 py-2 fw-bold">
                                    <i class="bi bi-arrow-repeat me-1"></i> Actualizar Cantidades
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-4">
                <aside class="summary-card p-4 bg-white rounded-4 shadow-sm border">
                    <span class="eyebrow-badge mb-2"><i class="bi bi-receipt me-1"></i> Resumen</span>
                    <h3 class="h4 fw-bold mb-3">Total del Pedido</h3>
                    
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Ítems totales</span>
                        <strong class="text-dark"><?php echo storefront_escape($cartTotals['items']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Subtotal</span>
                        <strong class="text-dark"><?php echo storefront_escape(storefront_money($cartTotals['subtotal'])); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-3 my-2 border-bottom border-2">
                        <span class="h5 fw-bold text-dark m-0">Total</span>
                        <strong class="h4 fw-bold text-danger m-0"><?php echo storefront_escape(storefront_money($cartTotals['total'])); ?></strong>
                    </div>

                    <?php if (!empty($cart)): ?>
                        <a href="pedido.php" class="btn btn-danger w-100 py-3 mt-2 rounded-3 fw-bold fs-6 text-uppercase shadow-sm">
                            <i class="bi bi-bag-check-fill me-1"></i> Proceder a Confirmar Pedido
                        </a>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </div>
</section>
