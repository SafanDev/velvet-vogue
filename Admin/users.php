<?php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once __DIR__ . '/../Config/db.php';
require_once __DIR__ . '/Middleware/AuthMiddleware.php';

AuthMiddleware::requireAdmin();
$currentAdminID = (int) $_SESSION['userID'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, private');

    $action = (string) $_POST['action'];

    if ($action === 'add_user') {
        vv_enforce_rate_limit('admin-add-user', 20, 3600, (string) $currentAdminID);

        $firstName = trim((string) ($_POST['firstName'] ?? ''));
        $lastName = trim((string) ($_POST['lastName'] ?? ''));
        $email = vv_normalize_email((string) ($_POST['email'] ?? ''));
        $plainPassword = (string) ($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'customer';

        if (!vv_valid_name($firstName) || !vv_valid_name($lastName) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || strlen($plainPassword) < 10 || strlen($plainPassword) > 72) {
            vv_json_response(['status' => 'error', 'message' => 'Enter valid user details and a password of at least 10 characters.'], 422);
        }

        $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            vv_json_response(['status' => 'error', 'message' => 'The account could not be created.'], 500);
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO `user` (firstName, lastName, email, password, role, isActive) VALUES (?, ?, ?, ?, ?, 1)');
            $stmt->execute([$firstName, $lastName, $email, $passwordHash, $role]);
            vv_json_response(['status' => 'success', 'message' => 'Identity successfully established.']);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                vv_json_response(['status' => 'error', 'message' => 'The email address already exists.'], 409);
            }
            error_log('Admin user creation failed: ' . $exception->getMessage());
            vv_json_response(['status' => 'error', 'message' => 'The account could not be created.'], 500);
        }
    }

    $targetUserID = (int) ($_POST['userID'] ?? 0);
    if ($targetUserID < 1 || $targetUserID === $currentAdminID) {
        vv_json_response(['status' => 'error', 'message' => 'Your current administrator account cannot be modified here.'], 403);
    }

    if (!in_array($action, ['toggle_status', 'change_role'], true)) {
        vv_json_response(['status' => 'error', 'message' => 'Unknown action.'], 400);
    }

    try {
        $pdo->beginTransaction();

        $targetStmt = $pdo->prepare('SELECT role, isActive FROM `user` WHERE userID = ? LIMIT 1 FOR UPDATE');
        $targetStmt->execute([$targetUserID]);
        $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            $pdo->rollBack();
            vv_json_response(['status' => 'error', 'message' => 'User not found.'], 404);
        }

        if ($action === 'toggle_status') {
            $newStatus = (int) ($_POST['isActive'] ?? -1);
            if (!in_array($newStatus, [0, 1], true)) {
                $pdo->rollBack();
                vv_json_response(['status' => 'error', 'message' => 'Invalid account status.'], 422);
            }

            if ($newStatus === 0 && $target['role'] === 'admin' && (int) $target['isActive'] === 1) {
                $activeAdmins = $pdo->query("SELECT userID FROM `user` WHERE role = 'admin' AND isActive = 1 FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
                if (count($activeAdmins) <= 1) {
                    $pdo->rollBack();
                    vv_json_response(['status' => 'error', 'message' => 'At least one active administrator must remain.'], 409);
                }
            }

            $stmt = $pdo->prepare('UPDATE `user` SET isActive = ? WHERE userID = ?');
            $stmt->execute([$newStatus, $targetUserID]);
            $pdo->commit();
            vv_json_response(['status' => 'success', 'message' => $newStatus === 1 ? 'Account activated.' : 'Account suspended.']);
        }

        $newRole = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'customer';
        if ($target['role'] === 'admin' && $newRole !== 'admin' && (int) $target['isActive'] === 1) {
            $activeAdmins = $pdo->query("SELECT userID FROM `user` WHERE role = 'admin' AND isActive = 1 FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
            if (count($activeAdmins) <= 1) {
                $pdo->rollBack();
                vv_json_response(['status' => 'error', 'message' => 'At least one active administrator must remain.'], 409);
            }
        }

        $stmt = $pdo->prepare('UPDATE `user` SET role = ? WHERE userID = ?');
        $stmt->execute([$newRole, $targetUserID]);
        $pdo->commit();
        vv_json_response(['status' => 'success', 'message' => 'Role updated to ' . strtoupper($newRole) . '.']);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Admin user update failed: ' . $exception->getMessage());
        vv_json_response(['status' => 'error', 'message' => 'The account could not be updated.'], 500);
    }
}

