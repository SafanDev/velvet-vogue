<?php
// admin/order-view.php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();

require_once '../Config/db.php';
require_once __DIR__ . '/Middleware/AuthMiddleware.php';

AuthMiddleware::requireAdmin();

$orderID = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($orderID === 0) {
    header("Location: orders.php");
    exit;
}

// Handle AJAX status updates for this order.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    vv_enforce_rate_limit('admin-order-view-update', 60, 300, (string) ($_SESSION['userID'] ?? vv_client_ip()));

    $action = (string) ($_POST['action'] ?? '');
    $newStatus = strtolower(trim((string) ($_POST['newStatus'] ?? '')));

    try {
        if ($action === 'update_status') {
            $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];
            if (!in_array($newStatus, $validStatuses, true)) {
                vv_json_response(['status' => 'error', 'message' => 'Invalid order status.'], 422);
            }

            $updateStmt = $pdo->prepare('UPDATE `order` SET orderStatus = ? WHERE orderID = ?');
            $updateStmt->execute([$newStatus, $orderID]);
            vv_json_response(['status' => 'success']);
        }

        if ($action === 'update_payment_status') {
            $validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded'];
            if (!in_array($newStatus, $validPaymentStatuses, true)) {
                vv_json_response(['status' => 'error', 'message' => 'Invalid payment status.'], 422);
            }

            $updateStmt = $pdo->prepare('UPDATE payment SET paymentStatus = ? WHERE orderID = ?');
            $updateStmt->execute([$newStatus, $orderID]);
            if ($updateStmt->rowCount() === 0) {
                vv_json_response(['status' => 'error', 'message' => 'The payment record was not found.'], 404);
            }
            vv_json_response(['status' => 'success']);
        }
    } catch (Throwable $exception) {
        error_log('Admin order detail update failed: ' . $exception->getMessage());
        vv_json_response(['status' => 'error', 'message' => 'The order could not be updated.'], 500);
    }

    vv_json_response(['status' => 'error', 'message' => 'Invalid request.'], 422);
}

// Fetch Order, User & Payment Info (JOINED)
$orderStmt = $pdo->prepare("
    SELECT o.*, u.firstName, u.lastName, u.email, u.phoneNo, COALESCE(p.paymentMethod, 'Not recorded') AS paymentMethod, COALESCE(p.paymentStatus, 'pending') AS paymentStatus
    FROM `order` o
    LEFT JOIN `user` u ON o.userID = u.userID
    LEFT JOIN payment p ON o.orderID = p.orderID
    WHERE o.orderID = ?
");
$orderStmt->execute([$orderID]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    echo 'Order not found.';
    exit;
}

$validOrderStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];
$validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded'];
$order['orderStatus'] = in_array(strtolower((string) $order['orderStatus']), $validOrderStatuses, true)
    ? strtolower((string) $order['orderStatus'])
    : 'pending';
$order['paymentStatus'] = in_array(strtolower((string) $order['paymentStatus']), $validPaymentStatuses, true)
    ? strtolower((string) $order['paymentStatus'])
    : 'pending';

