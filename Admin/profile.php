<?php
// admin/profile.php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();

require_once '../Config/db.php';
require_once __DIR__ . '/Middleware/AuthMiddleware.php';

AuthMiddleware::requireAdmin();

$userID = $_SESSION['userID'];
$message = '';
$messageType = '';

$uploadDir = __DIR__ . '/../Assets/images/avatars';

// =======================================================
// HANDLE FORM SUBMISSIONS
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. UPDATE BASIC INFO & AVATAR
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $firstName = trim($_POST['firstName']);
        $lastName = trim($_POST['lastName']);
        $phoneNo = trim($_POST['phoneNo']);
        $gender = $_POST['gender'] ?? null;

        if (!vv_valid_name($firstName) || !vv_valid_name($lastName) || strlen($phoneNo) > 40 || !preg_match('/^[0-9+() .-]{0,40}$/', $phoneNo) || !in_array($gender, [null, '', 'Male', 'Female', 'Other'], true)) {
            $message = 'Enter valid profile details.';
            $messageType = "error";
        } else {
            $newProfilePath = null;
            try {
                $currentImageStmt = $pdo->prepare('SELECT profileImage FROM `user` WHERE userID = ? LIMIT 1');
                $currentImageStmt->execute([$userID]);
                $oldProfilePath = $currentImageStmt->fetchColumn() ?: null;

                if (isset($_FILES['avatar']) && (int) ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $newProfilePath = vv_store_uploaded_image($_FILES['avatar'], $uploadDir, 'Assets/images/avatars', 3145728);
                }

                $pdo->beginTransaction();
                $stmt = $pdo->prepare('UPDATE `user` SET firstName = ?, lastName = ?, phoneNo = ?, gender = ?, profileImage = COALESCE(?, profileImage) WHERE userID = ?');
                $stmt->execute([$firstName, $lastName, $phoneNo, $gender, $newProfilePath, $userID]);
                $pdo->commit();

                $_SESSION['firstName'] = $firstName;
                $_SESSION['lastName'] = $lastName;
                if ($newProfilePath !== null) {
                    $_SESSION['profileImage'] = $newProfilePath;
                    if (is_string($oldProfilePath) && $oldProfilePath !== '' && $oldProfilePath !== $newProfilePath) {
                        vv_delete_public_file($oldProfilePath, $uploadDir);
                    }
                }

                $message = 'Profile details updated successfully.';
                $messageType = 'success';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($newProfilePath !== null) {
                    vv_delete_public_file($newProfilePath, $uploadDir);
                }
                error_log('Admin profile update failed: ' . $exception->getMessage());
                $message = 'The profile could not be updated.';
                $messageType = 'error';
            }
        }
    }

    // 2. SECURITY: CHANGE PASSWORD
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $currentPassword = (string) ($_POST['currentPassword'] ?? '');
        $newPassword = (string) ($_POST['newPassword'] ?? '');
        $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');
        vv_enforce_rate_limit('admin-password-change', 5, 900, (string) $userID);

        $stmt = $pdo->prepare("SELECT password FROM `user` WHERE userID = ?");
        $stmt->execute([$userID]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            $message = "Error: Current password is incorrect.";
            $messageType = "error";
        } elseif ($newPassword !== $confirmPassword) {
            $message = "Error: New passwords do not match.";
            $messageType = "error";
        } elseif (strlen($newPassword) < 10 || strlen($newPassword) > 72) {
            $message = "Error: New password must be between 10 and 72 characters.";
            $messageType = "error";
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE `user` SET password = ? WHERE userID = ?");
            if ($updateStmt->execute([$hashedPassword, $userID])) {
                vv_clear_remember_cookie();
                session_regenerate_id(true);
                vv_rotate_csrf_token();
                $message = 'Password updated successfully.';
                $messageType = "success";
            } else {
                $message = "System Error: Could not update password.";
                $messageType = "error";
            }
        }
    }
}

// =======================================================
// FETCH CURRENT ADMIN DATA
// =======================================================
$stmt = $pdo->prepare("SELECT * FROM `user` WHERE userID = ?");
$stmt->execute([$userID]);
$adminData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$adminData) {
    http_response_code(404);
    vv_fail_request('Administrator profile not found.', 404);
}

$accountAge = (new DateTime())->diff(new DateTime($adminData['createdAt']))->days;

// Safe check for profile image
$profileImage = !empty($adminData['profileImage']) ? $adminData['profileImage'] : null;
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
    <title>Admin Profile | Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/admin-global.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/profile.css')) ?>">
