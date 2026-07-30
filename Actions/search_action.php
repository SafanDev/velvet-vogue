<?php

declare(strict_types=1);

// Search does not change state and is safe to cache briefly in the browser.
define('VV_SKIP_SESSION_START', true);
define('VV_SKIP_SESSION_SYNC', true);
require_once __DIR__ . '/../Config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}

vv_enforce_rate_limit('product-search-ip', 180, 600);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: public, max-age=20, stale-while-revalidate=40');

$query = trim((string) ($_GET['q'] ?? ''));
$queryLength = function_exists('mb_strlen') ? mb_strlen($query) : strlen($query);
if ($queryLength < 2 || $queryLength > 80) {
    exit;
}

$sql = "
    SELECT
        p.productID, p.productName, p.brand, p.basePrice, p.salePrice, p.slug,
        (SELECT pi.filePath
         FROM productimage pi
         WHERE pi.productID = p.productID AND pi.isPrimary = 1
         ORDER BY pi.sortOrder, pi.imageID
         LIMIT 1) AS searchImg
    FROM product p
    WHERE p.isActive = 1
      AND (p.productName LIKE :q1 ESCAPE '=' OR p.brand LIKE :q2 ESCAPE '=')
    ORDER BY p.viewsCount DESC, p.productID DESC
    LIMIT 6
";

try {
    $stmt = $pdo->prepare($sql);
    $literalQuery = str_replace(['=', '%', '_'], ['==', '=%', '=_'], $query);
    $term = '%' . $literalQuery . '%';
    $stmt->execute([':q1' => $term, ':q2' => $term]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('Product search failed: ' . $exception->getMessage());
    http_response_code(500);
    echo '<div class="col-12 text-center mt-4 text-danger">Search is temporarily unavailable.</div>';
    exit;
}

if ($results === []) {
    echo '<div class="col-12 text-center mt-4"><p style="color:#666;letter-spacing:2px">No artifacts found matching "<span class="gold-text">'
        . vv_e($query)
        . '</span>"</p></div>';
    exit;
}

foreach ($results as $item):
    $displayPrice = $item['salePrice'] !== null ? (float) $item['salePrice'] : (float) $item['basePrice'];
    $image = vv_public_asset_url($item['searchImg'] ?? null);
?>
    <a href="product_detail.php?slug=<?= rawurlencode((string) $item['slug']) ?>" class="search-result-item" style="display:flex;align-items:center;gap:20px;padding:15px;border-bottom:1px solid rgba(255,255,255,.05);text-decoration:none;transition:.3s">
        <div style="width:60px;height:80px;overflow:hidden;background:#111">
            <img src="<?= vv_e($image) ?>" loading="lazy" decoding="async" width="60" height="80" style="width:100%;height:100%;object-fit:cover" alt="<?= vv_e($item['productName']) ?>">
        </div>
        <div>
            <span style="font-size:.6rem;color:#666;letter-spacing:3px;text-transform:uppercase"><?= vv_e($item['brand'] ?: 'Velvet Vogue') ?></span>
            <h4 style="font-family:var(--font-heading);font-size:1.1rem;color:#fff;margin:3px 0"><?= vv_e($item['productName']) ?></h4>
            <span class="gold-text" style="font-size:.8rem">Rs. <?= number_format($displayPrice, 0) ?></span>
        </div>
    </a>
<?php endforeach;
