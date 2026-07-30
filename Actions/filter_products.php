<?php

declare(strict_types=1);

// Product filtering is read-only, so it uses a cacheable GET request rather than CSRF-protected POST.
define('VV_SKIP_SESSION_START', true);
define('VV_SKIP_SESSION_SYNC', true);
require_once __DIR__ . '/../Config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}

vv_enforce_rate_limit('product-filter-ip', 240, 600);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: public, max-age=20, stale-while-revalidate=40');

$category = trim((string) ($_GET['category'] ?? 'All'));
$sort = (string) ($_GET['sort'] ?? 'newest');
$maxPrice = max(0, min(10000000, (int) ($_GET['max_price'] ?? 100000)));
$genders = json_decode((string) ($_GET['genders'] ?? '[]'), true);
$sizes = json_decode((string) ($_GET['sizes'] ?? '[]'), true);
$colors = json_decode((string) ($_GET['colors'] ?? '[]'), true);

$cleanList = static function (mixed $values, int $maxItems, int $maxLength): array {
    if (!is_array($values)) {
        return [];
    }

    $result = [];
    foreach (array_slice($values, 0, $maxItems) as $value) {
        if (!is_string($value)) {
            continue;
        }

        $value = trim($value);
        if ($value !== '' && strlen($value) <= $maxLength) {
            $result[] = $value;
        }
    }

    return array_values(array_unique($result));
};

$genders = array_values(array_intersect($cleanList($genders, 3, 20), ['Men', 'Women', 'Unisex']));
$sizes = $cleanList($sizes, 20, 20);
$colors = $cleanList($colors, 20, 40);
$category = strlen($category) <= 120 ? $category : 'All';
$sort = in_array($sort, ['newest', 'price_asc', 'price_desc'], true) ? $sort : 'newest';
$page = max(1, min(1000, (int) ($_GET['page'] ?? 1)));
$limit = 12;
$offset = ($page - 1) * $limit;

$sql = "
    SELECT
        p.productID, p.brand, p.productName, p.basePrice, p.salePrice,
        p.isNewArrival, p.categoryID, p.slug, c.categoryName,
        (SELECT pi.filePath
         FROM productimage pi
         WHERE pi.productID = p.productID AND pi.isPrimary = 1
         ORDER BY pi.sortOrder, pi.imageID
         LIMIT 1) AS mainImg,
        (SELECT pi.filePath
         FROM productimage pi
         WHERE pi.productID = p.productID AND pi.sortOrder = 2
         ORDER BY pi.imageID
         LIMIT 1) AS hoverImg
    FROM product p
    LEFT JOIN category c ON p.categoryID = c.categoryID
    WHERE p.isActive = 1
      AND COALESCE(p.salePrice, p.basePrice) <= :maxPrice
";

$params = [':maxPrice' => $maxPrice];

if ($genders !== []) {
    $holders = [];
    foreach ($genders as $index => $gender) {
        $key = ':gender' . $index;
        $holders[] = $key;
        $params[$key] = $gender;
    }
    $sql .= ' AND p.gender IN (' . implode(',', $holders) . ')';
}

if ($category !== 'All') {
    $sql .= ' AND c.categoryName = :category';
    $params[':category'] = $category;
}

if ($sizes !== [] || $colors !== []) {
    $sql .= ' AND EXISTS (SELECT 1 FROM productvariant pvf WHERE pvf.productID = p.productID AND pvf.isActive = 1';

    if ($sizes !== []) {
        $holders = [];
        foreach ($sizes as $index => $size) {
            $key = ':size' . $index;
            $holders[] = $key;
            $params[$key] = $size;
        }
        $sql .= ' AND pvf.size IN (' . implode(',', $holders) . ')';
    }

    if ($colors !== []) {
        $holders = [];
        foreach ($colors as $index => $color) {
            $key = ':color' . $index;
            $holders[] = $key;
            $params[$key] = $color;
        }
        $sql .= ' AND pvf.color IN (' . implode(',', $holders) . ')';
    }

    $sql .= ')';
}

$sql .= match ($sort) {
    'price_asc' => ' ORDER BY COALESCE(p.salePrice, p.basePrice) ASC, p.productID DESC',
    'price_desc' => ' ORDER BY COALESCE(p.salePrice, p.basePrice) DESC, p.productID DESC',
    default => ' ORDER BY p.createdAt DESC, p.productID DESC',
};
$sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('Product filtering failed: ' . $exception->getMessage());
    http_response_code(500);
    echo '<div class="col-12 text-center py-5 text-danger">Products are temporarily unavailable.</div>';
    exit;
}

if ($products === []) {
    if ($page === 1) {
        echo '<div class="col-12 text-center py-5 w-100" style="grid-column:1/-1">'
            . '<h4 class="gold-text" style="font-family:var(--font-heading);letter-spacing:3px">THE ARCHIVE IS EMPTY</h4>'
            . '<p style="color:#666;font-family:var(--font-body);letter-spacing:1px">No pieces currently match your criteria.</p>'
            . '</div>';
    }
    exit;
}