// Fetch Order Items
$itemStmt = $pdo->prepare("
    SELECT oi.*, pv.productID,
    (SELECT filePath FROM productimage pi WHERE pi.productID = pv.productID AND pi.color = oi.colorSnap LIMIT 1) as colorImg,
    (SELECT filePath FROM productimage pi WHERE pi.productID = pv.productID AND pi.isPrimary = 1 LIMIT 1) as primaryImg
    FROM orderitem oi
    LEFT JOIN productvariant pv ON oi.variantID = pv.variantID
    WHERE oi.orderID = ?
");
$itemStmt->execute([$orderID]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

$shippingAddress = json_decode((string) $order['shippingAddressSnap'], true);
if (!is_array($shippingAddress)) {
    $shippingAddress = ['Address' => (string) $order['shippingAddressSnap']];
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
    <title>Order #<?= vv_e($order['orderNumber']) ?> | Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/admin-global.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/order-view.css')) ?>">
</head>
<body>

    <div class="hide-on-print">
        <?php include 'adminheader.php'; ?>
    </div>

    <main class="container-fluid px-xl-5 pt-4 pb-5 hide-on-print" style="max-width: 1500px;">

        <div class="d-flex justify-content-between align-items-end mb-4 pb-3 border-bottom-dark scroll-reveal">
            <div>
                <a href="orders.php" class="back-link mb-2"><i class="fa-solid fa-arrow-left me-2"></i> Back to Ledger</a>
                <h1 class="massive-title m-0">Order <span class="text-gold">#<?= vv_e($order['orderNumber']) ?></span></h1>
                <span class="font-monospace text-silver" style="font-size: 0.9rem;"><?= date('M d, Y \a\t h:i A', strtotime($order['createdAt'])) ?></span>
            </div>

            <div class="d-flex align-items-end gap-4">
                <div class="text-end">
                    <span class="d-block mb-1 font-body text-silver fw-bold" style="font-size: 0.75rem; letter-spacing: 2px; text-transform: uppercase;">Update Status</span>
                    <div class="elegant-select-wrapper">
                        <select id="orderStatusSelect" class="elegant-select status-<?= vv_e($order['orderStatus']) ?>" data-order-id="<?= (int) $order['orderID'] ?>" data-update-type="orderStatus">
                            <option value="pending" <?= $order['orderStatus'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="processing" <?= $order['orderStatus'] == 'processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="shipped" <?= $order['orderStatus'] == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                            <option value="delivered" <?= $order['orderStatus'] == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="cancelled" <?= $order['orderStatus'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            <option value="returned" <?= $order['orderStatus'] == 'returned' ? 'selected' : '' ?>>Returned</option>
                        </select>
                        <i class="fa-solid fa-chevron-down select-arrow"></i>
                    </div>
                </div>

                <button type="button" class="btn-premium-print" onclick="window.print()">
                    <i class="fa-solid fa-print me-2"></i> Print Invoice
                </button>
            </div>
        </div>

        <div class="row g-4 mb-4">

            <div class="col-xl-4 col-lg-6 scroll-reveal">
                <div class="info-card h-100">
                    <h4 class="card-title-gold">Customer Details</h4>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="avatar-circle"><i class="fa-solid fa-user"></i></div>
                        <div>
                            <span class="d-block text-white font-heading fw-bold" style="font-size: 1.1rem; text-transform: uppercase;"><?= vv_e(trim(($order['firstName'] ?? '') . ' ' . ($order['lastName'] ?? ''))) ?></span>
                            <span class="text-silver font-monospace" style="font-size: 0.85rem;">Customer ID: <strong class="text-white"><?= (int) $order['userID'] ?></strong></span>
                        </div>
                    </div>
                    <div class="info-lines mt-4">
                        <p><i class="fa-regular fa-envelope me-3 text-gold"></i> <span class="text-silver"><?= vv_e($order['email'] ?? '') ?></span></p>
                        <p><i class="fa-solid fa-phone me-3 text-gold"></i> <span class="text-silver"><?= vv_e($order['phoneNo'] ?? 'No phone provided') ?></span></p>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-lg-6 scroll-reveal">
                <div class="info-card h-100">
                    <h4 class="card-title-gold">Shipping Address</h4>
                    <div class="address-content">
                        <?php
                        foreach($shippingAddress as $key => $val) {
                            if(!empty($val)) {
                                $isBold = ($key === 'Recipient Name') ? 'text-white fw-bold mb-1 text-uppercase' : 'text-silver';
                                echo "<span class='d-block {$isBold}'>" . vv_e($val) . "</span>";
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-lg-12 scroll-reveal">
                <div class="info-card h-100">
                    <h4 class="card-title-gold">Payment Summary</h4>

                    <?php
                        $payColor = '#cccccc';
                        if($order['paymentStatus'] == 'paid') $payColor = '#2ecc71';
                        if($order['paymentStatus'] == 'failed') $payColor = '#e74c3c';
                        if($order['paymentStatus'] == 'refunded') $payColor = '#f39c12';
                    ?>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-silver font-body text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">Method</span>
                        <span class="text-white font-monospace"><?= vv_e($order['paymentMethod']) ?></span>
                    </div>

                    <div class="d-flex justify-content-between mb-4 pb-3 border-bottom-dark">
                        <span class="text-silver font-body text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">Status</span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="status-dot dot-<?= (int) $order['orderID'] ?>" style="background-color: <?= $payColor ?>; box-shadow: 0 0 8px <?= $payColor ?>;"></span>
                            <select class="elegant-select pay-status-<?= vv_e($order['paymentStatus']) ?> m-0 p-0 h-auto w-auto text-end bg-transparent border-0" data-order-id="<?= (int) $order['orderID'] ?>" data-update-type="paymentStatus" style="font-size: 0.85rem;">
                                <option value="pending" <?= $order['paymentStatus'] == 'pending' ? 'selected' : '' ?>>PENDING</option>
                                <option value="paid" <?= $order['paymentStatus'] == 'paid' ? 'selected' : '' ?>>PAID</option>
                                <option value="failed" <?= $order['paymentStatus'] == 'failed' ? 'selected' : '' ?>>FAILED</option>
                                <option value="refunded" <?= $order['paymentStatus'] == 'refunded' ? 'selected' : '' ?>>REFUNDED</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-auto">
                        <span class="text-white fw-bold text-uppercase" style="font-size: 1rem; letter-spacing: 2px;">Total Paid</span>
                        <span class="text-gold font-heading fw-bold" style="font-size: 1.5rem;">Rs. <?= number_format($order['totalPaid'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">

            <div class="col-xl-8 scroll-reveal">
                <div class="info-card p-0 overflow-hidden h-100 d-flex flex-column">
                    <div class="p-4 border-bottom-dark bg-black-solid">
                        <h4 class="card-title-gold m-0" style="border: none; padding: 0;">Ordered Items</h4>
                    </div>

                    <div class="table-responsive flex-grow-1">
                        <table class="table custom-item-table m-0">
                            <thead>
                                <tr>
                                    <th class="pl-custom">Item Image</th>
                                    <th>Item Description</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end pr-custom">Total Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($items as $item):
                                    $imgSrc = $item['colorImg'] ?: ($item['primaryImg'] ?: '');
                                    $rowTotal = $item['unitPrice'] * $item['quantityBought'];
                                ?>
                                    <tr>
                                        <td class="pl-custom" style="width: 100px;">
                                            <div class="product-thumb">
                                                <?php if($imgSrc): ?>
                                                    <img loading="lazy" decoding="async" src="<?= vv_e(vv_admin_public_url($imgSrc)) ?>" alt="Product Image">
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="d-block text-white fw-bold mb-1 font-heading" style="font-size: 1.1rem; text-transform: uppercase;"><?= htmlspecialchars($item['productNameSnap']) ?></span>
                                            <span class="text-silver font-monospace" style="font-size: 0.85rem;">Size: <strong class="text-white"><?= htmlspecialchars($item['sizeSnap']) ?></strong> &nbsp;|&nbsp; Color: <strong class="text-white"><?= htmlspecialchars($item['colorSnap']) ?></strong></span>
                                            <span class="d-block mt-1 text-silver font-body" style="font-size: 0.8rem;">Unit Price: Rs. <?= number_format($item['unitPrice'], 2) ?></span>
                                        </td>
                                        <td class="text-center text-white fw-bold font-body" style="font-size: 1.1rem;">x<?= $item['quantityBought'] ?></td>
                                        <td class="text-end text-white fw-bold pr-custom font-body" style="font-size: 1.1rem;">Rs. <?= number_format($rowTotal, 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 scroll-reveal">
                <div class="info-card h-100 d-flex flex-column justify-content-center">
                    <h4 class="card-title-gold">Invoice Totals</h4>

                    <div class="d-flex justify-content-between mb-3 mt-3">
                        <span class="text-silver text-uppercase font-body fw-bold" style="font-size: 0.85rem; letter-spacing: 1px;">Subtotal</span>
                        <span class="text-white font-monospace" style="font-size: 1.1rem;">Rs. <?= number_format($order['subTotal'], 2) ?></span>
                    </div>

                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-silver text-uppercase font-body fw-bold" style="font-size: 0.85rem; letter-spacing: 1px;">Shipping</span>
                        <span class="text-white font-monospace" style="font-size: 1.1rem;">Rs. <?= number_format($order['shippingCost'], 2) ?></span>
                    </div>

                    <div class="d-flex justify-content-between mb-4">
                        <span class="text-silver text-uppercase font-body fw-bold" style="font-size: 0.85rem; letter-spacing: 1px;">Taxes</span>
                        <span class="text-white font-monospace" style="font-size: 1.1rem;">Rs. <?= number_format($order['taxAmount'], 2) ?></span>
                    </div>

                    <?php if($order['discountAmount'] > 0): ?>
                    <div class="d-flex justify-content-between mb-4 text-gold">
                        <span class="text-uppercase font-body fw-bold" style="font-size: 0.85rem; letter-spacing: 1px;">Discount</span>
                        <span class="font-monospace fw-bold" style="font-size: 1.1rem;">- Rs. <?= number_format($order['discountAmount'], 2) ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex flex-column mt-auto pt-4 border-top-dark">
                        <span class="text-silver fw-bold text-uppercase mb-2" style="font-size: 0.8rem; letter-spacing: 2px;">Final Authorized Total</span>
                        <span class="text-gold font-heading fw-bold" style="font-size: 2.2rem; line-height: 1;">Rs. <?= number_format($order['totalPaid'], 2) ?></span>
                    </div>
                </div>
            </div>

        </div>

    </main>


    <div class="print-only-invoice">

        <div class="invoice-header">
            <h1 class="print-brand">VELVET VOGUE</h1>
            <p class="print-sub">Official Order Invoice</p>
        </div>

        <div class="invoice-meta">
            <div>
                <strong>Order Ref:</strong> <?= vv_e($order['orderNumber']) ?><br>
                <strong>Date:</strong> <?= date('F j, Y', strtotime($order['createdAt'])) ?>
            </div>
            <div class="text-right">
                <strong>Payment:</strong> <?= vv_e($order['paymentMethod']) ?><br>
                <strong>Status:</strong> <?= vv_e(strtoupper((string) $order['paymentStatus'])) ?>
            </div>
        </div>

        <div class="invoice-addresses">
            <div class="address-box">
                <h4>Billed To</h4>
                <p><strong><?= vv_e(trim(($order['firstName'] ?? '') . ' ' . ($order['lastName'] ?? ''))) ?></strong></p>
                <p><?= vv_e($order['email'] ?? '') ?></p>
                <p><?= htmlspecialchars($order['phoneNo'] ?? '') ?></p>
            </div>
            <div class="address-box text-right">
                <h4>Shipped To</h4>
                <?php
                foreach($shippingAddress as $key => $val) {
                    if(!empty($val)) {
                        $bold = ($key === 'Recipient Name') ? '<strong>' . vv_e($val) . '</strong>' : vv_e($val);
                        echo "<p>{$bold}</p>";
                    }
                }
                ?>
            </div>
        </div>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th style="text-align: left;">Ordered Items</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Unit Price</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($items as $item): ?>
                    <tr>
                        <td style="text-align: left;">
                            <strong><?= htmlspecialchars($item['productNameSnap']) ?></strong><br>
                            <span style="font-size: 0.85em; color: #555;">Size: <?= htmlspecialchars($item['sizeSnap']) ?> | Color: <?= htmlspecialchars($item['colorSnap']) ?></span>
                        </td>
                        <td style="text-align: center; font-weight: bold;">x<?= $item['quantityBought'] ?></td>
                        <td style="text-align: right;">Rs. <?= number_format($item['unitPrice'], 2) ?></td>
                        <td style="text-align: right; font-weight: bold;">Rs. <?= number_format($item['unitPrice'] * $item['quantityBought'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="invoice-totals">
            <div class="tot-row"><span>Subtotal:</span><span>Rs. <?= number_format($order['subTotal'], 2) ?></span></div>
            <div class="tot-row"><span>Shipping:</span><span>Rs. <?= number_format($order['shippingCost'], 2) ?></span></div>
            <div class="tot-row"><span>Taxes:</span><span>Rs. <?= number_format($order['taxAmount'], 2) ?></span></div>
            <?php if($order['discountAmount'] > 0): ?>
                <div class="tot-row"><span>Discount:</span><span>- Rs. <?= number_format($order['discountAmount'], 2) ?></span></div>
            <?php endif; ?>
            <div class="tot-row final-tot"><span>Final Total:</span><span>Rs. <?= number_format($order['totalPaid'], 2) ?></span></div>
        </div>

        <div class="invoice-footer">
            <p>Thank you for shopping with Velvet Vogue.</p>
            <p>Returns & Exchanges valid within 14 days of delivery. Keep this invoice for your records.</p>
        </div>

    </div>

    <div class="position-fixed bottom-0 end-0 p-3 hide-on-print" style="z-index: 1050">
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
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/orders.js')) ?>"></script> </body>
</html>