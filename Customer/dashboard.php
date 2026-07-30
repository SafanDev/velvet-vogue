<?php
// dashboard.php - User Account
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once '../Config/db.php';

$userID = vv_require_logged_in();
$user = [];
$addresses = [];
$orders = [];
$wishlistCount = 0;

try {
    // 1. Fetch User Profile
    $stmt = $pdo->prepare("SELECT userID, firstName, lastName, email, phoneNo, gender, createdAt FROM `user` WHERE userID = ?");
    $stmt->execute([$userID]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header("Location: auth.php");
        exit;
    }

    // 2. Fetch Addresses
    $addrStmt = $pdo->prepare("SELECT addressID, addressLabel, recipientName, street, city, postalCode, country, isDefault FROM useraddress WHERE userID = ? ORDER BY isDefault DESC, addressID DESC");
    $addrStmt->execute([$userID]);
    $addresses = $addrStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch orders and their items in two queries.
    $orderStmt = $pdo->prepare("SELECT orderID, orderNumber, orderStatus, totalPaid, createdAt FROM `order` WHERE userID = ? ORDER BY createdAt DESC");
    $orderStmt->execute([$userID]);
    $orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($orders) {
        $orderIds = array_map(static fn(array $order): int => (int) $order['orderID'], $orders);
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $itemStmt = $pdo->prepare("SELECT orderItemID, orderID, productNameSnap, colorSnap, sizeSnap, quantityBought FROM orderitem WHERE orderID IN ($placeholders) ORDER BY orderItemID");
        $itemStmt->execute($orderIds);

        $itemsByOrder = [];
        foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $itemsByOrder[(int) $item['orderID']][] = $item;
        }

        foreach ($orders as &$order) {
            $order['items'] = $itemsByOrder[(int) $order['orderID']] ?? [];
        }
        unset($order);
    }

    // 4. Fetch Wishlist Count
    $wishStmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE userID = ?");
    $wishStmt->execute([$userID]);
    $wishlistCount = $wishStmt->fetchColumn();

} catch (PDOException $exception) {
    error_log('Customer dashboard failed: ' . $exception->getMessage());
    http_response_code(500);
    $dashboardError = 'Your account could not be loaded. Please try again later.';
}

$page_css = "dashboard.css";
$page_js = "dashboard.js";
include '../ReuseableUI/header.php';
?>

<?php if (!empty($dashboardError)): ?>
    <main class="dashboard-wrapper">
        <div class="container py-5"><div class="alert alert-danger" role="alert"><?= vv_e($dashboardError) ?></div></div>
    </main>
    <?php include '../ReuseableUI/footer.php'; return; ?>
<?php endif; ?>

