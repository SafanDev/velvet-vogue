<?php

function vv_checkout_intent_token(int $userId, int $ttlSeconds = 7200): string
{
    $key = vv_csrf_signing_key();
    if ($userId < 1) {
        throw new RuntimeException('Checkout security is unavailable.');
    }

    $expiresAt = time() + max(300, min($ttlSeconds, 7200));
    $nonce = bin2hex(random_bytes(16));
    $payload = 'velvet-vogue-checkout-v1|' . vv_cookie_scope() . '|' . $userId . '|' . $expiresAt . '|' . $nonce;
    $signature = hash_hmac('sha256', $payload, $key);

    return 'v1.' . $expiresAt . '.' . $nonce . '.' . $signature;
}

function vv_checkout_intent_is_valid(string $token, int $userId): bool
{
    $key = vv_csrf_signing_key();
    $token = strtolower(trim($token));
    if ($userId < 1 || preg_match('/^v1\.([0-9]{10})\.([a-f0-9]{32})\.([a-f0-9]{64})$/', $token, $matches) !== 1) {
        return false;
    }

    $expiresAt = (int) $matches[1];
    $now = time();
    if ($expiresAt < $now - 30 || $expiresAt > $now + 7260) {
        return false;
    }

    $nonce = $matches[2];
    $providedSignature = $matches[3];
    $payload = 'velvet-vogue-checkout-v1|' . vv_cookie_scope() . '|' . $userId . '|' . $expiresAt . '|' . $nonce;
    $expectedSignature = hash_hmac('sha256', $payload, $key);

    return hash_equals($expectedSignature, $providedSignature);
}

function vv_verify_checkout_intent(string $token, int $userId): void
{
    if (!vv_origin_matches_host()) {
        vv_json_response([
            'status' => 'error',
            'code' => 'checkout_origin_invalid',
            'message' => 'The checkout request origin could not be verified. Reload checkout and try again.',
        ], 403);
    }

    if (!vv_checkout_intent_is_valid($token, $userId)) {
        vv_json_response([
            'status' => 'error',
            'code' => 'checkout_intent_invalid',
            'message' => 'Your checkout authorization expired. Reload the checkout page and place the order again.',
        ], 403);
    }
}


function vv_calculate_coupon_discount(array $coupon, float $subtotal): float
{
    if ($subtotal <= 0) {
        return 0.0;
    }

    $value = max(0.0, (float) ($coupon['discountValue'] ?? 0));
    $discount = ($coupon['discountType'] ?? '') === 'percentage'
        ? $subtotal * min($value, 100.0) / 100
        : $value;

    return round(min($subtotal, max(0.0, $discount)), 2);
}

function vv_find_coupon_by_code(PDO $pdo, string $code, bool $lock = false): ?array
{
    $sql = "
        SELECT couponID, code, discountType, discountValue, minOrderValue, maxUses, useCount, isActive, startsAt, expiresAt
        FROM coupon
        WHERE code = ?
          AND isActive = 1
          AND (startsAt IS NULL OR startsAt <= NOW())
          AND (expiresAt IS NULL OR expiresAt >= NOW())
        LIMIT 1
    ";

    if ($lock) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    return $coupon ?: null;
}

function vv_find_coupon_by_id(PDO $pdo, int $couponId, bool $lock = false): ?array
{
    $sql = "
        SELECT couponID, code, discountType, discountValue, minOrderValue, maxUses, useCount, isActive, startsAt, expiresAt
        FROM coupon
        WHERE couponID = ?
          AND isActive = 1
          AND (startsAt IS NULL OR startsAt <= NOW())
          AND (expiresAt IS NULL OR expiresAt >= NOW())
        LIMIT 1
    ";

    if ($lock) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$couponId]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    return $coupon ?: null;
}

function vv_coupon_is_available(array $coupon, float $subtotal): bool
{
    $maxUses = isset($coupon['maxUses']) ? (int) $coupon['maxUses'] : 0;
    $useCount = isset($coupon['useCount']) ? (int) $coupon['useCount'] : 0;
    $minimum = isset($coupon['minOrderValue']) ? (float) $coupon['minOrderValue'] : 0.0;

    return ($maxUses <= 0 || $useCount < $maxUses) && $subtotal >= $minimum;
}