$productIds = array_map(static fn (array $product): int => (int) $product['productID'], $products);
$variantMap = [];
$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$variantStmt = $pdo->prepare("
    SELECT pv.productID, pv.color, pv.size, pv.stockCount, pi.filePath AS imagePath
    FROM productvariant pv
    LEFT JOIN productimage pi
      ON pi.imageID = (
          SELECT pi2.imageID
          FROM productimage pi2
          WHERE pi2.productID = pv.productID AND pi2.color = pv.color
          ORDER BY pi2.isPrimary DESC, pi2.sortOrder, pi2.imageID
          LIMIT 1
      )
    WHERE pv.isActive = 1 AND pv.stockCount > 0 AND pv.productID IN ($placeholders)
    ORDER BY pv.productID, pv.variantID
");
$variantStmt->execute($productIds);

foreach ($variantStmt->fetchAll(PDO::FETCH_ASSOC) as $variant) {
    $productId = (int) $variant['productID'];
    $variantMap[$productId] ??= ['colors' => [], 'sizes' => [], 'combinations' => []];

    $color = trim((string) ($variant['color'] ?? ''));
    $size = trim((string) ($variant['size'] ?? ''));
    if ($color !== '' && !array_key_exists($color, $variantMap[$productId]['colors'])) {
        $variantMap[$productId]['colors'][$color] = vv_public_asset_url($variant['imagePath'] ?? null, '');
    }
    if ($size !== '' && !in_array($size, $variantMap[$productId]['sizes'], true)) {
        $variantMap[$productId]['sizes'][] = $size;
    }
    if ($color !== '' && $size !== '') {
        $variantMap[$productId]['combinations'][$color] ??= [];
        $variantMap[$productId]['combinations'][$color][] = [
            'size' => $size,
            'stock' => max(0, (int) ($variant['stockCount'] ?? 0)),
        ];
    }
}

$counter = 0;
foreach ($products as $product):
    $counter++;
    $productId = (int) $product['productID'];
    $mainImage = vv_public_asset_url($product['mainImg'] ?? null);
    $hoverImage = vv_public_asset_url($product['hoverImg'] ?? null);
    $variants = $variantMap[$productId] ?? ['colors' => [], 'sizes' => [], 'combinations' => []];
    $productNameArg = vv_e(json_encode((string) $product['productName'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR));
    $priceArg = json_encode((float) ($product['salePrice'] !== null ? $product['salePrice'] : $product['basePrice']), JSON_THROW_ON_ERROR);
    $imageArg = vv_e(json_encode($mainImage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR));
    $variantsArg = vv_e(json_encode(json_encode($variants, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR));
    if ($page === 1 && $counter === 7): ?>
        <div class="grid-promo-fullwidth gsap-reveal">
            <div class="promo-fw-bg" style="background-image:url('https://images.unsplash.com/photo-1550614000-4b95dd2449bb?q=80&w=2000&auto=format&fit=crop')"></div>
            <div class="promo-fw-overlay"></div>
            <div class="promo-fw-content d-flex flex-column flex-md-row justify-content-between align-items-center w-100">
                <div class="mb-3 mb-md-0 text-center text-md-start">
                    <span class="promo-fw-tag">THE EDITORIAL</span>
                    <h3 class="promo-fw-title">ONYX SYNDICATE</h3>
                </div>
                <a href="about.php" class="btn-vogue-mask" style="padding:12px 30px">
                    <span class="btn-text">DISCOVER CAMPAIGN</span>
                    <span class="btn-mask" aria-hidden="true">DISCOVER CAMPAIGN</span>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <article class="p-card-unique gsap-reveal" data-id="<?= $productId ?>">
        <div class="p-img-wrap">
            <?php if ((int) $product['isNewArrival'] === 1): ?><span class="p-badge">NEW</span><?php endif; ?>

            <button class="action-icon ai-wish p-wishlist-hover" onclick="toggleWish(this, <?= $productId ?>)" aria-label="Add <?= vv_e($product['productName']) ?> to wishlist">
                <i class="fa-regular fa-heart"></i>
            </button>

            <a href="product_detail.php?slug=<?= rawurlencode((string) $product['slug']) ?>" class="img-zoom-container d-block">
                <img src="<?= vv_e($mainImage) ?>" loading="lazy" decoding="async" class="img-primary" alt="<?= vv_e($product['productName']) ?>">
                <img src="<?= vv_e($hoverImage) ?>" loading="lazy" decoding="async" class="img-hover" alt="<?= vv_e($product['productName']) ?> alternate view">
            </a>

            <div class="p-card-actions">
                <button class="btn-add-unique btn-add-magnetic" onclick="openAddToCartModal(<?= $productId ?>, <?= $productNameArg ?>, <?= $priceArg ?>, <?= $imageArg ?>, <?= $variantsArg ?>)">ADD TO CART</button>
            </div>
        </div>
        <div class="p-details-unique">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div class="pe-2">
                    <span class="p-brand"><?= vv_e($product['brand'] ?? 'VELVET VOGUE') ?></span>
                    <a href="product_detail.php?slug=<?= rawurlencode((string) $product['slug']) ?>" style="text-decoration:none">
                        <h3 class="p-name" style="transition:color .3s" onmouseover="this.style.color='var(--color-gold-metallic)'" onmouseout="this.style.color='#fff'"><?= vv_e($product['productName']) ?></h3>
                    </a>
                </div>
                <div class="p-pricing text-end mt-1 mt-sm-0">
                    <?php if ($product['salePrice'] !== null): ?>
                        <del>Rs. <?= number_format((float) $product['basePrice'], 0) ?></del><br>
                        <span class="gold-text">Rs. <?= number_format((float) $product['salePrice'], 0) ?></span>
                    <?php else: ?>
                        <span>Rs. <?= number_format((float) $product['basePrice'], 0) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </article>
<?php endforeach;
