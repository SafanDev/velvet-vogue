<?php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once __DIR__ . '/../Config/db.php';
require_once __DIR__ . '/Middleware/AuthMiddleware.php';

AuthMiddleware::requireAdmin();

$validDate = static function (mixed $value, string $fallback): string {
    $candidate = is_string($value) ? trim($value) : '';
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $candidate);
    return $date && $date->format('Y-m-d') === $candidate ? $candidate : $fallback;
};

$today = date('Y-m-d');
$startDate = $validDate($_GET['start'] ?? null, date('Y-m-d', strtotime('-30 days')));
$endDate = $validDate($_GET['end'] ?? null, $today);

if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$startObject = new DateTimeImmutable($startDate);
$endObject = new DateTimeImmutable($endDate);
if ($startObject->diff($endObject)->days > 366) {
    $startObject = $endObject->modify('-366 days');
    $startDate = $startObject->format('Y-m-d');
}

$startDateTime = $startDate . ' 00:00:00';
$endDateTime = $endDate . ' 23:59:59';

$csvCell = static function (mixed $value): string {
    $text = (string) $value;
    if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
        return "'" . $text;
    }
    return $text;
};

if (isset($_GET['export'])) {
    $exportType = (string) $_GET['export'];
    $allowedExports = ['financials', 'products', 'orders', 'audience'];
    if (!in_array($exportType, $allowedExports, true)) {
        vv_fail_request('Invalid export type.', 422);
    }

    vv_enforce_rate_limit('admin-report-export', 20, 300, (string) ($_SESSION['userID'] ?? vv_client_ip()));
    header('Content-Type: text/csv; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        vv_fail_request('The export could not be generated.', 500);
    }
    fwrite($output, "\xEF\xBB\xBF");

    if ($exportType === 'financials') {
        header('Content-Disposition: attachment; filename="velvet_vogue_financials_' . $startDate . '_to_' . $endDate . '.csv"');
        fputcsv($output, ['Order Number', 'Date', 'Gross Revenue (Rs)', 'Discount Applied (Rs)', 'Tax Collected (Rs)', 'Net Paid (Rs)', 'Payment Method']);
        $stmt = $pdo->prepare("SELECT o.orderNumber, o.createdAt, o.subTotal, o.discountAmount, o.taxAmount, o.totalPaid, p.paymentMethod FROM `order` o JOIN payment p ON p.orderID = o.orderID WHERE p.paymentStatus = 'paid' AND o.createdAt BETWEEN ? AND ? ORDER BY o.createdAt DESC");
        $stmt->execute([$startDateTime, $endDateTime]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, array_map($csvCell, [$row['orderNumber'], $row['createdAt'], $row['subTotal'], $row['discountAmount'], $row['taxAmount'], $row['totalPaid'], strtoupper((string) $row['paymentMethod'])]));
        }
    } elseif ($exportType === 'products') {
        header('Content-Disposition: attachment; filename="velvet_vogue_top_sellers_' . $startDate . '_to_' . $endDate . '.csv"');
        fputcsv($output, ['Product Name', 'Total Units Sold', 'Total Revenue Generated (Rs)']);
        $stmt = $pdo->prepare("SELECT oi.productNameSnap, SUM(oi.quantityBought) AS qty, SUM(oi.quantityBought * oi.unitPrice) AS rev FROM orderitem oi JOIN `order` o ON oi.orderID = o.orderID JOIN payment p ON p.orderID = o.orderID WHERE p.paymentStatus = 'paid' AND o.createdAt BETWEEN ? AND ? GROUP BY oi.productNameSnap ORDER BY rev DESC");
        $stmt->execute([$startDateTime, $endDateTime]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, array_map($csvCell, [$row['productNameSnap'], $row['qty'], $row['rev']]));
        }
    } elseif ($exportType === 'orders') {
        header('Content-Disposition: attachment; filename="velvet_vogue_orders_' . $startDate . '_to_' . $endDate . '.csv"');
        fputcsv($output, ['Order Number', 'Date', 'Total Paid (Rs)', 'Payment Status', 'Fulfillment Status']);
        $stmt = $pdo->prepare('SELECT o.orderNumber, o.createdAt, o.totalPaid, COALESCE(p.paymentStatus, \'pending\') AS paymentStatus, o.orderStatus FROM `order` o LEFT JOIN payment p ON p.orderID = o.orderID WHERE o.createdAt BETWEEN ? AND ? ORDER BY o.createdAt DESC');
        $stmt->execute([$startDateTime, $endDateTime]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, array_map($csvCell, [$row['orderNumber'], $row['createdAt'], $row['totalPaid'], strtoupper((string) $row['paymentStatus']), strtoupper((string) $row['orderStatus'])]));
        }
    } else {
        header('Content-Disposition: attachment; filename="velvet_vogue_vip_customers_' . $startDate . '_to_' . $endDate . '.csv"');
        fputcsv($output, ['First Name', 'Last Name', 'Email', 'Total Orders', 'Lifetime Spend (Rs)']);
        $stmt = $pdo->prepare("SELECT u.firstName, u.lastName, u.email, COUNT(o.orderID) AS orders, SUM(o.totalPaid) AS spent FROM `user` u JOIN `order` o ON u.userID = o.userID JOIN payment p ON p.orderID = o.orderID WHERE p.paymentStatus = 'paid' AND o.createdAt BETWEEN ? AND ? GROUP BY u.userID, u.firstName, u.lastName, u.email ORDER BY spent DESC");
        $stmt->execute([$startDateTime, $endDateTime]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, array_map($csvCell, [$row['firstName'], $row['lastName'], $row['email'], $row['orders'], $row['spent']]));
        }
    }

    fclose($output);
    exit;
}

