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

$action = (string) ($_POST['action'] ?? '');

if ($action === 'register') {
    vv_enforce_rate_limit('customer-register-ip', 8, 3600);

    $firstName = trim((string) ($_POST['fname'] ?? ''));
    $lastName = trim((string) ($_POST['lname'] ?? ''));
    $email = vv_normalize_email((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!vv_valid_name($firstName) || !vv_valid_name($lastName)) {
        vv_json_response(['status' => 'error', 'message' => 'Enter a valid first and last name.'], 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        vv_json_response(['status' => 'error', 'message' => 'Enter a valid email address.'], 422);
    }

    if (strlen($password) < 10 || strlen($password) > 72) {
        vv_json_response(['status' => 'error', 'message' => 'Use a password between 10 and 72 characters.'], 422);
    }

    vv_enforce_rate_limit('customer-register-email', 4, 3600, hash('sha256', $email));

    $checkStmt = $pdo->prepare('SELECT userID FROM `user` WHERE email = ? LIMIT 1');
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        vv_json_response(['status' => 'error', 'message' => 'This email is already registered.'], 409);
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    if ($hashedPassword === false) {
        vv_json_response(['status' => 'error', 'message' => 'The account could not be created.'], 500);
    }

    try {
        $insertStmt = $pdo->prepare("INSERT INTO `user` (firstName, lastName, email, password, role, isActive) VALUES (?, ?, ?, ?, 'customer', 1)");
        $insertStmt->execute([$firstName, $lastName, $email, $hashedPassword]);
        vv_json_response([
            'status' => 'success',
            'message' => 'Profile initialized. Welcome to the Atelier.',
            'fname' => $firstName,
        ]);
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() === '23000') {
            vv_json_response(['status' => 'error', 'message' => 'This email is already registered.'], 409);
        }
        error_log('Customer registration failed: ' . $exception->getMessage());
        vv_json_response(['status' => 'error', 'message' => 'The account could not be created. Please try again.'], 500);
    }
}

if ($action === 'login') {
    $email = vv_normalize_email((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $identity = vv_client_ip() . '|' . hash('sha256', $email);
    vv_enforce_rate_limit('customer-login-ip', 60, 900);
    vv_enforce_rate_limit('customer-login', 8, 900, $identity);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        vv_json_response(['status' => 'error', 'message' => 'Invalid email or password.'], 401);
    }

    $stmt = $pdo->prepare('SELECT userID, firstName, lastName, email, password, role, isActive FROM `user` WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $passwordHash = $user ? (string) $user['password'] : '$2y$10$4pDgSnTGgxdSKD3CZ3Qx6eBmq3iHZFZy0CEKSJPfyWJ2uOJn6uS6S';
    $passwordValid = password_verify($password, $passwordHash);
    if (!$user || !$passwordValid) {
        vv_json_response(['status' => 'error', 'message' => 'Invalid email or password.'], 401);
    }

    if ((int) $user['isActive'] !== 1) {
        vv_json_response(['status' => 'error', 'message' => 'Your account has been suspended.'], 403);
    }

    if (password_needs_rehash((string) $user['password'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        if ($newHash !== false) {
            $pdo->prepare('UPDATE `user` SET password = ? WHERE userID = ?')->execute([$newHash, (int) $user['userID']]);
            $user['password'] = $newHash;
        }
    }

    session_regenerate_id(true);
    $_SESSION['userID'] = (int) $user['userID'];
    $_SESSION['firstName'] = (string) $user['firstName'];
    $_SESSION['lastName'] = (string) ($user['lastName'] ?? '');
    $_SESSION['email'] = (string) ($user['email'] ?? '');
    $_SESSION['role'] = (string) $user['role'];
    $_SESSION['_regenerated_at'] = time();
    vv_rotate_csrf_token();

    try {
        vv_merge_guest_wishlist($pdo, (int) $user['userID']);
        vv_merge_guest_cart($pdo, (int) $user['userID']);
    } catch (Throwable $exception) {
        error_log('Guest cart merge failed: ' . $exception->getMessage());
        vv_destroy_session();
        vv_json_response(['status' => 'error', 'message' => 'Your account was verified, but the cart could not be restored. Please try again.'], 500);
    }

    if (($_POST['remember'] ?? '') === '1') {
        vv_set_remember_cookie((int) $user['userID'], (string) $user['password']);
    } else {
        vv_clear_remember_cookie();
    }

    $pdo->prepare('UPDATE `user` SET lastLogin = CURRENT_TIMESTAMP WHERE userID = ?')->execute([(int) $user['userID']]);

    vv_json_response([
        'status' => 'success',
        'message' => 'Authentication successful.',
        'fname' => (string) $user['firstName'],
    ]);
}

vv_json_response(['status' => 'error', 'message' => 'Unknown action.'], 400);
