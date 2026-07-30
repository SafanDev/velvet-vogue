<?php
// admin/reviews.php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();

require_once '../Config/db.php';
require_once __DIR__ . '/Middleware/AuthMiddleware.php';

AuthMiddleware::requireAdmin();

// =======================================================
// HANDLE AJAX REQUESTS
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    vv_enforce_rate_limit('admin-review-update', 90, 300, (string) ($_SESSION['userID'] ?? vv_client_ip()));

    $action = (string) ($_POST['action'] ?? '');
    $reviewID = filter_var($_POST['reviewID'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($reviewID === false) {
        vv_json_response(['status' => 'error', 'message' => 'Invalid review reference.'], 422);
    }

    try {
        if ($action === 'toggle_approval') {
            $newStatus = filter_var($_POST['isApproved'] ?? null, FILTER_VALIDATE_INT);
            if (!in_array($newStatus, [0, 1], true)) {
                vv_json_response(['status' => 'error', 'message' => 'Invalid review status.'], 422);
            }
            $stmt = $pdo->prepare('UPDATE review SET isApproved = ? WHERE reviewID = ?');
            $stmt->execute([$newStatus, (int) $reviewID]);
            vv_json_response(['status' => 'success', 'message' => $newStatus === 1 ? 'Review approved for storefront.' : 'Review hidden from storefront.']);
        }

        if ($action === 'delete_review') {
            $stmt = $pdo->prepare('DELETE FROM review WHERE reviewID = ?');
            $stmt->execute([(int) $reviewID]);
            vv_json_response(['status' => 'success', 'message' => 'Review deleted.']);
        }
    } catch (Throwable $exception) {
        error_log('Review administration failed: ' . $exception->getMessage());
        vv_json_response(['status' => 'error', 'message' => 'The review could not be updated.'], 500);
    }

    vv_json_response(['status' => 'error', 'message' => 'Invalid review action.'], 422);
}

// =======================================================
// FETCH ALL REVIEWS (Joined with User, Product, and Image)
// =======================================================
$query = "
    SELECT
        r.*,
        u.firstName, u.lastName,
        p.productName,
        (SELECT filePath FROM productimage pi WHERE pi.productID = p.productID AND pi.isPrimary = 1 LIMIT 1) as productImage
    FROM review r
    JOIN `user` u ON r.userID = u.userID
    JOIN product p ON r.productID = p.productID
    ORDER BY r.createdAt DESC
";
$reviews = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Metrics
$totalReviews = count($reviews);
$pendingCount = 0;
$approvedCount = 0;
$averageRating = 0;
$totalStars = 0;

foreach ($reviews as $r) {
    if ($r['isApproved'] == 0) $pendingCount++;
    if ($r['isApproved'] == 1) $approvedCount++;
    $totalStars += $r['rating'];
}

if ($totalReviews > 0) {
    $averageRating = number_format($totalStars / $totalReviews, 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?= vv_e(vv_versioned_asset('../favicon.ico')) ?>" sizes="any">
    <link rel="icon" type="image/svg+xml" href="<?= vv_e(vv_versioned_asset('../Assets/images/brand/velvet-vogue-mark.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= vv_e(vv_versioned_asset('../Assets/images/brand/velvet-vogue-favicon-32.png')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= vv_e(vv_versioned_asset('../Assets/images/brand/velvet-vogue-apple-touch.png')) ?>">
    <meta name="theme-color" content="#050505">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <title>Brand Moderation | Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/admin-global.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/reviews.css')) ?>">
</head>
<body>

    <?php include 'adminheader.php'; ?>

    <main class="container-fluid px-xl-5 pt-4 pb-5" style="max-width: 1800px;">

        <div class="d-flex justify-content-between align-items-end mb-4 pb-3 border-bottom-dark scroll-reveal visible">
            <div>
                <div class="d-flex align-items-center gap-3 mb-1">
                    <span class="simple-label text-gold m-0">Quality Control</span>
                    <span class="badge-count text-white" id="totalBadge"><?= $totalReviews ?> Submissions</span>
                </div>
                <h1 class="massive-title text-white m-0">Brand Moderation</h1>
            </div>

            <div class="tactical-search">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="reviewSearch" class="search-input" placeholder="Search product, name, or text..." autocomplete="off">
            </div>
        </div>

        <div class="row g-4 mb-5 scroll-reveal visible" id="metricsContainer">
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-regular fa-comments metric-icon text-white"></i>
                    <div class="metric-info">
                        <span class="metric-label">Total Reviews</span>
                        <span class="metric-value" id="countTotal"><?= $totalReviews ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-hourglass-half metric-icon text-danger"></i>
                    <div class="metric-info">
                        <span class="metric-label">Pending Approval</span>
                        <span class="metric-value text-danger" id="countPending"><?= $pendingCount ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-check-double metric-icon text-success"></i>
                    <div class="metric-info">
                        <span class="metric-label">Approved</span>
                        <span class="metric-value text-success" id="countApproved"><?= $approvedCount ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-star metric-icon text-gold"></i>
                    <div class="metric-info">
                        <span class="metric-label">Store Average</span>
                        <span class="metric-value text-gold" id="countAvg"><?= $averageRating ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="review-grid scroll-reveal visible">
            <?php if(empty($reviews)): ?>
                <div class="col-12 text-center py-5 text-muted font-body w-100" style="grid-column: 1 / -1;">No reviews have been submitted yet.</div>
            <?php else: ?>
                <?php foreach($reviews as $rev): ?>
                    <div class="review-card <?= $rev['isApproved'] == 0 ? 'card-pending' : '' ?>"
                         data-status="<?= $rev['isApproved'] ?>"
                         data-rating="<?= $rev['rating'] ?>"
                         data-search="<?= vv_e(strtolower($rev['firstName'] . ' ' . $rev['lastName'] . ' ' . $rev['productName'] . ' ' . $rev['comment'])) ?>">

                        <div class="rc-header">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <span class="rc-name d-block"><?= vv_e(trim(($rev['firstName'] ?? '') . ' ' . ($rev['lastName'] ?? ''))) ?></span>

                                    <?php if(!is_null($rev['orderItemID'])): ?>
                                        <div class="verified-badge mt-1">
                                            <i class="fa-solid fa-certificate"></i> Verified Purchase
                                        </div>
                                    <?php else: ?>
                                        <div class="unverified-badge mt-1 text-muted">
                                            <i class="fa-regular fa-user"></i> Store Visitor
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="rc-date"><?= date('M d, Y', strtotime($rev['createdAt'])) ?></span>
                            </div>

                            <div class="d-flex align-items-center gap-3 rc-product-box">
                                <div class="rc-thumb">
                                    <?php if($rev['productImage']): ?>
                                        <img loading="lazy" decoding="async" src="<?= vv_e(vv_admin_public_url($rev['productImage'])) ?>" alt="Product">
                                    <?php else: ?>
                                        <i class="fa-solid fa-image text-muted"></i>
                                    <?php endif; ?>
                                </div>
                                <span class="rc-product-name"><?= vv_e($rev['productName']) ?></span>
                            </div>
                        </div>

                        <div class="rc-body">
                            <div class="rc-stars mb-3">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <?php if($i <= $rev['rating']): ?>
                                        <i class="fa-solid fa-star text-gold"></i>
                                    <?php else: ?>
                                        <i class="fa-regular fa-star text-muted" style="opacity: 0.3;"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                            <p class="rc-comment">"<?= nl2br(vv_e($rev['comment'])) ?>"</p>
                        </div>

                        <div class="rc-footer">
                            <div class="d-flex align-items-center gap-2">
                                <label class="luxury-switch" title="Toggle Storefront Approval">
                                    <input type="checkbox" class="status-toggle" data-id="<?= $rev['reviewID'] ?>" <?= $rev['isApproved'] == 1 ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                                <span class="status-text <?= $rev['isApproved'] == 1 ? 'text-success' : 'text-danger' ?>">
                                    <?= $rev['isApproved'] == 1 ? 'PUBLISHED' : 'PENDING' ?>
                                </span>
                            </div>

                            <button type="button" class="btn-action-ghost text-hover-red" onclick="triggerDeleteModal(<?= $rev['reviewID'] ?>, this)" title="Delete Review">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>

    <div class="custom-modal-overlay" id="deleteModalOverlay"></div>
    <div class="custom-modal-box" id="deleteModalBox">
        <i class="fa-solid fa-triangle-exclamation modal-icon-warn"></i>
        <h3 class="modal-title">Purge Review</h3>
        <p class="modal-text">Are you sure you want to permanently delete this customer review? This action cannot be undone.</p>
        <div class="d-flex gap-3 justify-content-center mt-4">
            <button class="btn-modal-cancel" id="cancelDeleteBtn">Cancel</button>
            <button class="btn-modal-confirm" id="confirmDeleteBtn">Yes, Purge</button>
        </div>
    </div>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999">
        <div id="actionToast" class="toast align-items-center text-white bg-dark border border-secondary" role="alert">
            <div class="d-flex">
                <div class="toast-body font-body" id="toastMessage"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/admin-global.js')) ?>"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/reviews.js')) ?>"></script>
</body>
</html>