$metricsStmt = $pdo->prepare("SELECT COUNT(o.orderID) AS totalOrders, COALESCE(SUM(o.totalPaid), 0) AS totalRevenue, COALESCE(SUM(o.discountAmount), 0) AS totalDiscounts, COALESCE(SUM(o.taxAmount), 0) AS totalTax FROM `order` o JOIN payment p ON p.orderID = o.orderID WHERE p.paymentStatus = 'paid' AND o.createdAt BETWEEN ? AND ?");
$metricsStmt->execute([$startDateTime, $endDateTime]);
$metrics = $metricsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$chartStmt = $pdo->prepare("SELECT DATE(o.createdAt) AS orderDate, SUM(o.totalPaid) AS dailyRevenue FROM `order` o JOIN payment p ON p.orderID = o.orderID WHERE p.paymentStatus = 'paid' AND o.createdAt BETWEEN ? AND ? GROUP BY DATE(o.createdAt) ORDER BY orderDate ASC");
$chartStmt->execute([$startDateTime, $endDateTime]);
$financialChartData = $chartStmt->fetchAll(PDO::FETCH_ASSOC);
$finLabels = [];
$finValues = [];
foreach ($financialChartData as $data) {
    $finLabels[] = date('M d', strtotime((string) $data['orderDate']));
    $finValues[] = (float) $data['dailyRevenue'];
}

$topSellersStmt = $pdo->prepare("SELECT oi.productNameSnap, SUM(oi.quantityBought) AS totalQty, SUM(oi.quantityBought * oi.unitPrice) AS productRev FROM orderitem oi JOIN `order` o ON oi.orderID = o.orderID JOIN payment p ON p.orderID = o.orderID WHERE p.paymentStatus = 'paid' AND o.createdAt BETWEEN ? AND ? GROUP BY oi.productNameSnap ORDER BY productRev DESC LIMIT 5");
$topSellersStmt->execute([$startDateTime, $endDateTime]);
$topSellers = $topSellersStmt->fetchAll(PDO::FETCH_ASSOC);

$lowStockStmt = $pdo->query('SELECT p.productName, pv.skuCode, pv.stockCount, pv.color, pv.size FROM productvariant pv JOIN product p ON pv.productID = p.productID WHERE pv.stockCount <= 5 AND p.isActive = 1 ORDER BY pv.stockCount ASC LIMIT 6');
$lowStockItems = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);
$totalProds = (int) $pdo->query('SELECT COUNT(*) FROM product WHERE isActive = 1')->fetchColumn();

$orderStatsStmt = $pdo->prepare('SELECT orderStatus, COUNT(*) AS cnt FROM `order` WHERE createdAt BETWEEN ? AND ? GROUP BY orderStatus');
$orderStatsStmt->execute([$startDateTime, $endDateTime]);
$orderStatsRaw = $orderStatsStmt->fetchAll(PDO::FETCH_ASSOC);
$orderStatusCounts = ['pending' => 0, 'processing' => 0, 'shipped' => 0, 'delivered' => 0, 'cancelled' => 0, 'returned' => 0];
foreach ($orderStatsRaw as $statusRow) {
    if (array_key_exists((string) $statusRow['orderStatus'], $orderStatusCounts)) {
        $orderStatusCounts[(string) $statusRow['orderStatus']] = (int) $statusRow['cnt'];
    }
}

