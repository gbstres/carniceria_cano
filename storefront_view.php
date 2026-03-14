<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Tienda en linea de Carniceria Cano con catalogo, carrito y pedidos web.">
        <link rel="shortcut icon" href="img/logo_1.png">
        <title>Tienda | Carniceria Cano</title>
        <link href="css/bootstrap.min.css" rel="stylesheet">
        <link href="css/storefront.css" rel="stylesheet">
        <link href="css/storefront-shop.css" rel="stylesheet">
    </head>
    <body>
        <header class="store-header">
            <div class="container">
                <nav class="navbar navbar-expand-lg storefront-nav">
                    <a class="navbar-brand brand-lockup" href="#inicio">
                        <img src="img/logo_1.jpeg" alt="Carniceria Cano" class="brand-logo">
                        <span>
                            <strong>Carniceria Cano</strong>
                            <small>Tienda completa con carrito y pedidos</small>
                        </span>
                    </a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#storefrontNav" aria-controls="storefrontNav" aria-expanded="false" aria-label="Abrir navegacion">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="storefrontNav">
                        <ul class="navbar-nav ms-auto align-items-lg-center">
                            <li class="nav-item"><a class="nav-link" href="#catalogo">Catalogo</a></li>
                            <li class="nav-item"><a class="nav-link" href="#carrito">Carrito</a></li>
                            <li class="nav-item"><a class="nav-link" href="#pedido">Pedido</a></li>
                            <li class="nav-item ms-lg-3"><a class="btn btn-outline-light btn-sm" href="login/login.php">Entrar al sistema</a></li>
                        </ul>
                    </div>
                </nav>
            </div>
        </header>

        <main id="inicio">
            <section class="hero-section hero-section-compact">
                <div class="container">
                    <div class="catalog-hero">
                        <div>
                            <span class="eyebrow">Catalogo</span>
                            <h1>Encuentra productos por categoria</h1>
                            <p class="hero-copy">
                                Una tienda mas simple: busca por nombre o codigo, filtra por categoria y agrega al carrito.
                            </p>
                        </div>
                        <div class="catalog-hero-stats">
                            <span><?php echo storefront_escape(count($products)); ?> productos</span>
                            <span><?php echo storefront_escape(count($categoryCounts)); ?> categorias</span>
                            <span><?php echo storefront_escape($cartTotals['items']); ?> en carrito</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="catalog-section" id="catalogo">
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
                            <p>
                                Tu pedido web quedo guardado con el folio <strong>#<?php echo storefront_escape($orderSuccess['id']); ?></strong>
                                por un total de <strong><?php echo storefront_escape(storefront_money($orderSuccess['total'])); ?></strong>.
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="catalog-shell">
                        <aside class="filter-sidebar">
                            <form method="get" action="index.php" class="filter-panel">
                                <label class="filter-label" for="buscar">Buscar producto</label>
                                <input
                                    id="buscar"
                                    name="buscar"
                                    type="text"
                                    class="form-control filter-input"
                                    placeholder="Nombre, codigo o categoria"
                                    value="<?php echo storefront_escape($searchTerm); ?>"
                                >
                                <button type="submit" class="btn btn-primary w-100">Buscar</button>
                                <a href="index.php#catalogo" class="btn btn-outline-secondary w-100">Limpiar filtros</a>
                            </form>

                            <div class="filter-panel">
                                <div class="filter-label">Categorias</div>
                                <div class="category-list">
                                    <a href="index.php#catalogo" class="category-link <?php echo $selectedCategory === '' ? 'active' : ''; ?>">
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
                                        <?php if ($selectedCategory !== ''): ?>
                                            <?php echo storefront_escape($selectedCategory); ?>
                                        <?php else: ?>
                                            Todos los productos
                                        <?php endif; ?>
                                    </h2>
                                    <p>
                                        <?php echo storefront_escape($totalFilteredProducts); ?> productos encontrados
                                        <?php if ($searchTerm !== ''): ?>
                                            para "<?php echo storefront_escape($searchTerm); ?>"
                                        <?php endif; ?>
                                        <?php if ($totalFilteredProducts > 0): ?>
                                            | Mostrando <?php echo storefront_escape($offset + 1); ?>-<?php echo storefront_escape(min($offset + $productsPerPage, $totalFilteredProducts)); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <a href="#carrito" class="results-cart-link">Carrito: <?php echo storefront_escape($cartTotals['items']); ?></a>
                            </div>

                            <?php if (empty($paginatedProducts)): ?>
                                <div class="empty-state">
                                    <h3>No encontramos productos</h3>
                                    <p>Prueba con otra categoria o cambia el texto de busqueda.</p>
                                </div>
                            <?php else: ?>
                                <div class="row g-4">
                                    <?php foreach ($paginatedProducts as $product): ?>
                                        <div class="col-md-6 col-xl-4">
                                            <article class="product-card product-card-store">
                                                <div class="product-card-top">
                                                    <span class="product-category"><?php echo storefront_escape($product['categoria']); ?></span>
                                                    <span class="product-stock"><?php echo storefront_escape(storefront_stock_label($product['almacen'])); ?></span>
                                                </div>
                                                <h3><?php echo storefront_escape($product['descripcion']); ?></h3>
                                                <p class="product-code">Codigo: <?php echo storefront_escape($product['codigo']); ?></p>
                                                <div class="product-meta">
                                                    <strong><?php echo storefront_escape(storefront_money($product['precio_venta'])); ?></strong>
                                                    <span>Stock aprox: <?php echo storefront_escape(number_format((float) $product['almacen'], 2)); ?></span>
                                                </div>
                                                <form method="post" class="product-form">
                                                    <input type="hidden" name="action" value="add_to_cart">
                                                    <input type="hidden" name="product_code" value="<?php echo storefront_escape($product['codigo']); ?>">
                                                    <label for="qty-<?php echo storefront_escape($product['codigo']); ?>">Cantidad</label>
                                                    <div class="product-form-row">
                                                        <input id="qty-<?php echo storefront_escape($product['codigo']); ?>" type="number" name="quantity" min="0.25" step="0.25" value="1" class="form-control">
                                                        <button type="submit" class="btn btn-primary">Agregar</button>
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
                                            <a class="page-link-store <?php echo $page === $currentPage ? 'active' : ''; ?>" href="<?php echo storefront_escape(storefront_build_url(['page' => $page])); ?>">
                                                <?php echo storefront_escape($page); ?>
                                            </a>
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

            <section class="cart-section" id="carrito">
                <div class="container">
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
                                        <p>Agrega productos del catalogo para empezar el pedido.</p>
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
                                                            <td>
                                                                <input type="number" min="0.25" step="0.25" name="quantities[<?php echo storefront_escape($item['codigo']); ?>]" value="<?php echo storefront_escape(number_format((float) $item['quantity'], 2, '.', '')); ?>" class="form-control cart-qty">
                                                            </td>
                                                            <td><?php echo storefront_escape(storefront_money($item['subtotal'])); ?></td>
                                                            <td class="text-end">
                                                                <a href="?remove=<?php echo storefront_escape(urlencode($item['codigo'])); ?>#carrito" class="btn btn-sm btn-outline-danger">Quitar</a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <button type="submit" class="btn btn-light-dark">Actualizar carrito</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <aside class="summary-card">
                                <span class="eyebrow">Resumen</span>
                                <h3>Total del pedido</h3>
                                <div class="summary-line">
                                    <span>Items</span>
                                    <strong><?php echo storefront_escape($cartTotals['items']); ?></strong>
                                </div>
                                <div class="summary-line">
                                    <span>Subtotal</span>
                                    <strong><?php echo storefront_escape(storefront_money($cartTotals['subtotal'])); ?></strong>
                                </div>
                                <div class="summary-line summary-total">
                                    <span>Total</span>
                                    <strong><?php echo storefront_escape(storefront_money($cartTotals['total'])); ?></strong>
                                </div>
                                <a href="#pedido" class="btn btn-primary w-100">Continuar al pedido</a>
                            </aside>
                        </div>
                    </div>
                </div>
            </section>

            <section class="checkout-section" id="pedido">
                <div class="container">
                    <div class="section-heading">
                        <span class="eyebrow">Pedido</span>
                        <h2>Captura datos del cliente y confirma la compra</h2>
                        <p>Al enviar este formulario se registra un pedido web con su folio y detalle de productos.</p>
                    </div>
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
                                    <button type="submit" class="btn btn-primary btn-lg">Registrar pedido</button>
                                    <span class="checkout-total">Total actual: <?php echo storefront_escape(storefront_money($cartTotals['total'])); ?></span>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </main>
        <script src="js/bootstrap.bundle.min.js"></script>
    </body>
</html>