// =======================================================
// FETCH ALL USERS (Admin pinned to top)
// =======================================================
$query = "
    SELECT
        userID, firstName, lastName, email, phoneNo, role, isActive, createdAt, lastLogin,
        (SELECT COUNT(*) FROM `order` WHERE userID = `User`.userID) as totalOrders
    FROM `user`
    ORDER BY (userID = " . (int)$currentAdminID . ") DESC, createdAt DESC
";
$users = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Metrics Calculation
$totalUsers = count($users);
$adminCount = 0;
$suspendedCount = 0;
$activeCustomerCount = 0;

foreach ($users as $u) {
    if ($u['role'] === 'admin') {
        $adminCount++;
    } elseif ($u['role'] === 'customer' && $u['isActive'] == 1) {
        $activeCustomerCount++;
    }
    if ($u['isActive'] == 0) {
        $suspendedCount++;
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
    <title>Client Dossier | Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/admin-global.css')) ?>">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/users.css')) ?>">
</head>
<body>

    <?php include 'adminheader.php'; ?>

    <main class="container-fluid px-xl-5 pt-4 pb-5" style="max-width: 1700px;">

        <div class="d-flex justify-content-between align-items-end mb-4 pb-3 border-bottom-dark scroll-reveal visible">
            <div>
                <div class="d-flex align-items-center gap-3 mb-1">
                    <span class="simple-label text-gold m-0">Identity Access Management</span>
                    <span class="badge-count text-white" id="totalUsersBadge"><?= $totalUsers ?> Profiles</span>
                </div>
                <h1 class="massive-title text-white m-0">Client Dossier</h1>
            </div>

            <div class="d-flex gap-4 align-items-center">
                <div class="tactical-search">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="userSearch" class="search-input" placeholder="Search Identity..." autocomplete="off">
                </div>

                <button type="button" class="btn-nebula" id="openAddUserModal">
                    <span>New Identity</span>
                    <i class="fa-solid fa-plus ms-2"></i>
                </button>
            </div>
        </div>

        <div class="row g-4 mb-5 scroll-reveal visible" id="metricsContainer">
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-users metric-icon text-white"></i>
                    <div class="metric-info">
                        <span class="metric-label">Total Base</span>
                        <span class="metric-value" id="countTotal"><?= $totalUsers ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-user-check metric-icon text-success"></i>
                    <div class="metric-info">
                        <span class="metric-label">Active Clients</span>
                        <span class="metric-value text-success" id="countCustomers"><?= $activeCustomerCount ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-user-shield metric-icon text-gold"></i>
                    <div class="metric-info">
                        <span class="metric-label">Staff Clearances</span>
                        <span class="metric-value text-gold" id="countAdmins"><?= $adminCount ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card spotlight-card">
                    <i class="fa-solid fa-user-lock metric-icon text-danger"></i>
                    <div class="metric-info">
                        <span class="metric-label">Suspended</span>
                        <span class="metric-value text-danger" id="countSuspended"><?= $suspendedCount ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-container-solid mb-5 scroll-reveal visible">
            <div class="table-responsive pb-2">
                <table class="table custom-ledger-table align-middle m-0" id="usersTable">
                    <thead class="sticky-top z-3">
                        <tr>
                            <th style="padding-left: 40px;">Identity Profile</th>
                            <th>Role / Clearance</th>
                            <th>Engagement</th>
                            <th>Date Joined</th>
                            <th class="text-end" style="padding-right: 40px;">Account Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($users)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted font-body">No identities found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($users as $user):
                                $isSelf = ($user['userID'] === $currentAdminID);
                                // ONLY your account gets the gold name.
                                $nameColorClass = $isSelf ? 'text-gold' : 'text-white';
                            ?>
                                <tr class="ledger-row <?= $user['isActive'] == 0 ? 'row-suspended' : '' ?>"
                                    data-role="<?= vv_e($user['role']) ?>"
                                    data-active="<?= $user['isActive'] ?>"
                                    data-search="<?= vv_e(strtolower($user['firstName'] . ' ' . $user['lastName'] . ' ' . $user['email'] . ' ' . $user['phoneNo'])) ?>">

                                    <td style="padding-left: 40px; padding-top: 25px; padding-bottom: 25px;">
                                        <div class="d-flex align-items-center mb-1">
                                            <span class="font-heading data-title <?= $nameColorClass ?>" style="font-size: 1.05rem; text-transform: uppercase;">
                                                <?= htmlspecialchars($user['firstName'] . ' ' . $user['lastName']) ?>
                                            </span>
                                            <span class="ms-2 text-muted font-monospace" style="font-size: 0.75rem;">#<?= $user['userID'] ?></span>
                                        </div>
                                        <div class="d-flex align-items-center gap-3 data-subtext mt-1">
                                            <span><i class="fa-regular fa-envelope me-1" style="color:#555;"></i> <?= htmlspecialchars($user['email']) ?></span>
                                            <?php if($user['phoneNo']): ?>
                                                <span><i class="fa-solid fa-phone me-1" style="color:#555;"></i> <?= htmlspecialchars($user['phoneNo']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?php if($isSelf): ?>
                                            <div class="protected-badge">
                                                <i class="fa-solid fa-shield-halved me-2"></i> YOUR ACCOUNT
                                            </div>
                                        <?php else: ?>
                                            <div class="elegant-select-wrapper">
                                                <select class="elegant-select role-select text-white" data-user-id="<?= $user['userID'] ?>">
                                                    <option value="customer" <?= $user['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                </select>
                                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="text-white font-body" style="font-size: 1.05rem; font-weight: 500;"><?= $user['totalOrders'] ?></span>
                                        <span class="data-subtext ms-1 text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px;">Orders</span>
                                    </td>

                                    <td>
                                        <span class="data-text text-silver font-body"><?= date('M d, Y', strtotime($user['createdAt'])) ?></span>
                                    </td>

                                    <td class="text-end" style="padding-right: 40px;">
                                        <?php if($isSelf): ?>
                                            <span class="text-success font-body" style="font-size: 0.75rem; letter-spacing: 1px; font-weight: 700;"><i class="fa-solid fa-check me-1"></i> ACTIVE</span>
                                        <?php else: ?>
                                            <label class="luxury-switch">
                                                <input type="checkbox" class="status-toggle" data-user-id="<?= $user['userID'] ?>" <?= $user['isActive'] == 1 ? 'checked' : '' ?>>
                                                <span class="slider"></span>
                                                <span class="status-text"><?= $user['isActive'] == 1 ? 'ACTIVE' : 'BANNED' ?></span>
                                            </label>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <div class="side-panel-overlay" id="sidePanelOverlay"></div>
    <div class="side-panel" id="sidePanel">
        <div class="panel-header">
            <h4 class="font-heading text-gold m-0 text-uppercase" style="font-size: 1.1rem; letter-spacing: 2px;">Establish Identity</h4>
            <button type="button" class="btn-close-panel" id="closeSidePanel"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="panel-body">

            <form id="addUserForm" method="post" autocomplete="off">
                <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">
                <input type="hidden" name="action" value="add_user">

                <input style="display:none" type="email" name="fakeusernameremembered"/>
                <input style="display:none" type="password" name="fakepasswordremembered"/>

                <div class="row g-3 mb-4 mt-2">
                    <div class="col-6">
                        <div class="form-floating-custom">
                            <input type="text" name="firstName" id="firstName" class="luxury-input" placeholder=" " autocomplete="new-password" required>
                            <label for="firstName">First Name</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-floating-custom">
                            <input type="text" name="lastName" id="lastName" class="luxury-input" placeholder=" " autocomplete="new-password" required>
                            <label for="lastName">Last Name</label>
                        </div>
                    </div>
                </div>

                <div class="form-floating-custom mb-4">
                    <input type="email" name="email" id="email" class="luxury-input" placeholder=" " autocomplete="new-password" required>
                    <label for="email">Secure Email</label>
                </div>

                <div class="form-floating-custom mb-4">
                    <input type="password" name="password" id="password" class="luxury-input" placeholder=" " autocomplete="new-password" required>
                    <label for="password">Initial Password</label>
                </div>

                <div class="mb-5 mt-2">
                    <span class="d-block mb-2 font-body text-silver" style="font-size: 0.75rem; letter-spacing: 1px; text-transform: uppercase; font-weight: 600;">Account Role</span>
                    <div class="elegant-select-wrapper w-100" style="border-color: rgba(255,255,255,0.2);">
                        <select name="role" class="elegant-select w-100 text-gold" style="height: 45px; font-size: 0.85rem;">
                            <option value="customer" style="color: #fff;">Customer</option>
                            <option value="admin" style="color: #fff;">Admin</option>
                        </select>
                        <i class="fa-solid fa-chevron-down select-arrow"></i>
                    </div>
                </div>

                <button type="submit" class="btn-core-solid w-100" id="submitUserBtn">
                    CREATE
                </button>
            </form>
        </div>
    </div>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999">
        <div id="actionToast" class="toast align-items-center text-white bg-dark border border-secondary" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body font-body" id="toastMessage"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/admin-global.js')) ?>"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/users.js')) ?>"></script>
</body>
</html>