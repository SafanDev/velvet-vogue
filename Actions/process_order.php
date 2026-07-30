<?php

require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once __DIR__ . '/../Config/db.php';
require_once __DIR__ . '/../Config/commerce.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-VV-Checkout-Security: 6');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    vv_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

$userId = vv_require_logged_in();
vv_verify_checkout_intent((string) ($_POST['checkout_intent'] ?? ''), $userId);
vv_enforce_rate_limit('order-create-user', 8, 600, (string) $userId);

$addressSelection = trim((string) ($_POST['addressID'] ?? ''));
$paymentInput = strtolower(trim((string) ($_POST['paymentMethod'] ?? 'cod')));

if ($paymentInput !== 'cod') {
    vv_json_response(['status' => 'error', 'message' => 'Online card payments are not enabled. Select Cash on Delivery.'], 422);
}

try {
    $pdo->beginTransaction();

    $cartStmt = $pdo->prepare('SELECT cartID FROM cart WHERE userID = ? LIMIT 1 FOR UPDATE');
    $cartStmt->execute([$userId]);
    $cart = $cartStmt->fetch(PDO::FETCH_ASSOC);
    if (!$cart) {
        throw new RuntimeException('Your cart is empty.');
    }

    $cartId = (int) $cart['cartID'];
    $itemStmt = $pdo->prepare("
        SELECT
            ci.quantity,
            pv.variantID, pv.color, pv.size, pv.additionalPrice, pv.stockCount,
            p.productName, p.basePrice, p.salePrice
        FROM cartitem ci
        JOIN productvariant pv ON ci.variantID = pv.variantID
        JOIN product p ON pv.productID = p.productID
        WHERE ci.cartID = ?
          AND p.isActive = 1
          AND pv.isActive = 1
        FOR UPDATE
    ");
    $itemStmt->execute([$cartId]);
    $cartItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$cartItems) {
        throw new RuntimeException('Your cart is empty or contains unavailable products.');
    }
    if (count($cartItems) > 100) {
        throw new RuntimeException('Your cart contains too many items to process at once.');
    }

    $shippingSnapshot = '';
    $addressId = null;

    if ($addressSelection === 'new') {
        $recipientName = trim((string) ($_POST['recipientName'] ?? ''));
        $street = trim((string) ($_POST['street'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        $postalCode = trim((string) ($_POST['postalCode'] ?? ''));

        if (!vv_valid_name($recipientName, 120) || $street === '' || strlen($street) > 255 || !vv_valid_name($city, 120) || strlen($postalCode) > 30) {
            throw new RuntimeException('Enter a valid shipping address.');
        }

        $addressStmt = $pdo->prepare('INSERT INTO useraddress (userID, recipientName, street, city, postalCode) VALUES (?, ?, ?, ?, ?)');
        $addressStmt->execute([$userId, $recipientName, $street, $city, $postalCode]);
        $addressId = (int) $pdo->lastInsertId();
        $shippingSnapshot = implode(', ', array_filter([$recipientName, $street, $city, $postalCode]));
    } else {
        $addressId = filter_var($addressSelection, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($addressId === false) {
            throw new RuntimeException('Select a valid shipping address.');
        }

        $addressStmt = $pdo->prepare('SELECT recipientName, street, city, postalCode, country FROM useraddress WHERE addressID = ? AND userID = ? LIMIT 1');
        $addressStmt->execute([(int) $addressId, $userId]);
        $address = $addressStmt->fetch(PDO::FETCH_ASSOC);
        if (!$address) {
            throw new RuntimeException('The selected shipping address is unavailable.');
        }

        $shippingSnapshot = implode(', ', array_filter([
            $address['recipientName'] ?? '',
            $address['street'] ?? '',
            $address['city'] ?? '',
            $address['postalCode'] ?? '',
            $address['country'] ?? '',
        ]));
    }

    $subtotal = 0.0;
    foreach ($cartItems as $item) {
        $quantity = (int) $item['quantity'];
        $stock = (int) $item['stockCount'];
        if ($quantity < 1 || $quantity > 10 || $stock < $quantity) {
            throw new RuntimeException("{$item['productName']} is unavailable in the selected quantity.");
        }

        $unitPrice = (float) ($item['salePrice'] !== null ? $item['salePrice'] : $item['basePrice']);
        $unitPrice += (float) $item['additionalPrice'];
        $subtotal += $unitPrice * $quantity;
    }
    $subtotal = round($subtotal, 2);

    $discountAmount = 0.0;
    $couponId = null;
    if (!empty($_SESSION['applied_coupon']['couponID'])) {
        $candidateId = (int) $_SESSION['applied_coupon']['couponID'];
        $coupon = vv_find_coupon_by_id($pdo, $candidateId, true);

        if ($coupon && vv_coupon_is_available($coupon, $subtotal)) {
            $couponId = (int) $coupon['couponID'];
            $discountAmount = vv_calculate_coupon_discount($coupon, $subtotal);
        } else {
            unset($_SESSION['applied_coupon']);
        }
    }

    $shippingCost = 0.0;
    $taxAmount = 0.0;
    $totalPaid = round(max(0.0, $subtotal - $discountAmount + $shippingCost + $taxAmount), 2);

    $orderStmt = $pdo->prepare("
        INSERT INTO `order`
            (userID, addressID, couponID, orderNumber, subTotal, discountAmount, shippingCost, taxAmount, totalPaid, shippingAddressSnap, orderStatus)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");

    $orderNumber = '';
    $orderId = 0;
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $orderNumber = 'VV-' . strtoupper(bin2hex(random_bytes(6)));
        try {
            $orderStmt->execute([
                $userId,
                $addressId,
                $couponId,
                $orderNumber,
                $subtotal,
                $discountAmount,
                $shippingCost,
                $taxAmount,
                $totalPaid,
                $shippingSnapshot,
            ]);
            $orderId = (int) $pdo->lastInsertId();
            break;
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() !== '23000' || $attempt === 2) {
                throw $exception;
            }
        }
    }

    if ($orderId < 1) {
        throw new RuntimeException('The order number could not be generated. Please try again.');
    }

    $paymentStmt = $pdo->prepare("INSERT INTO payment (orderID, paymentMethod, paymentStatus) VALUES (?, 'Cash on Delivery', 'pending')");
    $paymentStmt->execute([$orderId]);

    $itemInsertStmt = $pdo->prepare('INSERT INTO orderitem (orderID, variantID, productNameSnap, sizeSnap, colorSnap, quantityBought, unitPrice) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stockUpdateStmt = $pdo->prepare('UPDATE productvariant SET stockCount = stockCount - ? WHERE variantID = ? AND stockCount >= ?');

    foreach ($cartItems as $item) {
        $quantity = (int) $item['quantity'];
        $unitPrice = (float) ($item['salePrice'] !== null ? $item['salePrice'] : $item['basePrice']);
        $unitPrice += (float) $item['additionalPrice'];

        $stockUpdateStmt->execute([$quantity, (int) $item['variantID'], $quantity]);
        if ($stockUpdateStmt->rowCount() !== 1) {
            throw new RuntimeException("{$item['productName']} went out of stock. Refresh your cart and try again.");
        }

        $itemInsertStmt->execute([
            $orderId,
            (int) $item['variantID'],
            (string) $item['productName'],
            (string) $item['size'],
            (string) $item['color'],
            $quantity,
            round($unitPrice, 2),
        ]);
    }

    if ($couponId !== null) {
        $couponUpdateStmt = $pdo->prepare('UPDATE coupon SET useCount = useCount + 1 WHERE couponID = ? AND (maxUses IS NULL OR maxUses = 0 OR useCount < maxUses)');
        $couponUpdateStmt->execute([$couponId]);
        if ($couponUpdateStmt->rowCount() !== 1) {
            throw new RuntimeException('The promo code reached its usage limit.');
        }
    }

    $pdo->prepare('DELETE FROM cartitem WHERE cartID = ?')->execute([$cartId]);
    unset($_SESSION['applied_coupon']);
    vv_invalidate_nav_counts();

    $pdo->commit();
    vv_json_response(['status' => 'success', 'orderNumber' => $orderNumber]);
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    vv_json_response(['status' => 'error', 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Order processing failed: ' . $exception->getMessage());
    vv_json_response(['status' => 'error', 'message' => 'The order could not be completed. Please try again.'], 500);
}
