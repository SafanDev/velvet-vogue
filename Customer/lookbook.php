<?php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once '../Config/db.php';

$page_css = 'lookbook.css';
$page_js = 'lookbook.js';

// =======================================================
// DYNAMIC DATA ENGINE WITH FALLBACK
// =======================================================

// 1. Define High-End Fallback Data (In case DB is empty)
$mockLooks = [
    ['title' => 'Midnight Silk', 'desc' => 'Fluidity meets structure in our signature outerwear.', 'price' => 24000, 'img' => 'https://images.unsplash.com/photo-1539109136881-3be0616acf4b?q=80&w=1974&auto=format&fit=crop', 'slug' => 'midnight-silk'],
    ['title' => 'The Monolith', 'desc' => 'Unapologetic tailoring for the modern executive.', 'price' => 35000, 'img' => 'https://images.unsplash.com/photo-1617137968427-85924c800a22?q=80&w=1974&auto=format&fit=crop', 'slug' => 'the-monolith'],
    ['title' => 'Urban Vanguard', 'desc' => 'Street-level dominance encoded into every thread.', 'price' => 18500, 'img' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?q=80&w=2070&auto=format&fit=crop', 'slug' => 'urban-vanguard'],
    ['title' => 'Crimson Fall', 'desc' => 'Redefining evening wear with gravity-defying drapes.', 'price' => 42000, 'img' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?q=80&w=1962&auto=format&fit=crop', 'slug' => 'crimson-fall'],
    ['title' => 'Azure Dream', 'desc' => 'Breathe in the summer breeze with lightweight linen.', 'price' => 12000, 'img' => 'https://images.unsplash.com/photo-1495385794356-15371f348c31?q=80&w=1940&auto=format&fit=crop', 'slug' => 'azure-dream'],
    ['title' => 'Obsidian Edge', 'desc' => 'Premium leather treated for a flawless matte finish.', 'price' => 55000, 'img' => 'https://images.unsplash.com/photo-1550614000-4b95dd2458bf?q=80&w=1974&auto=format&fit=crop', 'slug' => 'obsidian-edge'],
];

$mockDetails = [
    'https://images.unsplash.com/photo-1485230895905-ef291bc31e67?q=80&w=1974&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1509631179647-0c500ab14c50?q=80&w=1988&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1445205170230-053b83016050?q=80&w=1975&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1571513722275-4b41940f54b8?q=80&w=1974&auto=format&fit=crop'
];

$dbLooks = [];
try {
    $stmt = $pdo->query("
        SELECT p.productName, p.description, p.basePrice, p.slug,
               (SELECT pi.filePath FROM productimage pi WHERE pi.productID = p.productID AND pi.isPrimary = 1 ORDER BY pi.sortOrder, pi.imageID LIMIT 1) AS filePath
        FROM product p
        WHERE p.isActive = 1
        ORDER BY p.isFeatured DESC, p.createdAt DESC
        LIMIT 100
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dbLooks[] = [
            'title' => $row['productName'],
            'desc' => substr(strip_tags((string) ($row['description'] ?? 'A Velvet Vogue exclusive.')), 0, 80) . '...',
            'price' => $row['basePrice'],
            'img' => !empty($row['filePath']) ? (string) $row['filePath'] : null,
            'slug' => $row['slug']
        ];
    }
} catch (PDOException $exception) {
    error_log('Lookbook product loading failed: ' . $exception->getMessage());
}

// 3. Horizontal Gallery (Strictly 6 Items to protect UX)
$finalLooks = [];
for ($i = 0; $i < 6; $i++) {
    if (isset($dbLooks[$i]) && $dbLooks[$i]['img'] != null) {
        $finalLooks[] = $dbLooks[$i];
    } else {
        $finalLooks[] = $mockLooks[$i];
    }
}

// 4. Masonry Details Grid (Dynamic - Shows ALL remaining items)
$finalDetails = [];
$totalDbLooks = count($dbLooks);

if ($totalDbLooks > 6) {
    // Use the remaining products in the masonry grid.
    for ($i = 6; $i < $totalDbLooks; $i++) {
        if ($dbLooks[$i]['img'] != null) {
            $finalDetails[] = $dbLooks[$i]['img'];
        }
    }
} else {
    // If the database is new/empty, fall back to the 4 mock details
    $finalDetails = $mockDetails;
}

// Layout styling patterns to cycle through so the 6 horizontal panels look unique
$layoutClasses = [
    ['text' => '', 'img' => '', 'hotspot' => 'top: 40%; left: 30%;'],                // Left Img, Right Text
    ['text' => 'alt-pos', 'img' => '', 'hotspot' => 'top: 30%; left: 60%;'],          // Left Text, Right Img
    ['text' => 'floating-text', 'img' => 'lb-wide-img', 'hotspot' => 'none'],         // Full Width Img, Floating Text
    ['text' => 'text-end', 'img' => '', 'hotspot' => 'top: 50%; left: 50%;'],         // Center Img, Right Text
    ['text' => 'alt-pos', 'img' => 'lb-wide-img', 'hotspot' => 'none'],               // Left Text, Wide Img
    ['text' => '', 'img' => '', 'hotspot' => 'top: 60%; left: 40%;'],                 // Left Img, Right Text
];


$lookbookImageUrl = static function (?string $path): string {
    $value = trim((string) $path);
    if (str_starts_with($value, 'https://images.unsplash.com/')) {
        return $value;
    }
    return vv_public_asset_url($value);
};

include '../ReuseableUI/header.php';
?>

<div class="lb-cursor"></div>

<main class="lookbook-main">

    <section class="lb-hero">
        <div class="lb-hero-bg">
            <img decoding="async" fetchpriority="high" src="https://images.unsplash.com/photo-1490481651871-ab68de25d43d?q=80&w=2070&auto=format&fit=crop" alt="Velvet Vogue Campaign">
            <div class="lb-hero-overlay"></div>
        </div>
        <div class="lb-hero-content container">
            <span class="lb-season font-monospace text-gold scroll-reveal-txt">WINTER 2026</span>
            <h1 class="lb-title scroll-reveal-txt">The<br>Editorial.</h1>
            <p class="lb-subtitle scroll-reveal-txt">A symphony of shadows and gold. Explore the new standard of modern elegance.</p>
            <div class="lb-scroll-indicator scroll-reveal-txt">

            </div>
        </div>
    </section>

    <section class="lb-statement container">
        <h2 class="statement-text">
            <span class="d-block text-mask"><span class="reveal-up">We don't follow trends.</span></span>
            <span class="d-block text-mask"><span class="reveal-up">We architect <i class="text-gold">identities</i>.</span></span>
        </h2>
    </section>

    <section class="lb-horizontal-wrap">
        <div class="lb-horizontal-container">

            <?php foreach ($finalLooks as $index => $look):
                $layout = $layoutClasses[$index % count($layoutClasses)];
            ?>
                <div class="lb-panel">
                    <div class="lb-panel-inner">

                        <?php if($layout['text'] !== 'alt-pos'): // Image comes first in DOM ?>
                            <div class="lb-image-wrapper <?= vv_e($layout['img']) ?>">
                                <img loading="lazy" decoding="async" src="<?= vv_e($lookbookImageUrl($look['img'])) ?>" class="lb-parallax-img" alt="<?= htmlspecialchars($look['title']) ?>">

                                <?php if($layout['hotspot'] !== 'none'): ?>
                                    <a href="product_detail.php?slug=<?= rawurlencode((string) $look['slug']) ?>" class="lb-hotspot" style="<?= vv_e($layout['hotspot']) ?>">
                                        <div class="hotspot-core"></div>
                                        <div class="hotspot-tooltip"><?= htmlspecialchars($look['title']) ?> - Rs. <?= number_format($look['price']) ?></div>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="lb-panel-text <?= vv_e($layout['text']) ?>">
                            <span class="lb-panel-num">0<?= $index + 1 ?></span>
                            <h3><?= htmlspecialchars($look['title']) ?></h3>
                            <?php if($layout['text'] !== 'floating-text'): ?>
                                <p><?= htmlspecialchars($look['desc']) ?></p>
                            <?php else: ?>
                                <a href="product_detail.php?slug=<?= rawurlencode((string) $look['slug']) ?>" class="btn-tech mt-4" style="width: fit-content;">Shop The Look <i class="fa-solid fa-arrow-right"></i></a>
                            <?php endif; ?>
                        </div>

                        <?php if($layout['text'] === 'alt-pos'): ?>
                            <div class="lb-image-wrapper <?= vv_e($layout['img']) ?>">
                                <img loading="lazy" decoding="async" src="<?= vv_e($lookbookImageUrl($look['img'])) ?>" class="lb-parallax-img" alt="<?= htmlspecialchars($look['title']) ?>">

                                <?php if($layout['hotspot'] !== 'none'): ?>
                                    <a href="product_detail.php?slug=<?= rawurlencode((string) $look['slug']) ?>" class="lb-hotspot" style="<?= vv_e($layout['hotspot']) ?>">
                                        <div class="hotspot-core"></div>
                                        <div class="hotspot-tooltip"><?= htmlspecialchars($look['title']) ?> - Rs. <?= number_format($look['price']) ?></div>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    </section>

    <section class="lb-grid-chapter">
        <div class="container">
            <div class="d-flex justify-content-between align-items-end mb-5">
                <h2 class="massive-title m-0">The Details.</h2>
                <a href="shop.php" class="text-gold font-monospace text-decoration-none hover-letter-space d-none d-md-block">EXPLORE CATALOG <i class="fa-solid fa-arrow-right ms-2"></i></a>
            </div>

            <div class="lb-masonry">
                <?php foreach($finalDetails as $index => $detailImg): ?>
                    <div class="lb-masonry-item <?= ($index % 2 != 0) ? 'mt-md-5' : '' ?>">
                        <img loading="lazy" decoding="async" src="<?= vv_e($lookbookImageUrl($detailImg)) ?>" alt="Detail Shot <?= $index + 1 ?>">
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-5 text-center d-md-none">
                <a href="shop.php" class="text-gold font-monospace text-decoration-none hover-letter-space">EXPLORE CATALOG <i class="fa-solid fa-arrow-right ms-2"></i></a>
            </div>
        </div>
    </section>

    <section class="lb-outro">
        <div class="container text-center">
            <h2 class="font-heading text-white mb-4" style="font-size: clamp(2.5rem, 5vw, 4rem);">Curate Your Wardrobe.</h2>
            <a href="shop.php" class="btn-pri btn-premium" style="padding: 18px 40px; font-size: 1.1rem;">Enter The Shop</a>
        </div>
    </section>

</main>

<?php include '../ReuseableUI/footer.php'; ?>