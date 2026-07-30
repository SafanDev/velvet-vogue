<?php
// admin/products.php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();

// Config and Middleware
require_once '../Config/db.php';
require_once __DIR__ . '/Middleware/AuthMiddleware.php';

AuthMiddleware::requireAdmin();

// =======================================================
// FETCH PRODUCTS
// =======================================================
$query = "
    SELECT
        p.productID,
        p.productName,
        p.slug,
        p.basePrice,
        p.isActive,
        p.gender,
        c.categoryName,
        (SELECT SUM(stockCount) FROM productvariant pv WHERE pv.productID = p.productID) as totalStock,
        (SELECT skuCode FROM productvariant pv WHERE pv.productID = p.productID LIMIT 1) as sampleSku,
        (SELECT filePath FROM productimage pi WHERE pi.productID = p.productID AND pi.isPrimary = 1 LIMIT 1) as primaryImage
    FROM product p
    LEFT JOIN category c ON p.categoryID = c.categoryID
    ORDER BY p.createdAt DESC
";
$products = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals for UX display
$totalProducts = count($products);

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
    <title>Products | Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/admin-global.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/products.css')) ?>">
</head>
<body>

    <?php include 'adminheader.php'; ?>

    <main class="container-fluid px-xl-5 pt-4 pb-5" style="max-width: 1800px;">

        <section class="inventory-container top-glow-card position-relative">

            <div class="editorial-watermark">V</div>

            <div class="d-flex justify-content-between align-items-end mb-5 border-bottom-dark pb-4 position-relative z-2">
                <div>
                    <div class="d-flex align-items-center gap-3 mb-1">
                        <span class="simple-label text-gold m-0">Inventory Database</span>
                        <span class="badge-count" id="totalProductsBadge"><?= $totalProducts ?> Registered</span>
                    </div>
                    <h1 class="massive-title text-white m-0">Products</h1>
                </div>
                <div class="d-flex gap-4 align-items-center">
                    <div class="tactical-search">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                        <input type="text" id="productSearch" class="search-input" placeholder="Search by name, SKU, or category...">
                    </div>
                    <a href="product-add.php" class="btn-luxury-add">
                        <span class="btn-text"><i class="fa-solid fa-plus me-2"></i> Add Product</span>
                    </a>
                </div>
            </div>

            <div class="ledger-wrapper position-relative z-2">
                <table class="table custom-ledger-table align-middle m-0">
                    <thead>
                        <tr>
                            <th style="width: 140px;">SKU</th>
                            <th style="width: 90px;">Image</th>
                            <th>Product Details</th>
                            <th>Category</th>
                            <th>Stock Status</th>
                            <th>Price</th>
                            <th>Visibility</th>
                            <th class="text-end" style="padding-right: 30px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="productTableBody">
                        <?php foreach($products as $prod): ?>
                            <tr class="ledger-row">
                                <td>
                                    <span class="d-block text-white fw-bold font-body mb-1" style="font-size: 0.9rem;">#<?= sprintf('%04d', $prod['productID']) ?></span>
                                    <span class="text-gold font-body" style="font-size: 0.75rem; letter-spacing: 1px;"><?= htmlspecialchars($prod['sampleSku'] ?? 'NO-SKU') ?></span>
                                </td>

                                <td>
                                    <div class="product-thumbnail">
                                        <?php if(isset($prod['primaryImage']) && $prod['primaryImage']): ?>
                                            <img loading="lazy" decoding="async" src="<?= vv_e(vv_admin_public_url($prod['primaryImage'])) ?>" alt="Product">
                                        <?php else: ?>
                                            <i class="fa-solid fa-image text-muted"></i>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="d-block text-white fw-bold product-name mb-1"><?= htmlspecialchars($prod['productName']) ?></span>
                                    <span class="text-light-silver font-body" style="font-size: 0.7rem; letter-spacing: 1px; text-transform: uppercase;">/<?= htmlspecialchars($prod['slug']) ?> &bull; <?= htmlspecialchars($prod['gender']) ?></span>
                                </td>

                                <td><span class="text-silver text-uppercase font-body" style="font-size: 0.8rem; letter-spacing: 1px;"><?= htmlspecialchars($prod['categoryName']) ?></span></td>

                                <td>
                                    <?php if(is_null($prod['totalStock']) || $prod['totalStock'] == 0): ?>
                                        <span class="status-pill status-depleted">OUT OF STOCK</span>
                                    <?php elseif($prod['totalStock'] <= 5): ?>
                                        <span class="status-pill status-low">LOW (<?= $prod['totalStock'] ?>)</span>
                                    <?php else: ?>
                                        <span class="status-pill status-instock"><?= $prod['totalStock'] ?> IN STOCK</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-gold font-body fw-bold fs-6">Rs. <?= number_format($prod['basePrice']) ?></td>

                                <td>
                                    <?php if($prod['isActive']): ?>
                                        <span class="status-pill status-active">ACTIVE</span>
                                    <?php else: ?>
                                        <span class="status-pill status-offline">HIDDEN</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end align-items-center gap-2">
                                        <a href="product-edit.php?id=<?= (int) $prod['productID'] ?>" class="btn-action-ghost" title="Edit Product"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <button type="button" class="btn-action-ghost text-hover-red" title="Delete Product" onclick="triggerDeleteModal(<?= (int) $prod['productID'] ?>, this)">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </section>

    </main>

    <div class="custom-modal-overlay" id="deleteModalOverlay"></div>
    <div class="custom-modal-box" id="deleteModalBox">
        <i class="fa-solid fa-triangle-exclamation modal-icon-warn"></i>
        <h3 class="modal-title">Confirm Deletion</h3>
        <p class="modal-text">Are you certain you want to purge this product from the database? All associated variants, stock counts, and imagery will be permanently destroyed.</p>
        <div class="d-flex gap-3 justify-content-center mt-4">
            <button class="btn-modal-cancel" id="cancelDeleteBtn">Cancel</button>
            <button class="btn-modal-confirm" id="confirmDeleteBtn">Yes, Purge Record</button>
        </div>
    </div>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050">
        <div id="actionToast" class="toast align-items-center text-white bg-dark border border-secondary" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body font-body" id="toastMessage"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/admin-global.js')) ?>"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/products.js')) ?>"></script>
</body>
</html>