<?php

require_once __DIR__ . '/../../Config/bootstrap.php';
vv_session_start();
require_once __DIR__ . '/../../Config/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    vv_json_response(['status' => 'error', 'msg' => 'Invalid request method.'], 405);
}

$email = vv_normalize_email((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$identity = vv_client_ip() . '|' . hash('sha256', $email);
vv_enforce_rate_limit('admin-login-ip', 30, 900);
vv_enforce_rate_limit('admin-login', 6, 900, $identity);

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    vv_json_response(['status' => 'error', 'msg' => 'Authentication failed.'], 401);
}

try {
    $stmt = $pdo->prepare('SELECT userID, firstName, lastName, email, password, role, isActive FROM `user` WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $passwordHash = $user ? (string) $user['password'] : '$2y$10$4pDgSnTGgxdSKD3CZ3Qx6eBmq3iHZFZy0CEKSJPfyWJ2uOJn6uS6S';
    $passwordValid = password_verify($password, $passwordHash);
    if (!$user || !$passwordValid) {
        vv_json_response(['status' => 'error', 'msg' => 'Authentication failed.'], 401);
    }

    if ($user['role'] !== 'admin' || (int) $user['isActive'] !== 1) {
        vv_json_response(['status' => 'error', 'msg' => 'Administrator access is not available for this account.'], 403);
    }

    session_regenerate_id(true);
    $_SESSION['userID'] = (int) $user['userID'];
    $_SESSION['firstName'] = (string) $user['firstName'];
    $_SESSION['lastName'] = (string) ($user['lastName'] ?? '');
    $_SESSION['email'] = (string) $user['email'];
    $_SESSION['role'] = 'admin';
    $_SESSION['_regenerated_at'] = time();
    vv_rotate_csrf_token();

    $pdo->prepare('UPDATE `user` SET lastLogin = CURRENT_TIMESTAMP WHERE userID = ?')->execute([(int) $user['userID']]);
    vv_json_response(['status' => 'success', 'msg' => 'Access granted.']);
} catch (PDOException $exception) {
    error_log('Admin login failed: ' . $exception->getMessage());
    vv_json_response(['status' => 'error', 'msg' => 'The service is temporarily unavailable.'], 503);
}
