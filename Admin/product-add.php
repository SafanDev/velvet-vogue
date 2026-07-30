<?php
// admin/product-add.php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();

require_once '../Config/db.php';
require_once __DIR__ . '/Middleware/AuthMiddleware.php';

AuthMiddleware::requireAdmin();

// Fetch flat categories
$catStmt = $pdo->query("SELECT categoryID, categoryName FROM category WHERE isActive = 1 ORDER BY categoryName ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vv_enforce_rate_limit('admin-product-create', 20, 600, (string) ($_SESSION['userID'] ?? vv_client_ip()));

    // PHP can reject an oversized upload before it parses the form fields.
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    if (empty($_POST) && $contentLength > 0) {
        $error_msg = "Upload Failed: The files you uploaded exceed the server's maximum size limit (" . ini_get('post_max_size') . "B). Please update your php.ini settings or upload smaller/fewer images.";
    } else {
        $adminID = $_SESSION['userID'];

        // Core Details
        $productName = trim((string) ($_POST['productName'] ?? ''));
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $productName)));
        $categoryID = (int) ($_POST['categoryID'] ?? 0);
        $gender = (string) ($_POST['gender'] ?? '');
        $basePrice = (float) ($_POST['basePrice'] ?? 0);
        $salePrice = isset($_POST['salePrice']) && $_POST['salePrice'] !== '' ? (float) $_POST['salePrice'] : null;
        $brandText = trim((string) ($_POST['brand'] ?? ''));
        $materialText = trim((string) ($_POST['material'] ?? ''));
        $brand = $brandText === '' ? null : $brandText;
        $material = $materialText === '' ? null : $materialText;

        // The description column is required, so an empty editor is stored as an empty string.
        $rawDesc = trim((string) ($_POST['description'] ?? ''));
        $description = vv_sanitize_rich_text($rawDesc);

        // Toggles
        $isActive = isset($_POST['isActive']) ? 1 : 0;
        $isFeatured = isset($_POST['isFeatured']) ? 1 : 0;
        $isNewArrival = isset($_POST['isNewArrival']) ? 1 : 0;

        $categoryCheck = $pdo->prepare('SELECT COUNT(*) FROM category WHERE categoryID = ? AND isActive = 1');
        $categoryCheck->execute([$categoryID]);
        $categoryExists = (int) $categoryCheck->fetchColumn() === 1;
        $variantSkus = is_array($_POST['v_sku'] ?? null) ? $_POST['v_sku'] : [];

        if (!vv_valid_name($productName, 180)
            || !$categoryExists
            || !in_array($gender, ['Women', 'Men', 'Unisex'], true)
            || $basePrice <= 0
            || $basePrice > 100000000
            || ($salePrice !== null && ($salePrice < 0 || $salePrice > $basePrice))
            || ($brand !== null && strlen($brand) > 120)
            || ($material !== null && strlen($material) > 180)
            || count($variantSkus) < 1
            || count($variantSkus) > 100
        ) {
            $error_msg = 'Enter valid product details, prices, and at least one variant.';
        } else {
            $uploadedPaths = [];
            try {
                $pdo->beginTransaction();

            // Insert the product before its variants so the generated ID can be used in SKUs.
            $stmt = $pdo->prepare("INSERT INTO product (categoryID, adminID, productName, slug, description, basePrice, salePrice, brand, material, gender, isFeatured, isNewArrival, isActive) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$categoryID, $adminID, $productName, $slug, $description, $basePrice, $salePrice, $brand, $material, $gender, $isFeatured, $isNewArrival, $isActive]);

            // Use the generated product ID to keep variant SKUs unique.
            $productID = $pdo->lastInsertId();

            // Insert product variants with unique SKU suffixes.
            if (!empty($_POST['v_sku'])) {
                $varStmt = $pdo->prepare("INSERT INTO productvariant (productID, skuCode, size, color, stockCount, additionalPrice) VALUES (?, ?, ?, ?, ?, ?)");

                // Track generated SKUs before writing them.
                $used_skus = [];

                $variantCount = count($variantSkus);
                $validVariantCount = 0;
                for ($i = 0; $i < $variantCount; $i++) {

                    $submitted_sku = strtoupper(trim((string) ($variantSkus[$i] ?? '')));
                    $v_size = trim((string) (($_POST['v_size'] ?? [])[$i] ?? ''));
                    $v_color = trim((string) (($_POST['v_color'] ?? [])[$i] ?? ''));
                    $v_stock = filter_var(($_POST['v_stock'] ?? [])[$i] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
                    $v_price = (($_POST['v_price'] ?? [])[$i] ?? '') !== '' ? filter_var(($_POST['v_price'] ?? [])[$i], FILTER_VALIDATE_FLOAT) : 0.00;

                    if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,49}$/', $submitted_sku)
                        || !vv_valid_name($v_size, 40)
                        || !vv_valid_name($v_color, 80)
                        || $v_stock === false
                        || $v_price === false
                        || $v_price < 0
                        || $v_price > 10000000
                    ) {
                        throw new RuntimeException('Every product variant needs a valid SKU, size, color, stock, and additional price.');
                    }

                    if (!empty($submitted_sku) && !empty($v_size) && !empty($v_color)) {

                        $base_sku = $submitted_sku . '-' . $productID;
                        $final_sku = $base_sku;

                        $counter = 1;
                        while (in_array($final_sku, $used_skus)) {
                            $final_sku = $base_sku . '-' . $counter;
                            $counter++;
                        }

                        $used_skus[] = $final_sku;

                        $varStmt->execute([$productID, $final_sku, $v_size, $v_color, (int) $v_stock, round((float) $v_price, 2)]);
                        $validVariantCount++;
                    }
                }
                if ($validVariantCount < 1) {
                    throw new RuntimeException('Add at least one valid product variant.');
                }
            }

            $uploadDir = __DIR__ . '/image';
            $imgStmt = $pdo->prepare("INSERT INTO productimage (productID, color, isPrimary, sortOrder, filePath) VALUES (?, ?, ?, ?, ?)");
            $hasSetPrimary = false;
            $sortOrderCounter = 1;

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
                    $isPrimary = $rawColor === 'main' && !$hasSetPrimary ? 1 : 0;
                    if ($isPrimary === 1) {
                        $hasSetPrimary = true;
                    }
                    $imgStmt->execute([$productID, $imgColor, $isPrimary, $sortOrderCounter, $dbPath]);
                    $sortOrderCounter++;
                }
            }

            $pdo->commit();
            $success_msg = "Asset successfully registered in the Vault.";

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                foreach ($uploadedPaths as $uploadedPath) {
                    vv_delete_public_file($uploadedPath, __DIR__ . '/image');
                }
                error_log('Product creation failed: ' . $e->getMessage());
                $error_msg = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'The product could not be saved. Check the details and uploaded images.';
            }
        }
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
    <title>Add Product | Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/admin-global.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/product-add.css')) ?>">