function vv_user_cart_subtotal(PDO $pdo, int $userId): float
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM((COALESCE(p.salePrice, p.basePrice) + pv.additionalPrice) * ci.quantity), 0)
        FROM cartitem ci
        JOIN cart c ON ci.cartID = c.cartID
        JOIN productvariant pv ON ci.variantID = pv.variantID
        JOIN product p ON pv.productID = p.productID
        WHERE c.userID = ?
          AND p.isActive = 1
          AND pv.isActive = 1
    ");
    $stmt->execute([$userId]);
    return round((float) $stmt->fetchColumn(), 2);
}
function vv_merge_guest_cart(PDO $pdo, int $userId): int
{
    $guestCart = $_SESSION['cart'] ?? [];
    if (!is_array($guestCart) || $guestCart === []) {
        return 0;
    }

    $requested = [];
    foreach ($guestCart as $item) {
        $variantId = (int) ($item['variant_id'] ?? 0);
        $quantity = max(1, min(10, (int) ($item['quantity'] ?? 1)));
        if ($variantId > 0) {
            $requested[$variantId] = min(10, ($requested[$variantId] ?? 0) + $quantity);
        }
    }

    if ($requested === []) {
        unset($_SESSION['cart']);
        return 0;
    }

    $pdo->beginTransaction();
    try {
        $placeholders = implode(',', array_fill(0, count($requested), '?'));
        $variantStmt = $pdo->prepare("
            SELECT pv.variantID, pv.stockCount
            FROM productvariant pv
            JOIN product p ON p.productID = pv.productID
            WHERE pv.variantID IN ($placeholders)
              AND pv.isActive = 1
              AND p.isActive = 1
            FOR UPDATE
        ");
        $variantStmt->execute(array_keys($requested));

        $available = [];
        foreach ($variantStmt->fetchAll(PDO::FETCH_ASSOC) as $variant) {
            $available[(int) $variant['variantID']] = max(0, (int) $variant['stockCount']);
        }

        $cartStmt = $pdo->prepare('SELECT cartID FROM cart WHERE userID = ? LIMIT 1 FOR UPDATE');
        $cartStmt->execute([$userId]);
        $cartId = $cartStmt->fetchColumn();
        if (!$cartId) {
            $pdo->prepare('INSERT INTO cart (userID) VALUES (?)')->execute([$userId]);
            $cartId = (int) $pdo->lastInsertId();
        } else {
            $cartId = (int) $cartId;
        }

        $existingStmt = $pdo->prepare('SELECT cartItemID, quantity FROM cartitem WHERE cartID = ? AND variantID = ? LIMIT 1 FOR UPDATE');
        $updateStmt = $pdo->prepare('UPDATE cartitem SET quantity = ? WHERE cartItemID = ?');
        $insertStmt = $pdo->prepare('INSERT INTO cartitem (cartID, variantID, quantity) VALUES (?, ?, ?)');
        $mergedCount = 0;

        foreach ($requested as $variantId => $requestedQuantity) {
            $stock = $available[$variantId] ?? 0;
            if ($stock < 1) {
                continue;
            }

            $existingStmt->execute([$cartId, $variantId]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            $existingQuantity = $existing ? max(0, (int) $existing['quantity']) : 0;
            $newQuantity = min(10, $stock, $existingQuantity + $requestedQuantity);

            if ($newQuantity < 1) {
                continue;
            }

            if ($existing) {
                $updateStmt->execute([$newQuantity, (int) $existing['cartItemID']]);
            } else {
                $insertStmt->execute([$cartId, $variantId, $newQuantity]);
            }
            $mergedCount += $newQuantity;
        }

        $pdo->commit();
        unset($_SESSION['cart'], $_SESSION['applied_coupon']);
        vv_invalidate_nav_counts();
        return $mergedCount;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
function vv_merge_guest_wishlist(PDO $pdo, int $userId): int
{
    $guestWishlist = $_SESSION['wishlist'] ?? [];
    if (!is_array($guestWishlist) || $guestWishlist === []) {
        return 0;
    }

    $productIds = array_values(array_unique(array_filter(
        array_map('intval', array_slice($guestWishlist, 0, 100)),
        static fn (int $productId): bool => $productId > 0,
    )));
    if ($productIds === []) {
        unset($_SESSION['wishlist']);
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $productStmt = $pdo->prepare("SELECT productID FROM product WHERE productID IN ($placeholders) AND isActive = 1");
    $productStmt->execute($productIds);
    $availableProductIds = array_map('intval', $productStmt->fetchAll(PDO::FETCH_COLUMN));

    $checkStmt = $pdo->prepare('SELECT wishlistID FROM wishlist WHERE userID = ? AND productID = ? LIMIT 1');
    $insertStmt = $pdo->prepare('INSERT INTO wishlist (userID, productID) VALUES (?, ?)');
    $merged = 0;

    foreach ($availableProductIds as $productId) {
        $checkStmt->execute([$userId, $productId]);
        if (!$checkStmt->fetchColumn()) {
            try {
                $insertStmt->execute([$userId, $productId]);
            } catch (PDOException $exception) {
                if ((string) $exception->getCode() !== '23000') {
                    throw $exception;
                }
            }
        }
        $merged++;
    }

    unset($_SESSION['wishlist']);
    vv_invalidate_nav_counts();
    return $merged;
}

