<?php

require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once __DIR__ . '/../Config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'add_look') {
    header('Location: wardrobe.php');
    exit;
}

vv_enforce_rate_limit('wardrobe-cart-ip', 30, 600);
$productIds = json_decode((string) ($_POST['look_data'] ?? '[]'), true);
if (!is_array($productIds)) {
    $productIds = [];
}

$productIds = array_values(array_unique(array_filter(array_map('intval', array_slice($productIds, 0, 12)), static fn (int $id): bool => $id > 0)));
if (!$productIds) {
    $_SESSION['error'] = 'Select at least one available wardrobe item.';
    header('Location: wardrobe.php');
    exit;
}

$placeholders = implode(',', array_fill(0, count($productIds), '?'));

try {
    $variantStmt = $pdo->prepare("
        SELECT p.productID, p.productName, pv.variantID, pv.color, pv.size, pv.stockCount
        FROM product p
        JOIN productvariant pv ON p.productID = pv.productID
        JOIN productimage pi ON p.productID = pi.productID AND pi.isPrimary = 1
        WHERE p.productID IN ($placeholders)
          AND p.isActive = 1
          AND pv.isActive = 1
          AND pv.stockCount > 0
          AND pi.wardrobePNG IS NOT NULL
        ORDER BY p.productID, pv.variantID
    ");
    $variantStmt->execute($productIds);

    $selectedVariants = [];
    foreach ($variantStmt->fetchAll(PDO::FETCH_ASSOC) as $variant) {
        $productId = (int) $variant['productID'];
        $selectedVariants[$productId] ??= $variant;
    }

    if (!$selectedVariants) {
        $_SESSION['error'] = 'The selected products are no longer available.';
        header('Location: wardrobe.php');
        exit;
    }

    if (isset($_SESSION['userID'])) {
        $userId = vv_require_logged_in();
        $pdo->beginTransaction();

        $cartStmt = $pdo->prepare('SELECT cartID FROM cart WHERE userID = ? LIMIT 1 FOR UPDATE');
        $cartStmt->execute([$userId]);
        $cartId = $cartStmt->fetchColumn();
        if (!$cartId) {
            $pdo->prepare('INSERT INTO cart (userID) VALUES (?)')->execute([$userId]);
            $cartId = (int) $pdo->lastInsertId();
        } else {
            $cartId = (int) $cartId;
        }

        $existsStmt = $pdo->prepare('SELECT cartItemID FROM cartitem WHERE cartID = ? AND variantID = ? LIMIT 1');
        $insertStmt = $pdo->prepare('INSERT INTO cartitem (cartID, variantID, quantity) VALUES (?, ?, 1)');

        foreach ($selectedVariants as $variant) {
            $variantId = (int) $variant['variantID'];
            $existsStmt->execute([$cartId, $variantId]);
            if (!$existsStmt->fetchColumn()) {
                $insertStmt->execute([$cartId, $variantId]);
            }
        }

        $pdo->commit();
    } else {
        $_SESSION['cart'] ??= [];
        foreach ($selectedVariants as $variant) {
            $productId = (int) $variant['productID'];
            $color = (string) $variant['color'];
            $size = (string) $variant['size'];
            $key = hash('sha256', $productId . '|' . strtolower($color) . '|' . strtolower($size));
            $_SESSION['cart'][$key] ??= [
                'product_id' => $productId,
                'variant_id' => (int) $variant['variantID'],
                'color' => $color,
                'size' => $size,
                'quantity' => 1,
                'added_at' => time(),
            ];
        }
    }

    unset($_SESSION['applied_coupon']);
    $_SESSION['success'] = 'Curation successfully secured.';
    header('Location: cart.php');
    exit;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Wardrobe cart update failed: ' . $exception->getMessage());
    $_SESSION['error'] = 'The wardrobe selection could not be added.';
    header('Location: wardrobe.php');
    exit;
}
