<?php
require_once '../Config/db.php';

$settingsQuery = $pdo->query("SELECT settingKey, settingValue FROM storesettings WHERE settingKey IN ('shop_sale_active', 'shop_sale_title', 'shop_sale_subtitle', 'shop_sale_image')");
$settings = [];
while ($row = $settingsQuery->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['settingKey']] = $row['settingValue'];
}
$isPromoActive = (isset($settings['shop_sale_active']) && $settings['shop_sale_active'] == '1');

$countStmt = $pdo->query("SELECT COUNT(*) FROM product WHERE isActive = 1");
$totalArchivePieces = (int) $countStmt->fetchColumn();

$query = "
    SELECT
        p.productID, p.brand, p.productName, p.basePrice, p.salePrice,
        p.isNewArrival, p.categoryID, p.slug, c.categoryName,
        (SELECT pi.filePath FROM productimage pi WHERE pi.productID = p.productID AND pi.isPrimary = 1 ORDER BY pi.sortOrder, pi.imageID LIMIT 1) AS mainImg,
        (SELECT pi.filePath FROM productimage pi WHERE pi.productID = p.productID AND pi.sortOrder = 2 ORDER BY pi.imageID LIMIT 1) AS hoverImg
    FROM product p
    LEFT JOIN category c ON p.categoryID = c.categoryID
    WHERE p.isActive = 1
    ORDER BY p.createdAt DESC
    LIMIT 12
";
$products = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

$variantsMap = [];
$productIds = array_values(array_filter(array_map(
    static fn (array $product): int => (int) ($product['productID'] ?? 0),
    $products,
)));

