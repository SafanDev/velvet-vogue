<?php
// checkout.php - Secure Checkout
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once '../Config/db.php';
require_once '../Config/commerce.php';

// Ensure user is logged in to access checkout
$userID = vv_require_logged_in();

// 1. Fetch Logged-in User Data
$stmtUser = $pdo->prepare("SELECT firstName, lastName, email, phoneNo FROM `user` WHERE userID = ?");
$stmtUser->execute([$userID]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

// 2. Fetch Saved Addresses
$stmtAddr = $pdo->prepare("SELECT addressID as id, addressLabel as label, street, city, postalCode as zip FROM useraddress WHERE userID = ? ORDER BY isDefault DESC");
$stmtAddr->execute([$userID]);
$savedAddresses = $stmtAddr->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch Cart Items directly from Database
$stmtCart = $pdo->prepare("
    SELECT
        p.productName,
        pv.color,
        pv.size,
        (COALESCE(p.salePrice, p.basePrice) + pv.additionalPrice) AS price,
        ci.quantity,
        (SELECT filePath FROM productimage WHERE productID = p.productID AND color = pv.color LIMIT 1) AS img,
        (SELECT filePath FROM productimage WHERE productID = p.productID AND isPrimary = 1 LIMIT 1) AS fallbackImg
    FROM cartitem ci
    JOIN cart c ON ci.cartID = c.cartID
    JOIN productvariant pv ON ci.variantID = pv.variantID
    JOIN product p ON pv.productID = p.productID
    WHERE c.userID = ?
      AND p.isActive = 1
      AND pv.isActive = 1
");
$stmtCart->execute([$userID]);
$cartItems = $stmtCart->fetchAll(PDO::FETCH_ASSOC);

// Redirect back to cart if empty
if (empty($cartItems)) {
    header("Location: cart.php");
    exit;
}

// Calculate Subtotal dynamically
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += ($item['price'] * $item['quantity']);
}

$shipping = 0; // Free Shipping applied

// Recalculate any saved coupon against the current database cart.
$appliedDiscount = 0.0;
$appliedCode = '';
if (!empty($_SESSION['applied_coupon']['couponID'])) {
    $coupon = vv_find_coupon_by_id($pdo, (int) $_SESSION['applied_coupon']['couponID']);
    if ($coupon && vv_coupon_is_available($coupon, (float) $subtotal)) {
        $appliedDiscount = vv_calculate_coupon_discount($coupon, (float) $subtotal);
        $appliedCode = (string) $coupon['code'];
    } else {
        unset($_SESSION['applied_coupon']);
    }
}
$finalTotal = max(0.0, (float) $subtotal + (float) $shipping - $appliedDiscount);
$checkoutIntent = vv_checkout_intent_token($userID);

$page_css = "checkout.css";
$page_js = "checkout.js";
include '../ReuseableUI/header.php';
?>

<main class="checkout-wrapper">
    <input type="hidden" id="checkoutIntentToken" value="<?= vv_e($checkoutIntent) ?>">
    <div class="container-fluid px-lg-5 py-4">

        <div class="checkout-header mb-5 pt-5 gsap-fade-in text-center text-lg-start">
            <h1 class="checkout-title">SECURE CHECKOUT</h1>
            <span class="gold-text tracking-luxury" style="font-size: 0.65rem;">SECURE PAYMENT <i class="fa-solid fa-lock ms-2"></i></span>
        </div>

        <div class="row g-5 justify-content-between">

            <div class="col-lg-7">
                <div class="checkout-flow-container gsap-fade-in">

                    <div class="co-step active" id="step1">
                        <div class="step-header">
                            <span class="step-num">01</span>
                            <h3 class="step-title">YOUR DETAILS</h3>
                            <i class="fa-solid fa-circle-check step-check"></i>
                        </div>

                        <div class="step-body">
                            <div class="row g-4 mt-1 mb-4">
                                <div class="col-12">
                                    <div class="vv-floating-group"><input type="email" class="vv-input" id="coEmail" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required placeholder=" "><label for="coEmail" class="vv-label">EMAIL ADDRESS</label></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="vv-floating-group"><input type="text" class="vv-input" id="coFName" value="<?= htmlspecialchars($user['firstName'] ?? '') ?>" required placeholder=" "><label for="coFName" class="vv-label">FIRST NAME</label></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="vv-floating-group"><input type="text" class="vv-input" id="coLName" value="<?= htmlspecialchars($user['lastName'] ?? '') ?>" required placeholder=" "><label for="coLName" class="vv-label">LAST NAME</label></div>
                                </div>
                                <div class="col-12">
                                    <div class="vv-floating-group"><input type="tel" class="vv-input" id="coPhone" value="<?= htmlspecialchars($user['phoneNo'] ?? '') ?>" required placeholder=" "><label for="coPhone" class="vv-label">CONTACT NUMBER</label></div>
                                </div>
                            </div>
                            <div class="d-flex">
                                <button class="btn-next-step flex-grow-1" onclick="goToStep(2)">
                                    PROCEED TO SHIPPING <i class="fa-solid fa-arrow-right ms-2 icon-arrow"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="co-step" id="step2">
                        <div class="step-header" onclick="goToStep(2)">
                            <span class="step-num">02</span>
                            <h3 class="step-title">SHIPPING ADDRESS</h3>
                            <i class="fa-solid fa-circle-check step-check"></i>
                        </div>
                        <div class="step-body" style="display: none;">

                            <div class="saved-data-list mb-4">
                                <?php if (!empty($savedAddresses)): ?>
                                    <?php foreach ($savedAddresses as $index => $addr): ?>
                                        <label class="vv-minimal-radio <?= $index === 0 ? 'active' : '' ?>">
                                            <input type="radio" name="addressID" value="<?= (int) $addr['id'] ?>" <?= $index === 0 ? 'checked' : '' ?> class="d-none">
                                            <div class="radio-dot me-3"></div>
                                            <div class="radio-content">
                                                <span class="r-title"><?= htmlspecialchars($addr['label'] ?? 'SAVED ADDRESS') ?></span>
                                                <span class="r-desc text-white opacity-75 mt-1"><?= htmlspecialchars($addr['street']) ?>, <?= htmlspecialchars($addr['city']) ?> <?= htmlspecialchars($addr['zip']) ?></span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <label class="vv-minimal-radio <?= empty($savedAddresses) ? 'active' : '' ?>" id="triggerNewAddress">
                                    <input type="radio" name="addressID" value="new" <?= empty($savedAddresses) ? 'checked' : '' ?> class="d-none">
                                    <div class="radio-dot me-3"></div>
                                    <div class="radio-content">
                                        <span class="r-title gold-text">+ ADD NEW ADDRESS</span>
                                    </div>
                                </label>
                            </div>

                            <div id="newAddressForm" style="display: <?= empty($savedAddresses) ? 'block' : 'none' ?>;" class="mb-4">
                                <div class="row g-4">
                                    <div class="col-12">
                                        <div class="vv-floating-group"><input type="text" class="vv-input" required placeholder=" "><label class="vv-label">RECIPIENT NAME</label></div>
                                    </div>
                                    <div class="col-12">
                                        <div class="vv-floating-group"><input type="text" class="vv-input" required placeholder=" "><label class="vv-label">STREET ADDRESS (SUITE/APT)</label></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="vv-floating-group"><input type="text" class="vv-input" required placeholder=" "><label class="vv-label">CITY</label></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="vv-floating-group"><input type="text" class="vv-input" required placeholder=" "><label class="vv-label">POSTAL CODE</label></div>
                                    </div>
                                </div>
                            </div>

                            <div class="shipping-method-box mt-2 mb-4 p-4 border-dark rounded-1">
                                <span class="d-block text-silver tracking-luxury mb-2" style="font-size: 0.65rem;">SELECTED METHOD</span>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-white tracking-luxury" style="font-size: 0.85rem;">STANDARD DELIVERY <span class="ms-2 text-silver text-capitalize font-body" style="letter-spacing:1px; font-size:0.75rem;">(2-4 Days)</span></span>
                                    <span class="gold-text tracking-luxury" style="font-size: 0.75rem;">FREE</span>
                                </div>
                            </div>

                            <div class="d-flex gap-3">
                                <button class="btn-prev-step" onclick="goToStep(1)">BACK</button>
                                <button class="btn-next-step flex-grow-1" onclick="goToStep(3)">
                                    PROCEED TO PAYMENT <i class="fa-solid fa-arrow-right ms-2 icon-arrow"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="co-step" id="step3">
                        <div class="step-header">
                            <span class="step-num">03</span>
                            <h3 class="step-title">PAYMENT METHOD</h3>
                            <i class="fa-solid fa-circle-check step-check"></i>
                        </div>
                        <div class="step-body" style="display: none;">
                            <div class="saved-data-list mb-4">
                                <label class="vv-minimal-radio active">
                                    <input type="radio" name="paymentMethod" value="cod" checked class="d-none">
                                    <div class="radio-dot me-4"></div>
                                    <div class="radio-content">
                                        <span class="r-title">CASH ON DELIVERY (COD)</span>
                                        <span class="r-desc text-silver mt-1">Pay with cash when your order arrives.</span>
                                    </div>
                                </label>
                                <label class="vv-minimal-radio">
                                    <input type="radio" name="paymentMethod" value="new_card" class="d-none" disabled>
                                    <div class="radio-dot me-4"></div>
                                    <div class="radio-content">
                                        <span class="r-title">CREDIT / DEBIT CARD</span>
                                        <span class="r-desc text-silver mt-1">Online card processing is not enabled for this deployment.</span>
                                    </div>
                                </label>
                            </div>

                            <div class="d-flex align-items-center mt-4">
                                <button type="button" class="btn-prev-step me-4" onclick="goToStep(2)"><i class="fa-solid fa-arrow-left me-2"></i> PREVIOUS</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="col-lg-5 col-xl-4 mx-auto mx-lg-0">
                <div class="sticky-summary gsap-fade-in">
                    <div class="summary-ghost-box">
                        <h4 class="summary-title mb-4 border-bottom-dark pb-3">ORDER SUMMARY</h4>

                        <div class="dossier-items mb-4 border-bottom-dark pb-4">
                            <?php foreach ($cartItems as $item): ?>

                                <?php
                                $displayImg = $item['img'] ? $item['img'] : $item['fallbackImg'];
                                $displayImgPath = vv_public_asset_url($displayImg);
                                ?>

                                <div class="d-flex align-items-center mb-3">
                                    <div class="mini-img-wrap me-3">
                                        <img decoding="async" src="<?= vv_e($displayImgPath) ?>" alt="<?= htmlspecialchars($item['productName']) ?>">
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="ci-name"><?= htmlspecialchars($item['productName']) ?></h5>
                                        <span class="ci-variant"><?= htmlspecialchars($item['color'] . ' / ' . $item['size']) ?> &times; <?= (int) $item['quantity'] ?></span>
                                    </div>
                                    <div class="ci-price text-end">RS. <?= number_format($item['price'], 0) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="promo-uplink-module mb-4 pb-4 border-bottom-dark">
                            <span class="d-block text-silver font-monospace mb-3" style="font-size: 0.65rem; letter-spacing: 2px;"><i class="fa-solid fa-tag me-2"></i>PROMO CODE</span>

                            <div id="promoInputState" class="d-flex gap-2" style="<?= $appliedDiscount > 0 ? 'display:none !important;' : '' ?>">
                                <input type="text" id="promoCodeInput" class="vv-input flex-grow-1 font-monospace text-uppercase m-0 py-2" placeholder="ENTER CODE" style="letter-spacing: 3px; font-size: 0.8rem;" autocomplete="off">
                                <button type="button" id="btnApplyPromo" class="btn-outline-gold px-3 py-2" style="font-size: 0.65rem;">APPLY</button>
                            </div>

                            <div id="promoActiveState" class="d-flex justify-content-between align-items-center" style="<?= $appliedDiscount > 0 ? 'display:flex !important;' : 'display:none !important;' ?>">
                                <div>
                                    <i class="fa-solid fa-check text-success me-2"></i>
                                    <span class="text-white font-monospace" style="letter-spacing: 2px; font-size: 0.85rem;" id="activePromoCode"><?= htmlspecialchars($appliedCode) ?></span>
                                </div>
                                <button type="button" id="btnRemovePromo" class="btn-text-danger" style="font-size: 0.65rem;"><i class="fa-solid fa-xmark me-1"></i> REMOVE</button>
                            </div>

                            <div id="promoFeedback" class="mt-2 font-monospace" style="font-size: 0.65rem; letter-spacing: 1px; display: none;"></div>
                        </div>

                        <div class="dossier-math mb-5">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="sl-label">SUBTOTAL</span>
                                <span class="sl-value font-monospace" id="summarySubtotal" data-val="<?= vv_e(number_format((float) $subtotal, 2, '.', '')) ?>">RS. <?= number_format($subtotal, 0) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="sl-label">SHIPPING</span>
                                <span class="sl-value font-monospace text-silver">FREE</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3 pb-3 border-bottom-dark">
                                <span class="sl-label">DISCOUNT</span>
                                <span class="sl-value font-monospace gold-text" id="summaryDiscount">- RS. <?= number_format($appliedDiscount, 0) ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-end mt-2">
                                <span class="st-label">TOTAL</span>
                                <span class="st-value font-monospace" id="summaryTotal">RS. <?= number_format($finalTotal, 0) ?></span>
                            </div>
                        </div>

                        <div class="placeholder-action text-center text-silver tracking-luxury" style="font-size: 0.65rem; padding: 20px; border: 1px dashed rgba(255,255,255,0.1);">
                            COMPLETE PREVIOUS STEPS TO PLACE ORDER
                        </div>

                        <div id="finalConfirmBtn" class="d-none mt-2 w-100">
                            <button type="button" class="btn-premium-solid w-100" id="btnAuthorize">
                                <span id="checkoutMaskText">PLACE ORDER</span>
                                <i class="fa-solid fa-arrow-right ms-2" id="placeOrderArrow" style="transition: transform 0.3s;"></i>
                            </button>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<div class="vv-success-modal" id="successModal">
    <div class="success-receipt text-center">

        <div class="d-flex justify-content-center mb-4">
            <div class="success-ring-anim">
                <i class="fa-solid fa-check"></i>
            </div>
        </div>

        <h2 class="success-title mb-2">ORDER CONFIRMED</h2>
        <p class="text-silver tracking-luxury mb-4" style="font-size: 0.75rem;">YOUR ORDER HAS BEEN PLACED SUCCESSFULLY</p>

        <div class="order-id-display mb-4 p-3 border-dark rounded-1">
            <span class="text-silver d-block mb-2 tracking-luxury" style="font-size: 0.65rem;">ORDER NUMBER</span>
            <span class="gold-text fw-bold" style="font-family: var(--font-body); font-size: 2.2rem; letter-spacing: 3px;" id="successOrderNumber">#VV-PENDING</span>
        </div>

        <div class="receipt-details text-start mb-5">
            <div class="d-flex justify-content-between border-bottom-dark pb-2 mb-2">
                <span class="text-silver tracking-luxury" style="font-size: 0.65rem;">DATE & TIME</span>
                <span class="text-white" style="font-family: var(--font-body); font-size: 0.8rem; letter-spacing: 1px;"><?= date('Y-m-d H:i') ?></span>
            </div>
            <div class="d-flex justify-content-between pb-2">
                <span class="text-silver tracking-luxury" style="font-size: 0.65rem;">STATUS</span>
                <span class="gold-text" style="font-family: var(--font-body); font-size: 0.8rem; letter-spacing: 1px; font-weight: 600;">PROCESSING</span>
            </div>
        </div>

        <p class="text-white opacity-75 mb-5 px-3" style="font-size: 0.85rem; line-height: 1.6; font-family: var(--font-body);">Your order details are available in your dashboard and invoice history.</p>

        <button class="btn-outline-gold w-100" onclick="window.location.href='dashboard.php'">GO TO DASHBOARD</button>
    </div>
</div>

<?php include '../ReuseableUI/footer.php'; ?>