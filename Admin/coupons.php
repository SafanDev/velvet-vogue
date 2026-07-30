<?php
// admin/coupons.php
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
    vv_enforce_rate_limit('admin-coupon-update', 60, 300, (string) ($_SESSION['userID'] ?? vv_client_ip()));

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_coupon') {
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $type = strtolower(trim((string) ($_POST['discountType'] ?? '')));
            $value = filter_var($_POST['discountValue'] ?? null, FILTER_VALIDATE_FLOAT);
            $minOrder = ($_POST['minOrderValue'] ?? '') !== '' ? filter_var($_POST['minOrderValue'], FILTER_VALIDATE_FLOAT) : null;
            $maxUses = ($_POST['maxUses'] ?? '') !== '' ? filter_var($_POST['maxUses'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]) : null;
            $startsInput = trim((string) ($_POST['startsAt'] ?? ''));
            $expiresInput = trim((string) ($_POST['expiresAt'] ?? ''));

            if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,31}$/', $code)) {
                vv_json_response(['status' => 'error', 'message' => 'Use 3 to 32 letters, numbers, dashes, or underscores for the code.'], 422);
            }
            if (!in_array($type, ['percentage', 'fixed'], true) || $value === false || $value <= 0) {
                vv_json_response(['status' => 'error', 'message' => 'Enter a valid discount type and value.'], 422);
            }
            if ($type === 'percentage' && $value > 100) {
                vv_json_response(['status' => 'error', 'message' => 'Percentage discounts cannot exceed 100%.'], 422);
            }
            if ($type === 'fixed' && $value > 10000000) {
                vv_json_response(['status' => 'error', 'message' => 'The fixed discount is too large.'], 422);
            }
            if ($minOrder === false || ($minOrder !== null && ($minOrder < 0 || $minOrder > 100000000))) {
                vv_json_response(['status' => 'error', 'message' => 'Enter a valid minimum order value.'], 422);
            }
            if ($maxUses === false) {
                vv_json_response(['status' => 'error', 'message' => 'Enter a valid maximum use count.'], 422);
            }

            $parseDate = static function (string $value): ?string {
                if ($value === '') {
                    return null;
                }
                $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
                return $date && $date->format('Y-m-d\TH:i') === $value ? $date->format('Y-m-d H:i:s') : null;
            };

            $startsAt = $parseDate($startsInput);
            $expiresAt = $parseDate($expiresInput);
            if (($startsInput !== '' && $startsAt === null) || ($expiresInput !== '' && $expiresAt === null)) {
                vv_json_response(['status' => 'error', 'message' => 'Enter valid coupon dates.'], 422);
            }
            if ($startsAt !== null && $expiresAt !== null && strtotime($expiresAt) <= strtotime($startsAt)) {
                vv_json_response(['status' => 'error', 'message' => 'The expiry must be after the start date.'], 422);
            }

            $stmt = $pdo->prepare('INSERT INTO coupon (code, discountType, discountValue, minOrderValue, startsAt, expiresAt, maxUses, isActive) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
            $stmt->execute([$code, $type, round((float) $value, 2), $minOrder === null ? null : round((float) $minOrder, 2), $startsAt, $expiresAt, $maxUses]);
            vv_json_response(['status' => 'success', 'message' => 'Coupon saved successfully.']);
        }

        $couponID = filter_var($_POST['couponID'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($couponID === false) {
            vv_json_response(['status' => 'error', 'message' => 'Invalid coupon reference.'], 422);
        }

        if ($action === 'toggle_status') {
            $newStatus = filter_var($_POST['isActive'] ?? null, FILTER_VALIDATE_INT);
            if (!in_array($newStatus, [0, 1], true)) {
                vv_json_response(['status' => 'error', 'message' => 'Invalid coupon status.'], 422);
            }
            $stmt = $pdo->prepare('UPDATE coupon SET isActive = ? WHERE couponID = ?');
            $stmt->execute([$newStatus, (int) $couponID]);
            vv_json_response(['status' => 'success', 'message' => $newStatus === 1 ? 'Coupon activated.' : 'Coupon paused.']);
        }

        if ($action === 'delete_coupon') {
            $usageStmt = $pdo->prepare('SELECT useCount FROM coupon WHERE couponID = ? LIMIT 1');
            $usageStmt->execute([(int) $couponID]);
            $useCount = $usageStmt->fetchColumn();
            if ($useCount === false) {
                vv_json_response(['status' => 'error', 'message' => 'Coupon not found.'], 404);
            }
            if ((int) $useCount > 0) {
                $pdo->prepare('UPDATE coupon SET isActive = 0 WHERE couponID = ?')->execute([(int) $couponID]);
                vv_json_response(['status' => 'success', 'message' => 'Used coupons are retained for order history and have been paused.']);
            }

            $pdo->prepare('DELETE FROM coupon WHERE couponID = ?')->execute([(int) $couponID]);
            vv_json_response(['status' => 'success', 'message' => 'Coupon deleted.']);
        }
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() === '23000') {
            vv_json_response(['status' => 'error', 'message' => 'This promo code already exists or is linked to an order.'], 409);
        }
        error_log('Coupon administration failed: ' . $exception->getMessage());
        vv_json_response(['status' => 'error', 'message' => 'The coupon could not be updated.'], 500);
    }

    vv_json_response(['status' => 'error', 'message' => 'Invalid coupon action.'], 422);
}