if ($productIds) {
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $allVarsStmt = $pdo->prepare("
        SELECT pv.productID, pv.color, pv.size, pv.stockCount,
               (SELECT pi.filePath FROM productimage pi WHERE pi.productID = pv.productID AND pi.color = pv.color ORDER BY pi.isPrimary DESC, pi.sortOrder, pi.imageID LIMIT 1) AS img
        FROM productvariant pv
        WHERE pv.isActive = 1
          AND pv.stockCount > 0
          AND pv.productID IN ($placeholders)
        ORDER BY pv.productID, pv.variantID
    ");
    $allVarsStmt->execute($productIds);

    foreach ($allVarsStmt->fetchAll(PDO::FETCH_ASSOC) as $variant) {
        $productId = (int) $variant['productID'];
        $variantsMap[$productId] ??= ['colors' => [], 'sizes' => [], 'combinations' => []];

        $color = trim((string) ($variant['color'] ?? ''));
        $size = trim((string) ($variant['size'] ?? ''));
        if ($color !== '' && !isset($variantsMap[$productId]['colors'][$color])) {
            $variantsMap[$productId]['colors'][$color] = vv_public_asset_url($variant['img'] ?? null, '');
        }
        if ($size !== '' && !in_array($size, $variantsMap[$productId]['sizes'], true)) {
            $variantsMap[$productId]['sizes'][] = $size;
        }
        if ($color !== '' && $size !== '') {
            $variantsMap[$productId]['combinations'][$color] ??= [];
            $variantsMap[$productId]['combinations'][$color][] = [
                'size' => $size,
                'stock' => max(0, (int) ($variant['stockCount'] ?? 0)),
            ];
        }
    }
}

$page_css = "shop.css";
$page_js = "shop.js";
include '../ReuseableUI/header.php';
?>

<div class="ghost-search-overlay" id="searchOverlay">
    <button class="close-search" id="closeSearch"><i class="fa-solid fa-xmark"></i></button>
    <div class="search-container">
        <input type="text" id="ghostSearchInput" placeholder="SEARCH OUR COLLECTION..." autocomplete="off">
        <div class="search-results-grid" id="searchResults"></div>
    </div>
</div>

<div class="atc-modal-overlay" id="mobileFilterOverlay"></div>

<main class="archive-wrapper">
    <div class="cinematic-grain"></div>

    <div class="hero-scroll-container">
        <section class="archive-hero-fullscreen d-flex align-items-center justify-content-center text-center" id="heroSection">
            <div class="hero-bg" style="background-image: url('https://images.unsplash.com/photo-1490481651871-ab68de25d43d?q=80&w=2000&auto=format&fit=crop');"></div>
            <div class="hero-overlay"></div>

            <div class="container-fluid px-3 px-md-5 position-relative z-2 hero-content-block">
                <div class="vv-breadcrumbs gsap-hero justify-content-center mb-4">
                    <a href="home.php">Home</a> <span class="sep">/</span> <span class="current">Shop</span>
                </div>
                <h1 class="gsap-hero title-monolith">THE COLLECTION</h1>
                <p class="gsap-hero subtitle-monolith">Premium materials. Timeless designs. Discover your style.</p>
            </div>
        </section>
    </div>

    <nav class="category-nav-bar" id="stickyActionBar">
        <div class="container-fluid px-3 px-md-5">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">

                <div class="cat-button-track-wrapper" style="flex: 1; min-width: 0;">
                    <div class="cat-button-track" id="categoryToggles">
                        <button class="vv-btn-cat active" data-filter="All">ALL</button>
                        <button class="vv-btn-cat" data-filter="Tops">TOPS</button>
                        <button class="vv-btn-cat" data-filter="Bottoms">BOTTOMS</button>
                        <button class="vv-btn-cat" data-filter="Dresses & Gowns">DRESSES</button>
                        <button class="vv-btn-cat" data-filter="Tailoring & Suiting">TAILORING</button>
                        <button class="vv-btn-cat" data-filter="Outerwear">OUTERWEAR</button>
                        <button class="vv-btn-cat" data-filter="Accessories">ACCESSORIES</button>
                        <button class="vv-btn-cat" data-filter="Footwear">FOOTWEAR</button>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between justify-content-lg-end gap-3 gap-md-4 flex-shrink-0">

                    <button class="btn-ghost-search d-lg-none" id="openMobileFilters">
                        <i class="fa-solid fa-sliders"></i> <span class="d-none d-sm-inline">FILTER</span>
                    </button>

                    <div class="custom-sort-dropdown" id="customSort">
                        <div class="sort-selected" id="sortSelected">
                            <span class="d-none d-sm-inline">SORT:</span> NEWEST <i class="fa-solid fa-chevron-down ms-2"></i>
                        </div>
                        <ul class="sort-options">
                            <li data-val="newest" class="active">NEWEST</li>
                            <li data-val="price_asc">PRICE: LOW TO HIGH</li>
                            <li data-val="price_desc">PRICE: HIGH TO LOW</li>
                        </ul>
                    </div>

                    <button class="btn-ghost-search" id="triggerGhostSearch">
                        <i class="fa-solid fa-magnifying-glass"></i> <span class="d-none d-sm-inline">SEARCH</span>
                    </button>

                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 px-md-5 mt-4" id="archiveGridSection">

        <div class="active-filters-row" id="activeFiltersRow" style="display: none;">
            <div class="d-flex align-items-center flex-wrap gap-2 w-100">
                <span class="filter-label-text me-2 d-none d-sm-block">FILTERS:</span>
                <div id="activeTagsContainer" class="d-flex gap-2 flex-wrap"></div>
                <button class="btn-clear-all ms-sm-3 mt-2 mt-sm-0" id="clearAllFilters">CLEAR ALL <i class="fa-solid fa-xmark ms-1"></i></button>

                <div class="telemetry-counter ms-auto d-none d-md-block">
                    SHOWING <span id="currentCount" class="gold-text"><?= count($products) ?></span> / <?= $totalArchivePieces ?>
                </div>
            </div>
        </div>

        <div class="row mt-4 position-relative">

            <aside class="col-lg-3 filter-sidebar pr-lg-4" id="filterSidebar">

                <div class="d-flex justify-content-between align-items-center d-lg-none mb-4 border-bottom-dark pb-3">
                    <h3 style="font-family: var(--font-heading); color: var(--color-gold-metallic); margin: 0;">FILTERS</h3>
                    <button class="btn-close-sidebar" id="closeMobileFilters"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <div class="telemetry-sidebar-fallback mb-5 pb-3 border-bottom-dark d-none d-lg-block" id="sidebarTelemetry">
                    SHOWING <span class="gold-text"><?= count($products) ?></span> / <?= $totalArchivePieces ?>
                </div>

                <div class="filter-group">
                    <h4 class="filter-title">GENDER</h4>
                    <div class="filter-content">
                        <label class="vv-checkbox"><input type="checkbox" value="Men" class="filter-chk"><span class="checkmark"></span><span class="chk-text">MENSWEAR</span></label>
                        <label class="vv-checkbox"><input type="checkbox" value="Women" class="filter-chk"><span class="checkmark"></span><span class="chk-text">WOMENSWEAR</span></label>
                        <label class="vv-checkbox"><input type="checkbox" value="Unisex" class="filter-chk"><span class="checkmark"></span><span class="chk-text">UNISEX</span></label>
                    </div>
                </div>

                <div class="filter-group border-top-dark">
                    <h4 class="filter-title">PRICE</h4>
                    <div class="price-slider-container mt-4">
                        <input type="range" min="0" max="100000" value="100000" class="vv-price-slider" id="priceRangeSlider">
                        <div class="d-flex justify-content-between mt-3 price-labels">
                            <span>RS. 0</span>
                            <span id="priceDisplay" class="gold-text">RS. 100,000</span>
                        </div>
                    </div>
                </div>

                <div class="filter-group border-top-dark">
                    <h4 class="filter-title">SIZE</h4>
                    <div class="size-grid-sidebar" id="sizeFilters">
                        <button class="size-btn">S</button>
                        <button class="size-btn">M</button>
                        <button class="size-btn">L</button>
                        <button class="size-btn">XL</button>
                        <button class="size-btn">XXL</button>
                    </div>
                </div>

                <div class="filter-group border-top-dark">
                    <h4 class="filter-title">COLOR</h4>
                    <div class="d-flex flex-wrap gap-2" id="colorFilters">
                        <div class="attr-pill" data-value="Black"><span class="color-dot bg-dark border border-secondary"></span>Black</div>
                        <div class="attr-pill" data-value="White"><span class="color-dot bg-white border border-secondary"></span>White</div>
                        <div class="attr-pill" data-value="Grey"><span class="color-dot" style="background:#808080;"></span>Grey</div>
                        <div class="attr-pill" data-value="Beige"><span class="color-dot" style="background:#F5F5DC; border:1px solid #ccc;"></span>Beige</div>
                        <div class="attr-pill" data-value="Navy"><span class="color-dot" style="background:#000080;"></span>Navy</div>
                        <div class="attr-pill" data-value="Blue"><span class="color-dot" style="background:#0000FF;"></span>Blue</div>
                        <div class="attr-pill" data-value="Red"><span class="color-dot" style="background:#FF0000;"></span>Red</div>
                        <div class="attr-pill" data-value="Burgundy"><span class="color-dot" style="background:#800020;"></span>Burgundy</div>
                        <div class="attr-pill" data-value="Pink"><span class="color-dot" style="background:#FFC0CB;"></span>Pink</div>
                        <div class="attr-pill" data-value="Purple"><span class="color-dot" style="background:#800080;"></span>Purple</div>
                        <div class="attr-pill" data-value="Green"><span class="color-dot" style="background:#008000;"></span>Green</div>
                        <div class="attr-pill" data-value="Olive"><span class="color-dot" style="background:#808000;"></span>Olive</div>
                        <div class="attr-pill" data-value="Brown"><span class="color-dot" style="background:#8B4513;"></span>Brown</div>
                        <div class="attr-pill" data-value="Yellow"><span class="color-dot" style="background:#FFFF00;"></span>Yellow</div>
                        <div class="attr-pill" data-value="Gold"><span class="color-dot" style="background:#D4AF37;"></span>Gold</div>
                        <div class="attr-pill" data-value="Silver"><span class="color-dot" style="background:#C0C0C0;"></span>Silver</div>
                    </div>
                </div>

                <div class="d-lg-none mt-4">
                    <button class="btn-vogue-mask w-100" id="applyMobileFiltersBtn">
                        <span class="btn-text">APPLY FILTERS</span>
                        <span class="btn-mask" aria-hidden="true">APPLY FILTERS</span>
                    </button>
                </div>

            </aside>

            <div class="col-lg-9 ps-lg-4">
                <div class="archive-grid" id="mainProductGrid">
                    <?php if (empty($products)): ?>
                        <div class="col-12 text-center py-5 w-100" style="grid-column: 1 / -1;">
                            <h4 class="gold-text" style="font-family: var(--font-heading); letter-spacing: 3px;">COLLECTION EMPTY</h4>
                            <p style="color: #666; font-family: var(--font-body); letter-spacing: 1px;">No items match your current selection.</p>
                        </div>
                    <?php else: ?>
                        <?php
                        $counter = 0;
                        foreach ($products as $p):
                            $counter++;
                            $pid = $p['productID'];

                            $varData = isset($variantsMap[$pid]) ? $variantsMap[$pid] : ['colors' => [], 'sizes' => [], 'combinations' => []];
                            $mainImage = vv_public_asset_url($p['mainImg'] ?? null);
                            $hoverImage = vv_public_asset_url($p['hoverImg'] ?? null);
                            $variantPayload = json_encode($varData, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                            $productNameArg = vv_e(json_encode((string) $p['productName'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR));
                            $priceArg = json_encode((float) ($p['salePrice'] ?: $p['basePrice']), JSON_THROW_ON_ERROR);
                            $imageArg = vv_e(json_encode($mainImage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR));
                            $variantsArg = vv_e(json_encode($variantPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR));

                            // ==========================================
                            // Promotional banner from store settings.
                            // ==========================================
                            if ($isPromoActive && $counter == 7):
                                $promoImg = !empty($settings['shop_sale_image']) ? vv_public_asset_url($settings['shop_sale_image']) : 'https://images.unsplash.com/photo-1550614000-4b95dd2449bb?q=80&w=2000&auto=format&fit=crop';
                                $promoTitle = !empty($settings['shop_sale_title']) ? (string) $settings['shop_sale_title'] : 'ONYX SYNDICATE';
                                $promoSubtitle = !empty($settings['shop_sale_subtitle']) ? (string) $settings['shop_sale_subtitle'] : 'FEATURED COLLECTION';
                        ?>
                                <div class="grid-promo-fullwidth gsap-reveal">
                                    <div class="promo-fw-bg" style="background-image: url('<?= vv_e($promoImg) ?>');"></div>
                                    <div class="promo-fw-overlay"></div>
                                    <div class="promo-fw-content d-flex flex-column flex-md-row justify-content-between align-items-center w-100">
                                        <div class="mb-3 mb-md-0 text-center text-md-start">
                                            <span class="promo-fw-tag"><?= vv_e($promoSubtitle) ?></span>
                                            <h3 class="promo-fw-title"><?= vv_e($promoTitle) ?></h3>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <article class="p-card-unique gsap-reveal" data-id="<?= (int) $p['productID'] ?>">
                                <div class="p-img-wrap">
                                    <?php if ($p['isNewArrival']): ?><span class="p-badge">NEW</span><?php endif; ?>

                                    <button class="action-icon ai-wish p-wishlist-hover" onclick="toggleWish(this, <?= (int) $p['productID'] ?>)">
                                        <i class="fa-regular fa-heart"></i>
                                    </button>

                                    <a href="product_detail.php?slug=<?= rawurlencode((string) $p['slug']) ?>" class="img-zoom-container d-block">
                                        <img src="<?= vv_e($mainImage) ?>" loading="lazy" decoding="async" class="img-primary" alt="<?= vv_e($p['productName']) ?>">
                                        <img src="<?= vv_e($hoverImage) ?>" loading="lazy" decoding="async" class="img-hover" alt="Alternate product view">
                                    </a>

                                    <div class="p-card-actions">
                                        <button class="btn-add-unique btn-add-magnetic" onclick="openAddToCartModal(<?= (int) $p['productID'] ?>, <?= $productNameArg ?>, <?= $priceArg ?>, <?= $imageArg ?>, <?= $variantsArg ?>)">
                                            ADD TO CART
                                        </button>
                                    </div>
                                </div>
                                <div class="p-details-unique">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                                        <div class="pe-2">
                                            <span class="p-brand"><?= vv_e($p['brand'] ?? 'VELVET VOGUE') ?></span>
                                            <a href="product_detail.php?slug=<?= rawurlencode((string) $p['slug']) ?>" style="text-decoration: none;">
                                                <h3 class="p-name" style="transition: color 0.3s;" onmouseover="this.style.color='var(--color-gold-metallic)'" onmouseout="this.style.color='#fff'"><?= vv_e($p['productName']) ?></h3>
                                            </a>
                                        </div>
                                        <div class="p-pricing text-end mt-1 mt-sm-0">
                                            <?php if ($p['salePrice']): ?>
                                                <del>Rs. <?= number_format($p['basePrice'], 0) ?></del><br>
                                                <span class="gold-text">Rs. <?= number_format($p['salePrice'], 0) ?></span>
                                            <?php else: ?>
                                                <span>Rs. <?= number_format($p['basePrice'], 0) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="text-center mt-5 pt-5 pb-5">
                    <button id="loadMoreBtn" class="btn-load-more-luxury" <?= empty($products) ? 'style="display:none;"' : '' ?>>
                        <span>LOAD MORE PRODUCTS</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="atc-modal-overlay" id="atcOverlay"></div>
    <div class="atc-modal" id="atcModal">
        <button class="close-atc" id="closeAtcModal"><i class="fa-solid fa-xmark"></i></button>

        <div class="row g-0 h-100">
            <div class="col-md-5 d-none d-md-block h-100 position-relative bg-black" id="modalImgContainer">
                <div class="img-loader" id="modalImgLoader"><i class="fa-solid fa-circle-notch fa-spin"></i></div>
                <img src="" id="modalProductImg" class="atc-modal-img" alt="Product">
            </div>

            <div class="col-md-7 atc-modal-content d-flex flex-column">
                <div class="flex-grow-1">
                    <span class="p-brand mb-2 d-block">VELVET VOGUE</span>
                    <h2 id="modalProductName" class="modal-title">PRODUCT NAME</h2>
                    <p id="modalProductPrice" class="modal-price gold-text">RS. 0</p>

                    <div class="mt-4">
                        <span class="options-label">COLOR: <strong id="selectedColorName" class="text-white">...</strong></span>
                        <div class="color-options d-flex flex-wrap gap-2 mt-2" id="modalColorSwatches"></div>
                    </div>

                    <div class="mt-4">
                        <span class="options-label">SIZE</span>
                        <div class="modal-horizontal-sizes mt-2" id="modalSizeSwatches" style="flex-wrap: wrap;"></div>
                    </div>

                    <div class="mt-4">
                        <span class="options-label">QUANTITY</span>
                        <div class="qty-selector mt-2">
                            <button class="qty-btn" id="qtyMinus"><i class="fa-solid fa-minus"></i></button>
                            <input type="number" id="qtyInput" value="1" min="1" max="10" readonly>
                            <button class="qty-btn" id="qtyPlus"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    </div>
                </div>

                <div class="mask-btn-container w-100 mt-4">
                    <span class="mas" id="maskBtnText">ADD TO CART</span>
                    <button id="confirmAddToCart" type="button" name="Hover">ADD TO CART</button>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../ReuseableUI/footer.php'; ?>