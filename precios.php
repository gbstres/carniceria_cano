<?php
$idSucursalPrecios = isset($_GET['sucursal']) ? (int) $_GET['sucursal'] : 0;
if ($idSucursalPrecios <= 0) {
    $idSucursalPrecios = 1;
}

define('STOREFRONT_SUCURSAL_ID', $idSucursalPrecios);
require_once __DIR__ . '/storefront_logic.php';

$branchName = storefront_fetch_branch_name($link, $idSucursalPrecios);
$products = storefront_fetch_price_products($link, $idSucursalPrecios);
$groupedProducts = [];
foreach ($products as $product) {
    $groupedProducts[$product['categoria']][] = $product;
}
?>
<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Lista de precios de menudeo por sucursal de Carniceria Cano.">
        <link rel="shortcut icon" href="img/logo_1.png">
        <title>Precios | Carniceria Cano</title>
        <link href="css/bootstrap.min.css" rel="stylesheet">
        <style>
            :root {
                --cano-red: #8d1d1d;
                --cano-ink: #241916;
                --cano-muted: #756862;
                --cano-line: #e7ddd5;
                --cano-paper: #fffaf4;
            }
            body {
                margin: 0;
                color: var(--cano-ink);
                background: #f5f1ea;
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            }
            .price-header {
                background: #8d1d1d;
                color: #fff;
                padding: 1.25rem 0;
            }
            .brand-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
            }
            .brand-title {
                display: flex;
                align-items: center;
                gap: 0.85rem;
            }
            .brand-title img {
                width: 58px;
                height: 58px;
                border-radius: 8px;
                object-fit: cover;
                background: #fff;
            }
            h1 {
                margin: 0;
                font-size: clamp(1.45rem, 4vw, 2.35rem);
                line-height: 1.1;
                font-family: Georgia, "Times New Roman", serif;
            }
            .branch-pill {
                border: 1px solid rgba(255, 255, 255, 0.42);
                border-radius: 8px;
                padding: 0.55rem 0.85rem;
                font-weight: 700;
                text-align: right;
            }
            .price-main {
                padding: 1rem 0 3rem;
            }
            .notice {
                color: var(--cano-muted);
                margin: 0 0 1rem;
            }
            .category-block {
                background: #fff;
                border: 1px solid var(--cano-line);
                border-radius: 8px;
                margin-bottom: 1rem;
                overflow: hidden;
            }
            .category-title {
                margin: 0;
                padding: 0.8rem 1rem;
                background: var(--cano-paper);
                border-bottom: 1px solid var(--cano-line);
                font-size: 1.05rem;
                font-weight: 800;
            }
            .price-row {
                display: grid;
                grid-template-columns: 1fr auto;
                gap: 1rem;
                padding: 0.72rem 1rem;
                border-bottom: 1px solid #f0e7df;
                align-items: center;
            }
            .price-row:last-child {
                border-bottom: 0;
            }
            .product-name {
                font-weight: 650;
            }
            .product-code {
                display: block;
                color: var(--cano-muted);
                font-size: 0.84rem;
                margin-top: 0.12rem;
            }
            .product-price {
                color: var(--cano-red);
                font-weight: 850;
                font-size: 1.05rem;
                white-space: nowrap;
            }
            .empty-state {
                background: #fff;
                border: 1px solid var(--cano-line);
                border-radius: 8px;
                padding: 1.4rem;
                text-align: center;
            }
            @media (max-width: 575px) {
                .brand-row {
                    align-items: flex-start;
                    flex-direction: column;
                }
                .branch-pill {
                    text-align: left;
                }
            }
        </style>
    </head>
    <body>
        <header class="price-header">
            <div class="container">
                <div class="brand-row">
                    <div class="brand-title">
                        <img src="img/logo_1.jpeg" alt="Carniceria Cano">
                        <div>
                            <h1>Precios de menudeo</h1>
                            <div>Carniceria Cano</div>
                        </div>
                    </div>
                    <div class="branch-pill"><?php echo storefront_escape($branchName); ?></div>
                </div>
            </div>
        </header>
        <main class="price-main">
            <div class="container">
                <p class="notice">Los precios se muestran por sucursal y pueden cambiar sin previo aviso.</p>

                <?php if (empty($groupedProducts)): ?>
                    <div class="empty-state">
                        <strong>No hay productos de menudeo activos para esta sucursal.</strong>
                    </div>
                <?php else: ?>
                    <?php foreach ($groupedProducts as $category => $categoryProducts): ?>
                        <section class="category-block">
                            <h2 class="category-title"><?php echo storefront_escape($category); ?></h2>
                            <?php foreach ($categoryProducts as $product): ?>
                                <div class="price-row">
                                    <div>
                                        <span class="product-name"><?php echo storefront_escape($product['descripcion']); ?></span>
                                        <span class="product-code">Codigo <?php echo storefront_escape($product['codigo']); ?></span>
                                    </div>
                                    <div class="product-price"><?php echo storefront_escape(storefront_money($product['precio_venta'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </body>
</html>
