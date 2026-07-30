<?php
// admin/product-edit.php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();

require_once '../Config/db.php';
require_once __DIR__ . '/Middleware/AuthMiddleware.php';

AuthMiddleware::requireAdmin();

$productID = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productID === 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: products.php");
    exit;
}

// Fetch categories for dropdown
$catStmt = $pdo->query("SELECT categoryID, categoryName FROM category WHERE isActive = 1 ORDER BY categoryName ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

$success_msg = '';
$error_msg = '';

// =======================================================
// HANDLE FORM SUBMISSION (UPDATE)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vv_enforce_rate_limit('admin-product-update', 30, 600, (string) ($_SESSION['userID'] ?? vv_client_ip()));

    $productID = filter_var($_POST['productID'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $productName = trim((string) ($_POST['productName'] ?? ''));
    $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $productName)));
    $categoryID = filter_var($_POST['categoryID'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $gender = (string) ($_POST['gender'] ?? '');
    $basePrice = filter_var($_POST['basePrice'] ?? null, FILTER_VALIDATE_FLOAT);
    $salePrice = ($_POST['salePrice'] ?? '') !== '' ? filter_var($_POST['salePrice'], FILTER_VALIDATE_FLOAT) : null;
    $brandText = trim((string) ($_POST['brand'] ?? ''));
    $materialText = trim((string) ($_POST['material'] ?? ''));
    $brand = $brandText === '' ? null : $brandText;
    $material = $materialText === '' ? null : $materialText;
    $description = vv_sanitize_rich_text(trim((string) ($_POST['description'] ?? '')));
    $isActive = isset($_POST['isActive']) ? 1 : 0;
    $isFeatured = isset($_POST['isFeatured']) ? 1 : 0;
    $isNewArrival = isset($_POST['isNewArrival']) ? 1 : 0;

    $variantSkus = is_array($_POST['v_sku'] ?? null) ? $_POST['v_sku'] : [];
    $variantSizes = is_array($_POST['v_size'] ?? null) ? $_POST['v_size'] : [];
    $variantColors = is_array($_POST['v_color'] ?? null) ? $_POST['v_color'] : [];
    $variantStocks = is_array($_POST['v_stock'] ?? null) ? $_POST['v_stock'] : [];
    $variantPrices = is_array($_POST['v_price'] ?? null) ? $_POST['v_price'] : [];

    $categoryExists = false;
    if ($categoryID !== false) {
        $categoryStmt = $pdo->prepare('SELECT COUNT(*) FROM category WHERE categoryID = ?');
        $categoryStmt->execute([(int) $categoryID]);
        $categoryExists = (int) $categoryStmt->fetchColumn() === 1;
    }

    if ($productID === false
        || !vv_valid_name($productName, 180)
        || $slug === ''
        || strlen($slug) > 200
        || !$categoryExists
        || !in_array($gender, ['Women', 'Men', 'Unisex'], true)
        || $basePrice === false
        || $basePrice <= 0
        || $basePrice > 100000000
        || ($salePrice !== null && ($salePrice === false || $salePrice < 0 || $salePrice > $basePrice))
        || ($brand !== null && strlen($brand) > 120)
        || ($material !== null && strlen($material) > 180)
        || count($variantSkus) < 1
        || count($variantSkus) > 100
    ) {
        $error_msg = 'Enter valid product details, prices, and at least one variant.';
    } else {
        $variantsToSave = [];
        $seenSkus = [];
        for ($i = 0, $count = count($variantSkus); $i < $count; $i++) {
            $sku = strtoupper(trim((string) ($variantSkus[$i] ?? '')));
            $size = trim((string) ($variantSizes[$i] ?? ''));
            $color = trim((string) ($variantColors[$i] ?? ''));
            $stock = filter_var($variantStocks[$i] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
            $additionalPrice = ($variantPrices[$i] ?? '') !== '' ? filter_var($variantPrices[$i], FILTER_VALIDATE_FLOAT) : 0.0;

            if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,79}$/', $sku)
                || isset($seenSkus[$sku])
                || !vv_valid_name($size, 40)
                || !vv_valid_name($color, 80)
                || $stock === false
                || $additionalPrice === false
                || $additionalPrice < 0
                || $additionalPrice > 10000000
            ) {
                $error_msg = 'Every variant needs a unique valid SKU, size, color, stock, and additional price.';
                break;
            }

            $seenSkus[$sku] = true;
            $variantsToSave[] = [$sku, $size, $color, (int) $stock, round((float) $additionalPrice, 2)];
        }

        if ($error_msg === '') {
            $uploadedPaths = [];
            $filesToDelete = [];

            try {
                $pdo->beginTransaction();

                $productLockStmt = $pdo->prepare('SELECT productID FROM product WHERE productID = ? FOR UPDATE');
                $productLockStmt->execute([(int) $productID]);
                if (!$productLockStmt->fetchColumn()) {
                    throw new RuntimeException('Product not found.');
                }

                $stmt = $pdo->prepare('UPDATE product SET categoryID=?, productName=?, slug=?, description=?, basePrice=?, salePrice=?, brand=?, material=?, gender=?, isFeatured=?, isNewArrival=?, isActive=? WHERE productID=?');
                $stmt->execute([(int) $categoryID, $productName, $slug, $description, round((float) $basePrice, 2), $salePrice === null ? null : round((float) $salePrice, 2), $brand, $material, $gender, $isFeatured, $isNewArrival, $isActive, (int) $productID]);

                $pdo->prepare('UPDATE productvariant SET isActive = 0 WHERE productID = ?')->execute([(int) $productID]);
                $findVariantStmt = $pdo->prepare('SELECT variantID, productID FROM productvariant WHERE skuCode = ? LIMIT 1 FOR UPDATE');
                $updateVariantStmt = $pdo->prepare('UPDATE productvariant SET size = ?, color = ?, stockCount = ?, additionalPrice = ?, isActive = 1 WHERE variantID = ? AND productID = ?');
                $insertVariantStmt = $pdo->prepare('INSERT INTO productvariant (productID, skuCode, size, color, stockCount, additionalPrice, isActive) VALUES (?, ?, ?, ?, ?, ?, 1)');

                foreach ($variantsToSave as [$sku, $size, $color, $stock, $additionalPrice]) {
                    $findVariantStmt->execute([$sku]);
                    $existingVariant = $findVariantStmt->fetch(PDO::FETCH_ASSOC);
                    if ($existingVariant && (int) $existingVariant['productID'] !== (int) $productID) {
                        throw new RuntimeException('A variant SKU is already used by another product.');
                    }

                    if ($existingVariant) {
                        $updateVariantStmt->execute([$size, $color, $stock, $additionalPrice, (int) $existingVariant['variantID'], (int) $productID]);
                    } else {
                        $insertVariantStmt->execute([(int) $productID, $sku, $size, $color, $stock, $additionalPrice]);
                    }
                }

                $deletedImageIds = is_array($_POST['deleted_images'] ?? null) ? array_slice($_POST['deleted_images'], 0, 100) : [];
                if ($deletedImageIds) {
                    $findImageStmt = $pdo->prepare('SELECT filePath FROM productimage WHERE imageID = ? AND productID = ? LIMIT 1');
                    $deleteImageStmt = $pdo->prepare('DELETE FROM productimage WHERE imageID = ? AND productID = ?');
                    foreach ($deletedImageIds as $deletedImageId) {
                        $imageId = filter_var($deletedImageId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                        if ($imageId === false) {
                            continue;
                        }
                        $findImageStmt->execute([(int) $imageId, (int) $productID]);
                        $filePath = $findImageStmt->fetchColumn();
                        if ($filePath) {
                            $filesToDelete[] = (string) $filePath;
                        }
                        $deleteImageStmt->execute([(int) $imageId, (int) $productID]);
                    }
                }

                $uploadDir = __DIR__ . '/image';
                $imgStmt = $pdo->prepare('INSERT INTO productimage (productID, color, isPrimary, filePath) VALUES (?, ?, ?, ?)');
                $hasPrimaryStmt = $pdo->prepare('SELECT COUNT(*) FROM productimage WHERE productID = ? AND isPrimary = 1');
                $hasPrimaryStmt->execute([(int) $productID]);
                $hasSetPrimary = (int) $hasPrimaryStmt->fetchColumn() > 0;
                $totalUploadCount = 0;

                foreach ($_FILES as $inputName => $fileArray) {
                    if (!str_starts_with($inputName, 'image_upload_') || !is_array($fileArray['name'] ?? null)) {
                        continue;
                    }

                    $rawColor = substr($inputName, 13);
                    $imgColor = $rawColor === 'main' ? null : str_replace('_', ' ', $rawColor);
                    $fileCount = count($fileArray['name']);
                    $totalUploadCount += $fileCount;
                    if ($totalUploadCount > 20) {
                        throw new RuntimeException('Upload no more than 20 product images at a time.');
                    }

                    for ($i = 0; $i < $fileCount; $i++) {
                        $error = (int) ($fileArray['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                        if ($error === UPLOAD_ERR_NO_FILE) {
                            continue;
                        }

                        $upload = [
                            'name' => $fileArray['name'][$i] ?? '',
                            'type' => $fileArray['type'][$i] ?? '',
                            'tmp_name' => $fileArray['tmp_name'][$i] ?? '',
                            'error' => $error,
                            'size' => $fileArray['size'][$i] ?? 0,
                        ];
                        $dbPath = vv_store_uploaded_image($upload, $uploadDir, 'Admin/image');
                        $uploadedPaths[] = $dbPath;
                        $isPrimary = !$hasSetPrimary ? 1 : 0;
                        $hasSetPrimary = true;
                        $imgStmt->execute([(int) $productID, $imgColor, $isPrimary, $dbPath]);
                    }
                }

                if (!$hasSetPrimary) {
                    $pdo->prepare('UPDATE productimage SET isPrimary = 1 WHERE imageID = (SELECT imageID FROM (SELECT imageID FROM productimage WHERE productID = ? ORDER BY imageID ASC LIMIT 1) first_image)')->execute([(int) $productID]);
                }

                $pdo->commit();
                foreach ($filesToDelete as $fileToDelete) {
                    vv_delete_public_file($fileToDelete, __DIR__ . '/image');
                }
                $success_msg = 'Asset successfully updated in the Vault.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                foreach ($uploadedPaths as $uploadedPath) {
                    vv_delete_public_file($uploadedPath, __DIR__ . '/image');
                }
                error_log('Product update failed: ' . $exception->getMessage());
                $error_msg = $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'The product could not be updated. Check the details and uploaded images.';
            }
        }
    }
}

// =======================================================
// FETCH EXISTING DATA FOR DISPLAY
// =======================================================
$stmt = $pdo->prepare("SELECT * FROM product WHERE productID = ?");
$stmt->execute([$productID]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "Product not found.";
    exit;
}

// Fetch Variants
$varStmt = $pdo->prepare("SELECT * FROM productvariant WHERE productID = ? ORDER BY size, color");
$varStmt->execute([$productID]);
$variants = $varStmt->fetchAll(PDO::FETCH_ASSOC);

$activeSizes = array_unique(array_column($variants, 'size'));
$activeColors = array_unique(array_column($variants, 'color'));

// Fetch Images
$imgStmt = $pdo->prepare("SELECT * FROM productimage WHERE productID = ? ORDER BY isPrimary DESC, imageID ASC");
$imgStmt->execute([$productID]);
$images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Edit Product | Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/admin-global.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/product-add.css')) ?>"> </head>
<body>

    <?php include 'adminheader.php'; ?>

    <main class="container-fluid px-xl-5 pt-4 pb-5" style="max-width: 1600px;">

        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom-dark scroll-reveal visible">
            <div>
                <a href="products.php" class="text-light-silver text-decoration-none font-body mb-2 d-inline-block" style="font-size: 0.8rem; letter-spacing: 1px;">
                    <i class="fa-solid fa-arrow-left me-2"></i> Back to Inventory
                </a>
                <h1 class="massive-title text-white m-0">Edit Product: <span class="text-gold"><?= vv_e($product['productName']) ?></span></h1>
            </div>
            <div class="text-end">
                <span class="text-light-silver font-monospace" style="font-size: 0.85rem;">ID: #<?= sprintf('%04d', $product['productID']) ?></span>
            </div>
        </div>

        <?php if($success_msg): ?>
            <div class="alert alert-success bg-dark text-success border-success font-body scroll-reveal visible"><?= vv_e($success_msg) ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-danger bg-dark text-danger border-danger font-body scroll-reveal visible"><?= vv_e($error_msg) ?></div>
        <?php endif; ?>

        <form action="product-edit.php?id=<?= $productID ?>" method="POST" enctype="multipart/form-data" id="addProductForm">
            <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">
            <input type="hidden" name="productID" value="<?= $productID ?>">

            <div class="row g-4">

                <div class="col-xl-8">
                    <div class="atelier-card top-glow-card position-relative overflow-hidden mb-4">
                        <div class="blueprint-lines"></div>
                        <div class="card-top-flare"></div>

                        <h4 class="form-section-title">Core Identity</h4>

                        <div class="form-floating-custom mb-4">
                            <input type="text" id="productName" name="productName" class="luxury-input" placeholder=" " value="<?= vv_e($product['productName']) ?>" required>
                            <label for="productName">Product Name *</label>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-md-4">
                                <div class="form-floating-custom">
                                    <select id="categoryID" name="categoryID" class="luxury-input" required>
                                        <option value="" disabled></option>
                                        <?php foreach($categories as $cat): ?>
                                            <option value="<?= $cat['categoryID'] ?>" <?= $cat['categoryID'] == $product['categoryID'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['categoryName']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="categoryID">Category *</label>
                                    <i class="fa-solid fa-chevron-down select-icon"></i>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating-custom">
                                    <select id="gender" name="gender" class="luxury-input" required>
                                        <option value="Women" <?= $product['gender'] == 'Women' ? 'selected' : '' ?>>Women</option>
                                        <option value="Men" <?= $product['gender'] == 'Men' ? 'selected' : '' ?>>Men</option>
                                        <option value="Unisex" <?= $product['gender'] == 'Unisex' ? 'selected' : '' ?>>Unisex</option>
                                    </select>
                                    <label for="gender">Target Gender *</label>
                                    <i class="fa-solid fa-chevron-down select-icon"></i>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating-custom">
                                    <input type="number" step="0.01" id="basePrice" name="basePrice" class="luxury-input" placeholder=" " value="<?= vv_e($product['basePrice']) ?>" required>
                                    <label for="basePrice">Base Price (Rs.) *</label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-md-4">
                                <div class="form-floating-custom">
                                    <input type="text" id="brand" name="brand" class="luxury-input" placeholder=" " value="<?= vv_e($product['brand'] ?? '') ?>">
                                    <label for="brand">Brand / Designer</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating-custom">
                                    <input type="text" id="material" name="material" class="luxury-input" placeholder=" " value="<?= vv_e($product['material'] ?? '') ?>">
                                    <label for="material">Material Fabric</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating-custom">
                                    <input type="number" step="0.01" id="salePrice" name="salePrice" class="luxury-input" placeholder=" " value="<?= vv_e($product['salePrice']) ?>">
                                    <label for="salePrice">Sale Price (Optional)</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="simple-label mb-2 text-muted">Editorial Description (Optional)</label>
                            <div id="quillEditorContainer" style="height: 150px;"><?= vv_sanitize_rich_text((string) $product['description']) ?></div>
                            <input type="hidden" id="description" name="description">
                        </div>
                    </div>

                    <div class="atelier-card top-glow-card position-relative overflow-hidden">
                        <div class="blueprint-lines"></div>
                        <div class="card-top-flare"></div>

                        <h4 class="form-section-title m-0 mb-4 position-relative z-2">Media Studio</h4>

                        <?php if(!empty($images)): ?>
                        <div class="mb-4 position-relative z-2 pb-4 border-bottom-dark">
                            <span class="simple-label mb-3">Existing Archive Media</span>
                            <div class="d-flex flex-wrap gap-3">
                                <?php foreach($images as $img): ?>
                                    <div class="existing-img-card position-relative" id="img-card-<?= $img['imageID'] ?>">
                                        <img loading="lazy" decoding="async" src="<?= vv_e(vv_admin_public_url($img['filePath'])) ?>" class="mini-preview-img" style="width: 70px; height: 70px; border-color: <?= $img['isPrimary'] ? 'var(--color-gold-metallic)' : 'var(--border-subtle)' ?>;">
                                        <button type="button" class="img-remove-btn" onclick="markImageDeleted(<?= $img['imageID'] ?>)"><i class="fa-solid fa-trash"></i></button>
                                        <span class="badge bg-dark border border-secondary position-absolute bottom-0 start-50 translate-middle-x mb-1 w-100 rounded-0" style="font-size:0.55rem; letter-spacing: 1px;"><?= vv_e($img['color'] ?? 'MAIN') ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div id="deletedImagesContainer"></div>
                        </div>
                        <?php endif; ?>

                        <p class="text-muted font-body position-relative z-2 mb-4" style="font-size: 0.75rem;">Select colors on the right to append new drops to specific color variations.</p>

                        <div id="dynamicDropzonesContainer" class="row g-3 position-relative z-2">
                            <div class="col-12 col-md-6">
                                <div class="luxury-dropzone-wrapper">
                                    <span class="dropzone-title">MAIN GALLERY (ALL COLORS)</span>
                                    <div class="luxury-dropzone" onclick="document.getElementById('upload_main').click()">
                                        <input type="file" id="upload_main" name="image_upload_main[]" accept="image/*" class="d-none" multiple onchange="previewImages(this, 'preview_main', 'upload_main')">
                                        <div class="dropzone-content">
                                            <i class="fa-solid fa-cloud-arrow-up dropzone-icon"></i>
                                            <span class="dropzone-text">Add General Images</span>
                                        </div>
                                    </div>
                                    <div id="preview_main" class="d-flex flex-wrap gap-2 mt-2"></div>
                                </div>
                            </div>

                            <?php foreach($activeColors as $color): if(empty($color) || $color === 'Standard') continue; $safeCol = preg_replace('/[^A-Za-z0-9_-]/', '_', $color) ?: 'color'; ?>
                            <div class="col-12 col-md-6 color-dropzone-existing" data-color="<?= htmlspecialchars($color) ?>">
                                <div class="luxury-dropzone-wrapper">
                                    <span class="dropzone-title text-white"><?= htmlspecialchars(strtoupper($color)) ?> MEDIA</span>
                                    <div class="luxury-dropzone" onclick="document.getElementById('upload_<?= $safeCol ?>').click()">
                                        <input type="file" id="upload_<?= $safeCol ?>" name="image_upload_<?= $safeCol ?>[]" accept="image/*" class="d-none" multiple onchange="previewImages(this, 'preview_<?= $safeCol ?>', 'upload_<?= $safeCol ?>')">
                                        <div class="dropzone-content">
                                            <i class="fa-solid fa-camera dropzone-icon"></i>
                                            <span class="dropzone-text">Add <?= htmlspecialchars($color) ?> Images</span>
                                        </div>
                                    </div>
                                    <div id="preview_<?= $safeCol ?>" class="d-flex flex-wrap gap-2 mt-2"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">

                    <div class="atelier-card top-glow-card mb-4 position-relative overflow-hidden">
                        <div class="card-top-flare"></div>
                        <h4 class="form-section-title mb-4">Visibility</h4>

                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom-dark position-relative z-2">
                            <span class="text-white font-body" style="font-size: 0.9rem;">Active in Store</span>
                            <label class="luxury-switch"><input type="checkbox" name="isActive" <?= $product['isActive'] ? 'checked' : '' ?>><span class="slider"></span></label>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom-dark position-relative z-2">
                            <span class="text-white font-body" style="font-size: 0.9rem;">Featured Product</span>
                            <label class="luxury-switch"><input type="checkbox" name="isFeatured" <?= $product['isFeatured'] ? 'checked' : '' ?>><span class="slider"></span></label>
                        </div>
                        <div class="d-flex justify-content-between align-items-center position-relative z-2">
                            <span class="text-white font-body" style="font-size: 0.9rem;">New Arrival</span>
                            <label class="luxury-switch"><input type="checkbox" name="isNewArrival" <?= $product['isNewArrival'] ? 'checked' : '' ?>><span class="slider"></span></label>
                        </div>
                    </div>

                    <div class="atelier-card top-glow-card mb-4 position-relative overflow-hidden">
                        <div class="card-top-flare"></div>
                        <h4 class="form-section-title mb-3">Variant Modder</h4>
                        <p class="text-muted font-body mb-4 position-relative z-2" style="font-size: 0.75rem; line-height: 1.6;">
                            Add new combinations to the grid below without losing existing stock data.
                        </p>

                        <div class="mb-4 position-relative z-2">
                            <span class="simple-label mb-2">Sizes</span>
                            <div class="d-flex flex-wrap gap-2" id="sizeSelector">
                                <?php $allSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'Free Size']; foreach($allSizes as $sz): ?>
                                    <div class="attr-pill <?= in_array($sz, $activeSizes) ? 'active' : '' ?>" data-value="<?= $sz ?>"><?= $sz ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-4 position-relative z-2">
                            <span class="simple-label mb-2">Colors</span>
                            <div class="d-flex flex-wrap gap-2" id="colorSelector">
                                <?php
                                $allColors = ['Black'=>'#000', 'White'=>'#fff', 'Grey'=>'#808080', 'Beige'=>'#F5F5DC', 'Navy'=>'#000080', 'Blue'=>'#0000FF', 'Red'=>'#FF0000', 'Burgundy'=>'#800020', 'Pink'=>'#FFC0CB', 'Purple'=>'#800080', 'Green'=>'#008000', 'Olive'=>'#808000', 'Brown'=>'#8B4513', 'Yellow'=>'#FFFF00', 'Gold'=>'#D4AF37', 'Silver'=>'#C0C0C0'];
                                foreach($allColors as $cName => $cHex):
                                ?>
                                    <div class="attr-pill <?= in_array($cName, $activeColors) ? 'active' : '' ?>" data-value="<?= $cName ?>"><span class="color-dot <?= $cName=='White'?'border border-secondary':'' ?>" style="background:<?= $cHex ?>;"></span><?= $cName ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button type="button" class="btn-stealth w-100 position-relative z-2" id="generateVariantsBtn">
                            <i class="fa-solid fa-wand-magic-sparkles me-2"></i> Append Grid
                        </button>
                    </div>

                    <div class="atelier-card top-glow-card position-relative overflow-hidden">
                        <div class="card-top-flare"></div>
                        <div class="d-flex justify-content-between align-items-center mb-3 position-relative z-2">
                            <h4 class="form-section-title m-0">Inventory Grid</h4>
                        </div>

                        <div id="bulkCommandModule" class="row g-2 mb-3 pb-3 border-bottom-dark position-relative z-2" <?= empty($variants) ? 'style="display: none;"' : '' ?>>
                            <div class="col-5">
                                <input type="number" id="bulkStockInput" class="luxury-input-small border border-secondary" placeholder="Bulk Stock" min="0">
                            </div>
                            <div class="col-4">
                                <input type="number" id="bulkPriceInput" class="luxury-input-small border border-secondary" placeholder="+ Extra Rs.">
                            </div>
                            <div class="col-3">
                                <button type="button" id="bulkApplyBtn" class="btn-action-ghost w-100 h-100 text-gold" style="font-size: 0.7rem; border: 1px solid var(--color-gold-metallic);">APPLY</button>
                            </div>
                        </div>

                        <div class="row g-2 mb-2 px-1 text-light-silver position-relative z-2" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; <?= empty($variants) ? 'display: none;' : 'display: flex;' ?>" id="gridHeaders">
                            <div class="col-5">SKU</div>
                            <div class="col-3 text-center">Stock</div>
                            <div class="col-4 text-center">Extra Price</div>
                        </div>

                        <div id="variantsContainer" class="custom-scrollbar position-relative z-2" style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                            <?php if(empty($variants)): ?>
                                <div id="emptyGridMsg" class="text-center py-5">
                                    <i class="fa-solid fa-table-cells-large text-muted mb-3" style="font-size: 2rem;"></i>
                                    <p class="text-light-silver font-body m-0" style="font-size: 0.8rem;">Select sizes and colors to generate data fields.</p>
                                </div>
                            <?php else: ?>
                                <div id="emptyGridMsg" class="text-center py-5" style="display: none;"><i class="fa-solid fa-table-cells-large text-muted mb-3" style="font-size: 2rem;"></i><p class="text-light-silver font-body m-0" style="font-size: 0.8rem;">Select sizes and colors to generate data fields.</p></div>

                                <?php foreach($variants as $v): ?>
                                    <div class="variant-row mb-3 pb-3 border-bottom-dark" data-combo="<?= htmlspecialchars($v['size'] . '-' . $v['color']) ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-gold font-body fw-bold" style="font-size: 0.85rem; letter-spacing: 1px;"><?= htmlspecialchars($v['size']) ?> &bull; <?= htmlspecialchars($v['color']) ?></span>
                                            <button type="button" class="btn-action-ghost remove-row-btn m-0" style="width: 24px; height: 24px; font-size: 0.8rem;"><i class="fa-solid fa-xmark pointer-events-none"></i></button>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-12 col-md-5">
                                                <div class="input-wrapper">
                                                    <input type="text" name="v_sku[]" class="luxury-input-small font-monospace text-gold px-3" value="<?= htmlspecialchars($v['skuCode']) ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <div class="input-wrapper">
                                                    <button type="button" class="num-btn num-minus">-</button>
                                                    <input type="number" name="v_stock[]" class="luxury-input-small font-monospace matrix-stock-input" value="<?= vv_e($v['stockCount']) ?>" required min="0">
                                                    <button type="button" class="num-btn num-plus">+</button>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-4">
                                                <div class="input-wrapper">
                                                    <button type="button" class="num-btn num-minus">-</button>
                                                    <input type="number" name="v_price[]" class="luxury-input-small font-monospace matrix-price-input" value="<?= vv_e($v['additionalPrice']) ?>">
                                                    <button type="button" class="num-btn num-plus">+</button>
                                                </div>
                                            </div>
                                        </div>
                                        <input type="hidden" name="v_size[]" value="<?= htmlspecialchars($v['size']) ?>">
                                        <input type="hidden" name="v_color[]" value="<?= htmlspecialchars($v['color']) ?>">
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            </div>

            <div class="d-flex justify-content-end gap-4 mt-5 border-top-dark pt-4 scroll-reveal visible">
                <a href="products.php" class="btn-action-ghost" style="padding: 14px 0;">Discard Changes</a>
                <button type="submit" class="btn-luxury-solid">
                    <span class="btn-text">Apply Updates</span>
                </button>
            </div>
        </form>

    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/admin-global.js')) ?>"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/product-edit.js')) ?>"></script>
</body>
</html>