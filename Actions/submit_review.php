<?php

require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once __DIR__ . '/../Config/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    vv_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

$userId = vv_require_logged_in();
vv_enforce_rate_limit('review-submit-user', 10, 3600, (string) $userId);

$productId = (int) ($_POST['productID'] ?? 0);
$orderItemId = (int) ($_POST['orderItemID'] ?? 0);
$rating = (int) ($_POST['rating'] ?? 0);
$comment = trim((string) ($_POST['comment'] ?? ''));

if ($rating < 1 || $rating > 5 || $productId < 1 || $orderItemId < 1 || (function_exists('mb_strlen') ? mb_strlen($comment) : strlen($comment)) > 2000) {
    vv_json_response(['status' => 'error', 'message' => 'Enter a valid rating and review.'], 422);
}

try {
    $ownershipStmt = $pdo->prepare("
        SELECT oi.orderItemID
        FROM orderitem oi
        JOIN `order` o ON oi.orderID = o.orderID
        JOIN productvariant pv ON oi.variantID = pv.variantID
        JOIN product p ON pv.productID = p.productID
        WHERE oi.orderItemID = ?
          AND o.userID = ?
          AND o.orderStatus = 'delivered'
          AND p.productID = ?
        LIMIT 1
    ");
    $ownershipStmt->execute([$orderItemId, $userId, $productId]);
    if (!$ownershipStmt->fetchColumn()) {
        vv_json_response(['status' => 'error', 'message' => 'This order item is not eligible for review.'], 403);
    }

    $checkStmt = $pdo->prepare('SELECT reviewID FROM review WHERE userID = ? AND orderItemID = ? LIMIT 1');
    $checkStmt->execute([$userId, $orderItemId]);
    if ($checkStmt->fetchColumn()) {
        vv_json_response(['status' => 'error', 'message' => 'You have already reviewed this item.'], 409);
    }

    $stmt = $pdo->prepare('INSERT INTO review (productID, userID, orderItemID, rating, comment, isApproved) VALUES (?, ?, ?, ?, ?, 0)');
    $stmt->execute([$productId, $userId, $orderItemId, $rating, $comment]);

    vv_json_response(['status' => 'success', 'message' => 'Thank you! Your review has been submitted successfully.']);
} catch (PDOException $exception) {
    error_log('Review submission failed: ' . $exception->getMessage());
    vv_json_response(['status' => 'error', 'message' => 'The review could not be submitted.'], 500);
}
