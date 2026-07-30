<?php
// admin/login.php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
if (isset($_SESSION['userID']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: dashboard.php");
    exit;
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
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <meta name="csrf-token" content="<?= vv_e(vv_csrf_token()) ?>">
    <meta name="app-base-url" content="<?= vv_e(vv_app_url()) ?>">
    <title>Admin Portal | Velvet Vogue</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;900&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/adminpages/login.css')) ?>">
</head>
<body>

    <div class="curtain-overlay" id="curtainOverlay">
        <div class="curtain top"></div>
        <div class="curtain-seam"></div>
        <div class="curtain bottom"></div>
    </div>

    <div class="studio-spotlight"></div>
    <div class="ambient-flare flare-1"></div>
    <div class="ambient-flare flare-2"></div>
    <div class="cinematic-grain"></div>
    <div class="transit-grid-bg"></div>

    <main class="login-wrapper">
        <div class="glass-card" id="loginCard">

            <div class="card-header">
                <div class="crown-plate-wrapper" id="crownReveal">
                    <div class="metal-plate">
                        <i class="fa-solid fa-crown brand-crown"></i>
                        <div class="shimmer-sweep"></div>
                    </div>
                </div>
                <h1 class="brand-title gold-foil-text">Velvet Vogue</h1>
                <h2 class="form-title">Administration</h2>
            </div>

            <div class="alert-box" id="errorBox" style="display: none;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span id="errorText"></span>
            </div>

            <form id="adminLoginForm" method="post" autocomplete="off">
                    <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">

                <div class="tactile-input-group mb-4">
                    <label for="email" class="tactile-label">Email</label>
                    <div class="input-carving">
                        <i class="fa-solid fa-user input-icon"></i>
                        <input type="email" name="email" id="email" class="tactile-input" maxlength="254" autocomplete="email" required>
                        <div class="laser-trace"></div>
                    </div>
                </div>

                <div class="tactile-input-group mb-5">
                    <label for="password" class="tactile-label">Password</label>
                    <div class="input-carving">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" class="tactile-input" maxlength="72" autocomplete="current-password" required>
                        <div class="laser-trace"></div>
                        <button type="button" class="btn-eye" id="togglePassword">
                            <i class="fa-solid fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="btn-mask-container">
                    <div class="btn-mask-base-text" id="btnTextBase">LOGIN</div>
                    <button type="submit" class="btn-mask-action" id="btnSubmit">LOGIN</button>
                </div>

            </form>

        </div>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/security.js')) ?>"></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/adminpages/login.js')) ?>"></script>
</body>
</html>