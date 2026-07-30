<?php
// admin/categories.php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();

require_once '../Config/db.php';
require_once __DIR__ . '/Middleware/AuthMiddleware.php';

AuthMiddleware::requireAdmin();

$success_msg = '';
$error_msg = '';

// =======================================================
// HANDLE FORM SUBMISSIONS (ADD, EDIT, DELETE)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vv_enforce_rate_limit('admin-category-update', 40, 300, (string) ($_SESSION['userID'] ?? vv_client_ip()));

    // Handle Delete Request
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $delID = filter_var($_POST['categoryID'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($delID === false) {
            $error_msg = 'Invalid category reference.';
        } else try {
            // Failsafe: Check if products are still in this category
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE categoryID = ?");
            $checkStmt->execute([$delID]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception("Cannot delete this category. It still contains products. Move or delete the products first.");
            }

            // Get image to delete physical file off the server
            $imgStmt = $pdo->prepare("SELECT imageURL FROM category WHERE categoryID = ?");
            $imgStmt->execute([$delID]);
            $imgPath = $imgStmt->fetchColumn();

            $deleteStmt = $pdo->prepare("DELETE FROM category WHERE categoryID = ?");
            $deleteStmt->execute([$delID]);
            if ($deleteStmt->rowCount() !== 1) {
                throw new RuntimeException('Category not found.');
            }

            if ($imgPath) {
                vv_delete_public_file((string) $imgPath, __DIR__ . '/image/category');
            }
            $success_msg = "Category deleted successfully.";
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
        }
    }
    // Handle Add / Edit Request
    else {
        $categoryID = (int)($_POST['categoryID'] ?? 0);
        $categoryName = trim((string) ($_POST['categoryName'] ?? ''));

        // Generate slug if left empty
        $rawSlug = trim((string) ($_POST['slug'] ?? ''));
        $slug = empty($rawSlug) ? strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $categoryName))) : strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $rawSlug)));

        $descriptionText = trim(strip_tags((string) ($_POST['description'] ?? '')));
        $description = $descriptionText === '' ? null : substr($descriptionText, 0, 500);
        $sortOrder = max(0, min(10000, (int) ($_POST['sortOrder'] ?? 0)));
        if (!vv_valid_name($categoryName, 120) || $slug === '' || strlen($slug) > 140) {
            $error_msg = 'Enter a valid category name and URL slug.';
        }
        $isActive = isset($_POST['isActive']) ? 1 : 0;

        if ($error_msg === '') try {
            $imageURL = null;
            $previousImageURL = null;
            $newImageURL = null;
            if ($categoryID > 0) {
                $existingStmt = $pdo->prepare('SELECT imageURL FROM category WHERE categoryID = ? LIMIT 1');
                $existingStmt->execute([$categoryID]);
                $imageURL = $existingStmt->fetchColumn() ?: null;
                $previousImageURL = $imageURL;
            }
            if (isset($_FILES['imageURL']) && (int) ($_FILES['imageURL']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $newImageURL = vv_store_uploaded_image($_FILES['imageURL'], __DIR__ . '/image/category', 'Admin/image/category');
                $imageURL = $newImageURL;
            }

            if ($categoryID > 0) {
                // UPDATE
                $stmt = $pdo->prepare("UPDATE category SET categoryName=?, slug=?, description=?, imageURL=?, sortOrder=?, isActive=? WHERE categoryID=?");
                $stmt->execute([$categoryName, $slug, $description, $imageURL, $sortOrder, $isActive, $categoryID]);
                $success_msg = "Category updated successfully.";
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO category (categoryName, slug, description, imageURL, sortOrder, isActive) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$categoryName, $slug, $description, $imageURL, $sortOrder, $isActive]);
                $success_msg = "New category added successfully.";
            }

            if ($newImageURL !== null && is_string($previousImageURL) && $previousImageURL !== '' && $previousImageURL !== $newImageURL) {
                vv_delete_public_file($previousImageURL, __DIR__ . '/image/category');
            }
        } catch (RuntimeException $e) {
            if (isset($newImageURL) && is_string($newImageURL)) {
                vv_delete_public_file($newImageURL, __DIR__ . '/image/category');
            }
            $error_msg = $e->getMessage();
        } catch (PDOException $e) {
            if (isset($newImageURL) && is_string($newImageURL)) {
                vv_delete_public_file($newImageURL, __DIR__ . '/image/category');
            }
            if ($e->getCode() == 23000) {
                $error_msg = "A category with this exact URL link already exists. It must be unique.";
            } else {
                error_log('Category save failed: ' . $e->getMessage());
                $error_msg = 'The category could not be saved.';
            }
        }
    }
}

// =======================================================
// FETCH ALL CATEGORIES
// =======================================================
$query = "
    SELECT c.*,
    (SELECT COUNT(*) FROM product p WHERE p.categoryID = c.categoryID) as productCount
    FROM category c
    ORDER BY c.sortOrder ASC, c.categoryName ASC
