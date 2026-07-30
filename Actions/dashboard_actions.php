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
$action = (string) ($_POST['action'] ?? '');

if ($action === 'update_identity') {
    vv_enforce_rate_limit('customer-profile-update', 20, 900, (string) $userId);
    $firstName = trim((string) ($_POST['fname'] ?? ''));
    $lastName = trim((string) ($_POST['lname'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $gender = (string) ($_POST['gender'] ?? '');

    if (!vv_valid_name($firstName) || !vv_valid_name($lastName) || strlen($phone) > 40 || !preg_match('/^[0-9+() .-]{0,40}$/', $phone) || !in_array($gender, ['Male', 'Female', 'Other'], true)) {
        vv_json_response(['status' => 'error', 'message' => 'Enter valid profile details.'], 422);
    }

    try {
        $stmt = $pdo->prepare('UPDATE `user` SET firstName = ?, lastName = ?, phoneNo = ?, gender = ? WHERE userID = ?');
        $stmt->execute([$firstName, $lastName, $phone, $gender, $userId]);
        $_SESSION['firstName'] = $firstName;
        $_SESSION['lastName'] = $lastName;
        vv_json_response(['status' => 'success', 'message' => 'Profile updated successfully.']);
    } catch (PDOException $exception) {
        error_log('Profile update failed: ' . $exception->getMessage());
        vv_json_response(['status' => 'error', 'message' => 'The profile could not be updated.'], 500);
    }
}

if ($action === 'change_password') {
    vv_enforce_rate_limit('customer-password-change', 5, 900, (string) $userId);

    $currentPassword = (string) ($_POST['current_pwd'] ?? '');
    $newPassword = (string) ($_POST['new_pwd'] ?? '');
    if (strlen($newPassword) < 10 || strlen($newPassword) > 72 || hash_equals($currentPassword, $newPassword)) {
        vv_json_response(['status' => 'error', 'message' => 'Use a new password between 10 and 72 characters.'], 422);
    }

    $stmt = $pdo->prepare('SELECT password FROM `user` WHERE userID = ? LIMIT 1');
    $stmt->execute([$userId]);
    $currentHash = $stmt->fetchColumn();
    if (!$currentHash || !password_verify($currentPassword, (string) $currentHash)) {
        vv_json_response(['status' => 'error', 'message' => 'Current password is not correct.'], 403);
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    if ($newHash === false) {
        vv_json_response(['status' => 'error', 'message' => 'The password could not be changed.'], 500);
    }

    $pdo->prepare('UPDATE `user` SET password = ? WHERE userID = ?')->execute([$newHash, $userId]);
    vv_clear_remember_cookie();
    session_regenerate_id(true);
    vv_json_response(['status' => 'success', 'message' => 'Password changed successfully.']);
}

if ($action === 'remove_address') {
    vv_enforce_rate_limit('customer-address-update', 30, 900, (string) $userId);
    $addressId = (int) ($_POST['addressID'] ?? 0);
    if ($addressId < 1) {
        vv_json_response(['status' => 'error', 'message' => 'Invalid address.'], 422);
    }

    $stmt = $pdo->prepare('DELETE FROM useraddress WHERE addressID = ? AND userID = ?');
    $stmt->execute([$addressId, $userId]);
    vv_json_response(['status' => 'success', 'message' => 'Address removed.']);
}

if ($action === 'add_address') {
    vv_enforce_rate_limit('customer-address-update', 30, 900, (string) $userId);
    $label = trim((string) ($_POST['label'] ?? ''));
    $recipient = trim((string) ($_POST['name'] ?? ''));
    $street = trim((string) ($_POST['street'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $postalCode = trim((string) ($_POST['zip'] ?? ''));

    if (strlen($label) > 80 || !vv_valid_name($recipient, 120) || $street === '' || strlen($street) > 255 || !vv_valid_name($city, 120) || strlen($postalCode) > 30) {
        vv_json_response(['status' => 'error', 'message' => 'Enter a valid address.'], 422);
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO useraddress (userID, addressLabel, recipientName, street, city, postalCode) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $label, $recipient, $street, $city, $postalCode]);
        vv_json_response(['status' => 'success', 'message' => 'Address added successfully.']);
    } catch (PDOException $exception) {
        error_log('Address creation failed: ' . $exception->getMessage());
        vv_json_response(['status' => 'error', 'message' => 'The address could not be saved.'], 500);
    }
}

vv_json_response(['status' => 'error', 'message' => 'Unknown action.'], 400);
