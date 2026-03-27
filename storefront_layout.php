<?php
function storefront_render_header($title, $currentPage, array $cartTotals)
{
    ?>
<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Tienda en linea de Carniceria Cano con catalogo, carrito y pedidos web.">
        <link rel="shortcut icon" href="img/logo_1.png">
        <title><?php echo storefront_escape($title); ?></title>
        <link href="css/bootstrap.min.css" rel="stylesheet">
        <link href="css/storefront.css" rel="stylesheet">
        <link href="css/storefront-shop.css" rel="stylesheet">
    </head>
    <body>
        <header class="store-header">
            <div class="container">
                <nav class="navbar navbar-expand-lg storefront-nav">
                    <a class="navbar-brand brand-lockup" href="index.php">
                        <img src="img/logo_1.jpeg" alt="Carniceria Cano" class="brand-logo">
                        <span>
                            <strong>Carniceria Cano</strong>
                            <small>Tienda publica</small>
                        </span>
                    </a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#storefrontNav" aria-controls="storefrontNav" aria-expanded="false" aria-label="Abrir navegacion">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="storefrontNav">
                        <ul class="navbar-nav ms-auto align-items-lg-center">
                            <li class="nav-item">
                                <a class="nav-link storefront-menu-link <?php echo $currentPage === 'catalogo' ? 'active' : ''; ?>" href="index.php">Tienda</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link storefront-menu-link nav-link-cart <?php echo $currentPage === 'carrito' ? 'active' : ''; ?>" href="carrito.php">
                                    Carrito
                                    <?php if (!empty($cartTotals['items'])): ?>
                                        <span class="nav-cart-badge"><?php echo storefront_escape($cartTotals['items']); ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link storefront-menu-link <?php echo $currentPage === 'pedido' ? 'active' : ''; ?>" href="pedido.php">Cerrar pedido</a>
                            </li>
                            <li class="nav-item ms-lg-3"><a class="storefront-menu-link storefront-menu-link-ghost" href="login/login.php">Entrar al sistema</a></li>
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
                            <p class="hero-copy">Navega productos en la tienda, revisa el carrito en su propia pagina y captura los datos del cliente al final.</p>
                        </div>
                        <div class="catalog-hero-actions">
                            <a class="hero-action-link hero-action-link-primary" href="pedido.php">Cerrar pedido</a>
                        </div>
                    </div>
                </div>
            </section>
    <?php
}

function storefront_render_footer()
{
    ?>
        </main>
        <script src="js/bootstrap.bundle.min.js"></script>
    </body>
</html>
    <?php
}
