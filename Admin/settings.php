<?php
// admin/settings.php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();

require_once '../Config/db.php';
require_once __DIR__ . '/Middleware/AuthMiddleware.php';

AuthMiddleware::requireAdmin();

// =======================================================
// HANDLE AJAX SAVE & IMAGE UPLOAD
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, private');

    if (($_POST['action'] ?? '') !== 'update_settings') {
        vv_json_response(['status' => 'error', 'message' => 'Invalid settings request.'], 422);
    }

    vv_enforce_rate_limit('admin-settings-update', 20, 600, (string) ($_SESSION['userID'] ?? vv_client_ip()));

    $newImagePath = null;
    $oldImageStmt = $pdo->prepare("SELECT settingValue FROM storesettings WHERE settingKey = 'shop_sale_image' LIMIT 1");
    $oldImageStmt->execute();
    $oldImagePath = $oldImageStmt->fetchColumn();

    try {
        $settings = isset($_POST['settings']) && is_array($_POST['settings']) ? $_POST['settings'] : [];
        $settings['shop_sale_active'] = isset($settings['shop_sale_active']) ? '1' : '0';

        if (isset($_FILES['shop_sale_image_file']) && (int) ($_FILES['shop_sale_image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $newImagePath = vv_store_uploaded_image(
                $_FILES['shop_sale_image_file'],
                __DIR__ . '/../Assets/images/promotion',
                'Assets/images/promotion',
                5242880
            );
            $settings['shop_sale_image'] = $newImagePath;
        }

        $allowedSettings = [
            'contact_email', 'contact_phone', 'hero_banner_text', 'shipping_cost',
            'shop_sale_active', 'shop_sale_subtitle', 'shop_sale_title', 'tax_rate'
        ];
        if ($newImagePath !== null) {
            $allowedSettings[] = 'shop_sale_image';
        }

        $pdo->beginTransaction();
        $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM storesettings WHERE settingKey = ?');
        $insertStmt = $pdo->prepare('INSERT INTO storesettings (settingKey, settingValue) VALUES (?, ?)');
        $updateStmt = $pdo->prepare('UPDATE storesettings SET settingValue = ? WHERE settingKey = ?');

        foreach ($settings as $key => $value) {
            if (!is_string($key) || !in_array($key, $allowedSettings, true) || !is_scalar($value)) {
                continue;
            }

            $cleanValue = trim((string) $value);
            $maxLength = in_array($key, ['hero_banner_text', 'shop_sale_subtitle'], true) ? 500 : 160;
            if (strlen($cleanValue) > $maxLength || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $cleanValue)) {
                throw new RuntimeException('A settings value is invalid or too long.');
            }

            if ($key === 'contact_email' && $cleanValue !== '' && !filter_var($cleanValue, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid contact email address.');
            }
            if ($key === 'contact_phone' && $cleanValue !== '' && !preg_match('/^[0-9+() .-]{3,40}$/', $cleanValue)) {
                throw new RuntimeException('Enter a valid contact phone number.');
            }
            if ($key === 'shipping_cost' && (!is_numeric($cleanValue) || (float) $cleanValue < 0 || (float) $cleanValue > 10000000)) {
                throw new RuntimeException('Enter a valid shipping cost.');
            }
            if ($key === 'tax_rate' && (!is_numeric($cleanValue) || (float) $cleanValue < 0 || (float) $cleanValue > 100)) {
                throw new RuntimeException('Enter a tax rate between 0 and 100.');
            }
            if ($key === 'shop_sale_active' && !in_array($cleanValue, ['0', '1'], true)) {
                throw new RuntimeException('The promotion status is invalid.');
            }
            if ($key === 'shop_sale_image' && $cleanValue !== $newImagePath) {
                continue;
            }

            $checkStmt->execute([$key]);
            if ((int) $checkStmt->fetchColumn() > 0) {
                $updateStmt->execute([$cleanValue, $key]);
            } else {
                $insertStmt->execute([$key, $cleanValue]);
            }
        }

        $pdo->commit();

        if ($newImagePath !== null && is_string($oldImagePath) && $oldImagePath !== '' && $oldImagePath !== $newImagePath) {
            vv_delete_public_file($oldImagePath, __DIR__ . '/../Assets/images/promotion');
        }

        vv_json_response(['status' => 'success', 'message' => 'Settings saved successfully.']);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($newImagePath !== null) {
            vv_delete_public_file($newImagePath, __DIR__ . '/../Assets/images/promotion');
        }
        error_log('Settings update failed: ' . $exception->getMessage());
        vv_json_response(['status' => 'error', 'message' => 'The settings could not be saved.'], 500);
    }
}

