<?php

require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once __DIR__ . '/../Config/db.php';

vv_restore_remembered_user($pdo);

$globalCartCount = 0;
$globalWishlistCount = 0;

if (isset($_SESSION['userID'])) {
    $userID = (int) $_SESSION['userID'];
    $cachedCounts = $_SESSION['_nav_counts'] ?? null;
    $cacheIsFresh = is_array($cachedCounts)
        && (int) ($cachedCounts['user_id'] ?? 0) === $userID
        && time() - (int) ($cachedCounts['updated_at'] ?? 0) < 15;

    if ($cacheIsFresh) {
        $globalCartCount = (int) ($cachedCounts['cart_count'] ?? 0);
        $globalWishlistCount = (int) ($cachedCounts['wishlist_count'] ?? 0);
    } else {
        $counterStmt = $pdo->prepare("
            SELECT
                (SELECT COALESCE(SUM(ci.quantity), 0)
                 FROM cartitem ci
                 JOIN cart c ON ci.cartID = c.cartID
                 WHERE c.userID = ?) AS cartCount,
                (SELECT COUNT(*) FROM wishlist WHERE userID = ?) AS wishlistCount
        ");
        $counterStmt->execute([$userID, $userID]);
        $counters = $counterStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $globalCartCount = (int) ($counters['cartCount'] ?? 0);
        $globalWishlistCount = (int) ($counters['wishlistCount'] ?? 0);
        $_SESSION['_nav_counts'] = [
            'user_id' => $userID,
            'cart_count' => $globalCartCount,
            'wishlist_count' => $globalWishlistCount,
            'updated_at' => time(),
        ];
    }
} else {
    foreach ($_SESSION['cart'] ?? [] as $item) {
        $globalCartCount += max(0, (int) ($item['quantity'] ?? 0));
    }
    $globalWishlistCount = count($_SESSION['wishlist'] ?? []);
}

// Navigation active state logic
$current_page = basename($_SERVER['PHP_SELF']);
$is_nav_page = in_array($current_page, ['home.php', 'shop.php', 'lookbook.php', 'about.php', 'contact.php', '']);
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
    <meta name="csrf-token" content="<?= vv_e(vv_csrf_token()) ?>">
    <meta name="app-base-url" content="<?= vv_e(vv_app_url()) ?>">
    <title>Velvet Vogue</title>

    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/style.css')) ?>">
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/security.js')) ?>" defer></script>

    <?php if (isset($page_css)): ?>
        <link rel="stylesheet" href="<?= vv_e(vv_versioned_asset('../Assets/css/pages/' . (string) $page_css)) ?>">
    <?php endif; ?>

    <?php if (isset($page_css) && $page_css === 'home.css'): ?>
        <link rel="preload" as="video" href="../Assets/video/heroBackground.webm" type="video/webm">
    <?php endif; ?>

    <style>
        /* =========================================
           MOBILE MENU OVERLAY & HAMBURGER (RESPONSIVE)
           ========================================= */
        .mobile-menu-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            width: 42px;
            height: 42px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-end;
            gap: 8px;
            z-index: 2005;
            position: relative;
            padding: 0;
            margin-left: 10px;
        }

        .mobile-menu-btn span {
            display: block;
            height: 2px;
            background: #fff;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            transform-origin: right center;
            box-shadow: 0 0 5px rgba(255, 255, 255, 0.5);
        }

        /* Asymmetric Fashion Aesthetic */
        .mobile-menu-btn span:nth-child(1) {
            width: 30px;
        }

        .mobile-menu-btn span:nth-child(2) {
            width: 20px;
        }

        /* Active State: Transforms into an 'X' */
        .mobile-menu-btn.active span:nth-child(1) {
            transform: rotate(-45deg) translateY(-2px);
            width: 30px;
            background: var(--color-gold-metallic, #d4af37);
            box-shadow: 0 0 10px var(--color-gold-metallic, #d4af37);
        }

        .mobile-menu-btn.active span:nth-child(2) {
            transform: rotate(45deg) translateY(2px);
            width: 30px;
            background: var(--color-gold-metallic, #d4af37);
            box-shadow: 0 0 10px var(--color-gold-metallic, #d4af37);
        }

        /* Cinematic Overlay */
        .mobile-nav-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(5, 5, 5, 0.98);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            z-index: 1999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.4s ease;
        }

        .mobile-nav-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .mobile-nav-inner {
            width: 100%;
            padding: 0 10%;
        }

        .mobile-nav-links {
            list-style: none;
            padding: 0;
            margin: 0;
            text-align: center;
        }

        .mobile-nav-links li {
            overflow: hidden;
            margin: 25px 0;
        }

        .mobile-link {
            font-family: var(--font-heading, "Playfair Display", serif);
            font-size: 2.5rem;
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 5px;
            text-decoration: none;
            display: block;
            transform: translateY(100%);
            opacity: 0;
            transition: color 0.3s ease;
        }

        @media(min-width: 576px) {
            .mobile-link {
                font-size: 3.5rem;
            }
        }

        .mobile-link:hover,
        .mobile-link:active {
            color: var(--color-gold-metallic, #d4af37);
            text-decoration: none;
        }
    </style>
</head>

<body>

    <header class="main-header" style="position: sticky; top: 0; z-index: 2000;">
        <a href="home.php" class="brand-logo" style="position: relative; z-index: 2001;">Velvet Vogue</a>

        <div class="nav-wrapper d-none d-lg-block">
            <div class="nav-outer" id="navOuter">
                <div class="nav-inner" id="navInner">
                    <div class="hover-pill" id="hoverPill"></div>

                    <div class="active-glow" id="activeGlow" style="<?= !$is_nav_page ? 'display: none !important;' : '' ?>"></div>
                    <div class="active-pill" id="activePill" style="<?= !$is_nav_page ? 'display: none !important;' : '' ?>"></div>

                    <a href="home.php" class="nav-item <?= ($current_page == 'home.php' || $current_page == '') ? 'active' : '' ?>">
                        <span class="item-inner" style="animation-delay:0.10s">
                            <i class="fa-solid fa-house nav-icon"></i>
                            <span>Home</span>
                        </span>
                    </a>

                    <a href="shop.php" class="nav-item <?= ($current_page == 'shop.php') ? 'active' : '' ?>">
                        <span class="item-inner" style="animation-delay:0.16s">
                            <i class="fa-solid fa-bag-shopping nav-icon"></i>
                            <span>Shop</span>
                        </span>
                    </a>

                    <a href="lookbook.php" class="nav-item <?= ($current_page == 'lookbook.php') ? 'active' : '' ?>">
                        <span class="item-inner" style="animation-delay:0.22s">
                            <i class="fa-solid fa-camera-retro nav-icon"></i>
                            <span>Gallery</span>
                        </span>
                    </a>

                    <a href="about.php" class="nav-item <?= ($current_page == 'about.php') ? 'active' : '' ?>">
                        <span class="item-inner" style="animation-delay:0.28s">
                            <i class="fa-solid fa-users nav-icon"></i>
                            <span>About</span>
                        </span>
                    </a>

                    <a href="contact.php" class="nav-item <?= ($current_page == 'contact.php') ? 'active' : '' ?>">
                        <div class="badge"></div>
                        <span class="item-inner" style="animation-delay:0.34s">
                            <i class="fa-solid fa-envelope nav-icon"></i>
                            <span>Contact</span>
                        </span>
                    </a>
                </div>
            </div>
        </div>

        <div class="header-actions" style="position: relative; z-index: 2001;">
            <?php if (isset($_SESSION['userID'])): ?>

                <a href="wishlist.php" class="action-icon ai-wish <?= ($current_page == 'wishlist.php') ? 'active' : '' ?>" title="Wishlist" style="position: relative;">
                    <i class="fa-regular fa-heart"></i>
                    <?php if ($globalWishlistCount > 0): ?>
                        <span class="vv-header-wish-badge" id="globalWishBadge"><?= $globalWishlistCount ?></span>
                    <?php endif; ?>
                </a>

                <a href="cart.php" class="action-icon ai-cart <?= ($current_page == 'cart.php') ? 'active' : '' ?>" title="Cart" style="position: relative;">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <?php if ($globalCartCount > 0): ?>
                        <span class="vv-header-cart-badge" id="globalCartBadge"><?= $globalCartCount ?></span>
                    <?php endif; ?>
                </a>

                <a href="dashboard.php" class="action-icon ai-user <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>" title="My Profile">
                    <i class="fa-regular fa-user"></i>
                </a>

            <?php else: ?>

                <a href="wishlist.php" class="action-icon ai-wish d-lg-none" style="position: relative;">
                    <i class="fa-regular fa-heart"></i>
                    <?php if ($globalWishlistCount > 0): ?>
                        <span class="vv-header-wish-badge" id="globalWishBadge"><?= $globalWishlistCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="cart.php" class="action-icon ai-cart d-lg-none" style="position: relative;">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <?php if ($globalCartCount > 0): ?>
                        <span class="vv-header-cart-badge" id="globalCartBadge"><?= $globalCartCount ?></span>
                    <?php endif; ?>
                </a>

                <a href="auth.php" class="btn-premium btn-sec d-none d-lg-inline-flex">Login</a>
                <a href="auth.php?action=register" class="btn-premium btn-pri d-none d-lg-inline-flex">Sign Up</a>
            <?php endif; ?>

            <button class="mobile-menu-btn d-lg-none" id="mobileMenuBtn">
                <span></span>
                <span></span>
            </button>
        </div>

        <div class="mobile-nav-overlay d-lg-none" id="mobileNavOverlay">
            <div class="mobile-nav-inner">
                <ul class="mobile-nav-links">
                    <li><a href="home.php" class="mobile-link">Home</a></li>
                    <li><a href="shop.php" class="mobile-link">Shop</a></li>
                    <li><a href="lookbook.php" class="mobile-link">Gallery</a></li>
                    <li><a href="about.php" class="mobile-link">About</a></li>
                    <li><a href="contact.php" class="mobile-link">Contact</a></li>

                    <?php if (!isset($_SESSION['userID'])): ?>
                        <li style="margin-top: 40px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 40px;">
                            <a href="auth.php" class="mobile-link" style="font-size: 1.5rem; color: var(--color-gold-metallic, #d4af37);">Login / Register</a>
                        </li>
                    <?php else: ?>
                        <li style="margin-top: 40px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 40px;">
                            <a href="dashboard.php" class="mobile-link" style="font-size: 1.5rem; color: var(--color-gold-metallic, #d4af37);">Dashboard</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </header>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            "use strict";

            // 1. MASTER WISHLIST BADGE UPDATER
            window.updateGlobalWishBadge = function(count, doBounce = true) {
                const wishIcons = document.querySelectorAll('.ai-wish');

                wishIcons.forEach(iconLink => {
                    let badge = iconLink.querySelector('.vv-header-wish-badge');

                    if (count > 0) {
                        if (!badge) {
                            badge = document.createElement('span');
                            badge.className = 'vv-header-wish-badge';
                            badge.style.cssText = 'position:absolute; top:-8px; right:-10px; background:#fff; color:#000; font-family: "Montserrat", sans-serif; font-size:0.6rem; font-weight:700; width:18px; height:18px; display:flex; align-items:center; justify-content:center; border-radius:50%; border:2px solid #080808; z-index:10; transition: transform 0.3s;';
                            iconLink.appendChild(badge);
                        }
                        badge.innerText = count;

                        if (doBounce && typeof gsap !== 'undefined') {
                            gsap.fromTo(badge, {
                                scale: 0.2
                            }, {
                                scale: 1,
                                duration: 0.4,
                                ease: "back.out(3)"
                            });
                        }
                    } else {
                        if (badge) badge.remove();
                    }
                });
            };

            // 2. MASTER CART BADGE UPDATER
            window.updateGlobalCartBadge = function(count, doBounce = true) {
                const cartIcons = document.querySelectorAll('.ai-cart');

                cartIcons.forEach(iconLink => {
                    let badge = iconLink.querySelector('.vv-header-cart-badge');

                    if (count > 0) {
                        if (!badge) {
                            badge = document.createElement('span');
                            badge.className = 'vv-header-cart-badge';
                            badge.style.cssText = 'position:absolute; top:-8px; right:-10px; background:var(--color-gold-metallic, #d4af37); color:#000; font-family: "Montserrat", sans-serif; font-size:0.6rem; font-weight:700; width:18px; height:18px; display:flex; align-items:center; justify-content:center; border-radius:50%; border:2px solid #080808; z-index:10; transition: transform 0.3s;';
                            iconLink.appendChild(badge);
                        }
                        badge.innerText = count;

                        if (doBounce && typeof gsap !== 'undefined') {
                            gsap.fromTo(badge, {
                                scale: 0.2
                            }, {
                                scale: 1,
                                duration: 0.4,
                                ease: "back.out(3)"
                            });
                        }
                    } else {
                        if (badge) badge.remove();
                    }
                });
            };

            // 3. MASTER WISHLIST CLICK EVENT
            window.toggleWish = function(btnElement, productID) {
                const icon = btnElement.querySelector('i');
                const isCurrentlyAdded = icon.classList.contains('fa-solid');

                let currentBadge = document.querySelector('.vv-header-wish-badge');
                let currentCount = currentBadge ? parseInt(currentBadge.innerText) : 0;

                if (isCurrentlyAdded) {
                    icon.classList.replace('fa-solid', 'fa-regular');
                    btnElement.classList.remove('active');
                    if (btnElement.classList.contains('pd-wishlist-btn')) {
                        btnElement.style.color = "#fff";
                        btnElement.style.borderColor = "#444";
                    }
                    currentCount = Math.max(0, currentCount - 1);
                } else {
                    icon.classList.replace('fa-regular', 'fa-solid');
                    btnElement.classList.add('active');
                    if (btnElement.classList.contains('pd-wishlist-btn')) {
                        btnElement.style.color = "var(--color-gold-metallic, #d4af37)";
                        btnElement.style.borderColor = "var(--color-gold-metallic, #d4af37)";
                    }
                    currentCount += 1;
                    if (typeof gsap !== 'undefined') {
                        gsap.fromTo(icon, {
                            scale: 0.5
                        }, {
                            scale: 1.3,
                            duration: 0.4,
                            ease: "back.out(3)"
                        });
                    }
                }

                window.updateGlobalWishBadge(currentCount, true);

                let formData = new FormData();
                formData.append('id', productID);

                fetch('../Actions/wishlist_action.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            localStorage.setItem('vv_wishlist_sync', Date.now() + '|' + data.count);
                            if (data.count !== currentCount) {
                                window.updateGlobalWishBadge(data.count, false);
                            }
                        }
                    })
                    .catch(err => {
                        console.error('Wishlist sync failed:', err);
                        alert("Network error. Could not update wishlist.");
                        window.location.reload();
                    });
            };

            window.addEventListener('storage', function(e) {
                if (e.key === 'vv_wishlist_sync') {
                    const count = parseInt(e.newValue.split('|')[1]);
                    window.updateGlobalWishBadge(count, true);
                }
                if (e.key === 'vv_cart_sync') {
                    const count = parseInt(e.newValue.split('|')[1]);
                    window.updateGlobalCartBadge(count, true);
                }
            });

            // ==========================================
            // 4. RESPONSIVE MOBILE MENU (CINEMATIC)
            // ==========================================
            const mobBtn = document.getElementById('mobileMenuBtn');
            const mobOverlay = document.getElementById('mobileNavOverlay');
            const mobLinks = document.querySelectorAll('.mobile-link');
            let isMobMenuOpen = false;

            if (mobBtn && mobOverlay) {
                mobBtn.addEventListener('click', function() {
                    isMobMenuOpen = !isMobMenuOpen;

                    mobBtn.classList.toggle('active');

                    if (isMobMenuOpen) {
                        mobOverlay.classList.add('active');
                        document.body.style.overflow = 'hidden'; // Lock background scrolling

                        if (typeof gsap !== "undefined") {
                            gsap.to(mobLinks, {
                                y: 0,
                                opacity: 1,
                                duration: 0.8,
                                stagger: 0.1,
                                ease: "power4.out",
                                delay: 0.2
                            });
                        } else {
                            mobLinks.forEach(link => {
                                link.style.transform = "translateY(0)";
                                link.style.opacity = "1";
                            });
                        }
                    } else {
                        // Close Menu
                        mobOverlay.classList.remove('active');
                        document.body.style.overflow = ''; // Unlock scrolling

                        // GSAP Hide text
                        if (typeof gsap !== "undefined") {
                            gsap.to(mobLinks, {
                                y: "100%",
                                opacity: 0,
                                duration: 0.4,
                                ease: "power2.in"
                            });
                        } else {
                            mobLinks.forEach(link => {
                                link.style.transform = "translateY(100%)";
                                link.style.opacity = "0";
                            });
                        }
                    }
                });
            }
        });
    </script>