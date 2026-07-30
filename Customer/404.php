<?php
// customer/404.php - The Velvet Vogue 404 Game
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
http_response_code(404);
header('Cache-Control: no-store, private');

$loggedInUser = '';
if (isset($_SESSION['userID']) && isset($_SESSION['firstName'])) {
    $firstName = (string) $_SESSION['firstName'];
    $loggedInUser = strtoupper(function_exists('mb_substr') ? mb_substr($firstName, 0, 10) : substr($firstName, 0, 10));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= vv_e(vv_app_url('Customer/')) ?>">
    <link rel="icon" href="<?= vv_e(vv_versioned_asset('../favicon.ico')) ?>" sizes="any">
    <link rel="icon" type="image/svg+xml" href="<?= vv_e(vv_versioned_asset('../Assets/images/brand/velvet-vogue-mark.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= vv_e(vv_versioned_asset('../Assets/images/brand/velvet-vogue-favicon-32.png')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= vv_e(vv_versioned_asset('../Assets/images/brand/velvet-vogue-apple-touch.png')) ?>">
    <meta name="theme-color" content="#050505">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <meta name="csrf-token" content="<?= vv_e(vv_csrf_token()) ?>">
    <meta name="app-base-url" content="<?= vv_e(vv_app_url()) ?>">
    <title>404 - Page Not Found | Velvet Vogue</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/style.css')) ?>">

    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/pages/404.css')) ?>">
</head>

<body class="vv-404-intro-active" style="background-color: #000; overflow-x: hidden;">

    <div id="overrideAlert" class="override-alert">
        <h1 class="mega-glitch" data-text="SYSTEM OVERRIDE" style="font-size: 4rem;">SYSTEM OVERRIDE</h1>
        <p class="font-monospace text-white mt-2 fs-5">RESTRICTIONS LIFTED. INITIATING PROTOCOL.</p>
    </div>

    <div id="truckCrashOverlay" class="crash-intro-screen" aria-hidden="true">
        <div class="shatter-logo">
            <span class="sl-1">V</span><span class="sl-2">E</span><span class="sl-3">L</span><span class="sl-4">V</span><span class="sl-5">E</span><span class="sl-6">T</span>
            <span class="ms-3 sl-7">V</span><span class="sl-8">O</span><span class="sl-9">G</span><span class="sl-10">U</span><span class="sl-11">E</span>
        </div>
        <div class="crash-camera-rig">
            <div class="crash-container">
                <div class="cyber-rig">
                    <div class="rig-headlight-beam"></div>
                    <div class="rig-trailer">
                        <div class="trailer-lines"></div><span class="font-monospace">VELVET VOGUE</span>
                    </div>
                    <div class="rig-cab">
                        <div class="rig-window"></div>
                        <div class="rig-grill"></div>
                        <div class="rig-headlight"></div>
                    </div>
                    <div class="rig-wheel w1">
                        <div class="wheel-hub"></div>
                    </div>
                    <div class="rig-wheel w2">
                        <div class="wheel-hub"></div>
                    </div>
                    <div class="rig-wheel w3">
                        <div class="wheel-hub"></div>
                    </div>
                    <div class="rig-wheel w4">
                        <div class="wheel-hub"></div>
                    </div>
                </div>
                <div class="armored-vault-door">
                    <div class="door-half door-top">
                        <div class="hazard-tape"></div>
                    </div>
                    <div class="door-seam"></div>
                    <div class="door-half door-bottom">
                        <div class="hazard-tape"></div>
                    </div>
                </div>
                <div class="spark s1"></div>
                <div class="spark s2"></div>
                <div class="spark s3"></div>
                <div class="spark s4"></div>
                <div class="spark s5"></div>
                <div class="spark s6"></div>
                <div class="spilled-item i-1">👗</div>
                <div class="spilled-item i-2">👠</div>
                <div class="spilled-item i-3">👜</div>
                <div class="spilled-item i-4">📦</div>
                <div class="spilled-item i-5">🧥</div>
                <div class="crash-flash"></div>
                <div class="crash-smoke"></div>
            </div>
        </div>
    </div>

    <noscript>
        <style>
            #truckCrashOverlay { display: none !important; }
            .error-wrapper { visibility: visible !important; }
        </style>
    </noscript>

    <main class="error-wrapper position-relative d-flex flex-column align-items-center justify-content-center py-5" style="min-height: 100vh;">
        <div class="cinematic-grain"></div>
        <div class="transit-grid-bg opacity-25"></div>

        <div id="parallaxContainer" class="container position-relative z-2 text-center w-100 vv-vr-scene">

            <div class="mb-4 gsap-fade-in vv-vr-layer vv-vr-copy">
                <h1 class="mega-glitch" data-text="404">404</h1>
                <h2 class="text-white text-uppercase tracking-luxury mt-2 mb-3" style="font-size: 1.5rem; letter-spacing: 8px;">
                    PAGE NOT FOUND
                </h2>
                <p class="text-silver font-monospace mx-auto mb-4" style="max-width: 600px; font-size: 0.85rem; line-height: 1.8;">
                    The page you are looking for doesn't exist. Before you go, try catching the falling clothes! <br>
                    <span class="gold-text fw-bold">RUMOR: A restricted 15% Syndicate Access Key is hidden in the debris.</span>
                </p>
            </div>

            <div class="row justify-content-center gsap-fade-in mt-2 vv-vr-layer vv-vr-game">
                <div class="col-12">
                    <div class="game-zone-wrapper p-2">

                        <div class="d-flex justify-content-between align-items-center mb-2 px-3 pt-2 position-relative">
                            <div class="font-monospace text-white d-flex align-items-center gap-2">
                                <span style="font-size: 0.8rem; color: #888;">SCORE:</span>
                                <span id="scoreVal" class="gold-text fs-4" style="font-weight: 900; transition: color 0.3s;">$0</span>
                            </div>

                            <div class="d-flex align-items-center gap-3">
                                <button id="btnShowLeaderboard" class="btn-trophy" title="View Top Scores"><i class="fa-solid fa-trophy"></i></button>
                                <div class="font-monospace" style="font-size: 1.5rem; letter-spacing: 2px;" id="livesVal">❤️❤️❤️</div>
                            </div>
                        </div>

                        <div id="gameZone" class="game-zone position-relative">
                            <div class="laser-grid-bg"></div>

                            <div id="goofyOverlay" class="goofy-overlay d-flex flex-column align-items-center justify-content-center">
                                <h2 class="text-white tracking-luxury mb-2" id="goofyTitle" style="font-size: 1.8rem;">CATCH & WIN</h2>
                                <p class="text-white font-monospace mb-4 fw-bold text-center px-3" id="goofyDesc" style="letter-spacing: 1px; border-bottom: 1px solid rgba(212,175,55,0.5); padding-bottom: 10px;">
                                    CATCH THE CLOTHES. AVOID THE BOMBS. EARN A REWARD.
                                </p>

                                <div id="leaderboardInputUI" class="mb-4 text-center" style="display: none;">
                                    <p class="gold-text font-monospace mb-2" style="font-size: 0.8rem;">SAVE YOUR SCORE</p>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" id="playerNameInput" class="arcade-input" placeholder="YOUR NAME" maxlength="10" value="<?= vv_e($loggedInUser) ?>" <?= !empty($loggedInUser) ? 'readonly' : '' ?>>
                                    </div>
                                </div>

                                <div class="d-flex flex-column flex-sm-row gap-3">
                                    <button id="startGoofyGame" class="btn-cyber-start px-5">START GAME</button>
                                    <button id="btnCashOut" class="btn-solid-gold px-4" style="display: none;"><i class="fa-solid fa-sack-dollar me-2"></i> CASH OUT & SAVE</button>
                                </div>
                            </div>

                            <div id="leaderboardOverlay" class="goofy-overlay flex-column align-items-center justify-content-center" style="display: none !important; background: rgba(5,5,5,0.98);">
                                <div class="w-100 px-4" style="max-width: 500px;">
                                    <div class="text-center mb-4 border-bottom-dark pb-3">
                                        <i class="fa-solid fa-trophy gold-text fs-2 mb-2"></i>
                                        <h3 class="text-white tracking-luxury mb-0">TOP SCORES</h3>
                                    </div>
                                    <ul id="leaderboardList" class="leaderboard-list list-unstyled m-0 font-monospace mb-4" style="max-height: 250px; overflow-y: auto;">
                                        <li class="text-center text-silver py-4"><i class="fa-solid fa-circle-notch fa-spin"></i> LOADING SCORES...</li>
                                    </ul>
                                    <button id="btnCloseLeaderboard" class="btn-outline-gold w-100 py-3">CLOSE</button>
                                </div>
                            </div>

                            <div id="couponOverlay" class="goofy-overlay flex-column align-items-center justify-content-center" style="display: none !important; background: rgba(0,0,0,0.95); border: 2px solid var(--color-gold-metallic);">
                                <i class="fa-solid fa-key gold-text mb-3" style="font-size: 3rem;"></i>
                                <h2 class="text-white tracking-luxury mb-2" style="font-size: 1.5rem;">REWARD UNLOCKED</h2>
                                <p class="text-silver font-monospace mb-2" style="max-width: 350px; font-size: 0.8rem;">You earned a 15% discount. Copy the code below to use at checkout.</p>

                                <div class="d-flex align-items-stretch mb-4 mt-3" style="background: #000; border: 1px dashed var(--color-gold-metallic);">
                                    <div class="p-3 d-flex align-items-center"><span class="font-monospace fs-3 text-white fw-bold" id="generatedCouponCode" style="letter-spacing: 4px;">GENERATING CODE...</span></div>
                                    <button id="btnCopyCoupon" class="btn-solid-gold px-4" title="Copy"><i class="fa-solid fa-copy fs-4"></i></button>
                                </div>

                                <div class="d-flex flex-column flex-sm-row gap-3">
                                    <a href="shop.php" class="btn-outline-gold px-4 py-3 text-decoration-none text-center">GO TO SHOP</a>
                                    <button id="btnResumeFromCoupon" class="btn-cyber-start">PLAY AGAIN</button>
                                </div>
                            </div>

                            <div id="playerCart" class="player-cart">🛒</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <input type="hidden" id="authUserName" value="<?= vv_e($loggedInUser) ?>">

    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/security.js')) ?>" defer></script>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/pages/404.js')) ?>" defer></script>
</body>

</html>