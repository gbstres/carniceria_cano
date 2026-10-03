<section class="catalog-section py-4" id="producto-detalle">
    <div class="container">
        <?php if (!empty($feedback['message'])): ?>
            <div class="alert alert-<?php echo storefront_escape($feedback['type']); ?> storefront-alert alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i>
                <?php echo storefront_escape($feedback['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <?php if ($product === null): ?>
            <div class="empty-state-card text-center py-5 bg-white rounded-4 shadow-sm p-4">
                <div class="empty-icon mb-3"><i class="bi bi-exclamation-triangle-fill text-warning display-4"></i></div>
                <h2 class="fw-bold">Producto no encontrado</h2>
                <p class="text-muted">El producto solicitado no está disponible o ya no existe en el catálogo.</p>
                <a href="index.php" class="btn btn-primary-store px-4 py-2 rounded-3 mt-2">
                    <i class="bi bi-arrow-left me-1"></i> Volver a la Tienda
                </a>
            </div>
        <?php else: ?>
            <div class="product-detail-shell">
                <div class="product-detail-gallery">
                    <div class="product-detail-main-image rounded-4 overflow-hidden shadow-sm border">
                        <img src="<?php echo storefront_escape($product['image_large']); ?>" alt="<?php echo storefront_escape($product['image_alt']); ?>" class="product-detail-image w-100">
                    </div>
                    <?php if (count($productGallery) > 1): ?>
                        <div class="product-detail-thumbs d-flex gap-2 mt-3">
                            <?php foreach ($productGallery as $image): ?>
                                <a href="<?php echo storefront_escape($image['large']); ?>" class="product-detail-thumb rounded-3 overflow-hidden border" target="_blank" rel="noopener">
                                    <img src="<?php echo storefront_escape($image['small']); ?>" alt="<?php echo storefront_escape($image['alt']); ?>" class="w-100 h-100 object-fit-cover">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="product-detail-card p-4 bg-white rounded-4 shadow-sm border">
                    <span class="eyebrow-badge mb-2"><i class="bi bi-tag-fill me-1"></i> <?php echo storefront_escape($product['categoria']); ?></span>
                    <h2 class="fw-bold text-dark mb-2"><?php echo storefront_escape($product['descripcion']); ?></h2>
                    <p class="product-detail-code text-muted mb-3"><i class="bi bi-barcode me-1"></i> Código: <?php echo storefront_escape($product['codigo']); ?></p>

                    <div class="product-detail-price-row d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded-3">
                        <div>
                            <span class="text-muted small d-block">Precio por unidad/kg:</span>
                            <strong class="h2 fw-bold text-danger mb-0"><?php echo storefront_escape(storefront_money($product['precio_venta'])); ?></strong>
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success fs-6 px-3 py-2 rounded-pill">
                            <i class="bi bi-check-circle-fill me-1"></i> <?php echo storefront_escape(storefront_stock_label($product['almacen'])); ?>
                        </span>
                    </div>

                    <div class="product-detail-meta row g-3 mb-4">
                        <div class="col-6">
                            <div class="p-3 border rounded-3 bg-white">
                                <span class="product-detail-meta-label text-muted small text-uppercase fw-semibold d-block mb-1">Disponibilidad Aproximada</span>
                                <p class="fw-bold text-dark m-0"><?php echo storefront_escape(number_format((float) $product['almacen'], 2)); ?> kg/pza</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 border rounded-3 bg-white">
                                <span class="product-detail-meta-label text-muted small text-uppercase fw-semibold d-block mb-1">Categoría</span>
                                <p class="fw-bold text-dark m-0"><?php echo storefront_escape($product['categoria']); ?></p>
                            </div>
                        </div>
                    </div>

                    <form method="post" class="product-form product-detail-form mb-4">
                        <input type="hidden" name="action" value="add_to_cart">
                        <input type="hidden" name="product_code" value="<?php echo storefront_escape($product['codigo']); ?>">
                        
                        <label class="form-label fw-bold small text-dark mb-1" for="qty-product-detail">Cantidad deseada:</label>
                        <div class="quantity-input-group mb-3">
                            <button type="button" class="btn-qty-step" onclick="stepQtyDetail(-0.25)">-</button>
                            <input id="qty-product-detail" type="number" name="quantity" min="0.25" step="0.25" value="1" class="form-control qty-field text-center fw-bold">
                            <button type="button" class="btn-qty-step" onclick="stepQtyDetail(0.25)">+</button>
                        </div>
                        <button type="submit" class="btn btn-danger w-100 py-3 rounded-3 fw-bold shadow-sm fs-6">
                            <i class="bi bi-cart-plus-fill me-1"></i> Agregar al Carrito
                        </button>
                    </form>

                    <div class="product-detail-actions d-flex gap-2">
                        <a href="index.php#catalogo" class="btn btn-outline-secondary rounded-3 w-50 py-2">
                            <i class="bi bi-arrow-left me-1"></i> Volver a la Tienda
                        </a>
                        <a href="pedido.php" class="btn btn-outline-danger rounded-3 w-50 py-2">
                            <i class="bi bi-bag-check me-1"></i> Ir al Pedido
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
function stepQtyDetail(step) {
    var el = document.getElementById('qty-product-detail');
    if (!el) return;
    var val = parseFloat(el.value) || 1;
    val = Math.max(0.25, val + step);
    el.value = val.toFixed(2).replace(/\.00$/, '');
}
</script>