$pendingOrdersStmt = $pdo->prepare("SELECT orderID, orderNumber, totalPaid, createdAt FROM `order` WHERE orderStatus = 'pending' AND createdAt BETWEEN ? AND ? ORDER BY createdAt DESC LIMIT 6");
$pendingOrdersStmt->execute([$startDateTime, $endDateTime]);
$pendingOrders = $pendingOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

$userStatsStmt = $pdo->prepare("SELECT COUNT(*) FROM `user` WHERE createdAt BETWEEN ? AND ? AND role = 'customer'");
$userStatsStmt->execute([$startDateTime, $endDateTime]);
$newCustomers = (int) $userStatsStmt->fetchColumn();
$totalCustomers = (int) $pdo->query("SELECT COUNT(*) FROM `user` WHERE role = 'customer'")->fetchColumn();

$vipStmt = $pdo->prepare("SELECT u.firstName, u.lastName, SUM(o.totalPaid) AS lifetimeSpend, COUNT(o.orderID) AS orderCount FROM `user` u JOIN `order` o ON u.userID = o.userID JOIN payment p ON p.orderID = o.orderID WHERE p.paymentStatus = 'paid' AND o.createdAt BETWEEN ? AND ? GROUP BY u.userID, u.firstName, u.lastName ORDER BY lifetimeSpend DESC LIMIT 6");
$vipStmt->execute([$startDateTime, $endDateTime]);
$vipCustomers = $vipStmt->fetchAll(PDO::FETCH_ASSOC);

