<?php
// product_detail.php - Velvet Vogue
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once '../Config/db.php';

$productSlug = trim((string) ($_GET['slug'] ?? ''));
$productId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (strlen($productSlug) > 180 || ($productSlug !== '' && !preg_match('/^[A-Za-z0-9-]+$/', $productSlug))) {
    $productSlug = '';
}

// Older links used the numeric product ID in the slug parameter.
if ($productId === false && ctype_digit($productSlug)) {
    $productId = (int) $productSlug;
    $productSlug = '';
}

$product = null;
$colorDataArray = [];
$availableSizes = [];
$variantMatrix = [];
$defaultImg = '';

// Initialize Review Variables to prevent errors
$reviews = [];
$totalReviews = 0;
$avgRating = 0;

// Fetch User's Wishlist for initial button state
$userWishlist = [];
if (isset($_SESSION['userID'])) {
    $wStmt = $pdo->prepare("SELECT productID FROM wishlist WHERE userID = ?");
    $wStmt->execute([$_SESSION['userID']]);
    $userWishlist = $wStmt->fetchAll(PDO::FETCH_COLUMN);
} else if (isset($_SESSION['wishlist'])) {
    $userWishlist = $_SESSION['wishlist'];
}

if ($productSlug !== '' || $productId !== false) {
    $lookupColumn = $productSlug !== '' ? 'p.slug' : 'p.productID';
    $lookupValue = $productSlug !== '' ? $productSlug : (int) $productId;

    $stmt = $pdo->prepare("
        SELECT p.*, c.categoryName, c.slug AS categorySlug
        FROM product p
        LEFT JOIN category c ON p.categoryID = c.categoryID
        WHERE {$lookupColumn} = ? AND p.isActive = 1
        LIMIT 1
    ");
    $stmt->execute([$lookupValue]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $productID = $product['productID'];

        // 2. Fetch Colors
        $colorStmt = $pdo->prepare("
            SELECT DISTINCT pv.color,
            (SELECT filePath FROM productimage WHERE productID = pv.productID AND color = pv.color LIMIT 1) as img
            FROM productvariant pv
            WHERE pv.productID = ? AND pv.isActive = 1 AND pv.stockCount > 0
        ");
        $colorStmt->execute([$productID]);
        $variants = $colorStmt->fetchAll(PDO::FETCH_ASSOC);

        $colorMap = [
            'Black' => '#000000', 'White' => '#ffffff', 'Grey' => '#808080', 'Beige' => '#F5F5DC',
            'Navy' => '#000080', 'Blue' => '#0000FF', 'Red' => '#FF0000', 'Burgundy' => '#800020',
            'Pink' => '#FFC0CB', 'Purple' => '#800080', 'Green' => '#008000', 'Olive' => '#808000',
            'Brown' => '#8B4513', 'Yellow' => '#FFFF00', 'Gold' => '#D4AF37', 'Silver' => '#C0C0C0'
        ];

        foreach ($variants as $v) {
            $cName = $v['color'] ?: 'Standard';
            $cHex = isset($colorMap[$cName]) ? $colorMap[$cName] : '#222222';
            $cImg = $v['img'] ? vv_public_asset_url((string) $v['img'], '') : '';
            $colorDataArray[$cName] = ['hex' => $cHex, 'img' => $cImg];
        }

        // 3. Fetch Sizes
        $sizeStmt = $pdo->prepare("SELECT DISTINCT size FROM productvariant WHERE productID = ? AND isActive = 1 AND stockCount > 0 ORDER BY FIELD(size, 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'OS', 'N/A')");
        $sizeStmt->execute([$productID]);
        $availableSizes = $sizeStmt->fetchAll(PDO::FETCH_COLUMN);

        $matrixStmt = $pdo->prepare("SELECT color, size, stockCount FROM productvariant WHERE productID = ? AND isActive = 1 AND stockCount > 0 ORDER BY variantID");
        $matrixStmt->execute([$productID]);
        foreach ($matrixStmt->fetchAll(PDO::FETCH_ASSOC) as $variantRow) {
            $matrixColor = trim((string) ($variantRow['color'] ?? '')) ?: 'Standard';
            $matrixSize = trim((string) ($variantRow['size'] ?? '')) ?: 'Standard';
            $variantMatrix[$matrixColor] ??= [];
            $variantMatrix[$matrixColor][] = [
                'size' => $matrixSize,
                'stock' => max(0, (int) ($variantRow['stockCount'] ?? 0)),
            ];
        }

        // 4. Default Image
        $imgStmt = $pdo->prepare("SELECT filePath FROM productimage WHERE productID = ? AND isPrimary = 1 LIMIT 1");
        $imgStmt->execute([$productID]);
        $primaryImg = $imgStmt->fetchColumn();

        if (!empty($colorDataArray)) {
            $firstColorData = reset($colorDataArray);
            $defaultImg = !empty($firstColorData['img']) ? $firstColorData['img'] : ($primaryImg ? vv_public_asset_url((string) $primaryImg) : '');
        } else {
            $defaultImg = $primaryImg ? vv_public_asset_url((string) $primaryImg) : '';
        }

        // Only approved reviews are visible on the storefront.
        $reviewStmt = $pdo->prepare("
            SELECT r.*, u.firstName, SUBSTRING(u.lastName, 1, 1) as lastInitial
            FROM review r
            JOIN `user` u ON r.userID = u.userID
            WHERE r.productID = ? AND r.isApproved = 1
            ORDER BY r.createdAt DESC
            LIMIT 100
        ");
        $reviewStmt->execute([$productID]);
        $reviews = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalReviews = count($reviews);
        if ($totalReviews > 0) {
            $sum = array_sum(array_column($reviews, 'rating'));
            $avgRating = number_format($sum / $totalReviews, 1);
        }
    }
}

if (empty($colorDataArray)) $colorDataArray = ['Standard' => ['hex' => '#222', 'img' => '']];
if (empty($availableSizes)) $availableSizes = ['Standard'];
if (empty($variantMatrix)) $variantMatrix = [];

$page_css = "product_detail.css";
$page_js = "product_detail.js";
include '../ReuseableUI/header.php';

if (!$product) {
    echo '<div class="container-fluid py-5 text-center" style="min-height: 60vh; display: flex; align-items: center; justify-content: center;"><h2 style="font-family: var(--font-heading); color: var(--color-gold-metallic);">PRODUCT NOT FOUND</h2></div>';
    include '../ReuseableUI/footer.php';
    exit;
}

// Initial Wishlist State
$isWishlisted = in_array((int) $productID, array_map('intval', $userWishlist), true);
?>

<main class="pd-wrapper" data-product-id="<?= (int) $product['productID'] ?>" data-variants="<?= vv_e(json_encode($variantMatrix, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) ?>">

    <section class="pd-hero">
        <div class="pd-image-col">
            <div class="vv-breadcrumbs d-lg-none px-4 pt-4 pb-2 w-100 position-absolute top-0 start-0 z-3">
                <a href="shop.php">Shop</a> <span class="sep">/</span>
                <a href="shop.php?category=<?= rawurlencode((string) ($product['categorySlug'] ?? '')) ?>"><?= htmlspecialchars($product['categoryName']) ?></a>
            </div>

            <div class="pd-main-img-container gsap-img-reveal" id="imgPanContainer">
                <img decoding="async" fetchpriority="high" src="<?= vv_e($defaultImg ?: '../Assets/images/fallback.webp') ?>" id="pdMainImage" class="pd-main-img" alt="<?= htmlspecialchars($product['productName']) ?>">
                <div class="img-loader" id="pdImgLoader"><i class="fa-solid fa-circle-notch fa-spin"></i></div>
            </div>
        </div>

        <div class="pd-info-col">
            <div class="pd-info-content">

                <div class="vv-breadcrumbs d-none d-lg-flex mb-3">
                    <a href="shop.php">Shop</a> <span class="sep">/</span>
                    <a href="shop.php?category=<?= rawurlencode((string) ($product['categorySlug'] ?? '')) ?>"><?= htmlspecialchars($product['categoryName']) ?></a> <span class="sep">/</span>
                    <span class="current"><?= htmlspecialchars($product['productName']) ?></span>
                </div>

                <div class="pd-header mb-4">
                    <span class="pd-brand animate-magnetic" style="display:inline-block;"><?= htmlspecialchars($product['brand'] ?? 'VELVET VOGUE') ?></span>
                    <h1 class="pd-title mt-2"><?= htmlspecialchars($product['productName']) ?></h1>

                    <div class="pd-rating-mini d-flex align-items-center mt-2 mb-3">
                        <div class="stars gold-text" style="font-size: 0.75rem;">
                            <?php
                                $fullStars = floor($avgRating);
                                $halfStar = ($avgRating - $fullStars) >= 0.5 ? 1 : 0;
                                $emptyStars = 5 - $fullStars - $halfStar;
                                for($i=0; $i<$fullStars; $i++) echo '<i class="fa-solid fa-star"></i>';
                                if($halfStar) echo '<i class="fa-solid fa-star-half-stroke"></i>';
                                for($i=0; $i<$emptyStars; $i++) echo '<i class="fa-regular fa-star"></i>';
                            ?>
                        </div>
                        <a href="#reviewsSection" class="ms-2 rating-link text-white"><?= $avgRating > 0 ? $avgRating : '0.0' ?> (<?= $totalReviews ?> Reviews)</a>
                    </div>

                    <div class="pd-price-wrap mt-2">
                        <?php if($product['salePrice']): ?>
                            <del class="pd-price-old text-silver">Rs. <?= number_format($product['basePrice'], 0) ?></del>
                            <span class="pd-price gold-text">Rs. <?= number_format($product['salePrice'], 0) ?></span>
                        <?php else: ?>
                            <span class="pd-price gold-text">Rs. <?= number_format($product['basePrice'], 0) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pd-options mb-4 border-top-dark pt-4">
                    <div class="mb-4">
                        <?php $firstColorName = array_key_first($colorDataArray); ?>
                        <span class="options-label text-silver">COLOR: <strong id="pdSelectedColor" class="text-white"><?= htmlspecialchars(strtoupper($firstColorName)) ?></strong></span>
                        <div class="color-options d-flex flex-wrap gap-3 mt-3" id="pdColorSwatches">
                            <?php
                            $first = true;
                            foreach($colorDataArray as $name => $data):
                                $hex = is_array($data) ? $data['hex'] : $data;
                                $imgUrl = is_array($data) ? $data['img'] : '';
                            ?>
                                <button class="color-swatch animate-magnetic <?= $first ? 'active' : '' ?>" style="background: <?= htmlspecialchars($hex) ?>" data-color="<?= htmlspecialchars($name) ?>" data-img="<?= htmlspecialchars($imgUrl) ?>" title="<?= htmlspecialchars($name) ?>"></button>
                            <?php $first = false; endforeach; ?>
                        </div>
                    </div>

                    <div class="row g-4 mb-3">
                        <div class="col-sm-6 col-12">
                            <span class="options-label text-silver">SIZE</span>
                            <div class="modal-horizontal-sizes mt-2" id="pdSizeSelectors" style="flex-wrap: wrap;">
                                <?php
                                $firstSize = true;
                                foreach($availableSizes as $size):
                                ?>
                                    <button class="size-btn-horiz animate-magnetic <?= $firstSize ? 'active' : '' ?>"><?= htmlspecialchars($size) ?></button>
                                <?php $firstSize = false; endforeach; ?>
                            </div>
                        </div>
                        <div class="col-sm-6 col-12">
                            <span class="options-label text-silver">QUANTITY</span>
                            <div class="qty-selector mt-2">
                                <button class="qty-btn animate-magnetic" id="pdQtyMinus"><i class="fa-solid fa-minus"></i></button>
                                <input type="number" id="pdQtyInput" value="1" min="1" max="10" readonly>
                                <button class="qty-btn animate-magnetic" id="pdQtyPlus"><i class="fa-solid fa-plus"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pd-actions d-flex flex-column flex-sm-row gap-3 mt-4">
                    <div class="mask-btn-container w-100">
                        <span class="mas" id="pdMaskBtnText">ADD TO CART</span>
                        <button id="pdAddToCartBtn" type="button">ADD TO CART</button>
                    </div>

                    <button class="pd-wishlist-btn animate-magnetic mx-auto mx-sm-0 <?= $isWishlisted ? 'active' : '' ?>" onclick="toggleWish(this, <?= (int) $product['productID'] ?>)" style="<?= $isWishlisted ? 'color: var(--color-gold-metallic); border-color: var(--color-gold-metallic);' : '' ?>">
                        <i class="<?= $isWishlisted ? 'fa-solid' : 'fa-regular' ?> fa-heart icon-heart"></i>
                    </button>
                </div>

            </div>
        </div>
    </section>

    <section class="pd-secondary border-top-dark" id="reviewsSection">
        <div class="container-fluid px-3 px-md-5 py-5">

            <div class="row g-5 mt-2">
                <div class="col-lg-7 gsap-scroll-reveal">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h4 class="gold-text m-0" style="font-family: var(--font-heading); letter-spacing: 2px;">CUSTOMER REVIEWS</h4>
                        <span class="rating-text text-white"><?= $avgRating > 0 ? $avgRating : '0.0' ?> / 5.0 (<?= $totalReviews ?>)</span>
                    </div>

                    <div class="pd-review-list">
                        <?php if(empty($reviews)): ?>
                            <p class="text-silver">No reviews yet for this product. Be the first to leave one after purchasing!</p>
                        <?php else: ?>
                            <?php foreach($reviews as $rev): ?>
                                <div class="pd-review-card mb-4 position-relative overflow-hidden">
                                    <i class="fa-solid fa-quote-right quote-watermark"></i>
                                    <div class="position-relative z-1">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <span class="rev-author text-white"><?= htmlspecialchars($rev['firstName'] . ' ' . $rev['lastInitial']) ?>. <i class="fa-solid fa-circle-check gold-text ms-1" style="font-size: 0.6rem;"></i></span>
                                            <div class="stars gold-text" style="font-size: 0.6rem;">
                                                <?php
                                                    $rVal = (int)$rev['rating'];
                                                    for($i=1; $i<=5; $i++) {
                                                        if($i <= $rVal) echo '<i class="fa-solid fa-star"></i>';
                                                        else echo '<i class="fa-regular fa-star"></i>';
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                        <?php if(!empty($rev['comment'])): ?>
                                            <p class="rev-text text-silver"><?= htmlspecialchars($rev['comment']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-5 gsap-scroll-reveal">
                    <h4 class="gold-text mb-4" style="font-family: var(--font-heading); letter-spacing: 2px;">SHIPPING & RETURNS</h4>

                    <div class="pd-logistics-grid">
                        <div class="logistics-item hover-box">
                            <i class="fa-solid fa-box-open log-icon text-silver"></i>
                            <div class="logistics-info ms-4">
                                <h6 class="text-white">SIGNATURE PACKAGING</h6>
                                <p class="text-silver">Arrives securely in a rigid Velvet Vogue premium box.</p>
                            </div>
                        </div>
                        <div class="logistics-item hover-plane mt-4">
                            <i class="fa-solid fa-plane-up log-icon text-silver"></i>
                            <div class="logistics-info ms-4">
                                <h6 class="text-white">GLOBAL EXPRESS</h6>
                                <p class="text-silver">Complimentary 2-4 business days worldwide delivery.</p>
                            </div>
                        </div>
                        <div class="logistics-item hover-rotate mt-4">
                            <i class="fa-solid fa-rotate-left log-icon text-silver"></i>
                            <div class="logistics-info ms-4">
                                <h6 class="text-white">FREE RETURNS</h6>
                                <p class="text-silver">14-day return policy. Security tag must remain attached.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

</main>

<?php include '../ReuseableUI/footer.php'; ?>