// =======================================================
// FETCH CURRENT SETTINGS
// =======================================================
$settingsQuery = $pdo->query("SELECT * FROM storesettings");
$settingsData = $settingsQuery->fetchAll(PDO::FETCH_ASSOC);

$config = [];
foreach($settingsData as $row) {
    $config[$row['settingKey']] = $row['settingValue'];
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
    <title>Store Settings | Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/admin-global.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/settings.css')) ?>">
</head>
<body>

    <?php include 'adminheader.php'; ?>

    <main class="container-fluid px-xl-5 pt-4 pb-5" style="max-width: 1200px;">

        <div class="d-flex justify-content-between align-items-end mb-5 border-bottom-dark pb-4 scroll-reveal">
            <div>
                <h1 class="massive-title text-white m-0">Store Settings</h1>
                <p class="text-muted mt-2 mb-0 font-body" style="font-size: 0.9rem;">Manage global configurations, checkout math, and storefront promotions.</p>
            </div>

            <div class="d-flex align-items-center gap-4">

                <div class="status-pill success d-none d-sm-flex" id="statusPill">
                    <div class="pulse-dot"></div>
                    <span id="syncStatusText">System Saved</span>
                </div>

                <button type="submit" form="settingsForm" class="btn-save-standard" id="saveBtn">
                    <i class="fa-solid fa-check btn-icon"></i>
                    <span class="btn-text">Save Changes</span>
                </button>
            </div>
        </div>

        <form id="settingsForm" method="post" autocomplete="off" spellcheck="false" class="scroll-reveal" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">
            <input type="hidden" name="action" value="update_settings">

            <div class="settings-layout">

                <aside class="settings-sidebar">
                    <div class="pref-nav">
                        <button type="button" class="pref-tab active" data-target="pref-contact">
                            <span class="nav-indicator"></span>
                            <i class="fa-solid fa-address-book nav-icon"></i> Contact Info
                        </button>
                        <button type="button" class="pref-tab" data-target="pref-finance">
                            <span class="nav-indicator"></span>
                            <i class="fa-solid fa-calculator nav-icon"></i> Checkout Rules
                        </button>
                        <button type="button" class="pref-tab" data-target="pref-promo">
                            <span class="nav-indicator"></span>
                            <i class="fa-solid fa-images nav-icon"></i> Shop Banner
                        </button>
                    </div>
                </aside>

                <main class="settings-content">

                    <section id="pref-contact" class="pref-section active">
                        <div class="section-header">
                            <h3 class="section-title">Contact Information</h3>
                        </div>

                        <div class="pref-card">
                            <div class="pref-row">
                                <div class="pref-info">
                                    <span class="pref-label">Support Email</span>
                                </div>
                                <div class="pref-input-area">
                                    <div class="custom-input-box">
                                        <div class="box-icon"><i class="fa-regular fa-envelope"></i></div>
                                        <input type="email" name="settings[contact_email]" class="box-input" value="<?= htmlspecialchars($config['contact_email'] ?? '') ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="pref-row">
                                <div class="pref-info">
                                    <span class="pref-label">Phone Number</span>
                                </div>
                                <div class="pref-input-area">
                                    <div class="custom-input-box">
                                        <div class="box-icon"><i class="fa-solid fa-phone"></i></div>
                                        <input type="text" name="settings[contact_phone]" class="box-input" value="<?= htmlspecialchars($config['contact_phone'] ?? '') ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="pref-row">
                                <div class="pref-info">
                                    <span class="pref-label">Homepage Welcome Text</span>
                                </div>
                                <div class="pref-input-area">
                                    <div class="custom-input-box">
                                        <div class="box-icon"><i class="fa-solid fa-quote-left"></i></div>
                                        <input type="text" name="settings[hero_banner_text]" class="box-input" value="<?= htmlspecialchars($config['hero_banner_text'] ?? '') ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>


                    <section id="pref-finance" class="pref-section d-none">
                        <div class="section-header">
                            <h3 class="section-title">Checkout Rules</h3>
                        </div>

                        <div class="pref-card">
                            <div class="pref-row">
                                <div class="pref-info">
                                    <span class="pref-label">Base Tax Rate (%)</span>
                                </div>
                                <div class="pref-input-area">
                                    <div class="custom-input-box">
                                        <div class="box-icon"><i class="fa-solid fa-percent"></i></div>
                                        <input type="number" step="0.01" name="settings[tax_rate]" class="box-input font-monospace" value="<?= htmlspecialchars($config['tax_rate'] ?? '0') ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="pref-row">
                                <div class="pref-info">
                                    <span class="pref-label">Flat Shipping Rate (Rs)</span>
                                </div>
                                <div class="pref-input-area">
                                    <div class="custom-input-box">
                                        <div class="box-icon font-monospace fw-bold" style="font-size:0.8rem;">Rs.</div>
                                        <input type="number" step="0.01" name="settings[shipping_cost]" class="box-input font-monospace" value="<?= htmlspecialchars($config['shipping_cost'] ?? '0') ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>


                    <section id="pref-promo" class="pref-section d-none">
                        <div class="section-header">
                            <h3 class="section-title">Shop Banner</h3>
                        </div>

                        <div class="pref-card">

                            <div class="pref-row align-items-center">
                                <div class="pref-info">
                                    <span class="pref-label">Display Banner on Shop</span>
                                </div>
                                <div class="pref-input-area d-flex justify-content-end">
                                    <label class="theme-switch">
                                        <input type="checkbox" name="settings[shop_sale_active]" value="1" <?= (isset($config['shop_sale_active']) && $config['shop_sale_active'] == '1') ? 'checked' : '' ?>>
                                        <span class="switch-track"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="pref-row">
                                <div class="pref-info">
                                    <span class="pref-label">Banner Title</span>
                                </div>
                                <div class="pref-input-area">
                                    <div class="custom-input-box">
                                        <div class="box-icon"><i class="fa-solid fa-heading"></i></div>
                                        <input type="text" name="settings[shop_sale_title]" class="box-input" value="<?= htmlspecialchars($config['shop_sale_title'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="pref-row align-items-start">
                                <div class="pref-info pt-2">
                                    <span class="pref-label">Banner Image</span>
                                    <?php if(!empty($config['shop_sale_image'])): ?>
                                        <div class="mt-3">
                                            <span class="text-silver d-block mb-1 font-body" style="font-size: 0.7rem; letter-spacing: 1px;">CURRENT IMAGE:</span>
                                            <img loading="lazy" decoding="async" src="<?= vv_e(vv_public_asset_url($config['shop_sale_image'])) ?>" alt="Banner Preview" style="width: 100%; max-width: 180px; height: 80px; object-fit: cover; border-radius: 4px; border: 1px solid rgba(212,175,55,0.4);">
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="pref-input-area">
                                    <div class="custom-input-box">
                                        <div class="box-icon"><i class="fa-regular fa-image"></i></div>
                                        <input type="file" name="shop_sale_image_file" class="box-input" accept="image/*">
                                    </div>
                                    <small class="text-silver mt-2 d-block px-1 font-body" style="font-size: 0.75rem;">Upload a new image to replace the current banner. Leave empty to keep the existing image.</small>
                                </div>
                            </div>

                            <div class="pref-row align-items-start">
                                <div class="pref-info pt-2">
                                    <span class="pref-label">Banner Details</span>
                                </div>
                                <div class="pref-input-area">
                                    <div class="custom-input-box align-items-start">
                                        <div class="box-icon pt-3 border-0"><i class="fa-solid fa-align-left"></i></div>
                                        <textarea name="settings[shop_sale_subtitle]" class="box-textarea custom-scrollbar border-start border-secondary border-opacity-10"><?= htmlspecialchars($config['shop_sale_subtitle'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </section>

                </main>
            </div>
        </form>

    </main>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999; pointer-events: none;">
        <div id="actionToast" class="toast align-items-center text-white bg-dark" role="alert" style="opacity: 0;">
            <div class="d-flex"><div class="toast-body" id="toastMessage"></div></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/admin-global.js')) ?>"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/settings.js')) ?>"></script>
</body>
</html>