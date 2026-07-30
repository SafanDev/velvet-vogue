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

$cartReference = (string) ($_POST['cart_id'] ?? '');
$action = (string) ($_POST['action'] ?? '');
$quantity = (int) ($_POST['quantity'] ?? 1);

if ($cartReference === '' || !in_array($action, ['update', 'remove'], true) || ($action === 'update' && ($quantity < 1 || $quantity > 10))) {
    vv_json_response(['status' => 'error', 'message' => 'Invalid cart request.'], 422);
}

try {
    $totalItems = 0;

    if (isset($_SESSION['userID'])) {
        $userId = vv_require_logged_in();
        $cartItemId = (int) $cartReference;

        $ownershipStmt = $pdo->prepare("
            SELECT ci.cartItemID, ci.variantID, pv.stockCount
            FROM cartitem ci
            JOIN cart c ON ci.cartID = c.cartID
            JOIN productvariant pv ON ci.variantID = pv.variantID
            WHERE ci.cartItemID = ? AND c.userID = ?
            LIMIT 1
        ");
        $ownershipStmt->execute([$cartItemId, $userId]);
        $cartItem = $ownershipStmt->fetch(PDO::FETCH_ASSOC);
        if (!$cartItem) {
            vv_json_response(['status' => 'error', 'message' => 'Cart item not found.'], 404);
        }

        if ($action === 'update') {
            if ((int) $cartItem['stockCount'] < $quantity) {
                vv_json_response(['status' => 'error', 'message' => 'The requested quantity is not available.'], 422);
            }
            $stmt = $pdo->prepare('UPDATE cartitem SET quantity = ? WHERE cartItemID = ?');
            $stmt->execute([$quantity, $cartItemId]);
        } else {
            $stmt = $pdo->prepare('DELETE FROM cartitem WHERE cartItemID = ?');
            $stmt->execute([$cartItemId]);
        }

        $countStmt = $pdo->prepare("
            SELECT COALESCE(SUM(ci.quantity), 0)
            FROM cartitem ci
            JOIN cart c ON ci.cartID = c.cartID
            WHERE c.userID = ?
        ");
        $countStmt->execute([$userId]);
        $totalItems = (int) $countStmt->fetchColumn();
    } else {
        if (!isset($_SESSION['cart'][$cartReference])) {
            vv_json_response(['status' => 'error', 'message' => 'Cart item not found.'], 404);
        }

        if ($action === 'update') {
            $variantId = (int) ($_SESSION['cart'][$cartReference]['variant_id'] ?? 0);
            if ($variantId < 1) {
                vv_json_response(['status' => 'error', 'message' => 'This cart item is no longer available. Remove it and add it again.'], 422);
            }
            $stockStmt = $pdo->prepare('SELECT stockCount FROM productvariant WHERE variantID = ? AND isActive = 1 LIMIT 1');
            $stockStmt->execute([$variantId]);
            $stock = $stockStmt->fetchColumn();
            if ($stock === false || (int) $stock < $quantity) {
                vv_json_response(['status' => 'error', 'message' => 'The requested quantity is not available.'], 422);
            }
            $_SESSION['cart'][$cartReference]['quantity'] = $quantity;
        } else {
            unset($_SESSION['cart'][$cartReference]);
        }

        foreach ($_SESSION['cart'] ?? [] as $item) {
            $totalItems += max(0, (int) ($item['quantity'] ?? 0));
        }
    }

    unset($_SESSION['applied_coupon']);
    vv_invalidate_nav_counts();
    vv_json_response(['status' => 'success', 'cart_count' => $totalItems, 'is_empty' => $totalItems === 0]);
} catch (PDOException $exception) {
    error_log('Cart update failed: ' . $exception->getMessage());
    vv_json_response(['status' => 'error', 'message' => 'The cart could not be updated.'], 500);
}
