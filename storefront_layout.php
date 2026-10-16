<?php
function storefront_render_header($title, $currentPage, array $cartTotals)
{
    $cartCount = !empty($cartTotals['items']) ? (int)$cartTotals['items'] : 0;
    global $storefrontBranches, $link;
    if (!isset($storefrontBranches) && isset($link) && $link instanceof mysqli) {
        $storefrontBranches = storefront_fetch_active_branches($link);
    }
    $headerBranches = is_array($storefrontBranches ?? null) ? $storefrontBranches : [];
    ?>
<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <title><?php echo storefront_escape($title); ?></title>
        <meta name="description" content="Carnicerías "EL Cinco" en Ecatepec de Morelos. Sucursales en San Cristóbal Centro y Olímpica Jajalpa (La Principal). Carne fresca de res, cerdo, pollo y cortes para asar con servicio a domicilio hasta 30 km. ¡Pide en línea!">
        <meta name="keywords" content="carnicería ecatepec, carnicería cano, carnicería la principal ecatepec, carnicería olimpica jajalpa, carnicería san cristobal centro, cortes finos ecatepec, carne a domicilio ecatepec">
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="https://carniceriaselcinco.com/">
        <link rel="shortcut icon" href="img/logo_1.png">

        <!-- Open Graph / Redes Sociales -->
        <meta property="og:type" content="website">
        <meta property="og:title" content="<?php echo storefront_escape($title); ?>">
        <meta property="og:description" content="Carnes frescas de res, cerdo, pollo y cortes selectos para parrilladas en Ecatepec (San Cristóbal Centro y Olímpica Jajalpa). Calidad, peso exacto y servicio a domicilio rápido.">
        <meta property="og:url" content="https://carniceriaselcinco.com/">
        <meta property="og:site_name" content="Carnicerías "EL Cinco"">
        <meta property="og:locale" content="es_MX">
        <meta property="og:image" content="https://carniceriaselcinco.com/img/promo_asado.jpg">

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?php echo storefront_escape($title); ?>">
        <meta name="twitter:description" content="Carnes frescas y cortes selectos en Ecatepec (San Cristóbal y Olímpica Jajalpa). Reparto a domicilio hasta 30 km.">
        <meta name="twitter:image" content="https://carniceriaselcinco.com/img/promo_asado.jpg">

        <!-- Geoposicionamiento Local para Motores de Búsqueda -->
        <meta name="geo.region" content="MX-MEX">
        <meta name="geo.placename" content="Ecatepec de Morelos">
        <meta name="geo.position" content="19.602154;-99.045108">
        <meta name="ICBM" content="19.602154, -99.045108">

        <!-- Datos Estructurados Schema.org Multi-Sucursal (Google Rich Results / LocalBusiness) -->
        <script type="application/ld+json">
        {
          "@context": "https://schema.org",
          "@graph": [
            {
              "@type": "ButcherShop",
              "@id": "https://carniceriaselcinco.com/#sucursal-ecatepec",
              "name": "Carnicerías "EL Cinco" - Sucursal Ecatepec (San Cristóbal Centro)",
              "image": "https://carniceriaselcinco.com/img/promo_asado.jpg",
              "url": "https://carniceriaselcinco.com/",
              "telephone": "+525534208819",
              "priceRange": "$$",
              "servesCuisine": "Carnes frescas de res, cerdo, pollo y cortes selectos",
              "address": {
                "@type": "PostalAddress",
                "streetAddress": "C. Miguel Hidalgo 19, San Cristóbal Centro",
                "addressLocality": "Ecatepec de Morelos",
                "addressRegion": "Estado de México",
                "postalCode": "55000",
                "addressCountry": "MX"
              },
              "geo": {
                "@type": "GeoCoordinates",
                "latitude": 19.6021539,
                "longitude": -99.0451077
              },
              "openingHoursSpecification": [
                {
                  "@type": "OpeningHoursSpecification",
                  "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"],
                  "opens": "07:30",
                  "closes": "15:30"
                }
              ],
              "areaServed": [
                { "@type": "AdministrativeArea", "name": "Ecatepec de Morelos" },
                { "@type": "Place", "name": "San Cristóbal Centro" },
                { "@type": "Place", "name": "Las Américas" },
                { "@type": "Place", "name": "Coacalco" }
              ],
              "potentialAction": {
                "@type": "OrderAction",
                "target": {
                  "@type": "EntryPoint",
                  "urlTemplate": "https://carniceriaselcinco.com/",
                  "inLanguage": "es-MX",
                  "actionPlatform": [
                    "http://schema.org/DesktopWebPlatform",
                    "http://schema.org/MobileWebPlatform"
                  ]
                },
                "deliveryMethod": "http://purl.org/goodrelations/v1#DeliveryModeOwnFleet"
              }
            },
            {
              "@type": "ButcherShop",
              "@id": "https://carniceriaselcinco.com/#sucursal-la-principal",
              "name": "Carnicerías "EL Cinco" - Sucursal La Principal (Olímpica Jajalpa)",
              "image": "https://carniceriaselcinco.com/img/promo_asado.jpg",
              "url": "https://carniceriaselcinco.com/",
              "telephone": "+525521458855",
              "priceRange": "$$",
              "servesCuisine": "Carnes frescas de res, cerdo, pollo y cortes selectos",
              "address": {
                "@type": "PostalAddress",
                "streetAddress": "Nezahualcóyotl 1-Interior 1, Olímpica Jajalpa",
                "addressLocality": "Ecatepec de Morelos",
                "addressRegion": "Estado de México",
                "postalCode": "55090",
                "addressCountry": "MX"
              },
              "geo": {
                "@type": "GeoCoordinates",
                "latitude": 19.588193,
                "longitude": -99.0366936
              },
              "openingHoursSpecification": [
                {
                  "@type": "OpeningHoursSpecification",
                  "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"],
                  "opens": "08:00",
                  "closes": "16:00"
                }
              ],
              "areaServed": [
                { "@type": "AdministrativeArea", "name": "Ecatepec de Morelos" },
                { "@type": "Place", "name": "Olímpica Jajalpa" },
                { "@type": "Place", "name": "Jardines de Morelos" },
                { "@type": "Place", "name": "Las Américas" }
              ],
              "potentialAction": {
                "@type": "OrderAction",
                "target": {
                  "@type": "EntryPoint",
                  "urlTemplate": "https://carniceriaselcinco.com/",
                  "inLanguage": "es-MX",
                  "actionPlatform": [
                    "http://schema.org/DesktopWebPlatform",
                    "http://schema.org/MobileWebPlatform"
                  ]
                },
                "deliveryMethod": "http://purl.org/goodrelations/v1#DeliveryModeOwnFleet"
              }
            }
          ]
        }
        </script>

        <link rel="preconnect" href="https://cdn.jsdelivr.net">
        <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
        <link href="css/bootstrap.min.css" rel="stylesheet">
        <link href="css/storefront.css" rel="stylesheet">
        <link href="css/storefront-shop.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <?php 
        // Leaflet / Google Maps se cargan condicionalmente ÚNICAMENTE en la página de checkout (pedido.php)
        $isCheckoutPage = ($currentPage === 'pedido');
        if ($isCheckoutPage):
            $googleApiKey = defined('GOOGLE_MAPS_API_KEY') ? trim(GOOGLE_MAPS_API_KEY) : '';
            if (!empty($googleApiKey)): ?>
                <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo storefront_escape($googleApiKey); ?>&libraries=geometry" defer></script>
            <?php else: ?>
                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="" defer></script>
            <?php endif;
        endif; ?>
    </head>
    <body class="mobile-first-app">
        <!-- Top Bar Informativo y Horarios -->
        <div class="marketing-urgency-bar py-1 px-3 text-center text-white small">
            <span class="badge bg-warning text-dark fw-bold me-1"><i class="bi bi-clock-history"></i> HORARIOS DE ATENCIÓN</span>
            <?php if (!empty($headerBranches)): ?>
                <span class="branch-hours-carousel" aria-label="Sucursales, horarios y ubicaciones">
                    <span class="branch-hours-track">
                        <?php for ($copy = 0; $copy < 2; $copy++): ?>
                            <?php foreach ($headerBranches as $branch): ?>
                                <span class="branch-hours-item"><strong><?php echo storefront_escape(function_exists('storefront_format_branch_title') ? storefront_format_branch_title($branch['desc_sucursal']) : $branch['desc_sucursal']); ?>:</strong> <?php echo storefront_escape($branch['horario']); ?> · <?php echo storefront_escape($branch['direccion']); ?></span>
                            <?php endforeach; ?>
                        <?php endfor; ?>
                    </span>
                </span>
            <?php endif; ?>
            <span class="delivery-radius-note">• <strong>Reparto a Domicilio (Hasta 30 km)</strong></span>
        </div>

        <!-- Sticky Header Principal -->
        <header class="store-header sticky-top">
            <div class="container">
                <nav class="navbar navbar-expand-lg storefront-nav-mobile">
                    <a class="navbar-brand brand-lockup d-flex align-items-center" href="index.php">
                        <img src="img/logo_1.jpeg" alt="Carnicerías &quot;El Cinco&quot;" class="brand-logo me-2">
                        <div class="d-flex flex-column">
                            <span class="brand-title">CARNICERÍAS "EL CINCO"</span>
                            <div class="d-flex align-items-center gap-1">
                                <small class="text-gold fw-bold"><i class="bi bi-check-circle-fill text-warning"></i> Calidad y Peso Exacto</small>
                            </div>
                        </div>
                    </a>
                    
                    <button class="navbar-toggler border-0 text-white p-1" type="button" data-bs-toggle="collapse" data-bs-target="#storefrontNav" aria-controls="storefrontNav" aria-expanded="false" aria-label="Abrir menú">
                        <i class="bi bi-list fs-2 text-gold"></i>
                    </button>

                    <div class="collapse navbar-collapse" id="storefrontNav">
                        <ul class="navbar-nav ms-auto align-items-lg-center">
                            <li class="nav-item">
                                <a class="nav-link storefront-menu-link <?php echo ($currentPage === 'catalogo' || $currentPage === 'producto') ? 'active' : ''; ?>" href="index.php">
                                    <i class="bi bi-grid-fill me-1"></i> Catálogo
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link storefront-menu-link <?php echo $currentPage === 'mis_pedidos' ? 'active' : ''; ?>" href="mis_pedidos.php">
                                    <i class="bi bi-clock-history me-1"></i> Mis Pedidos
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
                <?php
                $heroSearchTerm = isset($_GET['buscar']) ? trim((string)$_GET['buscar']) : '';
                $isSearching = ($heroSearchTerm !== '');
                ?>
                <?php if ($isSearching): ?>
                    <!-- Hero Compacto en Búsqueda (Sin frame promocional de asado, solo cuadro de búsqueda activo) -->
                    <section class="hero-section hero-mobile-marketing py-3 py-md-4 border-bottom">
                        <div class="container">
                            <div class="search-hero-box mx-auto" style="max-width: 680px;">
                                <form method="get" action="index.php" class="d-flex align-items-stretch gap-2">
                                    <input type="text" name="buscar" class="form-control form-control-lg rounded-pill shadow-sm" placeholder="🔍 ¿Qué producto buscas? Ej: Bistec, Milanesa, Arrachera..." value="<?php echo storefront_escape($heroSearchTerm); ?>">
                                    <button type="submit" class="btn btn-warning btn-lg rounded-pill px-4 font-weight-bold shadow-sm d-inline-flex align-items-center justify-content-center">Buscar</button>
                                </form>
                                <div class="d-flex justify-content-between align-items-center mt-2 px-2 flex-wrap gap-1">
                                    <span class="small text-white-50">
                                        Resultados de búsqueda: <strong class="text-warning">"<?php echo storefront_escape($heroSearchTerm); ?>"</strong>
                                    </span>
                                    <a href="index.php" class="text-warning small text-decoration-none fw-bold d-inline-flex align-items-center">
                                        <i class="bi bi-x-circle-fill me-1"></i> Borrar búsqueda
                                    </a>
                                </div>
                            </div>
                        </div>
                    </section>
                <?php else: ?>
                    <!-- Hero Completo con Banner Promocional -->
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
                                            <input type="text" name="buscar" class="form-control form-control-lg rounded-pill shadow-sm" placeholder="🔍 ¿Qué producto buscas? Ej: Bistec, Milanesa, Arrachera..." value="<?php echo storefront_escape($heroSearchTerm); ?>">
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
                                        <picture>
                                            <source srcset="img/promo_asado.webp" type="image/webp">
                                            <img src="img/promo_asado.jpg" alt="Cortes para Asar" class="w-100 img-fluid" loading="lazy" decoding="async">
                                        </picture>
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
            <?php endif; ?>
    <?php
}

