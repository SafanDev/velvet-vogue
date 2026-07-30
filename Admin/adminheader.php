<?php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();

$current_page = basename($_SERVER['PHP_SELF']);
$nav_operations = in_array($current_page, ['dashboard.php', 'index.php', 'reports.php', 'settings.php'], true) ? 'active' : '';
$nav_catalog = in_array($current_page, ['products.php', 'product-add.php', 'product-edit.php', 'categories.php'], true) ? 'active' : '';
$nav_commerce = in_array($current_page, ['orders.php', 'order-view.php', 'coupons.php'], true) ? 'active' : '';
$nav_audience = in_array($current_page, ['users.php', 'inquiries.php', 'reviews.php', 'profile.php'], true) ? 'active' : '';

try {
    $badgeQuery = $pdo->query("SELECT COUNT(*) FROM inquiry WHERE inquiryStatus = 'open'");
    $globalPendingInquiries = $badgeQuery ? (int) $badgeQuery->fetchColumn() : 0;
} catch (Throwable $exception) {
    error_log('Unable to load inquiry badge: ' . $exception->getMessage());
    $globalPendingInquiries = 0;
}

$userImageUrl = $_SESSION['profileImage'] ?? null;
?>

<style>
/* ==========================================================================
   VELVET VOGUE - GLOBAL ADMIN HEADER STYLESHEET
   ========================================================================== */

:root {
    --color-gold-metallic: #D4AF37;
    --color-danger-muted: #c0392b;
    --color-bg-dark: #000000;
    --border-subtle: rgba(255, 255, 255, 0.15);
}