";
$categories = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Categories | Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/admin-global.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/categories.css')) ?>">
</head>
<body>

    <?php include 'adminheader.php'; ?>

    <main class="container-fluid px-xl-5 pt-4 pb-5" style="max-width: 1800px;">

        <div class="d-flex justify-content-between align-items-end mb-4 pb-3 border-bottom-dark scroll-reveal visible">
            <div>
                <div class="d-flex align-items-center gap-3 mb-1">
                    <span class="simple-label text-gold m-0">Store Organization</span>
                    <span class="badge-count"><?= count($categories) ?> Total</span>
                </div>
                <h1 class="massive-title text-white m-0">Categories</h1>
            </div>
        </div>

        <?php if($success_msg): ?>
            <div class="alert alert-success bg-dark text-success border-success font-body scroll-reveal visible"><?= vv_e($success_msg) ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-danger bg-dark text-danger border-danger font-body scroll-reveal visible"><?= vv_e($error_msg) ?></div>
        <?php endif; ?>

        <div class="row g-5">

            <div class="col-xl-8">
                <div class="row g-4" id="categoryGrid">

                    <?php if(empty($categories)): ?>
                        <div class="col-12 text-center py-5">
                            <i class="fa-solid fa-layer-group text-muted mb-3" style="font-size: 3rem;"></i>
                            <h5 class="text-white font-heading">No Categories Found</h5>
                            <p class="text-light-silver font-body">Use the form on the right to add your first category.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($categories as $cat):
                            $bgImg = $cat['imageURL'] ? '../' . $cat['imageURL'] : 'https://images.unsplash.com/photo-1618220179428-22790b46a013?q=80&w=800';
                        ?>
                            <div class="col-md-6 gsap-card">
                                <div class="cat-card position-relative overflow-hidden <?= !$cat['isActive'] ? 'cat-inactive' : '' ?>"
                                     data-id="<?= $cat['categoryID'] ?>"
                                     data-name="<?= vv_e($cat['categoryName']) ?>"
                                     data-slug="<?= vv_e($cat['slug']) ?>"
                                     data-desc="<?= vv_e($cat['description'] ?? '') ?>"
                                     data-sort="<?= $cat['sortOrder'] ?>"
                                     data-active="<?= $cat['isActive'] ?>"
                                     data-img="<?= vv_e($cat['imageURL'] ?? '') ?>">

                                    <div class="cat-card-bg" style="background-image: url('<?= vv_e($bgImg) ?>');"></div>
                                    <div class="cat-card-overlay"></div>

                                    <div class="cat-card-content position-relative z-2 h-100 d-flex flex-column justify-content-between p-4">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <span class="cat-count-badge"><?= $cat['productCount'] ?> Products</span>
                                            <?php if(!$cat['isActive']): ?>
                                                <span class="badge bg-danger bg-opacity-10 border border-danger text-danger font-body" style="font-size:0.6rem; letter-spacing:1px;">HIDDEN</span>
                                            <?php endif; ?>
                                        </div>

                                        <div>
                                            <h3 class="cat-title m-0"><?= vv_e($cat['categoryName']) ?></h3>
                                            <span class="cat-slug">/<?= vv_e($cat['slug']) ?></span>
                                        </div>
                                    </div>

                                    <div class="cat-card-actions">
                                        <button type="button" class="action-btn edit-cat-btn" title="Edit Category"><i class="fa-solid fa-pen"></i></button>
                                        <form action="categories.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                            <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="categoryID" value="<?= $cat['categoryID'] ?>">
                                            <button type="submit" class="action-btn delete-btn" title="Delete Category"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>

            <div class="col-xl-4">
                <div class="sticky-top" style="top: 100px;">
                    <div class="atelier-card top-glow-card position-relative overflow-hidden" id="consoleCard">
                        <div class="card-top-flare"></div>

                        <div class="d-flex justify-content-between align-items-center mb-4 position-relative z-2">
                            <h4 class="form-section-title m-0" id="consoleTitle">Add New Category</h4>
                            <button type="button" class="btn-action-ghost text-danger d-none" id="cancelEditBtn">
                                <span class="d-flex align-items-center"><i class="fa-solid fa-xmark me-1"></i> Cancel</span>
                            </button>
                        </div>

                        <form action="categories.php" method="POST" enctype="multipart/form-data" id="categoryForm" class="position-relative z-2">
                            <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">
                            <input type="hidden" name="categoryID" id="categoryID" value="0">
                            <input type="hidden" name="existingImage" id="existingImage" value="">

                            <div class="form-floating-custom mb-4">
                                <input type="text" id="categoryName" name="categoryName" class="luxury-input" placeholder=" " required>
                                <label for="categoryName">Category Name *</label>
                            </div>

                            <div class="form-floating-custom mb-4">
                                <input type="text" id="slug" name="slug" class="luxury-input text-gold font-monospace" placeholder=" ">
                                <label for="slug">URL Link (Leave blank to auto-generate)</label>
                            </div>

                            <div class="form-floating-custom mb-4">
                                <textarea id="description" name="description" class="luxury-input" style="height: 80px; resize: none;" placeholder=" "></textarea>
                                <label for="description">Short Description (Optional)</label>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-6">
                                    <div class="form-floating-custom">
                                        <input type="number" id="sortOrder" name="sortOrder" class="luxury-input" placeholder=" " value="0" required>
                                        <label for="sortOrder">List Order (0 is first)</label>
                                    </div>
                                </div>
                                <div class="col-6 d-flex flex-column justify-content-center">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-white font-body" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px;">Active</span>
                                        <label class="luxury-switch"><input type="checkbox" name="isActive" id="isActive" checked><span class="slider"></span></label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <span class="simple-label mb-2">Category Image (Optional)</span>
                                <div class="luxury-dropzone banner-dropzone" id="bannerDropzone" onclick="document.getElementById('imageURL').click()">
                                    <input type="file" id="imageURL" name="imageURL" accept="image/*" class="d-none">
                                    <div class="dropzone-content" id="dropzoneContent">
                                        <i class="fa-regular fa-image dropzone-icon"></i>
                                        <span class="dropzone-text">Click to upload image</span>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn-luxury-solid w-100 mt-2" id="submitBtn">
                                <span class="btn-text" style="position:relative; z-index:2;">Save Category</span>
                                <i class="fa-solid fa-arrow-right btn-icon"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/admin-global.js')) ?>"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/categories.js')) ?>"></script>
</body>
</html>