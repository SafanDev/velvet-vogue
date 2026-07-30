<?php
// customer/invoice.php - Order Invoice
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once '../Config/db.php';

$userId = vv_require_logged_in();
$orderNumber = trim((string) ($_GET['order'] ?? ''));
$orderData = null;

if ($orderNumber !== '' && strlen($orderNumber) <= 50 && preg_match('/^[A-Za-z0-9-]+$/', $orderNumber)) {
    $stmt = $pdo->prepare("
        SELECT o.*, p.paymentMethod, p.paymentStatus
        FROM `order` o
        LEFT JOIN payment p ON o.orderID = p.orderID
        WHERE o.orderNumber = ? AND o.userID = ?
    ");
    $stmt->execute([$orderNumber, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        $userStmt = $pdo->prepare("SELECT firstName, lastName, email, phoneNo FROM `user` WHERE userID = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        $itemStmt = $pdo->prepare("SELECT * FROM orderitem WHERE orderID = ?");
        $itemStmt->execute([$order['orderID']]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        $orderData = [
            'orderNumber' => $order['orderNumber'],
            'createdAt' => $order['createdAt'],
            'paymentMethod' => strtoupper((string) ($order['paymentMethod'] ?? 'Not recorded')),
            'subTotal' => $order['subTotal'],
            'shippingCost' => $order['shippingCost'],
            'discountAmount' => $order['discountAmount'] ?? 0,
            'taxAmount' => $order['taxAmount'] ?? 0,
            'totalPaid' => $order['totalPaid'],
            'status' => strtoupper($order['orderStatus']),
            'clientName' => $user['firstName'] . ' ' . $user['lastName'],
            'clientEmail' => $user['email'],
            'shippingAddress' => str_replace(',', "\n", $order['shippingAddressSnap']),
            'items' => []
        ];

        foreach($items as $i) {
            $orderData['items'][] = [
                'name' => $i['productNameSnap'],
                'sku' => 'ART-' . str_pad($i['variantID'] ?? $i['orderItemID'], 4, '0', STR_PAD_LEFT),
                'variant' => $i['colorSnap'] . ' / ' . $i['sizeSnap'],
                'qty' => $i['quantityBought'],
                'unitPrice' => $i['unitPrice'],
                'total' => $i['unitPrice'] * $i['quantityBought']
            ];
        }
    }
}

$page_css = "invoice.css";
$page_js = "invoice.js";
include '../ReuseableUI/header.php';
?>

<main class="invoice-wrapper position-relative">
    <div class="cinematic-grain no-print"></div>

    <div class="container py-5 mt-4 z-2 position-relative">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-5 pb-3 border-bottom-dark no-print action-bar gap-3">
            <a href="dashboard.php" class="btn-text-silver"><i class="fa-solid fa-arrow-left me-2"></i> BACK TO DASHBOARD</a>
            <button onclick="window.print()" class="btn-outline-gold px-5 py-3">
                <i class="fa-solid fa-print me-2"></i> PRINT INVOICE
            </button>
        </div>

        <?php if(!$orderData): ?>
            <div class="text-center py-5 no-print">
                <i class="fa-solid fa-file-circle-xmark text-silver mb-3" style="font-size: 3rem;"></i>
                <h2 class="text-white mb-3" style="font-family: var(--font-heading);">INVOICE NOT FOUND</h2>
                <p class="text-silver mb-4">We couldn't locate this document. Please verify your order number.</p>
                <a href="dashboard.php" class="btn-outline-gold px-4 py-2 d-inline-block">RETURN TO DASHBOARD</a>
            </div>
        <?php else: ?>
            <div class="invoice-document mx-auto gsap-fade-in position-relative">

                <div class="inv-corner tl no-print"></div><div class="inv-corner tr no-print"></div>
                <div class="inv-corner bl no-print"></div><div class="inv-corner br no-print"></div>

                <div class="inv-watermark no-print">AUTHENTIC</div>

                <div class="inv-header print-block">
                    <div class="brand-col">
                        <h1 class="brand-logo mb-1 decode-text">VELVET VOGUE</h1>
                        <span class="tracking-luxury text-silver d-block" style="font-size: 0.65rem;">HAUTE COUTURE // OFFICIAL RECEIPT</span>
                    </div>
                    <div class="meta-col text-md-end mt-4 mt-md-0">
                        <span class="d-block text-silver tracking-luxury mb-1" style="font-size: 0.65rem;">DOCUMENT TYPE</span>
                        <h2 class="tracking-luxury gold-text mb-1" style="font-size: 1.1rem;">ORDER INVOICE</h2>
                        <span class="font-monospace fw-bold inv-id" style="font-size: 1.1rem; letter-spacing: 2px;">#<?= htmlspecialchars($orderData['orderNumber']) ?></span>
                    </div>
                </div>

                <div class="details-grid print-block mb-4">
                    <div class="details-cell">
                        <span class="details-label">CUSTOMER DETAILS</span>
                        <strong class="details-value d-block mt-1 text-uppercase"><?= htmlspecialchars($orderData['clientName']) ?></strong>
                        <span class="details-desc d-block mt-1 text-lowercase"><?= htmlspecialchars($orderData['clientEmail']) ?></span>
                    </div>
                    <div class="details-cell">
                        <span class="details-label">SHIPPING ADDRESS</span>
                        <span class="details-desc d-block mt-1 font-monospace text-uppercase" style="white-space: pre-line; line-height: 1.6; color: #fff;"><?= htmlspecialchars($orderData['shippingAddress']) ?></span>
                    </div>
                    <div class="details-cell">
                        <span class="details-label">ORDER DATE</span>
                        <span class="details-value d-block mt-1 font-monospace"><?= date('M d, Y', strtotime($orderData['createdAt'])) ?></span>
                        <span class="details-desc d-block mt-1 font-monospace text-silver"><?= date('H:i:s T', strtotime($orderData['createdAt'])) ?></span>
                    </div>
                    <div class="details-cell">
                        <span class="details-label">PAYMENT INFO</span>
                        <span class="details-value d-block mt-1 font-monospace"><?= htmlspecialchars($orderData['paymentMethod']) ?></span>
                        <span class="details-desc d-block mt-1 text-uppercase" style="color: var(--color-gold-metallic); letter-spacing: 2px; font-weight: 700;"><?= htmlspecialchars($orderData['status']) ?></span>
                    </div>
                </div>

                <div class="inv-table-wrapper print-block mb-4">
                    <table class="inv-table w-100">
                        <thead>
                            <tr>
                                <th class="text-start" style="width: 50%;">ITEM DESCRIPTION</th>
                                <th class="text-center d-none d-sm-table-cell" style="width: 15%;">QTY</th>
                                <th class="text-end d-none d-sm-table-cell" style="width: 15%;">UNIT PRICE</th>
                                <th class="text-end" style="width: 20%;">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($orderData['items'] as $item): ?>
                            <tr>
                                <td class="text-start">
                                    <span class="d-block inv-item-name text-uppercase decode-text"><?= htmlspecialchars($item['name']) ?></span>
                                    <span class="d-block inv-item-meta font-monospace mt-1 text-silver">SKU: <?= htmlspecialchars($item['sku']) ?> &nbsp;|&nbsp; <?= htmlspecialchars($item['variant']) ?></span>
                                    <span class="d-block d-sm-none inv-item-meta font-monospace mt-2 text-silver">QTY: <?= $item['qty'] ?> &times; RS. <?= number_format($item['unitPrice'], 0) ?></span>
                                </td>
                                <td class="text-center font-monospace d-none d-sm-table-cell"><?= $item['qty'] ?></td>
                                <td class="text-end font-monospace text-silver d-none d-sm-table-cell">RS. <?= number_format($item['unitPrice'], 0) ?></td>
                                <td class="text-end font-monospace text-white">RS. <?= number_format($item['total'], 0) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="inv-totals-wrapper print-block mb-4">
                    <div class="math-breakdown">
                        <div class="math-line">
                            <span class="sl-label">SUBTOTAL</span>
                            <span class="sl-value font-monospace">RS. <?= number_format($orderData['subTotal'], 0) ?></span>
                        </div>
                        <div class="math-line">
                            <span class="sl-label">SHIPPING</span>
                            <span class="sl-value font-monospace"><?= $orderData['shippingCost'] == 0 ? 'FREE' : 'RS. ' . number_format($orderData['shippingCost'], 0) ?></span>
                        </div>
                        <?php if($orderData['discountAmount'] > 0): ?>
                        <div class="math-line">
                            <span class="sl-label gold-text">DISCOUNT</span>
                            <span class="sl-value font-monospace gold-text">- RS. <?= number_format($orderData['discountAmount'], 0) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="math-line border-bottom-dark pb-3 mb-3">
                            <span class="sl-label">TAXES</span>
                            <span class="sl-value font-monospace">RS. <?= number_format($orderData['taxAmount'], 0) ?></span>
                        </div>
                        <div class="math-line pt-2">
                            <span class="tracking-luxury text-white" style="font-size: 1rem;">TOTAL PAID</span>
                            <span class="font-monospace fw-bold gold-text inv-total-paid" style="font-size: 1.5rem;">RS. <?= number_format($orderData['totalPaid'], 0) ?></span>
                        </div>
                    </div>
                </div>

                <div class="inv-footer print-block border-top-dark pt-4 position-relative z-2">
                    <div class="dynamic-barcode" id="digitalBarcode"></div>
                    <div class="text-md-end mt-3 mt-md-0">
                        <span class="d-block font-monospace text-silver mb-1" style="font-size: 0.55rem; letter-spacing: 2px;">SECURE TRANSACTION HASH</span>
                        <span class="d-block font-monospace text-white" style="font-size: 0.65rem; word-break: break-all; opacity: 0.8;" id="cryptoHash" data-original="0x<?= hash('sha256', $orderData['orderNumber'] . $orderData['createdAt']) ?>">
                            0x<?= hash('sha256', $orderData['orderNumber'] . $orderData['createdAt']) ?>
                        </span>
                    </div>
                </div>

            </div>
        <?php endif; ?>
    </div>
</main>

<?php include '../ReuseableUI/footer.php'; ?>