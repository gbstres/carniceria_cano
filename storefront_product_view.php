<section class="catalog-section" id="producto-detalle">
    <div class="container">
        <?php if (!empty($feedback['message'])): ?>
            <div class="alert alert-<?php echo storefront_escape($feedback['type']); ?> storefront-alert" role="alert">
                <?php echo storefront_escape($feedback['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($product === null): ?>
            <div class="empty-state">
                <h2>Producto no encontrado</h2>
                <p>El producto solicitado no esta disponible o ya no existe.</p>
                <a href="index.php" class="btn-store">Volver a la tienda</a>
            </div>
        <?php else: ?>
            <div class="product-detail-shell">
                <div class="product-detail-gallery">
                    <div class="product-detail-main-image">
                        <img src="<?php echo storefront_escape($product['image_large']); ?>" alt="<?php echo storefront_escape($product['image_alt']); ?>" class="product-detail-image">
                    </div>
                    <?php if (count($productGallery) > 1): ?>
                        <div class="product-detail-thumbs">
                            <?php foreach ($productGallery as $image): ?>
                                <a href="<?php echo storefront_escape($image['large']); ?>" class="product-detail-thumb" target="_blank" rel="noopener">
                                    <img src="<?php echo storefront_escape($image['small']); ?>" alt="<?php echo storefront_escape($image['alt']); ?>">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="product-detail-card">
                    <span class="eyebrow"><?php echo storefront_escape($product['categoria']); ?></span>
                    <h2><?php echo storefront_escape($product['descripcion']); ?></h2>
                    <p class="product-detail-code">Codigo: <?php echo storefront_escape($product['codigo']); ?></p>

                    <div class="product-detail-price-row">
                        <strong><?php echo storefront_escape(storefront_money($product['precio_venta'])); ?></strong>
                        <span><?php echo storefront_escape(storefront_stock_label($product['almacen'])); ?></span>
                    </div>

                    <div class="product-detail-meta">
                        <div>
                            <span class="product-detail-meta-label">Stock aproximado</span>
                            <p><?php echo storefront_escape(number_format((float) $product['almacen'], 2)); ?></p>
                        </div>
                        <div>
                            <span class="product-detail-meta-label">Categoria</span>
                            <p><?php echo storefront_escape($product['categoria']); ?></p>
                        </div>
                    </div>

                    <form method="post" class="product-form product-detail-form">
                        <input type="hidden" name="action" value="add_to_cart">
                        <input type="hidden" name="product_code" value="<?php echo storefront_escape($product['codigo']); ?>">
                        <label for="qty-product-detail">Cantidad</label>
                        <div class="product-form-row">
                            <input id="qty-product-detail" type="number" name="quantity" min="0.25" step="0.25" value="1" class="form-control">
                            <button type="submit" class="btn-store">Agregar</button>
                        </div>
                    </form>

                    <div class="product-detail-actions">
                        <a href="index.php#catalogo" class="btn-store btn-store-secondary">Volver a la tienda</a>
                        <a href="pedido.php" class="btn-store">Cerrar pedido</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
