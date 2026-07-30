<?php
// review.php - Product Review Form
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once '../Config/db.php';

$userId = vv_require_logged_in();

$orderItemID = isset($_GET['order_item']) ? (int)$_GET['order_item'] : (isset($_GET['item']) ? (int)$_GET['item'] : 0);
$itemData = null;

if ($orderItemID > 0) {
    // Fetch real order item and product data securely
    $stmt = $pdo->prepare("
        SELECT oi.*, p.productID, p.productName, o.orderNumber,
        (SELECT filePath FROM productimage WHERE productID = p.productID AND isPrimary = 1 LIMIT 1) as imageURL
        FROM orderitem oi
        JOIN `order` o ON oi.orderID = o.orderID
        JOIN productvariant pv ON oi.variantID = pv.variantID
        JOIN product p ON pv.productID = p.productID
        WHERE oi.orderItemID = ? AND o.userID = ? AND o.orderStatus = 'delivered'
    ");
    $stmt->execute([$orderItemID, $userId]);
    $itemData = $stmt->fetch(PDO::FETCH_ASSOC);
}

$page_css = "review.css";
$page_js = "review.js";
include '../ReuseableUI/header.php';
?>

<main class="review-wrapper position-relative">
    <div class="cinematic-grain"></div>
    <div class="transit-grid-bg opacity-25"></div>

    <div class="container py-5 position-relative z-2 d-flex align-items-center justify-content-center" style="min-height: 85vh;">

        <?php if (!$itemData): ?>
            <div class="review-terminal w-100 text-center py-5" style="max-width: 600px;">
                <i class="fa-solid fa-triangle-exclamation text-silver mb-3" style="font-size: 3rem;"></i>
                <h2 class="text-white mb-3" style="font-family: var(--font-heading);">ITEM NOT FOUND</h2>
                <p class="text-silver mb-4">This product is either not eligible for a review or could not be found in your order history.</p>
                <a href="dashboard.php" class="btn-outline-gold px-4 py-2 d-inline-block">RETURN TO DASHBOARD</a>
            </div>
        <?php else: ?>
            <div class="review-terminal gsap-fade-in w-100" style="max-width: 1050px;">

                <div class="hud-corner top-left"></div>
                <div class="hud-corner top-right"></div>
                <div class="hud-corner bottom-left"></div>
                <div class="hud-corner bottom-right"></div>

                <div class="row g-0 h-100">

                    <div class="col-md-6 col-lg-7 review-left p-4 p-lg-5 d-flex flex-column">
                        <div class="terminal-scan-line"></div>

                        <a href="dashboard.php" class="btn-text-silver mb-4 d-inline-block" style="font-size: 0.65rem;"><i class="fa-solid fa-arrow-left me-2"></i> CANCEL REVIEW</a>

                        <div class="mb-5 border-bottom-dark pb-4 d-flex flex-column flex-sm-row align-items-sm-center gap-4">
                            <div class="product-image-wrapper">
                                <img decoding="async" src="<?= vv_e(vv_public_asset_url($itemData['imageURL'] ?? null)) ?>" alt="Product Image" class="product-image">
                            </div>
                            <div>
                                <span class="gold-text tracking-luxury mb-2 d-block" style="font-size: 0.65rem;">PRODUCT EVALUATION</span>
                                <h2 class="text-white text-uppercase mb-3 decode-text" style="font-family: var(--font-heading); font-size: 1.5rem; letter-spacing: 2px;">
                                    <?= htmlspecialchars($itemData['productNameSnap']) ?>
                                </h2>
                                <div class="spec-line"><span class="text-silver">COLOR:</span> <span class="text-white font-monospace ms-2 decode-text"><?= htmlspecialchars($itemData['colorSnap']) ?></span></div>
                                <div class="spec-line"><span class="text-silver">SIZE:</span> <span class="text-white font-monospace ms-2 decode-text"><?= htmlspecialchars($itemData['sizeSnap']) ?></span></div>
                                <div class="spec-line mb-0"><span class="text-silver">ORDER ID:</span> <span class="gold-text font-monospace ms-2 decode-text">#<?= htmlspecialchars($itemData['orderNumber']) ?></span></div>
                            </div>
                        </div>

                        <form id="reviewForm" method="post" class="flex-grow-1 d-flex flex-column justify-content-between">
                    <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">
                            <input type="hidden" name="productID" value="<?= htmlspecialchars($itemData['productID'] ?? 0) ?>">
                            <input type="hidden" name="orderItemID" value="<?= htmlspecialchars($itemData['orderItemID']) ?>">

                            <div>
                                <h3 class="text-white tracking-luxury mb-4" style="font-size: 1.2rem;">YOUR RATING</h3>

                                <div class="rating-matrix mb-5">
                                    <span class="d-block text-silver mb-3" style="font-size: 0.75rem; letter-spacing: 3px; font-weight: 600;">SELECT STARS</span>

                                    <div class="star-rating" id="starRatingSystem">
                                        <input type="radio" id="star5" name="rating" value="5" /><label for="star5" title="Excellent"></label>
                                        <input type="radio" id="star4" name="rating" value="4" /><label for="star4" title="Good"></label>
                                        <input type="radio" id="star3" name="rating" value="3" /><label for="star3" title="Average"></label>
                                        <input type="radio" id="star2" name="rating" value="2" /><label for="star2" title="Poor"></label>
                                        <input type="radio" id="star1" name="rating" value="1" /><label for="star1" title="Very Poor"></label>
                                    </div>
                                    <div class="clearfix"></div>
                                </div>

                                <div class="vv-floating-group mb-5">
                                    <textarea name="comment" class="vv-input" placeholder=" " rows="1" id="reviewComment" style="min-height: 50px; resize: none;"></textarea>
                                    <label class="vv-label">WRITTEN REVIEW (OPTIONAL)</label>
                                </div>
                            </div>

                            <div class="text-center pt-4 mt-auto w-100">
                                <button type="submit" class="btn-outline-gold px-5 py-3 w-100" style="font-size: 0.85rem; max-width: 400px; margin: 0 auto; display: block;" id="btnSubmitReview">
                                    SUBMIT REVIEW
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="col-md-6 col-lg-5 review-right p-4 p-lg-5 position-relative">
                        <div class="right-panel-grid"></div>
                        <div class="d-flex flex-column align-items-center justify-content-center w-100 h-100 position-relative z-2">

                            <div class="reaction-core state-0" id="reactionCore">

                                <div class="holo-ring outer-ring"></div>
                                <div class="holo-ring data-ring"></div>
                                <div class="holo-ring inner-ring"></div>
                                <div class="holo-ring pulse-ring"></div>

                                <div class="core-center-dome">

                                    <div class="animated-face">
                                        <div class="face-blush left"></div>
                                        <div class="face-blush right"></div>

                                        <div class="face-eye left"></div>
                                        <div class="face-eye right"></div>
                                        <div class="face-mouth"></div>

                                        <div class="sparkle sp-1">✦</div>
                                        <div class="sparkle sp-2">✦</div>
                                        <div class="sparkle sp-3">✦</div>
                                        <div class="sparkle sp-4">✦</div>
                                    </div>

                                </div>
                            </div>

                            <div class="core-status text-center mt-5 font-monospace text-silver" id="coreStatusText" style="font-size: 0.85rem; letter-spacing: 4px; font-weight: 700; text-shadow: 0 0 10px rgba(255,255,255,0.1);">
                                WAITING...
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php include '../ReuseableUI/footer.php'; ?>