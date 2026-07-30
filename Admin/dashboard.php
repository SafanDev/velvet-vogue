<?php
// admin/dashboard.php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();

require_once '../Config/db.php';
require_once __DIR__ . '/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/Services/DashboardService.php';

AuthMiddleware::requireAdmin();

$dashboardService = new DashboardService($pdo);
$d = $dashboardService->getDashboardData();

$maxAssetRev = 0;
if(!empty($d['topProducts'])) {
    foreach($d['topProducts'] as $prod) {
        if($prod['totalRevenue'] > $maxAssetRev) $maxAssetRev = $prod['totalRevenue'];
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
    <title>Command Center | Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/admin-global.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/dashboard.css')) ?>">
</head>
<body>

    <?php include 'adminheader.php'; ?>

    <main class="container-fluid px-xl-5 pt-2 pb-5" style="max-width: 1800px;">

        <div class="row main-stage-row g-4 mb-5 align-items-stretch">

            <div class="col-xl-9 col-lg-8 d-flex flex-column gap-4 left-column-wrapper">

                <section class="hero-stage-container carve-box position-relative">
                    <div class="laser-trace"></div>

                    <div class="hero-panel active" data-id="revenue">
                        <div class="holographic-halo halo-gold"></div>
                        <div class="row align-items-center h-100 m-0 position-relative z-2 w-100">
                            <div class="col-md-5 p-5 d-flex flex-column justify-content-center">
                                <span class="simple-label"><i class="fa-solid fa-sack-dollar text-gold me-2"></i> Global Yield</span>
                                <div class="d-flex align-items-baseline gap-2 mt-2 mb-3">
                                    <span class="currency-tag text-silver">Rs.</span>
                                    <h1 class="massive-num text-gold counter-value" data-target="<?= $d['totalRevenue'] ?>">0</h1>
                                </div>
                                <div class="d-flex gap-5 mt-auto pt-4">
                                    <div class="mini-data-block">
                                        <span class="md-label text-light-silver">TODAY'S YIELD</span>
                                        <span class="md-val text-white">Rs. <?= number_format($d['todayRevenue']) ?></span>
                                    </div>
                                    <div class="mini-data-block">
                                        <span class="md-label text-light-silver">TODAY'S SALES</span>
                                        <span class="md-val text-white"><?= number_format($d['todaySales']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-7 p-0 h-100">
                                <div class="chart-wrapper p-4 h-100 w-100 d-flex flex-column justify-content-end position-relative">
                                    <span class="position-absolute top-0 end-0 m-4 text-light-silver font-monospace" style="font-size:0.75rem; letter-spacing: 2px;">LAST 7 DAYS</span>
                                    <canvas id="revenueChart" style="max-height: 250px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="hero-panel" data-id="orders">
                        <div class="holographic-halo halo-silver"></div>
                        <div class="row align-items-center h-100 m-0 position-relative z-2 w-100">
                            <div class="col-md-5 p-5 d-flex flex-column justify-content-center h-100">
                                <span class="simple-label"><i class="fa-solid fa-barcode text-cyan me-2"></i> Transmissions</span>
                                <h1 class="massive-num text-cyan mt-2 mb-4 counter-value" data-target="<?= $d['totalOrders'] ?>">0</h1>
                                <a href="orders.php" class="btn-stealth mt-auto" style="width: fit-content; border-color: var(--color-cyan) !important; color: var(--color-cyan) !important;">Manage Orders</a>
                            </div>
                            <div class="col-md-7 p-5 h-100 d-flex align-items-center">
                                <div class="w-100 p-4 border border-secondary rounded" style="background: rgba(255,255,255,0.02);">
                                    <h6 class="text-white mb-4 tracking-widest font-monospace" style="font-size:0.8rem;">ORDER STATUS</h6>

                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-light-silver" style="font-size: 0.9rem;">Pending Dispatch</span>
                                        <span class="text-warning fw-bold fs-5"><?= $d['pendingOrders'] ?></span>
                                    </div>
                                    <div class="progress mb-4" style="height: 6px; background: rgba(255,255,255,0.1);">
                                        <div class="progress-bar bg-warning progress-glow-warning" style="width: <?= ($d['totalOrders']>0) ? ($d['pendingOrders']/$d['totalOrders'])*100 : 0 ?>%;"></div>
                                    </div>

                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-light-silver" style="font-size: 0.9rem;">Shipped</span>
                                        <span class="text-info fw-bold fs-5"><?= $d['shippedOrders'] ?></span>
                                    </div>
                                    <div class="progress mb-4" style="height: 6px; background: rgba(255,255,255,0.1);">
                                        <div class="progress-bar bg-info" style="width: <?= ($d['totalOrders']>0) ? ($d['shippedOrders']/$d['totalOrders'])*100 : 0 ?>%;"></div>
                                    </div>

                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-light-silver" style="font-size: 0.9rem;">Delivered</span>
                                        <span class="text-success fw-bold fs-5"><?= $d['deliveredOrders'] ?></span>
                                    </div>
                                    <div class="progress" style="height: 6px; background: rgba(255,255,255,0.1);">
                                        <div class="progress-bar bg-success" style="width: <?= ($d['totalOrders']>0) ? ($d['deliveredOrders']/$d['totalOrders'])*100 : 0 ?>%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="hero-panel" data-id="users">
                        <div class="holographic-halo halo-gold"></div>
                        <div class="row align-items-center h-100 m-0 position-relative z-2 w-100">
                            <div class="col-md-5 p-5 d-flex flex-column justify-content-center h-100">
                                <span class="simple-label"><i class="fa-solid fa-users text-gold me-2"></i> Clientele</span>
                                <h1 class="massive-num text-gold mt-2 mb-4 counter-value" data-target="<?= $d['totalCustomers'] ?>">0</h1>
                                <a href="users.php" class="btn-stealth mt-auto" style="width: fit-content;">View Roster</a>
                            </div>
                            <div class="col-md-7 p-5 d-flex justify-content-between align-items-center h-100">
                                <div class="neural-network-visual">
                                    <div class="nn-core"></div><div class="nn-ring nn-ring-1"></div><div class="nn-ring nn-ring-2"></div><div class="nn-ring nn-ring-3"></div>
                                    <div class="nn-node node-1"></div><div class="nn-node node-2"></div><div class="nn-node node-3"></div><div class="nn-node node-4"></div>
                                </div>
                                <div class="text-end ms-auto">
                                    <h3 class="text-white mb-2 font-monospace" style="letter-spacing: 2px;">NETWORK SECURE</h3>
                                    <span class="text-gold fw-bold" style="font-size: 1.2rem;"><?= number_format($d['activeCustomers']) ?> ACTIVE</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="hero-panel" data-id="inventory">
                        <div class="holographic-halo halo-silver"></div>
                        <div class="row align-items-center h-100 m-0 position-relative z-2 w-100">
                            <div class="col-md-5 p-5 d-flex flex-column justify-content-center h-100">
                                <span class="simple-label"><i class="fa-solid fa-boxes-stacked text-white me-2"></i> Vault Assets</span>
                                <h1 class="massive-num text-white mt-2 mb-4 counter-value" data-target="<?= $d['totalProducts'] ?>">0</h1>
                                <a href="products.php" class="btn-stealth mt-auto" style="width: fit-content; border-color: var(--color-purple) !important; color: var(--color-purple) !important;">Manage Vault</a>
                            </div>
                            <div class="col-md-7 p-5 h-100 d-flex align-items-center gap-4">
                                <div class="matrix-grid-visual">
                                    <?php for($i=0; $i<16; $i++): ?>
                                        <div class="mg-cell <?= rand(0,10)>7 ? 'mg-active' : '' ?>"></div>
                                    <?php endfor; ?>
                                </div>
                                <div class="w-100 p-4 border border-secondary rounded" style="background: rgba(255,255,255,0.02);">
                                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom-dark pb-3">
                                        <span class="text-light-silver font-monospace">LOW STOCK (<5)</span>
                                        <span class="text-warning fw-bold fs-3"><?= $d['lowStockItems'] ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-light-silver font-monospace">OUT OF STOCK</span>
                                        <span class="text-danger fw-bold fs-3"><?= $d['outOfStockItems'] ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="hero-panel" data-id="tickets">
                        <div class="holographic-halo halo-red"></div>
                        <div class="row align-items-center h-100 m-0 position-relative z-2 w-100">
                            <div class="col-md-5 p-5 d-flex flex-column justify-content-center h-100">
                                <span class="simple-label"><i class="fa-solid fa-envelope text-danger-muted me-2"></i> Open Tickets</span>
                                <h1 class="massive-num text-white mt-2 mb-4 counter-value" data-target="<?= $d['pendingInquiries'] ?>">0</h1>
                                <div class="mini-data-block mt-auto">
                                    <span class="md-label text-light-silver">RESOLVED TICKETS</span>
                                    <span class="md-val text-success"><?= number_format($d['resolvedInquiries']) ?></span>
                                </div>
                            </div>
                            <div class="col-md-7 p-0 h-100">
                                <?php if($d['pendingInquiries'] == 0): ?>
                                    <div class="d-flex align-items-center justify-content-center h-100 p-5 border-left-dark">
                                        <div class="text-center text-success border border-success p-4 rounded w-100" style="background: rgba(29, 209, 161, 0.05);">
                                            <i class="fa-regular fa-circle-check fs-1 mb-3"></i>
                                            <h4>All Clear</h4>
                                            <p class="m-0 text-light-silver">No pending messages in the queue.</p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="emergency-panel h-100 d-flex flex-column justify-content-center align-items-end p-5 text-end">
                                        <i class="fa-solid fa-triangle-exclamation fs-1 text-danger-muted mb-3 shadow-glow-red"></i>
                                        <h3 class="text-white mt-2 font-monospace tracking-widest">ACTION REQUIRED</h3>
                                        <p class="text-light-silver mb-4">You have <?= $d['pendingInquiries'] ?> ticket(s) waiting.</p>
                                        <a href="inquiries.php" class="btn-stealth btn-stealth-red">Resolve Now</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="grid-4-cols" id="tileRowContainer">

                    <div class="tile-wrapper carve-box" data-id="revenue" data-order="1" style="display: none;">
                        <div class="data-tile">
                            <div class="tile-hover-scanner"></div>
                            <div class="tile-content">
                                <span class="tile-title"><i class="fa-solid fa-sack-dollar text-gold me-2"></i> REVENUE</span>
                                <h2 class="tile-num text-gold">Rs.<?= number_format($d['totalRevenue']/1000, 1) ?>k</h2>
                            </div>
                            <div class="tile-reveal border-top-gold">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-light-silver font-monospace" style="font-size: 0.65rem;">TODAY</span>
                                    <span class="text-white font-monospace fw-bold" style="font-size: 0.85rem;">Rs.<?= number_format($d['todayRevenue']/1000, 1) ?>k</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tile-wrapper carve-box active-tile" data-id="orders" data-order="2">
                        <div class="data-tile">
                            <div class="tile-hover-scanner"></div>
                            <div class="tile-content">
                                <span class="tile-title"><i class="fa-solid fa-barcode text-cyan me-2"></i> ORDERS</span>
                                <h2 class="tile-num text-white"><?= number_format($d['totalOrders']) ?></h2>
                            </div>
                            <div class="tile-reveal border-top-cyan">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-light-silver font-monospace" style="font-size: 0.65rem;">PENDING</span>
                                    <span class="text-gold font-monospace fw-bold" style="font-size: 0.85rem;"><?= $d['pendingOrders'] ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tile-wrapper carve-box active-tile" data-id="users" data-order="3">
                        <div class="data-tile">
                            <div class="tile-hover-scanner"></div>
                            <div class="tile-content">
                                <span class="tile-title"><i class="fa-solid fa-users text-white me-2"></i> CLIENTS</span>
                                <h2 class="tile-num text-white"><?= number_format($d['totalCustomers']) ?></h2>
                            </div>
                            <div class="tile-reveal border-top-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-light-silver font-monospace" style="font-size: 0.65rem;">ACTIVE</span>
                                    <span class="text-gold font-monospace fw-bold" style="font-size: 0.85rem;"><?= $d['activeCustomers'] ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tile-wrapper carve-box active-tile" data-id="inventory" data-order="4">
                        <div class="data-tile">
                            <div class="tile-hover-scanner"></div>
                            <div class="tile-content">
                                <span class="tile-title"><i class="fa-solid fa-boxes-stacked text-purple me-2"></i> ASSETS</span>
                                <h2 class="tile-num text-purple"><?= number_format($d['totalProducts']) ?></h2>
                            </div>
                            <div class="tile-reveal border-top-purple">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-light-silver font-monospace" style="font-size: 0.65rem;">LOW STOCK</span>
                                    <span class="text-danger font-monospace fw-bold" style="font-size: 0.85rem;"><?= $d['lowStockItems'] ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tile-wrapper carve-box active-tile" data-id="tickets" data-order="5">
                        <div class="data-tile <?= $d['pendingInquiries'] > 0 ? 'alert-tile' : '' ?>">
                            <div class="tile-hover-scanner"></div>
                            <div class="tile-content">
                                <span class="tile-title"><i class="fa-solid fa-envelope text-danger-muted me-2"></i> TICKETS</span>
                                <h2 class="tile-num <?= $d['pendingInquiries'] > 0 ? 'text-danger-muted' : 'text-white' ?>"><?= number_format($d['pendingInquiries']) ?></h2>
                            </div>
                            <div class="tile-reveal <?= $d['pendingInquiries'] > 0 ? 'border-top-danger' : 'border-top-white' ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-light-silver font-monospace" style="font-size: 0.65rem;">RESOLVED</span>
                                    <span class="text-success font-monospace fw-bold" style="font-size: 0.85rem;"><?= $d['resolvedInquiries'] ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                </section>
            </div>

            <div class="col-xl-3 col-lg-4 d-flex">
                <section class="ledger-section carve-box w-100 position-relative d-flex flex-column">
                    <div class="laser-trace-vertical"></div>
                    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom-dark">
                        <span class="simple-label m-0" style="letter-spacing: 2px;">Recent Activity</span>
                        <a href="orders.php" class="btn-stealth" style="padding: 6px 12px; font-size: 0.65rem; border: none !important;">Archive</a>
                    </div>

                    <div class="ledger-stack flex-grow-1" style="justify-content: flex-start;">
                        <?php if(empty($d['recentOrders'])): ?>
                            <div class="text-center text-light-silver font-monospace py-5">NO DATA</div>
                        <?php else: ?>
                            <?php foreach($d['recentOrders'] as $order): ?>
                                <a href="orders.php" class="ledger-entry">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="entry-id font-monospace">#<?= htmlspecialchars($order['orderNumber']) ?></span>
                                        <span class="entry-price text-gold fw-bold">RS. <?= number_format($order['totalPaid'], 0) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="entry-date text-light-silver font-monospace"><?= date('H:i | M d', strtotime($order['createdAt'])) ?></span>
                                        <div class="entry-status"><span class="status-pill status-<?= vv_e(strtolower((string) $order['orderStatus'])) ?>"><?= vv_e(strtoupper((string) $order['orderStatus'])) ?></span></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>

        <div class="row g-4 mt-2">

            <div class="col-xl-5 col-lg-6">
                <div class="bi-panel bi-assets scroll-reveal">
                    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom-dark">
                        <span class="simple-label text-gold m-0"><i class="fa-solid fa-ranking-star text-gold me-2"></i> Top Performing Assets</span>
                    </div>
                    <div class="asset-list">
                        <?php if(empty($d['topProducts'])): ?>
                            <p class="text-muted font-monospace text-center py-4">Awaiting Sales Data</p>
                        <?php else: ?>
                            <?php foreach($d['topProducts'] as $index => $prod):
                                $percent = $maxAssetRev > 0 ? ($prod['totalRevenue'] / $maxAssetRev) * 100 : 0;
                            ?>
                                <div class="asset-row position-relative py-3 border-bottom-dark">
                                    <div class="asset-bg-bar" style="width: <?= $percent ?>%;"></div>
                                    <div class="d-flex justify-content-between align-items-center position-relative z-2 px-2">
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="text-muted font-monospace">0<?= $index + 1 ?></span>
                                            <span class="text-white fw-bold" style="font-size: 0.85rem;"><?= htmlspecialchars($prod['productNameSnap']) ?></span>
                                        </div>
                                        <div class="text-end">
                                            <span class="d-block text-gold fw-bold font-monospace">Rs. <?= number_format($prod['totalRevenue']) ?></span>
                                            <span class="text-light-silver" style="font-size: 0.7rem;"><?= $prod['totalSold'] ?> Units</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-lg-6">
                <div class="bi-panel bi-growth scroll-reveal h-100 d-flex flex-column position-relative overflow-hidden">
                    <div class="growth-bg-grid"></div>
                    <div class="position-relative z-2 h-100 d-flex flex-column">
                        <div class="mb-4 pb-3 border-bottom-dark">
                            <span class="simple-label text-silver m-0"><i class="fa-solid fa-chart-line text-silver me-2"></i> Growth & Retention</span>
                        </div>
                        <div class="flex-grow-1 d-flex flex-column justify-content-around">

                            <div class="bi-inner-row d-flex justify-content-between align-items-end mb-3 p-3">
                                <div>
                                    <span class="text-light-silver font-monospace d-block mb-1" style="font-size: 0.7rem;">MoM REVENUE GROWTH</span>
                                    <span class="massive-num text-white counter-value growth-num" style="font-size: 2.5rem;" data-target="<?= abs($d['growthPercentage']) ?>">0</span><span class="text-white fs-4">%</span>
                                </div>
                                <?php if($d['growthPercentage'] >= 0): ?>
                                    <span class="trend-badge positive mb-2"><i class="fa-solid fa-arrow-trend-up me-2"></i> UP</span>
                                <?php else: ?>
                                    <span class="trend-badge negative mb-2"><i class="fa-solid fa-arrow-trend-down me-2"></i> DOWN</span>
                                <?php endif; ?>
                            </div>

                            <div class="bi-inner-row d-flex justify-content-between align-items-center p-3 mb-3">
                                <span class="text-light-silver font-monospace" style="font-size: 0.75rem;">AVERAGE ORDER VALUE</span>
                                <span class="text-gold fw-bold font-monospace" style="font-size: 1.1rem;">Rs. <?= number_format($d['aov']) ?></span>
                            </div>

                            <div class="bi-inner-row d-flex justify-content-between align-items-center p-3">
                                <span class="text-light-silver font-monospace" style="font-size: 0.75rem;">CLIENT RETENTION RATE</span>
                                <span class="text-white fw-bold font-monospace" style="font-size: 1.1rem;"><?= $d['retentionRate'] ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-12">
                <div class="bi-panel bi-vault scroll-reveal h-100 d-flex flex-column position-relative overflow-hidden">
                    <div class="vault-ring-bg"></div>
                    <div class="position-relative z-2 h-100 d-flex flex-column">
                        <div class="mb-4 pb-3 border-bottom-dark">
                            <span class="simple-label text-gold m-0"><i class="fa-solid fa-building-columns text-gold me-2"></i> Vault Economics</span>
                        </div>

                        <div class="vault-value-card flex-grow-1 d-flex flex-column justify-content-center text-center p-4 rounded mb-4">
                            <span class="text-light-silver font-monospace tracking-widest mb-2" style="font-size: 0.7rem;">TOTAL INVENTORY VALUE</span>
                            <h2 class="text-gold fw-bold font-monospace massive-num m-0 counter-value" style="font-size: 2.2rem;" data-target="<?= $d['totalInventoryValue'] ?>">0</h2>
                        </div>

                        <div class="bi-inner-row d-flex justify-content-between align-items-center p-3">
                            <span class="text-light-silver font-monospace" style="font-size: 0.75rem;">REFUNDED CAPITAL</span>
                            <span class="text-white fw-bold font-monospace" style="font-size: 1rem;">Rs. <?= number_format($d['refundedAmount']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <script>
        const realChartLabels = <?= json_encode($d['chartLabels']) ?>;
        const realChartValues = <?= json_encode($d['chartValues']) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/admin-global.js')) ?>"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/dashboard.js')) ?>"></script>
</body>
</html>