<?php
function storefront_render_header($title, $currentPage, array $cartTotals)
{
    $cartCount = !empty($cartTotals['items']) ? (int)$cartTotals['items'] : 0;
    ?>
<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <meta name="description" content="Carnicería Cano - Carnes frescas de res, cerdo, pollo, embutidos y cortes para parrilla con pedido directo.">
        <link rel="shortcut icon" href="img/logo_1.png">
        <title><?php echo storefront_escape($title); ?></title>
        <link href="css/bootstrap.min.css" rel="stylesheet">
        <link href="css/storefront.css" rel="stylesheet">
        <link href="css/storefront-shop.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    </head>
    <body class="mobile-first-app">
        <!-- Top Bar Informativo y Horarios -->
        <div class="marketing-urgency-bar py-1 px-3 text-center text-white small">
            <span class="badge bg-warning text-dark fw-bold me-1"><i class="bi bi-clock-history"></i> HORARIOS DE ATENCIÓN</span>
            <span><strong>Sucursal 1:</strong> 7:30 AM - 3:30 PM | <strong>Sucursal 2:</strong> 8:00 AM - 4:00 PM • <strong>Reparto a Domicilio (Máx. 5 km)</strong></span>
        </div>

        <!-- Sticky Header Principal -->
        <header class="store-header sticky-top">
            <div class="container">
                <nav class="navbar navbar-expand-lg storefront-nav-mobile">
                    <a class="navbar-brand brand-lockup d-flex align-items-center" href="index.php">
                        <img src="img/logo_1.jpeg" alt="Carnicería Cano" class="brand-logo me-2">
                        <div class="d-flex flex-column">
                            <span class="brand-title">CARNICERÍA CANO</span>
                            <div class="d-flex align-items-center gap-1">
                                <small class="text-gold fw-bold"><i class="bi bi-check-circle-fill text-warning"></i> Calidad y Peso Exacto</small>
                            </div>
                        </div>
                    </a>
                    
                    <div class="d-flex align-items-center gap-2">
                        <!-- Carrito en Header Móvil -->
                        <a href="carrito.php" class="btn-quick-cart-header position-relative d-lg-none" aria-label="Ver carrito">
                            <i class="bi bi-bag-fill fs-5 text-white"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger shadow-sm header-cart-badge cart-items-counter <?php echo $cartCount > 0 ? '' : 'd-none'; ?>">
                                <?php echo storefront_escape($cartCount); ?>
                            </span>
                        </a>

                        <button class="navbar-toggler border-0 text-white p-1" type="button" data-bs-toggle="collapse" data-bs-target="#storefrontNav" aria-controls="storefrontNav" aria-expanded="false" aria-label="Abrir menú">
                            <i class="bi bi-list fs-2 text-gold"></i>
                        </button>
                    </div>

                    <div class="collapse navbar-collapse" id="storefrontNav">
                        <ul class="navbar-nav ms-auto align-items-lg-center">
                            <li class="nav-item">
                                <a class="nav-link storefront-menu-link <?php echo $currentPage === 'catalogo' ? 'active' : ''; ?>" href="index.php">
                                    <i class="bi bi-grid-fill me-1"></i> Catálogo
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link storefront-menu-link nav-link-cart <?php echo $currentPage === 'carrito' ? 'active' : ''; ?>" href="carrito.php">
                                    <i class="bi bi-cart3 me-1"></i> Carrito
                                    <span class="nav-cart-badge cart-items-counter <?php echo $cartCount > 0 ? '' : 'd-none'; ?>"><?php echo storefront_escape($cartCount); ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link storefront-menu-link <?php echo $currentPage === 'pedido' ? 'active' : ''; ?>" href="pedido.php">
                                    <i class="bi bi-bag-check-fill me-1"></i> Finalizar Pedido
                                </a>
                            </li>
                            <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                                <a class="btn-system-access" href="login/login.php">
                                    <i class="bi bi-shield-lock-fill me-1"></i> Acceso Sistema
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>
        </header>

        <main id="inicio" class="pb-5 mb-5 pb-lg-0 mb-lg-0">
            <?php if ($currentPage === 'catalogo'): ?>
            <!-- Hero Mobile-First Limpio & Auténtico -->
            <section class="hero-section hero-mobile-marketing">
                <div class="container">
                    <div class="row align-items-center">
                        <div class="col-lg-7 text-center text-lg-start">
                            <div class="badge-marketing mb-2">
                                <i class="bi bi-patch-check-fill text-warning me-1"></i> Cortes Frescos Seleccionados Diariamente
                            </div>
                            <h1 class="hero-marketing-title">
                                Carnes frescas de Res, Cerdo y Parrilla a tu mesa
                            </h1>
                            <p class="hero-marketing-subtitle">
                                Elige la cantidad exacta que necesites en kilos o piezas. Te ofrecemos atención personalizada, limpieza y productos frescos en cada pedido.
                            </p>
                            
                            <!-- Buscador Rápido -->
                            <div class="search-hero-box my-3">
                                <form method="get" action="index.php" class="d-flex align-items-stretch gap-2">
                                    <input type="text" name="buscar" class="form-control form-control-lg rounded-pill shadow-sm" placeholder="🔍 ¿Qué producto buscas? Ej: Bistec, Milanesa, Arrachera...">
                                    <button type="submit" class="btn btn-warning btn-lg rounded-pill px-4 font-weight-bold shadow-sm d-inline-flex align-items-center justify-content-center">Buscar</button>
                                </form>
                            </div>

                            <div class="marketing-trust-badges d-flex justify-content-center justify-content-lg-start gap-3 flex-wrap mt-3">
                                <span><i class="bi bi-check-circle-fill text-warning me-1"></i> Peso Exacto</span>
                                <span><i class="bi bi-truck text-warning me-1"></i> Entrega a Domicilio</span>
                                <span><i class="bi bi-shop text-warning me-1"></i> Recoge en Sucursal</span>
                            </div>
                        </div>

                        <div class="col-lg-5 mt-4 mt-lg-0">
                            <!-- Banner Promocional de Parrilla -->
                            <div class="promo-card-marketing overflow-hidden rounded-4 shadow-lg position-relative border border-warning border-opacity-50">
                                <img src="img/promo_asado.jpg" alt="Cortes para Asar" class="w-100 img-fluid">
                                <div class="promo-card-overlay p-3 text-white">
                                    <span class="badge bg-danger text-uppercase fw-bold mb-1"><i class="bi bi-fire"></i> Recomendación de la Casa</span>
                                    <h4 class="h5 font-serif fw-bold m-0">¿Carne Asada o Comida Familiar?</h4>
                                    <p class="small text-white-50 m-0">Tenemos los mejores cortes y marinados listos para ti.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>
    <?php
}