$userGrowthStmt = $pdo->prepare("SELECT DATE(createdAt) AS regDate, COUNT(*) AS dailyUsers FROM `user` WHERE role = 'customer' AND createdAt BETWEEN ? AND ? GROUP BY DATE(createdAt) ORDER BY regDate ASC");
$userGrowthStmt->execute([$startDateTime, $endDateTime]);
$userGrowthData = $userGrowthStmt->fetchAll(PDO::FETCH_ASSOC);
$audLabels = [];
$audValues = [];
foreach ($userGrowthData as $data) {
    $audLabels[] = date('M d', strtotime((string) $data['regDate']));
    $audValues[] = (int) $data['dailyUsers'];
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
    <title>Executive Reports | Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/admin-global.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/reports.css')) ?>">
</head>
<body>

    <?php include 'adminheader.php'; ?>

    <main class="container-fluid px-xl-5 pt-4 pb-5" style="max-width: 1800px;">

        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 pb-3 border-bottom-dark scroll-reveal visible gap-3">
            <div>
                <div class="d-flex align-items-center gap-3 mb-1">
                    <span class="simple-label text-gold m-0">Analytics Engine</span>
                    <span class="time-badge"><i class="fa-regular fa-clock me-2 text-gold"></i> <?= date('M d, Y', strtotime($startDate)) ?> &mdash; <?= date('M d, Y', strtotime($endDate)) ?></span>
                </div>
                <h1 class="massive-title text-white m-0">Executive Reports</h1>
            </div>

            <div class="d-flex gap-3 align-items-end">
                <form method="GET" action="reports.php" class="d-flex gap-2 align-items-end bg-dark-glass p-2 rounded border-dark">
                    <div>
                        <span class="filter-label">Start Date</span>
                        <input type="date" name="start" value="<?= vv_e($startDate) ?>" class="luxury-date-input" required>
                    </div>
                    <div>
                        <span class="filter-label">End Date</span>
                        <input type="date" name="end" value="<?= vv_e($endDate) ?>" class="luxury-date-input" required>
                    </div>
                    <button type="submit" class="btn-filter-icon" title="Apply Filter">
                        <i class="fa-solid fa-arrow-right-long"></i>
                    </button>
                </form>

                <a href="reports.php?start=<?= rawurlencode($startDate) ?>&amp;end=<?= rawurlencode($endDate) ?>&amp;export=financials" id="dynamicExportBtn" class="btn-export-core">
                    <span class="btn-text" id="exportBtnText">Export Financials</span>
                    <i class="fa-solid fa-download btn-icon ms-2"></i>
                </a>
            </div>
        </div>

        <div class="d-flex justify-content-center mb-5 scroll-reveal visible">
            <div class="luxury-tab-group">
                <button class="luxury-tab active" data-target="panel-financials" data-export="financials" data-label="Financials"><i class="fa-solid fa-chart-line me-2"></i> Financials</button>
                <button class="luxury-tab" data-target="panel-products" data-export="products" data-label="Catalog"><i class="fa-solid fa-gem me-2"></i> Catalog</button>
                <button class="luxury-tab" data-target="panel-orders" data-export="orders" data-label="Fulfillment"><i class="fa-solid fa-truck-fast me-2"></i> Fulfillment</button>
                <button class="luxury-tab" data-target="panel-audience" data-export="audience" data-label="Audience"><i class="fa-solid fa-users me-2"></i> Audience</button>
            </div>
        </div>

        <div id="panel-financials" class="report-section active scroll-reveal visible">
            <div class="row g-4 mb-4">
                <div class="col-md-3"><div class="metric-card spotlight-card"><i class="fa-solid fa-sack-dollar metric-icon text-white"></i><div class="metric-info"><span class="metric-label">Gross Revenue</span><span class="metric-value">Rs. <?= number_format($metrics['totalRevenue'] ?? 0) ?></span></div></div></div>
                <div class="col-md-3"><div class="metric-card spotlight-card"><i class="fa-solid fa-tags metric-icon text-cyan"></i><div class="metric-info"><span class="metric-label">Discounts Granted</span><span class="metric-value text-cyan">Rs. <?= number_format($metrics['totalDiscounts'] ?? 0) ?></span></div></div></div>
                <div class="col-md-3"><div class="metric-card spotlight-card"><i class="fa-solid fa-building-columns metric-icon text-danger"></i><div class="metric-info"><span class="metric-label">Tax Collected</span><span class="metric-value text-danger">Rs. <?= number_format($metrics['totalTax'] ?? 0) ?></span></div></div></div>
                <div class="col-md-3"><div class="metric-card spotlight-card"><i class="fa-solid fa-file-invoice-dollar metric-icon text-gold"></i><div class="metric-info"><span class="metric-label">Paid Orders</span><span class="metric-value text-gold"><?= number_format($metrics['totalOrders'] ?? 0) ?> <span style="font-size:0.8rem; color:#888;">Receipts</span></span></div></div></div>
            </div>

            <div class="report-panel">
                <div class="panel-header d-flex justify-content-between align-items-center">
                    <h4 class="panel-title m-0">Revenue Timeline</h4>
                    <span class="text-subtitle-crisp font-monospace">Daily Net Volume</span>
                </div>
                <div class="panel-body p-4 chart-backdrop">
                    <?php if(empty($finValues)): ?>
                        <div class="empty-state"><i class="fa-solid fa-chart-line"></i><span>No Revenue Data</span></div>
                    <?php else: ?>
                        <div class="chart-container"><canvas id="revenueChart"></canvas></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="panel-products" class="report-section d-none">
            <div class="row g-4 mb-4">
                <div class="col-md-6"><div class="metric-card spotlight-card"><i class="fa-solid fa-boxes-stacked metric-icon text-white"></i><div class="metric-info"><span class="metric-label">Total Active Products</span><span class="metric-value"><?= number_format($totalProds) ?></span></div></div></div>
                <div class="col-md-6"><div class="metric-card spotlight-card border-gold-subtle"><i class="fa-solid fa-crown metric-icon text-gold"></i><div class="metric-info"><span class="metric-label">Top Performer (Revenue)</span><span class="metric-value text-gold truncate-text" style="max-width:300px;"><?= !empty($topSellers) ? htmlspecialchars($topSellers[0]['productNameSnap']) : 'N/A' ?></span></div></div></div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="report-panel h-100 flex-column d-flex">
                        <div class="panel-header d-flex justify-content-between align-items-center">
                            <h4 class="panel-title m-0">Top Sellers Podium</h4>
                            <span class="text-subtitle-crisp font-monospace">By Gross Sales</span>
                        </div>
                        <div class="panel-body p-0 flex-grow-1 custom-scrollbar" style="max-height: 400px;">
                            <?php if(empty($topSellers)): ?>
                                <div class="empty-state"><span>No sales data.</span></div>
                            <?php else: ?>
                                <div class="ranking-list">
                                    <?php $rank = 1; foreach($topSellers as $item): ?>
                                        <div class="ranking-item d-flex align-items-center justify-content-between p-4 border-bottom-dark">
                                            <div class="d-flex align-items-center gap-4">
                                                <div class="rank-badge rank-<?= $rank ?>"><?php if($rank == 1) echo '<i class="fa-solid fa-crown"></i>'; else echo "#".$rank; ?></div>
                                                <div>
                                                    <span class="d-block text-white font-heading fw-bold mb-1 fs-6"><?= htmlspecialchars($item['productNameSnap']) ?></span>
                                                    <span class="text-muted font-body" style="font-size: 0.75rem;">Volume Moved: <span class="text-silver fw-bold"><?= $item['totalQty'] ?> Units</span></span>
                                                </div>
                                            </div>
                                            <span class="d-block text-gold font-monospace fw-bold fs-6">Rs. <?= number_format($item['productRev']) ?></span>
                                        </div>
                                    <?php $rank++; endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="report-panel h-100 flex-column d-flex" style="border-color: rgba(231, 76, 60, 0.3);">
                        <div class="panel-header d-flex justify-content-between align-items-center" style="background: rgba(231, 76, 60, 0.05);">
                            <h4 class="panel-title m-0 text-danger">Depletion Alerts</h4>
                            <span class="text-subtitle-crisp font-monospace">Low Stock Variants</span>
                        </div>
                        <div class="panel-body p-0 flex-grow-1 custom-scrollbar" style="max-height: 400px;">
                            <?php if(empty($lowStockItems)): ?>
                                <div class="empty-state"><i class="fa-solid fa-check text-success fs-2 mb-3"></i><span>Inventory Healthy</span></div>
                            <?php else: ?>
                                <div class="ranking-list">
                                    <?php foreach($lowStockItems as $item): ?>
                                        <div class="ranking-item p-4 border-bottom-dark d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="d-block text-white font-heading fw-bold mb-1 fs-6"><?= htmlspecialchars($item['productName']) ?></span>
                                                <span class="text-muted font-monospace" style="font-size: 0.75rem;">SKU: <?= htmlspecialchars($item['skuCode']) ?> &bull; <?= htmlspecialchars($item['color']) ?> / <?= htmlspecialchars($item['size']) ?></span>
                                            </div>
                                            <div class="text-center">
                                                <span class="d-block <?= $item['stockCount'] == 0 ? 'text-danger' : 'text-gold' ?> font-heading fw-bold" style="font-size: 1.5rem; line-height: 1;"><?= $item['stockCount'] ?></span>
                                                <span class="text-muted font-body" style="font-size: 0.6rem; text-transform: uppercase;">In Stock</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="panel-orders" class="report-section d-none">
            <div class="row g-4 mb-4">
                <div class="col-md-3"><div class="metric-card spotlight-card"><i class="fa-regular fa-clock metric-icon text-white"></i><div class="metric-info"><span class="metric-label">Pending</span><span class="metric-value"><?= number_format($orderStatusCounts['pending']) ?></span></div></div></div>
                <div class="col-md-3"><div class="metric-card spotlight-card"><i class="fa-solid fa-box-open metric-icon text-cyan"></i><div class="metric-info"><span class="metric-label">Processing</span><span class="metric-value text-cyan"><?= number_format($orderStatusCounts['processing']) ?></span></div></div></div>
                <div class="col-md-3"><div class="metric-card spotlight-card"><i class="fa-solid fa-truck-fast metric-icon text-gold"></i><div class="metric-info"><span class="metric-label">Shipped</span><span class="metric-value text-gold"><?= number_format($orderStatusCounts['shipped']) ?></span></div></div></div>
                <div class="col-md-3"><div class="metric-card spotlight-card"><i class="fa-solid fa-check-double metric-icon text-success"></i><div class="metric-info"><span class="metric-label">Delivered</span><span class="metric-value text-success"><?= number_format($orderStatusCounts['delivered']) ?></span></div></div></div>
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="report-panel h-100">
                        <div class="panel-header d-flex justify-content-between align-items-center">
                            <h4 class="panel-title m-0">Fulfillment Pipeline</h4>
                            <span class="text-subtitle-crisp font-monospace">Status Distribution</span>
                        </div>
                        <div class="panel-body p-4 chart-backdrop">
                            <div class="chart-container"><canvas id="orderStatusChart"></canvas></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="report-panel h-100 flex-column d-flex border-gold-subtle">
                        <div class="panel-header d-flex justify-content-between align-items-center">
                            <h4 class="panel-title m-0 text-gold">Action Required</h4>
                            <span class="text-subtitle-crisp font-monospace">Pending Orders</span>
                        </div>
                        <div class="panel-body p-0 flex-grow-1 custom-scrollbar" style="max-height: 350px;">
                            <?php if(empty($pendingOrders)): ?>
                                <div class="empty-state"><i class="fa-solid fa-check text-success fs-2 mb-3"></i><span>Queue is clear.</span></div>
                            <?php else: ?>
                                <div class="ranking-list">
                                    <?php foreach($pendingOrders as $order): ?>
                                        <div class="ranking-item p-3 px-4 border-bottom-dark d-flex justify-content-between align-items-center">
                                            <div>
                                                <a href="order-view.php?id=<?= (int) $order['orderID'] ?>" class="text-white fw-bold font-monospace d-block text-decoration-none highlight-hover">#<?= vv_e($order['orderNumber']) ?></a>
                                                <span class="text-muted font-body" style="font-size: 0.7rem;"><?= date('M d, g:i A', strtotime($order['createdAt'])) ?></span>
                                            </div>
                                            <span class="text-gold font-monospace fw-bold">Rs. <?= number_format($order['totalPaid']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="panel-audience" class="report-section d-none">
            <div class="row g-4 mb-4">
                <div class="col-md-6"><div class="metric-card spotlight-card"><i class="fa-solid fa-users metric-icon text-white"></i><div class="metric-info"><span class="metric-label">Lifetime Customers</span><span class="metric-value"><?= number_format($totalCustomers) ?> <span style="font-size:0.8rem; color:#888;">Registered</span></span></div></div></div>
                <div class="col-md-6"><div class="metric-card spotlight-card border-success-subtle"><i class="fa-solid fa-user-plus metric-icon text-success"></i><div class="metric-info"><span class="metric-label">New Customers (Selected Date Range)</span><span class="metric-value text-success">+<?= number_format($newCustomers) ?></span></div></div></div>
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="report-panel h-100">
                        <div class="panel-header d-flex justify-content-between align-items-center">
                            <h4 class="panel-title m-0">Audience Growth</h4>
                            <span class="text-subtitle-crisp font-monospace">Daily Registrations</span>
                        </div>
                        <div class="panel-body p-4 chart-backdrop">
                            <?php if(empty($audValues)): ?>
                                <div class="empty-state"><i class="fa-solid fa-chart-line"></i><span>No Registration Data</span></div>
                            <?php else: ?>
                                <div class="chart-container"><canvas id="audienceChart"></canvas></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="report-panel h-100 flex-column d-flex">
                        <div class="panel-header d-flex justify-content-between align-items-center">
                            <h4 class="panel-title m-0">VIP Clients</h4>
                            <span class="text-subtitle-crisp font-monospace">By Lifetime Value</span>
                        </div>
                        <div class="panel-body p-0 flex-grow-1 custom-scrollbar" style="max-height: 350px;">
                            <?php if(empty($vipCustomers)): ?>
                                <div class="empty-state"><span>No VIP data available.</span></div>
                            <?php else: ?>
                                <div class="ranking-list">
                                    <?php $rank = 1; foreach($vipCustomers as $vip): ?>
                                        <div class="ranking-item p-3 px-4 border-bottom-dark d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="rank-badge rank-<?= $rank ?> fs-6" style="width: 30px; height: 30px;"><?= $rank ?></div>
                                                <div>
                                                    <span class="d-block text-white font-heading fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($vip['firstName'] . ' ' . $vip['lastName']) ?></span>
                                                    <span class="text-muted font-body" style="font-size: 0.7rem;"><?= $vip['orderCount'] ?> Orders</span>
                                                </div>
                                            </div>
                                            <span class="text-gold font-monospace fw-bold" style="font-size: 0.9rem;">Rs. <?= number_format($vip['lifetimeSpend']) ?></span>
                                        </div>
                                    <?php $rank++; endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script>
        const exportBaseUrl = <?= json_encode("reports.php?start=" . rawurlencode($startDate) . "&end=" . rawurlencode($endDate) . "&export=", JSON_UNESCAPED_SLASHES) ?>;

        // Data for Chart 1 (Financials)
        const finLabels = <?= json_encode($finLabels) ?>;
        const finValues = <?= json_encode($finValues) ?>;

        // Data for Chart 2 (Order Statuses)
        const osData = [<?= $orderStatusCounts['pending'] ?>, <?= $orderStatusCounts['processing'] ?>, <?= $orderStatusCounts['shipped'] ?>, <?= $orderStatusCounts['delivered'] ?>, <?= $orderStatusCounts['cancelled'] ?>, <?= $orderStatusCounts['returned'] ?>];

        // Data for Chart 3 (Audience Growth)
        const audLabels = <?= json_encode($audLabels) ?>;
        const audValues = <?= json_encode($audValues) ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/admin-global.js')) ?>"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/reports.js')) ?>"></script>
</body>
</html>