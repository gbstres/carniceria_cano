<section class="catalog-section" id="catalogo">
    <div class="container">
        <?php if (!empty($feedback['message'])): ?>
            <div class="alert alert-<?php echo storefront_escape($feedback['type']); ?> storefront-alert" role="alert">
                <?php echo storefront_escape($feedback['message']); ?>
            </div>
        <?php endif; ?>

        <div class="catalog-shell">
            <aside class="filter-sidebar">
                <form method="get" action="index.php" class="filter-panel">
                    <label class="filter-label" for="buscar">Buscar producto</label>
                    <input id="buscar" name="buscar" type="text" class="form-control filter-input" placeholder="Nombre, codigo o categoria" value="<?php echo storefront_escape($searchTerm); ?>">
                    <button type="submit" class="btn-store w-100">Buscar</button>
                    <a href="index.php" class="btn-store btn-store-secondary w-100">Limpiar filtros</a>
                </form>

                <div class="filter-panel">
                    <div class="filter-label">Categorias</div>
                    <div class="category-list">
                        <a href="<?php echo storefront_escape(storefront_build_url(['mostrar' => 'todos', 'categoria' => null, 'page' => null])); ?>" class="category-link <?php echo $showAll && $selectedCategory === '' ? 'active' : ''; ?>">
                            Todas
                            <span><?php echo storefront_escape(count($products)); ?></span>
                        </a>
                        <?php foreach ($categoryCounts as $categoryName => $count): ?>
                            <a href="<?php echo storefront_escape(storefront_build_url(['categoria' => $categoryName, 'page' => null])); ?>" class="category-link <?php echo $selectedCategory === $categoryName ? 'active' : ''; ?>">
                                <?php echo storefront_escape($categoryName); ?>
                                <span><?php echo storefront_escape($count); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>

            <div class="catalog-results">
                <div class="results-toolbar">
                    <div>
                        <span class="eyebrow">Resultados</span>
                        <h2>
                            <?php
                            if ($selectedCategory !== '') {
                                echo storefront_escape($selectedCategory);
                            } elseif ($showAll) {
                                echo 'Todos los productos';
                            } else {
                                echo 'Explora por categoria';
                            }
                            ?>
                        </h2>
                        <p>
                            <?php if ($isInitialCatalog): ?>
                                Selecciona una categoria o usa el buscador para ver productos
                            <?php else: ?>
                                <?php echo storefront_escape($totalFilteredProducts); ?> productos encontrados
                            <?php endif; ?>
                            <?php if ($searchTerm !== ''): ?>
                                para "<?php echo storefront_escape($searchTerm); ?>"
                            <?php endif; ?>
                            <?php if (!$isInitialCatalog && $totalFilteredProducts > 0): ?>
                                | Mostrando <?php echo storefront_escape($offset + 1); ?>-<?php echo storefront_escape(min($offset + $productsPerPage, $totalFilteredProducts)); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <a href="carrito.php" class="btn-store btn-store-secondary results-cart-link">Carrito: <?php echo storefront_escape($cartTotals['items']); ?></a>
                </div>

                <?php if ($isInitialCatalog): ?>
                    <div class="empty-state">
                        <h3>Primero elige una categoria</h3>
                        <p>La tienda no muestra todo al entrar. Usa una categoria, busca un producto o entra a "Todas".</p>
                    </div>
                <?php elseif (empty($paginatedProducts)): ?>
                    <div class="empty-state">
                        <h3>No encontramos productos</h3>
                        <p>Prueba con otra categoria o cambia el texto de busqueda.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($paginatedProducts as $product): ?>
                            <div class="col-md-6 col-xl-4">
                                <article class="product-card product-card-store">
                                    <a href="<?php echo storefront_escape(storefront_product_url($product['codigo'])); ?>" class="product-image-link">
                                        <img src="<?php echo storefront_escape($product['image_small']); ?>" alt="<?php echo storefront_escape($product['image_alt']); ?>" class="product-card-image">
                                    </a>
                                    <div class="product-card-top">
                                        <span class="product-category"><?php echo storefront_escape($product['categoria']); ?></span>
                                        <span class="product-stock"><?php echo storefront_escape(storefront_stock_label($product['almacen'])); ?></span>
                                    </div>
                                    <h3><a href="<?php echo storefront_escape(storefront_product_url($product['codigo'])); ?>" class="product-title-link"><?php echo storefront_escape($product['descripcion']); ?></a></h3>
                                    <p class="product-code">Codigo: <?php echo storefront_escape($product['codigo']); ?></p>
                                    <div class="product-meta">
                                        <strong><?php echo storefront_escape(storefront_money($product['precio_venta'])); ?></strong>
                                        <span>Stock aprox: <?php echo storefront_escape(number_format((float) $product['almacen'], 2)); ?></span>
                                    </div>
                                    <div class="product-card-actions">
                                        <a href="<?php echo storefront_escape(storefront_product_url($product['codigo'])); ?>" class="btn-store btn-store-secondary">Ver detalle</a>
                                    </div>
                                    <form method="post" class="product-form">
                                        <input type="hidden" name="action" value="add_to_cart">
                                        <input type="hidden" name="product_code" value="<?php echo storefront_escape($product['codigo']); ?>">
                                        <label for="qty-<?php echo storefront_escape($product['codigo']); ?>">Cantidad</label>
                                        <div class="product-form-row">
                                            <input id="qty-<?php echo storefront_escape($product['codigo']); ?>" type="number" name="quantity" min="0.25" step="0.25" value="1" class="form-control">
                                            <button type="submit" class="btn-store">Agregar</button>
                                        </div>
                                    </form>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="catalog-pagination" aria-label="Paginacion de productos">
                            <?php if ($currentPage > 1): ?>
                                <a class="page-link-store" href="<?php echo storefront_escape(storefront_build_url(['page' => $currentPage - 1])); ?>">Anterior</a>
                            <?php endif; ?>
                            <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                                <a class="page-link-store <?php echo $page === $currentPage ? 'active' : ''; ?>" href="<?php echo storefront_escape(storefront_build_url(['page' => $page])); ?>"><?php echo storefront_escape($page); ?></a>
                            <?php endfor; ?>
                            <?php if ($currentPage < $totalPages): ?>
                                <a class="page-link-store" href="<?php echo storefront_escape(storefront_build_url(['page' => $currentPage + 1])); ?>">Siguiente</a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
