<?php
require_once __DIR__ . '/storefront_logic.php';
require_once __DIR__ . '/storefront_layout.php';

$productCode = isset($_GET['codigo']) ? trim((string) $_GET['codigo']) : '';
$product = isset($products[$productCode]) ? $products[$productCode] : null;
$productGallery = $product !== null ? storefront_fetch_product_gallery($link, $productCode) : [];

if ($product !== null && empty($productGallery)) {
    $productGallery[] = [
        'small' => $product['image_small'],
        'large' => $product['image_large'],
        'alt' => $product['image_alt'],
        'is_primary' => true,
    ];
}

storefront_render_header('Producto | Carniceria Cano', 'catalogo', $cartTotals);
require_once __DIR__ . '/storefront_product_view.php';
storefront_render_footer();
