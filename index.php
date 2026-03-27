<?php
require_once __DIR__ . '/storefront_logic.php';
require_once __DIR__ . '/storefront_layout.php';

storefront_render_header('Tienda | Carniceria Cano', 'catalogo', $cartTotals);
require_once __DIR__ . '/storefront_catalog_view.php';
storefront_render_footer();
