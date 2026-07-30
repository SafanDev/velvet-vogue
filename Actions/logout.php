<?php

require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    vv_fail_request('Invalid request method.', 405);
}

vv_verify_write_request();
vv_clear_remember_cookie();
vv_destroy_session();
header('Location: ../Customer/home.php');
exit;