// =======================================================
// FETCH ALL COUPONS
// =======================================================
$query = "SELECT * FROM coupon ORDER BY createdAt DESC";
$coupons = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

$totalCoupons = count($coupons);
$activeCoupons = 0;
$percentageCoupons = 0;
$fixedCoupons = 0;

foreach ($coupons as $c) {
    if ($c['isActive'] == 1) $activeCoupons++;
    if ($c['discountType'] == 'percentage') $percentageCoupons++;
    if ($c['discountType'] == 'fixed') $fixedCoupons++;
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
    <title>Coupons & Offers | Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/admin-global.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/coupons.css')) ?>">
</head>
<body>

    <?php include 'adminheader.php'; ?>

    <main class="container-fluid px-xl-5 pt-4 pb-5" style="max-width: 1700px;">

        <div class="d-flex justify-content-between align-items-end mb-4 pb-3 border-bottom-dark scroll-reveal visible">
            <div>
                <div class="d-flex align-items-center gap-3 mb-1">
                    <span class="simple-label text-gold m-0">Offers Management</span>
                    <span class="badge-count text-white" id="totalBadge"><?= $totalCoupons ?> Codes</span>
                </div>
                <h1 class="massive-title text-white m-0">Coupons & Promos</h1>
            </div>

            <div class="d-flex gap-4 align-items-center">
                <div class="tactical-search">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="couponSearch" class="search-input" placeholder="Search promo codes..." autocomplete="off">
                </div>

                <button type="button" class="btn-vip-ticket" id="openAddCouponModal" style="--bg-color: #000;">
                    <span class="ticket-tear-line"></span>
                    <span class="btn-text">Add Coupon</span>
                    <i class="fa-solid fa-plus btn-icon ms-2"></i>
                    <span class="ticket-barcode"></span>
                </button>
            </div>
        </div>

        <div class="row g-4 mb-5 scroll-reveal visible" id="metricsContainer">
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-ticket metric-icon text-white"></i>
                    <div class="metric-info">
                        <span class="metric-label">Total Codes</span>
                        <span class="metric-value" id="countTotal"><?= $totalCoupons ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-bolt metric-icon text-success"></i>
                    <div class="metric-info">
                        <span class="metric-label">Active Coupons</span>
                        <span class="metric-value text-success" id="countActive"><?= $activeCoupons ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-percent metric-icon text-cyan"></i>
                    <div class="metric-info">
                        <span class="metric-label">Percentage Off</span>
                        <span class="metric-value text-cyan" id="countPerc"><?= $percentageCoupons ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-coins metric-icon text-gold"></i>
                    <div class="metric-info">
                        <span class="metric-label">Fixed Amount Off</span>
                        <span class="metric-value text-gold" id="countFixed"><?= $fixedCoupons ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-container-solid mb-5 scroll-reveal visible">
            <div class="table-responsive pb-2">
                <table class="table custom-ledger-table align-middle m-0" id="couponTable">
                    <thead class="sticky-top z-3">
                        <tr>
                            <th style="padding-left: 40px;">Promo Code</th>
                            <th>Discount Value</th>
                            <th>Conditions</th>
                            <th>Usage Limit</th>
                            <th>Validity Window</th>
                            <th class="text-center">Status</th>
                            <th class="text-end" style="padding-right: 40px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($coupons)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-silver font-body">No coupons found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($coupons as $c): ?>
                                <tr class="ledger-row <?= $c['isActive'] == 0 ? 'row-suspended' : '' ?>" data-search="<?= vv_e(strtolower((string) $c['code'])) ?>">

                                    <td style="padding-left: 40px;">
                                        <div class="code-badge"><?= vv_e($c['code']) ?></div>
                                    </td>

                                    <td>
                                        <?php if($c['discountType'] == 'percentage'): ?>
                                            <span class="value-text text-cyan"><?= (float)$c['discountValue'] ?>% OFF</span>
                                        <?php else: ?>
                                            <span class="value-text text-gold">Rs. <?= number_format($c['discountValue'], 2) ?> OFF</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if($c['minOrderValue'] > 0): ?>
                                            <span class="sub-text">Min Spend: <span class="text-white">Rs. <?= number_format($c['minOrderValue'], 2) ?></span></span>
                                        <?php else: ?>
                                            <span class="sub-text text-silver">No minimum</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-white fw-bold"><?= $c['useCount'] ?></span>
                                            <span class="sub-text">/ <?= $c['maxUses'] ? $c['maxUses'] : '&infin;' ?></span>
                                        </div>
                                    </td>

                                    <td>
                                        <?php
                                            $start = $c['startsAt'] ? date('M d, Y g:i A', strtotime($c['startsAt'])) : 'Now';
                                            $end = $c['expiresAt'] ? date('M d, Y g:i A', strtotime($c['expiresAt'])) : 'Never';
                                        ?>
                                        <span class="sub-text d-block mb-1">From: <span class="text-silver"><?= $start ?></span></span>
                                        <span class="sub-text d-block">Till: <span class="text-silver"><?= $end ?></span></span>
                                    </td>

                                    <td class="text-center">
                                        <label class="luxury-switch mx-auto" title="Toggle Active Status">
                                            <input type="checkbox" class="status-toggle" data-id="<?= $c['couponID'] ?>" <?= $c['isActive'] == 1 ? 'checked' : '' ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </td>

                                    <td class="text-end pe-4">
                                        <button type="button" class="btn-action-ghost text-hover-red" onclick="triggerDeleteModal(<?= $c['couponID'] ?>, this)" title="Delete Coupon">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <div class="side-panel-overlay" id="sidePanelOverlay"></div>
    <div class="side-panel" id="sidePanel">
        <div class="panel-header">
            <h4 class="font-heading text-gold m-0 text-uppercase" style="font-size: 1.1rem; letter-spacing: 2px;">Add New Coupon</h4>
            <button type="button" class="btn-close-panel" id="closeSidePanel"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="panel-body">

            <form id="addForm" method="post" autocomplete="off">
                <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">
                <input type="hidden" name="action" value="create_coupon">

                <div class="form-floating-custom mb-4 mt-2">
                    <input type="text" name="code" id="code" class="luxury-input text-uppercase fw-bold" placeholder=" " required>
                    <label for="code">Promo Code (e.g. SUMMER20)</label>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <span class="d-block mb-2 font-body text-silver" style="font-size: 0.7rem; letter-spacing: 1px; text-transform: uppercase;">Discount Type</span>
                        <div class="elegant-select-wrapper w-100">
                            <select name="discountType" class="elegant-select w-100 text-gold">
                                <option value="percentage" class="text-white">Percentage (%)</option>
                                <option value="fixed" class="text-white">Fixed Amount (Rs.)</option>
                            </select>
                            <i class="fa-solid fa-chevron-down select-arrow"></i>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-floating-custom mt-4 pt-1">
                            <input type="text" inputmode="decimal" pattern="^\d*\.?\d*$" name="discountValue" id="discountValue" class="luxury-input" placeholder=" " required>
                            <label for="discountValue">Discount Value</label>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="form-floating-custom">
                            <input type="text" inputmode="decimal" pattern="^\d*\.?\d*$" name="minOrderValue" id="minOrderValue" class="luxury-input" placeholder=" ">
                            <label for="minOrderValue">Min Spend (Opt)</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-floating-custom">
                            <input type="text" inputmode="numeric" pattern="[0-9]*" name="maxUses" id="maxUses" class="luxury-input" placeholder=" ">
                            <label for="maxUses">Max Uses (Opt)</label>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <span class="d-block mb-2 font-body text-silver" style="font-size: 0.7rem; letter-spacing: 1px; text-transform: uppercase;">Start Date & Time</span>
                    <input type="datetime-local" name="startsAt" id="startsAt" class="luxury-input date-input-clean">
                </div>

                <div class="mb-5">
                    <span class="d-block mb-2 font-body text-silver" style="font-size: 0.7rem; letter-spacing: 1px; text-transform: uppercase;">Expiry Date & Time</span>
                    <input type="datetime-local" name="expiresAt" id="expiresAt" class="luxury-input date-input-clean">
                </div>

                <button type="submit" class="btn-vip-ticket w-100" id="submitBtn" style="--bg-color: #080808;">
                    <span class="ticket-tear-line"></span>
                    <span class="btn-text">Save Coupon</span>
                    <i class="fa-solid fa-check btn-icon ms-2"></i>
                    <span class="ticket-barcode"></span>
                </button>
            </form>
        </div>
    </div>

    <div class="custom-modal-overlay" id="deleteModalOverlay"></div>
    <div class="custom-modal-box" id="deleteModalBox">
        <i class="fa-solid fa-triangle-exclamation modal-icon-warn"></i>
        <h3 class="modal-title">Delete Coupon</h3>
        <p class="modal-text">Are you sure you want to permanently delete this promo code? It will immediately stop working for all customers.</p>
        <div class="d-flex gap-3 justify-content-center mt-4">
            <button class="btn-modal-cancel" id="cancelDeleteBtn">Cancel</button>
            <button class="btn-modal-confirm" id="confirmDeleteBtn">Yes, Delete</button>
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
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/coupons.js')) ?>"></script>
</body>
</html>