</head>
<body class="admin-terminal">

    <?php include 'adminheader.php'; ?>

    <main class="container-fluid px-xl-5 pt-4 pb-5 d-flex flex-column align-items-center">

        <div class="w-100" style="max-width: 1100px;">

            <div class="d-flex justify-content-between align-items-end mb-4 border-bottom-dark pb-3 scroll-reveal">
                <div>
                    <span class="d-block text-gold font-monospace mb-1" style="font-size: 0.7rem; letter-spacing: 3px;">SYSTEM MANAGEMENT</span>
                    <h1 class="massive-title text-white m-0">Admin <span class="fw-light text-silver">Profile</span></h1>
                </div>
                <div class="text-end d-none d-md-block">
                    <span class="d-block text-silver font-monospace mb-1" style="font-size: 0.65rem; letter-spacing: 1px;">CURRENT SESSION</span>
                    <span class="text-white font-body fw-bold" style="font-size: 0.85rem;"><i class="fa-solid fa-clock me-1 text-gold"></i> <?= date('H:i | M d, Y') ?></span>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert <?= $messageType === 'success' ? 'alert-success-dark' : 'alert-danger-dark' ?> scroll-reveal mb-4">
                    <i class="fa-solid <?= $messageType === 'success' ? 'fa-check text-success' : 'fa-triangle-exclamation text-danger' ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="row g-5 justify-content-center">

                <div class="col-lg-4 col-md-5 scroll-reveal">
                    <div class="id-card-panel">
                        <div class="id-card-header text-center">
                            <i class="fa-solid fa-fingerprint watermark-icon"></i>
                            <div class="d-flex justify-content-between align-items-start position-relative z-2">
                                <span class="id-badge">ID: <?= str_pad($adminData['userID'], 5, '0', STR_PAD_LEFT) ?></span>
                                <span class="status-dot green pulse"></span>
                            </div>

                            <div class="avatar-container mt-4 mb-3 position-relative z-2 mx-auto" id="avatarTrigger" title="Click to upload new image">
                                <div class="avatar-ring"></div>
                                <div class="avatar-circle overflow-hidden">
                                    <?php if($profileImage): ?>
                                        <img decoding="async" src="<?= vv_e(vv_admin_public_url($profileImage)) ?>" alt="Admin Profile" id="avatarPreviewImg" class="w-100 h-100" style="object-fit: cover;">
                                        <i class="fa-solid fa-user-tie d-none" id="avatarFallbackIcon"></i>
                                    <?php else: ?>
                                        <img decoding="async" src="" alt="Preview" id="avatarPreviewImg" class="w-100 h-100 d-none" style="object-fit: cover;">
                                        <i class="fa-solid fa-user-tie" id="avatarFallbackIcon"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="avatar-overlay">
                                    <i class="fa-solid fa-camera mb-1"></i>
                                    <span>EDIT</span>
                                </div>
                            </div>

                            <h3 class="text-center text-white font-heading fw-bold m-0 position-relative z-2" style="letter-spacing: 1px;">
                                <?= htmlspecialchars($adminData['firstName'] . ' ' . $adminData['lastName']) ?>
                            </h3>
                            <p class="text-center text-gold font-monospace mt-1 mb-0 position-relative z-2" style="font-size: 0.8rem;">ADMINISTRATOR</p>
                        </div>

                        <div class="id-card-body p-4 position-relative z-2">
                            <div class="data-row mb-3">
                                <span class="data-label">EMAIL ADDRESS</span>
                                <span class="data-value text-white"><?= htmlspecialchars($adminData['email']) ?></span>
                            </div>
                            <div class="data-row mb-3">
                                <span class="data-label">PHONE NUMBER</span>
                                <span class="data-value"><?= !empty($adminData['phoneNo']) ? '<span class="text-white">'.htmlspecialchars($adminData['phoneNo']).'</span>' : '<span class="data-missing"><i class="fa-solid fa-triangle-exclamation me-1"></i> NOT SET</span>' ?></span>
                            </div>
                            <div class="data-row mb-3">
                                <span class="data-label">GENDER</span>
                                <span class="data-value"><?= !empty($adminData['gender']) ? '<span class="text-white">'.htmlspecialchars($adminData['gender']).'</span>' : '<span class="data-missing"><i class="fa-solid fa-circle-question me-1"></i> UNKNOWN</span>' ?></span>
                            </div>
                            <div class="data-row mt-4 pt-3 border-top-dark">
                                <span class="data-label">JOINED DATE</span>
                                <span class="data-value text-silver"><?= date('M d, Y', strtotime($adminData['createdAt'])) ?> <span class="text-silver ms-1" style="font-size: 0.8rem; opacity: 0.7;">(<?= $accountAge ?> Days)</span></span>
                            </div>
                            <div class="data-row mt-2">
                                <span class="data-label">LAST LOGIN</span>
                                <span class="data-value text-silver"><?= $adminData['lastLogin'] ? date('H:i | M d, Y', strtotime($adminData['lastLogin'])) : 'Current Session' ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 col-md-7 scroll-reveal">

                    <div class="form-panel mb-5">
                        <div class="panel-header d-flex align-items-center gap-3">
                            <div class="icon-box border-gold"><i class="fa-solid fa-user-pen text-gold"></i></div>
                            <div>
                                <h4 class="panel-title m-0">Personal Information</h4>
                                <span class="text-silver font-monospace" style="font-size: 0.75rem;">Update your personal details and contact info.</span>
                            </div>
                        </div>

                        <div class="panel-body p-4 p-xl-5">
                            <form method="POST" action="profile.php" enctype="multipart/form-data">
                                <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">
                                <input type="hidden" name="action" value="update_profile">
                                <input type="file" name="avatar" id="avatarInput" class="d-none" accept="image/jpeg, image/png, image/webp, image/gif">

                                <div class="row g-4 mb-4">
                                    <div class="col-md-6">
                                        <div class="custom-input-box">
                                            <div class="box-icon"><i class="fa-solid fa-i-cursor"></i></div>
                                            <input type="text" name="firstName" class="box-input" placeholder="First Name" value="<?= htmlspecialchars($adminData['firstName']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="custom-input-box">
                                            <div class="box-icon"><i class="fa-solid fa-i-cursor"></i></div>
                                            <input type="text" name="lastName" class="box-input" placeholder="Last Name" value="<?= htmlspecialchars($adminData['lastName']) ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-4 mb-5">
                                    <div class="col-md-6">
                                        <div class="custom-input-box">
                                            <div class="box-icon"><i class="fa-solid fa-phone"></i></div>
                                            <input type="text" name="phoneNo" class="box-input" placeholder="Phone Number (Optional)" value="<?= htmlspecialchars($adminData['phoneNo'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="custom-input-box">
                                            <div class="box-icon"><i class="fa-solid fa-venus-mars"></i></div>
                                            <select name="gender" class="box-input" style="appearance: none; cursor: pointer;">
                                                <option value="" class="bg-dark" style="color: #888;">Select Gender...</option>
                                                <option value="Male" class="bg-dark" <?= $adminData['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                                <option value="Female" class="bg-dark" <?= $adminData['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                                <option value="Other" class="bg-dark" <?= $adminData['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <button type="submit" class="btn-save-standard" id="saveProfileBtn">
                                        <i class="fa-solid fa-check me-2"></i> Save Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="form-panel border-danger-subtle">
                        <div class="panel-header d-flex align-items-center gap-3" style="background: rgba(231, 76, 60, 0.02);">
                            <div class="icon-box border-danger"><i class="fa-solid fa-lock text-danger"></i></div>
                            <div>
                                <h4 class="panel-title m-0 text-danger">Password & Security</h4>
                                <span class="text-silver font-monospace" style="font-size: 0.75rem;">Change your account password securely.</span>
                            </div>
                        </div>

                        <div class="panel-body p-4 p-xl-5 position-relative overflow-hidden">
                            <div class="security-grid-bg"></div>

                            <form method="POST" action="profile.php" class="position-relative" style="z-index: 2;">
                                <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">
                                <input type="hidden" name="action" value="change_password">

                                <div class="custom-input-box mb-4">
                                    <div class="box-icon text-danger"><i class="fa-solid fa-key"></i></div>
                                    <input type="password" name="currentPassword" class="box-input" placeholder="Current Password" required>
                                </div>

                                <div class="row g-4 mb-5">
                                    <div class="col-md-6">
                                        <div class="custom-input-box">
                                            <div class="box-icon"><i class="fa-solid fa-shield"></i></div>
                                            <input type="password" name="newPassword" class="box-input" placeholder="New Password (Min 8 Chars)" required minlength="8">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="custom-input-box">
                                            <div class="box-icon"><i class="fa-solid fa-shield-check"></i></div>
                                            <input type="password" name="confirmPassword" class="box-input" placeholder="Confirm New Password" required minlength="8">
                                        </div>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <button type="submit" class="btn-danger-standard">
                                        <i class="fa-solid fa-arrows-rotate me-2"></i> Update Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/admin-global.js')) ?>"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/profile.js')) ?>"></script>
</body>
</html>