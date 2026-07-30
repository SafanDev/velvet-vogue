<?php

require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once __DIR__ . '/../Config/db.php';
require_once __DIR__ . '/../Config/commerce.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    vv_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

if (($_POST['generate_404'] ?? '') === 'true') {
    $userId = vv_require_logged_in();

    if (!empty($_SESSION['_game_coupon_generated'])) {
        $existingCode = strtoupper(trim((string) $_SESSION['_game_coupon_generated']));
        $existingCoupon = vv_find_coupon_by_code($pdo, $existingCode);
        if ($existingCoupon && vv_coupon_is_available($existingCoupon, 0.0)) {
            vv_json_response([
                'status' => 'success',
                'code' => $existingCode,
                'message' => 'Your reward code is ready.',
            ]);
        }

        unset($_SESSION['_game_coupon_generated']);
    }

    vv_enforce_rate_limit('coupon-game-ip', 3, 86400);
    vv_enforce_rate_limit('coupon-game-user', 1, 86400, (string) $userId);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $couponCode = 'VV-404-' . strtoupper(bin2hex(random_bytes(3)));

        try {
            $stmt = $pdo->prepare("INSERT INTO coupon (code, discountType, discountValue, minOrderValue, isActive, maxUses, useCount, startsAt, expiresAt) VALUES (?, 'percentage', 15.00, 0, 1, 1, 0, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))");
            $stmt->execute([$couponCode]);
            $_SESSION['_game_coupon_generated'] = $couponCode;
            vv_json_response(['status' => 'success', 'code' => $couponCode, 'message' => 'Reward code generated.']);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                error_log('Coupon generation failed: ' . $exception->getMessage());
                break;
            }
        }
    }

    vv_json_response(['status' => 'error', 'message' => 'The reward code could not be generated.'], 500);
}

$userId = vv_require_logged_in();
vv_enforce_rate_limit('coupon-apply-user', 20, 600, (string) $userId);

$code = strtoupper(trim((string) ($_POST['promo_code'] ?? '')));
if ($code === '' || strlen($code) > 50 || !preg_match('/^[A-Z0-9-]+$/', $code)) {
    vv_json_response(['status' => 'error', 'message' => 'Enter a valid promo code.'], 422);
}

try {
    $subtotal = vv_user_cart_subtotal($pdo, $userId);
    if ($subtotal <= 0) {
        vv_json_response(['status' => 'error', 'message' => 'Your cart is empty.'], 422);
    }

    $coupon = vv_find_coupon_by_code($pdo, $code);
    if (!$coupon || !vv_coupon_is_available($coupon, $subtotal)) {
        vv_json_response(['status' => 'error', 'message' => 'This promo code is invalid, expired, or unavailable.'], 422);
    }

    $discount = vv_calculate_coupon_discount($coupon, $subtotal);
    $_SESSION['applied_coupon'] = [
        'couponID' => (int) $coupon['couponID'],
        'code' => (string) $coupon['code'],
    ];

    vv_json_response([
        'status' => 'success',
        'message' => 'Promo code applied.',
        'discount_amount' => $discount,
        'new_total' => round($subtotal - $discount, 2),
        'code' => (string) $coupon['code'],
    ]);
} catch (PDOException $exception) {
    error_log('Coupon application failed: ' . $exception->getMessage());
    vv_json_response(['status' => 'error', 'message' => 'The promo code could not be applied.'], 500);
}
