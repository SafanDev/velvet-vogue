<?php
// cart.php - Your Cart
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once '../Config/db.php';

$cartItems = [];
$subtotal = 0.0;
$totalQuantity = 0;

if (isset($_SESSION['userID'])) {
    $userID = (int) $_SESSION['userID'];
    $stmt = $pdo->prepare("
        SELECT
            ci.cartItemID, ci.quantity,
            pv.variantID, pv.color, pv.size, pv.additionalPrice,
            p.productID, p.slug, p.brand, p.productName, p.basePrice, p.salePrice,
            (SELECT filePath FROM productimage WHERE productID = p.productID AND color = pv.color LIMIT 1) AS colorImg,
            (SELECT filePath FROM productimage WHERE productID = p.productID AND isPrimary = 1 LIMIT 1) AS fallbackImg
        FROM cartitem ci
        JOIN cart c ON ci.cartID = c.cartID
        JOIN productvariant pv ON ci.variantID = pv.variantID
        JOIN product p ON pv.productID = p.productID
        WHERE c.userID = ?
          AND p.isActive = 1
          AND pv.isActive = 1
        ORDER BY ci.cartItemID DESC
    ");
    $stmt->execute([$userID]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        $price = (float) ($row['salePrice'] !== null ? $row['salePrice'] : $row['basePrice']) + (float) $row['additionalPrice'];
        $imagePath = $row['colorImg'] ?: $row['fallbackImg'];
        $quantity = max(1, min(10, (int) $row['quantity']));

        $cartItems[] = [
            'id' => (int) $row['cartItemID'],
            'productID' => (int) $row['productID'],
            'slug' => (string) ($row['slug'] ?? ''),
            'brand' => $row['brand'] ?: 'VELVET VOGUE',
            'name' => (string) $row['productName'],
            'color' => (string) $row['color'],
            'size' => (string) $row['size'],
            'price' => $price,
            'quantity' => $quantity,
            'img' => vv_public_asset_url($imagePath),
        ];

        $subtotal += $price * $quantity;
        $totalQuantity += $quantity;
    }
} elseif (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $sessionItems = array_slice($_SESSION['cart'], 0, 100, true);
    $variantIds = [];
    foreach ($sessionItems as $item) {
        $variantId = (int) ($item['variant_id'] ?? 0);
        if ($variantId > 0) {
            $variantIds[$variantId] = $variantId;
        }
    }

    $variants = [];
    if ($variantIds) {
        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $stmt = $pdo->prepare("
            SELECT
                pv.variantID, pv.productID, pv.additionalPrice, pv.stockCount,
                p.slug, p.brand, p.productName, p.basePrice, p.salePrice,
                (SELECT filePath FROM productimage WHERE productID = p.productID AND color = pv.color LIMIT 1) AS colorImg,
                (SELECT filePath FROM productimage WHERE productID = p.productID AND isPrimary = 1 LIMIT 1) AS fallbackImg
            FROM productvariant pv
            JOIN product p ON pv.productID = p.productID
            WHERE pv.variantID IN ($placeholders)
              AND pv.isActive = 1
              AND p.isActive = 1
        ");
        $stmt->execute(array_values($variantIds));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $variants[(int) $row['variantID']] = $row;
        }
    }

    foreach ($sessionItems as $key => $item) {
        $variantId = (int) ($item['variant_id'] ?? 0);
        $productId = (int) ($item['product_id'] ?? 0);
        $row = $variants[$variantId] ?? null;
        if (!$row || (int) $row['productID'] !== $productId || (int) $row['stockCount'] < 1) {
            continue;
        }

        $price = (float) ($row['salePrice'] !== null ? $row['salePrice'] : $row['basePrice']) + (float) $row['additionalPrice'];
        $quantity = max(1, min(10, (int) ($item['quantity'] ?? 1), (int) $row['stockCount']));
        $imagePath = $row['colorImg'] ?: $row['fallbackImg'];

        $cartItems[] = [
            'id' => (string) $key,
            'productID' => $productId,
            'slug' => (string) ($row['slug'] ?? ''),
            'brand' => $row['brand'] ?: 'VELVET VOGUE',
            'name' => (string) $row['productName'],
            'color' => (string) ($item['color'] ?? ''),
            'size' => (string) ($item['size'] ?? ''),
            'price' => $price,
            'quantity' => $quantity,
            'img' => vv_public_asset_url($imagePath),
        ];

        $subtotal += $price * $quantity;
        $totalQuantity += $quantity;
    }
}

$shipping = 0;
$total = $subtotal + $shipping;

$page_css = "cart.css";
$page_js = "cart.js";
include '../ReuseableUI/header.php';
?>

<input type="hidden" id="initialCartCount" value="<?= (int) $totalQuantity ?>">

<main class="cart-wrapper">
    <div class="container-fluid px-3 px-md-5 py-4">

        <div class="cart-header mb-5 pt-4 gsap-fade-in">
            <h1 class="cart-title">YOUR CART</h1>
            <span class="cart-count gold-text" id="dossierCountDisplay">
                <i class="fa-solid fa-bag-shopping me-2"></i> <span id="dossierNum"><?= (int) $totalQuantity ?></span> ITEMS
            </span>
        </div>

        <?php if(empty($cartItems)): ?>
            <div class="empty-cart-state text-center gsap-fade-in">
                <div class="empty-icon-wrap mb-4">
                    <i class="fa-solid fa-bag-shopping"></i>
                </div>
                <h3 class="text-white tracking-luxury mb-3">YOUR CART IS EMPTY</h3>
                <p class="mb-5 text-silver" style="font-size: 0.9rem; font-family: var(--font-body);">Discover our latest arrivals and add items to your cart.</p>
                <a href="shop.php" class="btn-shop-empty">SHOP NOW</a>
            </div>
        <?php else: ?>
            <div class="row g-5">

                <div class="col-lg-8">
                    <div class="cart-items-container">

                        <div class="cart-labels d-none d-md-flex mb-3 pb-2 gsap-fade-in border-bottom-dark">
                            <div class="col-6"><span class="c-label text-silver">ITEM</span></div>
                            <div class="col-3 text-center"><span class="c-label text-silver">QUANTITY</span></div>
                            <div class="col-2 text-end"><span class="c-label text-silver">TOTAL</span></div>
                            <div class="col-1"></div>
                        </div>

                        <?php foreach($cartItems as $item): ?>
                            <div class="cart-item-card d-flex align-items-center mb-4 gsap-cart-item" data-cart-id="<?= vv_e((string) $item['id']) ?>" data-price="<?= vv_e(number_format((float) $item['price'], 2, '.', '')) ?>">

                                <div class="col-12 col-md-6 d-flex align-items-center mb-3 mb-md-0">
                                    <div class="ci-img-wrap me-4">
                                        <img decoding="async" src="<?= vv_e($item['img']) ?>" alt="<?= vv_e($item['name']) ?>" loading="lazy">
                                    </div>
                                    <div class="ci-details">
                                        <span class="ci-brand text-gold"><?= vv_e($item['brand']) ?></span>
                                        <h4 class="ci-name mt-1 mb-2"><a href="<?= vv_e($item['slug'] !== '' ? 'product_detail.php?slug=' . rawurlencode($item['slug']) : 'product_detail.php?id=' . $item['productID']) ?>"><?= vv_e($item['name']) ?></a></h4>
                                        <span class="ci-variant text-silver">COLOR: <span class="text-white"><?= vv_e($item['color']) ?></span> <span class="sep">|</span> SIZE: <span class="text-white"><?= vv_e($item['size']) ?></span></span>
                                        <div class="ci-unit-price mt-2">RS. <?= number_format($item['price'], 0) ?></div>
                                    </div>
                                </div>

                                <div class="col-6 col-md-3 d-flex justify-content-start justify-content-md-center">
                                    <div class="qty-selector cart-qty-selector">
                                        <button class="qty-btn btn-qty-minus"><i class="fa-solid fa-minus"></i></button>
                                        <input type="number" class="qty-input" value="<?= (int) $item['quantity'] ?>" min="1" max="10" readonly>
                                        <button class="qty-btn btn-qty-plus"><i class="fa-solid fa-plus"></i></button>
                                    </div>
                                </div>

                                <div class="col-4 col-md-2 text-end">
                                    <span class="ci-line-total gold-text">RS. <?= number_format($item['price'] * $item['quantity'], 0) ?></span>
                                </div>

                                <div class="col-2 col-md-1 text-end">
                                    <button class="btn-remove-item" title="Remove Item">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>

                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="summary-box gsap-fade-in" id="orderSummaryBox">
                        <h4 class="summary-title mb-4 pb-3">ORDER SUMMARY</h4>

                        <div class="summary-line d-flex justify-content-between mb-3 mt-4">
                            <span class="sl-label text-silver">SUBTOTAL</span>
                            <span class="sl-value" id="cartSubtotal">RS. <?= number_format($subtotal, 0) ?></span>
                        </div>

                        <div class="summary-line d-flex justify-content-between mb-5 pb-5 border-bottom-dark">
                            <span class="sl-label text-silver">SHIPPING</span>
                            <span class="sl-value gold-text" style="font-weight: 600; letter-spacing: 1px;">FREE</span>
                        </div>

                        <div class="summary-total d-flex justify-content-between mb-5">
                            <span class="st-label">TOTAL</span>
                            <span class="st-value gold-text" id="cartTotal">RS. <?= number_format($total, 0) ?></span>
                        </div>

                        <button class="vv-checkout-btn button w-100 mt-2" onclick="window.location.href='checkout.php'">
                            <span class="cap">
                                <span class="text">CHECKOUT</span>
                            </span>
                        </button>

                    </div>
                </div>

            </div>
        <?php endif; ?>

    </div>
</main>

<?php include '../ReuseableUI/footer.php'; ?>