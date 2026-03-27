<?php
require_once __DIR__ . '/storefront_logic.php';
require_once __DIR__ . '/storefront_layout.php';

storefront_render_header('Pedido | Carniceria Cano', 'pedido', $cartTotals);
require_once __DIR__ . '/storefront_checkout_view.php';
storefront_render_footer();
