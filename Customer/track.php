<?php
// track.php - Velvet Vogue Order Tracking
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once '../Config/db.php';

$orderQuery = isset($_GET['order']) ? strtoupper(trim((string) $_GET['order'])) : null;
$trackedOrder = null;
$errorMsg = null;
$currentUserId = isset($_SESSION['userID']) ? (int) $_SESSION['userID'] : null;

if ($orderQuery !== null && $orderQuery !== '') {
    vv_enforce_rate_limit('order-tracking', 30, 300, (string) ($currentUserId ?? vv_client_ip()));

    if ($currentUserId === null) {
        $errorMsg = 'Sign in to track an order associated with your account.';
    } elseif (!preg_match('/^VV-[A-Z0-9-]{4,32}$/', $orderQuery)) {
        $errorMsg = 'Enter a valid Velvet Vogue order number.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT orderNumber, orderStatus, createdAt, shippingAddressSnap FROM `order` WHERE orderNumber = ? AND userID = ? LIMIT 1');
            $stmt->execute([$orderQuery, $currentUserId]);
            $trackedOrder = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($trackedOrder === null) {
                $errorMsg = "We couldn't find that order in your account.";
            }
        } catch (Throwable $exception) {
            error_log('Order tracking failed: ' . $exception->getMessage());
            $errorMsg = 'Order tracking is temporarily unavailable.';
        }
    }
}

