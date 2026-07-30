<?php

require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
header('X-VV-Cart-Security: 5');
require_once __DIR__ . '/../Config/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    vv_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

vv_enforce_rate_limit('cart-add-ip', 60, 600);

$productId = (int) ($_POST['product_id'] ?? 0);
$color = trim((string) ($_POST['color'] ?? ''));
$size = trim((string) ($_POST['size'] ?? ''));
$quantity = (int) ($_POST['quantity'] ?? 1);

if ($productId < 1 || $color === '' || strlen($color) > 80 || $size === '' || strlen($size) > 40 || $quantity < 1 || $quantity > 10) {
    vv_json_response(['status' => 'error', 'message' => 'Invalid product details.'], 422);
}

try {
    $variantStmt = $pdo->prepare("
        SELECT pv.variantID, pv.stockCount
        FROM productvariant pv
        JOIN product p ON pv.productID = p.productID
        WHERE pv.productID = ?
          AND LOWER(pv.color) = LOWER(?)
          AND LOWER(pv.size) = LOWER(?)
          AND pv.isActive = 1
          AND p.isActive = 1
        LIMIT 1
    ");
    $variantStmt->execute([$productId, $color, $size]);
    $variant = $variantStmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant || (int) $variant['stockCount'] < $quantity) {
        vv_json_response(['status' => 'error', 'message' => 'The selected item is unavailable.'], 422);
    }

    if (isset($_SESSION['userID'])) {
        $userId = vv_require_logged_in();
        $pdo->beginTransaction();

        $cartStmt = $pdo->prepare('SELECT cartID FROM cart WHERE userID = ? LIMIT 1 FOR UPDATE');
        $cartStmt->execute([$userId]);
        $cartId = $cartStmt->fetchColumn();

        if (!$cartId) {
            $createStmt = $pdo->prepare('INSERT INTO cart (userID) VALUES (?)');
            $createStmt->execute([$userId]);
            $cartId = (int) $pdo->lastInsertId();
        } else {
            $cartId = (int) $cartId;
        }

        $existingStmt = $pdo->prepare('SELECT cartItemID, quantity FROM cartitem WHERE cartID = ? AND variantID = ? LIMIT 1 FOR UPDATE');
        $existingStmt->execute([$cartId, (int) $variant['variantID']]);
        $existingItem = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingItem) {
            $updatedQuantity = (int) $existingItem['quantity'] + $quantity;
            if ($updatedQuantity > min(10, (int) $variant['stockCount'])) {
                $pdo->rollBack();
                vv_json_response(['status' => 'error', 'message' => 'The requested quantity exceeds the available cart limit.'], 422);
            }

            $updateStmt = $pdo->prepare('UPDATE cartitem SET quantity = ? WHERE cartItemID = ?');
            $updateStmt->execute([$updatedQuantity, (int) $existingItem['cartItemID']]);
        } else {
            $itemCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cartitem WHERE cartID = ?');
            $itemCountStmt->execute([$cartId]);
            if ((int) $itemCountStmt->fetchColumn() >= 100) {
                $pdo->rollBack();
                vv_json_response(['status' => 'error', 'message' => 'Your cart has reached its item limit.'], 409);
            }

            $insertStmt = $pdo->prepare('INSERT INTO cartitem (cartID, variantID, quantity) VALUES (?, ?, ?)');
            $insertStmt->execute([$cartId, (int) $variant['variantID'], $quantity]);
        }

        $countStmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cartitem WHERE cartID = ?');
        $countStmt->execute([$cartId]);
        $totalCount = (int) $countStmt->fetchColumn();
        $pdo->commit();
        vv_invalidate_nav_counts();

        vv_json_response(['status' => 'success', 'message' => 'Item added.', 'cart_count' => $totalCount]);
    }

    $_SESSION['cart'] ??= [];
    $cartKey = hash('sha256', $productId . '|' . strtolower($color) . '|' . strtolower($size));
    if (isset($_SESSION['cart'][$cartKey])) {
        $updatedQuantity = (int) ($_SESSION['cart'][$cartKey]['quantity'] ?? 0) + $quantity;
        if ($updatedQuantity > min(10, (int) $variant['stockCount'])) {
            vv_json_response(['status' => 'error', 'message' => 'The requested quantity exceeds the available cart limit.'], 422);
        }
        $_SESSION['cart'][$cartKey]['quantity'] = $updatedQuantity;
        $_SESSION['cart'][$cartKey]['added_at'] = time();
    } else {
        if (count($_SESSION['cart']) >= 100) {
            vv_json_response(['status' => 'error', 'message' => 'Your cart has reached its item limit.'], 409);
        }

        $_SESSION['cart'][$cartKey] = [
            'product_id' => $productId,
            'variant_id' => (int) $variant['variantID'],
            'color' => $color,
            'size' => $size,
            'quantity' => $quantity,
            'added_at' => time(),
        ];
    }

    $totalCount = array_sum(array_map(static fn (array $item): int => (int) ($item['quantity'] ?? 0), $_SESSION['cart']));
    vv_invalidate_nav_counts();
    vv_json_response(['status' => 'success', 'message' => 'Item added.', 'cart_count' => $totalCount]);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Add to cart failed: ' . $exception->getMessage());
    vv_json_response(['status' => 'error', 'message' => 'The item could not be added.'], 500);
}
