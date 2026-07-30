<?php
// admin/orders.php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();

require_once '../Config/db.php';
require_once __DIR__ . '/Middleware/AuthMiddleware.php';

AuthMiddleware::requireAdmin();

// =======================================================
// HANDLE INLINE STATUS UPDATES (AJAX)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    vv_enforce_rate_limit('admin-order-update', 90, 300, (string) ($_SESSION['userID'] ?? vv_client_ip()));

    $action = (string) ($_POST['action'] ?? '');
    $orderID = filter_var($_POST['orderID'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $newStatus = strtolower(trim((string) ($_POST['newStatus'] ?? '')));

    if ($orderID === false) {
        vv_json_response(['status' => 'error', 'message' => 'Invalid order reference.'], 422);
    }

    try {
        if ($action === 'update_status') {
            $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];
            if (!in_array($newStatus, $validStatuses, true)) {
                vv_json_response(['status' => 'error', 'message' => 'Invalid order status.'], 422);
            }

            $stmt = $pdo->prepare('UPDATE `order` SET orderStatus = ? WHERE orderID = ?');
            $stmt->execute([$newStatus, (int) $orderID]);
            vv_json_response(['status' => 'success', 'message' => 'Status updated.']);
        }

        if ($action === 'update_payment_status') {
            $validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded'];
            if (!in_array($newStatus, $validPaymentStatuses, true)) {
                vv_json_response(['status' => 'error', 'message' => 'Invalid payment status.'], 422);
            }

            $stmt = $pdo->prepare('UPDATE payment SET paymentStatus = ? WHERE orderID = ?');
            $stmt->execute([$newStatus, (int) $orderID]);
            if ($stmt->rowCount() === 0) {
                vv_json_response(['status' => 'error', 'message' => 'The payment record was not found.'], 404);
            }
            vv_json_response(['status' => 'success', 'message' => 'Payment status updated.']);
        }
    } catch (Throwable $exception) {
        error_log('Admin order update failed: ' . $exception->getMessage());
        vv_json_response(['status' => 'error', 'message' => 'The order could not be updated.'], 500);
    }

    vv_json_response(['status' => 'error', 'message' => 'Invalid status update.'], 422);
}

// =======================================================
// FETCH ORDER DATA (JOIN WITH PAYMENT)
// =======================================================
$query = "
    SELECT
        o.orderID,
        o.orderNumber,
        o.totalPaid,
        COALESCE(p.paymentMethod, 'Not recorded') AS paymentMethod,
        COALESCE(p.paymentStatus, 'pending') AS paymentStatus,
        o.orderStatus,
        o.createdAt,
        u.firstName,
        u.lastName,
        u.email,
        (SELECT SUM(quantityBought) FROM orderitem WHERE orderID = o.orderID) as totalItems
    FROM `order` o
    LEFT JOIN `user` u ON o.userID = u.userID
    LEFT JOIN payment p ON o.orderID = p.orderID
    ORDER BY o.createdAt DESC
";
$orders = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Calculate Metrics
$totalOrdersCount = count($orders);
$totalRevenue = 0;
$pendingCount = 0;
$completedCount = 0;

