<?php

require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
require_once __DIR__ . '/../Config/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');

$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');

if ($action === 'save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        vv_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
    }

    vv_enforce_rate_limit('leaderboard-save-ip', 10, 3600);
    $name = strtoupper(trim((string) ($_POST['player_name'] ?? 'ANONYMOUS')));
    $name = preg_replace('/[^A-Z0-9 _-]/', '', $name) ?: 'ANONYMOUS';
    $name = substr($name, 0, 15);
    $score = (int) ($_POST['score'] ?? 0);

    if ($score < 1 || $score > 1000000) {
        vv_json_response(['status' => 'ignored']);
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO gameleaderboard (playerName, score) VALUES (?, ?)');
        $stmt->execute([$name, $score]);
        vv_json_response(['status' => 'success']);
    } catch (PDOException $exception) {
        error_log('Leaderboard save failed: ' . $exception->getMessage());
        vv_json_response(['status' => 'error', 'message' => 'The score could not be saved.'], 500);
    }
}

if ($action === 'fetch') {
    try {
        $stmt = $pdo->query('SELECT playerName, score FROM gameleaderboard ORDER BY score DESC, achievedAt ASC LIMIT 10');
        vv_json_response(['status' => 'success', 'leaderboard' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $exception) {
        error_log('Leaderboard fetch failed: ' . $exception->getMessage());
        vv_json_response(['status' => 'error'], 500);
    }
}

vv_json_response(['status' => 'invalid_action'], 400);