</head>
<body>

    <?php include 'adminheader.php'; ?>

    <main class="container-fluid px-xl-5 pt-4 pb-5" style="max-width: 1600px;">

        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom-dark scroll-reveal visible">
            <div>
                <a href="products.php" class="text-light-silver text-decoration-none font-body mb-2 d-inline-block" style="font-size: 0.8rem; letter-spacing: 1px;">
                    <i class="fa-solid fa-arrow-left me-2"></i> Back to Inventory
                </a>
                <h1 class="massive-title text-white m-0">Add Product</h1>
            </div>
        </div>

        <?php if($success_msg): ?>
            <div class="alert alert-success bg-dark text-success border-success font-body scroll-reveal visible"><?= vv_e($success_msg) ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-danger bg-dark text-danger border-danger font-body scroll-reveal visible"><?= vv_e($error_msg) ?></div>
        <?php endif; ?>

        <form action="product-add.php" method="POST" enctype="multipart/form-data" id="addProductForm">
            <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">

            <div class="row g-4">

                <div class="col-xl-8">
                    <div class="atelier-card top-glow-card position-relative overflow-hidden mb-4">
                        <div class="blueprint-lines"></div>
                        <div class="card-top-flare"></div>

                        <h4 class="form-section-title">Core Identity</h4>

                        <div class="form-floating-custom mb-4">
                            <input type="text" id="productName" name="productName" class="luxury-input" placeholder=" " required>
                            <label for="productName">Product Name *</label>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-md-4">
                                <div class="form-floating-custom">
                                    <select id="categoryID" name="categoryID" class="luxury-input" required>
                                        <option value="" disabled selected></option>
                                        <?php foreach($categories as $cat): ?>
                                            <option value="<?= $cat['categoryID'] ?>"><?= htmlspecialchars($cat['categoryName']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="categoryID">Category *</label>
                                    <i class="fa-solid fa-chevron-down select-icon"></i>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating-custom">
                                    <select id="gender" name="gender" class="luxury-input" required>
                                        <option value="" disabled selected></option>
                                        <option value="Women">Women</option>
                                        <option value="Men">Men</option>
                                        <option value="Unisex">Unisex</option>
                                    </select>
                                    <label for="gender">Target Gender *</label>
                                    <i class="fa-solid fa-chevron-down select-icon"></i>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating-custom">
                                    <input type="number" step="0.01" id="basePrice" name="basePrice" class="luxury-input" placeholder=" " required>
                                    <label for="basePrice">Base Price (Rs.) *</label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-md-4">
                                <div class="form-floating-custom">
                                    <input type="text" id="brand" name="brand" class="luxury-input" placeholder=" ">
                                    <label for="brand">Brand / Designer</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating-custom">
                                    <input type="text" id="material" name="material" class="luxury-input" placeholder=" ">
                                    <label for="material">Material Fabric</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating-custom">
                                    <input type="number" step="0.01" id="salePrice" name="salePrice" class="luxury-input" placeholder=" ">
                                    <label for="salePrice">Sale Price (Optional)</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-floating-custom mb-2">
                            <textarea id="description" name="description" class="luxury-input" placeholder=" " style="height: 120px; resize: vertical; padding-top: 20px;"></textarea>
                            <label for="description">Editorial Description (Optional)</label>
                        </div>
                    </div>

                    <div class="atelier-card top-glow-card position-relative overflow-hidden">
                        <div class="blueprint-lines"></div>
                        <div class="card-top-flare"></div>

                        <h4 class="form-section-title m-0 mb-4 position-relative z-2">Media Studio</h4>
                        <p class="text-muted font-body position-relative z-2 mb-4" style="font-size: 0.75rem;">Select colors on the right to automatically spawn designated dropzones. 1st image in Main Gallery is Cover, 2nd is Hover.</p>

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
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">

                    <div class="atelier-card top-glow-card mb-4 position-relative overflow-hidden">
                        <div class="card-top-flare"></div>
                        <h4 class="form-section-title mb-4">Visibility</h4>

                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom-dark position-relative z-2">
                            <span class="text-white font-body" style="font-size: 0.9rem;">Active in Store</span>
                            <label class="luxury-switch"><input type="checkbox" name="isActive" checked><span class="slider"></span></label>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom-dark position-relative z-2">
                            <span class="text-white font-body" style="font-size: 0.9rem;">Featured Product</span>
                            <label class="luxury-switch"><input type="checkbox" name="isFeatured"><span class="slider"></span></label>
                        </div>
                        <div class="d-flex justify-content-between align-items-center position-relative z-2">
                            <span class="text-white font-body" style="font-size: 0.9rem;">New Arrival</span>
                            <label class="luxury-switch"><input type="checkbox" name="isNewArrival"><span class="slider"></span></label>
                        </div>
                    </div>

                    <div class="atelier-card top-glow-card mb-4 position-relative overflow-hidden">
                        <div class="card-top-flare"></div>
                        <h4 class="form-section-title mb-3">Variant Builder</h4>
                        <p class="text-light-muted font-body mb-4 position-relative z-2" style="font-size: 0.75rem; line-height: 1.6;">
                            Click to select sizes and colors, then build the matrix. Choose OS / N/A for standard accessories.
                        </p>

                        <div class="mb-4 position-relative z-2">
                            <span class="simple-label mb-2">Sizes</span>
                            <div class="d-flex flex-wrap gap-2" id="sizeSelector">
                                <div class="attr-pill" data-value="OS">OS (One Size)</div>
                                <div class="attr-pill" data-value="XS">XS</div>
                                <div class="attr-pill" data-value="S">S</div>
                                <div class="attr-pill" data-value="M">M</div>
                                <div class="attr-pill" data-value="L">L</div>
                                <div class="attr-pill" data-value="XL">XL</div>
                                <div class="attr-pill" data-value="XXL">XXL</div>
                            </div>
                        </div>

                        <div class="mb-4 position-relative z-2">
                            <span class="simple-label mb-2">Colors</span>
                            <div class="d-flex flex-wrap gap-2" id="colorSelector">
                                <div class="attr-pill" data-value="N/A"><span class="color-dot border border-secondary" style="background:transparent;"></span>N/A</div>
                                <div class="attr-pill" data-value="Black"><span class="color-dot bg-dark border border-secondary"></span>Black</div>
                                <div class="attr-pill" data-value="White"><span class="color-dot bg-white border border-secondary"></span>White</div>
                                <div class="attr-pill" data-value="Grey"><span class="color-dot" style="background:#808080;"></span>Grey</div>
                                <div class="attr-pill" data-value="Beige"><span class="color-dot" style="background:#F5F5DC; border:1px solid #ccc;"></span>Beige</div>
                                <div class="attr-pill" data-value="Navy"><span class="color-dot" style="background:#000080;"></span>Navy</div>
                                <div class="attr-pill" data-value="Blue"><span class="color-dot" style="background:#0000FF;"></span>Blue</div>
                                <div class="attr-pill" data-value="Red"><span class="color-dot" style="background:#FF0000;"></span>Red</div>
                                <div class="attr-pill" data-value="Burgundy"><span class="color-dot" style="background:#800020;"></span>Burgundy</div>
                                <div class="attr-pill" data-value="Pink"><span class="color-dot" style="background:#FFC0CB;"></span>Pink</div>
                                <div class="attr-pill" data-value="Purple"><span class="color-dot" style="background:#800080;"></span>Purple</div>
                                <div class="attr-pill" data-value="Green"><span class="color-dot" style="background:#008000;"></span>Green</div>
                                <div class="attr-pill" data-value="Olive"><span class="color-dot" style="background:#808000;"></span>Olive</div>
                                <div class="attr-pill" data-value="Brown"><span class="color-dot" style="background:#8B4513;"></span>Brown</div>
                                <div class="attr-pill" data-value="Yellow"><span class="color-dot" style="background:#FFFF00;"></span>Yellow</div>
                                <div class="attr-pill" data-value="Gold"><span class="color-dot" style="background:#D4AF37;"></span>Gold</div>
                                <div class="attr-pill" data-value="Silver"><span class="color-dot" style="background:#C0C0C0;"></span>Silver</div>
                            </div>
                        </div>

                        <button type="button" class="btn-stealth w-100 position-relative z-2" id="generateVariantsBtn">
                            <i class="fa-solid fa-wand-magic-sparkles me-2"></i> Build Matrix
                        </button>
                    </div>

                    <div class="atelier-card top-glow-card position-relative overflow-hidden">
                        <div class="card-top-flare"></div>
                        <div class="d-flex justify-content-between align-items-center mb-3 position-relative z-2">
                            <h4 class="form-section-title m-0">Inventory Grid</h4>
                        </div>

                        <div id="bulkCommandModule" class="row g-2 mb-3 pb-3 border-bottom-dark position-relative z-2" style="display: none;">
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

                        <div class="row g-2 mb-2 px-1 text-light-silver position-relative z-2" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; display: none;" id="gridHeaders">
                            <div class="col-5">SKU</div>
                            <div class="col-3 text-center">Stock</div>
                            <div class="col-4 text-center">Extra Price</div>
                        </div>

                        <div id="variantsContainer" class="custom-scrollbar position-relative z-2" style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                            <div id="emptyGridMsg" class="text-center py-5">
                                <i class="fa-solid fa-table-cells-large text-muted mb-3" style="font-size: 2rem;"></i>
                                <p class="text-light-silver font-body m-0" style="font-size: 0.8rem;">Select sizes and colors to generate data fields.</p>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <div class="d-flex justify-content-end gap-4 mt-5 border-top-dark pt-4 scroll-reveal visible">
                <a href="products.php" class="btn-action-ghost" style="padding: 14px 0;">Discard Draft</a>
                <button type="submit" class="btn-luxury-solid">
                    <span class="btn-text">Publish Product</span>
                </button>
            </div>
        </form>

    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/admin-global.js')) ?>"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/product-add.js')) ?>"></script>
</body>
</html>