function storefront_render_footer()
{
    $cartCount = !empty($_SESSION['store_cart']) ? count($_SESSION['store_cart']) : 0;
    global $storefrontBranches, $link;
    if ((!isset($storefrontBranches) || empty($storefrontBranches)) && isset($link) && $link instanceof mysqli) {
        $storefrontBranches = storefront_fetch_active_branches($link);
    }
    $footerBranches = is_array($storefrontBranches ?? null) ? $storefrontBranches : [];

    // Fallback de seguridad en caso de desconexión temporal de BD
    if (empty($footerBranches)) {
        $footerBranches = [
            1 => [
                'id_sucursal' => 1,
                'desc_sucursal' => 'Sucursal Ecatepec',
                'direccion' => 'C. Miguel Hidalgo 19, San Cristóbal Centro, 55000 Ecatepec, Méx.',
                'latitud' => '19.6021539',
                'longitud' => '-99.0451077',
                'horario' => '7:30 AM - 3:30 PM',
                'whatsapp' => '5534208819',
                'wa_instancia' => 'ecatepec'
            ],
            2 => [
                'id_sucursal' => 2,
                'desc_sucursal' => 'Sucursal La Principal',
                'direccion' => 'Nezahualcóyotl 1-Int 1, Olímpica Jajalpa, 55090 Ecatepec, Méx.',
                'latitud' => '19.588193',
                'longitud' => '-99.0366936',
                'horario' => '8:00 AM - 4:00 PM',
                'whatsapp' => '5521458855',
                'wa_instancia' => 'olimpica'
            ]
        ];
    }
    ?>
        </main>
        
        <!-- Footer con Información de Sucursales en Carrusel Horizontal -->
        <footer class="store-footer-centered py-4 bg-white border-top">
            <div class="container text-center">
                <img src="img/logo_1.jpeg" alt="Carnicerías &quot;El Cinco&quot;" class="footer-logo-only mb-3" loading="lazy" decoding="async">
                <h5 class="fw-bold text-dark font-serif mb-1">Carnicerías "El Cinco" - Sucursales en Ecatepec</h5>
                <p class="text-muted small mb-3">Calidad, frescura, higiene y peso exacto en cada uno de tus cortes.</p>
                
                <!-- Contenedor de Sucursales: Grid completo en PC / Carrusel en Móviles y Pantallas Pequeñas -->
                <div class="branch-carousel-shell position-relative mx-auto mb-3">
                    <button type="button" class="branch-carousel-arrow arrow-left d-lg-none" onclick="scrollBranchCarousel(-320)" aria-label="Sucursal anterior">
                        <i class="bi bi-chevron-left"></i>
                    </button>

                    <div class="branch-horizontal-scroll-container" id="branchCarouselTrack">
                        <div class="branch-horizontal-wrapper">
                            <?php foreach ($footerBranches as $branch): ?>
                                <?php
                                $branchName = function_exists('storefront_format_branch_title') 
                                    ? storefront_format_branch_title($branch['desc_sucursal'] ?? '') 
                                    : ($branch['desc_sucursal'] ?? 'Sucursal');
                                $lat = trim((string)($branch['latitud'] ?? ''));
                                $lng = trim((string)($branch['longitud'] ?? ''));
                                $mapsUrl = (!empty($lat) && !empty($lng))
                                    ? 'https://www.google.com/maps?q=' . rawurlencode($lat . ',' . $lng)
                                    : 'https://maps.google.com/?q=' . rawurlencode($branch['direccion'] ?? 'Carnicerías "EL Cinco" Ecatepec');
                                
                                $waRaw = trim((string)($branch['whatsapp'] ?? ''));
                                $cleanWa = preg_replace('/[^0-9]/', '', $waRaw);
                                $waDisplay = (!empty($cleanWa) && strlen($cleanWa) === 10)
                                    ? preg_replace('/(\d{2})(\d{4})(\d{4})/', '$1 $2 $3', $cleanWa)
                                    : ($waRaw ?: '55 3420 8819');
                                
                                $waMsg = 'Hola Carnicerías "EL Cinco" (' . $branchName . '), quisiera hacer un pedido.';
                                $waUrl = !empty($cleanWa)
                                    ? 'https://wa.me/52' . $cleanWa . '?text=' . rawurlencode($waMsg)
                                    : 'https://wa.me/?text=' . rawurlencode($waMsg);
                                
                                $instanciaWa = trim((string)($branch['wa_instancia'] ?? ''));
                                ?>
                                <div class="branch-carousel-card p-3 bg-light rounded-3 border h-100 shadow-sm text-start d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="badge bg-danger"><i class="bi bi-shop me-1"></i> <?php echo storefront_escape($branchName); ?></span>
                                            <a href="<?php echo storefront_escape($mapsUrl); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-danger py-0 px-2 rounded-pill shadow-xs" style="font-size: 0.72rem;" title="Abrir ubicación en Google Maps">
                                                <i class="bi bi-geo-alt-fill me-1"></i> Maps
                                            </a>
                                        </div>
                                        <p class="small text-dark fw-bold mb-1">
                                            <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                                            <a href="<?php echo storefront_escape($mapsUrl); ?>" target="_blank" rel="noopener" class="text-dark text-decoration-none branch-map-link" title="Ver en Google Maps">
                                                <?php echo storefront_escape($branch['direccion'] ?? ''); ?>
                                            </a>
                                        </p>
                                        <p class="small text-muted mb-2">
                                            <i class="bi bi-clock-history me-1"></i> Horario: <?php echo storefront_escape($branch['horario'] ?? '7:30 AM - 3:30 PM'); ?>
                                        </p>
                                    </div>
                                    <div class="pt-2 border-top border-secondary border-opacity-10 mt-auto">
                                        <p class="small mb-0 d-flex align-items-center justify-content-between flex-wrap gap-1">
                                            <span>
                                                <i class="bi bi-whatsapp text-success me-1"></i> WhatsApp: 
                                                <a href="<?php echo storefront_escape($waUrl); ?>" target="_blank" rel="noopener" class="text-success fw-bold text-decoration-none">
                                                    <?php echo storefront_escape($waDisplay); ?>
                                                </a>
                                            </span>
                                            <?php if (!empty($instanciaWa)): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 0.7rem;" title="Instancia Evolution API: <?php echo storefront_escape($instanciaWa); ?>">
                                                    <i class="bi bi-check-circle-fill me-1"></i> WA Activo
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="button" class="branch-carousel-arrow arrow-right d-lg-none" onclick="scrollBranchCarousel(320)" aria-label="Siguiente sucursal">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>

                <p class="small text-muted mb-0"><i class="bi bi-truck text-success me-1"></i> <strong>Reparto a Domicilio (Hasta 30 km):</strong> San Cristóbal Centro, Olímpica Jajalpa, Las Américas, Jardines de Morelos, Coacalco y zonas aledañas.</p>
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
        <script>
        function scrollBranchCarousel(distance) {
            var track = document.getElementById('branchCarouselTrack');
            if (track) {
                track.scrollBy({ left: distance, behavior: 'smooth' });
            }
        }

        function updateBranchCarouselArrows() {
            var track = document.getElementById('branchCarouselTrack');
            var leftBtn = document.querySelector('.branch-carousel-arrow.arrow-left');
            var rightBtn = document.querySelector('.branch-carousel-arrow.arrow-right');
            if (!track || !leftBtn || !rightBtn) return;
            
            // En pantallas de escritorio (PC >= 992px) es un Grid estático completo sin flechas
            if (window.innerWidth >= 992) {
                leftBtn.style.display = 'none';
                rightBtn.style.display = 'none';
                return;
            }

            var canScroll = track.scrollWidth > track.clientWidth + 8;
            if (!canScroll) {
                leftBtn.style.display = 'none';
                rightBtn.style.display = 'none';
            } else {
                leftBtn.style.display = 'inline-flex';
                rightBtn.style.display = 'inline-flex';
                leftBtn.style.opacity = track.scrollLeft <= 10 ? '0.35' : '1';
                leftBtn.style.pointerEvents = track.scrollLeft <= 10 ? 'none' : 'auto';
                var atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 10;
                rightBtn.style.opacity = atEnd ? '0.35' : '1';
                rightBtn.style.pointerEvents = atEnd ? 'none' : 'auto';
            }
        }

        window.addEventListener('load', function () {
            var track = document.getElementById('branchCarouselTrack');
            if (track) {
                track.scrollLeft = 0;
                track.addEventListener('scroll', updateBranchCarouselArrows);
            }
            updateBranchCarouselArrows();
            window.addEventListener('resize', updateBranchCarouselArrows);
        });
        </script>
    </body>
</html>
    <?php
}
