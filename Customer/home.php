<?php
require_once '../Config/db.php';

// 1. Fetch 5 Products for the Horizontal Gallery Lookbook (Fixed from 4 to 5)
$hgStmt = $pdo->query("
    SELECT p.productName, p.material, p.slug,
    (SELECT filePath FROM productimage pi WHERE pi.productID = p.productID AND pi.isPrimary = 1 LIMIT 1) as img
    FROM product p
    WHERE p.isActive = 1
    ORDER BY p.viewsCount DESC, p.createdAt DESC
    LIMIT 5
");
$hgProducts = $hgStmt->fetchAll(PDO::FETCH_ASSOC);

// Ensure we have exactly 5 items for the hardcoded layout positions
$hgData = [];
for ($i = 0; $i < 5; $i++) {
    $hgData[] = isset($hgProducts[$i]) ? $hgProducts[$i] : [
        'productName' => 'Velvet Vogue Archive',
        'material' => 'Premium Blend',
        'slug' => '',
        'img' => null
    ];
}

// 2. Fetch 6 Featured/Latest Products for the Fan Cards
$fanStmt = $pdo->query("
    SELECT p.productName, p.slug, p.basePrice, p.salePrice,
    (SELECT filePath FROM productimage pi WHERE pi.productID = p.productID AND pi.isPrimary = 1 LIMIT 1) as img
    FROM product p
    WHERE p.isActive = 1
    ORDER BY p.isFeatured DESC, p.createdAt DESC
    LIMIT 6
");
$fanProducts = $fanStmt->fetchAll(PDO::FETCH_ASSOC);

// Ensure we have exactly 6 items for the Fan JS logic
$fanData = [];
for ($i = 0; $i < 6; $i++) {
    $fanData[] = isset($fanProducts[$i]) ? $fanProducts[$i] : [
        'productName' => 'Exclusive Collection',
        'basePrice' => 0,
        'salePrice' => null,
        'slug' => '',
        'img' => null
    ];
}

// Identify the initial center card (Index 2 based on your JS fanState)
$centerCard = $fanData[2];
$centerPrice = $centerCard['salePrice'] ? $centerCard['salePrice'] : $centerCard['basePrice'];


$page_css = "home.css";
$page_js = "home.js";

// Include the Master Header
include '../ReuseableUI/header.php';
?>

<main>
    <script>
        (function() {
            try {
                var nav = performance.getEntriesByType("navigation")[0];
                var isReload = nav && nav.type === "reload";
                var hasVisited = sessionStorage.getItem('vv_preloader_shown');
                if (hasVisited && !isReload) {
                    document.documentElement.classList.add('skip-pl');
                } else {
                    sessionStorage.setItem('vv_preloader_shown', 'true');
                }
            } catch (e) {}
        })();
    </script>

    <div class="vv-preloader" id="vvPreloader">
        <div class="pl-curtain pl-curtain-top" id="pl-curtain-top"></div>
        <div class="pl-curtain pl-curtain-bottom" id="pl-curtain-bottom"></div>

        <div class="pl-content">
            <div class="pl-logo-wrap text-mask">
                <h1 class="pl-logo-text" id="pl-logo-text">Velvet Vogue</h1>
            </div>
            <div class="pl-telemetry">
                <span>Express Your True Identity</span>
                <span id="pl-counter">000</span>
            </div>
        </div>
    </div>

    <section class="hero-section">
        <video class="hero-video" autoplay muted loop playsinline preload="auto">
            <source src="../Assets/video/heroBackground.webm" type="video/webm">
            <source src="../Assets/video/heroBackground.mp4" type="video/mp4">
        </video>
        <div class="hero-overlay"></div>
        <div id="particles-container"></div>

        <div class="hero-glass-card">
            <div class="text-mask">
                <h1 class="hero-title-line gsap-hero-title">Velvet Vogue</h1>
            </div>
            <div class="text-mask" style="margin-top: 10px;">
                <p class="hero-subtitle gsap-hero-sub">Express Your True Identity</p>
            </div>
            <div class="text-mask">
                <div class="gsap-hero-sub" style="margin-top: 15px;">
                    <a href="shop.php" class="btn-vogue-mask">
                        <span class="btn-text">Explore Collection</span>
                        <span class="btn-mask" aria-hidden="true">Explore Collection</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="scroll-indicator">
            <div class="mouse-icon">
                <div class="wheel"></div>
            </div>
            <span>Scroll</span>
        </div>
    </section>

    <section class="horizontal-gallery-wrapper">
        <div class="gallery-track">

            <div class="g-item horizontal-entrance" style="left: 10vw; top: 15vh; width: 25vw; height: 70vh;">
                <div class="g-visual-container">
                    <div class="g-img-wrap"><img loading="lazy" decoding="async" src="<?= vv_e(vv_public_asset_url($hgData[0]['img'] ?? null)) ?>" alt="Look 1"></div>
                    <div class="g-spec-reveal">
                        <h3 class="spec-header"><?= htmlspecialchars($hgData[0]['productName']) ?></h3>
                        <div class="spec-item"><span class="spec-label">Material</span><span class="spec-value"><?= htmlspecialchars($hgData[0]['material'] ?: 'Premium Blend') ?></span></div>
                        <div class="spec-item"><span class="spec-label">Cut</span><span class="spec-value">Tailored Fit</span></div>
                        <a href="<?= $hgData[0]['slug'] ? 'product_detail.php?slug=' . rawurlencode((string) $hgData[0]['slug']) : 'shop.php' ?>" style="text-decoration: none;">
                            <div class="spec-arrow-container">
                                <div class="spec-arrow-line"></div><span class="spec-arrow-text">Examine</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <div class="snap-text" style="left: 42vw; top: 40vh;">
                <h3 class="quote-text">It doesn't matter <strong>where</strong><br>you start, it's <strong>how</strong> you<br>progress from there.</h3>
                <span class="quote-signature">John Finlo</span>
            </div>

            <div class="g-item horizontal-entrance" style="left: 85vw; top: 30vh; width: 30vw; height: 55vh;">
                <div class="g-visual-container">
                    <div class="g-img-wrap"><img loading="lazy" decoding="async" src="<?= vv_e(vv_public_asset_url($hgData[1]['img'] ?? null)) ?>" alt="Look 2"></div>
                    <div class="g-spec-reveal">
                        <h3 class="spec-header"><?= htmlspecialchars($hgData[1]['productName']) ?></h3>
                        <div class="spec-item"><span class="spec-label">Fabric</span><span class="spec-value"><?= htmlspecialchars($hgData[1]['material'] ?: 'Matte Silk') ?></span></div>
                        <div class="spec-item"><span class="spec-label">Details</span><span class="spec-value">Premium Hardware</span></div>
                        <a href="<?= $hgData[1]['slug'] ? 'product_detail.php?slug=' . rawurlencode((string) $hgData[1]['slug']) : 'shop.php' ?>" style="text-decoration: none;">
                            <div class="spec-arrow-container">
                                <div class="spec-arrow-line"></div><span class="spec-arrow-text">Examine</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <div class="g-item horizontal-entrance" style="left: 105vw; top: 55vh; width: 15vw; height: 15vw; z-index: 4;">
                <div class="g-visual-container">
                    <div class="g-img-wrap"><img loading="lazy" decoding="async" src="<?= vv_e(vv_public_asset_url($hgData[2]['img'] ?? null)) ?>" alt="Look Detail"></div>
                    <div class="g-spec-reveal">
                        <h3 class="spec-header"><?= htmlspecialchars($hgData[2]['productName']) ?></h3>
                        <div class="spec-item"><span class="spec-label">Fabric</span><span class="spec-value"><?= htmlspecialchars($hgData[2]['material'] ?: 'Raw Silk') ?></span></div>
                    </div>
                </div>
            </div>

            <div class="snap-text" style="left: 125vw; top: 20vh;">
                <h3 class="quote-text">Flawless tailoring meets<br>unparalleled materials.<br><strong>Own the room.</strong></h3>
                <span class="quote-signature">John Finlo</span>
            </div>

            <div class="g-item horizontal-entrance" style="left: 170vw; top: 10vh; width: 25vw; height: 70vh;">
                <div class="g-visual-container">
                    <div class="g-img-wrap"><img loading="lazy" decoding="async" src="<?= vv_e(vv_public_asset_url($hgData[3]['img'] ?? null)) ?>" alt="Look 3"></div>
                    <div class="g-spec-reveal">
                        <h3 class="spec-header"><?= htmlspecialchars($hgData[3]['productName']) ?></h3>
                        <div class="spec-item"><span class="spec-label">Base</span><span class="spec-value"><?= htmlspecialchars($hgData[3]['material'] ?: 'Heavy Cotton') ?></span></div>
                        <div class="spec-item"><span class="spec-label">Stitching</span><span class="spec-value">Hand-Sewn</span></div>
                        <a href="<?= $hgData[3]['slug'] ? 'product_detail.php?slug=' . rawurlencode((string) $hgData[3]['slug']) : 'shop.php' ?>" style="text-decoration: none;">
                            <div class="spec-arrow-container">
                                <div class="spec-arrow-line"></div><span class="spec-arrow-text">Examine</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <div class="snap-text" style="left: 205vw; top: 48vh;">
                <h3 class="quote-text">Uncompromising design for<br>those who <strong>lead</strong>. Every stitch<br>is pure <strong>intention</strong>.</h3>
                <span class="quote-signature">John Finlo</span>
            </div>

            <div class="g-item horizontal-entrance" style="left: 250vw; top: 20vh; width: 40vw; height: 60vh;">
                <div class="g-visual-container">
                    <div class="g-img-wrap"><img loading="lazy" decoding="async" src="<?= vv_e(vv_public_asset_url($hgData[4]['img'] ?? null)) ?>" alt="Look Finale"></div>
                    <div class="g-spec-reveal">
                        <h3 class="spec-header"><?= htmlspecialchars($hgData[4]['productName']) ?></h3>
                        <div class="spec-item"><span class="spec-label">Design</span><span class="spec-value"><?= htmlspecialchars($hgData[4]['material'] ?: 'Unapologetic') ?></span></div>
                        <a href="<?= $hgData[4]['slug'] ? 'product_detail.php?slug=' . rawurlencode((string) $hgData[4]['slug']) : 'shop.php' ?>" style="text-decoration: none;">
                            <div class="spec-arrow-container">
                                <div class="spec-arrow-line"></div><span class="spec-arrow-text">Enter Shop</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <section class="featured-section">
        <div class="section-header text-mask">
            <div class="gsap-reveal" style="display: flex; justify-content: space-between; align-items: flex-end; width: 100%;">
                <div>
                    <p>Director's Choice</p>
                    <h2>Featured Lookbook</h2>
                </div>
                <a href="shop.php">View Entire Archive <i class="fa-solid fa-arrow-right" style="margin-left: 8px;"></i></a>
            </div>
        </div>

        <div class="fan-container">
            <?php foreach ($fanData as $fc):
                $fcPrice = $fc['salePrice'] ? $fc['salePrice'] : $fc['basePrice'];
                $fcLink = $fc['slug'] ? "product_detail.php?slug=" . rawurlencode((string) $fc['slug']) : "shop.php";
            ?>
                <div class="fan-card" data-title="<?= htmlspecialchars($fc['productName']) ?>" data-price="Rs. <?= number_format($fcPrice, 2) ?>" data-link="<?= vv_e($fcLink) ?>">
                    <img loading="lazy" decoding="async" src="<?= vv_e(vv_public_asset_url($fc['img'] ?? null)) ?>" alt="Product">
                </div>
            <?php endforeach; ?>
        </div>

        <div class="fan-pedestal">
            <div class="pedestal-content">
                <h3 id="pedestal-title"><?= htmlspecialchars($centerCard['productName']) ?></h3>
                <div class="pedestal-bottom">
                    <span id="pedestal-price">Rs. <?= number_format($centerPrice, 2) ?></span>
                    <a href="<?= $centerCard['slug'] ? 'product_detail.php?slug=' . rawurlencode((string) $centerCard['slug']) : 'shop.php' ?>" id="pedestal-btn" class="btn-tech">Examine Piece <i class="fa-solid fa-expand"></i></a>
                </div>
            </div>
        </div>
    </section>

    <section class="anthem-section">
        <div class="anthem-bg gsap-parallax-anthem"></div>
        <div class="anthem-overlay"></div>
        <div class="anthem-content">
            <div class="text-mask">
                <h2 class="gsap-reveal-anthem">Unapologetically <br><span class="gold-text">Bold.</span></h2>
            </div>
            <div class="text-mask">
                <p class="gsap-reveal-anthem">We don't just design clothes. We engineer confidence. Founded by John Finlo, Velvet Vogue is the intersection of high-end formal wear and premium casual streetwear. Step into the new era of style.</p>
            </div>
            <div class="text-mask" style="margin-top: 20px;">
                <div class="gsap-reveal-anthem"><a href="about.php" class="btn-premium btn-sec">Discover The Atelier</a></div>
            </div>
        </div>
    </section>

</main>

<?php include '../ReuseableUI/footer.php'; ?>