$trackedStatus = 'pending';
if ($trackedOrder) {
    $candidateStatus = strtolower((string) ($trackedOrder['orderStatus'] ?? 'pending'));
    $trackedStatus = in_array($candidateStatus, ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'], true)
        ? $candidateStatus
        : 'pending';
}

$page_css = "track.css";
$page_js = "track.js";
include '../ReuseableUI/header.php';
?>

<main class="tracking-wrapper position-relative">
    <div class="cinematic-grain"></div>
    <div class="transit-grid-bg"></div>

    <div class="container-fluid px-lg-5 py-5 position-relative z-2">

        <?php if(!$trackedOrder): ?>
            <div class="tracking-search-terminal gsap-fade-in d-flex flex-column align-items-center justify-content-center text-center mx-auto">
                <div class="bespoke-search-icon mb-4 position-relative">
                    <div class="tailor-ring ring-fast"></div>
                    <div class="tailor-ring ring-slow"></div>
                    <i class="fa-solid fa-satellite-dish gold-text position-relative z-2" style="font-size: 2.5rem;"></i>
                </div>
                <h1 class="tracking-title mb-3">TRACK YOUR ORDER</h1>
                <p class="text-silver tracking-luxury mb-4" style="font-size: 0.8rem; line-height: 1.6; max-width: 500px;">
                    Enter your order number below to check the current status of your shipment.
                </p>

                <?php if($errorMsg): ?>
                    <div class="alert alert-danger bg-transparent border-danger text-danger font-body mb-4" style="font-size: 0.85rem; max-width: 500px;">
                        <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($errorMsg) ?>
                    </div>
                <?php endif; ?>

                <form action="track.php" method="GET" class="w-100 position-relative" id="trackingForm" style="max-width: 500px;">
                    <input type="text" name="order" class="tracking-input text-center font-monospace" placeholder="VV-XXXXXXXX" autocomplete="off" maxlength="35" pattern="VV-[A-Za-z0-9-]{4,32}" required>
                    <div class="input-glow-line"></div>
                    <button type="submit" class="btn-outline-gold w-100 mt-4 py-3" style="font-size: 0.85rem;">TRACK ORDER <i class="fa-solid fa-arrow-right ms-2"></i></button>
                </form>
            </div>

        <?php else: ?>
            <div class="tracking-active-state gsap-fade-in mx-auto" style="max-width: 1200px; padding-top: 2vh;">

                <a href="track.php" class="btn-text-silver mb-4 d-inline-block"><i class="fa-solid fa-arrow-left me-2"></i> TRACK ANOTHER ORDER</a>

                <div class="tracking-dossier-header d-flex justify-content-between align-items-end border-bottom-dark pb-4 mb-5 flex-wrap gap-4">
                    <div>
                        <span class="gold-text tracking-luxury d-block mb-2" style="font-size: 0.65rem;">ORDER NUMBER</span>
                        <h2 class="text-white font-monospace m-0" style="font-size: 2.8rem; letter-spacing: 2px;" id="trackedIdVal"><?= htmlspecialchars($trackedOrder['orderNumber']) ?></h2>
                    </div>
                    <div class="text-end">
                        <span class="text-silver tracking-luxury d-block mb-2" style="font-size: 0.65rem;">SHIPPING TO</span>
                        <span class="text-white opacity-75 font-monospace" style="font-size: 0.9rem;">
                            <?php
                                // Extract the city/zip from the snapshot for a cleaner display
                                $addressParts = explode(',', $trackedOrder['shippingAddressSnap']);
                                echo htmlspecialchars(isset($addressParts[1]) ? trim($addressParts[1]) : 'CONFIDENTIAL');
                            ?>
                        </span>
                    </div>
                </div>

                <div class="row g-5">
                    <div class="col-lg-6 col-xl-7">
                        <div class="tracking-timeline-container position-relative">

                            <div class="timeline-track"></div>
                            <div class="timeline-pulse-traveler" data-status="<?= vv_e($trackedStatus) ?>"></div>

                            <?php
                            // Determine current step index based on DB status
                            $statusArr = ['pending', 'processing', 'shipped', 'delivered'];
                            $currentIdx = array_search($trackedStatus, $statusArr, true);

                            // If order is cancelled/returned, we handle it separately
                            if ($currentIdx === false && in_array($trackedStatus, ['cancelled', 'returned'], true)) {
                                $currentIdx = -99;
                            }
                            ?>

                            <?php if ($currentIdx === -99): ?>
                                <div class="t-node current">
                                    <div class="node-indicator" style="border-color: #ff4d4d; color: #ff4d4d; background: rgba(255,77,77,0.1); box-shadow: inset 0 0 20px rgba(255,77,77,0.2);"><i class="fa-solid fa-xmark"></i></div>
                                    <div class="node-content">
                                        <h4 class="node-title" style="color: #ff4d4d;">ORDER <?= vv_e(strtoupper($trackedStatus)) ?></h4>
                                        <p class="node-desc">This order has been <?= vv_e($trackedStatus) ?>. Please contact support for more information.</p>
                                    </div>
                                </div>
                            <?php else: ?>

                                <div class="t-node <?= ($currentIdx >= 0) ? 'completed' : '' ?> <?= ($currentIdx == 0) ? 'current' : '' ?>">
                                    <div class="node-indicator"><i class="fa-solid fa-check"></i></div>
                                    <div class="node-content">
                                        <h4 class="node-title">ORDER PLACED</h4>
                                        <p class="node-desc">We have successfully received your order details.</p>
                                        <span class="node-time font-monospace text-silver"><?= date('M d, Y - H:i', strtotime($trackedOrder['createdAt'])) ?></span>
                                    </div>
                                </div>

                                <div class="t-node <?= ($currentIdx >= 1) ? 'completed' : '' ?> <?= ($currentIdx == 1) ? 'current' : '' ?>">
                                    <div class="node-indicator"><i class="fa-solid fa-box-open"></i></div>
                                    <div class="node-content">
                                        <h4 class="node-title">PREPARING ORDER</h4>
                                        <p class="node-desc">Your items are being carefully inspected and packed for shipment.</p>
                                    </div>
                                </div>

                                <div class="t-node <?= ($currentIdx >= 2) ? 'completed' : '' ?> <?= ($currentIdx == 2) ? 'current' : '' ?>">
                                    <div class="node-indicator"><i class="fa-solid fa-truck-fast"></i></div>
                                    <div class="node-content">
                                        <h4 class="node-title">IN TRANSIT</h4>
                                        <p class="node-desc">Your package is with our shipping partner and is on its way to you.</p>
                                        <?php if($currentIdx == 2): ?><span class="node-time gold-text font-monospace" style="animation: textFlicker 2s infinite;">ON THE MOVE</span><?php endif; ?>
                                    </div>
                                </div>

                                <div class="t-node <?= ($currentIdx >= 3) ? 'completed' : '' ?> <?= ($currentIdx == 3) ? 'current' : '' ?>">
                                    <div class="node-indicator"><i class="fa-solid fa-location-dot"></i></div>
                                    <div class="node-content">
                                        <h4 class="node-title">DELIVERED</h4>
                                        <p class="node-desc">Your order has been successfully delivered to your address.</p>
                                    </div>
                                </div>

                            <?php endif; ?>

                        </div>
                    </div>

                    <div class="col-lg-6 col-xl-5">
                        <div class="telemetry-panel">
                            <div class="tp-header">
                                <span class="tracking-luxury text-white" style="font-size: 0.65rem;">LIVE TRACKING INFO</span>
                                <div class="live-dot"></div>
                            </div>

                            <div class="vip-locator-container">

                                <div class="gps-visualizer">
                                    <div class="gps-grid"></div>
                                    <div class="gps-ring ring-outer"></div>
                                    <div class="gps-ring ring-inner"></div>

                                    <div class="gps-destination">
                                        <div class="dest-pulse"></div>
                                        <i class="fa-solid fa-location-crosshairs"></i>
                                    </div>

                                    <div class="gps-orbit-path">
                                        <div class="gps-courier-blip">
                                            <i class="fa-solid fa-truck"></i>
                                        </div>
                                    </div>

                                    <div class="gps-sweep"></div>
                                </div>

                                <div class="logistics-hud w-100">
                                    <div class="hud-data-row border-bottom-dark pb-3 mb-3">
                                        <div class="hdr-col">
                                            <span class="hdr-label">ESTIMATED ARRIVAL</span>
                                            <span class="hdr-val gold-text" id="liveEta">CALCULATING...</span>
                                        </div>
                                        <div class="hdr-col text-end">
                                            <span class="hdr-label">TRANSIT SPEED</span>
                                            <span class="hdr-val text-white" id="liveSpeed">-- KM/H</span>
                                        </div>
                                    </div>

                                    <div class="hud-data-row border-bottom-dark pb-3 mb-3">
                                        <div class="hdr-col">
                                            <span class="hdr-label">LAST KNOWN LOCATION</span>
                                            <span class="hdr-val text-white" id="liveCoords">UPDATING...</span>
                                        </div>
                                    </div>

                                    <div class="hud-data-row">
                                        <div class="hdr-col">
                                            <span class="hdr-label">SHIPPING METHOD</span>
                                            <span class="hdr-val text-silver">STANDARD DELIVERY</span>
                                        </div>
                                        <div class="hdr-col text-end">
                                            <span class="hdr-label">CURRENT STATUS</span>
                                            <span class="hdr-val text-success text-uppercase"><?= vv_e($trackedStatus) ?></span>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>

            </div>
        <?php endif; ?>

    </div>
</main>

<?php include '../ReuseableUI/footer.php'; ?>