function storefront_render_footer()
{
    ?>
        </main>
        
        <!-- Footer Simplificado: Logo Centrado Sin Texto -->
        <footer class="store-footer-centered text-center">
            <div class="container text-center">
                <img src="img/logo_1.jpeg" alt="Carnicería Cano" class="footer-logo-only">
            </div>
        </footer>

        <!-- Sticky Bottom Nav Bar para Móviles -->
        <nav class="mobile-bottom-nav d-lg-none">
            <a href="index.php" class="mobile-nav-item active">
                <i class="bi bi-house-door-fill"></i>
                <span>Inicio</span>
            </a>
            <a href="index.php#catalogo" class="mobile-nav-item">
                <i class="bi bi-grid-fill"></i>
                <span>Catálogo</span>
            </a>
            <a href="carrito.php" class="mobile-nav-item position-relative">
                <i class="bi bi-cart-fill"></i>
                <span>Carrito</span>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger shadow-sm cart-items-counter mobile-bottom-cart-badge <?php echo $cartCount > 0 ? '' : 'd-none'; ?>">
                    <?php echo storefront_escape($cartCount); ?>
                </span>
            </a>
            <a href="pedido.php" class="mobile-nav-item">
                <i class="bi bi-bag-check-fill"></i>
                <span>Pedido</span>
            </a>
            <a href="https://wa.me/?text=Hola%20Carnicer%C3%ADa%20Cano,%20quisiera%20hacer%20un%20pedido" target="_blank" rel="noopener" class="mobile-nav-item nav-whatsapp">
                <i class="bi bi-whatsapp"></i>
                <span>WhatsApp</span>
            </a>
        </nav>

        <script src="js/bootstrap.bundle.min.js"></script>
    </body>
</html>
    <?php
}
