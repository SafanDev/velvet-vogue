<?php

require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    vv_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

$token = vv_csrf_token();
header('X-CSRF-Token: ' . $token);
header('X-VV-Request-Security: ' . VV_REQUEST_SECURITY_VERSION);
header('Access-Control-Expose-Headers: X-CSRF-Token, X-VV-Request-Security');
vv_json_response(['status' => 'success', 'token' => $token]);
