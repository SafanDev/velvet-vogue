<?php

require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once __DIR__ . '/../Config/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    vv_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

vv_enforce_rate_limit('public-inquiry-ip', 5, 3600);

$name = trim((string) ($_POST['name'] ?? ''));
$email = vv_normalize_email((string) ($_POST['email'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
$userId = isset($_SESSION['userID']) ? vv_require_logged_in() : null;

if (!vv_valid_name($name, 120) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    vv_json_response(['status' => 'error', 'message' => 'Enter a valid name and email address.'], 422);
}

if ($message === '' || strlen($message) > 5000 || strlen($subject) > 180) {
    vv_json_response(['status' => 'error', 'message' => 'The subject or message is invalid.'], 422);
}

try {
    $stmt = $pdo->prepare("INSERT INTO inquiry (userID, senderName, senderEmail, subject, inquiryMessage, inquiryStatus) VALUES (?, ?, ?, ?, ?, 'open')");
    $stmt->execute([$userId, $name, $email, $subject, $message]);

    vv_json_response([
        'status' => 'success',
        'message' => 'Your dossier has been received. Our team will contact you shortly.',
        'fname' => explode(' ', $name)[0],
    ]);
} catch (PDOException $exception) {
    error_log('Inquiry submission failed: ' . $exception->getMessage());
    vv_json_response(['status' => 'error', 'message' => 'Your message could not be sent. Please try again.'], 500);
}