<main class="dashboard-wrapper">
    <div class="cinematic-grain"></div>

    <div class="container-fluid px-3 px-lg-5 py-5 mt-4">
        <div class="row g-5 position-relative">

            <div class="col-lg-3">
                <div class="dash-sidebar sticky-sidebar gsap-fade-in">

                    <div class="user-id-block mb-4 mb-lg-5 pb-4 border-bottom-dark">
                        <span class="gold-text tracking-luxury d-block mb-2" style="font-size: 0.65rem;">
                            ACCOUNT PROFILE
                        </span>
                        <h2 class="text-white text-uppercase m-0" style="font-family: var(--font-heading); font-size: 1.8rem; letter-spacing: 2px;">
                            <?= htmlspecialchars($user['firstName'] . ' ' . $user['lastName']) ?>
                        </h2>
                        <span class="tracking-luxury mt-2 d-block text-silver" style="font-size: 0.75rem; font-weight: 600;">
                            MEMBER SINCE <?= date('Y', strtotime($user['createdAt'])) ?>
                        </span>
                    </div>

                    <nav class="dash-nav">
                        <button class="dash-nav-btn active" data-target="panel-overview">
                            <i class="fa-solid fa-border-all"></i> <span class="dn-text">OVERVIEW</span>
                        </button>
                        <button class="dash-nav-btn" data-target="panel-profile">
                            <i class="fa-regular fa-user"></i> <span class="dn-text">PROFILE SETTINGS</span>
                        </button>
                        <button class="dash-nav-btn" data-target="panel-orders">
                            <i class="fa-solid fa-bag-shopping"></i> <span class="dn-text">ORDER HISTORY</span>
                        </button>
                        <button class="dash-nav-btn" data-target="panel-addresses">
                            <i class="fa-solid fa-location-dot"></i> <span class="dn-text">ADDRESS BOOK</span>
                        </button>

                        <form method="post" action="../Actions/logout.php" class="mt-lg-5 pt-lg-4 d-none d-lg-block" style="border-top: 1px solid rgba(255,255,255,0.05);">
                            <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">
                            <button type="submit" class="dash-nav-btn text-danger w-100">
                                <i class="fa-solid fa-right-from-bracket"></i> <span class="dn-text">LOG OUT</span>
                            </button>
                        </form>

                        <a href="404.php" class="dash-nav-btn btn-glitch-link text-decoration-none mt-3 d-none d-lg-flex">
                            <i class="fa-solid fa-skull"></i>
                            <span class="dn-text glitch-text-hover" data-text="RESTRICTED SECTOR">RESTRICTED SECTOR</span>
                        </a>
                    </nav>

                </div>
            </div>

            <div class="col-lg-9 ps-lg-5">
                <div class="dash-terminal position-relative gsap-fade-in">

                    <div class="terminal-scan-line"></div>

                    <div class="dash-panel active" id="panel-overview">
                        <div class="panel-header mb-5">
                            <h3 class="panel-title decode-text">DASHBOARD</h3>
                            <p class="panel-subtitle decode-text">Welcome back to your account.</p>
                        </div>

                        <div class="row g-4 mb-5">
                            <div class="col-6 col-md-4">
                                <div class="stat-card">
                                    <i class="fa-solid fa-boxes-stacked stat-icon"></i>
                                    <div class="stat-value decode-text"><?= count($orders) ?></div>
                                    <div class="stat-label decode-text">TOTAL ORDERS</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="stat-card">
                                    <i class="fa-solid fa-location-dot stat-icon"></i>
                                    <div class="stat-value decode-text"><?= count($addresses) ?></div>
                                    <div class="stat-label decode-text">SAVED ADDRESSES</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="stat-card">
                                    <i class="fa-solid fa-heart stat-icon"></i>
                                    <div class="stat-value decode-text"><?= (int) $wishlistCount ?></div>
                                    <div class="stat-label decode-text">WISHLIST ITEMS</div>
                                </div>
                            </div>
                        </div>

                        <div class="recent-activity mt-4 pt-4 border-top-dark">
                            <h4 class="gold-text tracking-luxury mb-4" style="font-size: 0.8rem;">RECENT ORDER</h4>
                            <?php if(!empty($orders)): $latest = $orders[0]; ?>
                                <div class="order-list-item">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                                        <div>
                                            <span class="o-number decode-text font-monospace">ORDER: <?= vv_e($latest['orderNumber']) ?></span>
                                            <span class="o-date decode-text font-monospace text-silver mt-1 d-block"><?= date('M d, Y', strtotime($latest['createdAt'])) ?></span>
                                        </div>
                                        <div class="text-start text-md-end d-flex flex-column align-items-md-end mt-3 mt-md-0 w-100 w-md-auto">
                                            <span class="o-status status-<?= vv_e(strtolower((string) $latest['orderStatus'])) ?> decode-text mb-2"><?= vv_e(strtoupper((string) $latest['orderStatus'])) ?></span>
                                            <span class="o-total gold-text decode-text mt-2 mb-3 d-block">RS. <?= number_format($latest['totalPaid'], 0) ?></span>

                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="track.php?order=<?= urlencode($latest['orderNumber']) ?>" class="btn-track-order flex-grow-1 text-center justify-content-center">
                                                    <i class="fa-solid fa-truck track-icon"></i> <span class="decode-text">TRACK ORDER</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="text-silver decode-text">You haven't placed any orders yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="dash-panel" id="panel-profile">
                        <div class="panel-header mb-5 border-bottom-dark pb-4">
                            <h3 class="panel-title decode-text">PROFILE SETTINGS</h3>
                            <p class="panel-subtitle decode-text">Update your personal information.</p>
                        </div>

                        <form class="vv-form-oversized" style="max-width: 700px;" id="profileForm" method="post">
                            <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">
                            <div class="row g-4">
                                <div class="col-md-6"><div class="vv-floating-group"><input type="text" id="fname_field" class="vv-input decode-val" value="<?= htmlspecialchars($user['firstName']) ?>" placeholder=" "><label class="vv-label">FIRST NAME</label></div></div>
                                <div class="col-md-6"><div class="vv-floating-group"><input type="text" id="lname_field" class="vv-input decode-val" value="<?= htmlspecialchars($user['lastName']) ?>" placeholder=" "><label class="vv-label">LAST NAME</label></div></div>

                                <div class="col-md-6">
                                    <div class="vv-floating-group locked-group">
                                        <input type="email" class="vv-input decode-val" value="<?= htmlspecialchars($user['email']) ?>" placeholder=" " readonly>
                                        <label class="vv-label">EMAIL ADDRESS</label>
                                        <i class="fa-solid fa-lock input-lock-icon"></i>
                                    </div>
                                </div>
                                <div class="col-md-6"><div class="vv-floating-group"><input type="tel" id="phone_field" class="vv-input decode-val" value="<?= htmlspecialchars($user['phoneNo'] ?? '') ?>" placeholder=" "><label class="vv-label">PHONE NUMBER</label></div></div>

                                <div class="col-12 mt-4">
                                    <span class="options-label d-block mb-3" style="font-size: 0.65rem; color: #e0e0e0; letter-spacing: 3px;">GENDER</span>
                                    <div class="d-flex flex-wrap gap-3">
                                        <label class="gender-pill">
                                            <input type="radio" name="gender" value="Male" class="gender-radio" <?= ($user['gender'] ?? '') == 'Male' ? 'checked' : '' ?>>
                                            <span class="gp-text decode-text">MALE</span>
                                        </label>
                                        <label class="gender-pill">
                                            <input type="radio" name="gender" value="Female" class="gender-radio" <?= ($user['gender'] ?? '') == 'Female' ? 'checked' : '' ?>>
                                            <span class="gp-text decode-text">FEMALE</span>
                                        </label>
                                        <label class="gender-pill">
                                            <input type="radio" name="gender" value="Other" class="gender-radio" <?= ($user['gender'] ?? '') == 'Other' ? 'checked' : '' ?>>
                                            <span class="gp-text decode-text">OTHER</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div id="genderErrorMsg" class="mt-4 tracking-luxury" style="display: none; font-size: 0.75rem; font-weight: 700; color: #ff4d4d; border-left: 2px solid #ff4d4d; padding-left: 15px; background: rgba(255, 77, 77, 0.05); padding-top: 10px; padding-bottom: 10px;">
                                <i class="fa-solid fa-triangle-exclamation me-2"></i> ERROR: PLEASE SELECT MALE OR FEMALE.
                            </div>

                            <button type="button" class="btn-outline-gold w-100 w-md-auto px-5 py-3 mt-4" style="font-size: 0.75rem;" id="btnUpdateProfile">SAVE CHANGES</button>
                        </form>

                        <div class="mt-5 pt-5 border-top-dark" style="max-width: 400px;">
                            <h4 class="text-danger tracking-luxury mb-4" style="font-size: 0.75rem;">SECURITY</h4>
                            <div class="vv-floating-group mb-4"><input type="password" id="current_pwd" class="vv-input" placeholder=" "><label class="vv-label">CURRENT PASSWORD</label></div>
                            <div class="vv-floating-group mb-4"><input type="password" id="new_pwd" class="vv-input" placeholder=" "><label class="vv-label">NEW PASSWORD</label></div>
                            <button type="button" class="btn-outline-gold w-100 w-md-auto px-5 py-3" style="font-size: 0.75rem; border-color: #ff4d4d; color: #ff4d4d;" id="btnUpdatePassword" onmouseover="this.style.background='#ff4d4d'; this.style.color='#000';" onmouseout="this.style.background='transparent'; this.style.color='#ff4d4d';">UPDATE PASSWORD</button>
                        </div>
                    </div>

                    <div class="dash-panel" id="panel-orders">
                        <div class="panel-header mb-5 border-bottom-dark pb-4">
                            <h3 class="panel-title decode-text">ORDER HISTORY</h3>
                            <p class="panel-subtitle decode-text">Review your past purchases and track deliveries.</p>
                        </div>

                        <div class="order-history-list">
                            <?php if(empty($orders)): ?>
                                <p class="text-silver decode-text" style="font-family: var(--font-body); font-size: 0.85rem;">You have no previous orders.</p>
                            <?php else: ?>
                                <?php foreach($orders as $order): ?>
                                    <div class="order-list-item mb-4">
                                        <div class="o-header d-flex justify-content-between align-items-center pb-3 border-bottom-dark mb-3 flex-wrap gap-3">
                                            <div>
                                                <span class="o-number decode-text font-monospace fs-5 d-block">ORDER: <?= vv_e($order['orderNumber']) ?></span>
                                                <span class="o-date decode-text font-monospace text-silver mt-1 d-block"><?= date('F j, Y - H:i', strtotime($order['createdAt'])) ?></span>
                                            </div>
                                            <div class="text-start text-md-end d-flex flex-column align-items-start align-items-md-end gap-2 w-100 w-md-auto">
                                                <span class="o-status status-<?= vv_e(strtolower((string) $order['orderStatus'])) ?> decode-text mb-1"><?= vv_e(strtoupper((string) $order['orderStatus'])) ?></span>
                                                <span class="o-total gold-text decode-text fs-5 mb-2">RS. <?= number_format($order['totalPaid'], 0) ?></span>

                                                <div class="d-flex flex-wrap gap-2 w-100">
                                                    <a href="invoice.php?order=<?= urlencode($order['orderNumber']) ?>" class="btn-track-order flex-grow-1 text-center justify-content-center">
                                                        <i class="fa-solid fa-file-invoice track-icon"></i> <span class="decode-text">INVOICE</span>
                                                    </a>
                                                    <a href="track.php?order=<?= urlencode($order['orderNumber']) ?>" class="btn-track-order flex-grow-1 text-center justify-content-center">
                                                        <i class="fa-solid fa-crosshairs track-icon"></i> <span class="decode-text">TRACK</span>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="o-body">
                                            <?php foreach($order['items'] as $item): ?>
                                                <div class="o-product-line mb-3 p-3 rounded d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);">
                                                    <div>
                                                        <span class="op-name text-white d-block mb-1" style="font-family: var(--font-heading); font-size: 1.1rem; text-transform: uppercase;">
                                                            <span class="decode-text"><?= htmlspecialchars($item['productNameSnap']) ?></span>
                                                            <span class="gold-text ms-2 font-monospace" style="font-size:0.8rem;">x<span class="decode-text"><?= (int) $item['quantityBought'] ?></span></span>
                                                        </span>
                                                        <span class="op-meta text-silver decode-text" style="font-size: 0.7rem; letter-spacing: 2px;">COLOR: <?= htmlspecialchars($item['colorSnap']) ?> | SIZE: <?= htmlspecialchars($item['sizeSnap']) ?></span>
                                                    </div>

                                                    <?php if(strtolower($order['orderStatus']) === 'delivered'): ?>
                                                        <div class="w-100 w-md-auto mt-3 mt-md-0">
                                                            <a href="review.php?order_item=<?= $item['orderItemID'] ?? 0 ?>" class="btn-review-product w-100 justify-content-center">
                                                                <i class="fa-solid fa-star review-icon"></i> <span class="decode-text">REVIEW PRODUCT</span>
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="dash-panel" id="panel-addresses">
                        <div class="panel-header mb-5 border-bottom-dark pb-4 d-flex justify-content-between align-items-end flex-wrap gap-3">
                            <div>
                                <h3 class="panel-title decode-text">ADDRESS BOOK</h3>
                                <p class="panel-subtitle decode-text">Manage your saved delivery locations.</p>
                            </div>
                            <button class="btn-outline-gold px-4 py-2 w-100 w-sm-auto" style="font-size: 0.7rem;" onclick="document.getElementById('newAddressBlock').style.display='block'">+ ADD NEW ADDRESS</button>
                        </div>

                        <div class="row g-4">
                            <?php if(empty($addresses)): ?>
                                <p class="text-silver decode-text" style="font-family: var(--font-body); font-size: 0.85rem;">You have no saved addresses.</p>
                            <?php else: ?>
                                <?php foreach($addresses as $addr): ?>
                                    <div class="col-md-6" id="addr-card-<?= (int) $addr['addressID'] ?>">
                                        <div class="address-card <?= $addr['isDefault'] ? 'default-addr' : '' ?>">
                                            <?php if($addr['isDefault']): ?><span class="def-badge decode-text">DEFAULT</span><?php endif; ?>
                                            <h5 class="addr-label decode-text"><?= htmlspecialchars($addr['addressLabel'] ?? 'ADDRESS') ?></h5>
                                            <div class="addr-details mt-3 decode-text text-silver">
                                                <strong class="text-white"><?= htmlspecialchars($addr['recipientName']) ?></strong><br>
                                                <?= htmlspecialchars($addr['street']) ?><br>
                                                <?= htmlspecialchars($addr['city']) ?> <?= htmlspecialchars($addr['postalCode']) ?><br>
                                                <?= htmlspecialchars($addr['country'] ?? 'Sri Lanka') ?>
                                            </div>
                                            <div class="addr-actions mt-4 pt-3 border-top-dark d-flex gap-4">
                                                <button class="btn-text-danger" onclick="removeAddress(<?= (int) $addr['addressID'] ?>)">REMOVE</button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="mt-5 pt-4 border-top-dark" id="newAddressBlock" style="display: none;">
                            <h4 class="gold-text tracking-luxury mb-4" style="font-size: 0.8rem;">NEW ADDRESS</h4>
                            <div class="row g-4">
                                <div class="col-md-6"><div class="vv-floating-group"><input type="text" id="newLabel" class="vv-input" placeholder=" "><label class="vv-label">LABEL (E.G. HOME)</label></div></div>
                                <div class="col-md-6"><div class="vv-floating-group"><input type="text" id="newName" class="vv-input" placeholder=" "><label class="vv-label">RECIPIENT NAME</label></div></div>
                                <div class="col-12"><div class="vv-floating-group"><input type="text" id="newStreet" class="vv-input" placeholder=" "><label class="vv-label">STREET ADDRESS</label></div></div>
                                <div class="col-md-6"><div class="vv-floating-group"><input type="text" id="newCity" class="vv-input" placeholder=" "><label class="vv-label">CITY</label></div></div>
                                <div class="col-md-6"><div class="vv-floating-group"><input type="text" id="newZip" class="vv-input" placeholder=" "><label class="vv-label">POSTAL CODE</label></div></div>
                            </div>
                            <div class="mt-4 d-flex flex-column flex-sm-row gap-3">
                                <button class="btn-outline-gold px-5 py-3" style="font-size: 0.75rem;" onclick="saveNewAddress()">SAVE ADDRESS</button>
                                <button class="btn-text-silver" onclick="document.getElementById('newAddressBlock').style.display='none'">CANCEL</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="col-12 d-block d-lg-none mt-5">
                <form method="post" action="../Actions/logout.php">
                    <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">
                    <button type="submit" class="dash-nav-btn text-danger w-100 justify-content-center" style="border: 1px solid rgba(255,77,77,0.3); padding: 15px;">
                        <i class="fa-solid fa-right-from-bracket me-2"></i> <span class="dn-text">LOG OUT</span>
                    </button>
                </form>
                <a href="404.php" class="dash-nav-btn btn-glitch-link w-100 justify-content-center text-decoration-none mt-3" style="border: 1px dashed rgba(255,77,77,0.2); padding: 15px;">
                    <i class="fa-solid fa-skull me-2"></i>
                    <span class="dn-text glitch-text-hover" data-text="RESTRICTED SECTOR">RESTRICTED SECTOR</span>
                </a>
            </div>

        </div>
    </div>
</main>

<?php include '../ReuseableUI/footer.php'; ?>