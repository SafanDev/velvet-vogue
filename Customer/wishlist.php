<?php
// wishlist.php - Velvet Vogue Wishlist
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once '../Config/db.php';

$wishlistGarments = [];

// SCENARIO A: AUTHORIZED USER (DATABASE)
if (isset($_SESSION['userID'])) {
    $userID = $_SESSION['userID'];

    $stmt = $pdo->prepare("
        SELECT
            p.productID, p.slug, p.brand, p.productName, p.basePrice, p.salePrice,
            c.categoryName,
            (SELECT filePath FROM productimage WHERE productID = p.productID AND isPrimary = 1 LIMIT 1) as img
        FROM wishlist w
        JOIN product p ON w.productID = p.productID
        LEFT JOIN category c ON p.categoryID = c.categoryID
        WHERE w.userID = ? AND p.isActive = 1
        ORDER BY w.addedAt DESC
    ");
    $stmt->execute([$userID]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        $price = $row['salePrice'] ? $row['salePrice'] : $row['basePrice'];

        $wishlistGarments[] = [
            'productID' => (int) $row['productID'],
            'slug' => (string) ($row['slug'] ?? ''),
            'brand' => $row['brand'] ?: 'VELVET VOGUE',
            'productName' => $row['productName'],
            'category' => $row['categoryName'] ?: 'Archive',
            'price' => $price,
            'img' => vv_public_asset_url($row['img'] ?? null)
        ];
    }
}
// SCENARIO B: GUEST USER (SESSION)
else {
    if (isset($_SESSION['wishlist']) && !empty($_SESSION['wishlist'])) {
        $sessionWishlist = array_slice(array_values(array_unique(array_map('intval', $_SESSION['wishlist']))), 0, 100);
        // Prepare placeholders for the IN clause
        $inQuery = implode(',', array_fill(0, count($sessionWishlist), '?'));

        $stmt = $pdo->prepare("
            SELECT
                p.productID, p.slug, p.brand, p.productName, p.basePrice, p.salePrice,
                c.categoryName,
                (SELECT filePath FROM productimage WHERE productID = p.productID AND isPrimary = 1 LIMIT 1) as img
            FROM product p
            LEFT JOIN category c ON p.categoryID = c.categoryID
            WHERE p.productID IN ($inQuery) AND p.isActive = 1
        ");
        $stmt->execute($sessionWishlist);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $row) {
            $price = $row['salePrice'] ? $row['salePrice'] : $row['basePrice'];

            $wishlistGarments[] = [
                'productID' => (int) $row['productID'],
                'slug' => (string) ($row['slug'] ?? ''),
                'brand' => $row['brand'] ?: 'VELVET VOGUE',
                'productName' => $row['productName'],
                'category' => $row['categoryName'] ?: 'Archive',
                'price' => $price,
                'img' => vv_public_asset_url($row['img'] ?? null)
            ];
        }
    }
}

// Calculate Total Cart Count for the Header Badge
$cartCount = 0;
if (isset($_SESSION['userID'])) {
    $cartStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cartitem ci JOIN cart c ON ci.cartID = c.cartID WHERE c.userID = ?");
    $cartStmt->execute([$_SESSION['userID']]);
    $cartCount = (int)$cartStmt->fetchColumn();
} elseif (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += $item['quantity'];
    }
}

$page_css = "wishlist.css";
$page_js = "wishlist.js";
include '../ReuseableUI/header.php';
?>

<input type="hidden" id="initialWishlistCount" value="<?= count($wishlistGarments) ?>">
<input type="hidden" id="initialCartCount" value="<?= (int) $cartCount ?>">

<main class="gallery-wrapper">
    <div class="cinematic-grain"></div>

    <div class="container-fluid px-3 px-lg-5 py-5">

        <div class="gallery-header mb-5 pt-5 text-center gsap-fade-in">
            <span class="gold-text tracking-luxury d-block mb-3" style="font-size: 0.75rem;">YOUR SAVED ITEMS</span>
            <h1 class="gallery-title">MY WISHLIST</h1>
            <p class="text-white tracking-luxury mt-4 mx-auto" style="max-width: 550px; font-size: 0.8rem; line-height: 1.8;">
                Your personal collection of saved items. <br>Keep them here until you are ready to check out.
            </p>
            <div class="telemetry-counter mt-4">
                <span id="galleryCount" class="gold-text"><?= count($wishlistGarments) ?></span> ITEMS SAVED
            </div>
        </div>

        <?php if(empty($wishlistGarments)): ?>
            <div class="empty-gallery-state text-center gsap-fade-in">
                <div class="empty-icon-wrap mb-4 mx-auto">
                    <i class="fa-solid fa-heart" style="color: rgba(255,255,255,0.2);"></i>
                </div>
                <h3 class="text-white tracking-luxury mb-3" style="font-family: var(--font-heading); font-size: 1.8rem;">YOUR WISHLIST IS EMPTY</h3>
                <p class="mb-5 text-silver" style="font-size: 0.9rem; font-family: var(--font-body);">Explore our shop to start saving your favorite pieces.</p>
                <a href="shop.php" class="btn-outline-gold">EXPLORE SHOP</a>
            </div>
        <?php else: ?>
            <div class="gallery-grid row g-5" id="galleryGrid">

                <?php foreach($wishlistGarments as $garment): ?>
                    <?php $productUrl = $garment['slug'] !== '' ? 'product_detail.php?slug=' . rawurlencode($garment['slug']) : 'product_detail.php?id=' . $garment['productID']; ?>
                    <div class="col-12 col-md-6 col-lg-4 gallery-item-wrapper" data-id="<?= (int) $garment['productID'] ?>" data-product-url="<?= vv_e($productUrl) ?>">
                        <article class="g-card">

                            <button class="btn-remove-garment" title="Remove from Wishlist">
                                <i class="fa-solid fa-xmark"></i>
                            </button>

                            <div class="g-img-wrap">
                                <div class="fabric-overlay fabric-top"></div>
                                <div class="fabric-overlay fabric-bottom"></div>

                                <a href="<?= vv_e($productUrl) ?>"><img decoding="async" src="<?= vv_e($garment['img']) ?>" class="g-artifact-img" alt="<?= vv_e($garment['productName']) ?>" loading="lazy"></a>

                                <div class="g-hover-actions">
                                    <div class="mask-btn-container w-100 mx-4">
                                        <span class="mas">VIEW OPTIONS</span>
                                        <button class="btn-move-to-bag" data-id="<?= (int) $garment['productID'] ?>" data-product-url="<?= vv_e($productUrl) ?>" type="button">VIEW PRODUCT</button>
                                    </div>
                                </div>
                            </div>

                            <div class="g-details mt-4 text-center">
                                <span class="g-brand"><?= vv_e($garment['brand']) ?></span>
                                <h3 class="g-name mt-2 mb-1">
                                    <a href="<?= vv_e($productUrl) ?>"><?= vv_e($garment['productName']) ?></a>
                                </h3>
                                <div class="d-flex justify-content-center align-items-center gap-3 mt-2">
                                    <span class="g-category"><?= vv_e($garment['category']) ?></span>
                                    <span class="sep" style="color: #444;">|</span>
                                    <span class="g-price gold-text">RS. <?= number_format($garment['price'], 0) ?></span>
                                </div>
                            </div>

                        </article>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php endif; ?>

    </div>
</main>

<?php include '../ReuseableUI/footer.php'; ?>