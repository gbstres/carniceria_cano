<section class="catalog-section py-3 py-lg-4" id="catalogo">
    <div class="container" id="catalog-container">
        <!-- Toast de Notificación AJAX de Carrito -->
        <div id="ajax-toast" class="toast-notification alert alert-success d-none position-fixed bottom-0 end-0 m-3 shadow-lg z-3 align-items-center" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <span id="toast-message">Producto agregado al carrito.</span>
        </div>

        <?php if (!empty($feedback['message'])): ?>
            <div class="alert alert-<?php echo storefront_escape($feedback['type']); ?> storefront-alert alert-dismissible fade show shadow-sm mb-3" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i>
                <?php echo storefront_escape($feedback['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <!-- Carrusel de Categorías con Flechas Navegables y Scroll Táctil -->
        <div class="category-carousel-shell mb-4 position-relative">
            <button type="button" class="category-carousel-arrow arrow-left" onclick="scrollCategoryCarousel(-250)" aria-label="Anterior categoría">
                <i class="bi bi-chevron-left"></i>
            </button>

            <div class="category-horizontal-scroll-container" id="categoryCarouselTrack">
                <div class="category-horizontal-wrapper d-flex gap-2">
                    <a href="<?php echo storefront_escape(storefront_build_url(['mostrar' => 'todos', 'categoria' => null, 'page' => null])); ?>" class="category-chip-btn ajax-nav-link <?php echo $showAll && $selectedCategory === '' ? 'active' : ''; ?>">
                        <i class="bi bi-grid-fill me-1"></i> Todos los Productos (<?php echo storefront_escape(count($products)); ?>)
                    </a>
                    <?php foreach ($categoryCounts as $categoryName => $count): ?>
                        <a href="<?php echo storefront_escape(storefront_build_url(['categoria' => $categoryName, 'page' => null])); ?>" class="category-chip-btn ajax-nav-link <?php echo $selectedCategory === $categoryName ? 'active' : ''; ?>">
                            <i class="bi bi-tag-fill me-1"></i> <?php echo storefront_escape($categoryName); ?> (<?php echo storefront_escape($count); ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="button" class="category-carousel-arrow arrow-right" onclick="scrollCategoryCarousel(250)" aria-label="Siguiente categoría">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>

        <div class="catalog-shell" id="catalog-main-content">
            <!-- Sidebar de Filtros en Escritorio -->
            <aside class="filter-sidebar d-none d-lg-block">
                <div class="filter-panel search-box-panel shadow-sm border-0 mb-3">
                    <form method="get" action="index.php" class="filter-form ajax-search-form">
                        <label class="filter-label" for="buscar">
                            <i class="bi bi-search me-1 text-warning"></i> Buscar en la tienda
                        </label>
                        <div class="input-group mb-2">
                            <input id="buscar" name="buscar" type="text" class="form-control filter-input" placeholder="Bistec, Chuleta, Arrachera..." value="<?php echo storefront_escape($searchTerm); ?>">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <?php if ($searchTerm !== '' || $selectedCategory !== '' || $showAll): ?>
                            <a href="index.php" class="btn-clear-filters text-decoration-none small text-danger d-inline-block mt-1 ajax-nav-link">
                                <i class="bi bi-x-circle-fill me-1"></i> Limpiar filtros
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="filter-panel category-panel shadow-sm border-0">
                    <div class="filter-label border-bottom pb-2 mb-3">
                        <i class="bi bi-tags-fill me-1 text-warning"></i> Categorías
                    </div>
                    <div class="category-list">
                        <a href="<?php echo storefront_escape(storefront_build_url(['mostrar' => 'todos', 'categoria' => null, 'page' => null])); ?>" class="category-link ajax-nav-link <?php echo $showAll && $selectedCategory === '' ? 'active' : ''; ?>">
                            <span><i class="bi bi-grid-3x3-gap-fill me-2 opacity-75"></i> Todas</span>
                            <span class="badge-count"><?php echo storefront_escape(count($products)); ?></span>
                        </a>
                        <?php foreach ($categoryCounts as $categoryName => $count): ?>
                            <a href="<?php echo storefront_escape(storefront_build_url(['categoria' => $categoryName, 'page' => null])); ?>" class="category-link ajax-nav-link <?php echo $selectedCategory === $categoryName ? 'active' : ''; ?>">
                                <span><i class="bi bi-bookmark-fill me-2 opacity-75"></i> <?php echo storefront_escape($categoryName); ?></span>
                                <span class="badge-count"><?php echo storefront_escape($count); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>

            <!-- Resultados de Productos -->
            <div class="catalog-results">
                <div class="results-header-mobile d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h4 font-serif fw-bold text-dark m-0">
                            <?php
                            if ($selectedCategory !== '') {
                                echo storefront_escape($selectedCategory);
                            } elseif ($showAll) {
                                echo 'Catálogo de Productos';
                            } else {
                                echo 'Explora por Categoría';
                            }
                            ?>
                        </h2>
                        <small class="text-muted">
                            <?php echo storefront_escape($totalFilteredProducts); ?> producto(s) listo(s) para ti
                        </small>
                    </div>

                    <a href="carrito.php" class="btn btn-dark btn-sm rounded-pill fw-bold px-3">
                        <i class="bi bi-cart-fill text-warning me-1"></i> Carrito (<span class="cart-items-counter"><?php echo storefront_escape($cartTotals['items']); ?></span>)
                    </a>
                </div>

                <?php if ($isInitialCatalog): ?>
                    <!-- Vista Inicial con Categorías Reales -->
                    <div class="mobile-categories-quick-grid my-3">
                        <div class="row g-3">
                            <?php foreach ($categoryCounts as $categoryName => $count): ?>
                                <div class="col-6 col-md-4">
                                    <a href="<?php echo storefront_escape(storefront_build_url(['categoria' => $categoryName, 'page' => null])); ?>" class="quick-category-card ajax-nav-link text-decoration-none">
                                        <div class="quick-cat-icon text-danger"><i class="bi bi-shop"></i></div>
                                        <div class="quick-cat-title"><?php echo storefront_escape($categoryName); ?></div>
                                        <div class="quick-cat-sub"><?php echo storefront_escape($count); ?> producto(s)</div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                            <div class="col-12">
                                <a href="<?php echo storefront_escape(storefront_build_url(['mostrar' => 'todos', 'categoria' => null, 'page' => null])); ?>" class="quick-category-card quick-category-card-all ajax-nav-link text-decoration-none text-center">
                                    <div class="fw-bold text-dark"><i class="bi bi-arrow-right-circle-fill text-warning me-1"></i> Ver Todo el Catálogo (<?php echo storefront_escape(count($products)); ?> productos)</div>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php elseif (empty($paginatedProducts)): ?>
                    <div class="empty-state-card text-center py-5 bg-white rounded-4 shadow-sm">
                        <div class="empty-icon mb-3"><i class="bi bi-search text-muted display-4"></i></div>
                        <h3 class="fw-bold">No encontramos este producto</h3>
                        <p class="text-muted">Prueba buscando otro nombre o selecciona otra categoría.</p>
                        <a href="index.php?mostrar=todos" class="btn btn-warning rounded-pill fw-bold px-4 mt-2 ajax-nav-link">Ver todo el catálogo</a>
                    </div>
                <?php else: ?>
                    <div class="row g-3 g-md-4">
                        <?php foreach ($paginatedProducts as $product): ?>
                            <?php
                                $stockStatus = storefront_stock_label($product['almacen']);
                                $isAvailable = ((float)$product['almacen']) > 0;
                            ?>
                            <div class="col-6 col-md-6 col-xl-4">
                                <article class="product-card-mobile shadow-sm">
                                    <div class="product-card-header position-relative">
                                        <a href="<?php echo storefront_escape(storefront_product_url($product['codigo'])); ?>" class="product-image-wrap">
                                            <img src="<?php echo storefront_escape($product['image_small']); ?>" alt="<?php echo storefront_escape($product['image_alt']); ?>" class="product-image" loading="lazy">
                                        </a>
                                    </div>

                                    <div class="product-card-body p-2 p-md-3">
                                        <div class="product-cat-name"><?php echo storefront_escape($product['categoria']); ?></div>
                                        <h3 class="product-title-mobile">
                                            <a href="<?php echo storefront_escape(storefront_product_url($product['codigo'])); ?>">
                                                <?php echo storefront_escape($product['descripcion']); ?>
                                            </a>
                                        </h3>
                                        
                                        <div class="product-price-row-mobile">
                                            <strong class="price-text"><?php echo storefront_escape(storefront_money($product['precio_venta'])); ?></strong>
                                            <span class="stock-badge <?php echo $isAvailable ? 'text-success' : 'text-warning'; ?>">
                                                <?php echo storefront_escape($stockStatus); ?>
                                            </span>
                                        </div>

                                        <form method="post" class="product-add-form-mobile ajax-add-cart-form mt-2">
                                            <input type="hidden" name="action" value="add_to_cart">
                                            <input type="hidden" name="product_code" value="<?php echo storefront_escape($product['codigo']); ?>">
                                            
                                            <div class="quantity-touch-stepper mb-2">
                                                <button type="button" class="btn-step-touch" onclick="stepQty('qty-<?php echo storefront_escape($product['codigo']); ?>', -0.25)">-</button>
                                                <input id="qty-<?php echo storefront_escape($product['codigo']); ?>" type="number" name="quantity" min="0.25" step="0.25" value="1" class="form-control qty-touch-input text-center fw-bold">
                                                <button type="button" class="btn-step-touch" onclick="stepQty('qty-<?php echo storefront_escape($product['codigo']); ?>', 0.25)">+</button>
                                            </div>

                                            <button type="submit" class="btn-add-touch w-100">
                                                <i class="bi bi-cart-plus-fill me-1"></i> Agregar
                                            </button>
                                        </form>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="catalog-pagination mt-4 justify-content-center" aria-label="Paginación">
                            <?php if ($currentPage > 1): ?>
                                <a class="page-link-store ajax-nav-link" href="<?php echo storefront_escape(storefront_build_url(['page' => $currentPage - 1])); ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                                <a class="page-link-store ajax-nav-link <?php echo $page === $currentPage ? 'active' : ''; ?>" href="<?php echo storefront_escape(storefront_build_url(['page' => $page])); ?>">
                                    <?php echo storefront_escape($page); ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <a class="page-link-store ajax-nav-link" href="<?php echo storefront_escape(storefront_build_url(['page' => $currentPage + 1])); ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
function stepQty(inputId, step) {
    var el = document.getElementById(inputId);
    if (!el) return;
    var val = parseFloat(el.value) || 1;
    val = Math.max(0.25, val + step);
    el.value = val.toFixed(2).replace(/\.00$/, '');
}

function scrollCategoryCarousel(distance) {
    var track = document.getElementById('categoryCarouselTrack');
    if (track) {
        track.scrollBy({ left: distance, behavior: 'smooth' });
    }
}

// Arquitectura SPA-Lite / AJAX Fetch Engine
document.addEventListener('DOMContentLoaded', function () {
    // Interceptor de Navegación AJAX (Categorías, Paginación, Limpiar Filtros)
    document.addEventListener('click', function (e) {
        var link = e.target.closest('.ajax-nav-link');
        if (!link) return;

        e.preventDefault();
        var targetUrl = link.getAttribute('href');
        if (!targetUrl) return;

        var fetchUrl = targetUrl + (targetUrl.indexOf('?') !== -1 ? '&ajax=1' : '?ajax=1');

        fetch(fetchUrl)
            .then(function (res) { return res.text(); })
            .then(function (html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var newContent = doc.getElementById('catalogo');
                var currentContent = document.getElementById('catalogo');

                if (newContent && currentContent) {
                    currentContent.innerHTML = newContent.innerHTML;
                    history.pushState(null, '', targetUrl);
                }
            })
            .catch(function (err) {
                window.location.href = targetUrl;
            });
    });

    // Interceptor de Búsqueda AJAX
    document.addEventListener('submit', function (e) {
        var searchForm = e.target.closest('.ajax-search-form');
        if (!searchForm) return;

        e.preventDefault();
        var formData = new FormData(searchForm);
        var searchParams = new URLSearchParams(formData);
        var targetUrl = 'index.php?' + searchParams.toString() + '#catalogo';
        var fetchUrl = 'index.php?' + searchParams.toString() + '&ajax=1';

        fetch(fetchUrl)
            .then(function (res) { return res.text(); })
            .then(function (html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var newContent = doc.getElementById('catalogo');
                var currentContent = document.getElementById('catalogo');

                if (newContent && currentContent) {
                    currentContent.innerHTML = newContent.innerHTML;
                    history.pushState(null, '', targetUrl);
                }
            })
            .catch(function (err) {
                window.location.href = targetUrl;
            });
    });

    // Interceptor de Agregar al Carrito por AJAX
    document.addEventListener('submit', function (e) {
        var cartForm = e.target.closest('.ajax-add-cart-form');
        if (!cartForm) return;

        e.preventDefault();
        var formData = new FormData(cartForm);
        formData.append('ajax', '1');

        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data && data.ok) {
                // Actualizar contadores del carrito
                var counters = document.querySelectorAll('.nav-cart-badge, .cart-items-counter');
                counters.forEach(function (c) {
                    c.textContent = data.cartTotals.items;
                });

                // Mostrar Toast de Notificación
                var toast = document.getElementById('ajax-toast');
                var toastMsg = document.getElementById('toast-message');
                if (toast && toastMsg) {
                    toastMsg.textContent = data.message;
                    toast.classList.remove('d-none');
                    toast.classList.add('d-flex');

                    setTimeout(function () {
                        toast.classList.remove('d-flex');
                        toast.classList.add('d-none');
                    }, 3000);
                }
            }
        })
        .catch(function (err) {
            cartForm.submit();
        });
    });
});
</script>
