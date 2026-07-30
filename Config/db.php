<?php

require_once __DIR__ . '/bootstrap.php';

// Every state-changing endpoint uses the same session-independent request guard.
vv_verify_write_request();

$isProduction = vv_is_production();
$host = trim((string) vv_env('DB_HOST', $isProduction ? '' : 'localhost'));
$port = trim((string) vv_env('DB_PORT', '3306'));
$dbname = trim((string) vv_env('DB_NAME', $isProduction ? '' : 'VelvetVogue'));
$username = trim((string) vv_env('DB_USER', $isProduction ? '' : 'root'));
$password = (string) vv_env('DB_PASSWORD', '');
$charset = 'utf8mb4';

if (vv_app_key() === null) {
    error_log('APP_KEY is missing or shorter than 32 characters. Persistent login is disabled.');
    if ($isProduction) {
        vv_fail_request('The application security configuration is incomplete.', 503);
    }
}

if ($host === '' || $dbname === '' || $username === '' || ($isProduction && $password === '')) {
    error_log('One or more required database environment variables are missing.');
    vv_fail_request('The database configuration is incomplete.', 503);
}

if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
    error_log('DB_PORT is invalid.');
    vv_fail_request('The database configuration is invalid.', 503);
}

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_STRINGIFY_FETCHES => false,
    PDO::ATTR_TIMEOUT => 5,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    vv_fail_request('The database is temporarily unavailable.', 503);
}

// Public catalogue endpoints can opt out because they never use account state.
if (!defined('VV_SKIP_SESSION_SYNC') || VV_SKIP_SESSION_SYNC !== true) {
    vv_sync_session_user($pdo);
}
