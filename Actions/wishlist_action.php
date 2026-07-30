<?php

require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once __DIR__ . '/../Config/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    vv_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

vv_enforce_rate_limit('wishlist-toggle-ip', 120, 600);

$productId = (int) ($_POST['id'] ?? 0);
if ($productId < 1) {
    vv_json_response(['status' => 'error', 'message' => 'Invalid product.'], 422);
}

$productStmt = $pdo->prepare('SELECT productID FROM product WHERE productID = ? AND isActive = 1 LIMIT 1');
$productStmt->execute([$productId]);
if (!$productStmt->fetchColumn()) {
    vv_json_response(['status' => 'error', 'message' => 'Product not found.'], 404);
}

try {
    if (isset($_SESSION['userID'])) {
        $userId = vv_require_logged_in();
        vv_enforce_rate_limit('wishlist-toggle-user', 120, 600, (string) $userId);
        $checkStmt = $pdo->prepare('SELECT wishlistID FROM wishlist WHERE userID = ? AND productID = ? LIMIT 1');
        $checkStmt->execute([$userId, $productId]);
        $wishlistId = $checkStmt->fetchColumn();

        if ($wishlistId) {
            $pdo->prepare('DELETE FROM wishlist WHERE wishlistID = ? AND userID = ?')->execute([(int) $wishlistId, $userId]);
            $state = 'removed';
        } else {
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM wishlist WHERE userID = ?');
            $countStmt->execute([$userId]);
            if ((int) $countStmt->fetchColumn() >= 100) {
                vv_json_response(['status' => 'error', 'message' => 'Your wishlist has reached its item limit.'], 409);
            }

            $pdo->prepare('INSERT INTO wishlist (userID, productID) VALUES (?, ?)')->execute([$userId, $productId]);
            $state = 'added';
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM wishlist WHERE userID = ?');
        $countStmt->execute([$userId]);
        $count = (int) $countStmt->fetchColumn();
    } else {
        $_SESSION['wishlist'] ??= [];
        $items = array_values(array_unique(array_map('intval', $_SESSION['wishlist'])));
        $position = array_search($productId, $items, true);

        if ($position !== false) {
            unset($items[$position]);
            $items = array_values($items);
            $state = 'removed';
        } else {
            if (count($items) >= 100) {
                vv_json_response(['status' => 'error', 'message' => 'Your wishlist has reached its item limit.'], 409);
            }
            $items[] = $productId;
            $state = 'added';
        }

        $_SESSION['wishlist'] = $items;
        $count = count($items);
    }

    vv_invalidate_nav_counts();
    vv_json_response(['status' => 'success', 'state' => $state, 'count' => $count]);
} catch (PDOException $exception) {
    error_log('Wishlist update failed: ' . $exception->getMessage());
    vv_json_response(['status' => 'error', 'message' => 'The wishlist could not be updated.'], 500);
}
