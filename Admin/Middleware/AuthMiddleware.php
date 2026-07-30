<?php

require_once __DIR__ . '/../../Config/bootstrap.php';

class AuthMiddleware
{
    public static function requireAdmin(): void
    {
        vv_session_start();
        global $pdo;

        if (!isset($_SESSION['userID']) || !isset($pdo) || !$pdo instanceof PDO) {
            self::deny();
        }

        $stmt = $pdo->prepare('SELECT userID, firstName, lastName, email, role, isActive FROM `user` WHERE userID = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['userID']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || (int) $user['isActive'] !== 1 || $user['role'] !== 'admin') {
            vv_clear_remember_cookie();
            vv_destroy_session();
            self::deny();
        }

        $_SESSION['firstName'] = (string) $user['firstName'];
        $_SESSION['lastName'] = (string) ($user['lastName'] ?? '');
        $_SESSION['email'] = (string) ($user['email'] ?? '');
        $_SESSION['role'] = 'admin';
    }

    private static function deny(): never
    {
        if (vv_request_is_json()) {
            vv_json_response(['status' => 'error', 'message' => 'Administrator authentication is required.'], 401);
        }

        header('Location: login.php');
        exit;
    }
}