foreach ($orders as $o) {
    if ($o['paymentStatus'] === 'paid' && !in_array($o['orderStatus'], ['cancelled', 'returned'], true)) {
        $totalRevenue += $o['totalPaid'];
    }
    if ($o['orderStatus'] === 'pending' || $o['orderStatus'] === 'processing') {
        $pendingCount++;
    }
    if ($o['orderStatus'] === 'delivered') {
        $completedCount++;
    }
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
    <title>Order Management | Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/admin-global.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/orders.css')) ?>">
</head>
<body>

    <?php include 'adminheader.php'; ?>

    <main class="container-fluid px-xl-5 pt-4 pb-5" style="max-width: 1800px;">

        <div class="d-flex justify-content-between align-items-end mb-4 pb-3 border-bottom-dark scroll-reveal visible">
            <div>
                <div class="d-flex align-items-center gap-3 mb-1">
                    <span class="simple-label text-gold m-0">Revenue Pipeline</span>
                    <span class="badge-count text-white"><?= $totalOrdersCount ?> Total</span>
                </div>
                <h1 class="massive-title text-white m-0">Order Ledger</h1>
            </div>

            <div class="d-flex gap-4">
                <div class="tactical-search">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="orderSearch" class="search-input" placeholder="Search Reference, Client, or Email...">
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5 scroll-reveal visible" id="metricsContainer">
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-receipt metric-icon text-white"></i>
                    <div class="metric-info">
                        <span class="metric-label">Total Volume</span>
                        <span class="metric-value"><?= $totalOrdersCount ?> <span class="metric-suffix">Orders</span></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-sack-dollar metric-icon text-gold"></i>
                    <div class="metric-info">
                        <span class="metric-label">Verified Revenue</span>
                        <span class="metric-value text-gold">Rs. <?= number_format($totalRevenue, 0) ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-box-open metric-icon text-cyan"></i>
                    <div class="metric-info">
                        <span class="metric-label">Action Required</span>
                        <span class="metric-value text-cyan"><?= $pendingCount ?> <span class="metric-suffix">Orders</span></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-check-double metric-icon text-success"></i>
                    <div class="metric-info">
                        <span class="metric-label">Fulfilled</span>
                        <span class="metric-value text-success"><?= $completedCount ?> <span class="metric-suffix">Orders</span></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-container-solid mb-5 scroll-reveal visible">

            <div class="table-responsive pb-2">
                <table class="table custom-ledger-table align-middle m-0" id="ordersTable">
                    <thead class="sticky-top z-3">
                        <tr>
                            <th style="padding-left: 30px;">Ref</th>
                            <th>Client Identity</th>
                            <th>Date & Time</th>
                            <th>Items</th>
                            <th>Total Value</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th class="text-end" style="padding-right: 30px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($orders)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="fa-solid fa-file-invoice-dollar mb-3 text-muted" style="font-size: 2.5rem;"></i>
                                    <h5 class="font-heading m-0 text-white" style="font-size: 1.2rem;">The ledger is empty.</h5>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($orders as $order): ?>
                                <tr class="ledger-row" data-search="<?= vv_e(strtolower(($order['orderNumber'] ?? '') . ' ' . ($order['firstName'] ?? '') . ' ' . ($order['lastName'] ?? '') . ' ' . ($order['email'] ?? ''))) ?>">

                                    <td style="padding-left: 30px;">
                                        <span class="d-block text-gold fw-bold font-monospace" style="font-size: 0.85rem; letter-spacing: 1px;"><?= vv_e($order['orderNumber']) ?></span>
                                    </td>

                                    <td>
                                        <span class="d-block text-white fw-bold mb-1" style="font-size: 0.85rem; text-transform: uppercase;"><?= vv_e(trim(($order['firstName'] ?? '') . ' ' . ($order['lastName'] ?? ''))) ?></span>
                                        <span class="font-body" style="font-size: 0.75rem; color: #888;"><?= vv_e($order['email'] ?? '') ?></span>
                                    </td>

                                    <td>
                                        <span class="d-block font-body mb-1 text-white" style="font-size: 0.85rem;"><?= date('M d, Y', strtotime($order['createdAt'])) ?></span>
                                        <span class="font-monospace" style="font-size: 0.75rem; color: #888;"><?= date('H:i A', strtotime($order['createdAt'])) ?></span>
                                    </td>

                                    <td>
                                        <span class="text-white font-body fw-bold" style="font-size: 0.85rem;"><?= $order['totalItems'] ?? 0 ?></span>
                                        <span class="font-body" style="font-size: 0.7rem; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-left: 4px;">units</span>
                                    </td>

                                    <td>
                                        <span class="d-block text-white font-body" style="font-size: 0.9rem; font-weight: 600;">Rs. <?= number_format($order['totalPaid'], 2) ?></span>
                                    </td>

                                    <td>
                                        <?php
                                            $dotColor = '#777';
                                            if($order['paymentStatus'] == 'paid') $dotColor = '#2ecc71';
                                            if($order['paymentStatus'] == 'failed') $dotColor = '#e74c3c';
                                            if($order['paymentStatus'] == 'refunded') $dotColor = '#f39c12';
                                        ?>
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="status-dot dot-<?= $order['orderID'] ?>" style="background-color: <?= $dotColor ?>; box-shadow: 0 0 8px <?= $dotColor ?>;"></span>

                                            <select class="elegant-select pay-status-<?= $order['paymentStatus'] ?> m-0 p-0 h-auto w-auto bg-transparent border-0" data-order-id="<?= $order['orderID'] ?>" data-update-type="paymentStatus" style="font-size: 0.75rem; letter-spacing: 1px;">
                                                <option value="pending" <?= $order['paymentStatus'] == 'pending' ? 'selected' : '' ?>>PENDING</option>
                                                <option value="paid" <?= $order['paymentStatus'] == 'paid' ? 'selected' : '' ?>>PAID</option>
                                                <option value="failed" <?= $order['paymentStatus'] == 'failed' ? 'selected' : '' ?>>FAILED</option>
                                                <option value="refunded" <?= $order['paymentStatus'] == 'refunded' ? 'selected' : '' ?>>REFUNDED</option>
                                            </select>
                                        </div>
                                        <span class="d-block font-body" style="font-size: 0.7rem; color: #888;"><?= vv_e($order['paymentMethod']) ?></span>
                                    </td>

                                    <td>
                                        <div class="elegant-select-wrapper">
                                            <select class="elegant-select status-<?= $order['orderStatus'] ?>" data-order-id="<?= $order['orderID'] ?>" data-update-type="orderStatus">
                                                <option value="pending" <?= $order['orderStatus'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="processing" <?= $order['orderStatus'] == 'processing' ? 'selected' : '' ?>>Processing</option>
                                                <option value="shipped" <?= $order['orderStatus'] == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                                <option value="delivered" <?= $order['orderStatus'] == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                                <option value="cancelled" <?= $order['orderStatus'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                <option value="returned" <?= $order['orderStatus'] == 'returned' ? 'selected' : '' ?>>Returned</option>
                                            </select>
                                        </div>
                                    </td>

                                    <td class="text-end" style="padding-right: 30px;">
                                        <a href="order-view.php?id=<?= $order['orderID'] ?>" class="btn-inspect">
                                            Inspect <i class="fa-solid fa-arrow-right-long inspect-icon"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050">
        <div id="statusToast" class="toast align-items-center text-white bg-dark border border-secondary" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body font-body" id="toastMessage"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/admin-global.js')) ?>"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/orders.js')) ?>"></script>
</body>
</html>