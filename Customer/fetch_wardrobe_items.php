<?php

require_once __DIR__ . '/../Config/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, max-age=60');

$allowedCategories = ['Tops', 'Bottoms', 'Dresses & Gowns', 'Tailoring & Suiting', 'Outerwear', 'Accessories', 'Footwear', 'Bags'];
$allowedGenders = ['Men', 'Women'];
$category = in_array($_GET['category'] ?? '', $allowedCategories, true) ? (string) $_GET['category'] : 'Tops';
$gender = in_array($_GET['gender'] ?? '', $allowedGenders, true) ? (string) $_GET['gender'] : 'Women';

$isSafeAssetPath = static function (string $path): bool {
    $normalized = str_replace('\\', '/', trim($path));
    return $normalized !== ''
        && !str_contains($normalized, '..')
        && !str_contains($normalized, ':')
        && preg_match('#^[A-Za-z0-9_./ -]+\.(png|jpe?g|webp|gif)$#i', $normalized) === 1;
};

try {
    $stmt = $pdo->prepare("
        SELECT p.productID, p.productName, p.basePrice, pi.wardrobePNG, pi.filePath AS thumbnail
        FROM product p
        JOIN category c ON p.categoryID = c.categoryID
        JOIN productimage pi ON p.productID = pi.productID
        WHERE c.categoryName = :category
          AND (p.gender = :gender OR p.gender = 'Unisex')
          AND pi.wardrobePNG IS NOT NULL
          AND pi.isPrimary = 1
          AND p.isActive = 1
        ORDER BY p.productName ASC
        LIMIT 100
    ");
    $stmt->execute(['category' => $category, 'gender' => $gender]);

    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $png = (string) $item['wardrobePNG'];
        $thumbnail = (string) $item['thumbnail'];
        if (!$isSafeAssetPath($png) || !$isSafeAssetPath($thumbnail)) {
            continue;
        }

        $items[] = [
            'id' => (int) $item['productID'],
            'name' => (string) $item['productName'],
            'price' => round((float) $item['basePrice'], 2),
            'wardrobeImage' => ltrim(str_replace('\\', '/', $png), '/'),
            'thumbnail' => ltrim(str_replace('\\', '/', $thumbnail), '/'),
        ];
    }

    vv_json_response(['status' => 'success', 'category' => $category, 'items' => $items]);
} catch (Throwable $exception) {
    error_log('Wardrobe retrieval failed: ' . $exception->getMessage());
    vv_json_response(['status' => 'error', 'message' => 'Items are temporarily unavailable.'], 500);
}