.admin-header { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; background: #040404; position: sticky; top: 0; z-index: 1040; padding: 15px 4%; border-bottom: 1px solid var(--border-subtle) !important; overflow: visible !important; }
.header-left { display: flex; justify-content: flex-start; }
.header-center { display: flex; justify-content: center; }
.header-right { display: flex; justify-content: flex-end; }

/* ==========================================
   THE 3D 'WEARING THE CROWN' MONOGRAM LOGO
   ========================================== */
.admin-brand-logo {
    display: flex;
    align-items: baseline;
    text-decoration: none;
    outline: none;
    padding-top: 10px;
    perspective: 800px; /* Enables true 3D space for the tilt effect */
}

/* The V Container */
.v-emblem {
    position: relative;
    display: inline-block;
    line-height: 1;
    transform-style: preserve-3d; /* Allows children to pop out in 3D */
    transition: transform 0.5s cubic-bezier(0.34, 1.5, 0.64, 1);
}

/* The Letter V */
.v-letter {
    font-family: 'Playfair Display', serif;
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(180deg, #f3e5ab 0%, #d4af37 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    position: relative;
    z-index: 1;
}

/* The Crown - Precisely resting ON the left edge of the V */
.v-crown {
    position: absolute;
    top: -1px;      /* Moved down so it physically overlaps the font */
    left: -7px;     /* Hooked onto the left serif of the V */
    font-size: 1.15rem;
    color: var(--color-gold-metallic);
    -webkit-text-fill-color: var(--color-gold-metallic);
    transform: rotate(-24deg) translateZ(5px);
    /* Casts a harsh, tiny shadow ON the letter V to prove it's resting there */
    filter: drop-shadow(2px 3px 2px rgba(0,0,0,0.9));
    transition: all 0.5s cubic-bezier(0.34, 1.5, 0.64, 1);
    z-index: 2;
}

/* The Rest of the Word */
.logo-text-rest {
    font-family: 'Playfair Display', serif;
    font-size: 1.4rem;
    font-weight: 700;
    letter-spacing: 3px;
    text-transform: uppercase;
    background: linear-gradient(110deg, #e0e0e0 0%, #ffffff 40%, #d4af37 50%, #ffffff 60%, #e0e0e0 100%);
    background-size: 200% auto;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: logoShine 5s linear infinite;
    transition: letter-spacing 0.5s cubic-bezier(0.25, 1, 0.5, 1);
    margin-left: 1px;
}

/* THE 3D TILT BACK HOVER EFFECT */
.admin-brand-logo:hover .v-emblem {
    /* Tilts backward in 3D space and slightly rotates right */
    transform: rotateX(15deg) rotateY(-15deg) scale(1.05);
}

.admin-brand-logo:hover .v-crown {
    /* Straightens out, pops out towards the user (translateZ), and glows */
    transform: rotate(0deg) translateY(-8px) translateX(4px) translateZ(25px);
    filter: drop-shadow(0 15px 20px rgba(212, 175, 55, 0.8));
}

.admin-brand-logo:hover .logo-text-rest {
    letter-spacing: 6px;
    animation: logoShine 3s linear infinite;
}

@keyframes logoShine {
    0% { background-position: 200% 0; }
    50% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}


/* ==========================================
   3D NAV PILL CORE
   ========================================== */
.nav-wrapper { position: relative; }
.nav-outer { position: relative; display: inline-flex; background: #080808; border-radius: 100px; padding: 5px; border: 2.5px solid rgba(255, 255, 255, 0.13); box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.85), 0 22px 64px rgba(0, 0, 0, 0.92), 0 8px 24px rgba(0, 0, 0, 0.7), 0 3px 8px rgba(0, 0, 0, 0.55), inset 0 1px 0 rgba(255, 255, 255, 0.1), inset 0 -1px 0 rgba(0, 0, 0, 0.65); transform-style: preserve-3d; will-change: transform; --mx: 50%; --my: 30%; }
.nav-outer::before { content: ""; position: absolute; inset: 0; border-radius: 100px; background: radial-gradient(ellipse 60% 55% at var(--mx) var(--my), rgba(255, 255, 255, 0.1) 0%, transparent 68%); pointer-events: none; z-index: 20; }
.nav-inner { position: relative; display: flex; align-items: stretch; padding: 0 3px; border-radius: 90px; box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.07), inset 0 -1px 0 rgba(0, 0, 0, 0.35); }

.hover-pill { position: absolute; top: 0; background: linear-gradient(175deg, #545454 0%, #3f3f3f 100%); border-radius: 90px; border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.13), inset 0 -1px 0 rgba(0, 0, 0, 0.28), 0 3px 10px rgba(0, 0, 0, 0.4); pointer-events: none; z-index: 0; will-change: left, width; transition: left 0.42s cubic-bezier(0.34, 1.18, 0.64, 1), width 0.42s cubic-bezier(0.34, 1.18, 0.64, 1); }
.active-glow { position: absolute; background: rgba(255, 255, 255, 0.18); border-radius: 90px; filter: blur(12px); pointer-events: none; z-index: 1; animation: glowPulse 3.5s ease-in-out infinite; transition: left 0.42s cubic-bezier(0.34, 1.18, 0.64, 1), width 0.42s cubic-bezier(0.34, 1.18, 0.64, 1); }
.active-pill { position: absolute; top: 0; background: linear-gradient(180deg, #ffffff 0%, #ffffff 100%); border-radius: 90px; box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.5), inset 0 -1px 0 rgba(0, 0, 0, 0.2), 0 2px 6px rgba(0, 0, 0, 0.32), 0 8px 22px rgba(212, 175, 55, 0.3); pointer-events: none; z-index: 2; transition: left 0.42s cubic-bezier(0.34, 1.18, 0.64, 1), width 0.42s cubic-bezier(0.34, 1.18, 0.64, 1); }

.nav-item { position: relative; z-index: 3; background: transparent; border: none; outline: none; cursor: pointer; border-radius: 90px; padding: 0; display: flex; align-items: center; text-decoration: none; margin: 0 !important;}
.item-inner { display: flex; align-items: center; gap: 8px; padding: 12px 24px; color: rgba(255, 255, 255, 0.6); font-size: 14px; font-weight: 600; font-family: var(--font-body); letter-spacing: 0.5px; white-space: nowrap; transition: color 0.2s ease; pointer-events: none; }
.nav-item:hover:not(.active) .item-inner { color: rgba(255, 255, 255, 0.95); }
.nav-item.active .item-inner, .nav-item.active .nav-icon { color: #111111 !important; }
.nav-icon { font-size: 15px; flex-shrink: 0; transition: transform 0.2s cubic-bezier(0.34, 1.4, 0.64, 1); }
.nav-item:hover:not(.active) .nav-icon { transform: scale(1.18); }

.badge { position: absolute; top: 6px; right: 12px; width: 7px; height: 7px; background: var(--color-danger-muted); border-radius: 50%; border: 1.5px solid #2e2e2e; z-index: 6; box-shadow: 0 0 8px rgba(192, 57, 43, 0.8); animation: badgePop 2.4s ease-in-out infinite; }
@keyframes badgePop { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.3); } }

/* ==========================================
   THE GLASS DROPDOWNS
   ========================================== */
.glass-dropdown {
    position: absolute;
    top: calc(100% + 15px);
    left: 50%;
    transform: translateX(-50%) translateY(-10px) scaleY(0.95);
    background: rgba(8, 8, 8, 0.95);
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.9), inset 0 1px 0 rgba(255,255,255,0.05);
    padding: 8px;
    min-width: 220px;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    transform-origin: top center;
    z-index: 9999;
}

.nav-item::after { content: ''; position: absolute; top: 100%; left: 0; right: 0; height: 25px; background: transparent; z-index: 9998; }
.nav-item:hover .glass-dropdown { opacity: 1; visibility: visible; pointer-events: auto; transform: translateX(-50%) translateY(0) scaleY(1); }

.drop-item { color: #aaa; font-family: var(--font-body); font-size: 0.8rem; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; padding: 12px 18px; border-radius: 4px; transition: all 0.3s ease; display: flex; align-items: center; gap: 12px; text-decoration: none; }
.drop-item i { font-size: 0.9rem; color: #555; transition: 0.3s; width: 18px; text-align: center; }
.drop-item:hover { background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; }
.drop-item:hover i { color: var(--color-gold-metallic); transform: scale(1.1); }
.drop-item.current { background: rgba(212,175,55,0.1); color: var(--color-gold-metallic); }
.drop-item.current i { color: var(--color-gold-metallic); }

/* ==========================================
   USER CAPSULE & PROFILE LINK
   ========================================== */
.user-capsule { display: flex; align-items: center; background: rgba(255,255,255,0.03); border: 1px solid var(--border-subtle); padding: 4px; border-radius: 50px; transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); width: 48px; position: relative; overflow: hidden; }
.user-capsule:hover { width: 195px; border-color: var(--color-gold-metallic); background: #050505; padding-right: 42px; box-shadow: 0 0 15px rgba(212,175,55,0.1); }

.uc-profile-link { display: flex; align-items: center; gap: 10px; text-decoration: none !important; color: inherit; flex-grow: 1; outline: none; }

.uc-avatar { width: 38px; height: 38px; border-radius: 50%; background: #111; border: 1px solid var(--color-gold-metallic); display: flex; align-items: center; justify-content: center; color: var(--color-gold-metallic); font-size: 1.1rem; flex-shrink: 0; overflow: hidden; }
.uc-avatar img { width: 100%; height: 100%; object-fit: cover; }

.uc-details { white-space: nowrap; opacity: 0; transition: opacity 0.3s; margin-left: 2px; transform: translateX(-10px); display: block; }
.user-capsule:hover .uc-details { opacity: 1; transition-delay: 0.1s; transform: translateX(0); }
.uc-name { font-size: 0.8rem; font-weight: 700; color: #fff; letter-spacing: 2px; font-family: var(--font-body); display: block; }

.uc-logout { background: rgba(192, 57, 43, 0.1); color: var(--color-danger-muted); border: 1px solid var(--color-danger-muted); width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; position: absolute; right: 5px; opacity: 0; transform: scale(0.5) rotate(-45deg); transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); text-decoration: none; z-index: 5; }
.user-capsule:hover .uc-logout { opacity: 1; transform: scale(1) rotate(0deg); }
.uc-logout:hover { background: var(--color-danger-muted); color: #fff; box-shadow: 0 0 15px rgba(192, 57, 43, 0.6); text-decoration: none; }
</style>

<div class="cinematic-grain"></div>

<script src="<?= vv_e(vv_versioned_asset('../Assets/js/security.js')) ?>"></script>
<header class="main-header admin-header" data-csrf-token="<?= vv_e(vv_csrf_token()) ?>" data-app-base-url="<?= vv_e(vv_app_url()) ?>">

    <div class="header-left">
        <a href="dashboard.php" class="admin-brand-logo">
            <span class="v-emblem">
                <i class="fa-solid fa-crown v-crown"></i>
                <span class="v-letter">V</span>
            </span>
            <span class="logo-text-rest">ELVET VOGUE</span>
        </a>
    </div>

    <div class="header-center d-none d-xl-flex">
        <div class="nav-wrapper">
            <div class="nav-outer" id="navOuter">
                <div class="nav-inner" id="navInner">
                    <div class="hover-pill" id="hoverPill"></div>
                    <div class="active-glow" id="activeGlow"></div>
                    <div class="active-pill" id="activePill"></div>

                    <div class="nav-item <?= $nav_operations ?>">
                        <span class="item-inner" style="animation-delay:0.10s">
                            <i class="fa-solid fa-server nav-icon"></i>
                            <span>Operations</span>
                        </span>
                        <div class="glass-dropdown">
                            <a href="dashboard.php" class="drop-item <?= in_array($current_page, ['dashboard.php', 'index.php']) ? 'current' : '' ?>">
                                <i class="fa-solid fa-chart-line"></i> Terminal
                            </a>
                            <a href="reports.php" class="drop-item <?= $current_page == 'reports.php' ? 'current' : '' ?>">
                                <i class="fa-solid fa-file-invoice-dollar"></i> Reports
                            </a>
                            <a href="settings.php" class="drop-item <?= $current_page == 'settings.php' ? 'current' : '' ?>">
                                <i class="fa-solid fa-sliders"></i> Settings
                            </a>
                        </div>
                    </div>

                    <div class="nav-item <?= $nav_catalog ?>">
                        <span class="item-inner" style="animation-delay:0.18s">
                            <i class="fa-solid fa-gem nav-icon"></i>
                            <span>Catalog</span>
                        </span>
                        <div class="glass-dropdown">
                            <a href="products.php" class="drop-item <?= in_array($current_page, ['products.php', 'product-add.php', 'product-edit.php']) ? 'current' : '' ?>">
                                <i class="fa-solid fa-book-open"></i> Inventory
                            </a>
                            <a href="categories.php" class="drop-item <?= $current_page == 'categories.php' ? 'current' : '' ?>">
                                <i class="fa-solid fa-layer-group"></i> Categories
                            </a>
                        </div>
                    </div>

                    <div class="nav-item <?= $nav_commerce ?>">
                        <span class="item-inner" style="animation-delay:0.26s">
                            <i class="fa-solid fa-barcode nav-icon"></i>
                            <span>Commerce</span>
                        </span>
                        <div class="glass-dropdown">
                            <a href="orders.php" class="drop-item <?= in_array($current_page, ['orders.php', 'order-view.php']) ? 'current' : '' ?>">
                                <i class="fa-solid fa-receipt"></i> Order Ledger
                            </a>
                            <a href="coupons.php" class="drop-item <?= $current_page == 'coupons.php' ? 'current' : '' ?>">
                                <i class="fa-solid fa-ticket"></i> Campaigns
                            </a>
                        </div>
                    </div>

                    <div class="nav-item <?= $nav_audience ?>">
                        <?php if($globalPendingInquiries > 0): ?><div class="badge"></div><?php endif; ?>
                        <span class="item-inner" style="animation-delay:0.34s">
                            <i class="fa-solid fa-users nav-icon"></i>
                            <span>Audience</span>
                        </span>
                        <div class="glass-dropdown">
                            <a href="users.php" class="drop-item <?= $current_page == 'users.php' ? 'current' : '' ?>">
                                <i class="fa-solid fa-user-shield"></i> Client Dossier
                            </a>
                            <a href="inquiries.php" class="drop-item <?= $current_page == 'inquiries.php' ? 'current' : '' ?>">
                                <i class="fa-solid fa-envelope-open-text"></i> Support Desk
                                <?php if($globalPendingInquiries > 0): ?><span class="ms-auto text-danger fw-bold" style="font-size: 0.8rem;"><?= $globalPendingInquiries ?></span><?php endif; ?>
                            </a>
                            <a href="reviews.php" class="drop-item <?= $current_page == 'reviews.php' ? 'current' : '' ?>">
                                <i class="fa-solid fa-star-half-stroke"></i> Moderation
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="header-right">
        <div class="user-capsule">
            <a href="profile.php" class="uc-profile-link">
                <div class="uc-avatar">
                    <?php if($userImageUrl): ?>
                        <img decoding="async" src="<?= vv_e(vv_admin_public_url($userImageUrl)) ?>" alt="Admin">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="uc-details">
                    <span class="uc-name"><?= strtoupper(htmlspecialchars($_SESSION['firstName'] ?? 'ADMIN')) ?></span>
                </div>
            </a>
            <form method="post" action="logout.php">
                <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">
                <button type="submit" class="uc-logout" title="Secure Logout" aria-label="Log out">
                    <i class="fa-solid fa-power-off"></i>
                </button>
            </form>
        </div>
    </div>
</header>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const navInner = document.getElementById('navInner');
        const activeItem = document.querySelector('.nav-item.active');
        const activePill = document.getElementById('activePill');
        const activeGlow = document.getElementById('activeGlow');
        const hoverPill = document.getElementById('hoverPill');

        function setPillPosition(item, targetPill) {
            if (!item || !targetPill) return;
            targetPill.style.width = item.offsetWidth + 'px';
            targetPill.style.left = item.offsetLeft + 'px';
        }

        if (activeItem) {
            setPillPosition(activeItem, activePill);
            setPillPosition(activeItem, activeGlow);
            setPillPosition(activeItem, hoverPill);
        }

        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('mouseenter', function() {
                setPillPosition(this, hoverPill);
                if(hoverPill) hoverPill.style.opacity = '1';
            });
        });

        if(navInner) {
            navInner.addEventListener('mouseleave', function() {
                if(activeItem && hoverPill) {
                    setPillPosition(activeItem, hoverPill);
                } else if(hoverPill) {
                    hoverPill.style.opacity = '0';
                }
            });
        }
    });
</script>