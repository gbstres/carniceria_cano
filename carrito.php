<?php
require_once __DIR__ . '/storefront_logic.php';
require_once __DIR__ . '/storefront_layout.php';

storefront_render_header('Carrito | Carnicerías "EL Cinco"', 'carrito', $cartTotals);
require_once __DIR__ . '/storefront_cart_view.php';
storefront_render_footer();
