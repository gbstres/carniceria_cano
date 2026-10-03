<?php
require_once __DIR__ . '/storefront_logic.php';

// Si es una petición AJAX para actualización parcial del catálogo
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    require_once __DIR__ . '/storefront_catalog_view.php';
    exit;
}

require_once __DIR__ . '/storefront_layout.php';

storefront_render_header('Tienda | Carniceria Cano', 'catalogo', $cartTotals);
require_once __DIR__ . '/storefront_catalog_view.php';
storefront_render_footer();
