<?php

require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once __DIR__ . '/../Config/db.php';
require_once __DIR__ . '/Middleware/AuthMiddleware.php';

AuthMiddleware::requireAdmin();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    vv_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

$productId = (int) ($_POST['productID'] ?? 0);
if ($productId < 1) {
    vv_json_response(['status' => 'error', 'message' => 'Invalid product.'], 422);
}

try {
    $pdo->beginTransaction();

    $imageStmt = $pdo->prepare('SELECT filePath FROM productimage WHERE productID = ?');
    $imageStmt->execute([$productId]);
    $images = $imageStmt->fetchAll(PDO::FETCH_COLUMN);

    $deleteStmt = $pdo->prepare('DELETE FROM product WHERE productID = ?');
    $deleteStmt->execute([$productId]);
    if ($deleteStmt->rowCount() !== 1) {
        throw new RuntimeException('Product not found.');
    }

    $pdo->commit();

    foreach ($images as $imagePath) {
        vv_delete_public_file((string) $imagePath, __DIR__ . '/image');
    }

    vv_json_response(['status' => 'success']);
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    vv_json_response(['status' => 'error', 'message' => $exception->getMessage()], 404);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Product deletion failed: ' . $exception->getMessage());
    vv_json_response(['status' => 'error', 'message' => 'The product could not be deleted.'], 500);
}
